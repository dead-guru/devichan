<?php

declare(strict_types=1);

namespace DevichanE2E\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

final class ApiIntegrationTest extends TestCase
{
    private array $originalConfig;
    private mixed $originalBoard;

    protected function setUp(): void
    {
        global $config, $board;

        $this->originalConfig = $config;
        $this->originalBoard = $board ?? null;
        self::assertTrue(openBoard('b'));
    }

    protected function tearDown(): void
    {
        global $config, $board;

        $config = $this->originalConfig;
        $board = $this->originalBoard;
        openBoard('b');
    }

    public function testThreadApiSerializesFlagsSlugsAndLegacyFileHashes(): void
    {
        global $config;

        $row = query('SELECT * FROM ``posts_b`` WHERE `id` = 1')->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);

        $row['body_nomarkup'] = '<tinyboard flag>ua</tinyboard>'
            . '<tinyboard flag alt>Ukraine</tinyboard>';
        $row['filehash'] = md5('legacy-file-hash');
        $row['files'] = json_encode([[
            'name' => 'integration.jpg',
            'file' => '1700000000.jpg',
            'thumbheight' => 50,
            'thumbwidth' => 100,
            'height' => 500,
            'width' => 1000,
            'size' => 12345,
        ]], JSON_THROW_ON_ERROR);

        $config['country_flags'] = true;
        $config['slugify'] = true;
        $thread = new \Thread($row);
        $payload = (new \Api())->translateThread($thread);
        $post = $payload['posts'][0];

        self::assertSame('UA', $post['country']);
        self::assertSame('Ukraine', $post['country_name']);
        self::assertSame('seed-thread', $post['semantic_url']);
        self::assertSame(base64_encode(hex2bin($row['filehash'])), $post['md5']);
    }
}
