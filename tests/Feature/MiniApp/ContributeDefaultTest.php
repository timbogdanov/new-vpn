<?php

namespace Tests\Feature\MiniApp;

use App\Models\TelegramUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContributeDefaultTest extends TestCase
{
    use RefreshDatabase;
    use InitDataHelper;

    public function test_new_user_is_created_with_ooni_contribute_true(): void
    {
        $initData = $this->buildInitData(['user' => ['id' => 777001, 'first_name' => 'New', 'language_code' => 'en']]);

        $res = $this->getJson('/api/miniapp/bootstrap', [
            'X-Telegram-Init-Data' => $initData,
        ])->assertOk();

        $res->assertJsonPath('user.contributeSignals', true);

        $user = TelegramUser::query()->where('telegram_id', 777001)->first();
        $this->assertNotNull($user);
        $this->assertTrue((bool) $user->ooni_contribute);
        $this->assertNull($user->ooni_contribute_acked_at);
    }
}
