<?php

declare(strict_types=1);

namespace DevichanE2E\Support;

use PDO;

trait AttachmentCleanup
{
    private array $attachmentPostIds = [];

    public function _after(HttpTester|BrowserTester $I): void
    {
        if (!$this->attachmentPostIds) return;
        $db = new PDO(getenv('E2E_DB_DSN'), getenv('E2E_DB_USER'), getenv('E2E_DB_PASSWORD'));
        foreach ($this->attachmentPostIds as $id) {
            $query = $db->prepare('SELECT files FROM posts_b WHERE id = ?');
            $query->execute([$id]);
            foreach (json_decode((string) $query->fetchColumn(), true) ?: [] as $file) {
                foreach (['file' => 'src', 'thumb' => 'thumb'] as $key => $dir) {
                    $path = 'b/' . $dir . '/' . ($file[$key] ?? '');
                    if (is_file($path)) unlink($path);
                }
            }
            // posts_b has no primary key, so Codeception cannot clean up edited rows.
            $db->prepare('DELETE FROM posts_b WHERE id = ?')->execute([$id]);
        }
        $this->attachmentPostIds = [];
        $I->amOnPage('/mod.php?/b/edit/1');
        $I->click('input[name="post"]');
    }
}
