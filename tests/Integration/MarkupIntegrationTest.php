<?php

declare(strict_types=1);

namespace DevichanE2E\Integration;

use PHPUnit\Framework\TestCase;

final class MarkupIntegrationTest extends TestCase
{
    private array $originalConfig;

    protected function setUp(): void
    {
        global $config;

        $this->originalConfig = $config;
        reset_events();
        self::assertTrue(openBoard('b'));
    }

    protected function tearDown(): void
    {
        global $config;

        $config = $this->originalConfig;
        reset_events();
        openBoard('b');
    }

    public function testWordFiltersCoverLiteralRegexAndCallbackRules(): void
    {
        global $config;

        $config['wordfilters'] = [
            ['literal', 'replacement'],
            ['/(regex)/i', '<$1>', true],
            ['/(callback)/i', static fn(array $match): string => strtoupper($match[1]), true],
        ];
        $body = 'Literal regex callback';
        wordfilters($body);

        self::assertSame('replacement <regex> CALLBACK', $body);
        self::assertSame(make_comment_hex('>>>/b/ Héllo!'), make_comment_hex('Hello'));

        $config['robot_strip_repeating'] = true;
        self::assertSame(makerobot('Heeellooo'), makerobot('Helo'));
        $config['robot_strip_repeating'] = false;
        self::assertNotSame(makerobot('Heeellooo'), makerobot('Helo'));
    }

    public function testQuotesUnicodeAndModifiersCoverFormattingBranches(): void
    {
        global $config;

        $config['minify_html'] = false;
        self::assertSame("&gt;one\n&gt;two\n", quote('one<br/>two'));
        $config['minify_html'] = true;
        self::assertSame('&gt;one&#010;&gt;two&#010;', quote('one<br/>two'));

        self::assertSame(
            '&hellip; &larr; &rarr; &mdash; &ndash;',
            unicodify('... &lt;-- --&gt; --- --'),
        );

        $body = '<tinyboard flag>ua</tinyboard>'
            . '<tinyboard flag alt>Ukraine</tinyboard>'
            . '<tinyboard escape tag>visible</tinyboard>';
        self::assertSame(
            ['flag' => 'ua', 'flag alt' => 'Ukraine'],
            extract_modifiers($body),
        );
        self::assertSame('', remove_modifiers($body));
    }

    public function testMarkupRendersUrlsCodeQuotesAndCrossBoardCitations(): void
    {
        global $config;

        $config['markup_urls'] = true;
        $config['auto_unicode'] = true;
        $config['track_cites'] = true;
        $config['strip_superfluous_returns'] = true;
        $config['markup_repair_tidy'] = false;

        $body = "> quoted line\n"
            . ">>1 >>>/sec/1 >>>/sec/\n"
            . "https://example.com/path...\n"
            . "```php\necho 1;\n```";
        $tracked = markup($body, true);

        self::assertContains(['b', '1'], array_map(
            static fn(array $cite): array => [$cite[0], (string) $cite[1]],
            $tracked,
        ));
        self::assertContains(['sec', '1'], array_map(
            static fn(array $cite): array => [$cite[0], (string) $cite[1]],
            $tracked,
        ));
        self::assertStringContainsString('class="quote"', $body);
        self::assertStringContainsString('target="_blank"', $body);
        self::assertStringContainsString("language-php", $body);
        self::assertStringContainsString('&hellip;', $body);
        self::assertStringContainsString('&gt;&gt;&gt;/sec/', $body);
    }

    public function testRawMarkupAndMarkupUrlEventsPreserveExplicitHtml(): void
    {
        global $config, $markup_urls;

        $body = '<strong>trusted</strong><tinyboard raw html>1</tinyboard>';
        self::assertSame([], markup($body, true));
        self::assertStringContainsString('<strong>trusted</strong>', $body);

        reset_events();
        event_handler('markup-url', static function (object $link): void {
            $link->rel = 'noopener';
            $link->after = '[after]';
        });
        $markup_urls = [];
        $config['link_prefix'] = '';
        $link = markup_url([1 => 'https://example.com', 2 => '.']);
        self::assertStringContainsString('rel="noopener"', $link);
        self::assertStringEndsWith('[after].', $link);
        self::assertSame(['https://example.com'], $markup_urls);
    }

    public function testDiceRollerSupportsDefaultsModifiersAndInvalidInput(): void
    {
        $single = (object) ['email' => 'dice%20d6', 'body' => 'body'];
        diceRoller($single);
        self::assertStringContainsString('Rolled ', $single->body);

        $multiple = (object) ['email' => 'dice%202d6-2', 'body' => 'body'];
        diceRoller($multiple);
        self::assertStringContainsString(' - 2 = ', $multiple->body);

        $invalid = (object) ['email' => 'dice%200d0', 'body' => 'body'];
        diceRoller($invalid);
        self::assertSame('body', $invalid->body);

        $ignored = (object) ['email' => 'sage', 'body' => 'body'];
        diceRoller($ignored);
        self::assertSame('body', $ignored->body);
    }
}
