<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\AdminSession;
use DevichanE2E\Support\HttpAssertions;
use DevichanE2E\Support\HttpTester;

final class ModeratorBanFormatsCest
{
    use AdminSession;
    use HttpAssertions;

    public function administratorCanBanIpv4AndIpv6Ranges(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $banIds = [];

        foreach ([
            ['198.51.101.*', '', 'E2E IPv4 wildcard ban'],
            ['198.51.102.0/24', '3600', 'E2E IPv4 CIDR ban'],
            ['2001:db8:1234::/64', '2 days', 'E2E IPv6 CIDR ban'],
            ['2001:db8:1234::42', '1 week', 'E2E IPv6 address ban'],
        ] as [$ip, $length, $reason]) {
            $I->amOnPage('/mod.php?/ban');
            $I->submitForm('form[action="?/ban"]', [
                'ip' => $ip,
                'reason' => $reason,
                'length' => $length,
                'board' => '*',
                'new_ban' => 'New Ban',
            ]);
            $this->assertHealthyPage($I);
            $banIds[] = (int) $I->grabFromDatabase('bans', 'id', ['reason' => $reason]);
        }

        $I->amOnPage('/mod.php?/bans');
        foreach ($banIds as $banId) {
            $I->assertGreaterThan(1, $banId);
        }

        $selectedBans = ['unban' => 'Unban selected'];
        foreach ($banIds as $banId) {
            $selectedBans['ban_' . $banId] = 'on';
        }
        $I->submitForm('form.banform', $selectedBans);
        $this->assertHealthyPage($I);

        foreach ($banIds as $banId) {
            $I->dontSeeInDatabase('bans', ['id' => $banId]);
        }
    }

    public function invalidBanRangeIsRejectedWithoutAFatalError(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $I->amOnPage('/mod.php?/ban');
        $I->submitForm('form[action="?/ban"]', [
            'ip' => '198.51.100.0/33',
            'reason' => 'E2E invalid range',
            'length' => '1 day',
            'board' => '*',
            'new_ban' => 'New Ban',
        ]);

        $I->seeResponseCodeIs(400);
        $I->seeInSource('<title>Error</title>');
        $I->dontSeeInSource('Caught fatal error');
        $I->dontSeeInDatabase('bans', ['reason' => 'E2E invalid range']);
    }
}
