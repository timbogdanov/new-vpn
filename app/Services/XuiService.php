<?php

namespace App\Services;

use App\DTO\VpnClientDTO;
use App\DTO\TrafficDataDTO;
use App\Exceptions\VpnPanel\AuthenticationException;
use App\Exceptions\VpnPanel\ClientCreationException;
use App\Exceptions\VpnPanel\ApiConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class XuiService
{
    private string $host;
    private string $port;
    private string $path;
    private string $username;
    private string $password;
    private int $inboundId;
    private int $sessionCacheTtl = 3600; // 1 hour

    public function __construct()
    {
        $this->host = config('services.3x_ui.host');
        $this->port = config('services.3x_ui.port');
        $this->path = config('services.3x_ui.path', '');
        $this->username = config('services.3x_ui.username');
        $this->password = config('services.3x_ui.password');
        $this->inboundId = (int) config('services.3x_ui.inbound_id', 1);
    }

    /**
     * Get or create VPN client by Telegram ID
     */
    public function getOrCreateClient(int $telegramId, ?string $firstName = null, ?string $lastName = null): VpnClientDTO
    {
        $existing = $this->getClientByTelegramId($telegramId);

        if ($existing) {
            return $existing;
        }

        return $this->createClient($telegramId, $firstName, $lastName);
    }

    /**
     * Get client by Telegram ID
     */
    public function getClientByTelegramId(int $telegramId): ?VpnClientDTO
    {
        $clients = $this->getAllClients();

        foreach ($clients as $client) {
            if ($client->telegramId === $telegramId) {
                return $client;
            }
        }

        return null;
    }

    /**
     * Generate client email from Telegram name and UUID
     */
    private function generateClientEmail(string $uuid, ?string $firstName = null, ?string $lastName = null): string
    {
        $shortUuid = substr($uuid, 0, 8);

        if ($firstName) {
            $name = trim($firstName . ($lastName ? ' ' . $lastName : ''));
            // Sanitize: keep letters, numbers, spaces, and common characters
            $name = preg_replace('/[^\p{L}\p{N}\s\-_.]/u', '', $name);
            $name = trim($name);

            if (!empty($name)) {
                return $name . '-' . $shortUuid;
            }
        }

        // Fallback if no valid name
        return 'user-' . $shortUuid;
    }

    /**
     * Create a new VPN client
     */
    public function createClient(int $telegramId, ?string $firstName = null, ?string $lastName = null): VpnClientDTO
    {
        $uuid = (string) Str::uuid();
        $subId = Str::random(16);
        $clientEmail = $this->generateClientEmail($uuid, $firstName, $lastName);

        $payload = [
            'id' => $this->inboundId,
            'settings' => json_encode([
                'clients' => [[
                    'id' => $uuid,
                    'flow' => 'xtls-rprx-vision',
                    'email' => $clientEmail,
                    'tgId' => (string) $telegramId,
                    'limitIp' => config('vpn.default_device_limit', 2),
                    'totalGB' => config('vpn.default_traffic_limit', 0), // 0 = unlimited
                    'expiryTime' => 0, // No expiry
                    'enable' => true,
                    'subId' => $subId,
                    'reset' => 0,
                ]]
            ])
        ];

        $response = $this->makeRequest('POST', 'panel/api/inbounds/addClient', $payload);

        if (!($response['success'] ?? false)) {
            Log::error('XUI: Failed to create client', [
                'telegramId' => $telegramId,
                'response' => $response
            ]);
            throw new ClientCreationException(
                'Failed to create VPN client: ' . ($response['msg'] ?? 'Unknown error')
            );
        }

        Log::info('XUI: Created client', ['telegramId' => $telegramId, 'email' => $clientEmail]);

        return new VpnClientDTO(
            uuid: $uuid,
            email: $clientEmail,
            telegramId: $telegramId,
            subId: $subId,
            enabled: true,
            expiryTime: 0,
            totalGB: 0
        );
    }

    /**
     * Get traffic data for a client
     */
    public function getClientTraffic(string $email): TrafficDataDTO
    {
        $response = $this->makeRequest('GET', "panel/api/inbounds/getClientTraffics/{$email}");

        if (!($response['success'] ?? false) || empty($response['obj'])) {
            return new TrafficDataDTO(0, 0, 0);
        }

        $data = $response['obj'];

        return new TrafficDataDTO(
            upload: $data['up'] ?? 0,
            download: $data['down'] ?? 0,
            expiryTime: $data['expiryTime'] ?? 0
        );
    }

    /**
     * Update a client's email/name in XUI
     */
    public function updateClient(string $currentEmail, string $newEmail): bool
    {
        $payload = [
            'id' => $this->inboundId,
            'settings' => json_encode([
                'clients' => [[
                    'email' => $currentEmail,
                ]]
            ])
        ];

        // First get the client to preserve all settings
        $clients = $this->getAllClients();
        $client = null;
        foreach ($clients as $c) {
            if ($c->email === $currentEmail) {
                $client = $c;
                break;
            }
        }

        if (!$client) {
            Log::warning('XUI: Client not found for update', ['email' => $currentEmail]);
            return false;
        }

        // Build update payload with new email
        $payload = [
            'id' => $this->inboundId,
            'settings' => json_encode([
                'clients' => [[
                    'id' => $client->uuid,
                    'flow' => 'xtls-rprx-vision',
                    'email' => $newEmail,
                    'tgId' => (string) $client->telegramId,
                    'limitIp' => config('vpn.default_device_limit', 2),
                    'totalGB' => $client->totalGB,
                    'expiryTime' => $client->expiryTime,
                    'enable' => $client->enabled,
                    'subId' => $client->subId,
                    'reset' => 0,
                ]]
            ])
        ];

        $response = $this->makeRequest('POST', "panel/api/inbounds/updateClient/{$client->uuid}", $payload);

        if (!($response['success'] ?? false)) {
            Log::error('XUI: Failed to update client', [
                'currentEmail' => $currentEmail,
                'newEmail' => $newEmail,
                'response' => $response
            ]);
            return false;
        }

        Log::info('XUI: Updated client name', ['from' => $currentEmail, 'to' => $newEmail]);
        return true;
    }

    /**
     * Get all clients from inbound
     */
    public function getAllClients(): array
    {
        $response = $this->makeRequest('GET', 'panel/api/inbounds/get/' . $this->inboundId);

        if (!($response['success'] ?? false)) {
            return [];
        }

        $settings = json_decode($response['obj']['settings'] ?? '{}', true);
        $clients = $settings['clients'] ?? [];

        return array_map(fn($c) => new VpnClientDTO(
            uuid: $c['id'],
            email: $c['email'],
            telegramId: isset($c['tgId']) ? (int) $c['tgId'] : null,
            subId: $c['subId'] ?? '',
            enabled: $c['enable'] ?? false,
            expiryTime: $c['expiryTime'] ?? 0,
            totalGB: $c['totalGB'] ?? 0
        ), $clients);
    }

    /**
     * Get session cookie with caching
     */
    private function getSessionCookie(): string
    {
        $cacheKey = 'xui_session_' . md5($this->host . $this->username);

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $url = $this->buildUrl('login');

        try {
            $response = Http::timeout(10)->asForm()->post($url, [
                'username' => $this->username,
                'password' => $this->password,
            ]);
        } catch (\Exception $e) {
            Log::error('XUI: Connection failed', ['error' => $e->getMessage()]);
            throw new ApiConnectionException('Cannot connect to 3x-ui panel: ' . $e->getMessage());
        }

        if (!$response->successful() || !$response->json('success')) {
            Log::error('XUI: Login failed', ['response' => $response->json()]);
            throw new AuthenticationException('3x-ui login failed');
        }

        foreach ($response->cookies() as $cookie) {
            if ($cookie->getName() === '3x-ui') {
                $sessionId = $cookie->getValue();
                Cache::put($cacheKey, $sessionId, $this->sessionCacheTtl);
                return $sessionId;
            }
        }

        throw new AuthenticationException('3x-ui session cookie not found');
    }

    /**
     * Make API request to 3x-ui
     */
    private function makeRequest(string $method, string $endpoint, array $payload = []): array
    {
        $sessionCookie = $this->getSessionCookie();
        $url = $this->buildUrl($endpoint);

        try {
            $request = Http::timeout(15)->withHeaders([
                'Cookie' => "3x-ui={$sessionCookie}"
            ]);

            $response = ($method === 'POST')
                ? $request->post($url, $payload)
                : $request->get($url);

            if (!$response->successful()) {
                Log::error('XUI: API request failed', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                return ['success' => false, 'msg' => 'Request failed with status ' . $response->status()];
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('XUI: API exception', ['endpoint' => $endpoint, 'error' => $e->getMessage()]);
            throw new ApiConnectionException('API request failed: ' . $e->getMessage());
        }
    }

    /**
     * Build URL for API endpoint
     */
    private function buildUrl(string $endpoint): string
    {
        $base = "http://{$this->host}:{$this->port}";

        if (!empty($this->path)) {
            $base .= '/' . trim($this->path, '/');
        }

        return $base . '/' . ltrim($endpoint, '/');
    }

    /**
     * Clear session cache (useful for re-authentication)
     */
    public function clearSession(): void
    {
        $cacheKey = 'xui_session_' . md5($this->host . $this->username);
        Cache::forget($cacheKey);
    }

    /**
     * Get VLESS link for a client
     */
    public function getVlessLink(VpnClientDTO $client): ?string
    {
        try {
            $response = $this->makeRequest('GET', 'panel/api/inbounds/get/' . $this->inboundId);

            if (!($response['success'] ?? false) || empty($response['obj'])) {
                Log::error('XUI: Failed to get inbound for VLESS link');
                return null;
            }

            $inbound = $response['obj'];
            $streamSettings = json_decode($inbound['streamSettings'] ?? '{}', true);

            // Get connection parameters
            $protocol = $inbound['protocol'] ?? 'vless';
            $port = $inbound['port'] ?? 443;
            $network = $streamSettings['network'] ?? 'tcp';
            $security = $streamSettings['security'] ?? 'reality';

            // Get server address from config (panel_domain has the VPN port exposed)
            $serverAddress = config('vpn.panel_domain', $this->host);

            // Build query parameters
            $params = [
                'type' => $network,
                'encryption' => 'none',
                'security' => $security,
                'flow' => $client->flow ?? 'xtls-rprx-vision',
            ];

            // Add Reality settings if applicable
            if ($security === 'reality') {
                $realitySettings = $streamSettings['realitySettings'] ?? [];
                $settings = $realitySettings['settings'] ?? [];

                if (!empty($realitySettings['serverNames'][0])) {
                    $params['sni'] = $realitySettings['serverNames'][0];
                }
                if (!empty($settings['publicKey'])) {
                    $params['pbk'] = $settings['publicKey'];
                }
                if (!empty($realitySettings['shortIds'][0])) {
                    $params['sid'] = $realitySettings['shortIds'][0];
                }
                if (!empty($settings['spiderX'])) {
                    $params['spx'] = $settings['spiderX'];
                }
                if (!empty($settings['fingerprint'])) {
                    $params['fp'] = $settings['fingerprint'];
                } else {
                    $params['fp'] = 'chrome';
                }
            }

            // Build VLESS URL
            $query = http_build_query($params);
            $remark = urlencode($client->email ?? 'VPN');

            return "vless://{$client->uuid}@{$serverAddress}:{$port}?{$query}#{$remark}";

        } catch (\Exception $e) {
            Log::error('XUI: Failed to build VLESS link', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
