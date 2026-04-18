<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Subscription extends Model
{
    public const STATUS_TRIALING = 'trialing';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_REFUNDED = 'refunded';

    public const TIER_TRIAL = 'trial';
    public const TIER_PRO = 'pro';
    public const TIER_PRO_ANNUAL = 'pro_annual';

    protected $fillable = [
        'telegram_user_id',
        'plan_key',
        'tier',
        'status',
        'started_at',
        'expires_at',
        'stars_paid',
        'telegram_payment_charge_id',
        'telegram_provider_payment_charge_id',
        'auto_renew',
        'canceled_at',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'canceled_at' => 'datetime',
            'refunded_at' => 'datetime',
            'auto_renew' => 'boolean',
            'stars_paid' => 'integer',
        ];
    }

    public function telegramUser(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class, 'telegram_user_id', 'telegram_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeForUser(Builder $q, int $telegramId): Builder
    {
        return $q->where('telegram_user_id', $telegramId);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_TRIALING, self::STATUS_ACTIVE])
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', Carbon::now());
            });
    }

    public function isActive(): bool
    {
        if (!in_array($this->status, [self::STATUS_TRIALING, self::STATUS_ACTIVE], true)) {
            return false;
        }
        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function isTrial(): bool
    {
        return $this->tier === self::TIER_TRIAL;
    }

    public function planConfig(): array
    {
        return (array) config('billing.plans.' . $this->plan_key, []);
    }

    public function trafficCapBytes(): ?int
    {
        $cap = $this->planConfig()['traffic_bytes'] ?? null;
        return $cap === null ? null : (int) $cap;
    }
}
