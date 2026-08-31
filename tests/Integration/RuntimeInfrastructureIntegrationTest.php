<?php

declare(strict_types=1);

namespace DevichanE2E\Integration;

use PHPUnit\Framework\TestCase;

final class RuntimeInfrastructureIntegrationTest extends TestCase
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

    public function testPermissionsCoverAnonymousGlobalAndPerBoardModerators(): void
    {
        global $config, $mod;

        $mod = false;
        self::assertFalse(hasPermission());

        $mod = ['type' => 10, 'boards' => ['b']];
        self::assertFalse(hasPermission(20, 'b'));
        self::assertTrue(hasPermission(10));
        self::assertTrue(hasPermission(10, 'b'));
        self::assertFalse(hasPermission(10, 'sec'));

        $mod = ['type' => 30, 'boards' => ['*']];
        self::assertTrue(hasPermission(20, 'sec'));

        unset($mod['boards']);
        self::assertFalse(hasPermission(20, 'b'));

        $config['mod']['skip_per_board'] = true;
        self::assertTrue(hasPermission(20, 'missing'));
    }

    public function testPhpFilesystemAndRedisCacheBackendsRoundTrip(): void
    {
        global $config, $debug;

        $debug = ['cached' => []];
        $config['debug'] = true;
        $config['cache']['prefix'] = 'e2e-integration-';
        $config['cache']['timeout'] = 60;

        foreach (['php', 'fs'] as $backend) {
            $config['cache']['enabled'] = $backend;
            \Cache::init();
            self::assertFalse(\Cache::get('missing-' . $backend));
            \Cache::set('key-' . $backend, ['backend' => $backend]);
            self::assertSame(['backend' => $backend], \Cache::get('key-' . $backend));
            \Cache::delete('key-' . $backend);
            self::assertFalse(\Cache::get('key-' . $backend));
            self::assertFalse(\Cache::flush());
        }

        $config['cache']['enabled'] = 'redis';
        $config['cache']['redis'] = ['credis', 6379, 'devichan_e2e', 1];
        \Cache::init();
        \Cache::set('redis-key', ['backend' => 'redis'], 60);
        self::assertSame(['backend' => 'redis'], \Cache::get('redis-key'));
        \Cache::delete('redis-key');
        self::assertNull(\Cache::get('redis-key'));
        self::assertTrue(\Cache::flush());

        self::assertNotEmpty($debug['cached']);
    }

    public function testFilesystemLocksAndQueuesSerializeWork(): void
    {
        global $config, $queues;

        $config['lock']['enabled'] = 'fs';
        $config['queue']['enabled'] = 'fs';
        $queues = [];
        $queueDirectory = 'tmp/queue/e2e-integration';
        if (!is_dir($queueDirectory)) {
            mkdir($queueDirectory, 0777, true);
        }

        $lock = new \Lock('e2e/integration');
        self::assertSame($lock, $lock->get());
        self::assertSame($lock, $lock->free());
        self::assertSame($lock, $lock->get_ex(true));
        self::assertSame($lock, $lock->free());

        $queue = get_queue('e2e-integration');
        self::assertSame($queue, get_queue('e2e-integration'));
        self::assertSame($queue, $queue->push('first'));
        usleep(1000);
        self::assertSame($queue, $queue->push('second'));
        $queued = $queue->pop(2);
        sort($queued);
        self::assertSame(['first', 'second'], $queued);
        self::assertSame([], $queue->pop());
        rmdir($queueDirectory);
    }

    public function testFilesystemWritesGzipDebugsAndDeletesGeneratedData(): void
    {
        global $config, $debug;

        $directory = 'tests/_output/integration-files';
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        $path = $directory . '/runtime.txt';

        $debug = ['write' => [], 'unlink' => []];
        $config['debug'] = true;
        $config['gzip_static'] = true;
        unset($config['purge']);

        file_write($path, str_repeat('coverage ', 200), false, true);
        self::assertFileExists($path);
        self::assertFileExists($path . '.gz');

        file_write($path, 'small', true, true);
        self::assertFileDoesNotExist($path . '.gz');
        self::assertTrue(file_unlink($path));
        self::assertFileDoesNotExist($path);
        self::assertNotEmpty($debug['write']);
        self::assertContains($path, $debug['unlink']);
    }

    public function testBoardQueriesThreadStateAndThemeSettingsUseTheRealDatabase(): void
    {
        global $board;

        $boards = listBoards();
        self::assertContains('b', array_column($boards, 'uri'));
        self::assertContains('sec', listBoards(true));
        self::assertSame('Random', getBoardInfo('b')['title']);
        self::assertFalse(getBoardInfo('missing'));

        self::assertTrue(threadExists(1));
        self::assertFalse(threadExists(999999));
        self::assertFalse(threadLocked(1));
        self::assertFalse(threadLocked(999999));
        self::assertFalse(threadSageLocked(1));
        self::assertFalse(threadSageLocked(999999));
        self::assertGreaterThanOrEqual(1, (int) numPosts(1)['replies']);
        self::assertGreaterThanOrEqual(1.0, thread_find_page(1));
        self::assertFalse(thread_find_page(999999));

        $settings = themeSettings('catalog');
        self::assertSame('b', $settings['boards']);
        self::assertSame('b', $board['uri']);
    }
}
