<?php

declare(strict_types=1);

namespace DevichanE2E\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

final class ModeratorFunctionIntegrationTest extends TestCase
{
    private array $originalConfig;
    private mixed $originalMod;
    private mixed $originalBoard;
    private array $originalPost;

    protected function setUp(): void
    {
        global $config, $mod, $board;

        $this->originalConfig = $config;
        $this->originalMod = $mod;
        $this->originalBoard = $board ?? null;
        $this->originalPost = $_POST;
        $mod = [
            'id' => 1,
            'type' => 30,
            'username' => 'admin',
            'boards' => ['*'],
        ];
        $_POST = [];
        self::assertTrue(openBoard('b'));
        $config['mod']['debug_sql'] = 30;
        require_once 'inc/mod/pages.php';
    }

    protected function tearDown(): void
    {
        global $config, $mod, $board;

        $config = $this->originalConfig;
        $mod = $this->originalMod;
        $board = $this->originalBoard;
        $_POST = $this->originalPost;
        openBoard('b');
    }

    public function testSearchRedirectAndExactWildcardQueriesRenderAllResultKinds(): void
    {
        $_POST = ['query' => '', 'type' => 'posts'];
        \mod_search_redirect();
        $_POST = ['query' => 'Seed public*', 'type' => 'posts'];
        \mod_search_redirect();
        $_POST = ['query' => 'ignored', 'type' => 'invalid'];
        \mod_search_redirect();

        $_POST = [];
        $posts = $this->capture(static fn() => \mod_search('posts', '"Seed"_public*'));
        self::assertStringContainsString('Search results', $posts);
        self::assertStringContainsString('Seed', $posts);

        foreach (['IP_notes', 'bans', 'log'] as $type) {
            $page = $this->capture(static fn() => \mod_search($type, 'Seed'));
            self::assertStringContainsString('Search results', $page);
        }
    }

    public function testDashboardLogsAndReadViewsRenderThroughDirectControllerBoundary(): void
    {
        global $config;

        $config['cache']['enabled'] = false;
        self::assertStringContainsString('Dashboard', $this->capture(static fn() => \mod_dashboard()));
        self::assertNotSame('', $this->capture(static fn() => \mod_log(1)));
        self::assertNotSame('', $this->capture(static fn() => \mod_user_log('admin', 1)));
        self::assertNotSame('', $this->capture(static fn() => \mod_board_log('b', 1, true, true)));
        self::assertNotSame('', $this->capture(static fn() => \mod_view_catalog('b')));
        self::assertNotSame('', $this->capture(static fn() => \mod_view_board('b', 1)));
        self::assertNotSame('', $this->capture(static fn() => \mod_view_thread('b', 1)));
        self::assertNotSame('', $this->capture(static fn() => \mod_view_thread50('b', 1)));
        self::assertNotSame('', $this->capture(static fn() => \mod_recent_posts(5)));
    }

    public function testDebugPagesExerciseAntispamRecentPostsAndSqlResultShapes(): void
    {
        global $pdo;

        $_POST = ['board' => 'b', 'thread' => '1'];
        $antispam = $this->capture(static fn() => \mod_debug_antispam());
        self::assertStringContainsString('Anti-spam', $antispam);

        $_POST = [];
        $recent = $this->capture(static fn() => \mod_debug_recent_posts());
        self::assertStringContainsString('Recent posts', $recent);

        $_POST = ['query' => 'SELECT `uri`, `title` FROM `boards` ORDER BY `uri`'];
        $sql = $this->capture(static fn() => \mod_debug_sql());
        self::assertStringContainsString('Random', $sql);

        $_POST = ['query' => 'SELECT `uri` FROM `boards` WHERE 1 = 0'];
        $empty = $this->capture(static fn() => \mod_debug_sql());
        self::assertStringContainsString('no result', $empty);

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $_POST = ['query' => 'SELECT * FROM table_that_does_not_exist'];
        $invalid = $this->capture(static fn() => \mod_debug_sql());
        self::assertStringContainsString('doesn', $invalid);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    private function capture(callable $action): string
    {
        ob_start();
        try {
            $action();
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
