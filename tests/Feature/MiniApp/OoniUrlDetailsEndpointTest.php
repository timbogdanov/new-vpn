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
            // Daily timeseries
            'api.ooni.org/api/v1/aggregation?*axis_x=measurement_start_day*' => Http::response([
                'v' => 0,
                'result' => [
                    ['measurement_start_day' => now('UTC')->subDays(2)->toDateString(), 'measurement_count' => 10, 'ok_count' => 2, 'anomaly_count' => 6, 'confirmed_count' => 2, 'failure_count' => 0],
                    ['measurement_start_day' => now('UTC')->subDay()->toDateString(),  'measurement_count' => 12, 'ok_count' => 3, 'anomaly_count' => 5, 'confirmed_count' => 4, 'failure_count' => 0],
                ],
            ]),
            // Per-ASN breakdown
            'api.ooni.org/api/v1/aggregation?*axis_x=probe_asn*' => Http::response([
                'v' => 0,
                'result' => [
                    ['probe_asn' => 25513, 'measurement_count' => 12, 'ok_count' => 1, 'anomaly_count' => 6, 'confirmed_count' => 5, 'failure_count' => 0],
                    ['probe_asn' => 8402,  'measurement_count' => 4,  'ok_count' => 4, 'anomaly_count' => 0, 'confirmed_count' => 0, 'failure_count' => 0],
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
                'asnBreakdown' => [['asn', 'measurements', 'status']],
                'recentMeasurements' => [['measurementUid', 'probeAsn', 'anomaly']],
                'freshAt',
                'lookbackDays',
            ],
        ]);

        $res->assertJsonPath('result.host', 'www.reddit.com');
        // Timeseries should be zero-padded to exactly `days` points.
        $this->assertCount(30, $res->json('result.timeseries'));
        // ASN list is sorted most-blocked first.
        $this->assertSame('AS25513', $res->json('result.asnBreakdown.0.asn'));
        // Confirmed counts drive the verdict to blocked.
        $this->assertSame('blocked', $res->json('result.verdictStatus'));
        $this->assertSame('confirmed_block', $res->json('result.verdictReason'));
    }

    public function test_details_rejects_invalid_url(): void
    {
        $initData = $this->buildInitData();
        $this->getJson('/api/miniapp/ooni/url-details?url=not+a+url&country=RU', [
            'X-Telegram-Init-Data' => $initData,
        ])->assertStatus(422);
    }
}
