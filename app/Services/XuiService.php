<?php

namespace App\Services;

use App\DTO\TrafficDataDTO;
use App\DTO\VpnClientDTO;
use App\DTO\XuiCredentialsDTO;
use App\Exceptions\VpnPanel\ApiConnectionException;
use App\Exceptions\VpnPanel\AuthenticationException;
use App\Exceptions\VpnPanel\ClientCreationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class XuiService
{
    private const SESSION_TTL = 3600;
    private const INBOUND_CACHE_TTL = 300;

    public function __construct(
        private readonly XuiCredentialsDTO $creds,
    ) {}

    public function getOrCreateClient(int $telegramId, ?string $firstName = null, ?string $lastName = null): VpnClientDTO
    {
        return $this->getClientByTelegramId($telegramId)
            ?? $this->createClient($telegramId, $firstName, $lastName);
    }

    public function getClientByTelegramId(int $telegramId): ?VpnClientDTO
    {
        foreach ($this->getAllClients() as $client) {
            if ($client->telegramId === $telegramId) {
                return $client;
            }
        }
        return null;
    }

    public function createClient(int $telegramId, ?string $firstName = null, ?string $lastName = null): VpnClientDTO
    {
        $uuid = (string) Str::uuid();
        $subId = Str::random(16);
        $email = $this->generateClientEmail($uuid, $firstName, $lastName);

        $payload = [
            'id' => $this->creds->inboundId,
            'settings' => json_encode([
                'clients' => [[
                    'id' => $uuid,
                    'flow' => 'xtls-rprx-vision',
                    'email' => $email,
                    'tgId' => (string) $telegramId,
                    'limitIp' => (int) config('vpn.default_device_limit', 2),
                    'totalGB' => (int) config('vpn.default_traffic_limit', 0),
                    'expiryTime' => 0,
                    'enable' => true,
                    'subId' => $subId,
                    'reset' => 0,
                ]],
            ]),
        ];

        $response = $this->makeRequest('POST', 'panel/api/inbounds/addClient', $payload);

        if (!($response['success'] ?? false)) {
            Log::error('XUI: Failed to create client', [
                'telegramId' => $telegramId,
                'server' => $this->creds->host,
                'response' => $response,
            ]);
            throw new ClientCreationException(
                'Failed to create VPN client: ' . ($response['msg'] ?? 'Unknown error')
            );
        }

        $this->forgetInboundCache();

        Log::info('XUI: Created client', [
            'telegramId' => $telegramId,
            'email' => $email,
            'server' => $this->creds->host,
        ]);

        return new VpnClientDTO(
            uuid: $uuid,
            email: $email,
            telegramId: $telegramId,
            subId: $subId,
            enabled: true,
            expiryTime: 0,
            totalGB: 0,
        );
    }

    /**
     * Disable a client in 3x-ui by UUID. Used by billing:enforce-quotas when
     * a quota is exhausted or a subscription expires.
     */
    public function disableClient(string $uuid): bool
    {
        $payload = $this->buildClientUpdatePayload($uuid, ['enable' => false]);
        if ($payload === null) {
            return false;
        }

        $response = $this->makeRequest('POST', "panel/api/inbounds/updateClient/{$uuid}", $payload);
        $ok = (bool) ($response['success'] ?? false);

        if ($ok) {
            $this->forgetInboundCache();
            Log::info('XUI: Disabled client', ['uuid' => $uuid, 'server' => $this->creds->host]);
        } else {
            Log::warning('XUI: Failed to disable client', [
                'uuid' => $uuid,
                'response' => $response,
            ]);
        }

        return $ok;
    }

    private function buildClientUpdatePayload(string $uuid, array $changes): ?array
    {
        $inbound = $this->getInboundCached();
        if (!$inbound) {
            return null;
        }

        $settings = json_decode($inbound['settings'] ?? '{}', true);
        $clients = $settings['clients'] ?? [];

        foreach ($clients as &$client) {
            if (($client['id'] ?? null) === $uuid) {
                foreach ($changes as $k => $v) {
                    $client[$k] = $v;
                }
                return [
                    'id' => $this->creds->inboundId,
                    'settings' => json_encode(['clients' => [$client]]),
                ];
            }
        }

        return null;
    }

    public function getClientTraffic(string $email): TrafficDataDTO
    {
        $response = $this->makeRequest('GET', "panel/api/inbounds/getClientTraffics/{$email}");

        if (!($response['success'] ?? false) || empty($response['obj'])) {
            return new TrafficDataDTO(0, 0, 0);
        }

        $data = $response['obj'];
        return new TrafficDataDTO(
            upload: (int) ($data['up'] ?? 0),
            download: (int) ($data['down'] ?? 0),
            expiryTime: (int) ($data['expiryTime'] ?? 0),
        );
    }

    public function getAllClients(): array
    {
        $inbound = $this->getInboundCached();
        if (!$inbound) {
            return [];
        }

        $settings = json_decode($inbound['settings'] ?? '{}', true);
        $clients = $settings['clients'] ?? [];

        return array_map(fn ($c) => new VpnClientDTO(
            uuid: $c['id'] ?? '',
            email: $c['email'] ?? '',
            telegramId: isset($c['tgId']) && $c['tgId'] !== '' ? (int) $c['tgId'] : null,
            subId: $c['subId'] ?? '',
            enabled: (bool) ($c['enable'] ?? false),
            expiryTime: (int) ($c['expiryTime'] ?? 0),
            totalGB: (int) ($c['totalGB'] ?? 0),
        ), $clients);
    }

    public function clientCount(): int
    {
        return count($this->getAllClients());
    }

    public function getInboundCached(): ?array
    {
        $key = 'xui_inbound_' . $this->creds->fingerprint();

        return Cache::remember($key, self::INBOUND_CACHE_TTL, function () {
            $response = $this->makeRequest('GET', 'panel/api/inbounds/get/' . $this->creds->inboundId);
            if (!($response['success'] ?? false) || empty($response['obj'])) {
                return null;
            }
            return $response['obj'];
        });
    }

    public function forgetInboundCache(): void
    {
        Cache::forget('xui_inbound_' . $this->creds->fingerprint());
    }

    public function getVlessLink(VpnClientDTO $client, ?string $serverAddress = null, ?string $remark = null): ?string
    {
        try {
            $inbound = $this->getInboundCached();
            if (!$inbound) {
                return null;
            }

            $streamSettings = json_decode($inbound['streamSettings'] ?? '{}', true);
            $port = $inbound['port'] ?? 443;
            $network = $streamSettings['network'] ?? 'tcp';
            $security = $streamSettings['security'] ?? 'reality';
            $host = $serverAddress ?: $this->creds->host;

            $params = [
                'type' => $network,
                'encryption' => 'none',
                'security' => $security,
                'flow' => 'xtls-rprx-vision',
            ];

            if ($security === 'reality') {
                $reality = $streamSettings['realitySettings'] ?? [];
                $settings = $reality['settings'] ?? [];

                if (!empty($reality['serverNames'][0])) {
                    $params['sni'] = $reality['serverNames'][0];
                }
                if (!empty($settings['publicKey'])) {
                    $params['pbk'] = $settings['publicKey'];
                }
                if (!empty($reality['shortIds'][0])) {
                    $params['sid'] = $reality['shortIds'][0];
                }
                if (!empty($settings['spiderX'])) {
                    $params['spx'] = $settings['spiderX'];
                }
                $params['fp'] = $settings['fingerprint'] ?? 'chrome';
            }

            $query = http_build_query($params);
            $label = urlencode($remark ?? $client->email ?? 'VPN');

            return "vless://{$client->uuid}@{$host}:{$port}?{$query}#{$label}";
        } catch (\Throwable $e) {
            Log::warning('XUI: getVlessLink failed', [
                'server' => $this->creds->host,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function clearSession(): void
    {
        Cache::forget($this->sessionCacheKey());
    }

    private function sessionCacheKey(): string
    {
        return 'xui_session_' . $this->creds->fingerprint();
    }

    private function getSessionCookie(): string
    {
        $key = $this->sessionCacheKey();
        $cached = Cache::get($key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $url = $this->buildUrl('login');

        try {
            $response = Http::timeout(10)->asForm()->post($url, [
                'username' => $this->creds->username,
                'password' => $this->creds->password,
            ]);
        } catch (\Throwable $e) {
            Log::error('XUI: Connection failed', [
                'server' => $this->creds->host,
                'error' => $e->getMessage(),
            ]);
            throw new ApiConnectionException('Cannot connect to 3x-ui panel: ' . $e->getMessage());
        }

        if (!$response->successful() || !$response->json('success')) {
            Log::error('XUI: Login failed', [
                'server' => $this->creds->host,
                'response' => $response->json(),
            ]);
            throw new AuthenticationException('3x-ui login failed');
        }

        foreach ($response->cookies() as $cookie) {
            if ($cookie->getName() === '3x-ui') {
                $sessionId = $cookie->getValue();
                Cache::put($key, $sessionId, self::SESSION_TTL);
                return $sessionId;
            }
        }

        throw new AuthenticationException('3x-ui session cookie not found');
    }

    private function makeRequest(string $method, string $endpoint, array $payload = []): array
    {
        $session = $this->getSessionCookie();
        $url = $this->buildUrl($endpoint);

        try {
            $request = Http::timeout(15)->withHeaders([
                'Cookie' => "3x-ui={$session}",
            ]);

            $response = $method === 'POST'
                ? $request->post($url, $payload)
                : $request->get($url);

            if (!$response->successful()) {
                Log::error('XUI: API request failed', [
                    'server' => $this->creds->host,
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                ]);
                return ['success' => false, 'msg' => 'Request failed: status ' . $response->status()];
            }

            return $response->json() ?? [];
        } catch (\Throwable $e) {
            Log::error('XUI: API exception', [
                'server' => $this->creds->host,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw new ApiConnectionException('API request failed: ' . $e->getMessage());
        }
    }

    private function buildUrl(string $endpoint): string
    {
        $base = "http://{$this->creds->host}:{$this->creds->port}";
        if ($this->creds->path !== '') {
            $base .= '/' . trim($this->creds->path, '/');
        }
        return $base . '/' . ltrim($endpoint, '/');
    }

    private function generateClientEmail(string $uuid, ?string $firstName = null, ?string $lastName = null): string
    {
        $short = substr($uuid, 0, 8);
        $name = trim(($firstName ?? '') . ' ' . ($lastName ?? ''));
        $name = preg_replace('/[^\p{L}\p{N}\s\-_.]/u', '', $name);
        $name = trim((string) $name);
        return $name !== '' ? ($name . '-' . $short) : ('user-' . $short);
    }
}
