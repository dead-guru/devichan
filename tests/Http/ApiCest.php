<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\HttpTester;

final class ApiCest
{
    public function boardAndThreadApisReturnJson(HttpTester $I): void
    {
        $routes = [
            '/b/0.json',
            '/b/threads.json',
            '/b/catalog.json',
            '/b/res/1.json',
            '/recent.json',
        ];

        foreach ($routes as $route) {
            $I->comment("GET {$route}");
            $I->amOnPage($route);
            $I->seeResponseCodeIs(200);
            $I->assertIsArray(json_decode(
                $I->grabPageSource(),
                true,
                flags: JSON_THROW_ON_ERROR,
            ));
        }
    }
}
