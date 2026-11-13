<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\HttpAssertions;
use DevichanE2E\Support\HttpTester;

final class StylesheetCacheCest
{
    use HttpAssertions;

    public function _after(HttpTester $I): void
    {
        $I->resetCookie('stylesheet');
    }

    public function themeUrlsChangeWithTheirContent(HttpTester $I): void
    {
        // the default theme comes from the cookie on dynamic pages
        $I->setCookie('stylesheet', 'Photon');
        $I->amOnPage('/stats.php');
        $this->assertHealthyPage($I);
        preg_match('/var m=(\{.*?\});/', $I->grabPageSource(), $match);
        $themes = array_filter(json_decode($match[1] ?? 'null', true) ?? []);
        $I->assertArrayHasKey('Photon', $themes);

        [, $script] = $this->fetch('/main.js');
        foreach ($themes as $url) {
            $I->assertMatchesRegularExpression('~^/stylesheets/[\w-]+\.css\?v=[0-9a-f]{32}$~', $url);
            // the theme switcher must not fetch the same theme a second time
            $I->assertStringContainsString($url, $script);
            [$cache, $css] = $this->fetch($url);
            $I->assertSame(substr($url, -32), md5($css));
            $I->assertSame('public, max-age=31536000, immutable', $cache);
        }
    }

    public function unversionedAssetsAreRevalidated(HttpTester $I): void
    {
        foreach (['/stylesheets/photon.css', '/main.js'] as $url) {
            $I->assertSame('no-cache', $this->fetch($url)[0], $url);
        }
    }

    /** @return array{string, string} Cache-Control and body */
    private function fetch(string $path): array
    {
        $body = file_get_contents(getenv('E2E_BASE_URL') . $path);
        $headers = array_change_key_case(get_headers(getenv('E2E_BASE_URL') . $path, true));

        return [$headers['cache-control'] ?? '', (string) $body];
    }
}
