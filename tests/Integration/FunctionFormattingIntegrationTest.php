<?php

declare(strict_types=1);

namespace DevichanE2E\Integration;

use PHPUnit\Framework\TestCase;

final class FunctionFormattingIntegrationTest extends TestCase
{
    private array $originalConfig;

    protected function setUp(): void
    {
        global $config;

        $this->originalConfig = $config;
    }

    protected function tearDown(): void
    {
        global $config;

        $config = $this->originalConfig;
    }

    public function testPlaceholdersAndMultibyteReplacementWork(): void
    {
        self::assertSame(
            'Hello Alice, #42',
            sprintf3('Hello %name%, #%id%', ['name' => 'Alice', 'id' => 42]),
        );
        self::assertSame(
            'Hello Alice',
            sprintf3('Hello :name:', ['name' => 'Alice'], ':'),
        );
        self::assertSame('абXYґ', mb_substr_replace('абвгґ', 'XY', 2, 2));
    }

    public function testRelativeTimeFormattingCoversEveryUnit(): void
    {
        $now = time();
        $futureOffsets = [1, 120, 7200, 172800, 1209600, 63072000];
        $pastOffsets = [1, 120, 7200, 172800, 1209600, 63072000];

        foreach ($futureOffsets as $offset) {
            self::assertNotSame('', until($now + $offset));
        }
        foreach ($pastOffsets as $offset) {
            self::assertNotSame('', ago($now - $offset));
        }
    }

    public function testBoardAndPostLinksUseEveryConfiguredShape(): void
    {
        global $board, $config;

        self::assertTrue(openBoard('b'));
        self::assertSame('Random', boardTitle('b'));
        self::assertFalse(boardTitle('missing'));

        $config['slugify'] = true;
        $post = ['id' => 10, 'thread' => null, 'slug' => 'example-thread'];
        self::assertSame('10-example-thread.html', link_for($post));
        self::assertSame('10-example-thread+50.html', link_for($post, true));

        $config['slugify'] = false;
        self::assertSame('10.html', link_for($post));
        self::assertSame('10+50.html', link_for($post, true));

        self::assertSame('b', $board['uri']);
    }

    public function testTextEncodingAndCleanupHelpersKeepValidMarkup(): void
    {
        global $config;

        self::assertSame('&quot;&lt;&amp;&gt;', utf8tohtml('"<&>'));
        self::assertSame(
            '<tinyboard escape flag>ua</tinyboard>',
            escape_markup_modifiers('<tinyboard flag>ua</tinyboard>'),
        );
        self::assertSame('ab', strip_combining_chars("a\u{0301}b"));
        self::assertSame('a&#13;&#10;b&#09;c', prettify_textarea("a\nb\tc"));
        self::assertSame(['https://example.com/path'], get_urls('Go to https://example.com/path.'));

        $offset = 0;
        self::assertSame(0x1F642, ordutf8('🙂', $offset));
        self::assertSame(-1, $offset);

        $config['allowed_html'] = 'p,strong';
        self::assertSame(
            '<p><strong>safe</strong></p>',
            purify_html('<p><strong>safe</strong><script>bad()</script></p>'),
        );
    }

    public function testSlugsUseSubjectBodyAndHtmlFallbacks(): void
    {
        global $config;

        $config['slug_max_size'] = 12;
        self::assertContains(
            slugify(['subject' => ' Héllo, world! ']),
            ['hello-world', 'h-llo-world'],
        );
        self::assertSame('body-fallbac', slugify(['body_nomarkup' => 'Body fallback']));
        self::assertSame('html-fallbac', slugify(['body' => '<b>HTML fallback</b>']));
        self::assertSame('', slugify([]));
    }

    public function testTripcodesFractionsAndPosterIdsAreStable(): void
    {
        global $config;

        self::assertSame(['Anonymous'], generate_tripcode('Anonymous'));
        self::assertCount(2, generate_tripcode('Name#secret'));
        self::assertCount(2, generate_tripcode('Name##secure'));

        $config['custom_tripcode']['#custom'] = '!CUSTOM';
        $config['custom_tripcode']['##secure-custom'] = '!!CUSTOM';
        self::assertSame(['Name', '!CUSTOM'], generate_tripcode('Name#custom'));
        self::assertSame(['Name', '!!CUSTOM'], generate_tripcode('Name##secure-custom'));

        self::assertEquals(6, hcf(54, 24));
        self::assertEquals(6, hcf(24, 54));
        self::assertSame('2/3', fraction(8, 12, '/'));
        self::assertSame(
            poster_id('192.0.2.1', 42),
            poster_id('192.0.2.1', 42),
        );
    }

    public function testBase32MasksAndIpHashesRoundTrip(): void
    {
        global $config;

        $encoded = base32_encode('devichan');
        self::assertSame('devichan', base32_decode($encoded));

        $config['ipcrypt_key'] = '';
        self::assertSame('192.0.2.5', cloak_ip('192.0.2.5'));
        self::assertSame('192.0.2.5', uncloak_ip('192.0.2.5'));
        self::assertSame('192.0.2.5/24', cloak_mask('192.0.2.5/24'));
        self::assertSame('192.0.2.5/24', uncloak_mask('192.0.2.5/24'));

        $config['bcrypt_ip_addresses'] = false;
        self::assertSame('192.0.2.5', get_ip_hash('192.0.2.5'));
        $config['bcrypt_ip_addresses'] = true;
        $config['bcrypt_ip_cost'] = '10';
        $config['bcrypt_ip_salt'] = 'abcdefghijklmnopqrstuv';
        self::assertSame(get_ip_hash('192.0.2.6'), get_ip_hash('192.0.2.6'));
    }

    public function testGenerationStrategiesReturnTheirDocumentedActions(): void
    {
        self::assertSame(['immediate'], strategy_immediate('anything', []));
        self::assertSame(['build_on_load'], strategy_smart_build('anything', []));
        self::assertSame(['defer'], strategy_first('sb_thread', [null, 1]));
        self::assertSame(['defer'], strategy_first('sb_board', [null, 8]));
        self::assertSame(['build_on_load'], strategy_first('sb_board', [null, 9]));
        self::assertSame(['defer'], strategy_first('sb_api', []));
        self::assertSame(['defer'], strategy_first('sb_catalog', []));
        self::assertSame(['build_on_load'], strategy_first('sb_recent', []));
        self::assertSame(['build_on_load'], strategy_first('sb_sitemap', []));
        self::assertSame(['defer'], strategy_first('sb_ukko', []));
        self::assertNull(strategy_first('unknown', []));
        self::assertFalse(strategy_sane('sb_thread', []));
    }
}
