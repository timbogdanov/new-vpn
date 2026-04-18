<?php

namespace Tests\Feature\MiniApp;

use App\Models\TelegramUser;
use App\Services\TelegramAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class InitDataValidationTest extends TestCase
{
    use RefreshDatabase;
    use InitDataHelper;

    public function test_valid_init_data_upserts_user(): void
    {
        $initData = $this->buildInitData([
            'user' => [
                'id' => 55501,
                'first_name' => 'Ivan',
                'username' => 'ivan',
                'language_code' => 'ru',
            ],
        ]);

        /** @var TelegramAuthService $svc */
        $svc = app(TelegramAuthService::class);

        $user = $svc->validate($initData);

        $this->assertInstanceOf(TelegramUser::class, $user);
        $this->assertSame(55501, (int) $user->telegram_id);
        $this->assertSame('Ivan', $user->first_name);
        $this->assertSame('ru', $user->language_code);
    }

    public function test_tampered_hash_is_rejected(): void
    {
        $initData = $this->buildInitData(['tamper' => true]);
        $this->expectException(RuntimeException::class);
        app(TelegramAuthService::class)->validate($initData);
    }

    public function test_expired_auth_date_is_rejected(): void
    {
        config()->set('miniapp.init_data_ttl', 60);
        $initData = $this->buildInitData(['auth_date' => time() - 3600]);
        $this->expectException(RuntimeException::class);
        app(TelegramAuthService::class)->validate($initData);
    }

    public function test_missing_hash_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        app(TelegramAuthService::class)->validate('auth_date=' . time() . '&user={}');
    }

    public function test_middleware_returns_401_for_invalid_init_data(): void
    {
        $this->getJson('/api/miniapp/bootstrap', ['X-Telegram-Init-Data' => 'garbage'])
            ->assertStatus(401);
    }
}
