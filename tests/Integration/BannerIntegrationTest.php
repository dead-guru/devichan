<?php

declare(strict_types=1);

namespace DevichanE2E\Integration;

use PHPUnit\Framework\TestCase;

final class BannerIntegrationTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = dirname(__DIR__) . '/_output/banner-' . bin2hex(random_bytes(6));
        mkdir($this->directory . '/static/banners/b', 0777, true);
        copy(dirname(__DIR__, 2) . '/b.php', $this->directory . '/b.php');
        file_put_contents($this->directory . '/request.php', <<<'PHP'
<?php
$_GET = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
$_SERVER['REQUEST_METHOD'] = $argv[2];
mt_srand((int) $argv[3]);
register_shutdown_function(static function () {
    fwrite(STDERR, (string) (http_response_code() ?: 200));
});
require __DIR__ . '/b.php';
PHP);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->isDir() && !$file->isLink()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->directory);
    }

    public function testBoardRotationIncludesGlobalAndBoardBanners(): void
    {
        $global = $this->banner('shared.svg', 'global');
        $local = $this->banner('b/shared.svg', 'board');
        $this->banner('other/local.svg', 'other board');
        $this->banner('.hidden.svg', 'hidden');
        $this->banner('ignored.txt', 'unsupported');

        $bodies = [];
        for ($seed = 0; $seed < 16; $seed++) {
            $response = $this->request(['board' => 'b'], seed: $seed);
            self::assertSame(200, $response['status']);
            $bodies[] = $response['body'];
        }

        self::assertEqualsCanonicalizing([$global, $local], array_values(array_unique($bodies)));
        self::assertSame($global, $this->request([])['body']);
    }

    public function testMissingEmptyAndInvalidBoardsKeepGlobalBanners(): void
    {
        $global = $this->banner('shared.svg', 'global');

        foreach (['missing', 'b', '../private', ['b']] as $board) {
            $response = $this->request(['board' => $board]);
            self::assertSame(200, $response['status']);
            self::assertSame($global, $response['body']);
        }
    }

    public function testBoardSymlinksCannotEscapeTheBannerDirectory(): void
    {
        $global = $this->banner('shared.svg', 'global');
        mkdir($this->directory . '/private');
        file_put_contents($this->directory . '/private/secret.svg', 'private');
        symlink($this->directory . '/private', $this->directory . '/static/banners/escape');

        self::assertSame($global, $this->request(['board' => 'escape'])['body']);
    }

    public function testBoardBannersWorkWithoutGlobalBannersAndHeadHasNoBody(): void
    {
        $local = $this->banner('b/local.svg', 'board');

        self::assertSame(['status' => 200, 'body' => $local], $this->request(['board' => '/b/']));
        self::assertSame(['status' => 200, 'body' => ''], $this->request(['board' => 'b'], 'HEAD'));
    }

    public function testNoBannersReturnsNotFound(): void
    {
        self::assertSame(
            ['status' => 404, 'body' => 'No banners available'],
            $this->request(['board' => 'b'])
        );
    }

    private function banner(string $path, string $label): string
    {
        $path = $this->directory . '/static/banners/' . $path;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="100"><text y="50">'
            . $label . '</text></svg>';
        file_put_contents($path, $svg);
        return $svg;
    }

    private function request(array $query, string $method = 'GET', int $seed = 0): array
    {
        $process = proc_open(
            [PHP_BINARY, $this->directory . '/request.php', json_encode($query, JSON_THROW_ON_ERROR), $method, (string) $seed],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        self::assertIsResource($process);
        $body = stream_get_contents($pipes[1]);
        $status = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $status);
        self::assertMatchesRegularExpression('/^(200|404)$/', $status);
        return ['status' => (int) $status, 'body' => $body];
    }
}
