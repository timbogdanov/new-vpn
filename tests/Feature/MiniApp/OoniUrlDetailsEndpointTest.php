<?php

namespace Tests\Feature\MiniApp;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OoniUrlDetailsEndpointTest extends TestCase
{
    use RefreshDatabase;
    use InitDataHelper;

    public function test_details_returns_structured_payload_with_timeseries_and_asn_breakdown(): void
    {
        $initData = $this->buildInitData();

        Http::fake([
            // Per-ASN breakdown (match before measurement_start_day so the specific
            // matcher wins over the generic one).
            'api.ooni.org/api/v1/aggregation?*axis_x=probe_asn*' => Http::response([
                'v' => 0,
                'result' => [
                    ['probe_asn' => 25513, 'probe_asn_name' => 'MTS', 'measurement_count' => 12, 'ok_count' => 1, 'anomaly_count' => 6, 'confirmed_count' => 5, 'failure_count' => 0],
                    ['probe_asn' => 8402,  'probe_asn_name' => 'Beeline', 'measurement_count' => 4,  'ok_count' => 4, 'anomaly_count' => 0, 'confirmed_count' => 0, 'failure_count' => 0],
                ],
            ]),
            // Per-country breakdown (global, no probe_cc)
            'api.ooni.org/api/v1/aggregation?*axis_x=probe_cc*' => Http::response([
                'v' => 0,
                'result' => [
                    ['probe_cc' => 'RU', 'measurement_count' => 500, 'ok_count' => 100, 'anomaly_count' => 300, 'confirmed_count' => 100, 'failure_count' => 0],
                    ['probe_cc' => 'BY', 'measurement_count' => 40,  'ok_count' => 10,  'anomaly_count' => 25,  'confirmed_count' => 5,   'failure_count' => 0],
                    ['probe_cc' => 'IR', 'measurement_count' => 200, 'ok_count' => 20,  'anomaly_count' => 150, 'confirmed_count' => 30,  'failure_count' => 0],
                    ['probe_cc' => 'DE', 'measurement_count' => 80,  'ok_count' => 80,  'anomaly_count' => 0,   'confirmed_count' => 0,   'failure_count' => 0],
                    ['probe_cc' => 'KZ', 'measurement_count' => 20,  'ok_count' => 15,  'anomaly_count' => 5,   'confirmed_count' => 0,   'failure_count' => 0],
                ],
            ]),
            // Daily timeseries (last matcher so it only wins when axis_x=probe_{asn,cc} didn't)
            'api.ooni.org/api/v1/aggregation?*axis_x=measurement_start_day*' => Http::response([
                'v' => 0,
                'result' => [
                    ['measurement_start_day' => now('UTC')->subDays(2)->toDateString(), 'measurement_count' => 10, 'ok_count' => 2, 'anomaly_count' => 6, 'confirmed_count' => 2, 'failure_count' => 0],
                    ['measurement_start_day' => now('UTC')->subDay()->toDateString(),  'measurement_count' => 12, 'ok_count' => 3, 'anomaly_count' => 5, 'confirmed_count' => 4, 'failure_count' => 0],
                ],
            ]),
            // Recent measurements
            'api.ooni.org/api/v1/measurements*' => Http::response([
                'metadata' => ['count' => 2, 'pages' => 1],
                'results' => [
                    ['measurement_uid' => 'abc', 'report_id' => 'r1', 'probe_asn' => 25513, 'probe_cc' => 'RU', 'test_name' => 'web_connectivity', 'input' => 'https://www.reddit.com/', 'measurement_start_time' => now()->subHour()->toIso8601String(), 'anomaly' => true, 'confirmed' => true, 'failure' => false, 'measurement_url' => 'https://explorer.ooni.org/m/abc'],
                    ['measurement_uid' => 'def', 'report_id' => 'r2', 'probe_asn' => 25513, 'probe_cc' => 'RU', 'test_name' => 'web_connectivity', 'input' => 'https://www.reddit.com/', 'measurement_start_time' => now()->subHours(2)->toIso8601String(), 'anomaly' => false, 'confirmed' => false, 'failure' => false, 'measurement_url' => 'https://explorer.ooni.org/m/def'],
                ],
            ]),
        ]);

        $res = $this->getJson(
            '/api/miniapp/ooni/url-details?url=' . urlencode('https://www.reddit.com/') . '&country=RU&asn=AS25513&days=30',
            ['X-Telegram-Init-Data' => $initData],
        )->assertOk();

        $res->assertJsonStructure([
            'result' => [
                'url', 'host', 'urlHash', 'countryCode', 'asn',
                'verdictStatus', 'verdictReason',
                'measurements', 'confirmed', 'anomaly', 'ok', 'failure',
                'timeseries' => [['date', 'measurements', 'anomalyRatio']],
                'asnBreakdown' => [['asn', 'friendlyName', 'networkType', 'measurements', 'status']],
                'countryBreakdown' => [['countryCode', 'measurements', 'status', 'isRegional']],
                'recentMeasurements' => [['measurementUid', 'probeAsn', 'anomaly']],
                'aggregated' => [
                    'totalChecks', 'blockedChecks', 'okChecks', 'confirmedBlocks',
                    'windowDays', 'blockPercent', 'trendDirection',
                ],
                'freshAt',
                'lookbackDays',
            ],
        ]);

        $res->assertJsonPath('result.host', 'www.reddit.com');
        // Timeseries should be zero-padded to exactly `days` points.
        $this->assertCount(30, $res->json('result.timeseries'));
        // ASN list is sorted most-blocked first and enriched with friendly names.
        $this->assertSame('AS25513', $res->json('result.asnBreakdown.0.asn'));
        $this->assertSame('MTS', $res->json('result.asnBreakdown.0.friendlyName'));
        $this->assertSame('mobile', $res->json('result.asnBreakdown.0.networkType'));
        // Confirmed counts drive the verdict to blocked.
        $this->assertSame('blocked', $res->json('result.verdictStatus'));
        $this->assertSame('confirmed_block', $res->json('result.verdictReason'));

        // Country breakdown: user's own country (RU) excluded; regional peers
        // (BY, KZ) marked isRegional=true; DE (clean) still lands in the list
        // ahead of the limit, IR (very blocked) in worst-elsewhere.
        $countries = collect($res->json('result.countryBreakdown'));
        $this->assertFalse($countries->contains('countryCode', 'RU'), "user's own country should be excluded");
        $this->assertTrue($countries->firstWhere('countryCode', 'BY')['isRegional'] ?? false);
        $this->assertTrue($countries->firstWhere('countryCode', 'KZ')['isRegional'] ?? false);
        $this->assertNotNull($countries->firstWhere('countryCode', 'IR'), 'IR should appear as worst-elsewhere');

        // Aggregated block is present with a sensible trendDirection.
        $this->assertIsInt($res->json('result.aggregated.totalChecks'));
        $this->assertContains($res->json('result.aggregated.trendDirection'), ['worsening', 'improving', 'steady']);
    }

    public function test_details_rejects_invalid_url(): void
    {
        $initData = $this->buildInitData();
        $this->getJson('/api/miniapp/ooni/url-details?url=not+a+url&country=RU', [
            'X-Telegram-Init-Data' => $initData,
        ])->assertStatus(422);
    }
}
