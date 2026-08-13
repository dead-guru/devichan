<?php

declare(strict_types=1);

namespace DevichanE2E\Integration;

use PHPUnit\Framework\TestCase;

final class ThemeIntegrationTest extends TestCase
{
    private array $originalConfig;
    private mixed $originalBoard;
    private string $outputDirectory = 'tests/_output/integration-themes/';

    protected function setUp(): void
    {
        global $config, $board;

        $this->originalConfig = $config;
        $this->originalBoard = $board ?? null;
        if (!is_dir($this->outputDirectory)) {
            mkdir($this->outputDirectory, 0777, true);
        }
        $config['dir']['home'] = $this->outputDirectory;
        $config['referer_match'] = '~^$~';
        $config['minify_css'] = false;
        $config['categories'] = [
            'Public' => ['b', 'missing'],
            'Private' => ['sec'],
        ];
        self::assertTrue(openBoard('b'));
    }

    protected function tearDown(): void
    {
        global $config, $board;

        $config = $this->originalConfig;
        $board = $this->originalBoard;
        openBoard('b');
    }

    public function testBasicCategoriesAndFramesetBuildRealPages(): void
    {
        global $config;

        require_once 'templates/themes/basic/theme.php';
        require_once 'templates/themes/categories/theme.php';
        require_once 'templates/themes/frameset/theme.php';

        $basic = ['file' => 'basic.html', 'no_recent' => '1'];
        \basic_build('all', $basic, false);
        self::assertStringContainsString('Seed news', \Basic::homepage($basic));
        self::assertFileExists($this->outputDirectory . 'basic.html');

        $categories = [
            'file_main' => 'categories.html',
            'file_sidebar' => 'categories-sidebar.html',
            'file_news' => 'categories-news.html',
        ];
        \categories_build('all', $categories, false);
        self::assertStringContainsString('Seed news', \Categories::news($categories));
        self::assertStringContainsString('missing', \Categories::sidebar($categories));
        self::assertFileExists($this->outputDirectory . 'categories.html');
        self::assertFileExists($this->outputDirectory . 'categories-sidebar.html');
        self::assertFileExists($this->outputDirectory . 'categories-news.html');

        $frameset = [
            'file_main' => 'frames.html',
            'file_sidebar' => 'frames-sidebar.html',
            'file_news' => 'frames-news.html',
        ];
        \frameset_build('all', $frameset, false);
        self::assertStringContainsString('Seed news', \Frameset::news($frameset));
        self::assertNotSame('', \Frameset::sidebar($frameset));
        self::assertFileExists($this->outputDirectory . 'frames.html');
        self::assertFileExists($this->outputDirectory . 'frames-sidebar.html');
        self::assertFileExists($this->outputDirectory . 'frames-news.html');

        $config['dir']['home'] = $this->outputDirectory;
    }

    public function testRssAndBanlistRenderDatabaseContent(): void
    {
        require_once 'templates/themes/rss/theme.php';
        require_once 'templates/themes/public_banlist/theme.php';

        $rss = [
            'title' => 'Integration RSS',
            'exclude' => 'sec',
            'limit_posts' => '10',
            'xml' => 'recent.xml',
            'base_url' => 'http://caddy',
        ];
        \rss_recentposts_build('all', $rss, false);
        $rssTheme = new \RSSRecentPosts();
        $rssTheme->build('boards', $rss);
        $rssPage = $rssTheme->homepage($rss);
        self::assertStringContainsString('<item>', $rssPage);
        self::assertFileExists($this->outputDirectory . 'recent.xml');

        $banlist = ['file_bans' => 'bans.html', 'file_json' => 'bans.json'];
        \pbanlist_build('all', $banlist, false);
        self::assertJson(\PBanlist::gen_json($banlist));
        self::assertStringContainsString('Ban list', \PBanlist::homepage($banlist));
        self::assertFileExists($this->outputDirectory . 'bans.html');
        self::assertFileExists($this->outputDirectory . 'bans.json');
    }

    public function testUkkoRendersDatabaseContent(): void
    {
        global $config, $pdo;

        require_once 'templates/themes/ukko/theme.php';

        $ukkoSettings = [
            'uri' => $this->outputDirectory . 'ukko',
            'title' => 'All boards',
            'subtitle' => '%s newest threads',
            'thread_limit' => '1',
            'exclude' => 'sec',
        ];
        $files = json_encode([['file' => 'reply.png']], JSON_THROW_ON_ERROR);
        query("UPDATE ``posts_b`` SET `files` = " . $pdo->quote($files) . ", `num_files` = 1 WHERE `id` = 2");

        try {
            \ukko_install($ukkoSettings);
            $config['threads_preview'] = 1;
            $config['generation_strategies'] = ['strategy_immediate'];
            \ukko_build('all', $ukkoSettings);
            self::assertFileExists($ukkoSettings['uri'] . '/index.html');

            $config['generation_strategies'] = ['strategy_smart_build'];
            \ukko_build('all', $ukkoSettings);
            self::assertFileDoesNotExist($ukkoSettings['uri'] . '/index.html');

            $ukko = new \ukko();
            $ukko->settings = $ukkoSettings;
            $page = $ukko->build(false);
            self::assertStringContainsString('<div class="thread"', $page);
            self::assertStringContainsString('overflow', $page);
            self::assertFileExists($ukkoSettings['uri'] . '/ukko.js');
        } finally {
            query("UPDATE ``posts_b`` SET `files` = NULL, `num_files` = 0 WHERE `id` = 2");
        }
    }

    public function testCatalogBuildStrategyCreatesAndDeletesGeneratedPages(): void
    {
        global $config;

        require_once 'templates/themes/catalog/theme.php';
        $directory = $this->outputDirectory . 'b';
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        $settings = [
            'title' => 'Catalog',
            'boards' => 'b',
            'update_on_posts' => '1',
            'use_tooltipster' => '1',
        ];

        $config['generation_strategies'] = ['strategy_immediate'];
        \catalog_build('all', $settings, 'b');
        self::assertFileExists($directory . '/catalog.html');
        self::assertFileExists($directory . '/index.rss');

        $config['generation_strategies'] = ['strategy_smart_build'];
        \catalog_build('all', $settings, 'b');
        self::assertFileDoesNotExist($directory . '/catalog.html');
        self::assertFileDoesNotExist($directory . '/index.rss');
    }

    public function testCatalogUsesTheNextAvailableThumbnailAfterADeletedFile(): void
    {
        global $pdo;

        require_once 'templates/themes/catalog/theme.php';
        $files = json_encode([
            ['file' => 'deleted', 'thumb' => 'deleted'],
            ['file' => 'second.png', 'thumb' => 'second-thumb.png'],
        ], JSON_THROW_ON_ERROR);
        query("UPDATE ``posts_b`` SET `files` = " . $pdo->quote($files) . ", `num_files` = 2 WHERE `id` = 1");

        try {
            $catalog = new \Catalog();
            $page = $catalog->build([
                'title' => 'Catalog',
                'boards' => 'b',
                'update_on_posts' => '1',
                'use_tooltipster' => '1',
            ], 'b', true);
            self::assertStringContainsString('second-thumb.png', $page);
        } finally {
            query("UPDATE ``posts_b`` SET `files` = NULL, `num_files` = 0 WHERE `id` = 1");
        }
    }
}
