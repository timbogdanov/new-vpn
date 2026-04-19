<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class TelegramUser extends Model
{
    protected $table = 'telegram_users';
    protected $primaryKey = 'telegram_id';
    public $incrementing = false;
    protected $keyType = 'integer';

    protected $fillable = [
        'telegram_id',
        'username',
        'first_name',
        'last_name',
        'language_code',
        'photo_url',
        'allows_write_to_pm',
        'sub_token',
        'last_active_at',
        'trial_used_at',
        'onboarded_at',
        'admin_notes',
        'ooni_watchlist',
        'ooni_watchlist_urls',
        'ooni_watchlist_snapshot',
        'ooni_contribute',
        'ooni_contribute_acked_at',
        'ooni_last_country',
        'ooni_last_asn',
    ];

    protected function casts(): array
    {
        return [
            'allows_write_to_pm' => 'boolean',
            'last_active_at' => 'datetime',
            'trial_used_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'ooni_watchlist' => 'array',
            'ooni_watchlist_urls' => 'array',
            'ooni_watchlist_snapshot' => 'array',
            'ooni_contribute' => 'boolean',
            'ooni_contribute_acked_at' => 'datetime',
        ];
    }

    public function vpnClients(): HasMany
    {
        return $this->hasMany(VpnClient::class, 'telegram_user_id', 'telegram_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'telegram_user_id', 'telegram_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'telegram_user_id', 'telegram_id');
    }

    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()->active()->latest('started_at')->first();
    }

    public function getOrGenerateSubToken(): string
    {
        if (!$this->sub_token) {
            $this->sub_token = Str::random(32);
            $this->save();
        }
        return $this->sub_token;
    }

    public function rotateSubToken(): string
    {
        $this->sub_token = Str::random(32);
        $this->save();
        return $this->sub_token;
    }

    public function touchActivityIfStale(int $staleSeconds = 60): void
    {
        if (!$this->last_active_at || $this->last_active_at->lt(Carbon::now()->subSeconds($staleSeconds))) {
            $this->forceFill(['last_active_at' => Carbon::now()])->saveQuietly();
        }
    }

    public function displayName(): string
    {
        return trim($this->first_name . ' ' . ($this->last_name ?: '')) ?: ($this->username ?: 'Friend');
    }
}
