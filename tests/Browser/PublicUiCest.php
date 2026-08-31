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

    public function threadFileGalleryListsEveryFileAndKeepsPostLinks(BrowserTester $I): void
    {
        $I->amOnPage('/b/res/1.html');
        $I->waitForElement('.thread-file-list');
        $I->seeNumberOfElements('.thread-file-list__item', 3);
        $I->seeElement('.thread-file-list__item a[href$="/b/src/1700000000001.jpg"]');
        $I->seeElement('.thread-file-list__item a[href$="/b/src/1700000000003.txt"]');
        $I->seeElement('.thread-file-list__item a[href$="#1"]');
        $I->seeElement('.thread-file-list__item a[href$="#2"]');

        $I->executeJS(<<<'JS'
            const post = document.querySelector('#reply_2').cloneNode(true);
            post.querySelector('.post_anchor').id = '3';
            $(document).trigger('new_post', post);
            JS);
        $I->seeNumberOfElements('.thread-file-list__item', 5);
        $I->seeElement('.thread-file-list__item a[href$="#3"]');
    }

    public function externalPageCanRenderAThreadGalleryAcrossOrigins(BrowserTester $I): void
    {
        $I->amOnUrl('http://external-caddy/tests/fixtures/external-gallery.html');
        $I->waitForElement('#all-files .thread-file', 10);
        $I->seeNumberOfElements('#all-files .thread-file', 3);
        $I->seeElement('#all-files .thread-file__filename');
        $I->seeElement('#all-files a[href$="/b/src/1700000000001.jpg"]');
        $I->seeElement('#all-files a[href$="/b/src/1700000000003.txt"]');
        $I->seeElement('#all-files .thread-file__post-link[href$="/b/res/1.html#1"]');
        $I->seeElement('#all-files .thread-file__post-link[href$="/b/res/1.html#2"]');
        $I->seeNumberOfElements('#visual-files .thread-file', 2);
        $I->seeElement('#visual-files img[width="80"]');
        $I->waitForJS(<<<'JS'
            return Array.from(document.querySelectorAll('#visual-files img'))
                .every((image) => image.complete && image.naturalWidth > 0);
            JS, 10);
        $I->dontSeeElement('[data-vichan-thread-files] [role="alert"]');
    }

}
