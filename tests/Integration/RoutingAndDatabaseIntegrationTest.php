<?php

declare(strict_types=1);

namespace DevichanE2E\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

final class RoutingAndDatabaseIntegrationTest extends TestCase
{
    private array $originalConfig;
    private mixed $originalBoard;
    private string $directory = 'tests/_output/integration-routing/';

    protected function setUp(): void
    {
        global $config, $board;

        $this->originalConfig = $config;
        $this->originalBoard = $board ?? null;
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0777, true);
        }
        $config['dir']['home'] = $this->directory;
        $config['board_path'] = $this->directory . '%s/';
        $config['referer_match'] = '~^$~';
        $config['minify_css'] = false;
        $board = null;

        require_once 'inc/controller.php';
        require_once 'inc/route.php';
    }

    protected function tearDown(): void
    {
        global $config, $board;

        $config = $this->originalConfig;
        $board = $this->originalBoard;
        openBoard('b');
    }

    public function testRouteMatchesBoardsApisThreadsThemesAndCustomEndpoints(): void
    {
        global $config;

        self::assertSame(['sb_board', ['b']], \route('/b/'));
        self::assertSame(['sb_board', ['b', '2']], \route('/b/2.html?query=yes'));
        self::assertSame(['sb_api_board', ['b', '0']], \route('/b/0.json'));
        self::assertSame(['sb_api', ['b']], \route('/b/catalog.json'));
        self::assertSame(['sb_thread', ['b', '1']], \route('/b/res/1.json'));
        self::assertSame(['sb_recent', []], \route('/recent.html'));
        self::assertSame(['sb_sitemap', []], \route('/sitemap.xml'));
        self::assertFalse(\route('/not-a-route'));

        $config['controller_entrypoints']['/custom/%s/%d'] = 'custom_handler';
        self::assertSame(['custom_handler', ['42']], \route('/custom/value/42'));
    }

    public function testControllerBuildsBoardsApisThreadsAndConfiguredThemes(): void
    {
        global $config, $board;

        self::assertFalse(\sb_board('b', 0));
        self::assertFalse(\sb_board('missing', 1));
        self::assertFalse(\sb_board('b', $config['max_pages'] + 1));
        self::assertTrue(\sb_board('b', 1));
        self::assertTrue(\sb_api_board('b', 0));

        self::assertFalse(\sb_thread('b', 0));
        self::assertFalse(\sb_thread('bad/board', 1));
        self::assertFalse(\sb_thread('b', 999999));
        self::assertFalse(\sb_thread('b', 2));
        self::assertTrue(\sb_thread('b', 1));
        self::assertTrue(\sb_api('b'));
        self::assertFalse(\sb_api('missing'));

        self::assertTrue(\sb_catalog('b'));
        self::assertFalse(\sb_catalog('missing'));
        self::assertTrue(\sb_recent());
        self::assertTrue(\sb_sitemap());
        self::assertSame('b', $board['uri']);
    }

    public function testDebugDatabaseWrapperBindsExecutesExplainsAndRecordsQueries(): void
    {
        global $config, $debug, $pdo;

        $originalDebug = $debug;
        $config['debug'] = true;
        $config['debug_explain'] = true;
        $debug = ['sql' => [], 'time' => ['db_queries' => 0]];

        $statement = \prepare('SELECT `uri`, `title` FROM ``boards`` WHERE `uri` = :uri');
        self::assertInstanceOf(\PreparedQueryDebug::class, $statement);
        $statement->bindValue(':uri', 'b');
        self::assertTrue($statement->execute());
        self::assertSame('Random', $statement->fetch(PDO::FETCH_ASSOC)['title']);

        $result = \query('SELECT * FROM ``boards`` ORDER BY `uri`');
        self::assertNotFalse($result);
        self::assertGreaterThanOrEqual(2, $result->rowCount());
        self::assertNotEmpty($debug['sql']);
        self::assertGreaterThan(0, $debug['time']['db_queries']);
        self::assertIsInt(\mysql_version());

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $bad = $pdo->query('SELECT * FROM table_that_does_not_exist');
        self::assertFalse($bad);
        self::assertStringContainsString('doesn', (string) \db_error());
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $debug = $originalDebug;
    }
}
