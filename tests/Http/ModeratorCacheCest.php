<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\AdminSession;
use DevichanE2E\Support\HttpAssertions;
use DevichanE2E\Support\HttpTester;

final class ModeratorCacheCest
{
    use AdminSession;
    use HttpAssertions;

    public function administratorCanUseInvalidateAndFlushThePhpCache(HttpTester $I): void
    {
        $I->setCookie('e2e_cache', 'php');
        $this->loginAsAdmin($I);

        $I->amOnPage('/mod.php?/');
        $this->assertHealthyPage($I);
        $I->see('Панель керування');

        $I->amOnPage('/mod.php?/themes/catalog');
        $I->submitForm('form[action=""]', [
            'title' => 'Catalog',
            'boards' => 'b',
            'update_on_posts' => 'on',
            'use_tooltipster' => 'on',
            'install' => 'Install theme',
        ]);
        $this->assertHealthyPage($I);

        $I->amOnPage('/mod.php?/rebuild');
        $I->submitForm('form[action="?/rebuild"]', [
            'rebuild' => 'Rebuild',
            'rebuild_cache' => 'on',
        ]);
        $this->assertHealthyPage($I);
        $I->see('Flushing cache');
        $I->see('Clearing template cache');
    }
}
