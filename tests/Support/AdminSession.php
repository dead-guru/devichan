<?php

declare(strict_types=1);

namespace DevichanE2E\Support;

trait AdminSession
{
    private function loginAsAdmin(HttpTester $I): void
    {
        $I->amOnPage('/mod/');
        $I->submitForm('form', [
            'username' => 'admin',
            'password' => 'password',
            'login' => 'Continue',
        ]);
        $I->seeResponseCodeIs(200);
        $I->seeElement('body.is-moderator');
    }
}
