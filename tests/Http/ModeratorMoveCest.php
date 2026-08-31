<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\AdminSession;
use DevichanE2E\Support\HttpAssertions;
use DevichanE2E\Support\HttpTester;
use DevichanE2E\Support\PostFixture;

final class ModeratorMoveCest
{
    use AdminSession;
    use HttpAssertions;
    use PostFixture;

    public function administratorCanMoveAThreadWithAShadow(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $threadBody = 'E2E thread moved between boards ' . bin2hex(random_bytes(4));
        $threadId = $this->createPost($I, 'b', '198.51.100.70', body: $threadBody);
        $replyBody = 'E2E moved reply >>' . $threadId;
        $replyId = $this->createPost(
            $I,
            'b',
            '198.51.100.71',
            $threadId,
            $replyBody,
        );
        $I->haveInDatabase('cites', [
            'board' => 'b',
            'post' => $replyId,
            'target_board' => 'b',
            'target' => $threadId,
        ]);

        $I->amOnPage('/mod.php?/b/move/' . $threadId . '&e2e_move=1');
        $this->assertHealthyPage($I);
        $I->submitForm('form[action="?/b/move/' . $threadId . '"]', [
            'board' => 'sec',
            'e2e_move' => '1',
            'btnSubmit' => 'Move thread',
        ]);
        $this->assertHealthyPage($I);

        $newThreadId = (int) $I->grabFromDatabase('posts_sec', 'id', [
            'body_nomarkup' => $threadBody,
        ]);
        $I->assertGreaterThan(1, $newThreadId);
        $I->seeInDatabase('posts_sec', [
            'thread' => $newThreadId,
            'body_nomarkup' => 'E2E moved reply >>' . $newThreadId,
        ]);
        $I->seeInDatabase('posts_b', ['id' => $threadId, 'locked' => 1]);
        $I->seeInDatabase('posts_b', ['id' => $replyId, 'thread' => $threadId]);
        $I->seeInDatabase('posts_b', [
            'thread' => $threadId,
            'capcode' => 'Mod',
        ]);
    }

    public function administratorCanMoveAThreadWithoutAShadow(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $threadBody = 'E2E no-shadow move ' . bin2hex(random_bytes(4));
        $threadId = $this->createPost($I, 'b', '198.51.100.75', body: $threadBody);
        $replyId = $this->createPost(
            $I,
            'b',
            '198.51.100.76',
            $threadId,
            'E2E reply moved without shadow',
        );

        $I->amOnPage('/mod.php?/b/move/' . $threadId . '&e2e_move=1');
        $this->assertHealthyPage($I);
        $I->uncheckOption('input[name="shadow"]');
        $I->submitForm('form[action="?/b/move/' . $threadId . '"]', [
            'board' => 'sec',
            'e2e_move' => '1',
            'btnSubmit' => 'Move thread',
        ]);
        $this->assertHealthyPage($I);

        $I->seeInDatabase('posts_sec', ['body_nomarkup' => $threadBody]);
        $I->dontSeeInDatabase('posts_b', ['id' => $threadId]);
        $I->dontSeeInDatabase('posts_b', ['id' => $replyId]);
    }

    public function administratorCanMoveAReplyIntoAnExistingThread(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $sourceThread = $this->createPost($I, 'b', '198.51.100.72');
        $replyBody = 'E2E single reply moved ' . bin2hex(random_bytes(4));
        $replyId = $this->createPost(
            $I,
            'b',
            '198.51.100.73',
            $sourceThread,
            $replyBody,
        );
        $targetThread = $this->createPost($I, 'sec', '198.51.100.74');

        $I->amOnPage('/mod.php?/b/move_reply/' . $replyId . '&e2e_move=1');
        $this->assertHealthyPage($I);
        $I->submitForm('form[action="?/b/move_reply/' . $replyId . '"]', [
            'board' => 'sec',
            'target_thread' => (string) $targetThread,
            'e2e_move' => '1',
            'btnSubmit' => 'Move reply',
        ]);
        $this->assertHealthyPage($I);

        $I->seeInDatabase('posts_sec', [
            'thread' => $targetThread,
            'body_nomarkup' => $replyBody,
        ]);
        $I->dontSeeInDatabase('posts_b', ['id' => $replyId]);
    }

    public function administratorCanMoveAReplyAsANewThread(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $sourceThread = $this->createPost($I, 'b', '198.51.100.77');
        $replyBody = 'E2E reply promoted to thread ' . bin2hex(random_bytes(4));
        $replyId = $this->createPost(
            $I,
            'b',
            '198.51.100.78',
            $sourceThread,
            $replyBody,
        );

        $I->amOnPage('/mod.php?/b/move_reply/' . $replyId . '&e2e_move=1');
        $this->assertHealthyPage($I);
        $I->submitForm('form[action="?/b/move_reply/' . $replyId . '"]', [
            'board' => 'sec',
            'target_thread' => '',
            'e2e_move' => '1',
            'btnSubmit' => 'Move reply',
        ]);
        $this->assertHealthyPage($I);

        $I->seeInDatabase('posts_sec', [
            'thread' => null,
            'body_nomarkup' => $replyBody,
        ]);
        $I->dontSeeInDatabase('posts_b', ['id' => $replyId]);
    }
}
