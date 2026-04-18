<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VpnClient extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'telegram_user_id',
        'server_id',
        'uuid',
        'sub_id',
        'email',
        'enabled',
        'last_traffic_up',
        'last_traffic_down',
        'last_traffic_synced_at',
        'quota_bytes',
        'quota_bytes_used_cached',
        'expires_at',
        'disabled_reason',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_traffic_up' => 'integer',
            'last_traffic_down' => 'integer',
            'last_traffic_synced_at' => 'datetime',
            'quota_bytes' => 'integer',
            'quota_bytes_used_cached' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isQuotaExhausted(): bool
    {
        if ($this->quota_bytes === null) {
            return false;
        }
        return $this->totalTrafficBytes() >= $this->quota_bytes;
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function telegramUser(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class, 'telegram_user_id', 'telegram_id');
    }

    public function scopeForUser(Builder $q, int $telegramId): Builder
    {
        return $q->where('telegram_user_id', $telegramId);
    }

    public function totalTrafficBytes(): int
    {
        return (int) $this->last_traffic_up + (int) $this->last_traffic_down;
    }
}
