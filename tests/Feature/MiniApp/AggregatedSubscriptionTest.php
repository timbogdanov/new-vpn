<?php

namespace Tests\Feature\MiniApp;

use App\Models\Server;
use App\Models\TelegramUser;
use App\Models\VpnClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AggregatedSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_returns_base64_encoded_vless_lines(): void
    {
        Http::fake([
            '*/login' => Http::response(
                ['success' => true], 200, ['Set-Cookie' => '3x-ui=S; Path=/']
            ),
            '*/panel/api/inbounds/get/*' => Http::response([
                'success' => true,
                'obj' => [
                    'id' => 1, 'port' => 8443, 'protocol' => 'vless',
                    'settings' => json_encode(['clients' => []]),
                    'streamSettings' => json_encode([
                        'network' => 'tcp',
                        'security' => 'reality',
                        'realitySettings' => [
                            'serverNames' => ['sni.test'],
                            'shortIds' => ['sid1'],
                            'settings' => ['publicKey' => 'PUB', 'fingerprint' => 'chrome'],
                        ],
                    ]),
                ],
            ]),
        ]);

        $server = Server::create([
            'slug' => 'p', 'name' => 'Primary', 'country' => 'US', 'country_code' => 'US',
            'host' => 'p.example.com', 'port' => 8443, 'inbound_id' => 1,
            'xui_host' => 'xui', 'xui_port' => 2053, 'xui_username' => 'a', 'xui_password' => 'p',
            'is_active' => true, 'is_coming_soon' => false,
        ]);

        $user = TelegramUser::create([
            'telegram_id' => 77700,
            'first_name' => 'Bob',
            'language_code' => 'ru',
        ]);

        VpnClient::create([
            'telegram_user_id' => $user->telegram_id,
            'server_id' => $server->id,
            'uuid' => '11111111-2222-3333-4444-555555555555',
            'sub_id' => 'sub123',
            'email' => 'bob-11111111',
            'enabled' => true,
        ]);

        $token = $user->getOrGenerateSubToken();

        $res = $this->get("/sub/u/{$token}")->assertOk();
        $body = $res->getContent();

        $this->assertNotEmpty($body);
        $decoded = base64_decode($body, true);
        $this->assertNotFalse($decoded);
        $this->assertStringContainsString('vless://11111111-2222-3333-4444-555555555555@p.example.com:8443', $decoded);
    }

    public function test_subscription_404s_for_unknown_token(): void
    {
        $this->get('/sub/u/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa')->assertStatus(404);
    }

    public function test_subscription_rejects_short_tokens(): void
    {
        $this->get('/sub/u/tiny')->assertStatus(404);
    }
}
