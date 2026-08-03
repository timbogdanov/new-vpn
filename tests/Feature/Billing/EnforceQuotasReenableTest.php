<?php

namespace Tests\Feature\Billing;

use App\Models\Server;
use App\Models\TelegramUser;
use App\Models\VpnClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EnforceQuotasReenableTest extends TestCase
{
    use RefreshDatabase;

    private function fakePanel(array $clients = []): void
    {
        Http::fake([
            '*/login' => Http::response(
                ['success' => true], 200, ['Set-Cookie' => '3x-ui=S; Path=/']
            ),
            '*/panel/api/inbounds/get/*' => Http::response([
                'success' => true,
                'obj' => ['settings' => json_encode(['clients' => $clients])],
            ]),
            '*/panel/api/inbounds/updateClient/*' => Http::response(['success' => true]),
        ]);
    }

    private function server(): Server
    {
        return Server::create([
            'slug' => 'primary', 'name' => 'P', 'country' => 'US', 'country_code' => 'US',
            'host' => 'h', 'port' => 8443, 'inbound_id' => 1,
            'xui_host' => 'xui', 'xui_port' => 2053, 'xui_username' => 'a', 'xui_password' => 'p',
            'is_active' => true, 'is_coming_soon' => false,
        ]);
    }

    public function test_reenables_billing_disabled_client_after_renewal(): void
    {
        $server = $this->server();
        TelegramUser::create(['telegram_id' => 1, 'first_name' => 'A']);
        VpnClient::create([
            'telegram_user_id' => 1, 'server_id' => $server->id,
            'uuid' => 'uuid-re', 'sub_id' => 's', 'email' => 'e',
            'enabled' => false, 'disabled_reason' => 'subscription_expired',
            'expires_at' => Carbon::now()->addDays(20), // renewed → future
        ]);
        $this->fakePanel([['id' => 'uuid-re', 'enable' => false, 'email' => 'e']]);

        $this->artisan('billing:enforce-quotas')->assertExitCode(0);

        $this->assertDatabaseHas('vpn_clients', [
            'uuid' => 'uuid-re', 'enabled' => true, 'disabled_reason' => null,
        ]);
        Http::assertSent(fn ($req) => str_contains((string) $req->url(), 'updateClient/uuid-re'));
    }

    public function test_does_not_reenable_still_expired_client(): void
    {
        $server = $this->server();
        TelegramUser::create(['telegram_id' => 2, 'first_name' => 'B']);
        VpnClient::create([
            'telegram_user_id' => 2, 'server_id' => $server->id,
            'uuid' => 'uuid-exp', 'sub_id' => 's2', 'email' => 'e2',
            'enabled' => false, 'disabled_reason' => 'subscription_expired',
            'expires_at' => Carbon::now()->subDay(), // still in the past
        ]);
        $this->fakePanel();

        $this->artisan('billing:enforce-quotas')->assertExitCode(0);

        $this->assertDatabaseHas('vpn_clients', ['uuid' => 'uuid-exp', 'enabled' => false]);
    }

    public function test_does_not_reenable_refunded_client(): void
    {
        $server = $this->server();
        TelegramUser::create(['telegram_id' => 3, 'first_name' => 'C']);
        VpnClient::create([
            'telegram_user_id' => 3, 'server_id' => $server->id,
            'uuid' => 'uuid-ref', 'sub_id' => 's3', 'email' => 'e3',
            'enabled' => false, 'disabled_reason' => 'refunded',
            'expires_at' => Carbon::now()->addDays(20),
        ]);
        $this->fakePanel();

        $this->artisan('billing:enforce-quotas')->assertExitCode(0);

        // 'refunded' is not a billing-enforcement reason → must stay disabled.
        $this->assertDatabaseHas('vpn_clients', ['uuid' => 'uuid-ref', 'enabled' => false]);
    }
}
