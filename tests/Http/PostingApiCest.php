<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\HttpAssertions;
use DevichanE2E\Support\HttpTester;

final class PostingApiCest
{
    use HttpAssertions;

    public function apiCanCreateAndDeleteAThreadWithJsonResponses(HttpTester $I): void
    {
        $body = 'E2E API thread ' . bin2hex(random_bytes(4));

        $I->sendAjaxPostRequest('/post.php', [
            'api' => 'e2e-api-key',
            'json_response' => '1',
            'board' => 'b',
            'body' => $body,
            'password' => 'e2e-api-delete',
            'embed' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
        $I->seeResponseCodeIs(200);
        $response = json_decode(
            $I->grabPageSource(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $I->assertArrayHasKey('id', $response);
        $I->assertArrayHasKey('redirect', $response);
        $threadId = (int) $response['id'];
        $I->seeInDatabase('posts_b', ['id' => $threadId, 'body_nomarkup' => $body]);

        $I->sendAjaxPostRequest('/post.php', [
            'json_response' => '1',
            'board' => 'b',
            'password' => 'e2e-api-delete',
            'delete_' . $threadId => 'on',
            'delete' => 'Delete',
        ]);
        $I->seeResponseCodeIs(200);
        $I->assertSame(
            ['success' => true],
            json_decode($I->grabPageSource(), true, flags: JSON_THROW_ON_ERROR),
        );
        $I->dontSeeInDatabase('posts_b', ['id' => $threadId]);
    }

    public function reportsCanReturnJson(HttpTester $I): void
    {
        $reason = 'E2E JSON report ' . bin2hex(random_bytes(4));

        $I->sendAjaxPostRequest('/post.php', [
            'json_response' => '1',
            'board' => 'b',
            'delete_1' => 'on',
            'reason' => $reason,
            'report' => 'Submit',
        ]);
        $I->seeResponseCodeIs(200);
        $I->assertSame(
            ['success' => true],
            json_decode($I->grabPageSource(), true, flags: JSON_THROW_ON_ERROR),
        );
        $I->seeInDatabase('reports', ['board' => 'b', 'post' => 1, 'reason' => $reason]);
    }

    public function invalidPostInputIsRejected(HttpTester $I): void
    {
        sleep(2);
        $I->sendAjaxPostRequest('/post.php', [
            'api' => 'e2e-api-key',
            'board' => 'b',
            'body' => 'E2E invalid embed',
            'password' => 'e2e-invalid',
            'embed' => 'https://invalid.example/video',
        ]);
        $I->seeResponseCodeIs(500);
        $I->assertStringContainsString('<title>Error</title>', $I->grabPageSource());
        $I->dontSeeInDatabase('posts_b', ['body_nomarkup' => 'E2E invalid embed']);

        $I->sendAjaxPostRequest('/post.php', [
            'board' => 'b',
            'password' => 'wrong-password',
            'delete_1' => 'on',
            'delete' => 'Delete',
        ]);
        $I->seeResponseCodeIs(500);
        $I->assertStringContainsString('<title>Error</title>', $I->grabPageSource());
        $I->seeInDatabase('posts_b', ['id' => 1, 'password' => 'postpass']);
    }

    public function visitorReceivesAModeratorTelegramAfterPosting(HttpTester $I): void
    {
        $I->sendAjaxPostRequest('/post.php', [
            'api' => 'e2e-api-key',
            'json_response' => '1',
            'board' => 'b',
            'body' => 'E2E telegram address probe',
            'password' => 'e2e-telegram-probe',
            'embed' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
        $I->seeResponseCodeIs(200);
        $visitorIp = (string) $I->grabFromDatabase('posts_b', 'ip', [
            'body_nomarkup' => 'E2E telegram address probe',
        ]);
        $telegramId = $I->haveInDatabase('telegrams', [
            'mod_id' => 1,
            'ip' => $visitorIp,
            'message' => 'E2E important moderator message',
            'seen' => 0,
            'created_at' => time(),
        ]);

        $I->sendAjaxPostRequest('/post.php', [
            'api' => 'e2e-api-key',
            'board' => 'b',
            'body' => 'E2E telegram delivery post',
            'password' => 'e2e-telegram',
            'embed' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
        $I->seeResponseCodeIs(200);
        $I->assertStringContainsString(
            'E2E important moderator message',
            $I->grabPageSource(),
        );
        $I->seeInDatabase('telegrams', ['id' => $telegramId, 'seen' => 1]);
    }
}
