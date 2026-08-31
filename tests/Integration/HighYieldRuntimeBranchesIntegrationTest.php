<?php

declare(strict_types=1);

namespace DevichanE2E\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

final class HighYieldRuntimeBranchesIntegrationTest extends TestCase
{
    private array $originalConfig;
    private mixed $originalBoard;
    private mixed $originalMod;
    private array $originalServer;
    private array $originalCookie;
    private mixed $originalIp;
    private string $outputDirectory = 'tests/_output/high-yield-runtime';

    protected function setUp(): void
    {
        global $config, $board, $mod, $__ip;

        $this->originalConfig = $config;
        $this->originalBoard = $board ?? null;
        $this->originalMod = $mod;
        $this->originalServer = $_SERVER;
        $this->originalCookie = $_COOKIE;
        $this->originalIp = $__ip ?? null;
        self::assertTrue(openBoard('b'));
        if (!is_dir($this->outputDirectory)) {
            mkdir($this->outputDirectory, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        global $config, $board, $mod, $__ip;

        $config = $this->originalConfig;
        $board = $this->originalBoard;
        $mod = $this->originalMod;
        $_SERVER = $this->originalServer;
        $_COOKIE = $this->originalCookie;
        $__ip = $this->originalIp;
        reset_events();
        openBoard('b');
    }

    public function testConfigReloadCoversDerivedDefaultsDebugAndCachedFastPath(): void
    {
        global $config, $board, $debug, $events, $__ip;

        $_COOKIE['e2e_load_config'] = 'defaults';
        $_COOKIE['stylesheet'] = (string) array_key_first($config['stylesheets']);
        $_SERVER['HTTP_HOST'] = 'caddy';
        $_SERVER['REMOTE_ADDR'] = '::ffff:198.51.100.9';
        $__ip = $_SERVER['REMOTE_ADDR'];
        unset($_SERVER['HTTP_X_E2E_POST_CASE']);
        $debug = null;
        loadConfig();

        self::assertFalse($config['global_message']);
        self::assertSame('/post.php', $config['post_url']);
        self::assertStringContainsString('caddy', $config['referer_match']);
        self::assertSame('/static/', $config['dir']['static']);
        self::assertSame('/b/thumb/', $config['uri_thumb']);
        self::assertSame('/b/src/', $config['uri_img']);
        self::assertSame('198.51.100.9', $_SERVER['REMOTE_ADDR']);
        self::assertSame('E2E Anonymous', $config['anonymous']);
        self::assertIsArray($debug);
        self::assertNotEmpty($events['post']);

        unset($_COOKIE['e2e_load_config'], $_COOKIE['stylesheet']);
        $cached = $this->originalConfig;
        $cached['global_message'] = 'E2E cached config';
        $cached['cache']['enabled'] = 'php';
        $cached['cache']['prefix'] = 'e2e-reload-';
        $cached['cache_config'] = true;
        $cached['cache_config_loaded'] = true;
        $config = $cached;
        \Cache::init();
        \Cache::set('config_b', $cached);
        \Cache::set('events_b', $events);
        $config = [
            'cache_config' => true,
            'cache' => $cached['cache'],
            'debug' => false,
        ];
        $board = ['uri' => 'b', 'dir' => 'b/'];
        loadConfig();

        self::assertSame('E2E cached config', $config['global_message']);
        self::assertTrue($config['cache_config_loaded']);
    }

    public function testMuteDnsAndNoko50BranchesUseRealStorage(): void
    {
        global $config;

        $_SERVER['REMOTE_ADDR'] = '198.51.100.210';
        query("DELETE FROM ``mutes`` WHERE `ip` = '198.51.100.210'");
        event_handler('mute-time', static fn(): int => 1);
        self::assertNull(checkMute());
        query("INSERT INTO ``mutes`` VALUES ('198.51.100.210', " . (time() - 10) . ')');
        self::assertNull(checkMute());
        reset_events();

        $config['dnsbl_exceptions'] = [];
        $config['dnsbl'] = [
            ['%', ['127.0.0.9'], 'array rule'],
            ['%', static fn(string $ip): bool => false, 'callable rule'],
            'invalid.invalid',
        ];
        $_SERVER['REMOTE_ADDR'] = '1.2.3.4';
        checkDNSBL();

        $threadId = $this->insertPost(null, 'Runtime noko50 thread');
        $this->insertPost($threadId, 'Runtime noko50 reply one');
        $this->insertPost($threadId, 'Runtime noko50 reply two');
        $config['noko50_count'] = 2;
        $config['noko50_min'] = 1;
        self::assertStringContainsString(
            'Runtime noko50 thread',
            buildThread50($threadId, true),
        );
    }

    public function testCleanCoversRegularAndStagedEarlyDeletion(): void
    {
        global $config;

        query("DELETE FROM ``boards`` WHERE `uri` = 'e2eclean'");
        query('DROP TABLE IF EXISTS ``posts_e2eclean``');
        query('CREATE TABLE ``posts_e2eclean`` LIKE ``posts_b``');
        query("INSERT INTO ``boards`` VALUES ('e2eclean', 'E2E clean', NULL)");

        try {
            self::assertTrue(openBoard('e2eclean'));
            $first = $this->insertPost(null, 'Runtime clean regular', 'e2eclean');
            $config['max_pages'] = 0;
            $config['threads_per_page'] = 1;
            $config['early_404'] = false;
            clean($first);
            self::assertSame(0, (int) query("SELECT COUNT(*) FROM ``posts_e2eclean`` WHERE `id` = {$first}")->fetchColumn());

            $second = $this->insertPost(null, 'Runtime clean staged', 'e2eclean');
            $config['max_pages'] = 1000;
            $config['early_404'] = true;
            $config['early_404_page'] = 0;
            $config['early_404_replies'] = 10;
            $config['early_404_staged'] = false;
            clean($second);
            self::assertSame(0, (int) query("SELECT COUNT(*) FROM ``posts_e2eclean`` WHERE `id` = {$second}")->fetchColumn());

            $third = $this->insertPost(null, 'Runtime clean staged', 'e2eclean');
            $config['early_404_staged'] = true;
            clean($third);
            self::assertSame(1, (int) query("SELECT COUNT(*) FROM ``posts_e2eclean`` WHERE `id` = {$third}")->fetchColumn());
            deletePost($third, false, false);
        } finally {
            openBoard('b');
            query('DROP TABLE IF EXISTS ``posts_e2eclean``');
            query("DELETE FROM ``boards`` WHERE `uri` = 'e2eclean'");
        }
    }

    private function insertPost(?int $thread, string $body, string $board = 'b'): int
    {
        global $pdo;

        $time = time();
        $statement = prepare(
            "INSERT INTO ``posts_{$board}``
             (`thread`, `subject`, `email`, `name`, `trip`, `capcode`, `body`, `body_nomarkup`,
              `time`, `bump`, `files`, `num_files`, `filehash`, `password`, `ip`, `sticky`,
              `locked`, `cycle`, `sage`, `embed`, `slug`)
             VALUES
             (:thread, :subject, NULL, :name, NULL, NULL, :body, :body,
              :time, :bump, NULL, 0, NULL, :password, :ip, 0,
              0, 0, 0, NULL, :slug)",
        );
        $statement->bindValue(':thread', $thread, $thread === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':subject', $thread === null ? $body : null);
        $statement->bindValue(':name', 'Runtime integration');
        $statement->bindValue(':body', $body);
        $statement->bindValue(':time', $time, PDO::PARAM_INT);
        $statement->bindValue(':bump', $thread === null ? $time : null);
        $statement->bindValue(':password', 'integration');
        $statement->bindValue(':ip', '198.51.100.211');
        $statement->bindValue(':slug', $thread === null ? 'runtime-integration' : null);
        $statement->execute();

        return (int) $pdo->lastInsertId();
    }
}
