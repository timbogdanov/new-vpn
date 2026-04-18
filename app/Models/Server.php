<?php

namespace App\Models;

use App\DTO\ServerDTO;
use App\DTO\XuiCredentialsDTO;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'country',
        'country_code',
        'city',
        'flag_emoji',
        'host',
        'port',
        'protocol',
        'inbound_id',
        'xui_host',
        'xui_port',
        'xui_path',
        'xui_username',
        'xui_password',
        'reality_public_key',
        'reality_short_id',
        'reality_sni',
        'reality_fingerprint',
        'reality_spider_x',
        'subscription_host',
        'subscription_port',
        'is_active',
        'is_coming_soon',
        'capacity_clients',
        'load_percent',
        'ping_ms',
        'stats_updated_at',
        'last_error',
        'tags',
        'description',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'xui_username' => 'encrypted',
            'xui_password' => 'encrypted',
            'tags' => 'array',
            'is_active' => 'boolean',
            'is_coming_soon' => 'boolean',
            'port' => 'integer',
            'inbound_id' => 'integer',
            'xui_port' => 'integer',
            'subscription_port' => 'integer',
            'capacity_clients' => 'integer',
            'load_percent' => 'integer',
            'ping_ms' => 'integer',
            'sort_order' => 'integer',
            'stats_updated_at' => 'datetime',
        ];
    }

    public function vpnClients(): HasMany
    {
        return $this->hasMany(VpnClient::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeAvailable(Builder $q): Builder
    {
        return $q->where('is_active', true)->where('is_coming_soon', false);
    }

    public function xuiCredentials(): XuiCredentialsDTO
    {
        return new XuiCredentialsDTO(
            host: $this->xui_host ?: $this->host,
            port: (int) ($this->xui_port ?: 2053),
            path: (string) ($this->xui_path ?? ''),
            username: (string) $this->xui_username,
            password: (string) $this->xui_password,
            inboundId: (int) $this->inbound_id,
        );
    }

    public function subscriptionBaseUrl(): string
    {
        // Must be the public primary domain — xui_host is the internal docker
        // service name (e.g. `3x-ui`), never reachable by end users.
        $host = $this->subscription_host ?: config('vpn.primary_domain');
        $port = $this->subscription_port ?: config('vpn.subscription_port', 2096);

        $portSegment = (int) $port === 443 ? '' : ':' . $port;

        return "https://{$host}{$portSegment}/sub";
    }

    public function toPublicDTO(): ServerDTO
    {
        return new ServerDTO(
            slug: $this->slug,
            name: $this->name,
            country: $this->country,
            countryCode: strtoupper($this->country_code),
            city: $this->city,
            flagEmoji: $this->flag_emoji ?: self::flagFromCode($this->country_code),
            tags: $this->tags ?? [],
            loadPercent: $this->load_percent,
            pingMs: $this->ping_ms,
            isComingSoon: (bool) $this->is_coming_soon,
            description: $this->description,
            protocol: $this->protocol,
            hostPreview: $this->maskedHost(),
            capacityClients: $this->capacity_clients,
            statsUpdatedAt: $this->stats_updated_at?->toIso8601String(),
        );
    }

    public static function flagFromCode(?string $code): string
    {
        if (!$code || strlen($code) !== 2) {
            return '';
        }
        $code = strtoupper($code);
        return mb_chr(ord($code[0]) - ord('A') + 0x1F1E6)
             . mb_chr(ord($code[1]) - ord('A') + 0x1F1E6);
    }

    protected function maskedHost(): string
    {
        if (!$this->host) {
            return '';
        }
        $parts = explode('.', $this->host);
        if (count($parts) < 2) {
            return $this->host;
        }
        $parts[0] = str_repeat('*', max(3, strlen($parts[0])));
        return implode('.', $parts);
    }
}
