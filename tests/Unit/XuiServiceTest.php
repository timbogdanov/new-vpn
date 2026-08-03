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

    public function test_add_existing_client_posts_supplied_identity(): void
    {
        Http::fake([
            'xui.local:2053/login' => Http::response(
                ['success' => true], 200, ['Set-Cookie' => '3x-ui=ABC; Path=/']
            ),
            'xui.local:2053/panel/api/inbounds/addClient' => Http::response(['success' => true]),
        ]);

        $svc = new XuiService($this->creds());
        // Disabled row: enable must be preserved as false, not silently re-enabled.
        $dto = new VpnClientDTO('keep-uuid', 'keep-email', 555, 'keep-sub', false, 0, 0);

        $this->assertTrue($svc->addExistingClient($dto));

        Http::assertSent(function ($req) {
            if (!str_contains((string) $req->url(), 'addClient')) {
                return false;
            }
            $client = json_decode($req->data()['settings'], true)['clients'][0] ?? [];
            return ($client['id'] ?? null) === 'keep-uuid'
                && ($client['subId'] ?? null) === 'keep-sub'
                && ($client['email'] ?? null) === 'keep-email'
                && ($client['tgId'] ?? null) === '555'
                && ($client['enable'] ?? null) === false
                && ($client['flow'] ?? null) === 'xtls-rprx-vision';
        });
    }

    public function test_add_existing_client_treats_duplicate_as_success(): void
    {
        Http::fake([
            'xui.local:2053/login' => Http::response(
                ['success' => true], 200, ['Set-Cookie' => '3x-ui=ABC; Path=/']
            ),
            'xui.local:2053/panel/api/inbounds/addClient' => Http::response(
                ['success' => false, 'msg' => 'Email already exists']
            ),
        ]);

        $svc = new XuiService($this->creds());
        $dto = new VpnClientDTO('dup-uuid', 'dup-email', 1, 'dup-sub', true, 0, 0);

        // A single add that only "fails" because the client already exists is
        // idempotent success, so re-runs of vpn:reprovision don't report errors.
        $this->assertTrue($svc->addExistingClient($dto));
    }

    public function test_add_existing_clients_batch_returns_per_uuid_map(): void
    {
        Http::fake([
            'xui.local:2053/login' => Http::response(
                ['success' => true], 200, ['Set-Cookie' => '3x-ui=ABC; Path=/']
            ),
            'xui.local:2053/panel/api/inbounds/addClient' => Http::response(['success' => true]),
        ]);

        $svc = new XuiService($this->creds());
        $a = new VpnClientDTO('uuid-a', 'ea', 1, 'sa', true, 0, 0);
        $b = new VpnClientDTO('uuid-b', 'eb', 2, 'sb', false, 0, 0);

        $map = $svc->addExistingClients([$a, $b]);

        $this->assertSame(['uuid-a' => true, 'uuid-b' => true], $map);
        Http::assertSent(function ($req) {
            if (!str_contains((string) $req->url(), 'addClient')) {
                return false;
            }
            $clients = json_decode($req->data()['settings'], true)['clients'] ?? [];
            return count($clients) === 2 && $clients[1]['enable'] === false;
        });
    }

    public function test_enable_client_posts_enable_true(): void
    {
        Http::fake([
            'xui.local:2053/login' => Http::response(
                ['success' => true], 200, ['Set-Cookie' => '3x-ui=ABC; Path=/']
            ),
            'xui.local:2053/panel/api/inbounds/get/1' => Http::response([
                'success' => true,
                'obj' => ['settings' => json_encode(['clients' => [
                    ['id' => 'u-1', 'enable' => false, 'email' => 'e1'],
                ]])],
            ]),
            'xui.local:2053/panel/api/inbounds/updateClient/u-1' => Http::response(['success' => true]),
        ]);

        $svc = new XuiService($this->creds());
        $this->assertTrue($svc->enableClient('u-1'));

        Http::assertSent(function ($req) {
            if (!str_contains((string) $req->url(), 'updateClient/u-1')) {
                return false;
            }
            $client = json_decode($req->data()['settings'], true)['clients'][0] ?? [];
            return ($client['enable'] ?? null) === true;
        });
    }
}
