<?php

namespace Tests\Feature;

use App\Models\TelegramUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BroadcastCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_a_message_source(): void
    {
        $this->artisan('telegram:broadcast')->assertExitCode(1);
    }

    public function test_dry_run_reports_recipients_and_sends_nothing(): void
    {
        TelegramUser::create(['telegram_id' => 10, 'first_name' => 'A', 'language_code' => 'ru', 'allows_write_to_pm' => true]);
        TelegramUser::create(['telegram_id' => 11, 'first_name' => 'B', 'language_code' => 'en', 'allows_write_to_pm' => true]);
        // Opted out → must not be counted as a recipient.
        TelegramUser::create(['telegram_id' => 12, 'first_name' => 'C', 'allows_write_to_pm' => false]);

        $this->artisan('telegram:broadcast --key=broadcast.migration --open-app --dry-run')
            ->expectsOutputToContain('matched=2')
            ->expectsOutputToContain('Dry run: nothing sent.')
            ->assertExitCode(0);
    }

    public function test_unknown_config_server_fails(): void
    {
        $this->artisan('telegram:broadcast --key=broadcast.migration --with-config=ghost --dry-run')
            ->assertExitCode(1);
    }
}
