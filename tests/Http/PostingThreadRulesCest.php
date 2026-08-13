<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\HttpTester;
use DevichanE2E\Support\PostFixture;

final class PostingThreadRulesCest
{
    use PostFixture;

    public function visitorCannotReplyToALockedThread(HttpTester $I): void
    {
        $threadId = $this->createPost($I, 'b', '198.51.100.70');
        $I->updateInDatabase('posts_b', ['locked' => 1], ['id' => $threadId]);
        $body = 'E2E rejected locked reply ' . bin2hex(random_bytes(4));

        $I->sendAjaxPostRequest('/post.php', [
            'api' => 'e2e-api-key',
            'board' => 'b',
            'thread' => $threadId,
            'body' => $body,
            'password' => 'e2e-locked',
        ]);

        $I->seeResponseCodeIs(500);
        $I->assertStringContainsString('Нитку закрито', $I->grabPageSource());
        $I->dontSeeInDatabase('posts_b', ['body_nomarkup' => $body]);
    }

    public function cyclicalThreadKeepsOnlyItsNewestReplies(HttpTester $I): void
    {
        $I->setCookie('e2e_cycle_limit', '2');
        $threadId = $this->createPost($I, 'b', '198.51.100.71');
        $I->updateInDatabase('posts_b', ['cycle' => 1], ['id' => $threadId]);

        foreach (range(1, 3) as $replyNumber) {
            $I->sendAjaxPostRequest('/post.php', [
                'api' => 'e2e-api-key',
                'json_response' => '1',
                'board' => 'b',
                'thread' => $threadId,
                'body' => 'E2E cycle reply ' . $replyNumber,
                'password' => 'e2e-cycle',
            ]);
            $I->seeResponseCodeIs(200);
        }

        $I->seeNumRecords(2, 'posts_b', ['thread' => $threadId]);
        $I->dontSeeInDatabase('posts_b', [
            'thread' => $threadId,
            'body_nomarkup' => 'E2E cycle reply 1',
        ]);
        $I->seeInDatabase('posts_b', [
            'thread' => $threadId,
            'body_nomarkup' => 'E2E cycle reply 3',
        ]);
    }

    public function sageReplyDoesNotBumpItsThread(HttpTester $I): void
    {
        $threadId = $this->createPost($I, 'b', '198.51.100.72');
        $originalBump = (int) $I->grabFromDatabase('posts_b', 'bump', ['id' => $threadId]);

        $I->sendAjaxPostRequest('/post.php', [
            'api' => 'e2e-api-key',
            'json_response' => '1',
            'board' => 'b',
            'thread' => $threadId,
            'email' => 'sage',
            'body' => 'E2E sage reply',
            'password' => 'e2e-sage',
        ]);

        $I->seeResponseCodeIs(200);
        $I->assertSame(
            $originalBump,
            (int) $I->grabFromDatabase('posts_b', 'bump', ['id' => $threadId]),
        );
    }

    public function replyHardLimitRejectsAnotherReply(HttpTester $I): void
    {
        $I->setCookie('e2e_reply_hard_limit', '1');
        $threadId = $this->createPost($I, 'b', '198.51.100.73');
        $this->createPost($I, 'b', '198.51.100.74', $threadId);
        $body = 'E2E reply beyond hard limit';

        $I->sendAjaxPostRequest('/post.php', [
            'api' => 'e2e-api-key',
            'board' => 'b',
            'thread' => $threadId,
            'body' => $body,
            'password' => 'e2e-limit',
        ]);

        $I->seeResponseCodeIs(500);
        $I->assertStringContainsString('максимально припустимої', $I->grabPageSource());
        $I->dontSeeInDatabase('posts_b', ['body_nomarkup' => $body]);
    }
}
