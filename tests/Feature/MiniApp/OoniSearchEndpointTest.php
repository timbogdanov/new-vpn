<?php

namespace Tests\Feature\MiniApp;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OoniSearchEndpointTest extends TestCase
{
    use RefreshDatabase;
    use InitDataHelper;

    public function test_search_returns_typeahead_hits_from_config_catalog(): void
    {
        $initData = $this->buildInitData();

        $res = $this->getJson('/api/miniapp/ooni/search?q=reddit', [
            'X-Telegram-Init-Data' => $initData,
        ])->assertOk();

        $res->assertJsonStructure([
            'results' => [['label', 'url', 'urlHash', 'host', 'source']],
        ]);
        $hosts = collect($res->json('results'))->pluck('host')->all();
        $this->assertContains('www.reddit.com', $hosts);
    }

    public function test_search_accepts_free_form_domain_echo(): void
    {
        $initData = $this->buildInitData();

        $res = $this->getJson('/api/miniapp/ooni/search?q=myveryobscuredomain.example', [
            'X-Telegram-Init-Data' => $initData,
        ])->assertOk();

        $urls = collect($res->json('results'))->pluck('url')->all();
        $this->assertContains('https://myveryobscuredomain.example/', $urls);
    }

    public function test_search_requires_query_parameter(): void
    {
        $initData = $this->buildInitData();
        $this->getJson('/api/miniapp/ooni/search', [
            'X-Telegram-Init-Data' => $initData,
        ])->assertStatus(422);
    }
}
