<?php

namespace Tests\Unit;

use App\Services\OoniUrlNormalizer;
use Tests\TestCase;

class OoniUrlNormalizerTest extends TestCase
{
    private OoniUrlNormalizer $n;

    protected function setUp(): void
    {
        parent::setUp();
        $this->n = new OoniUrlNormalizer();
    }

    /**
     * @dataProvider canonicalCases
     */
    public function test_canonical_forms(string $input, ?string $expected): void
    {
        $this->assertSame($expected, $this->n->normalize($input));
    }

    public static function canonicalCases(): array
    {
        return [
            'bare domain'            => ['reddit.com', 'https://reddit.com/'],
            'uppercase host'         => ['Reddit.COM', 'https://reddit.com/'],
            'preserves www'          => ['www.reddit.com', 'https://www.reddit.com/'],
            'strips query+fragment'  => ['https://x.com/elon?utm=tg#bio', 'https://x.com/elon'],
            'keeps non-default port' => ['http://example.local:8080/path/', 'http://example.local:8080/path/'],
            'promotes http root'     => ['http://reddit.com', 'https://reddit.com/'],
            'not a url'              => ['not a url', null],
            'empty'                  => ['', null],
            'huge'                   => [str_repeat('a', 600), null],
            'double dot host'        => ['https://foo..bar.com/', null],
        ];
    }

    public function test_hash_is_deterministic(): void
    {
        $url = 'https://reddit.com/';
        $this->assertSame(sha1($url), $this->n->hash($url));
        $this->assertSame($this->n->hash($url), $this->n->hash($url));
    }
}
