<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\HttpAssertions;
use DevichanE2E\Support\HttpTester;

final class SearchAndStatsCest
{
    use HttpAssertions;

    public function searchFindsPostsAndSupportsFilters(HttpTester $I): void
    {
        foreach (['Seed public', 'id:1'] as $query) {
            $I->amOnPage('/search/?board=b&search=' . rawurlencode($query));
            $this->assertHealthyPage($I);
            $I->see('Seed public thread');
        }
    }

    public function statisticsAndPublicModerationLogRender(HttpTester $I): void
    {
        $I->amOnPage('/stats/');
        $this->assertHealthyPage($I);
        $I->see('/b/');

        $I->amOnPage('/log/?board=b');
        $this->assertHealthyPage($I);
    }
}
