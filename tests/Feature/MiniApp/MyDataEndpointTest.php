<?php

namespace Tests\Feature\MiniApp;

use App\Models\CommunityProbeSignal;
use App\Models\TelegramUser;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyDataEndpointTest extends TestCase
{
    use RefreshDatabase;
    use InitDataHelper;

    private function seedSignals(int $telegramId, int $count, string $result = 'blocked'): void
    {
        $hash = CommunityProbeSignal::hashForUser($telegramId);
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'user_hash' => $hash,
                'country_code' => 'RU',
                'asn' => 'AS25513',
                'service_key' => 'reddit',
                'url' => 'https://www.reddit.com/',
                'result' => $result,
                'observed_at' => Carbon::now()->subMinutes($i),
                'created_at' => Carbon::now()->subMinutes($i),
            ];
        }
        CommunityProbeSignal::query()->insert($rows);
    }

    public function test_index_returns_user_signals_only(): void
    {
        $mine = $this->buildInitData(['user' => ['id' => 10001, 'first_name' => 'A', 'language_code' => 'en']]);
        $theirs = CommunityProbeSignal::hashForUser(20002);

        $this->seedSignals(10001, 5, 'blocked');
        CommunityProbeSignal::query()->insert([
            'user_hash' => $theirs,
            'country_code' => 'RU',
            'asn' => 'AS8402',
            'service_key' => 'reddit',
            'url' => 'https://www.reddit.com/',
            'result' => 'blocked',
            'observed_at' => Carbon::now(),
            'created_at' => Carbon::now(),
        ]);

        $res = $this->getJson('/api/miniapp/ooni/my-data', [
            'X-Telegram-Init-Data' => $mine,
        ])->assertOk();

        $res->assertJsonPath('totalSignals', 5);
        $res->assertJsonPath('blockedCount', 5);
        $this->assertCount(5, $res->json('recentSignals'));
    }

    public function test_destroy_soft_deletes_signals(): void
    {
        $init = $this->buildInitData(['user' => ['id' => 10001, 'first_name' => 'A', 'language_code' => 'en']]);
        $this->seedSignals(10001, 3, 'reachable');

        $res = $this->deleteJson('/api/miniapp/ooni/my-data?confirm=1', [], [
            'X-Telegram-Init-Data' => $init,
        ])->assertOk();

        $this->assertSame(3, $res->json('tombstoned'));

        // Subsequent listing is empty — soft-deleted rows are filtered out.
        $this->getJson('/api/miniapp/ooni/my-data', ['X-Telegram-Init-Data' => $init])
            ->assertOk()
            ->assertJsonPath('totalSignals', 0);

        // Row still exists with deleted_at populated.
        $hash = CommunityProbeSignal::hashForUser(10001);
        $row = CommunityProbeSignal::query()->where('user_hash', $hash)->first();
        $this->assertNotNull($row);
        $this->assertNotNull($row->deleted_at);
    }

    public function test_destroy_requires_confirm_flag(): void
    {
        $init = $this->buildInitData();
        $this->deleteJson('/api/miniapp/ooni/my-data', [], ['X-Telegram-Init-Data' => $init])
            ->assertStatus(422);
    }
}
