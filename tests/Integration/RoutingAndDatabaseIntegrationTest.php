<?php

declare(strict_types=1);

namespace DevichanE2E\Integration;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RoutingAndDatabaseIntegrationTest extends TestCase
{
    private array $originalConfig;
    private mixed $originalBoard;
    private array $originalServer;
    private string $directory = 'tests/_output/integration-routing/';

    protected function setUp(): void
    {
        global $config, $board;

        $this->originalConfig = $config;
        $this->originalBoard = $board ?? null;
        $this->originalServer = $_SERVER;
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
        $_SERVER = $this->originalServer;
        openBoard('b');
    }

    #[DataProvider('rootRequests')]
    public function testDefaultRootMatchesTheInstallation(
        ?string $request,
        ?string $script,
        ?string $scriptName,
        string $expected,
        string $query = '',
    ): void {
        global $config;

        unset($_SERVER['REQUEST_URI'], $_SERVER['SCRIPT_FILENAME'], $_SERVER['SCRIPT_NAME']);
        if ($request !== null) {
            $_SERVER['REQUEST_URI'] = $request;
        }
        if ($script !== null) {
            $_SERVER['SCRIPT_FILENAME'] = getcwd() . '/' . $script;
        }
        if ($scriptName !== null) {
            $_SERVER['SCRIPT_NAME'] = $scriptName;
        }
        $_SERVER['QUERY_STRING'] = $query;

        require 'inc/config.php';

        self::assertSame($expected, $config['root']);
    }

    public static function rootRequests(): array
    {
        return [
            'root CAPTCHA' => ['/inc/captcha/entrypoint.php', 'inc/captcha/entrypoint.php', '/inc/captcha/entrypoint.php', '/'],
            'subdirectory CAPTCHA with query' => ['/chan/inc/captcha/entrypoint.php?mode=get&extra=abc', 'inc/captcha/entrypoint.php', '/chan/inc/captcha/entrypoint.php', '/chan/', 'mode=get&extra=abc'],
            'root script' => ['/chan/securimage.php?mode=get', 'securimage.php', '/chan/securimage.php', '/chan/', 'mode=get'],
            'rewritten script name' => ['/chan/search/', 'search.php', '/chan/search.php', '/chan/'],
            'rewritten fallback' => ['/chan/archive/search?q=test', 'search.php', '/chan/search/', '/chan/archive/', 'q=test'],
            'missing CGI paths' => ['/chan/mod.php', null, null, '/chan/'],
            'missing script file' => ['/chan/mod.php', 'does-not-exist.php', '/elsewhere/does-not-exist.php', '/chan/'],
            'ambiguous suffix' => ['/chan/mod.php', 'inc/captcha/entrypoint.php', '/chan/xinc/captcha/entrypoint.php', '/chan/'],
            'CLI' => [null, 'securimage.php', '/chan/securimage.php', '/'],
        ];
    }

    public function testCaptchaFirstRequestKeepsTheCachedSiteRoot(): void
    {
        global $config, $board;

        $root = getcwd();
        foreach (['/' => null, '/chan/' => null, '/custom/' => 'https://boards.example/custom/'] as $base => $override) {
            $directory = $root . '/' . $this->directory . 'config-' . bin2hex(random_bytes(4));
            mkdir($directory . '/inc', 0777, true);
            mkdir($directory . '/tmp/cache', 0777, true);
            symlink($root . '/inc/config.php', $directory . '/inc/config.php');
            symlink($root . '/inc/cache.php', $directory . '/inc/cache.php');
            file_put_contents($directory . '/inc/instance-config.php', "<?php require 'inc/secrets.php';\n");
            $settings = [
                'cache' => ['enabled' => 'php', 'prefix' => 'e2e-root-'],
                'cache_config' => true,
                'minify_js' => false,
                'allowed_ext_files' => [],
                'locale' => 'en',
            ];
            if ($override !== null) {
                $settings['root'] = $override;
            }
            file_put_contents($directory . '/inc/secrets.php', '<?php $config = array_replace_recursive($config, ' . var_export($settings, true) . ');');

            try {
                $board = null;
                $config['cache']['enabled'] = 'php';
                \Cache::init();
                chdir($directory);
                $_SERVER['SCRIPT_FILENAME'] = $root . '/inc/captcha/entrypoint.php';
                $_SERVER['SCRIPT_NAME'] = $base . 'inc/captcha/entrypoint.php';
                $_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'] . '?mode=get&extra=abc';
                $_SERVER['QUERY_STRING'] = 'mode=get&extra=abc';

                \loadConfig();

                $expected = $override ?? $base;
                self::assertSame($expected, $config['root']);
                self::assertSame($expected, \Cache::get('config_')['root']);
                self::assertFileExists('tmp/cache/cache_config.php');

                $_SERVER['SCRIPT_FILENAME'] = $root . '/mod.php';
                $_SERVER['SCRIPT_NAME'] = $base . 'mod.php';
                $_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
                $_SERVER['QUERY_STRING'] = '';
                \loadConfig();

                self::assertSame($expected, $config['root']);
                self::assertSame($expected . 'post.php', $config['post_url']);
            } finally {
                chdir($root);
                \rrmdir($directory);
            }
        }
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
