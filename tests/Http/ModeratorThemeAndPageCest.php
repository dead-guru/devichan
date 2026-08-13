<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\AdminSession;
use DevichanE2E\Support\HttpAssertions;
use DevichanE2E\Support\HttpTester;

final class ModeratorThemeAndPageCest
{
    use AdminSession;
    use HttpAssertions;

    public function administratorCanManageABoardStaticPage(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $pageName = 'e2eboardpage';

        $I->amOnPage('/mod.php?/edit_pages/b');
        $I->submitForm('form[method="POST"]', [
            'page' => $pageName,
            'title' => 'E2E board page',
        ]);
        $this->assertHealthyPage($I);
        $pageId = (int) $I->grabFromDatabase('pages', 'id', [
            'board' => 'b',
            'name' => $pageName,
        ]);

        $I->amOnPage('/mod.php?/edit_page/' . $pageId);
        $I->submitForm('form[method="POST"]', [
            'method' => 'infinity',
            'content' => '**E2E board page body**',
        ]);
        $this->assertHealthyPage($I);

        $I->amOnPage('/b/' . $pageName . '.html');
        $this->assertHealthyPage($I);
        $I->see('E2E board page body');

        $I->amOnPage('/mod.php?/edit_pages/b');
        $deleteUrl = $I->grabAttributeFrom(
            'a[href*="edit_pages/delete/' . $pageName . '/b/"]',
            'href',
        );
        $I->amOnPage($deleteUrl);
        $this->assertHealthyPage($I);
        $I->dontSeeInDatabase('pages', ['id' => $pageId]);
    }

    public function administratorCanUninstallAndInstallATheme(HttpTester $I): void
    {
        $this->loginAsAdmin($I);

        $I->amOnPage('/mod.php?/themes');
        $uninstallUrl = $I->grabAttributeFrom(
            'a[href*="themes/catalog/uninstall/"]',
            'href',
        );
        $I->amOnPage($uninstallUrl);
        $this->assertHealthyPage($I);
        $I->dontSeeInDatabase('theme_settings', ['theme' => 'catalog']);

        $I->amOnPage('/mod.php?/themes/catalog');
        $I->submitForm('form[action=""]', [
            'title' => 'Catalog',
            'boards' => 'b',
            'update_on_posts' => 'on',
            'use_tooltipster' => 'on',
            'install' => 'Install theme',
        ]);
        $this->assertHealthyPage($I);
        $I->seeInDatabase('theme_settings', [
            'theme' => 'catalog',
            'name' => 'boards',
            'value' => 'b',
        ]);
    }
}
