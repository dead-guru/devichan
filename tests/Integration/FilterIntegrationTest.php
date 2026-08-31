<?php

declare(strict_types=1);

namespace DevichanE2E\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

final class FilterIntegrationTest extends TestCase
{
    private array $originalConfig;
    private mixed $originalBoard;
    private array $originalServer;
    private string $ip = '198.51.100.246';

    protected function setUp(): void
    {
        global $config, $board;

        $this->originalConfig = $config;
        $this->originalBoard = $board ?? null;
        $this->originalServer = $_SERVER;
        $_SERVER['REMOTE_ADDR'] = $this->ip;
        self::assertTrue(openBoard('b'));
    }

    protected function tearDown(): void
    {
        global $config, $board;

        $ip = prepare('DELETE FROM ``bans`` WHERE `ipstart` = :ip');
        $ip->bindValue(':ip', inet_pton($this->ip), PDO::PARAM_LOB);
        $ip->execute();
        $note = prepare('DELETE FROM ``ip_notes`` WHERE `ip` = :ip');
        $note->bindValue(':ip', $this->ip);
        $note->execute();
        $flood = prepare('DELETE FROM ``flood`` WHERE `ip` = :ip');
        $flood->bindValue(':ip', $this->ip);
        $flood->execute();

        $config = $this->originalConfig;
        $board = $this->originalBoard;
        $_SERVER = $this->originalServer;
        openBoard('b');
    }

    public function testMatchCoversScalarFileCustomAndNegatedConditions(): void
    {
        $post = $this->post();
        $filter = new \Filter([
            'post' => $post,
            'condition' => [
                'custom' => static fn(array $candidate): bool => $candidate['board'] === 'b',
                'name' => '/Integration/',
                'trip' => '!trip',
                'email' => '/example\.com/',
                'subject' => '/Subject/',
                'body' => '/filter body/',
                'filehash' => 'file-hash',
                'filename' => '/image\.png/',
                'extension' => '/png/',
                'ip' => '/^198\.51\.100\./',
                'op' => true,
                'has_file' => true,
                'board' => 'b',
                'password' => 'secret',
                '!name' => '/Never matches/',
            ],
        ]);

        self::assertTrue($filter->check($post));
        self::assertFalse($filter->match('filename', '/missing/'));
        self::assertFalse($filter->match('extension', '/jpg/'));

        $withoutFiles = $this->post();
        $withoutFiles['files'] = [];
        $withoutFiles['has_file'] = false;
        $withoutFiles['body_nomarkup'] = 'http://caddy/b/';
        $withoutFilesFilter = new \Filter(['post' => $withoutFiles]);
        self::assertFalse($withoutFilesFilter->match('filename', '/image/'));
        self::assertFalse($withoutFilesFilter->match('extension', '/png/'));
        self::assertTrue($withoutFilesFilter->match('unshorten', '~^http://caddy/b/$~'));
    }

    public function testFloodMatchingCoversEveryComparisonAndCounter(): void
    {
        $post = $this->post();
        $matching = [
            'ip' => $this->ip,
            'board' => 'b',
            'posthash' => make_comment_hex($post['body_nomarkup']),
            'filehash' => $post['filehash'],
            'isreply' => 0,
            'time' => time(),
        ];
        $filter = new \Filter([
            'post' => $post,
            'condition' => ['flood-time' => 60],
        ]);
        $filter->flood_check = [$matching, array_merge($matching, ['ip' => '203.0.113.2'])];

        self::assertTrue($filter->match('flood-match', ['ip', 'body', 'file', 'board', 'isreply']));
        self::assertCount(1, $filter->flood_check);
        self::assertTrue($filter->match('flood-time', 60));
        self::assertTrue($filter->match('flood-count', 1));
        self::assertFalse($filter->match('flood-count', 2));

        $filter->flood_check = [array_merge($matching, ['time' => time() - 120])];
        self::assertFalse($filter->match('flood-time', 60));

        $withoutHash = $post;
        unset($withoutHash['filehash']);
        $withoutHashFilter = new \Filter(['post' => $withoutHash]);
        $withoutHashFilter->flood_check = [$matching];
        self::assertFalse($withoutHashFilter->match('flood-match', ['file']));

        foreach ([
            'file' => ['filehash' => 'different-file'],
            'board' => ['board' => 'sec'],
            'isreply' => ['isreply' => 1],
        ] as $field => $override) {
            $mismatch = new \Filter(['post' => $post]);
            $mismatch->flood_check = [array_merge($matching, $override)];
            self::assertFalse($mismatch->match('flood-match', [$field]));
        }

        $unmatchedUrlFilter = new \Filter(['post' => $post]);
        self::assertFalse($unmatchedUrlFilter->match('unshorten', '~^https://example.invalid/$~'));
    }

    public function testActionsNotesAndFilterPipelineUseDatabaseState(): void
    {
        global $config;

        $post = $this->post();
        $noteFilter = new \Filter(['post' => $post, 'add_note' => true]);
        $noteFilter->action();
        self::assertSame(
            1,
            (int) query("SELECT COUNT(*) FROM ``ip_notes`` WHERE `ip` = '{$this->ip}'")->fetchColumn(),
        );

        $config['generation_strategies'] = ['strategy_immediate'];
        $banFilter = new \Filter([
            'post' => $post,
            'action' => 'ban',
            'reason' => 'Integration automatic ban',
            'expires' => 60,
            'all_boards' => true,
            'reject' => false,
        ]);
        $banFilter->action();
        $lookup = prepare('SELECT COUNT(*) FROM ``bans`` WHERE `ipstart` = :ip');
        $lookup->bindValue(':ip', inet_pton($this->ip), PDO::PARAM_LOB);
        $lookup->execute();
        self::assertSame(1, (int) $lookup->fetchColumn());

        $config['flood_cache'] = -1;
        $config['filters'] = [[
            'condition' => [
                'flood-match' => ['ip', 'board', 'isreply'],
                'flood-time' => 120,
                'flood-count' => 1,
            ],
        ]];
        $this->insertFlood($post);
        do_filters($post);

        $withoutFile = $post;
        $withoutFile['has_file'] = false;
        unset($withoutFile['filehash']);
        do_filters($withoutFile);

        $config['filters'] = [[
            'condition' => ['custom' => static fn(array $candidate): bool => $candidate['board'] === 'b'],
        ]];
        do_filters($post);

        $config['filters'] = [];
        self::assertNull(do_filters($post));
        $config['flood_cache'] = 0;
        purge_flood_table();
    }

    private function post(): array
    {
        return [
            'name' => 'Integration Name',
            'trip' => '!trip',
            'email' => 'filter@example.com',
            'subject' => 'Integration Subject',
            'body' => 'Integration filter body',
            'body_nomarkup' => 'Integration filter body',
            'filehash' => 'file-hash',
            'files' => [['filename' => 'image.png', 'extension' => 'png']],
            'op' => true,
            'has_file' => true,
            'board' => 'b',
            'password' => 'secret',
        ];
    }

    private function insertFlood(array $post): void
    {
        $statement = prepare(
            'INSERT INTO ``flood`` (`ip`, `board`, `time`, `posthash`, `filehash`, `isreply`)
             VALUES (:ip, :board, :time, :posthash, :filehash, 0)',
        );
        $statement->bindValue(':ip', $this->ip);
        $statement->bindValue(':board', 'b');
        $statement->bindValue(':time', time(), PDO::PARAM_INT);
        $statement->bindValue(':posthash', make_comment_hex($post['body_nomarkup']));
        $statement->bindValue(':filehash', $post['filehash']);
        $statement->execute();
    }
}
