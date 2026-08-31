<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\HttpAssertions;
use DevichanE2E\Support\HttpTester;

final class PublicContentCest
{
    use HttpAssertions;

    public function generatedPagesExposeThePublicSite(HttpTester $I): void
    {
        $pages = [
            '/' => 'DeVichan',
            '/b/' => 'Seed public thread',
            '/b/index.html' => 'Seed public thread',
            '/b/res/1.html' => 'Seed reply',
            '/b/catalog.html' => 'Seed thread',
            '/recent.html' => 'Seed public thread',
        ];

        foreach ($pages as $route => $expectedText) {
            $I->comment("GET {$route}");
            $I->amOnPage($route);
            $this->assertHealthyPage($I);
            $I->see($expectedText);
        }
    }

    public function generatedFeedsAreValidXml(HttpTester $I): void
    {
        $feeds = [
            '/b/index.rss' => '<rss',
            '/sitemap.xml' => '<urlset',
        ];

        foreach ($feeds as $route => $rootElement) {
            $I->comment("GET {$route}");
            $I->amOnPage($route);
            $this->assertHealthyPage($I);
            $I->seeInSource('<?xml');
            $I->seeInSource($rootElement);
        }
    }

    public function auxiliaryPublicEndpointsHaveStableContracts(HttpTester $I): void
    {
        $I->amOnPage('/boards.php');
        $this->assertHealthyPage($I);
        $boards = json_decode($I->grabPageSource(), true, flags: JSON_THROW_ON_ERROR);
        $I->assertIsArray($boards);

        $I->amOnPage('/banner/?board=b');
        $I->seeResponseCodeIs(200);
        $I->assertNotSame('', $I->grabPageSource());

        $I->amOnPage('/player.php?v=%22%3E%3Cscript%3Ealert(1)%3C%2Fscript%3E&t=Video');
        $this->assertHealthyPage($I);
        $I->dontSeeInSource('<script>alert(1)</script>');
        $I->seeInSource('&lt;script&gt;alert(1)&lt;/script&gt;');

        $I->amOnPage('/banned/');
        $this->assertHealthyPage($I);
    }
}
