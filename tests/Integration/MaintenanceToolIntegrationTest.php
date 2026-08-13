<?php

declare(strict_types=1);

namespace DevichanE2E\Integration;

use PHPUnit\Framework\TestCase;

final class MaintenanceToolIntegrationTest extends TestCase
{
    public function testCaddyRouteSynchronizerReadsFixtureAndUpdatesTheTestProxy(): void
    {
        global $caddy_url, $config;

        $originalConfig = $config;
        ob_start();
        require 'tools/sync-caddy-routes.php';
        $output = (string) ob_get_clean();

        self::assertStringContainsString('Secret boards: sec', $output);
        self::assertStringContainsString('Done.', $output);
        $route = json_decode(\build_secret_route('private'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('secret_board_private', $route['@id']);
        self::assertSame('/private/*', $route['match'][0]['path'][2]);

        $response = \caddy_request('GET', '/config/apps/http/servers/srv0/routes');
        self::assertSame(200, $response['code']);
        self::assertStringContainsString('secret_board_sec', (string) $response['body']);
        $config = $originalConfig;
    }

    public function testStatisticsAndBumpRecountRunAgainstTheIntegrationDatabase(): void
    {
        global $argv, $config, $mod;

        ob_start();
        require 'tools/stats.php';
        $statistics = (string) ob_get_clean();
        self::assertStringContainsString('hour', $statistics);
        self::assertStringContainsString('b', $statistics);

        $argv = ['tools/recount-bumps.php', 'b'];
        ob_start();
        try {
            require 'tools/recount-bumps.php';
        } finally {
            $recount = (string) ob_get_clean();
        }
        self::assertStringContainsString('Thread ', $recount);
        self::assertStringContainsString('done', $recount);
        self::assertSame(30, $mod['type']);
        self::assertArrayHasKey('version', $config);
    }
}
