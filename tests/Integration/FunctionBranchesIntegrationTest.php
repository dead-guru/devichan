<?php

declare(strict_types=1);

namespace DevichanE2E\Integration;

use PHPUnit\Framework\TestCase;

final class FunctionBranchesIntegrationTest extends TestCase
{
    private array $originalConfig;
    private mixed $originalBoard;
    private mixed $originalMod;
    private array $originalServer;
    private string $directory = 'tests/_output/integration-functions';

    protected function setUp(): void
    {
        global $config, $board, $mod;

        $this->originalConfig = $config;
        $this->originalBoard = $board ?? null;
        $this->originalMod = $mod;
        $this->originalServer = $_SERVER;
        reset_events();
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0777, true);
        }
        self::assertTrue(openBoard('b'));
    }

    protected function tearDown(): void
    {
        global $config, $board, $mod;

        $config = $this->originalConfig;
        $board = $this->originalBoard;
        $mod = $this->originalMod;
        $_SERVER = $this->originalServer;
        reset_events();
        openBoard('b');
    }

    public function testThemeBoardAndPermissionCachesExerciseFastPaths(): void
    {
        global $config, $mod;

        self::assertFalse(loadThemeConfig('does-not-exist'));
        self::assertSame('Catalog', loadThemeConfig('catalog')['name']);

        $config['cache']['enabled'] = 'php';
        $config['cache']['prefix'] = 'e2e-branches-';
        \Cache::init();
        \Cache::set('theme_settings_cached-theme', ['value' => 'cached']);
        self::assertSame(['value' => 'cached'], themeSettings('cached-theme'));
        \Cache::set('all_boards_uri', ['cached-board']);
        self::assertSame(['cached-board'], listBoards(true));
        \Cache::set('board_cached', ['uri' => 'cached', 'title' => 'Cached']);
        self::assertSame('Cached', getBoardInfo('cached')['title']);

        $mod = ['type' => 20, 'boards' => ['b']];
        self::assertTrue(hasPermission(20, 'b', $mod));
        self::assertFalse(hasPermission(30, 'b', $mod));
    }

    public function testPurgeFilesystemEventsAndRecursiveRemovalStayInsideTestOutput(): void
    {
        global $config, $debug;

        $config['debug'] = true;
        $config['gzip_static'] = true;
        $config['purge'] = [];
        $config['referer_match'] = '~^/$~';
        $config['root'] = '/';
        $debug = ['purge' => [], 'write' => [], 'unlink' => []];
        $_SERVER['REQUEST_URI'] = '/b/res/1.html';

        $nested = $this->directory . '/nested/child';
        if (!is_dir($nested)) {
            mkdir($nested, 0777, true);
        }
        $path = $nested . '/index.html';
        file_write($path, str_repeat('large-output-', 100), false, false);
        self::assertFileExists($path . '.gz');
        self::assertNotEmpty($debug['purge']);
        self::assertNotEmpty($debug['write']);
        self::assertTrue(file_unlink($path));
        self::assertFileDoesNotExist($path . '.gz');

        file_put_contents($nested . '/one.txt', 'one');
        mkdir($nested . '/deeper');
        file_put_contents($nested . '/deeper/two.txt', 'two');
        rrmdir($this->directory . '/nested');
        self::assertDirectoryDoesNotExist($this->directory . '/nested');
    }

    public function testBanThreadAndPosterEventsShortCircuitDatabaseWork(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        self::assertNull(checkBan('b'));
        $_SERVER['REMOTE_ADDR'] = '127.0.0.31';

        event_handler('check-ban', static fn(): bool => true);
        self::assertTrue(checkBan('b'));
        reset_events();

        event_handler('check-locked', static fn(): bool => true);
        event_handler('check-sage-locked', static fn(): bool => true);
        event_handler('bump', static fn(): bool => true);
        event_handler('poster-id', static fn(): string => 'EVENT-ID');
        event_handler('tripcode', static fn(): array => ['Event', '!EVENT']);
        event_handler('check-robot', static fn(): bool => true);

        self::assertTrue(threadLocked(999999));
        self::assertTrue(threadSageLocked(999999));
        self::assertTrue(threadExists(1));
        self::assertTrue(bumpThread(1));
        self::assertSame('EVENT-ID', poster_id('192.0.2.1', 1));
        self::assertSame(['Event', '!EVENT'], generate_tripcode('ignored'));
        self::assertTrue(checkRobot('event'));
    }

    public function testPaginationDnsShellAndFlagUtilitiesCoverAlternateBranches(): void
    {
        global $config, $board, $debug;

        $pages = [
            ['num' => 1],
            ['num' => 2, 'selected' => true],
            ['num' => 3],
        ];
        $buttons = getPageButtons($pages, true);
        self::assertStringContainsString('status', $buttons['prev']);
        self::assertStringContainsString('status', $buttons['next']);

        $board['thread_count'] = 0;
        self::assertCount(1, getPages(false));
        unset($board['thread_count']);

        $_SERVER['REMOTE_ADDR'] = '2001:db8::1';
        self::assertTrue(isIPv6());
        checkDNSBL();
        unset($_SERVER['REMOTE_ADDR']);
        checkDNSBL();
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        checkDNSBL();
        self::assertSame('4.3.2.1', ReverseIPOctets('1.2.3.4'));

        $config['dns_system'] = false;
        $config['fcrdns'] = false;
        $config['cache']['enabled'] = false;
        self::assertNotFalse(DNS('localhost'));
        self::assertFalse(DNS('invalid.invalid'));
        self::assertNotSame('', rDNS('127.0.0.1'));

        $config['debug'] = true;
        $debug = ['exec' => [], 'time' => []];
        self::assertSame('', shell_exec_error('true'));
        self::assertSame('', shell_exec_error('printf hidden', true));
        self::assertStringContainsString('failure', shell_exec_error('sh -c "echo failure; exit 1"'));
        self::assertCount(3, $debug['exec']);
        self::assertArrayHasKey('exec', $debug['time']);

        $config['deprecation_errors'] = false;
        self::assertSame(ENT_QUOTES | ENT_SUBSTITUTE, defined_flags_accumulate([
            'ENT_QUOTES',
            'ENT_SUBSTITUTE',
            'NOT_A_REAL_FLAG',
        ]));
    }

    public function testHashesMuteImageCleanupAndEncodingCoverDatabaseAndFilesystemBranches(): void
    {
        global $config;

        query("UPDATE ``posts_b`` SET `filehash` = 'integration-hash' WHERE `id` = 1");
        self::assertSame(1, (int) getPostByHash('integration-hash')['id']);
        self::assertSame(1, (int) getPostByHashInThread('integration-hash', 1)['id']);
        self::assertFalse(getPostByHash('missing-hash'));
        self::assertFalse(getPostByHashInThread('missing-hash', 1));

        $source = $this->directory . '/source.tmp';
        $thumb = $this->directory . '/thumb.tmp';
        file_put_contents($source, 'source');
        file_put_contents($thumb, 'thumb');
        undoImage(['has_file' => true, 'files' => [[
            'file_path' => $source,
            'thumb_path' => $thumb,
        ]]]);
        self::assertFileDoesNotExist($source);
        self::assertFileDoesNotExist($thumb);
        undoImage(['has_file' => false]);

        $_SERVER['REMOTE_ADDR'] = '127.0.0.222';
        query("DELETE FROM ``mutes`` WHERE `ip` = '127.0.0.222'");
        self::assertSame(0, muteTime());
        self::assertGreaterThan(0, mute());

        $offset = 0;
        self::assertSame(65, ordutf8('A', $offset));
        $offset = 0;
        self::assertSame(0x00A2, ordutf8('¢', $offset));
        $offset = 0;
        self::assertSame(0x20AC, ordutf8('€', $offset));

        $config['ipcrypt_key'] = 'integration-key';
        $config['ipcrypt_prefix'] = 'e2e';
        self::assertSame('#ERROR', cloak_ip('not-an-ip'));
        self::assertSame('#ERROR', uncloak_ip('not-cloaked'));
        self::assertSame('not-cloaked/24', uncloak_mask('not-cloaked/24'));
        self::assertSame('#ERROR', cloak_mask('not-an-ip'));
    }
}
