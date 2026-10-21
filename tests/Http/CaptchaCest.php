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

    public function legacyCaptchaUsesTheSiteDatabaseAndCanBeCheckedOnce(HttpTester $I): void
    {
        $charset = 'abcXYZ123';
        $I->amOnPage('/inc/captcha/entrypoint.php?' . http_build_query([
            'mode' => 'get',
            'extra' => $charset,
        ]));
        $I->seeResponseCodeIs(200);
        $payload = json_decode($I->grabPageSource(), true, 512, JSON_THROW_ON_ERROR);
        $I->assertSame(20, strlen($payload['cookie']));
        $I->assertSame(120, $payload['expires_in']);
        $I->assertStringContainsString('data:image/jpg;base64,', $payload['captchahtml']);
        $image = getimagesizefromstring(base64_decode($payload['image'], true));
        $I->assertSame([250, 80, IMAGETYPE_PNG], [$image[0], $image[1], $image[2]]);

        $answer = $I->grabFromDatabase('captchas', 'text', [
            'cookie' => $payload['cookie'],
            'extra' => $charset,
        ]);
        $I->assertMatchesRegularExpression('/^[abcXYZ123]{6}$/', $answer);
        $check = '/inc/captcha/entrypoint.php?' . http_build_query([
            'mode' => 'check',
            'cookie' => $payload['cookie'],
            'extra' => $charset,
            'text' => $answer,
        ]);

        $I->amOnPage($check);
        $I->seeResponseCodeIs(200);
        $I->assertSame('1', $I->grabPageSource());
        $I->dontSeeInDatabase('captchas', ['cookie' => $payload['cookie']]);

        $I->amOnPage($check);
        $I->seeResponseCodeIs(200);
        $I->assertSame('0', $I->grabPageSource());
    }

    public function legacyRawCaptchaReturnsItsChallengeCookie(HttpTester $I): void
    {
        $I->amOnPage('/inc/captcha/entrypoint.php?mode=get&extra=abc123&raw=1');
        $I->seeResponseCodeIs(200);
        $image = getimagesizefromstring($I->grabPageSource());
        $I->assertSame([250, 80, IMAGETYPE_PNG], [$image[0], $image[1], $image[2]]);
        $I->seeCookie('captcha_cookie');
        $cookie = $I->grabCookie('captcha_cookie');
        $answer = $I->grabFromDatabase('captchas', 'text', ['cookie' => $cookie]);
        $I->assertMatchesRegularExpression('/^[abc123]{6}$/', $answer);

        $I->amOnPage('/inc/captcha/entrypoint.php?' . http_build_query([
            'mode' => 'check',
            'cookie' => $cookie,
            'extra' => 'abc123',
            'text' => $answer,
        ]));
        $I->seeResponseCodeIs(200);
        $I->assertSame('1', $I->grabPageSource());
    }

    public function legacyCaptchaRejectsAndConsumesWrongOrExpiredAnswers(HttpTester $I): void
    {
        foreach (['wrong', 'expired'] as $case) {
            $cookie = 'e2e-' . bin2hex(random_bytes(8));
            $I->haveInDatabase('captchas', [
                'cookie' => $cookie,
                'extra' => 'abc123',
                'text' => 'abc123',
                'created_at' => $case === 'expired' ? time() - 121 : time(),
            ]);

            $I->amOnPage('/inc/captcha/entrypoint.php?' . http_build_query([
                'mode' => 'check',
                'cookie' => $cookie,
                'extra' => 'abc123',
                'text' => $case === 'wrong' ? '321cba' : 'abc123',
            ]));
            $I->seeResponseCodeIs(200);
            $I->assertSame('0', $I->grabPageSource());
            $I->dontSeeInDatabase('captchas', ['cookie' => $cookie]);
        }
    }

    public function legacyCaptchaKeepsEmptyResponsesForIncompleteRequests(HttpTester $I): void
    {
        foreach (['', '?mode=get', '?mode=check&cookie=missing&extra=abc123'] as $query) {
            $I->amOnPage('/inc/captcha/entrypoint.php' . $query);
            $I->seeResponseCodeIs(200);
            $I->assertSame('', $I->grabPageSource());
        }
    }
}
