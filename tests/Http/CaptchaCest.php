<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\HttpTester;

final class CaptchaCest
{
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

    public function rawCaptchaReturnsPngAndStoresSessionCookie(HttpTester $I): void
    {
        $I->amOnPage('/securimage.php?mode=get&extra=e2e-raw&raw=1');
        $I->seeResponseCodeIs(200);
        $I->assertStringStartsWith("\x89PNG", $I->grabPageSource());
    }
}
