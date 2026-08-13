<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\AdminSession;
use DevichanE2E\Support\HttpAssertions;
use DevichanE2E\Support\HttpTester;

final class ModeratorReadRoutesCest
{
    use AdminSession;
    use HttpAssertions;

    public function administratorCanRenderEveryReadPage(HttpTester $I): void
    {
        $this->loginAsAdmin($I);

        $routes = [
            '/mod.php?/',
            '/mod.php?/users',
            '/mod.php?/users/1',
            '/mod.php?/users/new',
            '/mod.php?/new_PM/admin',
            '/mod.php?/PM/1',
            '/mod.php?/PM/1/reply',
            '/mod.php?/inbox',
            '/mod.php?/log',
            '/mod.php?/log/1',
            '/mod.php?/log:admin',
            '/mod.php?/log:b:b',
            '/mod.php?/edit_news',
            '/mod.php?/edit_news/1',
            '/mod.php?/edit_pages',
            '/mod.php?/edit_pages/b',
            '/mod.php?/edit_page/1',
            '/mod.php?/noticeboard',
            '/mod.php?/noticeboard/1',
            '/mod.php?/edit/b',
            '/mod.php?/new-board',
            '/mod.php?/rebuild',
            '/mod.php?/reports',
            '/mod.php?/IP/127.0.0.10',
            '/mod.php?/ban',
            '/mod.php?/bans',
            '/mod.php?/ban-appeals',
            '/mod.php?/recent/25',
            '/mod.php?/search/posts/Seed',
            '/mod.php?/search/IP_notes/Seed',
            '/mod.php?/search/bans/Seed',
            '/mod.php?/search/log/Seed',
            '/mod.php?/b/ban/1',
            '/mod.php?/b/edit/1',
            '/mod.php?/b/edit_raw/1',
            '/mod.php?/themes',
            '/mod.php?/themes/catalog',
            '/mod.php?/config',
            '/mod.php?/config/b',
            '/mod.php?/b/',
            '/mod.php?/b/index.html',
            '/mod.php?/b/catalog.html',
            '/mod.php?/b/res/1.html',
            '/mod.php?/b/res/1+50.html',
            '/mod.php?/b/res/1-seed-thread.html',
            '/mod.php?/b/res/1-seed-thread+50.html',
        ];

        foreach ($routes as $route) {
            $I->comment("GET {$route}");
            $I->amOnPage($route);
            $this->assertHealthyPage($I);
            $I->seeElement('body.is-moderator');
        }
    }

    public function administratorBanApiReturnsJson(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $I->amOnPage('/mod.php?/bans');
        $I->seeResponseCodeIs(200);
        $I->assertSame(1, preg_match(
            '/banlist_init\("([a-f0-9]{8})"/',
            $I->grabPageSource(),
            $matches,
        ));
        $I->haveHttpHeader('Accept-Encoding', 'identity');
        $I->amOnPage('/mod.php?/bans.json/' . $matches[1]);
        $I->seeResponseCodeIs(200);
        $I->assertIsArray(json_decode(
            $I->grabPageSource(),
            true,
            flags: JSON_THROW_ON_ERROR,
        ));
    }

    public function disabledMoveCapabilityRejectsAccess(HttpTester $I): void
    {
        $this->loginAsAdmin($I);

        foreach (['/mod.php?/b/move/1', '/mod.php?/b/move_reply/2'] as $route) {
            $I->amOnPage($route);
            $I->seeResponseCodeIs(500);
            $I->see("You don't have permission to do that.");
        }
    }

    public function secureActionsRenderANoJavascriptConfirmation(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $I->amOnPage('/mod.php?/logout');
        $this->assertHealthyPage($I);
        $I->seeElement('a[href*="?/logout/"]');
    }
}
