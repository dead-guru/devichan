<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\HttpAssertions;
use DevichanE2E\Support\HttpTester;

final class PostingMarkupCest
{
    use HttpAssertions;

    public function geoipPostingKeepsModeratorCountriesPrivateAndSurvivesDatabaseFailures(HttpTester $I): void
    {
        $I->haveHttpHeader('CF-Connecting-IP', '81.2.69.142');
        try {
            foreach (['geoip-private', 'geoip-public', 'geoip-opt-out', 'geoip-missing', 'geoip-corrupt'] as $case) {
                $I->comment($case);
                $I->haveHttpHeader('X-E2E-Post-Case', $case);
                $body = 'GeoIP posting ' . $case . ' ' . bin2hex(random_bytes(4));
                $request = [
                    'api' => 'e2e-api-key',
                    'json_response' => '1',
                    'board' => 'b',
                    'thread' => '1',
                    'body' => $body,
                    'password' => 'e2e-geoip',
                ];
                if ($case === 'geoip-opt-out') {
                    $request['no_country'] = '1';
                }
                $I->sendAjaxPostRequest('/post.php', $request);
                $I->seeResponseCodeIs(200);
                $response = json_decode($I->grabPageSource(), true, flags: JSON_THROW_ON_ERROR);
                $I->assertArrayHasKey('id', $response);
                $postId = (int) $response['id'];
                $I->seeInDatabase('posts_b', ['id' => $postId, 'ip' => '81.2.69.142']);

                $expected = $body;
                if ($case === 'geoip-public') {
                    $expected .= "\n<tinyboard flag>gb</tinyboard>\n<tinyboard flag alt>United Kingdom</tinyboard>";
                }
                $I->assertSame($expected, $I->grabFromDatabase('posts_b', 'body_nomarkup', ['id' => $postId]));

                $I->amOnPage('/b/res/1.json');
                $I->seeResponseCodeIs(200);
                $thread = json_decode($I->grabPageSource(), true, flags: JSON_THROW_ON_ERROR);
                $posts = array_column($thread['posts'], null, 'no');
                $I->assertArrayHasKey($postId, $posts);
                if ($case === 'geoip-public') {
                    $I->assertSame('GB', $posts[$postId]['country']);
                    $I->assertSame('United Kingdom', $posts[$postId]['country_name']);
                } else {
                    $I->assertArrayNotHasKey('country', $posts[$postId]);
                    $I->assertArrayNotHasKey('country_name', $posts[$postId]);
                }

                $I->amOnPage('/b/res/1.html');
                $I->seeResponseCodeIs(200);
                $I->dontSeeElement('#reply_' . $postId . ' .mod-ip-flag');
                if ($case === 'geoip-public') {
                    $I->seeElement('#reply_' . $postId . ' img.flag-gb');
                } else {
                    $I->dontSeeElement('#reply_' . $postId . ' img.flag');
                }
            }
        } finally {
            $I->deleteHeader('X-E2E-Post-Case');
            $I->deleteHeader('CF-Connecting-IP');
        }
    }

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

        $I->seeResponseCodeIs(400);
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
