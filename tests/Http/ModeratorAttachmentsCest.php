<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\AdminSession;
use DevichanE2E\Support\HttpAssertions;
use DevichanE2E\Support\HttpTester;
use DevichanE2E\Support\AttachmentCleanup;

final class ModeratorAttachmentsCest
{
    use AttachmentCleanup;
    use AdminSession;
    use HttpAssertions;

    public function replacesOneReplyAttachmentAndDeletesItsFiles(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $id = $this->fixturePost($I, true);
        $old = $this->files($I, $id);
        $I->amOnPage('/mod.php?/b/edit/' . $id);
        $I->seeNumberOfElements('input[type="file"]', 3);
        $I->seeNumberOfElements('.attachment-preview', 3);
        $I->seeNumberOfElements('.edit-attachment[data-editable="1"]', 2);
        $I->attachFile('#replacement-1', '../../../static/banners/default.png');
        $I->click('input[name="post"]');
        $this->assertHealthyPage($I);
        $I->seeCurrentUrlEquals('/mod.php?/b/res/1.html#' . $id);
        $new = $this->files($I, $id);
        $I->assertSame($old[0], $new[0]);
        $I->assertSame($old[2], $new[2]);
        $I->assertNotSame($old[1]['file'], $new[1]['file']);
        $I->assertFileDoesNotExist('b/src/' . $old[1]['file']);
        $I->assertFileDoesNotExist('b/thumb/' . $old[1]['thumb']);
        $I->assertFileExists('b/src/' . $new[1]['file']);
        $I->assertFileExists('b/thumb/' . $new[1]['thumb']);
        $I->assertSame(md5($old[0]['hash'] . $new[1]['hash'] . $old[2]['hash']), $I->grabFromDatabase('posts_b', 'filehash', ['id' => $id]));
        $I->seeInDatabase('posts_b', ['id' => $id, 'time' => 1700000000, 'bump' => 1700000000, 'num_files' => 3]);
        $I->seeInDatabase('modlogs', ['text' => 'Replaced file #2 of post #' . $id]);
        $I->amOnPage('/b/res/1.json');
        $I->see(pathinfo($new[1]['file'], PATHINFO_FILENAME));
    }

    public function rawEditReplacesTheOpAndRebuildsPublicPages(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $id = $this->fixturePost($I, false);
        $old = $this->files($I, $id)[0];
        $I->amOnPage('/mod.php?/b/edit_raw/' . $id);
        $I->attachFile('#replacement-0', '../../../static/banners/default.png');
        $I->fillField('textarea[name="body"]', '<strong>Edited attachment</strong>');
        $I->click('input[name="post"]');
        $this->assertHealthyPage($I);
        $new = $this->files($I, $id)[0];
        $I->assertFileDoesNotExist('b/src/' . $old['file']);
        $I->assertFileDoesNotExist('b/thumb/' . $old['thumb']);
        $I->seeInDatabase('posts_b', ['id' => $id, 'time' => 1700000000, 'bump' => 1700000000, 'filehash' => $new['hash']]);
        $I->amOnPage('/b/res/' . $id . '.html');
        $I->see('Edited attachment');
        $I->seeElement('a[href$="/b/src/' . $new['file'] . '"]');
        $I->amOnPage('/b/catalog.html');
        $I->seeElement('img[src$="/b/thumb/' . $new['thumb'] . '"]');
    }

    public function invalidReplacementPreservesAllFilesAndText(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $id = $this->fixturePost($I, true);
        $old = $this->files($I, $id);
        $before = glob('b/src/*');
        $thumbs = glob('b/thumb/*');
        $I->amOnPage('/mod.php?/b/edit/' . $id);
        $I->attachFile('#replacement-0', '../../../static/banners/default.png');
        $I->attachFile('#replacement-1', 'invalid-image.png');
        $I->fillField('textarea[name="body"]', 'Must not be saved');
        $I->click('input[name="post"]');
        $I->seeResponseCodeIs(400);
        $I->assertSame($old, $this->files($I, $id));
        $I->assertSame($before, glob('b/src/*'));
        $I->assertSame($thumbs, glob('b/thumb/*'));
        $I->dontSeeInDatabase('posts_b', ['id' => $id, 'body_nomarkup' => 'Must not be saved']);
    }

    public function rejectsAStaleEditorAndInvalidCsrfToken(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $id = $this->fixturePost($I, false);
        $old = $this->files($I, $id);
        $I->amOnPage('/mod.php?/b/edit/' . $id);
        $I->attachFile('#replacement-0', '../../../static/banners/default.png');
        $I->submitForm('#edit-post', ['original_file[0]' => 'stale.png']);
        $I->seeResponseCodeIs(409);
        $I->assertSame($old, $this->files($I, $id));
        $I->amOnPage('/mod.php?/b/edit/' . $id);
        $I->attachFile('#replacement-0', '../../../static/banners/default.png');
        $I->submitForm('#edit-post', ['token' => 'invalid']);
        $I->seeResponseCodeIs(400);
        $I->assertSame($old, $this->files($I, $id));
    }

    public function replacementUsesBoardFileLimits(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $id = $this->fixturePost($I, false);
        $old = $this->files($I, $id);
        foreach (['tiny-file-limit', 'restricted-op-extension'] as $limit) {
            $I->setCookie('e2e_post_case', $limit);
            $I->amOnPage('/mod.php?/b/edit/' . $id);
            $I->attachFile('#replacement-0', '../../../static/banners/default.png');
            $I->click('input[name="post"]');
            $I->seeResponseCodeIs(400);
            $I->assertSame($old, $this->files($I, $id));
        }
        $I->resetCookie('e2e_post_case');
    }

    public function moderatorCanReplaceButCannotChangeTextOrOtherBoards(HttpTester $I): void
    {
        $id = $this->fixturePost($I, false);
        $admin = [
            'password' => $I->grabFromDatabase('mods', 'password', ['id' => 1]),
            'version' => $I->grabFromDatabase('mods', 'version', ['id' => 1]),
        ];
        $username = 'files-' . bin2hex(random_bytes(3));
        $I->haveInDatabase('mods', $admin + ['username' => $username, 'type' => 20, 'boards' => 'b']);
        $I->amOnPage('/mod/');
        $I->submitForm('form', ['username' => $username, 'password' => 'password', 'login' => 'Continue']);
        $I->amOnPage('/mod.php?/b/edit/' . $id);
        $I->seeElement('textarea[name="body"][readonly]');
        $I->attachFile('#replacement-0', '../../../static/banners/default.png');
        $I->submitForm('#edit-post', ['body' => 'Unauthorized text change']);
        $this->assertHealthyPage($I);
        $I->dontSeeInDatabase('posts_b', ['id' => $id, 'body_nomarkup' => 'Unauthorized text change']);
        $I->assertSame('default.png', $this->files($I, $id)[0]['filename']);
        $I->amOnPage('/mod.php?/sec/edit/1');
        $I->seeResponseCodeIs(403);
        $I->amOnPage('/mod.php?/b/edit_raw/' . $id);
        $I->seeResponseCodeIs(403);
    }

    public function janitorCannotOpenTheAttachmentEditor(HttpTester $I): void
    {
        $username = 'janitor-' . bin2hex(random_bytes(3));
        $I->haveInDatabase('mods', [
            'username' => $username,
            'password' => $I->grabFromDatabase('mods', 'password', ['id' => 1]),
            'version' => $I->grabFromDatabase('mods', 'version', ['id' => 1]),
            'type' => 10,
            'boards' => '*',
        ]);
        $I->amOnPage('/mod/');
        $I->submitForm('form', ['username' => $username, 'password' => 'password', 'login' => 'Continue']);
        $I->amOnPage('/mod.php?/b/edit/1');
        $I->seeResponseCodeIs(403);
    }

    private function fixturePost(HttpTester $I, bool $reply): int
    {
        $files = [];
        for ($i = 0; $i < ($reply ? 3 : 1); $i++) {
            $text = $reply && $i === 0;
            $name = 'edit-test-' . bin2hex(random_bytes(5)) . ($text ? '.txt' : '.png');
            if ($text) file_put_contents('b/src/' . $name, 'Attachment text file');
            else {
                copy('static/banners/default.png', 'b/src/' . $name);
                copy('static/banners/default.png', 'b/thumb/' . $name);
            }
            $files[] = ['file' => $name, 'thumb' => $text ? 'file' : $name, 'name' => $name, 'filename' => $name,
                'size' => filesize('b/src/' . $name), 'hash' => md5_file('b/src/' . $name),
                'width' => 300, 'height' => 100, 'thumbwidth' => 150, 'thumbheight' => 50];
        }
        $id = (int) $I->haveInDatabase('posts_b', [
            'thread' => $reply ? 1 : null, 'name' => 'Anonymous', 'body' => 'Attachment fixture',
            'body_nomarkup' => 'Attachment fixture', 'time' => 1700000000, 'bump' => 1700000000,
            'files' => json_encode($files), 'num_files' => count($files), 'ip' => '127.0.0.10',
            'sticky' => 0, 'locked' => 0, 'cycle' => 0, 'sage' => 0,
        ]);
        $this->attachmentPostIds[] = $id;
        return $id;
    }

    private function files(HttpTester $I, int $id): array
    {
        return json_decode((string) $I->grabFromDatabase('posts_b', 'files', ['id' => $id]), true);
    }
}
