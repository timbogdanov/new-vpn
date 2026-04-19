<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the ooni_url_suggestions table that backs the typeahead for the
 * universal URL search. The catalog from config('ooni.services') is merged in
 * automatically; this seeder adds the long tail of commonly-probed URLs that
 * aren't in the curated grid but show up frequently in OONI reports.
 */
class OoniUrlSuggestionsSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $rows = [];

        // Fold the curated catalog in with a high base popularity.
        foreach ((array) config('ooni.services', []) as $svc) {
            foreach ((array) ($svc['urls'] ?? []) as $url) {
                $host = parse_url($url, PHP_URL_HOST);
                if (!$host) {
                    continue;
                }
                $rows[$url] = [
                    'host' => $host,
                    'url' => $url,
                    'country_code' => null,
                    'popularity' => 100,
                    'source' => 'seed',
                    'last_seen_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Additional long-tail URLs. `country_code` hints at typeahead relevance
        // but is not a hard filter.
        $extras = [
            // Global messaging / social
            ['https://threads.net/',             null],
            ['https://mastodon.social/',         null],
            ['https://bsky.app/',                null],
            ['https://telegra.ph/',              null],

            // News (EN)
            ['https://www.nytimes.com/',         null],
            ['https://www.bloomberg.com/',       null],
            ['https://www.washingtonpost.com/',  null],
            ['https://www.economist.com/',       null],
            ['https://www.ft.com/',              null],
            ['https://apnews.com/',              null],

            // Russia / CIS independent press
            ['https://meduza.io/en',             'RU'],
            ['https://zona.media/',              'RU'],
            ['https://www.kommersant.ru/',       'RU'],
            ['https://holod.media/',             'RU'],
            ['https://nastoyashcheevremya.ru/',  'RU'],
            ['https://www.rferl.org/',           'RU'],

            // Iran
            ['https://www.bbc.com/persian',      'IR'],
            ['https://www.iranintl.com/',        'IR'],
            ['https://www.radiofarda.com/',      'IR'],
            ['https://www.voanews.com/p/6060.html', 'IR'],

            // China
            ['https://www.google.com.hk/',       'CN'],
            ['https://zh.wikipedia.org/',        'CN'],

            // Circumvention + privacy
            ['https://bridges.torproject.org/',  null],
            ['https://www.eff.org/',             null],
            ['https://snowflake.torproject.org/', null],
            ['https://www.accessnow.org/',       null],
            ['https://ooni.org/',                null],
            ['https://explorer.ooni.org/',       null],

            // Dev / productivity
            ['https://stackoverflow.com/',       null],
            ['https://news.ycombinator.com/',    null],
            ['https://gitlab.com/',              null],
            ['https://bitbucket.org/',           null],
            ['https://vercel.com/',              null],

            // LGBTQ+ / civil society often targeted
            ['https://www.hrw.org/',             null],
            ['https://amnesty.org/',             null],
        ];

        foreach ($extras as [$url, $cc]) {
            $host = parse_url($url, PHP_URL_HOST);
            if (!$host) {
                continue;
            }
            $rows[$url] = [
                'host' => $host,
                'url' => $url,
                'country_code' => $cc,
                'popularity' => 25,
                'source' => 'seed',
                'last_seen_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('ooni_url_suggestions')->upsert(
            array_values($rows),
            ['url'],
            ['host', 'country_code', 'popularity', 'source', 'last_seen_at', 'updated_at'],
        );
    }
}
