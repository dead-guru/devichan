<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\HttpTester;

final class ModeratorFiltersCest
{
    private array $generatedImages = [];

    public function _after(): void
    {
        foreach ($this->generatedImages as $generatedImage) {
            if (is_file($generatedImage)) {
                unlink($generatedImage);
            }
        }
        $this->generatedImages = [];
    }

    public function compoundFieldFilterRejectsMatchingReply(HttpTester $I): void
    {
        $body = 'E2E compound field filter ' . bin2hex(random_bytes(4));
        $I->haveHttpHeader('CF-Connecting-IP', '198.51.100.82');
        $topicButton = $this->topicButton($I);
        $I->sendAjaxPostRequest('/post.php', [
            'post' => $topicButton,
            'board' => 'b',
            'name' => 'E2E Filter Name',
            'email' => 'filter@example.com',
            'subject' => 'E2E Filter Subject',
            'body' => $body,
            'password' => 'e2e-filter-fields',
            'embed' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'e2e_filter_case' => 'fields',
        ]);

        $I->seeResponseCodeIs(400);
        $I->assertStringContainsString('E2E compound filter rejection', $I->grabPageSource());
        $I->dontSeeInDatabase('posts_b', [
            'body_nomarkup' => $body,
        ]);
    }

    public function fileFilterRejectsMatchingUpload(HttpTester $I): void
    {
        $I->setCookie('e2e_filter_case', 'file');
        $I->haveHttpHeader('CF-Connecting-IP', '198.51.100.83');
        $filename = 'e2e-filter-file-' . bin2hex(random_bytes(4)) . '.png';
        $generatedImage = codecept_data_dir($filename);
        $this->generatedImages[] = $generatedImage;
        $image = imagecreatetruecolor(64, 64);
        imagefill($image, 0, 0, imagecolorallocate($image, 20, 60, 120));
        $I->assertTrue(imagepng($image, $generatedImage));
        imagedestroy($image);

        $I->amOnPage('/b/');
        $I->attachFile('form[name="post"] input[name="file"]', $filename);
        $I->fillField('form[name="post"] textarea[name="body"]', 'E2E file filter');
        $I->fillField('form[name="post"] input[name="password"]', 'e2e-file-filter');
        $I->click('form[name="post"] input[name="post"]');

        $I->seeResponseCodeIs(400);
        $I->see('E2E file filter rejection');
        $I->dontSeeInDatabase('posts_b', ['body_nomarkup' => 'E2E file filter']);
    }

    public function filterCanAutomaticallyBanAVisitor(HttpTester $I): void
    {
        $token = bin2hex(random_bytes(4));
        $reason = 'E2E automatic filter ban ' . $token;
        $ip = sprintf('198.51.%d.%d', random_int(1, 254), random_int(1, 254));
        $replyButton = $this->replyButton($I);
        $I->haveHttpHeader('CF-Connecting-IP', $ip);
        $I->sendAjaxPostRequest('/post.php', [
            'post' => $replyButton,
            'board' => 'b',
            'thread' => 1,
            'body' => 'E2E filter autoban ' . $token,
            'password' => 'e2e-autoban',
            'e2e_filter_case' => 'ban',
            'e2e_filter_token' => $token,
        ]);

        $I->seeResponseCodeIs(200);
        $I->seeInDatabase('bans', [
            'reason' => $reason,
            'board' => null,
            'seen' => 1,
        ]);
        $I->dontSeeInDatabase('posts_b', [
            'body_nomarkup' => 'E2E filter autoban ' . $token,
        ]);
    }

    public function floodFilterRejectsARepeatedReply(HttpTester $I): void
    {
        $token = bin2hex(random_bytes(4));
        $ip = sprintf('203.0.%d.%d', random_int(1, 254), random_int(1, 254));
        $replyButton = $this->replyButton($I);
        $I->haveHttpHeader('CF-Connecting-IP', $ip);

        foreach (['first', 'second'] as $attempt) {
            $I->sendAjaxPostRequest('/post.php', [
                'post' => $replyButton,
                'board' => 'b',
                'thread' => 1,
                'body' => 'E2E flood filter ' . $token . ' ' . $attempt,
                'password' => 'e2e-flood-filter',
                'e2e_filter_case' => 'flood',
            ]);

            if ($attempt === 'first') {
                $I->seeResponseCodeIs(200);
            } else {
                $I->seeResponseCodeIs(400);
                $I->assertStringContainsString(
                    'E2E flood filter rejection',
                    $I->grabPageSource(),
                );
            }
        }
    }

    private function replyButton(HttpTester $I): string
    {
        $I->amOnPage('/b/res/1.html');

        return (string) $I->grabAttributeFrom(
            'form[name="post"] input[name="post"]',
            'value',
        );
    }

    private function topicButton(HttpTester $I): string
    {
        $I->amOnPage('/b/');

        return (string) $I->grabAttributeFrom(
            'form[name="post"] input[name="post"]',
            'value',
        );
    }
}
