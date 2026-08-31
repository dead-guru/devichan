<?php

declare(strict_types=1);

namespace DevichanE2E\Integration;

use PHPUnit\Framework\TestCase;

final class DisplayIntegrationTest extends TestCase
{
    private array $originalConfig;
    private mixed $originalMod;

    protected function setUp(): void
    {
        global $config, $mod;

        $this->originalConfig = $config;
        $this->originalMod = $mod;
        self::assertTrue(openBoard('b'));
    }

    protected function tearDown(): void
    {
        global $config, $mod;

        $config = $this->originalConfig;
        $mod = $this->originalMod;
        openBoard('b');
    }

    public function testByteAndBoardListFormattingCoversNestedAndCustomLinks(): void
    {
        global $config;

        self::assertSame('1 B', format_bytes(1));
        self::assertSame('1 KB', format_bytes(1024));
        self::assertSame('1 MB', format_bytes(1024 ** 2));
        self::assertSame('1 GB', format_bytes(1024 ** 3));
        self::assertSame('1 TB', format_bytes(1024 ** 4));

        unset($config['boards']);
        self::assertSame(['top' => '', 'bottom' => ''], createBoardlist());

        $config['boards'] = ['b', 'More' => ['sec', 'Home' => '/']];
        $config['boardlist_wrap_bracket'] = true;
        $public = createBoardlist(false);
        self::assertStringContainsString('title="Random"', $public['top']);
        self::assertStringContainsString('data-description="More"', $public['top']);
        self::assertStringContainsString('href="/"', $public['bottom']);

        $moderator = createBoardlist(true);
        self::assertStringContainsString('href="?/b/', $moderator['top']);
    }

    public function testSnippetsCapcodesAndTruncationCoverEveryOutputShape(): void
    {
        global $config;

        self::assertSame('<em>short</em>', pm_snippet('<b>short</b>', 20));
        self::assertSame('<em>long&hellip;</em>', pm_snippet('long message', 4));

        self::assertFalse(capcode(false));
        $config['custom_capcode']['ArrayCap'] = ['[%s]', 'Staff', '!trip'];
        self::assertSame(
            ['cap' => '[ArrayCap]', 'name' => 'Staff', 'trip' => '!trip'],
            capcode('ArrayCap'),
        );
        $config['custom_capcode']['StringCap'] = '<b>%s</b>';
        self::assertSame(['cap' => '<b>StringCap</b>'], capcode('StringCap'));
        self::assertArrayHasKey('cap', capcode('UnknownCap'));

        $short = '<strong>short</strong>';
        self::assertSame($short, truncate($short, '/full', 10, 100));
        $long = '<strong>one<br/>two<br/>three</strong>';
        $truncated = truncate($long, '/full', 1, 12);
        self::assertStringContainsString('</strong>', $truncated);
        self::assertStringContainsString('Post too long', $truncated);
        self::assertStringNotContainsString('<!--', truncate('<!-- hidden -->abcdef', '/full', 10, 3));
    }

    public function testBidiCleanupSecureLinksAndEmbedsPreserveBoundaries(): void
    {
        global $mod;

        self::assertSame('plain', bidi_cleanup('plain'));
        self::assertSame("a\xE2\x80\xAAb\xE2\x80\xAC", bidi_cleanup("a\xE2\x80\xAAb"));
        self::assertSame('ab', bidi_cleanup("a\xE2\x80\xACb"));

        $mod = ['id' => 1, 'type' => 30, 'boards' => ['*']];
        self::assertMatchesRegularExpression('~/[a-f0-9]{8}$~', secure_link('b/delete/1'));
        self::assertStringContainsString('document.location', secure_link_confirm(
            'Delete',
            'Delete post',
            'Are you sure?',
            'b/delete/1',
        ));

        self::assertStringContainsString('<iframe', embed_html(
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ));
        self::assertSame('<video>legacy</video>', embed_html('<video>legacy</video>'));
        self::assertSame('Embedding error.', embed_html('https://invalid.example/video'));
    }

    public function testPostAndThreadObjectsRenderLinksAndTemplates(): void
    {
        global $config, $mod;

        $threadRow = query('SELECT * FROM ``posts_b`` WHERE `id` = 1')->fetch(\PDO::FETCH_ASSOC);
        $replyRow = query('SELECT * FROM ``posts_b`` WHERE `id` = 2')->fetch(\PDO::FETCH_ASSOC);
        self::assertIsArray($threadRow);
        self::assertIsArray($replyRow);

        $config['always_regenerate_markup'] = true;
        $thread = new \Thread($threadRow, '/', false, false);
        $reply = new \Post($replyRow, '/', false);
        $thread->add($reply);

        $embeddedRow = $replyRow;
        $embeddedRow['embed'] = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
        $embeddedReply = new \Post($embeddedRow);
        self::assertStringContainsString('<iframe', $embeddedReply->embed);

        self::assertSame(1, $thread->postCount());
        self::assertStringContainsString('/b/res/', $thread->link());
        self::assertStringContainsString('#2', $reply->link());
        self::assertNotSame('', $reply->build(false));
        self::assertNotSame('', $thread->build(false));
        self::assertNotSame('', $thread->build(true, true));

        $config['always_regenerate_markup'] = false;
        $mod = ['id' => 1, 'type' => 30, 'username' => 'admin', 'boards' => ['*']];
        $moderatorThread = new \Thread($threadRow, '?/', true);
        self::assertNotSame('', $moderatorThread->build(true));
    }

    public function testDebugTemplateIncludesRuntimeDiagnosticsAndBuildQueue(): void
    {
        global $config, $debug, $build_pages;

        $originalDebug = $debug ?? null;
        $originalBuildPages = $build_pages ?? null;

        try {
            $config['debug'] = true;
            $config['try_smarter'] = true;
            $debug = [
                'start' => microtime(true) - 0.1,
                'start_debug' => microtime(true) - 0.05,
                'time' => ['db_queries' => 0.001, 'exec' => 0.002],
            ];
            $build_pages = [['build', 'b', 1]];

            $rendered = Element('page.html', [
                'config' => $config,
                'title' => 'Integration debug page',
                'body' => '<p>Integration body</p>',
            ]);

            self::assertStringContainsString('Integration debug page', $rendered);
            self::assertStringContainsString('<h3>Debug</h3>', $rendered);
            self::assertStringContainsString('build_pages', $rendered);
        } finally {
            $debug = $originalDebug;
            $build_pages = $originalBuildPages;
        }
    }

    public function testModeratorDashboardUsesBannerHeightWithoutAWidth(): void
    {
        global $config, $mod;

        $mod = [
            'id' => 1,
            'type' => 30,
            'username' => 'admin',
            'boards' => ['*'],
        ];
        $config['url_banner'] = '/banner/test.png';
        $config['banner_width'] = 0;
        $config['banner_height'] = 120;

        $dashboard = Element('mod/dashboard.html', [
            'config' => $config,
            'mod' => $mod,
            'boards' => [],
            'noticeboard' => [],
            'unread_pms' => 0,
            'reports' => 0,
            'logout_token' => 'test-token',
        ]);

        self::assertStringContainsString('height:120px', $dashboard);
        self::assertStringNotContainsString('width:0px', $dashboard);
    }
}
