<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\AdminSession;
use DevichanE2E\Support\HttpAssertions;
use DevichanE2E\Support\HttpTester;

final class ModeratorAccountsAndMessagingCest
{
    use AdminSession;
    use HttpAssertions;

    public function administratorCanManageAUserAndPrivateMessage(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $username = 'e2e-moderator';

        $I->amOnPage('/mod.php?/users/new');
        $I->submitForm('form[action="?/users/new"]', [
            'username' => $username,
            'password' => 'e2e-password',
            'type' => '10',
            'allboards' => 'on',
        ]);
        $this->assertHealthyPage($I);
        $userId = (int) $I->grabFromDatabase('mods', 'id', ['username' => $username]);
        $I->assertGreaterThan(1, $userId);

        $I->amOnPage('/mod.php?/new_PM/' . $username);
        $I->submitForm('form[action="?/new_PM/' . $username . '"]', [
            'message' => 'E2E private message',
        ]);
        $this->assertHealthyPage($I);
        $messageId = (int) $I->grabFromDatabase('pms', 'id', [
            'to' => $userId,
            'sender' => 1,
        ]);
        $I->assertGreaterThan(1, $messageId);

        $I->amOnPage('/mod.php?/PM/' . $messageId);
        $this->assertHealthyPage($I);
        $I->see('E2E private message');
        $I->submitForm('form[action=""]', ['delete' => 'Delete forever']);
        $I->dontSeeInDatabase('pms', ['id' => $messageId]);

        $I->amOnPage('/mod.php?/users');
        $promoteUrl = $I->grabAttributeFrom(
            'a[href*="users/' . $userId . '/promote/"]',
            'href',
        );
        $I->amOnPage($promoteUrl);
        $I->seeInDatabase('mods', ['id' => $userId, 'type' => 20]);

        $I->amOnPage('/mod.php?/users');
        $demoteUrl = $I->grabAttributeFrom(
            'a[href*="users/' . $userId . '/demote/"]',
            'href',
        );
        $I->amOnPage($demoteUrl);
        $I->seeInDatabase('mods', ['id' => $userId, 'type' => 10]);

        $I->amOnPage('/mod.php?/users/' . $userId);
        $I->uncheckOption('#allboards');
        $I->checkOption('#board_b');
        $I->fillField('input[name="username"]', $username . '-renamed');
        $I->fillField('input[name="password"]', 'e2e-new-password');
        $I->click('form[action="?/users/' . $userId . '"] input[type="submit"]:not([name="delete"])');
        $this->assertHealthyPage($I);
        $I->seeInDatabase('mods', [
            'id' => $userId,
            'username' => $username . '-renamed',
            'boards' => 'b',
        ]);

        $I->amOnPage('/mod.php?/users/' . $userId);
        $I->submitForm('form[action="?/users/' . $userId . '"]', [
            'username' => $username . '-renamed',
            'password' => '',
            'board_b' => 'on',
            'delete' => 'Delete user',
        ]);
        $I->dontSeeInDatabase('mods', ['id' => $userId]);
    }
}
