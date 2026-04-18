<?php

namespace Tests\Feature\MiniApp;

use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BootstrapResponseTest extends TestCase
{
    use RefreshDatabase;
    use InitDataHelper;

    public function test_bootstrap_returns_user_and_servers(): void
    {
        Server::create([
            'slug' => 'us-1', 'name' => 'US', 'country' => 'USA', 'country_code' => 'US',
            'host' => 'us.example.com', 'port' => 443, 'inbound_id' => 1,
            'xui_host' => 'xui.us', 'xui_port' => 2053, 'xui_username' => 'admin', 'xui_password' => 's3cret',
            'is_active' => true, 'is_coming_soon' => false,
        ]);

        $initData = $this->buildInitData();

        $res = $this->getJson('/api/miniapp/bootstrap', [
            'X-Telegram-Init-Data' => $initData,
        ])->assertOk();

        $res->assertJsonPath('user.id', 99001);
        $res->assertJsonStructure([
            'user' => ['id', 'firstName', 'displayName', 'subToken'],
            'servers' => [['slug', 'name', 'country', 'flagEmoji']],
            'config'  => ['aggregatedSubscriptionUrl', 'primaryDomain'],
        ]);

        $body = $res->getContent();
        $this->assertStringNotContainsString('s3cret', $body, 'credentials must not leak');
        $this->assertStringNotContainsString('xui_password', $body);
    }
}
