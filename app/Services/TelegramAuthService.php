<?php

namespace App\Services;

use App\Models\TelegramUser;
use Illuminate\Support\Carbon;
use RuntimeException;

class TelegramAuthService
{
    /** @var array<string, TelegramUser> */
    private array $cache = [];

    public function validate(string $rawInitData): TelegramUser
    {
        if ($rawInitData === '') {
            throw new RuntimeException('empty initData');
        }

        if (isset($this->cache[$rawInitData])) {
            return $this->cache[$rawInitData];
        }

        $botToken = (string) config('telegram.bot_token');
        if ($botToken === '') {
            throw new RuntimeException('telegram bot token not configured');
        }

        $pairs = [];
        parse_str($rawInitData, $pairs);

        if (!isset($pairs['hash'])) {
            throw new RuntimeException('initData missing hash');
        }

        $receivedHash = (string) $pairs['hash'];
        unset($pairs['hash']);

        // Telegram spec: sort alphabetically, join as "k=v\n...".
        // Must use the RAW value from the querystring (urldecoded once by parse_str).
        ksort($pairs);
        $lines = [];
        foreach ($pairs as $k => $v) {
            $lines[] = $k . '=' . $v;
        }
        $dataCheckString = implode("\n", $lines);

        $secret = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $computed = hash_hmac('sha256', $dataCheckString, $secret);

        if (!hash_equals($computed, $receivedHash)) {
            throw new RuntimeException('initData signature mismatch');
        }

        $authDate = (int) ($pairs['auth_date'] ?? 0);
        $ttl = (int) config('miniapp.init_data_ttl', 86400);
        if ($authDate <= 0 || (time() - $authDate) > $ttl) {
            throw new RuntimeException('initData expired');
        }

        $userJson = $pairs['user'] ?? null;
        $userData = is_string($userJson) ? json_decode($userJson, true) : null;
        if (!is_array($userData) || empty($userData['id'])) {
            throw new RuntimeException('initData missing user');
        }

        $user = $this->upsert($userData);
        $this->cache[$rawInitData] = $user;
        return $user;
    }

    /** @param array<string, mixed> $from */
    public function upsertFromTelegramUpdate(array $from): TelegramUser
    {
        return $this->upsert($from);
    }

    /** @param array<string, mixed> $data */
    private function upsert(array $data): TelegramUser
    {
        $telegramId = (int) $data['id'];

        /** @var TelegramUser $user */
        $user = TelegramUser::firstOrNew(['telegram_id' => $telegramId]);

        $user->telegram_id = $telegramId;
        $user->username = $data['username'] ?? $user->username;
        $user->first_name = $data['first_name'] ?? $user->first_name;
        $user->last_name = $data['last_name'] ?? $user->last_name;
        $user->photo_url = $data['photo_url'] ?? $user->photo_url;
        if (isset($data['allows_write_to_pm'])) {
            $user->allows_write_to_pm = (bool) $data['allows_write_to_pm'];
        }

        // Only set language_code on first creation — let Mini App Profile own it thereafter.
        if (!$user->exists) {
            $tgLang = isset($data['language_code']) ? substr((string) $data['language_code'], 0, 8) : null;
            $user->language_code = $tgLang === 'ru' ? 'ru' : ($tgLang === 'en' ? 'en' : 'ru');
        }

        $throttle = (int) config('miniapp.last_active_throttle_seconds', 60);
        if (!$user->last_active_at || $user->last_active_at->lt(Carbon::now()->subSeconds($throttle))) {
            $user->last_active_at = Carbon::now();
        }

        $user->save();
        return $user;
    }
}
