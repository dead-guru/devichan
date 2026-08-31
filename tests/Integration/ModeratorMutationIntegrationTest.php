<?php

declare(strict_types=1);

namespace DevichanE2E\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

final class ModeratorMutationIntegrationTest extends TestCase
{
    private array $originalConfig;
    private mixed $originalMod;
    private mixed $originalBoard;
    private array $originalPost;

    protected function setUp(): void
    {
        global $config, $mod, $board;

        $this->originalConfig = $config;
        $this->originalMod = $mod;
        $this->originalBoard = $board ?? null;
        $this->originalPost = $_POST;
        $mod = [
            'id' => 1,
            'type' => 30,
            'username' => 'admin',
            'boards' => ['*'],
        ];
        $_POST = [];
        self::assertTrue(openBoard('b'));
        require_once 'inc/mod/pages.php';
    }

    protected function tearDown(): void
    {
        global $config, $mod, $board;

        $config = $this->originalConfig;
        $mod = $this->originalMod;
        $board = $this->originalBoard;
        $_POST = $this->originalPost;
        openBoard('b');
    }

    public function testLoginConfigAndThemeControllersRenderAlternateModes(): void
    {
        global $config;

        foreach ([
            ['login' => '1'],
            ['login' => '1', 'username' => str_repeat('u', 129), 'password' => 'x'],
            ['login' => '1', 'username' => 'missing-user', 'password' => 'wrong-password'],
        ] as $post) {
            $_POST = $post;
            self::assertStringContainsString('Login', $this->capture(static fn() => \mod_login('/reports')));
        }

        $_POST = [];
        $config['mod']['config_editor_php'] = true;
        self::assertStringContainsString('Config editor', $this->capture(static fn() => \mod_config()));

        $config['mod']['config_editor_php'] = false;
        self::assertStringContainsString('Config editor', $this->capture(static fn() => \mod_config()));
        self::assertStringContainsString('Manage themes', $this->capture(static fn() => \mod_themes_list()));
        self::assertStringContainsString('Configuring theme', $this->capture(static fn() => \mod_theme_configure('catalog')));
    }

    public function testBanAppealsExerciseListingDenialAndAcceptance(): void
    {
        $deniedBan = $this->insertBan('198.51.100.31', 'integration denied appeal');
        $deniedAppeal = $this->insertAppeal($deniedBan, null);
        $acceptedBan = $this->insertBan('198.51.100.32', 'integration accepted appeal');
        $acceptedAppeal = $this->insertAppeal($acceptedBan, json_encode([
            'board' => 'b',
            'id' => 999999,
            'thread' => null,
            'body' => 'deleted post snapshot',
        ], JSON_THROW_ON_ERROR));

        $_POST = [];
        self::assertStringContainsString('Ban appeals', $this->capture(static fn() => \mod_ban_appeals()));

        $_POST = ['appeal_id' => $deniedAppeal, 'deny' => '1'];
        $this->capture(static fn() => \mod_ban_appeals());
        self::assertSame(1, (int) query("SELECT `denied` FROM ``ban_appeals`` WHERE `id` = {$deniedAppeal}")->fetchColumn());

        $_POST = ['appeal_id' => $acceptedAppeal, 'unban' => '1'];
        $this->capture(static fn() => \mod_ban_appeals());
        self::assertSame(0, (int) query("SELECT COUNT(*) FROM ``bans`` WHERE `id` = {$acceptedBan}")->fetchColumn());
        self::assertSame(0, (int) query("SELECT COUNT(*) FROM ``ban_appeals`` WHERE `id` = {$acceptedAppeal}")->fetchColumn());
    }

    public function testIpNotesTelegramsAndThreadStateMutationsRunEndToEnd(): void
    {
        global $config;

        $config['ipcrypt_key'] = false;
        $ip = '198.51.100.41';
        query("DELETE FROM ``ip_notes`` WHERE `ip` = '{$ip}'");
        query("DELETE FROM ``telegrams`` WHERE `ip` = '{$ip}'");
        $note = prepare('INSERT INTO ``ip_notes`` VALUES (NULL, :ip, 1, :time, :body)');
        $note->execute([':ip' => $ip, ':time' => time(), ':body' => 'remove me']);
        $noteId = (int) query('SELECT LAST_INSERT_ID()')->fetchColumn();
        $telegram = prepare('INSERT INTO ``telegrams`` VALUES (NULL, 1, :ip, :message, 0, :time)');
        $telegram->execute([':ip' => $ip, ':message' => 'remove me', ':time' => time()]);
        $telegramId = (int) query('SELECT LAST_INSERT_ID()')->fetchColumn();

        \mod_ip_remove_note($ip, $noteId);
        \mod_ip_remove_telegram($ip, $telegramId);
        self::assertSame(0, (int) query("SELECT COUNT(*) FROM ``ip_notes`` WHERE `id` = {$noteId}")->fetchColumn());
        self::assertSame(0, (int) query("SELECT COUNT(*) FROM ``telegrams`` WHERE `id` = {$telegramId}")->fetchColumn());

        query('UPDATE ``posts_b`` SET `locked` = 0, `sticky` = 0, `cycle` = 0, `sage` = 0 WHERE `id` = 1');
        \mod_lock('b', false, 1);
        \mod_lock('b', true, 1);
        \mod_sticky('b', false, 1);
        \mod_sticky('b', true, 1);
        \mod_cycle('b', false, 1);
        \mod_cycle('b', true, 1);
        \mod_bumplock('b', false, 1);
        \mod_bumplock('b', true, 1);
        self::assertSame(
            ['locked' => 0, 'sticky' => 0, 'cycle' => 0, 'sage' => 0],
            query('SELECT `locked`, `sticky`, `cycle`, `sage` FROM ``posts_b`` WHERE `id` = 1')->fetch(PDO::FETCH_ASSOC),
        );
    }

    public function testPrivateMessagesReportsAndUserVariantsMutateTheDatabase(): void
    {
        $recipient = $this->insertModerator('integration-recipient');
        $pm = prepare('INSERT INTO ``pms`` VALUES (NULL, :sender, 1, :message, :time, 1)');
        $pm->execute([
            ':sender' => $recipient,
            ':message' => 'Integration unread PM',
            ':time' => time(),
        ]);
        $pmId = (int) query('SELECT LAST_INSERT_ID()')->fetchColumn();

        $_POST = [];
        self::assertStringContainsString('Integration unread PM', $this->capture(static fn() => \mod_pm($pmId)));
        self::assertSame(0, (int) query("SELECT `unread` FROM ``pms`` WHERE `id` = {$pmId}")->fetchColumn());
        self::assertStringContainsString('Integration unread PM', $this->capture(static fn() => \mod_pm($pmId, true)));

        $_POST = ['delete' => '1'];
        $this->capture(static fn() => \mod_pm($pmId));
        self::assertSame(0, (int) query("SELECT COUNT(*) FROM ``pms`` WHERE `id` = {$pmId}")->fetchColumn());

        $_POST = [];
        self::assertStringContainsString('New PM', $this->capture(static fn() => \mod_new_pm((string) $recipient)));
        $_POST = ['message' => 'Integration sent PM'];
        $this->capture(static fn() => \mod_new_pm('integration-recipient'));
        self::assertSame(1, (int) query("SELECT COUNT(*) FROM ``pms`` WHERE `to` = {$recipient} AND `message` LIKE '%Integration sent PM%'")->fetchColumn());

        query('DELETE FROM ``reports``');
        $report = prepare('INSERT INTO ``reports`` VALUES (NULL, :time, :ip, :board, :post, :reason)');
        $report->execute([
            ':time' => time(),
            ':ip' => '198.51.100.51',
            ':board' => 'b',
            ':post' => 1,
            ':reason' => 'integration report one',
        ]);
        $firstReport = (int) query('SELECT LAST_INSERT_ID()')->fetchColumn();
        $report->execute([
            ':time' => time(),
            ':ip' => '198.51.100.51',
            ':board' => 'b',
            ':post' => 1,
            ':reason' => 'integration report two',
        ]);
        $secondReport = (int) query('SELECT LAST_INSERT_ID()')->fetchColumn();
        $_POST = [];
        self::assertStringContainsString('Report queue', $this->capture(static fn() => \mod_reports()));
        \mod_report_dismiss($firstReport);
        \mod_report_dismiss($secondReport, true);
        self::assertSame(0, (int) query('SELECT COUNT(*) FROM ``reports``')->fetchColumn());

        query("DELETE FROM ``mods`` WHERE `id` = {$recipient}");
    }

    private function insertModerator(string $username): int
    {
        global $pdo;

        [$version, $password] = crypt_password('integration-password');
        $statement = prepare('INSERT INTO ``mods`` VALUES (NULL, :username, :password, :version, 10, :boards)');
        $statement->execute([
            ':username' => $username,
            ':password' => $password,
            ':version' => $version,
            ':boards' => 'b',
        ]);

        return (int) $pdo->lastInsertId();
    }

    private function insertBan(string $ip, string $reason): int
    {
        global $pdo;

        $statement = prepare(
            'INSERT INTO ``bans``
             (`ipstart`, `ipend`, `created`, `expires`, `board`, `creator`, `reason`, `seen`, `post`)
             VALUES (:ipstart, NULL, :created, NULL, :board, 1, :reason, 0, NULL)',
        );
        $statement->bindValue(':ipstart', inet_pton($ip), PDO::PARAM_LOB);
        $statement->bindValue(':created', time(), PDO::PARAM_INT);
        $statement->bindValue(':board', 'b');
        $statement->bindValue(':reason', $reason);
        $statement->execute();

        return (int) $pdo->lastInsertId();
    }

    private function insertAppeal(int $banId, ?string $post): int
    {
        global $pdo;

        if ($post !== null) {
            query("UPDATE ``bans`` SET `post` = " . $pdo->quote($post) . " WHERE `id` = {$banId}");
        }
        $statement = prepare('INSERT INTO ``ban_appeals`` VALUES (NULL, :ban, :time, :message, 0)');
        $statement->execute([
            ':ban' => $banId,
            ':time' => time(),
            ':message' => 'integration appeal',
        ]);

        return (int) $pdo->lastInsertId();
    }

    private function capture(callable $action): string
    {
        ob_start();
        try {
            $action();
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
