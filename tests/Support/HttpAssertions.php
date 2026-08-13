<?php

declare(strict_types=1);

namespace DevichanE2E\Support;

use Codeception\Actor;

trait HttpAssertions
{
    private function assertHealthyPage(Actor $I): void
    {
        $I->seeResponseCodeIs(200);
        $I->dontSeeInSource('Caught fatal error');
        $I->dontSeeInSource('Помилка бази даних');
        $I->dontSeeInSource('Stack trace:');
        $I->dontSeeInSource('<title>Error</title>');
    }
}
