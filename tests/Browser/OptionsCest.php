<?php

declare(strict_types=1);

namespace DevichanE2E\Browser;

use DevichanE2E\Support\BrowserTester;
use Facebook\WebDriver\WebDriverKeys;

final class OptionsCest
{
    public function _before(BrowserTester $I): void
    {
        $I->amOnPage('/mod/');
        $I->fillField('input[name="username"]', 'admin');
        $I->fillField('input[name="password"]', 'password');
        $I->click('input[name="login"]');
        $I->waitForElement('body.is-moderator');
        $I->setCookie('e2e_options', '1');
        $I->executeJS('localStorage.top_menu_position = "scroll";');
        $I->amOnPage('/mod.php?/b/res/1.html');
        $I->waitForElement('.top-menu');
    }

    public function menuFollowsTheViewportOnlyWhenChecked(BrowserTester $I): void
    {
        $toggle = '#options-panel-general > label input[type="checkbox"]';
        foreach ([true, false] as $fixed) {
            $I->executeJS('window.scrollTo(0, 0);');
            $I->click('#options_button');
            $I->waitForElementVisible($toggle);
            if ($fixed) {
                $I->checkOption($toggle);
            } else {
                $I->uncheckOption($toggle);
            }
            $I->click('#options_close');

            for ($reload = 0; $reload < 2; $reload++) {
                $I->assertSame($fixed, $I->executeJS('return document.querySelector(arguments[0]).checked;', [$toggle]));
                $I->assertSame($fixed ? 'fixed' : 'scroll', $I->executeJS('return localStorage.top_menu_position;'));
                $I->executeJS('window.scrollTo(0, 200);');
                $I->waitForJS('return window.scrollY > 0;');
                $top = $I->executeJS('return document.querySelector(".top-menu").getBoundingClientRect().top;');
                if ($fixed) {
                    $I->assertEquals(0, $top);
                } else {
                    $I->assertLessThan(0, $top);
                }
                if ($reload === 0) {
                    $I->reloadPage();
                    $I->waitForElement('.top-menu');
                }
            }
        }
    }

    public function nestedAlertKeepsSettingsOpenAndRestoresFocus(BrowserTester $I): void
    {
        $I->click('#options_button');
        $I->seeElement('dialog#options_div[open]');
        $I->click('#options-panel-general > label input[type="checkbox"]');
        $I->executeJS("window.optionsFocus = document.activeElement; alert('Settings remain open.');");
        $I->pressKey('#alert_div .alert_button', WebDriverKeys::ESCAPE);
        $I->waitForJS('return !document.querySelector("#alert_div");');
        $I->seeElement('dialog#options_div[open]');
        $I->assertSame('hidden', $I->executeJS('return getComputedStyle(document.body).overflow;'));
        $I->assertTrue($I->executeJS('return document.activeElement === window.optionsFocus;'));

        $I->pressKey('#options_close', WebDriverKeys::ESCAPE);
        $I->waitForJS('return !document.querySelector("#options_div").open && !document.body.classList.contains("dialog-open");');
        $I->assertSame('options_button', $I->executeJS('return document.activeElement.id;'));

        $I->click('#options_button');
        $I->seeElement('dialog#options_div[open]');
        $I->click('#options_close');
        $I->waitForJS('return !document.querySelector("#options_div").open && !document.body.classList.contains("dialog-open");');
        $I->assertSame('options_button', $I->executeJS('return document.activeElement.id;'));
    }

    public function storageImportValidatesBeforeReplacingData(BrowserTester $I): void
    {
        $I->executeJS('localStorage.dialogImportSentinel = "preserve";');
        $I->click('#options_button');
        $I->click('#options-panel-general .options-actions button:nth-child(2)');
        $I->seeElement('dialog#options-import[open]');
        $I->fillField('#options-import-data', '["invalid"]');
        $I->click('#options-import button[type="submit"]');
        $I->see('Invalid storage data.', '#alert_message');
        $I->assertSame('preserve', $I->executeJS('return localStorage.dialogImportSentinel;'));
        $I->click('#alert_div .alert_button');
        $I->waitForJS('return !document.querySelector("#alert_div");');
        $I->seeElement('dialog#options-import[open]');
        $I->pressKey('#options-import-data', WebDriverKeys::ESCAPE);
        $I->waitForJS('return !document.querySelector("#options-import");');
        $I->seeElement('dialog#options_div[open]');
        $I->assertTrue($I->executeJS('return document.activeElement === document.querySelector("#options-panel-general .options-actions button:nth-child(2)");'));

        $I->click('#options-panel-general .options-actions button:nth-child(2)');
        $I->fillField('#options-import-data', '{"version":"3","top_menu_position":"scroll","dialogImported":"saved"}');
        $I->click('#options-import button[type="submit"]');
        $I->waitForJS('return !document.querySelector("#options-import");');
        $I->waitForElement('.top-menu');
        $I->assertSame('saved', $I->executeJS('return localStorage.dialogImported;'));
        $I->assertNull($I->executeJS('return localStorage.getItem("dialogImportSentinel");'));
    }
}
