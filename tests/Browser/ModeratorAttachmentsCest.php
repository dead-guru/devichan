<?php

declare(strict_types=1);

namespace DevichanE2E\Browser;

use DevichanE2E\Support\BrowserTester;
use DevichanE2E\Support\AttachmentCleanup;

final class ModeratorAttachmentsCest
{
    use AttachmentCleanup;
    public function drawingStaysPendingUntilThePostIsSaved(BrowserTester $I): void
    {
        $name = 'paint-test-' . bin2hex(random_bytes(5)) . '.png';
        $image = imagecreatetruecolor(1800, 1200);
        imagefill($image, 0, 0, imagecolorallocate($image, 64, 128, 192));
        imagepng($image, 'b/src/' . $name);
        imagedestroy($image);
        copy('static/banners/default.png', 'b/thumb/' . $name);
        $files = [['file' => $name, 'thumb' => $name, 'filename' => 'original.png',
            'size' => filesize('b/src/' . $name), 'width' => 1800, 'height' => 1200,
            'thumbwidth' => 150, 'thumbheight' => 50]];
        $id = (int) $I->haveInDatabase('posts_b', [
            'thread' => 1, 'name' => 'Anonymous', 'body' => 'Paint fixture', 'body_nomarkup' => 'Paint fixture',
            'time' => 1700000000, 'bump' => 1700000000, 'files' => json_encode($files),
            'num_files' => 1, 'ip' => '127.0.0.10', 'sticky' => 0, 'locked' => 0, 'cycle' => 0, 'sage' => 0,
        ]);
        $this->attachmentPostIds[] = $id;
        $I->amOnPage('/mod/');
        $I->fillField('input[name="username"]', 'admin');
        $I->fillField('input[name="password"]', 'password');
        $I->click('input[name="login"]');
        $I->waitForElement('body.is-moderator');
        $I->amOnPage('/mod.php?/b/edit/' . $id);
        $I->waitForElementVisible('.edit-attachment-image');
        $I->click('.edit-attachment-image');
        $I->waitForElementVisible('.paint-canvas');
        $I->click('.paint-actions [data-action="cancel"]');
        $I->dontSeeElement('.paint-modal-overlay');
        $I->assertSame(0, $I->executeJS('return document.querySelector("#replacement-0").files.length;'));

        $this->drawAndApply($I, 1800, 1200);
        $this->drawAndApply($I, 1800, 1200);
        $I->assertSame(json_encode($files), $I->grabFromDatabase('posts_b', 'files', ['id' => $id]));
        $I->assertFileExists('b/src/' . $name);
        $I->click('.reset-attachment');
        $I->assertSame(0, $I->executeJS('return document.querySelector("#replacement-0").files.length;'));
        $this->drawAndApply($I, 1800, 1200);
        $I->makeScreenshot('moderator-attachment-pending');
        $I->click('#edit-post input[name="post"]');
        $I->waitForElement('#reply_' . $id);
        $new = json_decode((string) $I->grabFromDatabase('posts_b', 'files', ['id' => $id]), true)[0];
        $I->assertNotSame($name, $new['file']);
        $I->assertSame('original.png', $new['filename']);
        $I->assertSame([1800, 1200], [$new['width'], $new['height']]);
        $I->assertFileDoesNotExist('b/src/' . $name);
        $I->assertFileDoesNotExist('b/thumb/' . $name);
        $image = imagecreatefrompng('b/src/' . $new['file']);
        $I->assertSame(0xFFFFFF, imagecolorat($image, 0, 0) & 0xFFFFFF);
        imagedestroy($image);
        $I->seeInDatabase('posts_b', ['id' => $id, 'body_nomarkup' => 'Paint fixture', 'time' => 1700000000, 'bump' => 1700000000]);
    }

    public function animatedAttachmentsOnlyOfferReplace(BrowserTester $I): void
    {
        $name = 'paint-test-' . bin2hex(random_bytes(5)) . '.gif';
        copy('static/wolf.gif', 'b/src/' . $name);
        $id = (int) $I->haveInDatabase('posts_b', [
            'thread' => 1, 'name' => 'Anonymous', 'body' => 'Animated fixture', 'body_nomarkup' => 'Animated fixture',
            'time' => 1700000000, 'files' => json_encode([['file' => $name, 'thumb' => 'file', 'filename' => 'animated.gif', 'size' => filesize('b/src/' . $name)]]),
            'num_files' => 1, 'ip' => '127.0.0.10', 'sticky' => 0, 'locked' => 0, 'cycle' => 0, 'sage' => 0,
        ]);
        $this->attachmentPostIds[] = $id;
        $I->amOnPage('/mod/');
        $I->fillField('input[name="username"]', 'admin');
        $I->fillField('input[name="password"]', 'password');
        $I->click('input[name="login"]');
        $I->waitForElement('body.is-moderator');
        $I->amOnPage('/mod.php?/b/edit/' . $id);
        $I->seeElement('.replace-attachment');
        $I->dontSeeElement('.edit-attachment-image');

        $I->attachFile('#replacement-0', '../../../static/banners/default.png');
        $I->waitForElementVisible('.edit-attachment-image');
        $this->drawAndApply($I);
        $I->click('.reset-attachment');
        $I->dontSeeElement('.edit-attachment-image');
    }

    private function drawAndApply(BrowserTester $I, int $width = 300, int $height = 100): void
    {
        $I->click('.edit-attachment-image');
        $I->waitForJS('return window.paintTool.engine && window.paintTool.engine.history.length === 1 && document.querySelector("#paint-w").value != "600";', 10);
        $I->assertSame([$width, $height, $width, $height], $I->executeJS('return [document.querySelector(".paint-canvas").width, document.querySelector(".paint-canvas").height, document.querySelector(".paint-ruler-top").width, document.querySelector(".paint-ruler-left").height];'));
        $I->assertTrue($I->executeJS('const r = document.querySelector(".paint-canvas").getBoundingClientRect(); return r.width < innerWidth && r.height < innerHeight;'));
        $I->waitForJS('return document.fonts.check(\'900 14px "Font Awesome 6 Free"\');', 10);
        $I->assertLessThanOrEqual(1, $I->executeJS('const a = document.querySelector(".paint-actions [data-action=done]").getBoundingClientRect(); const b = document.querySelector(".paint-actions [data-action=cancel]").getBoundingClientRect(); return Math.abs(a.top + a.height / 2 - b.top - b.height / 2);'));
        $I->makeScreenshot('moderator-paint-' . $width);
        $I->click('.paint-toolbar [data-action="clear"]');
        $I->click('.paint-actions [data-action="done"]');
        $I->waitForElementNotVisible('.paint-modal-overlay');
        $I->assertSame(1, $I->executeJS('return document.querySelector("#replacement-0").files.length;'));
    }
}
