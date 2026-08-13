<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\AdminSession;
use DevichanE2E\Support\HttpAssertions;
use DevichanE2E\Support\HttpTester;

final class ModeratorBoardAndPostCest
{
    use AdminSession;
    use HttpAssertions;

    public function administratorCanCreateEditAndDeleteABoard(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $board = 'e2e';

        $I->amOnPage('/mod.php?/new-board');
        $I->submitForm('form[action="?/new-board"]', [
            'uri' => $board,
            'title' => 'E2E board',
            'subtitle' => 'Created by the integration suite',
        ]);
        $this->assertHealthyPage($I);
        $I->seeInDatabase('boards', ['uri' => $board, 'title' => 'E2E board']);
        $I->amOnPage('/' . $board . '/');
        $this->assertHealthyPage($I);

        $I->amOnPage('/mod.php?/edit/' . $board);
        $I->submitForm('form[action="?/edit/' . $board . '"]', [
            'title' => 'E2E board updated',
            'subtitle' => 'Updated by the integration suite',
        ]);
        $this->assertHealthyPage($I);
        $I->seeInDatabase('boards', [
            'uri' => $board,
            'title' => 'E2E board updated',
        ]);

        $I->amOnPage('/mod.php?/edit/' . $board);
        $I->submitForm('form[action="?/edit/' . $board . '"]', [
            'title' => 'E2E board updated',
            'subtitle' => 'Updated by the integration suite',
            'delete' => 'Delete board',
        ]);
        $I->dontSeeInDatabase('boards', ['uri' => $board]);
    }

    public function administratorCanModerateAThreadLifecycle(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $body = 'E2E moderator thread';

        $I->amOnPage('/mod.php?/b/');
        $postButton = $I->grabAttributeFrom(
            'form[name="post"] input[name="post"]',
            'value',
        );
        $I->submitForm('form[name="post"]', [
            'board' => 'b',
            'body' => $body,
            'password' => 'e2e-mod-thread',
            'embed' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'post' => $postButton,
        ]);
        $this->assertHealthyPage($I);
        $threadId = (int) $I->grabFromDatabase('posts_b', 'id', [
            'body_nomarkup' => $body,
        ]);
        $I->assertGreaterThan(2, $threadId);

        $this->toggleThreadState($I, $threadId, 'sticky', 'unsticky', 'sticky');
        $this->toggleThreadState($I, $threadId, 'lock', 'unlock', 'locked');
        $this->toggleThreadState($I, $threadId, 'cycle', 'uncycle', 'cycle');
        $this->toggleThreadState($I, $threadId, 'bumplock', 'bumpunlock', 'sage');

        $I->amOnPage('/mod.php?/b/edit/' . $threadId);
        $I->submitForm('form[action=""]', [
            'name' => 'E2E moderator',
            'email' => 'sage',
            'subject' => 'Edited subject',
            'body' => 'Edited markup body',
            'post' => 'Update',
        ]);
        $this->assertHealthyPage($I);
        $I->seeInDatabase('posts_b', [
            'id' => $threadId,
            'subject' => 'Edited subject',
            'body_nomarkup' => 'Edited markup body',
        ]);

        $I->amOnPage('/mod.php?/b/edit_raw/' . $threadId);
        $I->submitForm('form[action=""]', [
            'name' => 'E2E moderator',
            'email' => '',
            'subject' => 'Raw subject',
            'body' => '<strong>Raw moderator body</strong>',
            'post' => 'Update',
        ]);
        $this->assertHealthyPage($I);
        $I->seeInDatabase('posts_b', [
            'id' => $threadId,
            'subject' => 'Raw subject',
            'body' => '<strong>Raw moderator body</strong>',
        ]);

        $I->amOnPage('/mod.php?/b/res/' . $threadId . '.html');
        $I->assertSame(1, preg_match(
            "/document\\.location='([^']*b\\/delete\\/{$threadId}\\/[a-f0-9]{8})'/",
            $I->grabPageSource(),
            $matches,
        ));
        $I->amOnPage($matches[1]);
        $I->dontSeeInDatabase('posts_b', ['id' => $threadId]);
    }

    public function administratorCanSpoilerAndDeleteAnUploadedFile(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $body = 'E2E file moderation thread';

        $I->amOnPage('/mod.php?/b/');
        $I->attachFile(
            'form[name="post"] input[name="file"]',
            '../../../static/banners/default.png',
        );
        $I->fillField('form[name="post"] textarea[name="body"]', $body);
        $I->fillField('form[name="post"] input[name="password"]', 'e2e-file');
        $I->click('form[name="post"] input[name="post"]');
        $this->assertHealthyPage($I);
        $threadId = (int) $I->grabFromDatabase('posts_b', 'id', [
            'body_nomarkup' => $body,
        ]);
        $I->assertGreaterThan(2, $threadId);

        $I->amOnPage('/mod.php?/b/res/' . $threadId . '.html');
        $spoilerUrl = $this->grabConfirmedAction(
            $I,
            "b/spoiler/{$threadId}/0",
        );
        $I->amOnPage($spoilerUrl);
        $files = (string) $I->grabFromDatabase('posts_b', 'files', ['id' => $threadId]);
        $I->assertStringContainsString('"thumb":"spoiler"', $files);

        $I->amOnPage('/mod.php?/b/res/' . $threadId . '.html');
        $deleteFileUrl = $this->grabConfirmedAction(
            $I,
            "b/deletefile/{$threadId}/0",
        );
        $I->amOnPage($deleteFileUrl);
        $files = (string) $I->grabFromDatabase('posts_b', 'files', ['id' => $threadId]);
        $I->assertStringContainsString('"file":"deleted"', $files);

        $I->amOnPage('/mod.php?/b/res/' . $threadId . '.html');
        $deleteUrl = $this->grabConfirmedAction($I, "b/delete/{$threadId}");
        $I->amOnPage($deleteUrl);
        $I->dontSeeInDatabase('posts_b', ['id' => $threadId]);
    }

    public function animatedGifUploadsWork(HttpTester $I): void
    {
        $this->loginAsAdmin($I);

        $I->amOnPage('/mod.php?/b/');
        $I->attachFile(
            'form[name="post"] input[name="file"]',
            '../../../static/wolf.gif',
        );
        $I->fillField(
            'form[name="post"] textarea[name="body"]',
            'E2E animated GIF upload',
        );
        $I->fillField('form[name="post"] input[name="password"]', 'e2e-gif');
        $I->click('form[name="post"] input[name="post"]');
        $this->assertHealthyPage($I);
        $I->seeInDatabase('posts_b', [
            'body_nomarkup' => 'E2E animated GIF upload',
            'num_files' => 1,
        ]);
    }

    private function toggleThreadState(
        HttpTester $I,
        int $threadId,
        string $enableAction,
        string $disableAction,
        string $column,
    ): void {
        $I->amOnPage('/mod.php?/b/res/' . $threadId . '.html');
        $enableUrl = $I->grabAttributeFrom(
            'a[href*="b/' . $enableAction . '/' . $threadId . '/"]',
            'href',
        );
        $I->amOnPage($enableUrl);
        $I->seeInDatabase('posts_b', ['id' => $threadId, $column => 1]);

        $I->amOnPage('/mod.php?/b/res/' . $threadId . '.html');
        $disableUrl = $I->grabAttributeFrom(
            'a[href*="b/' . $disableAction . '/' . $threadId . '/"]',
            'href',
        );
        $I->amOnPage($disableUrl);
        $I->seeInDatabase('posts_b', ['id' => $threadId, $column => 0]);
    }

    private function grabConfirmedAction(HttpTester $I, string $action): string
    {
        $I->assertSame(1, preg_match(
            "/document\\.location='([^']*" . preg_quote($action, '/') . "\\/[a-f0-9]{8})'/",
            $I->grabPageSource(),
            $matches,
        ));

        return $matches[1];
    }
}
