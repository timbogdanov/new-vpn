<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityProbeSignal extends Model
{
    protected $table = 'community_probe_signals';

    // Our own `created_at` column exists but there's no updated_at on this
    // append-only signal table. Disable Eloquent's auto-maintenance of both.
    public $timestamps = false;

    protected $fillable = [
        'user_hash',
        'country_code',
        'asn',
        'service_key',
        'url',
        'result',
        'observed_at',
    ];

    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public static function hashForUser(int $telegramId): string
    {
        return hash_hmac('sha256', (string) $telegramId, (string) config('app.key'));
    }
}
