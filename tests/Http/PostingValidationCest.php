<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\HttpTester;

final class PostingValidationCest
{
    public function malformedDeletionAndReportRequestsAreRejected(HttpTester $I): void
    {
        $invalidRequests = [
            ['delete' => 'Delete'],
            ['delete' => 'Delete', 'board' => 'b', 'password' => ''],
            ['delete' => 'Delete', 'board' => 'missing', 'password' => 'e2e'],
            ['delete' => 'Delete', 'board' => 'b', 'password' => 'e2e'],
            ['report' => 'Submit'],
            ['report' => 'Submit', 'board' => 'missing', 'reason' => 'E2E'],
            ['report' => 'Submit', 'board' => 'b', 'reason' => 'E2E'],
        ];

        foreach ($invalidRequests as $request) {
            $I->sendAjaxPostRequest('/post.php', $request);
            $I->seeResponseCodeIs(500);
            $I->assertStringContainsString('<title>Error</title>', $I->grabPageSource());
        }
    }

    public function malformedPostRequestsAreRejected(HttpTester $I): void
    {
        $I->sendAjaxPostRequest('/post.php', ['post' => 'New Topic']);
        $I->seeResponseCodeIs(500);

        $I->sendAjaxPostRequest('/post.php', [
            'post' => 'New Topic',
            'board' => 'missing',
            'body' => 'E2E unknown board',
        ]);
        $I->seeResponseCodeIs(500);

        $I->sendAjaxPostRequest('/post.php', [
            'post' => 'not-the-real-button',
            'board' => 'b',
            'body' => 'E2E invalid submit button',
        ]);
        $I->seeResponseCodeIs(500);

        $I->sendAjaxPostRequest('/post.php', [
            'api' => 'e2e-api-key',
            'board' => 'b',
            'thread' => '999999',
            'body' => 'E2E missing thread',
        ]);
        $I->seeResponseCodeIs(500);

        $I->amOnPage('/post.php');
        $I->seeResponseCodeIs(500);
    }

    public function oversizedPostFieldsAreRejected(HttpTester $I): void
    {
        $cases = [
            ['name' => str_repeat('n', 36)],
            ['email' => str_repeat('e', 41)],
            ['subject' => str_repeat('s', 101)],
            ['body' => str_repeat('b', 1801)],
            ['password' => str_repeat('p', 21)],
        ];

        foreach ($cases as $fields) {
            $request = array_merge([
                'api' => 'e2e-api-key',
                'board' => 'b',
                'body' => 'E2E length validation',
                'embed' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ], $fields);
            $I->sendAjaxPostRequest('/post.php', $request);
            $I->seeResponseCodeIs(500);
            $I->assertStringContainsString('<title>Error</title>', $I->grabPageSource());
        }
    }

    public function invalidImageContentIsRejected(HttpTester $I): void
    {
        sleep(2);
        $I->amOnPage('/b/');
        $I->attachFile(
            'form[name="post"] input[name="file"]',
            'invalid-image.png',
        );
        $I->fillField(
            'form[name="post"] textarea[name="body"]',
            'E2E invalid image payload',
        );
        $I->fillField('form[name="post"] input[name="password"]', 'e2e-invalid-image');
        $I->click('form[name="post"] input[name="post"]');

        $I->seeResponseCodeIs(500);
        $I->seeInSource('<title>Error</title>');
        $I->dontSeeInDatabase('posts_b', [
            'body_nomarkup' => 'E2E invalid image payload',
        ]);
    }
}
