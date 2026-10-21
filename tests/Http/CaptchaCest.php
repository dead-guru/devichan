<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\HttpTester;

final class CaptchaCest
{
    public function _after(HttpTester $I): void
    {
        $I->deleteHeader('X-E2E-Post-Case');
    }

    public function generatedCaptchaCanBeCheckedOnce(HttpTester $I): void
    {
        $I->amOnPage('/securimage.php?mode=get&extra=e2e-captcha');
        $I->seeResponseCodeIs(200);
        $payload = json_decode($I->grabPageSource(), true, 512, JSON_THROW_ON_ERROR);
        $I->assertArrayHasKey('cookie', $payload);
        $I->assertStringContainsString('data:image/png;base64,', $payload['captchahtml']);

        $answer = $I->grabFromDatabase('captchas', 'text', [
            'cookie' => $payload['cookie'],
            'extra' => 'e2e-captcha',
        ]);
        $I->assertNotEmpty($answer);

        $I->amOnPage('/securimage.php?' . http_build_query([
            'mode' => 'check',
            'cookie' => $payload['cookie'],
            'extra' => 'e2e-captcha',
            'text' => $answer,
        ]));
        $I->assertSame('1', $I->grabPageSource());

        $I->amOnPage('/securimage.php?' . http_build_query([
            'mode' => 'check',
            'cookie' => $payload['cookie'],
            'extra' => 'e2e-captcha',
            'text' => $answer,
        ]));
        $I->assertSame('0', $I->grabPageSource());
    }

    public function rawCaptchaPersistsTheChallengeForTheNextPost(HttpTester $I): void
    {
        $I->haveHttpHeader('X-E2E-Post-Case', 'report-captcha');
        $I->amOnPage('/securimage.php?mode=get&raw=1');
        $I->seeResponseCodeIs(200);
        $I->assertStringStartsWith("\x89PNG", $I->grabPageSource());
        $I->seeCookie('PHPSESSID');

        $answer = $I->grabFromDatabase('captchas', 'text', ['extra' => 'e2e-report-captcha']);
        $I->assertNotEmpty($answer);
        $reason = 'E2E session captcha ' . bin2hex(random_bytes(4));
        $I->sendAjaxPostRequest('/post.php', [
            'board' => 'b',
            'reason' => $reason,
            'delete_1' => 'on',
            'report' => 'Submit',
            'captcha_text' => $answer,
            'json_response' => '1',
        ]);

        $I->seeResponseCodeIs(200);
        $I->assertSame(['success' => true], json_decode($I->grabPageSource(), true, flags: JSON_THROW_ON_ERROR));
        $I->seeInDatabase('reports', ['board' => 'b', 'post' => 1, 'reason' => $reason]);
        $I->dontSeeInDatabase('captchas', ['extra' => 'e2e-report-captcha']);
    }
}
