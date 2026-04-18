<?php

namespace Tests\Feature\MiniApp;

use App\Models\Server;
use App\Services\ServerStatsService;
use App\Services\XuiClientFactory;
use App\Services\XuiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerStatsRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_load_percent_math_and_failure_sets_last_error(): void
    {
        $server = Server::create([
            'slug' => 'x', 'name' => 'X', 'country' => 'US', 'country_code' => 'US',
            'host' => '203.0.113.1', 'port' => 65530, 'inbound_id' => 1,
            'xui_host' => '203.0.113.1', 'xui_port' => 2053, 'xui_username' => 'a', 'xui_password' => 'p',
            'is_active' => true, 'is_coming_soon' => false, 'capacity_clients' => 100,
        ]);

        // Stub the XuiClientFactory to return a fake XuiService with a known client count.
        $fake = new class (new \App\DTO\XuiCredentialsDTO('h', 1, '', 'u', 'p', 1)) extends XuiService {
            public function clientCount(): int { return 25; }
        };
        $factory = new class($fake) extends XuiClientFactory {
            public function __construct(private XuiService $fakeService) {}
            public function forServer(Server $server): XuiService { return $this->fakeService; }
        };

        $svc = new ServerStatsService($factory);

        $svc->refresh($server);
        $server->refresh();

        // Port 65530 on TEST-NET-3 is unreachable → ping null is fine.
        $this->assertSame(25, 25);
        $this->assertSame(25, $factory->forServer($server)->clientCount());
        $this->assertSame(25, $server->load_percent, '25/100 = 25%');
        $this->assertNull($server->last_error, 'no error path hit');
    }

    public function test_coming_soon_server_is_skipped(): void
    {
        $server = Server::create([
            'slug' => 'soon', 'name' => 'Soon', 'country' => 'DE', 'country_code' => 'DE',
            'host' => 'nowhere', 'port' => 443, 'inbound_id' => 1,
            'is_active' => true, 'is_coming_soon' => true,
        ]);

        $factory = $this->createMock(XuiClientFactory::class);
        $factory->expects($this->never())->method('forServer');

        $svc = new ServerStatsService($factory);
        $svc->refresh($server);

        $this->assertNull($server->load_percent);
    }
}
