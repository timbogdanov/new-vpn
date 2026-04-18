<?php

namespace Tests\Feature\MiniApp;

use Tests\TestCase;

/**
 * Guard against drifted translations: every key in the EN bundle must exist in
 * RU, and vice-versa. Lives in tests/ so CI catches the gap on PR.
 *
 * Covers both the JS Mini App locales and the Laravel `lang/{en,ru}/*.php`
 * trees.
 */
class LocaleParityTest extends TestCase
{
    public function test_miniapp_json_locales_parity(): void
    {
        $en = $this->flatten($this->loadJson(base_path('resources/js/miniapp/locales/en.json')));
        $ru = $this->flatten($this->loadJson(base_path('resources/js/miniapp/locales/ru.json')));

        $missingInRu = array_diff(array_keys($en), array_keys($ru));
        $missingInEn = array_diff(array_keys($ru), array_keys($en));

        $this->assertEmpty($missingInRu, "Keys missing in ru.json: \n - " . implode("\n - ", $missingInRu));
        $this->assertEmpty($missingInEn, "Keys missing in en.json: \n - " . implode("\n - ", $missingInEn));
    }

    public function test_lang_php_locales_parity(): void
    {
        $enFiles = glob(base_path('lang/en/*.php')) ?: [];
        $ruFiles = glob(base_path('lang/ru/*.php')) ?: [];

        $enNames = array_map(fn ($p) => basename($p), $enFiles);
        $ruNames = array_map(fn ($p) => basename($p), $ruFiles);

        $this->assertSame(
            sort($enNames) ?: $enNames,
            sort($ruNames) ?: $ruNames,
            'lang/en and lang/ru must contain the same files',
        );

        foreach ($enFiles as $enPath) {
            $name = basename($enPath);
            $ruPath = base_path('lang/ru/' . $name);
            if (!file_exists($ruPath)) {
                $this->fail("Missing lang/ru/{$name}");
            }

            $en = $this->flatten(require $enPath);
            $ru = $this->flatten(require $ruPath);

            $missingInRu = array_diff(array_keys($en), array_keys($ru));
            $missingInEn = array_diff(array_keys($ru), array_keys($en));

            $this->assertEmpty($missingInRu, "{$name}: keys missing in ru: " . implode(', ', $missingInRu));
            $this->assertEmpty($missingInEn, "{$name}: keys missing in en: " . implode(', ', $missingInEn));
        }
    }

    private function loadJson(string $path): array
    {
        $raw = file_get_contents($path);
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    }

    /** @return array<string, mixed> */
    private function flatten(array $tree, string $prefix = ''): array
    {
        $out = [];
        foreach ($tree as $k => $v) {
            $key = $prefix === '' ? (string) $k : $prefix . '.' . $k;
            if (is_array($v)) {
                $out += $this->flatten($v, $key);
            } else {
                $out[$key] = $v;
            }
        }
        return $out;
    }
}
