<?php

declare(strict_types=1);

namespace DevichanE2E\Integration;

use PHPUnit\Framework\TestCase;

final class PostLifecycleIntegrationTest extends TestCase
{
    private array $createdPostIds = [];
    private array $originalConfig;

    protected function setUp(): void
    {
        global $config;

        $this->originalConfig = $config;
        self::assertTrue(openBoard('b'));
    }

    protected function tearDown(): void
    {
        global $config;

        foreach (array_reverse($this->createdPostIds) as $postId) {
            if ($this->postExists($postId)) {
                deletePost($postId, false, false);
            }
        }
        $config = $this->originalConfig;
        openBoard('b');
    }

    public function testThreadAndReplyLifecycleBuildsAndDeletesRealContent(): void
    {
        global $config;

        $config['generation_strategies'] = ['strategy_immediate'];
        $config['try_smarter'] = false;
        $config['purge'] = [];
        $config['referer_match'] = '/^$/';
        $config['minify_css'] = false;

        $threadId = $this->createPost(true, null, [
            'subject' => 'Integration lifecycle',
            'email' => 'noko',
            'trip' => '!trip',
            'capcode' => 'Admin',
            'sticky' => true,
            'locked' => true,
            'cycle' => true,
            'embed' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
        $replyId = $this->createPost(false, $threadId, [
            'body' => 'Integration reply >>' . $threadId,
            'body_nomarkup' => 'Integration reply >>' . $threadId,
        ]);

        self::assertTrue(threadExists($threadId));
        self::assertTrue(threadLocked($threadId));
        self::assertFalse(threadSageLocked($threadId));
        self::assertSame('1', (string) numPosts($threadId)['replies']);

        bumpThread($threadId);
        self::assertNotSame('', buildThread($threadId, true));
        self::assertNotSame('', buildThread50($threadId, true));
        self::assertTrue(rebuildPost($replyId));

        $content = index(1, false, false);
        self::assertIsArray($content);
        self::assertNotEmpty($content['body']);
        self::assertIsArray(index(1, false, true));
        self::assertFalse(index(999, false, false));

        $pages = getPages();
        $pages[0]['selected'] = true;
        $buttons = getPageButtons($pages);
        self::assertArrayHasKey('prev', $buttons);
        self::assertArrayHasKey('next', $buttons);

        self::assertTrue(deletePost($replyId, true, false));
        $this->forgetPost($replyId);
        self::assertFalse($this->postExists($replyId));
        self::assertTrue(deletePost($threadId, true, false));
        $this->forgetPost($threadId);
        self::assertFalse($this->postExists($threadId));
        self::assertFalse(deletePost(999999, false, false));
    }

    public function testFloodRobotMuteHashAndShellHelpersUseRealStorage(): void
    {
        global $config;

        $post = [
            'body_nomarkup' => 'Integration flood body',
            'has_file' => false,
            'op' => true,
        ];
        insertFloodPost($post);
        self::assertFalse(checkSpam(['b', 1]));
        incrementSpamHash('missing-hash');

        $robotBody = 'Unique integration robot ' . bin2hex(random_bytes(4));
        self::assertFalse(checkRobot($robotBody));
        self::assertTrue(checkRobot($robotBody));
        self::assertTrue(checkRobot(''));

        $config['robot_mute_hour'] = 1;
        $config['robot_mute_multiplier'] = 2;
        query("DELETE FROM ``mutes`` WHERE `ip` = '127.0.0.1'");
        self::assertSame(0, muteTime());
        self::assertEquals(2, mute());

        self::assertSame('', shell_exec_error('true'));
        self::assertNotSame(false, shell_exec_error('sh -c "echo failure; exit 1"'));
        self::assertSame('1.0.0.127', ReverseIPOctets('127.0.0.1'));
        self::assertFalse(isIPv6());
    }

    private function createPost(bool $op, ?int $thread, array $overrides = []): int
    {
        $body = 'Integration post ' . bin2hex(random_bytes(4));
        $post = array_merge([
            'op' => $op,
            'thread' => $thread,
            'subject' => '',
            'email' => '',
            'name' => 'Integration',
            'trip' => '',
            'capcode' => false,
            'body' => $body,
            'body_nomarkup' => $body,
            'password' => 'integration',
            'ip' => '127.0.0.1',
            'mod' => true,
            'sticky' => false,
            'locked' => false,
            'cycle' => false,
            'embed' => '',
            'has_file' => false,
            'files' => [],
            'num_files' => 0,
            'filehash' => null,
        ], $overrides);

        $id = (int) post($post);
        $this->createdPostIds[] = $id;

        return $id;
    }

    private function postExists(int $postId): bool
    {
        $query = prepare('SELECT 1 FROM ``posts_b`` WHERE `id` = :id');
        $query->bindValue(':id', $postId, \PDO::PARAM_INT);
        $query->execute();

        return (bool) $query->fetchColumn();
    }

    private function forgetPost(int $postId): void
    {
        $this->createdPostIds = array_values(array_filter(
            $this->createdPostIds,
            static fn(int $id): bool => $id !== $postId,
        ));
    }
}
