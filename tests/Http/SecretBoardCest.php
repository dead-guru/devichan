<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\HttpAssertions;
use DevichanE2E\Support\HttpTester;

final class SecretBoardCest
{
    use HttpAssertions;

    public function secretBoardRequiresAndAcceptsItsPassword(HttpTester $I): void
    {
        $I->amOnPage('/sec/');
        $I->seeInCurrentUrl('/auth/login');
        $I->seeElement('form input[name=password]');

        $I->amOnPage('/auth/check?board=sec');
        $I->seeResponseCodeIs(401);

        $I->amOnPage('/auth/login?board=sec&redirect=/sec/');
        $I->submitForm('form', [
            'board' => 'sec',
            'password' => 'wrong-password',
            'redirect' => '/sec/',
        ]);
        $this->assertHealthyPage($I);
        $I->see('Invalid password');

        $I->submitForm('form', [
            'board' => 'sec',
            'password' => 'secret',
            'redirect' => '/sec/',
        ]);

        $I->seeInCurrentUrl('/sec/');
        $this->assertHealthyPage($I);
        $I->see('Secret board content');

        $I->amOnPage('/auth/check?board=sec');
        $I->seeResponseCodeIs(200);
    }

    public function publicBoardsDoNotRequireSecretBoardAuthentication(HttpTester $I): void
    {
        $I->amOnPage('/auth/check?board=b');
        $I->seeResponseCodeIs(200);
    }
}
