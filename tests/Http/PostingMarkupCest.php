<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\HttpAssertions;
use DevichanE2E\Support\HttpTester;

final class PostingMarkupCest
{
    use HttpAssertions;

    public function postMarkupTracksLinksCitesFlagsAndTags(HttpTester $I): void
    {
        $body = implode("\n", [
            'E2E replace me...',
            '>>1',
            '>>>/sec/1',
            '>quoted line',
            '**spoiler text**',
            'https://example.com/e2e',
            "```php\necho 'e2e';\n```",
        ]);

        $I->sendAjaxPostRequest('/post.php', [
            'api' => 'e2e-api-key',
            'json_response' => '1',
            'board' => 'b',
            'thread' => '1',
            'name' => 'E2E trip#password',
            'email' => 'noko',
            'body' => $body,
            'password' => 'e2e-markup',
            'user_flag' => 'ua',
            'tag' => 'e2e',
            'e2e_tags' => '1',
        ]);
        $I->seeResponseCodeIs(200);
        $response = json_decode(
            $I->grabPageSource(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $postId = (int) $response['id'];

        $storedBody = (string) $I->grabFromDatabase('posts_b', 'body', ['id' => $postId]);
        $I->assertStringContainsString('E2E replaced&hellip;', $storedBody);
        $I->assertStringContainsString('class="quote"', $storedBody);
        $I->assertStringContainsString('class="spoiler"', $storedBody);
        $I->assertStringContainsString('<pre class=', $storedBody);
        $I->assertStringContainsString('href="https://example.com/e2e"', $storedBody);
        $I->seeInDatabase('cites', [
            'board' => 'b',
            'post' => $postId,
            'target_board' => 'b',
            'target' => 1,
        ]);
        $I->seeInDatabase('cites', [
            'board' => 'b',
            'post' => $postId,
            'target_board' => 'sec',
            'target' => 1,
        ]);
        $I->assertNotSame('', $I->grabFromDatabase('posts_b', 'trip', ['id' => $postId]));
    }

    public function configuredFilterRejectsMatchingPostsAndAddsANote(HttpTester $I): void
    {
        sleep(2);
        $I->amOnPage('/b/');
        $postButton = $I->grabAttributeFrom(
            'form[name="post"] input[name="post"]',
            'value',
        );
        $I->submitForm('form[name="post"]', [
            'board' => 'b',
            'body' => 'E2E blocked by filter',
            'password' => 'e2e-filter',
            'embed' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'post' => $postButton,
        ]);

        $I->seeResponseCodeIs(500);
        $I->see('E2E filter rejection');
        $I->seeInDatabase('ip_notes', [
            'mod' => -1,
            'body' => 'Autoban message: E2E blocked by filter',
        ]);
        $I->dontSeeInDatabase('posts_b', [
            'body_nomarkup' => 'E2E blocked by filter',
        ]);
    }
}
