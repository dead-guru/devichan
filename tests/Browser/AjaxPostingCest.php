<?php

declare(strict_types=1);

namespace DevichanE2E\Browser;

use DevichanE2E\Support\BrowserTester;

final class AjaxPostingCest
{
    public function _before(BrowserTester $I): void
    {
        $I->amOnPage('/b/res/1.html');
        $I->click('h1.open-form a');
        $I->waitForElementVisible('form[name="post"]');
        $I->executeJS(<<<'JS'
            $(document).on('ajaxComplete', function(event, xhr, settings) {
                if (settings.type === 'POST') {
                    window.postStatus = xhr.status;
                }
            });
            JS);
    }

    public function _after(BrowserTester $I): void
    {
        $I->resetCookie('e2e_post_case');
    }

    public function oversizedFileShowsTheServerErrorAndKeepsTheDraft(BrowserTester $I): void
    {
        $I->setCookie('e2e_post_case', 'tiny-file-limit');
        $body = 'E2E AJAX oversized file ' . bin2hex(random_bytes(4));
        $I->fillField('form[name="post"] textarea[name="body"]', $body);
        $I->attachFile('form[name="post"] input[name="file"]', 'invalid-image.png');
        $button = $I->grabValueFrom('form[name="post"] input[type="submit"]');
        $size = number_format(filesize(codecept_data_dir('invalid-image.png')));

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $I->executeJS('window.postStatus = null;');
            $I->click('form[name="post"] input[type="submit"]');
            $I->waitForElementVisible('#alert_message');
            $I->see('Maximum file size: 1 bytes', '#alert_message');
            $I->see("Your file's size: {$size} bytes", '#alert_message');
            $I->seeElementInDOM('#alert_message br');
            $I->dontSeeElement('#alert_message textarea');
            $I->dontSee('The server took too long', '#alert_message');
            $I->waitForJS('return window.postStatus === 400;');
            $I->seeInField('form[name="post"] textarea[name="body"]', $body);
            $I->seeInField('form[name="post"] input[type="submit"]', $button);
            $I->dontSeeElement('form[name="post"] input[type="submit"][disabled]');
            $I->assertSame('invalid-image.png', $I->executeJS(
                'return document.querySelector(\'form[name="post"] input[name="file"]\').files[0].name;',
            ));
            $I->dontSeeInDatabase('posts_b', ['body_nomarkup' => $body]);
            $I->click('#alert_div .alert_button');
            $I->waitForElementNotVisible('#alert_handler');
        }
    }

    public function successfulReplyIsFollowedByAReadableFloodRejection(BrowserTester $I): void
    {
        $I->wait(2);
        $postedBody = 'E2E AJAX posted reply ' . bin2hex(random_bytes(4));
        $I->fillField('form[name="post"] textarea[name="body"]', $postedBody);
        $button = $I->grabValueFrom('form[name="post"] input[type="submit"]');
        $I->click('form[name="post"] input[type="submit"]');
        $I->waitForText($postedBody, 10, '.thread');
        $I->waitForJS('return window.postStatus === 200;');
        $I->assertSame('', $I->grabValueFrom('form[name="post"] textarea[name="body"]'));
        $I->seeInDatabase('posts_b', ['body_nomarkup' => $postedBody]);

        $I->setCookie('e2e_post_case', 'rate-limit');
        $body = 'E2E AJAX flood draft ' . bin2hex(random_bytes(4));
        $I->fillField('form[name="post"] textarea[name="body"]', $body);
        $I->executeJS('window.postStatus = null;');
        $I->click('form[name="post"] input[type="submit"]');
        $I->waitForElementVisible('#alert_message');
        $I->assertSame('Flood detected; Post discarded.', $I->grabTextFrom('#alert_message'));
        $I->dontSeeElement('#alert_message textarea');
        $I->waitForJS('return window.postStatus === 400;');
        $I->seeInField('form[name="post"] textarea[name="body"]', $body);
        $I->seeInField('form[name="post"] input[type="submit"]', $button);
        $I->dontSeeElement('form[name="post"] input[type="submit"][disabled]');
        $I->dontSeeInDatabase('posts_b', ['body_nomarkup' => $body]);
    }

    public function nonJsonResponseKeepsTheDraftAndExplainsTheUncertainResult(BrowserTester $I): void
    {
        $body = 'E2E AJAX unexpected response';
        $I->fillField('form[name="post"] textarea[name="body"]', $body);
        $I->executeJS(<<<'JS'
            document.querySelector('form[name="post"]').action = '/tests/fixtures/external-gallery.html';
            JS);
        $I->click('form[name="post"] input[type="submit"]');
        $I->waitForElementVisible('#alert_message');
        $I->assertSame(
            'Could not confirm whether your post was sent. Check the thread before trying again.',
            $I->grabTextFrom('#alert_message'),
        );
        $I->dontSeeElement('#alert_message textarea');
        $I->seeInField('form[name="post"] textarea[name="body"]', $body);
        $I->dontSeeElement('form[name="post"] input[type="submit"][disabled]');
        $I->dontSeeInDatabase('posts_b', ['body_nomarkup' => $body]);
    }
}
