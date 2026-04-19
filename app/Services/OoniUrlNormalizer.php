<?php

namespace App\Services;

/**
 * Single source of truth for URL canonicalization across OONI-adjacent code
 * (cache keys, watchlist storage, search inputs, contribute-signal payloads).
 *
 * OONI's /api/v1/aggregation `input` field is byte-sensitive:
 *   https://x.com  != https://x.com/  != https://X.COM/
 * so a consistent canonical form matters both for cache hit rate and for
 * matching measurements against our stored URLs.
 */
class OoniUrlNormalizer
{
    public function normalize(string $input): ?string
    {
        $rules = (array) config('ooni.normalization', []);
        $max   = (int) ($rules['max_url_length'] ?? 512);

        $s = trim($input);
        if ($s === '' || strlen($s) > $max) {
            return null;
        }

        if (!preg_match('~^https?://~i', $s)) {
            $s = 'https://' . $s;
        }

        $parts = parse_url($s);
        if (!is_array($parts) || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }
        if (($rules['force_https'] ?? true) && $scheme === 'http') {
            // Keep http:// for non-default ports (local dev); otherwise promote.
            if (empty($parts['port'])) {
                $scheme = 'https';
            }
        }

        $host = $parts['host'];
        if ($rules['lowercase_host'] ?? true) {
            $host = strtolower($host);
        }
        if (($rules['strip_www'] ?? false) && str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }
        if (!preg_match('/^[a-z0-9.\-]+$/i', $host) || str_contains($host, '..')) {
            return null;
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        $path = $parts['path'] ?? '';
        if (!($rules['allow_path'] ?? true)) {
            $path = '';
        }
        if ($path === '' || $path === '/') {
            // Canonical root: always trailing slash.
            $path = '/';
        } elseif (($rules['strip_trailing_slash'] ?? false) && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        $query = ($rules['strip_query'] ?? true) ? null : ($parts['query'] ?? null);
        $fragment = ($rules['strip_fragment'] ?? true) ? null : ($parts['fragment'] ?? null);

        $out = $scheme . '://' . $host;
        if ($port && !$this->isDefaultPort($scheme, $port)) {
            $out .= ':' . $port;
        }
        $out .= $path;
        if ($query !== null && $query !== '') {
            $out .= '?' . $query;
        }
        if ($fragment !== null && $fragment !== '') {
            $out .= '#' . $fragment;
        }

        return strlen($out) > $max ? null : $out;
    }

    public function hash(string $normalized): string
    {
        return sha1($normalized);
    }

    public function host(string $normalized): ?string
    {
        $parts = parse_url($normalized);
        return is_array($parts) ? ($parts['host'] ?? null) : null;
    }

    private function isDefaultPort(string $scheme, int $port): bool
    {
        return ($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443);
    }
}
