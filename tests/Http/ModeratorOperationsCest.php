<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\AdminSession;
use DevichanE2E\Support\HttpAssertions;
use DevichanE2E\Support\HttpTester;

final class ModeratorOperationsCest
{
    use AdminSession;
    use HttpAssertions;

    public function administratorCanDismissReports(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $I->amOnPage('/mod.php?/reports');
        $dismissUrl = $I->grabAttributeFrom(
            'a[href*="reports/1/dismiss/"]',
            'href',
        );
        $I->amOnPage($dismissUrl);
        $this->assertHealthyPage($I);
        $I->dontSeeInDatabase('reports', ['id' => 1]);
    }

    public function administratorCanAddIpNotesAndTelegrams(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $ip = '127.0.0.10';

        $I->amOnPage('/mod.php?/IP/' . $ip);
        $I->submitForm('#notes form', ['note' => 'E2E IP note']);
        $this->assertHealthyPage($I);
        $I->seeInDatabase('ip_notes', [
            'ip' => $ip,
            'mod' => 1,
            'body' => 'E2E IP note',
        ]);

        $I->amOnPage('/mod.php?/IP/' . $ip);
        $I->submitForm('#telegrams form', ['telegram' => 'E2E telegram']);
        $this->assertHealthyPage($I);
        $I->seeInDatabase('telegrams', [
            'ip' => $ip,
            'mod_id' => 1,
            'message' => 'E2E telegram',
            'seen' => 0,
        ]);
    }

    public function administratorCanCreateEditAndRemoveBans(HttpTester $I): void
    {
        $this->loginAsAdmin($I);

        $I->amOnPage('/mod.php?/ban');
        $I->submitForm('form[action="?/ban"]', [
            'ip' => '198.51.100.20',
            'reason' => 'E2E initial ban',
            'length' => '1 day',
            'board' => 'b',
            'new_ban' => 'New Ban',
        ]);
        $this->assertHealthyPage($I);
        $banId = (int) $I->grabFromDatabase('bans', 'id', [
            'reason' => 'E2E initial ban',
        ]);
        $I->assertGreaterThan(1, $banId);

        $I->amOnPage('/mod.php?/edit_ban/' . $banId);
        $I->submitForm('form[action=""]', [
            'reason' => 'E2E edited ban',
            'length' => '2 days',
            'board' => '*',
            'new_ban' => 'Edit Ban',
        ]);
        $this->assertHealthyPage($I);
        $editedBanId = (int) $I->grabFromDatabase('bans', 'id', [
            'reason' => 'E2E edited ban',
        ]);
        $I->assertGreaterThan(1, $editedBanId);
        $I->dontSeeInDatabase('bans', ['id' => $banId]);

        $I->amOnPage('/mod.php?/bans');
        $I->submitForm('form.banform', [
            'ban_' . $editedBanId => 'on',
            'unban' => 'Unban selected',
        ]);
        $this->assertHealthyPage($I);
        $I->dontSeeInDatabase('bans', ['id' => $editedBanId]);
    }

    public function administratorCanResolveBanAppeals(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $I->amOnPage('/mod.php?/ban-appeals');
        $I->submitForm('form[action=""]', [
            'appeal_id' => 1,
            'deny' => 'Deny appeal',
        ]);
        $this->assertHealthyPage($I);
        $I->seeInDatabase('ban_appeals', ['id' => 1, 'denied' => 1]);
    }

    public function administratorCanRebuildGeneratedContent(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $I->amOnPage('/mod.php?/rebuild');
        $I->submitForm('form[action="?/rebuild"]', [
            'rebuild' => 'Rebuild',
            'rebuild_javascript' => 'on',
            'rebuild_index' => 'on',
            'rebuild_thread' => 'on',
            'rebuild_themes' => 'on',
            'boards_all' => 'on',
        ]);
        $this->assertHealthyPage($I);
        $I->see('Creating index pages');
    }

    public function administratorCanConfigureAndRebuildThemes(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $I->amOnPage('/mod.php?/themes/catalog');
        $I->submitForm('form[action=""]', [
            'title' => 'Catalog',
            'boards' => 'b',
            'update_on_posts' => 'on',
            'use_tooltipster' => 'on',
            'install' => 'Install theme',
        ]);
        $this->assertHealthyPage($I);
        $I->seeInDatabase('theme_settings', [
            'theme' => 'catalog',
            'name' => 'boards',
            'value' => 'b',
        ]);

        $I->amOnPage('/mod.php?/themes');
        $rebuildUrl = $I->grabAttributeFrom(
            'a[href*="themes/catalog/rebuild/"]',
            'href',
        );
        $I->amOnPage($rebuildUrl);
        $this->assertHealthyPage($I);
    }
}
