<?php

declare(strict_types=1);

namespace DevichanE2E\Browser;

use DevichanE2E\Support\BrowserTester;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;

final class PostActionsCest
{
    public function reportAndDeleteRestoreTheirPostMenuTrigger(BrowserTester $I): void
    {
        $I->amOnPage('/mod/');
        $I->fillField('input[name="username"]', 'admin');
        $I->fillField('input[name="password"]', 'password');
        $I->click('input[name="login"]');
        $I->waitForElement('body.is-moderator');
        $I->setCookie('e2e_post_controls', '1');
        $I->amOnPage('/mod.php?/b/res/1.html');
        $I->waitForElement('.post-btn');

        foreach (['report', 'delete'] as $action) {
            $I->click('.post-btn');
            $I->executeJS('window.postActionTrigger = document.querySelector(".post-btn-open");');
            $I->click('#' . ($action === 'report' ? 'report_menu' : 'delete_post_menu'));
            $I->seeElement('dialog.post-action-dialog.ui-dialog[open]');
            $I->dontSeeElement('.post-menu');
            $I->assertSame('hidden', $I->executeJS('return getComputedStyle(document.body).overflow;'));
            if ($action === 'report') {
                $I->waitForJS('return !document.querySelector(".post-action-dialog input[name=report]").disabled;');
                $I->click('.post-action-reasons input[value="4"]');
                $I->seeElement('.post-action-dialog textarea[required]');
                $I->fillField('.post-action-dialog textarea', 'A report that will be cancelled.');
                $I->pressKey('.post-action-dialog textarea', WebDriverKeys::ESCAPE);
            } else {
                $I->seeElement('.post-action-dialog input[type="password"]');
                $I->executeInSelenium(static function(RemoteWebDriver $driver): void {
                    $dialog = $driver->findElement(WebDriverBy::cssSelector('.post-action-dialog'));
                    $driver->action()->moveToElement($dialog)
                        ->moveByOffset(-(int) ceil($dialog->getSize()->getWidth() / 2) - 4, 0)
                        ->click()->perform();
                });
            }
            $I->waitForJS('return !document.querySelector(".post-action-dialog");');
            $I->dontSeeElement('body.dialog-open');
            $I->assertTrue($I->executeJS('return document.activeElement === window.postActionTrigger;'));
        }
    }
}
