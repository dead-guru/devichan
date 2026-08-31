<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\HttpTester;
use DevichanE2E\Support\PostFixture;

final class PostingBranchMatrixCest
{
    use PostFixture;

    private array $generatedImages = [];

    public function _after(HttpTester $I): void
    {
        $I->deleteHeader('X-E2E-Post-Case');
        $I->deleteHeader('CF-Connecting-IP');
        $I->resetCookie('e2e_post_case');
        foreach ($this->generatedImages as $image) {
            if (is_file($image)) {
                unlink($image);
            }
        }
        $this->generatedImages = [];
    }

    public function lockedBoardRejectsPostDeleteAndReport(HttpTester $I): void
    {
        $this->setCase($I, 'locked');
        $thread = $this->createPost($I, 'b', '198.51.100.81');

        foreach ([
            [
                'api' => 'e2e-api-key',
                'board' => 'b',
                'body' => 'E2E locked board post',
            ],
            [
                'board' => 'b',
                'password' => 'e2e-fixture',
                'delete_' . $thread => 'on',
                'delete' => 'Delete',
            ],
            [
                'board' => 'b',
                'reason' => 'E2E locked report',
                'delete_' . $thread => 'on',
                'report' => 'Submit',
            ],
        ] as $request) {
            $I->sendAjaxPostRequest('/post.php', $request);
            $I->seeResponseCodeIs(400);
            $I->assertStringContainsString('Board is locked', $I->grabPageSource());
        }
    }

    public function deletionAndReportPoliciesRejectDisallowedRequests(HttpTester $I): void
    {
        $thread = $this->createPost($I, 'b', '198.51.100.82');
        $this->setCase($I, 'no-delete');
        $I->sendAjaxPostRequest('/post.php', [
            'board' => 'b',
            'password' => 'e2e-fixture',
            'delete_' . $thread => 'on',
            'delete' => 'Delete',
        ]);
        $I->seeResponseCodeIs(400);
        $I->seeInDatabase('posts_b', ['id' => $thread]);

        $this->setCase($I, 'report-limit');
        $I->sendAjaxPostRequest('/post.php', [
            'board' => 'b',
            'reason' => 'E2E too many reports',
            'delete_1' => 'on',
            'delete_' . $thread => 'on',
            'report' => 'Submit',
        ]);
        $I->seeResponseCodeIs(400);

        $this->setCase($I, 'report-captcha');
        $I->sendAjaxPostRequest('/post.php', [
            'board' => 'b',
            'reason' => 'E2E missing report captcha',
            'delete_1' => 'on',
            'report' => 'Submit',
        ]);
        $I->seeResponseCodeIs(400);
    }

    public function disabledFieldsAreForcedToSafeValues(HttpTester $I): void
    {
        $this->setCase($I, 'disabled-fields');
        $body = 'E2E disabled fields ' . bin2hex(random_bytes(4));
        $I->sendAjaxPostRequest('/post.php', [
            'api' => 'e2e-api-key',
            'json_response' => '1',
            'board' => 'b',
            'name' => 'Must disappear',
            'email' => 'must@example.com',
            'subject' => 'Must disappear',
            'password' => 'must-disappear',
            'body' => $body,
            'embed' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
        $I->seeResponseCodeIs(200);
        $I->seeInDatabase('posts_b', [
            'body_nomarkup' => $body,
            'name' => 'Anonymous',
            'email' => null,
            'subject' => null,
            'password' => '',
        ]);
    }

    public function bodyImageUrlAndFlagValidationCoverPolicyBranches(HttpTester $I): void
    {
        $this->setCase($I, 'force-image');
        $I->sendAjaxPostRequest('/post.php', [
            'api' => 'e2e-api-key',
            'board' => 'b',
            'body' => 'E2E requires an image',
        ]);
        $I->seeResponseCodeIs(400);

        $this->setCase($I, 'force-body');
        $I->sendAjaxPostRequest('/post.php', [
            'api' => 'e2e-api-key',
            'board' => 'b',
            'body' => " \n\t ",
            'embed' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
        $I->seeResponseCodeIs(400);

        $I->setCookie('e2e_upload_by_url', '1');
        foreach (['file:///etc/passwd', 'http://caddy/file.e2e-unknown'] as $url) {
            $I->sendAjaxPostRequest('/post.php', [
                'api' => 'e2e-api-key',
                'board' => 'b',
                'body' => 'E2E invalid URL upload',
                'file_url' => $url,
            ]);
            $I->seeResponseCodeIs(400);
        }

        $I->sendAjaxPostRequest('/post.php', [
            'api' => 'e2e-api-key',
            'board' => 'b',
            'body' => 'E2E invalid flag',
            'user_flag' => 'missing-flag',
            'embed' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
        $I->seeResponseCodeIs(400);
    }

    public function uploadedImagesExerciseSizeCountMethodAndExtensionPolicies(HttpTester $I): void
    {
        foreach ([
            'invalid-multiimage',
            'each-multiimage',
            'tiny-file-limit',
            'zero-image-limit',
            'restricted-op-extension',
        ] as $index => $case) {
            $filename = $this->createPng($I);
            $this->setCase($I, $case);
            $I->haveHttpHeader('CF-Connecting-IP', '198.51.100.' . (100 + $index));
            $I->amOnPage('/b/');
            $I->attachFile('form[name="post"] input[name="file"]', $filename);
            $I->fillField('form[name="post"] textarea[name="body"]', 'E2E upload branch ' . $case);
            $I->click('form[name="post"] input[name="post"]');

            if ($case === 'each-multiimage') {
                $I->seeResponseCodeIs(200);
                $I->seeInDatabase('posts_b', ['body_nomarkup' => 'E2E upload branch ' . $case]);
            } else {
                $I->seeResponseCodeIs(400);
                $I->dontSeeInDatabase('posts_b', ['body_nomarkup' => 'E2E upload branch ' . $case]);
            }
        }
    }

    public function combiningCharactersAndNokoVariantsArePersistedCorrectly(HttpTester $I): void
    {
        $this->setCase($I, 'strip-combining');
        $body = "E2E combining e\u{0301} " . bin2hex(random_bytes(4));
        $I->sendAjaxPostRequest('/post.php', [
            'api' => 'e2e-api-key',
            'json_response' => '1',
            'board' => 'b',
            'name' => "N\u{0301}ame",
            'subject' => "S\u{0301}ubject",
            'body' => $body,
            'embed' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
        $I->seeResponseCodeIs(200);

        $this->setCase($I, 'noko-email');
        foreach (['noko', 'nonoko', 'person@example.com'] as $email) {
            $postBody = 'E2E email ' . $email . ' ' . bin2hex(random_bytes(3));
            $I->sendAjaxPostRequest('/post.php', [
                'api' => 'e2e-api-key',
                'json_response' => '1',
                'board' => 'b',
                'email' => $email,
                'body' => $postBody,
                'embed' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ]);
            $I->seeResponseCodeIs(200);
            $I->seeInDatabase('posts_b', [
                'body_nomarkup' => $postBody,
                'email' => in_array($email, ['noko', 'nonoko'], true) ? null : $email,
            ]);
        }
    }

    private function setCase(HttpTester $I, string $case): void
    {
        $I->resetCookie('e2e_post_case');
        $I->haveHttpHeader('X-E2E-Post-Case', $case);
    }

    private function createPng(HttpTester $I): string
    {
        $filename = 'posting-branch-' . bin2hex(random_bytes(4)) . '.png';
        $path = codecept_data_dir($filename);
        $this->generatedImages[] = $path;
        $image = imagecreatetruecolor(32, 32);
        $color = imagecolorallocate($image, 40, 120, 180);
        imagefill($image, 0, 0, $color);
        $I->assertTrue(imagepng($image, $path));
        imagedestroy($image);

        return $filename;
    }
}
