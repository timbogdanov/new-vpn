<?php

namespace Tests\Feature\MiniApp;

use App\Models\Server;
use App\Models\VpnClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConnectEndpointTest extends TestCase
{
    use RefreshDatabase;
    use InitDataHelper;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*/login' => Http::response(
                ['success' => true, 'msg' => 'ok'],
                200,
                ['Set-Cookie' => '3x-ui=SESSION123; Path=/; HttpOnly']
            ),
            '*/panel/api/inbounds/get/*' => Http::response([
                'success' => true,
                'obj' => [
                    'id' => 1,
                    'port' => 8443,
                    'protocol' => 'vless',
                    'settings' => json_encode(['clients' => []]),
                    'streamSettings' => json_encode([
                        'network' => 'tcp',
                        'security' => 'reality',
                        'realitySettings' => [
                            'serverNames' => ['gmail.com'],
                            'shortIds' => ['1234'],
                            'settings' => [
                                'publicKey' => 'PUB',
                                'fingerprint' => 'chrome',
                            ],
                        ],
                    ]),
                ],
            ]),
            '*/panel/api/inbounds/addClient' => Http::response(['success' => true, 'msg' => 'ok']),
        ]);
    }

    public function test_connect_lazily_provisions_and_is_idempotent(): void
    {
        $server = Server::create([
            'slug' => 'primary', 'name' => 'Primary', 'country' => 'US', 'country_code' => 'US',
            'host' => 'panel.test', 'port' => 8443, 'inbound_id' => 1,
            'xui_host' => 'xui.test', 'xui_port' => 2053, 'xui_username' => 'admin', 'xui_password' => 'pw',
            'subscription_host' => 'panel.test', 'subscription_port' => 2096,
            'is_active' => true, 'is_coming_soon' => false,
        ]);

        $initData = $this->buildInitData();

        $first = $this->postJson("/api/miniapp/servers/{$server->slug}/connect", [], [
            'X-Telegram-Init-Data' => $initData,
        ])->assertOk();

        $first->assertJsonStructure(['subscriptionUrl', 'configText', 'deepLinks' => ['ios', 'android', 'macos', 'desktop']]);
        // Connect must hand out the universal (aggregated) subscription so it
        // never 400s on a per-server sub_id that has drifted from the panel.
        $this->assertStringContainsString('/sub/u/', $first->json('subscriptionUrl'));
        $this->assertStringContainsString('import', $first->json('deepLinks.ios'));
        // Raw copy-paste config: a self-contained vless:// line.
        $this->assertStringStartsWith('vless://', (string) $first->json('configText'));
        $this->assertSame(1, VpnClient::count());

        $second = $this->postJson("/api/miniapp/servers/{$server->slug}/connect", [], [
            'X-Telegram-Init-Data' => $initData,
        ])->assertOk();

        $this->assertSame(1, VpnClient::count(), 'connect must be idempotent');
        $this->assertSame(
            $first->json('subscriptionUrl'),
            $second->json('subscriptionUrl'),
            'same subscription URL returned on repeat'
        );
    }

    public function test_connect_rejects_coming_soon_server(): void
    {
        Server::create([
            'slug' => 'de', 'name' => 'DE', 'country' => 'Germany', 'country_code' => 'DE',
            'host' => 'de.example.com', 'port' => 443, 'inbound_id' => 1,
            'is_active' => true, 'is_coming_soon' => true,
        ]);

        $this->postJson('/api/miniapp/servers/de/connect', [], [
            'X-Telegram-Init-Data' => $this->buildInitData(),
        ])->assertStatus(422)->assertJsonPath('error', 'coming_soon');
    }

    public function test_connect_returns_404_for_unknown_slug(): void
    {
        $this->postJson('/api/miniapp/servers/nope/connect', [], [
            'X-Telegram-Init-Data' => $this->buildInitData(),
        ])->assertStatus(404);
    }
}
