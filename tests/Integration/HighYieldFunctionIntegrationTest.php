<?php

declare(strict_types=1);

namespace DevichanE2E\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

final class HighYieldFunctionIntegrationTest extends TestCase
{
    private array $originalConfig;
    private mixed $originalBoard;
    private mixed $originalMod;
    private string $originalDirectory;
    private string $outputDirectory = 'tests/_output/high-yield-functions';

    protected function setUp(): void
    {
        global $config, $board, $mod;

        $this->originalConfig = $config;
        $this->originalBoard = $board ?? null;
        $this->originalMod = $mod;
        $this->originalDirectory = getcwd();
        self::assertTrue(openBoard('b'));
        $mod = [
            'id' => 1,
            'type' => 30,
            'username' => 'admin',
            'boards' => ['*'],
        ];

        foreach (['', '/board', '/board/res', '/board/src', '/board/thumb'] as $suffix) {
            $directory = $this->outputDirectory . $suffix;
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
        }
    }

    protected function tearDown(): void
    {
        global $config, $board, $mod;

        chdir($this->originalDirectory);
        $config = $this->originalConfig;
        $board = $this->originalBoard;
        $mod = $this->originalMod;
        openBoard('b');
    }

    public function testJavascriptBuildCoversCssCompilationBundlingAndMinification(): void
    {
        global $config;

        $absoluteOutput = '/var/www/' . $this->outputDirectory;
        $assetDirectory = $absoluteOutput . '/assets';
        if (!is_dir($assetDirectory)) {
            mkdir($assetDirectory, 0777, true);
        }
        file_put_contents($assetDirectory . '/theme.css', "body { color: rgb(1, 2, 3); }\n");
        file_put_contents($absoluteOutput . '/extra.js', "window.e2eExtra = true;\n");
        if (!is_dir($absoluteOutput . '/js')) {
            mkdir($absoluteOutput . '/js', 0777, true);
        }

        $config['stylesheets'] = ['E2E theme' => 'theme.css', 'No theme' => ''];
        $config['uri_stylesheets'] = '/tests/_output/high-yield-functions/assets/';
        $config['minify_css'] = true;
        $config['additional_javascript_compile'] = true;
        $config['additional_javascript'] = [$absoluteOutput . '/extra.js'];
        $config['minify_js'] = true;
        $config['file_script'] = $absoluteOutput . '/main.min.js';

        chdir($absoluteOutput);
        buildJavascript();
        self::assertFileExists($assetDirectory . '/theme.min.css');
        self::assertFileExists($absoluteOutput . '/main.min.js');
        self::assertFileExists($absoluteOutput . '/js/package.json');
        self::assertStringContainsString('e2eExtra', (string) file_get_contents($absoluteOutput . '/main.min.js'));

        $config['minify_css'] = false;
        $config['minify_js'] = false;
        $config['additional_javascript_compile'] = false;
        $config['file_script'] = $absoluteOutput . '/main.js';
        buildJavascript();
        self::assertFileExists($absoluteOutput . '/main.js');
        self::assertFileDoesNotExist($absoluteOutput . '/js/package.json');
    }

    public function testFileAndPostDeletionCoverMultiFileAndCrossBoardCleanup(): void
    {
        global $board, $config;

        $board['dir'] = $this->outputDirectory . '/board/';
        $config['anti_bump_flood'] = true;
        $threadId = $this->insertPost(null, null, 0);

        $files = [
            ['file' => 'one.png', 'thumb' => 'one.png'],
            ['file' => 'two.png', 'thumb' => 'two.png'],
        ];
        foreach ($files as $file) {
            file_put_contents($board['dir'] . $config['dir']['img'] . $file['file'], 'source');
            file_put_contents($board['dir'] . $config['dir']['thumb'] . $file['thumb'], 'thumb');
        }
        $replyId = $this->insertPost($threadId, json_encode($files, JSON_THROW_ON_ERROR), 2);

        deleteFile($replyId, true, 0);
        self::assertFileDoesNotExist($board['dir'] . $config['dir']['img'] . 'one.png');
        deleteFile($replyId, true, 1);
        deleteFile($replyId, true, 1);

        $deletedOp = $this->insertPost(
            null,
            json_encode([['file' => 'deleted']], JSON_THROW_ON_ERROR),
            1,
        );
        deleteFile($deletedOp);
        self::assertSame(
            1,
            (int) query("SELECT COUNT(*) FROM ``posts_b`` WHERE `id` = {$deletedOp}")->fetchColumn(),
        );

        $cleanupFiles = [['file' => 'cleanup.png', 'thumb' => 'cleanup.png']];
        file_put_contents($board['dir'] . $config['dir']['img'] . 'cleanup.png', 'source');
        file_put_contents($board['dir'] . $config['dir']['thumb'] . 'cleanup.png', 'thumb');
        $cleanupReply = $this->insertPost(
            $threadId,
            json_encode($cleanupFiles, JSON_THROW_ON_ERROR),
            1,
        );
        query(sprintf(
            "INSERT INTO ``cites`` (`board`, `post`, `target_board`, `target`) VALUES ('sec', 1, 'b', %d)",
            $cleanupReply,
        ));
        self::assertTrue(deletePost($cleanupReply, false, false));
        self::assertFalse(deletePost(99999999, false, false));
        self::assertFileDoesNotExist($board['dir'] . $config['dir']['img'] . 'cleanup.png');
    }

    public function testGenerationLinksMarkdownUrlsAndCryptoUtilitiesCoverAlternatePaths(): void
    {
        global $config, $board;

        $config['generation_strategies'] = ['strategy_immediate'];
        self::assertSame('rebuild', generation_strategy('sb_board', ['b', 1]));
        $config['generation_strategies'] = ['strategy_smart_build'];
        self::assertSame('delete', generation_strategy('sb_board', ['b', 1]));
        $config['queue']['enabled'] = 'fs';
        $config['generation_strategies'] = ['strategy_first'];
        self::assertSame('ignore', generation_strategy('sb_thread', ['b', 1]));
        self::assertSame('delete', generation_strategy('sb_recent'));
        self::assertSame(['defer'], strategy_first('sb_api', ['b']));
        self::assertSame(['defer'], strategy_first('sb_catalog', ['b']));
        self::assertSame(['build_on_load'], strategy_first('sb_sitemap', []));
        self::assertSame(['defer'], strategy_first('sb_ukko', []));
        self::assertFalse(strategy_sane('sb_thread', ['b', 1]));

        $config['slugify'] = true;
        $config['cache']['enabled'] = 'php';
        \Cache::init();
        $board['uri'] = 'b';
        self::assertStringContainsString('seed-thread', link_for(['id' => 1]));
        self::assertStringContainsString('+50', link_for(['id' => 1], true));
        self::assertStringContainsString('seed-thread', link_for(
            ['id' => 2, 'thread' => 1],
            false,
            ['uri' => 'b'],
            ['slug' => 'seed-thread'],
        ));

        self::assertSame('<p><strong>safe</strong></p>', markdown('**safe**'));
        self::assertStringNotContainsString('<script', purify_html('<script>x</script><b>safe</b>'));
        self::assertSame(['https://example.com/path'], get_urls('See https://example.com/path.'));
        self::assertSame('hello', base32_decode(base32_encode('hello')));
        self::assertSame('http://caddy/b/', trace_url('http://caddy/b/'));

        $config['bcrypt_ip_addresses'] = true;
        $config['bcrypt_ip_cost'] = '04';
        $config['bcrypt_ip_salt'] = 'abcdefghijklmnopqrstuv';
        $hash = get_ip_hash('198.51.100.9');
        self::assertSame($hash, get_ip_hash('198.51.100.9'));
        self::assertNotSame('198.51.100.9', $hash);

        $config['ipcrypt_key'] = 'php85-audit-key';
        $config['ipcrypt_prefix'] = 'e2e';
        $config['ipcrypt_dns'] = false;
        self::assertSame('#ERROR', uncloak_ip('e2e:AAAA'));
        $cloaked = cloak_ip('198.51.100.10');
        self::assertStringStartsWith('e2e:', $cloaked);
        self::assertSame('198.51.100.10', uncloak_ip($cloaked));
    }

    public function testModeratorCanSpoilerAndDeleteAStoredFile(): void
    {
        global $board, $config;

        require_once 'inc/mod/pages.php';
        $board['dir'] = $this->outputDirectory . '/board/';
        $config['spoiler_image'] = $this->outputDirectory . '/spoiler.png';
        $config['generation_strategies'] = ['strategy_immediate'];
        $config['api']['enabled'] = false;
        $config['max_pages'] = 1;

        $spoiler = imagecreatetruecolor(16, 16);
        imagefill($spoiler, 0, 0, imagecolorallocate($spoiler, 20, 20, 20));
        self::assertTrue(imagepng($spoiler, $config['spoiler_image']));
        imagedestroy($spoiler);

        file_put_contents($board['dir'] . $config['dir']['img'] . 'moderated.png', 'source');
        file_put_contents($board['dir'] . $config['dir']['thumb'] . 'moderated.png', 'thumb');
        $files = [[
            'file' => 'moderated.png',
            'thumb' => 'moderated.png',
            'filename' => 'moderated.png',
            'size' => 6,
            'width' => 16,
            'height' => 16,
            'thumbwidth' => 16,
            'thumbheight' => 16,
        ]];
        $postId = $this->insertPost(null, json_encode($files, JSON_THROW_ON_ERROR), 1);

        \mod_spoiler_image('b', $postId, 0);
        $stored = json_decode(
            (string) query("SELECT `files` FROM ``posts_b`` WHERE `id` = {$postId}")->fetchColumn(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertSame('spoiler', $stored[0]['thumb']);

        \mod_deletefile('b', $postId, 0);
        $stored = json_decode(
            (string) query("SELECT `files` FROM ``posts_b`` WHERE `id` = {$postId}")->fetchColumn(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertSame('deleted', $stored[0]['file']);
        deletePost($postId, false, false);
    }

    private function insertPost(?int $thread, ?string $files, int $numFiles): int
    {
        global $pdo;

        $time = time();
        $statement = prepare(
            'INSERT INTO ``posts_b``
             (`thread`, `subject`, `email`, `name`, `trip`, `capcode`, `body`, `body_nomarkup`,
              `time`, `bump`, `files`, `num_files`, `filehash`, `password`, `ip`, `sticky`,
              `locked`, `cycle`, `sage`, `embed`, `slug`)
             VALUES
             (:thread, :subject, NULL, :name, NULL, NULL, :body, :body,
              :time, :bump, :files, :num_files, NULL, :password, :ip, 0,
              0, 0, 0, NULL, :slug)',
        );
        $statement->bindValue(':thread', $thread, $thread === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':subject', $thread === null ? 'High yield thread' : null);
        $statement->bindValue(':name', 'Integration');
        $statement->bindValue(':body', 'High yield integration ' . bin2hex(random_bytes(4)));
        $statement->bindValue(':time', $time, PDO::PARAM_INT);
        $statement->bindValue(':bump', $thread === null ? $time : null);
        $statement->bindValue(':files', $files, $files === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':num_files', $numFiles, PDO::PARAM_INT);
        $statement->bindValue(':password', 'integration');
        $statement->bindValue(':ip', '198.51.100.200');
        $statement->bindValue(':slug', $thread === null ? 'high-yield-thread' : null);
        $statement->execute();

        return (int) $pdo->lastInsertId();
    }
}
