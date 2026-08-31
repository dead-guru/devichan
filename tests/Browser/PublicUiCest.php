<?php

declare(strict_types=1);


namespace DevichanE2E\Browser;

use DevichanE2E\Support\BrowserTester;

final class PublicUiCest
{
    public function boardNavigationAndPostComposerWork(BrowserTester $I): void
    {
        $I->amOnPage('/b/');
        $I->see('Random');
        $I->seeElementInDOM('form[name="post"]');

        $I->click('h1.open-form a');
        $I->waitForElementVisible('form[name="post"]');

        $I->click('a[title="Catalog"]');
        $I->waitForElement('body');
        $I->seeInCurrentUrl('/b/catalog.html');
    }

    public function replyReferencePopulatesTheComposer(BrowserTester $I): void
    {
        $I->amOnPage('/b/res/1.html');
        $I->click('a.post_no[href*="#q2"]');
        $I->waitForElementVisible('form[name="post"] textarea[name="body"]');
        $I->seeInField('form[name="post"] textarea[name="body"]', ">>2\n");
    }

    public function secretBoardLoginWorksInTheBrowser(BrowserTester $I): void
    {
        $I->resetCookie('board_auth');
        $I->amOnPage('/sec/');
        $I->waitForElementVisible('form input[name="password"]');
        $I->fillField('form input[name="password"]', 'secret');
        $I->click('form input[type="submit"]');
        $I->waitForElement('body');
        $I->seeInCurrentUrl('/sec/');
        $I->see('Secret board content');
    }

    public function moderatorCanLoginAndOpenTheConfigEditor(BrowserTester $I): void
    {
        $I->amOnPage('/mod/');
        $I->fillField('input[name="username"]', 'admin');
        $I->fillField('input[name="password"]', 'password');
        $I->click('input[name="login"]');
        $I->waitForElement('body.is-moderator');

        $I->amOnPage('/mod.php?/config');
        $I->waitForElement('form');
        $I->seeElement('input[name="save"]');
    }
}
