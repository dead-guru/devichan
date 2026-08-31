<?php

declare(strict_types=1);

namespace DevichanE2E\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

final class BansIntegrationTest extends TestCase
{
    private mixed $originalMod;
    private mixed $originalBoard;
    private array $banIds = [];

    protected function setUp(): void
    {
        global $mod, $board;

        $this->originalMod = $mod;
        $this->originalBoard = $board ?? null;
        self::assertTrue(openBoard('b'));
        $mod = [
            'id' => 1,
            'type' => 30,
            'username' => 'admin',
            'boards' => ['*'],
        ];
    }

    protected function tearDown(): void
    {
        global $mod, $board;

        if ($this->banIds !== []) {
            query('DELETE FROM ``bans`` WHERE `id` IN (' . implode(',', array_map('intval', $this->banIds)) . ')');
        }
        $cleanup = prepare('DELETE FROM ``bans`` WHERE `ipstart` = :ip');
        $cleanup->bindValue(':ip', inet_pton('198.51.100.44'), PDO::PARAM_LOB);
        $cleanup->execute();
        $mod = $this->originalMod;
        $board = $this->originalBoard;
        openBoard('b');
    }

    public function testCustomDurationParserCoversEverySupportedUnit(): void
    {
        $before = time();
        $parsed = \Bans::parse_time('1ye 2mon 3we 4da5ho 6min 7se');
        $expectedSeconds = 365 * 86400 + 2 * 30 * 86400 + 3 * 7 * 86400 + 4 * 86400 + 5 * 3600 + 6 * 60 + 7;

        self::assertIsInt($parsed);
        self::assertGreaterThanOrEqual($before + $expectedSeconds, $parsed);
        self::assertLessThanOrEqual(time() + $expectedSeconds, $parsed);
        self::assertFalse(\Bans::parse_time('not a duration'));
        self::assertFalse(\Bans::parse_time(''));
        self::assertGreaterThan(time(), \Bans::parse_time('tomorrow'));
    }

    public function testRangeParsingCoversWildcardsCidrsAndInvalidInputs(): void
    {
        self::assertSame('192.0.2.4', \Bans::range_to_string(\Bans::parse_range('192.0.2.4')));
        self::assertSame('192.0.2.0/24', \Bans::range_to_string(\Bans::parse_range('192.0.2.*')));
        self::assertSame('192.0.20.0 - 192.0.29.255', \Bans::range_to_string(\Bans::parse_range('192.0.2*')));
        self::assertSame('2001:db8::/64', \Bans::range_to_string(\Bans::parse_range('2001:db8::/64')));
        self::assertFalse(\Bans::parse_range('192.0.2.0/33'));
        self::assertFalse(\Bans::parse_range('2001:db8::/129'));
        self::assertFalse(\Bans::parse_range('not-an-ip'));
        self::assertSame('???', \Bans::range_to_string([inet_pton('192.0.2.1'), inet_pton('2001:db8::1')]));
    }

    public function testJsonStreamMasksRestrictedBansAndPreservesAccessibleDetails(): void
    {
        $accessible = $this->insertBan('198.51.100.41', null, 'b', ['body' => 'Visible post body']);
        $restricted = $this->insertBan('203.0.113.0', '203.0.113.255', 'sec', null);

        $stream = fopen('php://temp', 'w+');
        self::assertIsResource($stream);
        \Bans::stream_json($stream, false, false, ['b']);
        rewind($stream);
        $json = json_decode((string) stream_get_contents($stream), true, 512, JSON_THROW_ON_ERROR);
        fclose($stream);

        $byId = [];
        foreach ($json as $ban) {
            $byId[(int) $ban['id']] = $ban;
        }

        self::assertTrue($byId[$accessible]['access']);
        self::assertTrue($byId[$accessible]['single_addr']);
        self::assertSame('Visible post body', $byId[$accessible]['message']);
        self::assertSame('?', $byId[$restricted]['username']);
        self::assertTrue($byId[$restricted]['masked']);
        self::assertStringContainsString('.x.x', $byId[$restricted]['mask']);

        ob_start();
        \Bans::stream_json(false, true, true, ['*']);
        $printed = json_decode((string) ob_get_clean(), true, 512, JSON_THROW_ON_ERROR);
        self::assertNotEmpty($printed);
        self::assertTrue($printed[0]['masked']);
        self::assertSame('?', $printed[0]['username']);
    }

    public function testFindSeenPurgeAndDeleteUseTheRealDatabase(): void
    {
        global $config;

        $expired = $this->insertBan('198.51.100.42', null, 'b', null, time() - 60, true);
        self::assertSame([], \Bans::find('198.51.100.42', 'b', true));
        self::assertSame(0, (int) query("SELECT COUNT(*) FROM ``bans`` WHERE `id` = {$expired}")->fetchColumn());

        $seen = $this->insertBan('198.51.100.43', null, 'b', null, time() + 3600);
        \Bans::seen($seen);
        self::assertSame(1, (int) query("SELECT `seen` FROM ``bans`` WHERE `id` = {$seen}")->fetchColumn());
        self::assertNotEmpty(\Bans::find('127.0.0.1', false, true, $seen));
        self::assertTrue(\Bans::delete($seen, true, ['*'], true));
        self::assertFalse(\Bans::delete(99999999, true, ['b'], true));

        $purgeable = $this->insertBan('198.51.100.45', null, null, null, time() - 60, true);
        \Bans::purge();
        self::assertSame(0, (int) query("SELECT COUNT(*) FROM ``bans`` WHERE `id` = {$purgeable}")->fetchColumn());
    }

    public function testNewBanReturnsTheCreatedDatabaseId(): void
    {
        global $config;

        $config['generation_strategies'] = ['strategy_immediate'];
        $returnedId = (int) \Bans::new_ban('198.51.100.44', '', 60, false, 1, false);
        $lookup = prepare('SELECT * FROM ``bans`` WHERE `ipstart` = :ip ORDER BY `id` DESC LIMIT 1');
        $lookup->bindValue(':ip', inet_pton('198.51.100.44'), PDO::PARAM_LOB);
        $lookup->execute();
        $stored = $lookup->fetch(PDO::FETCH_ASSOC);

        self::assertIsArray($stored);
        self::assertNull($stored['reason']);
        self::assertNull($stored['board']);
        self::assertGreaterThan(time(), (int) $stored['expires']);
        self::assertSame((int) $stored['id'], $returnedId, 'Bans::new_ban() must return the ID it inserted.');
    }

    public function testNewBanStoresAndFindsItsSourcePost(): void
    {
        global $board, $config;

        $config['generation_strategies'] = ['strategy_immediate'];
        $board = null;
        $post = [
            'board' => 'b',
            'id' => 1,
            'body' => 'Integration source post',
        ];
        \Bans::new_ban('198.51.100.46', 'Source post ban', 60, 'b', 1, $post);
        $lookup = prepare('SELECT `id` FROM ``bans`` WHERE `ipstart` = :ip ORDER BY `id` DESC LIMIT 1');
        $lookup->bindValue(':ip', inet_pton('198.51.100.46'), PDO::PARAM_LOB);
        $lookup->execute();
        $id = (int) $lookup->fetchColumn();
        $this->banIds[] = $id;

        $found = \Bans::find('198.51.100.46', 'b', false, $id);
        self::assertCount(1, $found);
        self::assertSame('b', $found[0]['post']['board']);
        self::assertSame('Integration source post', $found[0]['post']['body']);
    }

    private function insertBan(
        string $start,
        ?string $end,
        ?string $board,
        ?array $post,
        ?int $expires = null,
        bool $seen = false,
    ): int {
        global $pdo;

        $statement = prepare(
            'INSERT INTO ``bans`` (`ipstart`, `ipend`, `created`, `expires`, `board`, `creator`, `reason`, `seen`, `post`)
             VALUES (:start, :end, :created, :expires, :board, 1, :reason, :seen, :post)',
        );
        $statement->bindValue(':start', inet_pton($start), PDO::PARAM_LOB);
        $statement->bindValue(':end', $end === null ? null : inet_pton($end), $end === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
        $statement->bindValue(':created', time(), PDO::PARAM_INT);
        $statement->bindValue(':expires', $expires, $expires === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':board', $board, $board === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':reason', 'Integration ban');
        $statement->bindValue(':seen', $seen ? 1 : 0, PDO::PARAM_INT);
        $statement->bindValue(':post', $post === null ? null : json_encode($post, JSON_THROW_ON_ERROR), $post === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->execute();

        $id = (int) $pdo->lastInsertId();
        $this->banIds[] = $id;
        return $id;
    }
}
