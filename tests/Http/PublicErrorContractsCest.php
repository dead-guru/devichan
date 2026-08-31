<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\HttpAssertions;
use DevichanE2E\Support\HttpTester;

final class PublicErrorContractsCest
{
    use HttpAssertions;

    public function unknownSecretBoardIsRejected(HttpTester $I): void
    {
        $I->amOnPage('/auth/login?board=missing');
        $I->seeResponseCodeIs(404);
        $I->seeElement('body');
    }

    public function jsonErrorsUseTheMappedStatusAndBody(HttpTester $I): void
    {
        $I->sendAjaxPostRequest('/post.php', [
            'post' => 'New Topic',
            'json_response' => '1',
        ]);

        $I->seeResponseCodeIs(400);
        $I->assertIsArray(json_decode(
            $I->grabPageSource(),
            true,
            flags: JSON_THROW_ON_ERROR,
        ));
    }

    public function incompleteReportRequestReturnsBadRequest(HttpTester $I): void
    {
        $I->amOnPage('/report/');
        $I->seeResponseCodeIs(400);
        $I->seeElement('body');
    }

    public function reportForUnknownBoardReturnsBadRequest(HttpTester $I): void
    {
        $I->amOnPage('/report/?board=missing&post=delete_1');
        $I->seeResponseCodeIs(400);
        $I->seeElement('body');
    }

    public function logForUnknownBoardReturnsNotFound(HttpTester $I): void
    {
        $I->amOnPage('/log/?board=missing');
        $I->seeResponseCodeIs(404);
        $I->seeElement('body');
    }

    public function externalThreadApiDoesNotExposeSecretBoards(HttpTester $I): void
    {
        $I->amOnPage('/js/outside/thread.php?board=sec&thread=1');
        $I->seeResponseCodeIs(404);
        $I->assertIsArray(json_decode(
            $I->grabPageSource(),
            true,
            flags: JSON_THROW_ON_ERROR,
        ));
    }

    public function searchHandlesEmptyBroadAndMissingResults(HttpTester $I): void
    {
        $I->amOnPage('/search/');
        $this->assertHealthyPage($I);
        $I->seeElement('form');

        $I->amOnPage('/search/?board=b&search=*');
        $this->assertHealthyPage($I);
        $I->see('Query too broad');

        $I->amOnPage('/search/?board=b&search=' . rawurlencode('definitely absent e2e text'));
        $this->assertHealthyPage($I);
        $I->see('No results found');
    }

    public function randomNotFoundPageRenders(HttpTester $I): void
    {
        $I->amOnPage('/index.php');
        $this->assertHealthyPage($I);
        $I->see('404');
        $I->seeElement('img[src^="/static/404/"]');
    }
}
