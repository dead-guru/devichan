<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\AdminSession;
use DevichanE2E\Support\HttpAssertions;
use DevichanE2E\Support\HttpTester;

final class ModeratorSessionCest
{
    use AdminSession;
    use HttpAssertions;

    public function administratorCanLogOut(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $logoutUrl = $I->grabAttributeFrom('a[href*="?/logout/"]', 'href');
        $I->amOnPage($logoutUrl);
        $this->assertHealthyPage($I);
        $I->seeElement('form input[name="username"]');
        $I->seeElement('form input[name="password"]');
    }
}
