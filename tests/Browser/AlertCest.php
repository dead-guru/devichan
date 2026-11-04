<?php

declare(strict_types=1);

namespace DevichanE2E\Browser;

use DevichanE2E\Support\BrowserTester;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverKeys;

final class AlertCest
{
    public function _before(BrowserTester $I): void
    {
        $I->amOnPage('/b/res/1.html');
    }

    public function _after(BrowserTester $I): void
    {
        $I->resizeWindow(1440, 1000);
        $I->executeJS('localStorage.removeItem("stylesheet");');
    }

    public function keyboardDismissalRestoresFocus(BrowserTester $I): void
    {
        $I->click('h1.open-form a');
        $I->click('form[name="post"] textarea[name="body"]');
        $I->executeJS("alert('Flood detected; Post discarded.');");
        $I->seeElement('#alert_div[role="alertdialog"][aria-modal="true"]');
        $I->assertSame('OK', $I->executeJS('return document.activeElement.textContent;'));
        $I->pressKey('#alert_div .alert_button', WebDriverKeys::TAB);
        $I->assertSame('alert_close', $I->executeJS('return document.activeElement.id;'));
        $I->executeInSelenium(static function(RemoteWebDriver $driver): void {
            $driver->action()
                ->keyDown(null, WebDriverKeys::SHIFT)
                ->sendKeys(null, WebDriverKeys::TAB)
                ->keyUp(null, WebDriverKeys::SHIFT)
                ->perform();
        });
        $I->assertSame('OK', $I->executeJS('return document.activeElement.textContent;'));
        $I->pressKey('#alert_div .alert_button', WebDriverKeys::ESCAPE);
        $I->dontSeeElement('#alert_handler');
        $I->assertTrue($I->executeJS('return document.activeElement.matches(\'form[name="post"] textarea[name="body"]\');'));
    }

    public function confirmationRunsOnlyTheChosenCallback(BrowserTester $I): void
    {
        foreach (['ok', 'cancel', 'escape', 'close', 'backdrop'] as $action) {
            $I->executeJS(<<<'JS'
                window.confirmed = 0;
                window.cancelled = 0;
                alert('Delete this post?', true,
                    function() { window.confirmed++; },
                    function() { window.cancelled++; });
                JS);
            switch ($action) {
                case 'ok':
                    $I->click('#alert_div .alert_button');
                    break;
                case 'cancel':
                    $I->click('#alert_div .alert_button:last-child');
                    break;
                case 'escape':
                    $I->pressKey('#alert_div .alert_button', WebDriverKeys::ESCAPE);
                    break;
                case 'close':
                    $I->click('#alert_close');
                    break;
                case 'backdrop':
                    $I->executeJS('document.querySelector("#alert_background").click();');
                    break;
            }
            $I->dontSeeElement('#alert_handler');
            $I->assertSame($action === 'ok' ? [1, 0] : [0, 1], $I->executeJS(
                'return [window.confirmed, window.cancelled];',
            ));
        }
    }

    public function dialogFitsDesktopAndPhoneInLightAndDarkThemes(BrowserTester $I): void
    {
        foreach ([[1440, 1000], [390, 844]] as [$width, $height]) {
            $I->resizeWindow($width, $height);
            foreach (['Yotsuba B', 'Dark', 'Photon', 'Dark New Photon', 'Lainchan', 'Caffe'] as $theme) {
                $I->executeJS('changeStyle(arguments[0]);', [$theme]);
                $I->waitForJS('return !styles[selectedstyle] || !!document.getElementById("stylesheet").sheet;');
                $I->executeJS(<<<'JS'
                    alert("Maximum file size: 10,485,760 bytes<br>Your file's size: 15,728,640 bytes");
                    JS);
                $bounds = $I->executeJS(<<<'JS'
                    const rect = document.querySelector('#alert_div').getBoundingClientRect();
                    return [rect.left, rect.top, window.innerWidth - rect.right, window.innerHeight - rect.bottom];
                    JS);
                foreach ($bounds as $space) {
                    $I->assertGreaterThanOrEqual(0, $space);
                }
                $I->makeScreenshot('alert-' . strtolower(str_replace(' ', '-', $theme)) . '-' . $width);
                $I->click('#alert_div .alert_button');
            }
        }
    }
}
