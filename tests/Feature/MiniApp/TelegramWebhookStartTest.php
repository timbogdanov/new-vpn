<?php

namespace Tests\Feature\MiniApp;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramWebhookStartTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_upserts_user_and_returns_200(): void
    {
        // The Telegram SDK (irazasyed) uses its own Guzzle, not Laravel's Http facade,
        // so we cannot intercept its outgoing POSTs via Http::fake. We still set a
        // blanket fake to avoid any real network attempts from any other call site.
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
        ]);

        $payload = [
            'update_id' => 1,
            'message' => [
                'message_id' => 1,
                'date' => time(),
                'chat' => ['id' => 111, 'type' => 'private'],
                'from' => [
                    'id' => 66612,
                    'first_name' => 'Zoey',
                    'last_name' => 'Q',
                    'username' => 'zoey',
                    'language_code' => 'en',
                ],
                'text' => '/start',
            ],
        ];

        $this->postJson('/telegram/webhook', $payload)->assertOk();

        $this->assertDatabaseHas('telegram_users', [
            'telegram_id' => 66612,
            'first_name' => 'Zoey',
        ]);
    }

    public function test_webhook_tolerates_unknown_update_types(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $this->postJson('/telegram/webhook', ['update_id' => 9, 'foo' => 'bar'])
            ->assertOk();
    }
}
