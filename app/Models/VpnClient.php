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
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_traffic_up' => 'integer',
            'last_traffic_down' => 'integer',
            'last_traffic_synced_at' => 'datetime',
        ];
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
