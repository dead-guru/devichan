<?php

declare(strict_types=1);

namespace DevichanE2E\SmartBuild;

use DevichanE2E\Support\HttpAssertions;
use DevichanE2E\Support\SmartBuildTester;

final class SmartBuildCest
{
    use HttpAssertions;

    public function generatesPublicHtmlRoutes(SmartBuildTester $I): void
    {
        foreach ([
            '/b/' => 'Seed public thread',
            '/b/index.html' => 'Seed public thread',
            '/b/res/1-seed-thread.html' => 'Seed reply',
            '/b/catalog.html' => 'Seed thread',
            '/b/index.rss' => '<rss',
            '/recent.html' => 'Seed public thread',
            '/sitemap.xml' => '<urlset',
        ] as $route => $expectedText) {
            $I->amOnPage($route);
            $this->assertHealthyPage($I);
            $I->seeInSource($expectedText);
        }
    }

    public function generatesPublicJsonRoutes(SmartBuildTester $I): void
    {
        foreach ([
            '/b/0.json',
            '/b/threads.json',
            '/b/catalog.json',
            '/b/res/1.json',
        ] as $route) {
            $I->amOnPage($route);
            $I->seeResponseCodeIs(200);
            $I->assertIsArray(json_decode(
                $I->grabPageSource(),
                true,
                flags: JSON_THROW_ON_ERROR,
            ));
        }
    }

    public function rejectsUnknownAndInvalidRoutes(SmartBuildTester $I): void
    {
        foreach ([
            '/missing/',
            '/b/999.html',
            '/b/res/999999.json',
            '/not-a-route',
        ] as $route) {
            $I->amOnPage($route);
            $I->seeResponseCodeIs(404);
            $I->see('404 Not Found');
        }
    }
}
