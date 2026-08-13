<?php

declare(strict_types=1);

namespace DevichanE2E\Support;

trait PostFixture
{
    private function createPost(
        HttpTester $I,
        string $board,
        string $ip,
        ?int $thread = null,
        ?string $body = null,
    ): int {
        $time = time();
        $body ??= 'E2E database post ' . bin2hex(random_bytes(4));

        return $I->haveInDatabase('posts_' . $board, [
            'thread' => $thread,
            'subject' => $thread === null ? 'E2E fixture thread' : null,
            'email' => null,
            'name' => 'E2E fixture',
            'trip' => null,
            'capcode' => null,
            'body' => $body,
            'body_nomarkup' => $body,
            'time' => $time,
            'bump' => $thread === null ? $time : null,
            'files' => null,
            'num_files' => 0,
            'filehash' => null,
            'password' => 'e2e-fixture',
            'ip' => $ip,
            'sticky' => 0,
            'locked' => 0,
            'cycle' => 0,
            'sage' => 0,
            'embed' => null,
            'slug' => $thread === null ? 'e2e-fixture-thread' : null,
        ]);
    }
}
