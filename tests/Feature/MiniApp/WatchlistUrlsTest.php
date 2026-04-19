<?php

namespace Tests\Feature\MiniApp;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WatchlistUrlsTest extends TestCase
{
    use RefreshDatabase;
    use InitDataHelper;

    public function test_put_watchlist_accepts_urls_alongside_services(): void
    {
        $init = $this->buildInitData();

        $res = $this->putJson('/api/miniapp/ooni/watchlist', [
            'services' => ['youtube'],
            'urls' => ['reddit.com', 'https://twitter.com/'],
        ], [
            'X-Telegram-Init-Data' => $init,
        ])->assertOk();

        $res->assertJsonPath('services.0', 'youtube');
        $this->assertContains('https://reddit.com/', $res->json('urls'));
        $this->assertContains('https://twitter.com/', $res->json('urls'));
    }

    public function test_put_watchlist_rejects_bad_url(): void
    {
        $init = $this->buildInitData();

        // Malformed URLs are silently dropped (normalizer returns null); array
        // can be empty, but at least one of {services, urls} must be present.
        $this->putJson('/api/miniapp/ooni/watchlist', [
            'urls' => ['this is not a url'],
        ], [
            'X-Telegram-Init-Data' => $init,
        ])->assertOk()->assertJsonPath('urls', []);
    }
}
