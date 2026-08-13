<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\AdminSession;
use DevichanE2E\Support\HttpAssertions;
use DevichanE2E\Support\HttpTester;

final class ModeratorContentManagementCest
{
    use AdminSession;
    use HttpAssertions;

    public function administratorCanPublishAndDeleteNews(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $subject = 'E2E news lifecycle';

        $I->amOnPage('/mod.php?/edit_news');
        $I->submitForm('form[action=""]', [
            'name' => 'E2E admin',
            'subject' => $subject,
            'body' => 'Published by the integration suite.',
        ]);
        $this->assertHealthyPage($I);
        $newsId = (int) $I->grabFromDatabase('news', 'id', ['subject' => $subject]);
        $I->assertGreaterThan(1, $newsId);

        $I->amOnPage('/');
        $this->assertHealthyPage($I);
        $I->see($subject);

        $I->amOnPage('/mod.php?/edit_news');
        $deleteUrl = $I->grabAttributeFrom(
            'a[href*="edit_news/delete/' . $newsId . '/"]',
            'href',
        );
        $I->amOnPage($deleteUrl);
        $I->dontSeeInDatabase('news', ['id' => $newsId]);
    }

    public function administratorCanPublishAndDeleteNoticeboardEntries(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $subject = 'E2E notice lifecycle';

        $I->amOnPage('/mod.php?/noticeboard');
        $I->submitForm('form[action="?/noticeboard"]', [
            'subject' => $subject,
            'body' => 'Visible to the moderator team.',
        ]);
        $this->assertHealthyPage($I);
        $noticeId = (int) $I->grabFromDatabase('noticeboard', 'id', [
            'subject' => $subject,
        ]);
        $I->assertGreaterThan(1, $noticeId);

        $deleteUrl = $I->grabAttributeFrom(
            'a[href*="noticeboard/delete/' . $noticeId . '/"]',
            'href',
        );
        $I->amOnPage($deleteUrl);
        $I->dontSeeInDatabase('noticeboard', ['id' => $noticeId]);
    }

    public function administratorCanCreateEditRenderAndDeleteStaticPages(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $pageName = 'e2epage';

        $I->amOnPage('/mod.php?/edit_pages');
        $I->submitForm('form[method="POST"]', [
            'page' => $pageName,
            'title' => 'E2E static page',
        ]);
        $this->assertHealthyPage($I);
        $pageId = (int) $I->grabFromDatabase('pages', 'id', ['name' => $pageName]);
        $I->assertGreaterThan(1, $pageId);

        $I->amOnPage('/mod.php?/edit_page/' . $pageId);
        $I->submitForm('form[method="POST"]', [
            'method' => 'html',
            'content' => '<h2>E2E rendered page</h2>',
        ]);
        $this->assertHealthyPage($I);

        $I->amOnPage('/' . $pageName . '.html');
        $this->assertHealthyPage($I);
        $I->see('E2E rendered page');

        $I->amOnPage('/mod.php?/edit_pages');
        $deleteUrl = $I->grabAttributeFrom(
            'a[href*="edit_pages/delete/' . $pageName . '/"]',
            'href',
        );
        $I->amOnPage($deleteUrl);
        $I->dontSeeInDatabase('pages', ['id' => $pageId]);
    }

    public function markdownStaticPagesRender(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $pageName = 'e2emarkdown' . bin2hex(random_bytes(3));

        $I->amOnPage('/mod.php?/edit_pages');
        $I->submitForm('form[method="POST"]', [
            'page' => $pageName,
            'title' => 'E2E Markdown page',
        ]);
        $this->assertHealthyPage($I);
        $pageId = (int) $I->grabFromDatabase('pages', 'id', ['name' => $pageName]);

        $I->amOnPage('/mod.php?/edit_page/' . $pageId);
        $I->submitForm('form[method="POST"]', [
            'method' => 'markdown',
            'content' => '# E2E Markdown rendered page',
        ]);
        $this->assertHealthyPage($I);

        $I->amOnPage('/' . $pageName . '.html');
        $this->assertHealthyPage($I);
        $I->see('E2E Markdown rendered page');
    }
}
