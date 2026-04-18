<?php

namespace App\Models;

use App\Models\TelegramUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Announcement extends Model
{
    public const TARGET_ALL = 'all';
    public const TARGET_TIER_PRO = 'tier:pro';
    public const TARGET_LANG_RU = 'lang:ru';
    public const TARGET_LANG_EN = 'lang:en';

    protected $fillable = [
        'title',
        'body_md',
        'severity',
        'target',
        'starts_at',
        'ends_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function scopeActiveAt(Builder $q, Carbon $when): Builder
    {
        return $q
            ->where(function ($q) use ($when) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $when);
            })
            ->where(function ($q) use ($when) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', $when);
            });
    }

    public function appliesTo(TelegramUser $user): bool
    {
        if ($this->target === self::TARGET_ALL || $this->target === '') {
            return true;
        }
        if (str_starts_with($this->target, 'lang:')) {
            return $user->language_code === substr($this->target, 5);
        }
        if (str_starts_with($this->target, 'tier:')) {
            $sub = $user->activeSubscription();
            return $sub && $sub->tier === substr($this->target, 5);
        }
        return false;
    }
}
