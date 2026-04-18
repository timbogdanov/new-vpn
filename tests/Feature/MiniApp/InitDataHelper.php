<?php

namespace Tests\Feature\MiniApp;

trait InitDataHelper
{
    protected function buildInitData(array $overrides = []): string
    {
        $user = $overrides['user'] ?? [
            'id' => 99001,
            'first_name' => 'Alice',
            'last_name' => 'Test',
            'username' => 'alice',
            'language_code' => 'en',
            'allows_write_to_pm' => true,
        ];

        $params = [
            'auth_date' => $overrides['auth_date'] ?? time(),
            'query_id' => $overrides['query_id'] ?? 'AAH_TEST',
            'user' => is_string($user) ? $user : json_encode($user, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        foreach ($overrides as $k => $v) {
            if (in_array($k, ['user', 'hash', 'skipHash'], true)) continue;
            $params[$k] = $v;
        }

        ksort($params);
        $parts = [];
        foreach ($params as $k => $v) $parts[] = $k . '=' . $v;
        $dataCheckString = implode("\n", $parts);

        $botToken = (string) config('telegram.bot_token');
        $secret = hash_hmac('sha256', $botToken, 'WebAppData', true);

        $hash = $overrides['hash'] ?? hash_hmac('sha256', $dataCheckString, $secret);
        if (!empty($overrides['tamper'])) $hash = str_repeat('0', 64);

        $query = [];
        foreach ($params as $k => $v) {
            $query[] = $k . '=' . rawurlencode($v);
        }
        $query[] = 'hash=' . $hash;
        return implode('&', $query);
    }
}
