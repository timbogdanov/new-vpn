<?php

namespace App\Console\Commands;

use App\Services\XuiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class UpdateClientNames extends Command
{
    protected $signature = 'clients:update-names {--dry-run : Show what would be updated without making changes}';
    protected $description = 'Update existing VPN client names with Telegram names';

    public function handle(XuiService $xuiService): int
    {
        $token = config('telegram.bot_token');

        if (!$token) {
            $this->error('TELEGRAM_BOT_TOKEN is not set in .env');
            return 1;
        }

        $this->info('Fetching all clients from XUI panel...');

        $clients = $xuiService->getAllClients();

        if (empty($clients)) {
            $this->warn('No clients found.');
            return 0;
        }

        $this->info("Found {$this->count($clients)} clients.");

        $dryRun = $this->option('dry-run');
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($clients as $client) {
            if (!$client->telegramId) {
                $this->line("  Skipping {$client->email} - no Telegram ID");
                $skipped++;
                continue;
            }

            // Check if already in new format (contains full name pattern)
            if (preg_match('/^.+-[a-f0-9]{8}$/i', $client->email) && !str_starts_with($client->email, 'tg_')) {
                $this->line("  Skipping {$client->email} - already in new format");
                $skipped++;
                continue;
            }

            // Fetch user info from Telegram
            $userInfo = $this->getTelegramUserInfo($token, $client->telegramId);

            if (!$userInfo) {
                $this->warn("  Failed to get Telegram info for {$client->email} (ID: {$client->telegramId})");
                $failed++;
                continue;
            }

            $firstName = $userInfo['first_name'] ?? null;
            $lastName = $userInfo['last_name'] ?? null;

            $newEmail = $this->generateClientEmail($client->uuid, $firstName, $lastName);

            if ($newEmail === $client->email) {
                $this->line("  Skipping {$client->email} - no change needed");
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->info("  [DRY RUN] Would update: {$client->email} -> {$newEmail}");
                $updated++;
            } else {
                $this->info("  Updating: {$client->email} -> {$newEmail}");

                if ($xuiService->updateClient($client->email, $newEmail)) {
                    $updated++;
                } else {
                    $this->error("  Failed to update {$client->email}");
                    $failed++;
                }

                // Rate limit protection
                usleep(100000); // 100ms delay
            }
        }

        $this->newLine();
        $this->info("Summary:");
        $this->line("  Updated: {$updated}");
        $this->line("  Skipped: {$skipped}");
        $this->line("  Failed: {$failed}");

        if ($dryRun && $updated > 0) {
            $this->newLine();
            $this->warn("This was a dry run. Run without --dry-run to apply changes.");
        }

        return $failed > 0 ? 1 : 0;
    }

    private function getTelegramUserInfo(string $token, int $telegramId): ?array
    {
        try {
            $response = Http::get("https://api.telegram.org/bot{$token}/getChat", [
                'chat_id' => $telegramId,
            ]);

            if ($response->successful() && $response->json('ok')) {
                return $response->json('result');
            }
        } catch (\Exception $e) {
            // Silently fail, will be reported as failed
        }

        return null;
    }

    private function generateClientEmail(string $uuid, ?string $firstName = null, ?string $lastName = null): string
    {
        $shortUuid = substr($uuid, 0, 8);

        if ($firstName) {
            $name = trim($firstName . ($lastName ? ' ' . $lastName : ''));
            // Sanitize: keep letters, numbers, spaces, and common characters
            $name = preg_replace('/[^\p{L}\p{N}\s\-_.]/u', '', $name);
            $name = trim($name);

            if (!empty($name)) {
                return $name . '-' . $shortUuid;
            }
        }

        return 'user-' . $shortUuid;
    }

    private function count(array $items): int
    {
        return count($items);
    }
}
