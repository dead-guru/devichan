<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\AdminSession;
use DevichanE2E\Support\HttpAssertions;
use DevichanE2E\Support\HttpTester;

final class ModeratorIpAndReportCest
{
    use AdminSession;
    use HttpAssertions;

    public function administratorCanDismissEveryReportFromAnIp(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $reportIp = '198.51.100.55';

        $firstReport = $I->haveInDatabase('reports', [
            'time' => time(),
            'ip' => $reportIp,
            'board' => 'b',
            'post' => 1,
            'reason' => 'E2E first bulk report',
        ]);
        $secondReport = $I->haveInDatabase('reports', [
            'time' => time(),
            'ip' => $reportIp,
            'board' => 'b',
            'post' => 2,
            'reason' => 'E2E second bulk report',
        ]);

        $I->amOnPage('/mod.php?/reports');
        $dismissAllUrl = $I->grabAttributeFrom(
            'a[href*="reports/' . $firstReport . '/dismissall/"]',
            'href',
        );
        $I->amOnPage($dismissAllUrl);
        $this->assertHealthyPage($I);
        $I->dontSeeInDatabase('reports', ['id' => $firstReport]);
        $I->dontSeeInDatabase('reports', ['id' => $secondReport]);
    }

    public function administratorCanRemoveAnIpNote(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $ip = '198.51.100.56';
        $noteId = $I->haveInDatabase('ip_notes', [
            'ip' => $ip,
            'mod' => 1,
            'time' => time(),
            'body' => 'E2E removable note',
        ]);

        $I->amOnPage('/mod.php?/IP/' . $ip);
        $I->click('a[href*="remove_note/' . $noteId . '"]');
        $this->followConfirmation($I);
        $I->dontSeeInDatabase('ip_notes', ['id' => $noteId]);
    }

    public function administratorCanRemoveATelegram(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $ip = '198.51.100.57';
        $telegramId = $I->haveInDatabase('telegrams', [
            'mod_id' => 1,
            'ip' => $ip,
            'message' => 'E2E removable telegram',
            'seen' => 0,
            'created_at' => time(),
        ]);

        $I->amOnPage('/mod.php?/IP/' . $ip);
        $I->click('a[href*="remove_telegram/' . $telegramId . '"]');
        $this->followConfirmation($I);
        $I->dontSeeInDatabase('telegrams', ['id' => $telegramId]);
    }

    private function followConfirmation(HttpTester $I): void
    {
        $this->assertHealthyPage($I);
        $confirmUrl = $I->grabAttributeFrom('a[href^="?/IP/"][href*="/remove_"]', 'href');
        $I->amOnPage($confirmUrl);
        $this->assertHealthyPage($I);
    }
}
