<?php

declare(strict_types=1);

namespace DevichanE2E\Browser;

use DevichanE2E\Support\BrowserTester;

final class OptionsCest
{
    public function menuFollowsTheViewportOnlyWhenChecked(BrowserTester $I): void
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
}
