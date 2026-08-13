<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\AdminSession;
use DevichanE2E\Support\HttpAssertions;
use DevichanE2E\Support\HttpTester;

final class VisitorBanAppealCest
{
    use AdminSession;
    use HttpAssertions;

    private ?int $activeBanId = null;

    public function _after(HttpTester $I): void
    {
        if ($this->activeBanId === null) {
            return;
        }

        $I->updateInDatabase('bans', [
            'expires' => time() - 1,
            'seen' => 1,
        ], ['id' => $this->activeBanId]);
    }

    public function visitorCanSeeAndAppealTheirBan(HttpTester $I): void
    {
        $visitorIp = '198.51.100.90';
        $I->haveHttpHeader('CF-Connecting-IP', $visitorIp);
        $probeBody = 'E2E ban address probe ' . bin2hex(random_bytes(4));
        $I->sendAjaxPostRequest('/post.php', [
            'api' => 'e2e-api-key',
            'json_response' => '1',
            'board' => 'b',
            'body' => $probeBody,
            'password' => 'e2e-ban-probe',
            'embed' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
        $I->seeResponseCodeIs(200);
        $I->seeInDatabase('posts_b', [
            'body_nomarkup' => $probeBody,
            'ip' => $visitorIp,
        ]);

        $this->loginAsAdmin($I);
        $reason = 'E2E visitor appeal flow ' . bin2hex(random_bytes(4));
        $I->amOnPage('/mod.php?/ban');
        $I->submitForm('form[action="?/ban"]', [
            'ip' => $visitorIp,
            'reason' => $reason,
            'length' => '1 day',
            'board' => 'b',
            'new_ban' => 'New Ban',
        ]);
        $this->assertHealthyPage($I);
        $this->activeBanId = (int) $I->grabFromDatabase('bans', 'id', [
            'reason' => $reason,
        ]);

        $I->resetCookie('mod');
        $I->amOnPage('/b/');
        $postButton = $I->grabAttributeFrom(
            'form[name="post"] input[name="post"]',
            'value',
        );
        $I->submitForm('form[name="post"]', [
            'board' => 'b',
            'body' => 'E2E blocked visitor post',
            'password' => 'e2e-blocked',
            'embed' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'e2e_ban_appeals' => '1',
            'post' => $postButton,
        ]);
        $this->assertHealthyPage($I);
        $I->see($reason);
        $I->seeElement('form.ban-appeal');

        $appeal = 'E2E visitor asks for review';
        $I->submitForm('form.ban-appeal', [
            'ban_id' => $this->activeBanId,
            'appeal' => $appeal,
            'e2e_ban_appeals' => '1',
        ]);
        $this->assertHealthyPage($I);
        $I->seeInDatabase('ban_appeals', [
            'ban_id' => $this->activeBanId,
            'message' => $appeal,
            'denied' => 0,
        ]);

        $this->loginAsAdmin($I);
        $I->amOnPage('/mod.php?/bans');
        $I->submitForm('form.banform', [
            'ban_' . $this->activeBanId => 'on',
            'unban' => 'Unban selected',
        ]);
        $this->assertHealthyPage($I);
        $I->dontSeeInDatabase('bans', ['id' => $this->activeBanId]);
        $this->activeBanId = null;
    }
}
