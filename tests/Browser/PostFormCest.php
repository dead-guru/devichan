<?php

declare(strict_types=1);

namespace DevichanE2E\Browser;

use DevichanE2E\Support\BrowserTester;

final class PostFormCest
{
    private const MAIN = 'form[name="post"]:not(#quick-reply)';
    private const QUICK = '#quick-reply';
    private const VIDEO = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

    public function _before(BrowserTester $I): void
    {
        $I->amOnPage('/mod/');
        $I->fillField('input[name="username"]', 'admin');
        $I->fillField('input[name="password"]', 'password');
        $I->click('input[name="login"]');
        $I->waitForElement('body.is-moderator');
        $I->setCookie('e2e_post_form', '1');
    }

    public function _after(BrowserTester $I): void
    {
        $I->resetCookie('e2e_post_form');
    }

    public function firstVisitKeepsTheToolbarAndPostPassword(BrowserTester $I): void
    {
        $I->executeJS('localStorage.clear();');
        $this->openThread($I);
        $I->waitForElementVisible(self::MAIN . ' .format-text button');
        $I->assertSame(
            $I->grabValueFrom(self::MAIN . ' input[name="password"]'),
            $I->executeJS('return localStorage.password;'),
        );
    }

    public function returningVisitorKeepsTheirSettings(BrowserTester $I): void
    {
        $I->executeJS('localStorage.clear(); localStorage.version = "3"; localStorage.formatText_toolbar_1 = "false";');
        $this->openThread($I);
        $I->assertSame('false', $I->executeJS('return localStorage.formatText_toolbar_1;'));
        $I->dontSeeElement(self::MAIN . ' .format-text');
    }

    public function quickReplySwitchesAttachmentsTogetherWithTheMainForm(BrowserTester $I): void
    {
        $this->openQuickReply($I);
        $I->seeElement(self::QUICK . ' .upload-modes button[data-mode="file"][aria-pressed="true"]');
        $I->dontSeeElement(self::QUICK . ' input[name="embed"]');

        $I->click(self::QUICK . ' .upload-modes button[data-mode="embed"]');
        $I->seeElement(self::QUICK . ' input[name="embed"]');
        $I->seeElement(self::MAIN . ' input[name="embed"]');
        $I->dontSeeElement(self::QUICK . ' .dropzone');
        $I->fillField(self::QUICK . ' input[name="embed"]', self::VIDEO);
        $I->assertSame(self::VIDEO, $this->embed($I, self::MAIN));

        $I->click(self::QUICK . ' .upload-modes button[data-mode="file"]');
        $I->dontSeeElement(self::QUICK . ' input[name="embed"]');
        $I->dontSeeElement(self::MAIN . ' input[name="embed"]');
        $I->assertSame('', $this->embed($I, self::QUICK));
        $I->assertSame('', $this->embed($I, self::MAIN));

        $I->executeJS('window.FileSelector.addFile(new File(["x"], "queued.png", {type: "image/png"}));');
        $I->seeElement(self::QUICK . ' .tmb-container');
        $I->click(self::QUICK . ' .upload-modes button[data-mode="embed"]');
        $I->click(self::QUICK . ' .upload-modes button[data-mode="file"]');
        $I->seeElement(self::QUICK . ' .dropzone');
        $I->dontSeeElementInDOM('.tmb-container');
    }

    public function postedVideoIsNotAttachedToTheNextReply(BrowserTester $I): void
    {
        $this->openThread($I);
        $I->click('Embed', self::MAIN . ' .upload-modes');
        $this->openQuickReply($I, false);
        // posting rebuilds the shared static thread page with this request's config
        $I->resetCookie('e2e_post_form');

        $video = 'E2E quick reply video ' . bin2hex(random_bytes(4));
        $I->fillField(self::QUICK . ' textarea[name="body"]', $video);
        $I->fillField(self::QUICK . ' input[name="embed"]', self::VIDEO);
        $I->click(self::QUICK . ' input[type="submit"]');
        $I->waitForText($video, 10, '.thread');
        $I->seeInDatabase('posts_b', ['body_nomarkup' => $video, 'embed' => self::VIDEO]);
        $I->assertSame('', $this->embed($I, self::QUICK));
        $I->assertSame('', $this->embed($I, self::MAIN));

        $I->wait(2);
        $text = 'E2E quick reply after video ' . bin2hex(random_bytes(4));
        $I->fillField(self::QUICK . ' textarea[name="body"]', $text);
        $I->click(self::QUICK . ' input[type="submit"]');
        $I->waitForText($text, 10, '.thread');
        $I->seeInDatabase('posts_b', ['body_nomarkup' => $text, 'embed' => null]);
    }

    private function openQuickReply(BrowserTester $I, bool $load = true): void
    {
        if ($load) {
            $this->openThread($I);
        }
        // the seed thread is too short to scroll past the main form
        $I->executeJS('tb_settings["quick-reply"].hide_at_top = false;');
        $I->click('#reply_2 a.post_no[onclick^="citeReply"]');
        $I->waitForElementVisible(self::QUICK);
    }

    private function openThread(BrowserTester $I): void
    {
        $I->amOnPage('/mod.php?/b/res/1.html');
        $I->click('h1.open-form a');
        $I->waitForElementVisible(self::MAIN);
    }

    private function embed(BrowserTester $I, string $form): string
    {
        return $I->executeJS('return document.querySelector(arguments[0]).value;', [$form . ' input[name="embed"]']);
    }
}
