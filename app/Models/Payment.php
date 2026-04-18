<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'telegram_user_id',
        'subscription_id',
        'plan_key',
        'stars_amount',
        'currency',
        'telegram_payment_charge_id',
        'provider_payment_charge_id',
        'invoice_payload',
        'raw_payload',
        'status',
        'paid_at',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'stars_amount' => 'integer',
        ];
    }

    public function telegramUser(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class, 'telegram_user_id', 'telegram_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function scopeForUser(Builder $q, int $telegramId): Builder
    {
        return $q->where('telegram_user_id', $telegramId);
    }
}
