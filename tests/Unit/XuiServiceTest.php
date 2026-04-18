<?php

namespace Tests\Unit;

use App\DTO\VpnClientDTO;
use App\DTO\XuiCredentialsDTO;
use App\Services\XuiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class XuiServiceTest extends TestCase
{
    private function creds(): XuiCredentialsDTO
    {
        return new XuiCredentialsDTO(
            host: 'xui.local', port: 2053, path: '', username: 'admin', password: 'pw', inboundId: 1
        );
    }

    public function test_get_client_by_telegram_id_parses_inbound_clients(): void
    {
        Http::fake([
            'xui.local:2053/login' => Http::response(
                ['success' => true], 200, ['Set-Cookie' => '3x-ui=ABC; Path=/']
            ),
            'xui.local:2053/panel/api/inbounds/get/1' => Http::response([
                'success' => true,
                'obj' => [
                    'settings' => json_encode([
                        'clients' => [
                            ['id' => 'uuid1', 'email' => 'e1', 'tgId' => '12345', 'subId' => 's1', 'enable' => true, 'expiryTime' => 0, 'totalGB' => 0],
                            ['id' => 'uuid2', 'email' => 'e2', 'tgId' => '99999', 'subId' => 's2', 'enable' => true, 'expiryTime' => 0, 'totalGB' => 0],
                        ],
                    ]),
                ],
            ]),
        ]);

        $svc = new XuiService($this->creds());
        $client = $svc->getClientByTelegramId(99999);

        $this->assertInstanceOf(VpnClientDTO::class, $client);
        $this->assertSame('uuid2', $client->uuid);
        $this->assertSame(99999, $client->telegramId);
    }

    public function test_create_client_posts_to_add_client(): void
    {
        Http::fake([
            'xui.local:2053/login' => Http::response(
                ['success' => true], 200, ['Set-Cookie' => '3x-ui=ABC; Path=/']
            ),
            'xui.local:2053/panel/api/inbounds/addClient' => Http::response(['success' => true]),
        ]);

        $svc = new XuiService($this->creds());
        $client = $svc->createClient(42, 'Eve', null);

        $this->assertInstanceOf(VpnClientDTO::class, $client);
        $this->assertSame(42, $client->telegramId);
        $this->assertStringStartsWith('Eve-', $client->email);
        $this->assertSame(16, strlen($client->subId));

        Http::assertSent(fn ($req) => str_contains((string) $req->url(), 'addClient'));
    }

    public function test_get_vless_link_builds_reality_url(): void
    {
        Http::fake([
            'xui.local:2053/login' => Http::response(
                ['success' => true], 200, ['Set-Cookie' => '3x-ui=ABC; Path=/']
            ),
            'xui.local:2053/panel/api/inbounds/get/1' => Http::response([
                'success' => true,
                'obj' => [
                    'port' => 8443,
                    'streamSettings' => json_encode([
                        'network' => 'tcp',
                        'security' => 'reality',
                        'realitySettings' => [
                            'serverNames' => ['gmail.com'],
                            'shortIds' => ['1234'],
                            'settings' => ['publicKey' => 'PUB', 'fingerprint' => 'chrome'],
                        ],
                    ]),
                ],
            ]),
        ]);

        $svc = new XuiService($this->creds());
        $dto = new VpnClientDTO('uuidX', 'ann-uuidX', 1, 'sub', true, 0, 0);
        $link = $svc->getVlessLink($dto, 'vpn.example.com', 'A');

        $this->assertNotNull($link);
        $this->assertStringContainsString('vless://uuidX@vpn.example.com:8443', $link);
        $this->assertStringContainsString('sni=gmail.com', $link);
        $this->assertStringContainsString('pbk=PUB', $link);
        $this->assertStringContainsString('sid=1234', $link);
    }
}
