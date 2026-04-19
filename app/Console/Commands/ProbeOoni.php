<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Debug helper: prints the raw OONI /api/v1/aggregation response for a single
 * service URL on the given country/ASN. Useful when the Freedom Map is
 * showing "Unknown" and we need to verify what OONI is actually returning.
 *
 *   php artisan ooni:probe RU AS25513
 *   php artisan ooni:probe US
 *   php artisan ooni:probe RU AS25513 --url=https://signal.org/
 */
class ProbeOoni extends Command
{
    protected $signature = 'ooni:probe {country} {asn?} {--url=} {--days=7}';

    protected $description = 'Fetch and print a raw OONI aggregation result for one URL.';

    public function handle(): int
    {
        $country = strtoupper((string) $this->argument('country'));
        $asn = (string) ($this->argument('asn') ?? '');
        $url = (string) ($this->option('url')
            ?: (config('ooni.services.0.urls.0', 'https://www.instagram.com/')));
        $days = (int) $this->option('days');

        $base = rtrim((string) config('ooni.api_url', 'https://api.ooni.org'), '/');
        $since = Carbon::now('UTC')->subDays($days)->toDateString();
        $until = Carbon::now('UTC')->addDay()->toDateString();

        $params = [
            'test_name' => 'web_connectivity',
            'probe_cc'  => $country,
            'since'     => $since,
            'until'     => $until,
            'input'     => $url,
        ];
        if ($asn !== '') {
            $params['probe_asn'] = preg_replace('/^AS/i', '', strtoupper($asn));
        }

        $this->line('GET ' . $base . '/api/v1/aggregation');
        $this->line('params:');
        foreach ($params as $k => $v) {
            $this->line("  {$k} = {$v}");
        }
        $this->line('---');

        try {
            $resp = Http::timeout((int) config('ooni.timeout', 12))
                ->acceptJson()
                ->withUserAgent('Larastory-VPN-MiniApp/1.0 (+ooni:probe)')
                ->get($base . '/api/v1/aggregation', $params);
        } catch (\Throwable $e) {
            $this->error('Request threw: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->line('status: ' . $resp->status());
        $body = $resp->body();
        $this->line('body:');
        $this->line(substr($body, 0, 2000));
        if (strlen($body) > 2000) {
            $this->line('… (truncated, ' . strlen($body) . ' bytes total)');
        }

        return self::SUCCESS;
    }
}
