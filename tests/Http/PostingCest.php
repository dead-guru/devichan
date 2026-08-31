<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\HttpAssertions;
use DevichanE2E\Support\HttpTester;

final class PostingCest
{
    use HttpAssertions;

    public function visitorCanCreateAnEmbeddedThreadAndReply(HttpTester $I): void
    {
        sleep(2);
        $threadBody = 'E2E embedded thread ' . bin2hex(random_bytes(4));

        $I->amOnPage('/b/');
        $newTopicButton = $I->grabAttributeFrom(
            'form[name=post] input[name=post]',
            'value',
        );
        $I->submitForm('form[name=post]', [
            'board' => 'b',
            'name' => 'E2E visitor',
            'body' => $threadBody,
            'password' => 'e2e-thread',
            'embed' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'post' => $newTopicButton,
        ]);

        $this->assertHealthyPage($I);
        $threadId = (int) $I->grabFromDatabase('posts_b', 'id', [
            'body_nomarkup' => $threadBody,
        ]);
        $I->assertGreaterThan(2, $threadId);

        sleep(2);
        $replyBody = 'E2E reply ' . bin2hex(random_bytes(4));
        $I->amOnPage('/b/res/' . $threadId . '.html');
        $replyButton = $I->grabAttributeFrom(
            'form[name=post] input[name=post]',
            'value',
        );
        $I->submitForm('form[name=post]', [
            'board' => 'b',
            'thread' => $threadId,
            'body' => $replyBody,
            'password' => 'e2e-reply',
            'post' => $replyButton,
        ]);

        $this->assertHealthyPage($I);
        $I->seeInDatabase('posts_b', [
            'thread' => $threadId,
            'body_nomarkup' => $replyBody,
        ]);
    }

    public function visitorCanReportAPost(HttpTester $I): void
    {
        $reason = 'E2E report ' . bin2hex(random_bytes(4));

        $I->amOnPage('/report/?board=b&post=delete_1');
        $this->assertHealthyPage($I);
        $I->submitForm('#report_form', [
            'board' => 'b',
            'delete_1' => '1',
            'reason' => $reason,
            'report' => 'Submit',
        ]);

        $this->assertHealthyPage($I);
        $I->seeInDatabase('reports', [
            'board' => 'b',
            'post' => 1,
            'reason' => $reason,
        ]);
    }

    public function visitorCanDeleteTheirOwnThread(HttpTester $I): void
    {
        sleep(2);
        $body = 'E2E deletable thread ' . bin2hex(random_bytes(4));
        $password = 'e2e-delete';

        $I->amOnPage('/b/');
        $postButton = $I->grabAttributeFrom(
            'form[name="post"] input[name="post"]',
            'value',
        );
        $I->submitForm('form[name="post"]', [
            'board' => 'b',
            'body' => $body,
            'password' => $password,
            'embed' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'post' => $postButton,
        ]);
        $this->assertHealthyPage($I);
        $threadId = (int) $I->grabFromDatabase('posts_b', 'id', [
            'body_nomarkup' => $body,
        ]);

        $I->amOnPage('/b/res/' . $threadId . '.html');
        $I->submitForm('form[name="postcontrols"]', [
            'board' => 'b',
            'delete_' . $threadId => 'on',
            'password' => $password,
            'delete' => 'Delete',
        ]);
        $this->assertHealthyPage($I);
        $I->dontSeeInDatabase('posts_b', ['id' => $threadId]);
    }

    public function visitorCanDeleteTheirOwnUploadedFile(HttpTester $I): void
    {
        sleep(2);
        $body = 'E2E visitor file deletion ' . bin2hex(random_bytes(4));
        $password = 'e2e-user-file';

        $I->amOnPage('/b/');
        $I->attachFile(
            'form[name="post"] input[name="file"]',
            '../../../static/banners/default.png',
        );
        $I->fillField('form[name="post"] textarea[name="body"]', $body);
        $I->fillField('form[name="post"] input[name="password"]', $password);
        $I->click('form[name="post"] input[name="post"]');
        $this->assertHealthyPage($I);
        $threadId = (int) $I->grabFromDatabase('posts_b', 'id', [
            'body_nomarkup' => $body,
        ]);

        $I->sendAjaxPostRequest('/post.php', [
            'json_response' => '1',
            'board' => 'b',
            'password' => $password,
            'delete_' . $threadId => 'on',
            'file' => 'File',
            'delete' => 'Delete',
        ]);
        $I->seeResponseCodeIs(200);
        $files = (string) $I->grabFromDatabase('posts_b', 'files', ['id' => $threadId]);
        $I->assertStringContainsString('"file":"deleted"', $files);
    }

    public function visitorPostingIsRateLimited(HttpTester $I): void
    {
        sleep(2);
        $firstBody = 'E2E flood baseline ' . bin2hex(random_bytes(4));

        $I->amOnPage('/b/');
        $postButton = $I->grabAttributeFrom(
            'form[name="post"] input[name="post"]',
            'value',
        );
        $I->submitForm('form[name="post"]', [
            'board' => 'b',
            'body' => $firstBody,
            'password' => 'e2e-flood',
            'embed' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'post' => $postButton,
        ]);
        $this->assertHealthyPage($I);

        $I->amOnPage('/b/');
        $I->submitForm('form[name="post"]', [
            'board' => 'b',
            'body' => 'E2E flood rejected ' . bin2hex(random_bytes(4)),
            'password' => 'e2e-flood',
            'embed' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'post' => $postButton,
        ]);
        $I->seeResponseCodeIs(500);
        $I->see('Flood detected; Post discarded.');
    }
}
