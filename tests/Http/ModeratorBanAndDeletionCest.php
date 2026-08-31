<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\AdminSession;
use DevichanE2E\Support\HttpAssertions;
use DevichanE2E\Support\HttpTester;
use DevichanE2E\Support\PostFixture;

final class ModeratorBanAndDeletionCest
{
    use AdminSession;
    use HttpAssertions;
    use PostFixture;

    public function administratorCanBanAPostAndPublishTheBanMessage(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $postId = $this->createPost($I, 'b', '198.51.100.60');

        $I->amOnPage('/mod.php?/b/ban/' . $postId);
        $I->submitForm('form[action="?/b/ban/' . $postId . '"]', [
            'ip' => '198.51.100.60',
            'reason' => 'E2E post ban',
            'length' => '1 day',
            'board' => 'b',
            'public_message' => 'on',
            'message' => 'Banned %LENGTH%',
            'new_ban' => 'New Ban',
        ]);
        $this->assertHealthyPage($I);
        $I->seeInDatabase('bans', ['reason' => 'E2E post ban', 'board' => 'b']);
        $body = (string) $I->grabFromDatabase('posts_b', 'body_nomarkup', [
            'id' => $postId,
        ]);
        $I->assertStringContainsString('<tinyboard ban message>', $body);
    }

    public function administratorCanBanAndDeleteAPost(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $postId = $this->createPost($I, 'b', '198.51.100.61');

        $I->amOnPage('/mod.php?/b/ban&delete/' . $postId);
        $I->submitForm('form[action="?/b/ban/' . $postId . '"]', [
            'ip' => '198.51.100.61',
            'reason' => 'E2E ban and delete',
            'length' => '1 day',
            'board' => '*',
            'delete' => '1',
            'new_ban' => 'New Ban',
        ]);
        $this->assertHealthyPage($I);
        $I->seeInDatabase('bans', ['reason' => 'E2E ban and delete']);
        $I->dontSeeInDatabase('posts_b', ['id' => $postId]);
    }

    public function administratorCanDeletePostsByIpOnOneBoard(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $ip = '198.51.100.62';
        $threadId = $this->createPost($I, 'b', $ip);
        $replyId = $this->createPost($I, 'b', $ip, $threadId);

        $I->amOnPage('/mod.php?/b/res/' . $threadId . '.html');
        $deleteUrl = $this->grabSecureAction(
            $I,
            "b/deletebyip/{$threadId}",
        );
        $I->amOnPage($deleteUrl);
        $this->assertHealthyPage($I);
        $I->dontSeeInDatabase('posts_b', ['id' => $threadId]);
        $I->dontSeeInDatabase('posts_b', ['id' => $replyId]);
    }

    public function administratorCanDeletePostsByIpAcrossBoards(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $ip = '198.51.100.63';
        $publicPost = $this->createPost($I, 'b', $ip);
        $secretPost = $this->createPost($I, 'sec', $ip);

        $I->amOnPage('/mod.php?/b/res/' . $publicPost . '.html');
        $deleteUrl = $this->grabSecureAction(
            $I,
            "b/deletebyip/{$publicPost}/global",
        );
        $I->amOnPage($deleteUrl);
        $this->assertHealthyPage($I);
        $I->dontSeeInDatabase('posts_b', ['id' => $publicPost]);
        $I->dontSeeInDatabase('posts_sec', ['id' => $secretPost]);
    }

    public function administratorCanAcceptABanAppeal(HttpTester $I): void
    {
        $this->loginAsAdmin($I);

        $I->amOnPage('/mod.php?/ban');
        $I->submitForm('form[action="?/ban"]', [
            'ip' => '198.51.100.64',
            'reason' => 'E2E appeal acceptance',
            'length' => '1 day',
            'board' => 'b',
            'new_ban' => 'New Ban',
        ]);
        $this->assertHealthyPage($I);
        $banId = (int) $I->grabFromDatabase('bans', 'id', [
            'reason' => 'E2E appeal acceptance',
        ]);
        $appealId = $I->haveInDatabase('ban_appeals', [
            'ban_id' => $banId,
            'time' => time(),
            'message' => 'E2E accepted appeal',
            'denied' => 0,
        ]);

        $I->amOnPage('/mod.php?/ban-appeals');
        $I->submitForm('form[action=""]', [
            'appeal_id' => $appealId,
            'unban' => 'Unban',
        ]);
        $this->assertHealthyPage($I);
        $I->dontSeeInDatabase('ban_appeals', ['id' => $appealId]);
        $I->dontSeeInDatabase('bans', ['id' => $banId]);
    }

    private function grabSecureAction(HttpTester $I, string $action): string
    {
        $I->assertSame(1, preg_match(
            "/document\\.location='([^']*" . preg_quote($action, '/') . "\\/[a-f0-9]{8})'/",
            $I->grabPageSource(),
            $matches,
        ));

        return $matches[1];
    }
}
