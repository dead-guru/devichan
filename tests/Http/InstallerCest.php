<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\HttpAssertions;
use DevichanE2E\Support\HttpTester;

final class InstallerCest
{
    use HttpAssertions;

    public function installedApplicationRejectsReinstallation(HttpTester $I): void
    {
        $I->amOnPage('/install.php');
        $this->assertHealthyPage($I);
        $I->see('Already installed');
        $I->see('5.1.4');
    }

}
