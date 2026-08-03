<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\TelegramUser;
use App\Models\VpnClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReprovisionCommandTest extends TestCase
{
    use RefreshDatabase;

    private function fakePanel(array $existingClients): void
    {
        Http::fake([
            '*/login' => Http::response(
                ['success' => true], 200, ['Set-Cookie' => '3x-ui=S; Path=/']
            ),
            '*/panel/api/inbounds/get/*' => Http::response([
                'success' => true,
                'obj' => ['settings' => json_encode(['clients' => $existingClients])],
            ]),
            '*/panel/api/inbounds/addClient' => Http::response(['success' => true]),
        ]);
    }

    private function server(): Server
    {
        return Server::create([
            'slug' => 'primary', 'name' => 'Primary', 'country' => 'US', 'country_code' => 'US',
            'host' => 'new-ip.example', 'port' => 443, 'inbound_id' => 1,
            'xui_host' => 'xui', 'xui_port' => 2053, 'xui_username' => 'a', 'xui_password' => 'p',
            'is_active' => true, 'is_coming_soon' => false,
        ]);
    }

    private function client(int $tg, int $serverId, string $uuid, bool $enabled): void
    {
        TelegramUser::firstOrCreate(['telegram_id' => $tg], ['first_name' => 'U' . $tg]);
        VpnClient::create([
            'telegram_user_id' => $tg,
            'server_id' => $serverId,
            'uuid' => $uuid,
            'sub_id' => 'sub-' . $uuid,
            'email' => 'e-' . $uuid,
            'enabled' => $enabled,
        ]);
    }

    public function test_adds_missing_skips_existing_and_disabled(): void
    {
        $server = $this->server();
        $this->client(1, $server->id, 'uuidA', true);   // already on panel → skip
        $this->client(2, $server->id, 'uuidB', true);   // missing → add
        $this->client(3, $server->id, 'uuidC', false);  // disabled → skip (default)

        $this->fakePanel([
            ['id' => 'uuidA', 'email' => 'e-uuidA', 'tgId' => '1', 'subId' => 'sub-uuidA', 'enable' => true],
        ]);

        $this->artisan('vpn:reprovision primary')->assertExitCode(0);

        Http::assertSent(function ($req) {
            if (!str_contains((string) $req->url(), 'addClient')) {
                return false;
            }
            $ids = array_column(json_decode($req->data()['settings'], true)['clients'] ?? [], 'id');
            return in_array('uuidB', $ids, true)
                && !in_array('uuidA', $ids, true)
                && !in_array('uuidC', $ids, true);
        });
    }

    public function test_dry_run_writes_nothing(): void
    {
        $server = $this->server();
        $this->client(2, $server->id, 'uuidB', true);
        $this->fakePanel([]);

        $this->artisan('vpn:reprovision primary --dry-run')->assertExitCode(0);

        Http::assertNotSent(fn ($req) => str_contains((string) $req->url(), 'addClient'));
    }

    public function test_include_disabled_adds_disabled_preserving_enable_flag(): void
    {
        $server = $this->server();
        $this->client(3, $server->id, 'uuidC', false);
        $this->fakePanel([]);

        $this->artisan('vpn:reprovision primary --include-disabled')->assertExitCode(0);

        Http::assertSent(function ($req) {
            if (!str_contains((string) $req->url(), 'addClient')) {
                return false;
            }
            $c = json_decode($req->data()['settings'], true)['clients'][0] ?? [];
            return ($c['id'] ?? null) === 'uuidC' && ($c['enable'] ?? null) === false;
        });
    }

    public function test_unknown_slug_fails(): void
    {
        $this->artisan('vpn:reprovision nope')->assertExitCode(1);
    }
}
