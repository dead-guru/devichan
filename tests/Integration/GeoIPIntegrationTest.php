<?php

declare(strict_types=1);

namespace DevichanE2E\Integration;

use DeVichan\GeoIP;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class GeoIPIntegrationTest extends TestCase
{
    private const DATABASE = 'tests/fixtures/geoip/GeoIP2-Country-Test.mmdb';
    private string $originalDatabase;

    protected function setUp(): void
    {
        global $config;

        $this->originalDatabase = $config['geoip_database'];
        $config['geoip_database'] = dirname(__DIR__, 2) . '/' . self::DATABASE;
    }

    protected function tearDown(): void
    {
        global $config;

        $config['geoip_database'] = $this->originalDatabase;
    }

    #[DataProvider('countries')]
    public function testCountryLookupUsesCountryRatherThanRegisteredCountry(string $ip, array $expected): void
    {
        self::assertSame($expected, GeoIP::country($ip));
    }

    public static function countries(): array
    {
        return [
            'IPv4' => ['81.2.69.142', ['code' => 'gb', 'name' => 'United Kingdom']],
            'IPv6' => ['2001:218::', ['code' => 'jp', 'name' => 'Japan']],
            'mapped IPv4' => ['::ffff:81.2.69.142', ['code' => 'gb', 'name' => 'United Kingdom']],
            'hexadecimal mapped IPv4' => ['::ffff:5102:458e', ['code' => 'gb', 'name' => 'United Kingdom']],
        ];
    }

    public function testUnknownAndNonPublicAddressesHaveNoCountry(): void
    {
        foreach ([
            '8.8.8.8',
            '5.83.124.1',
            '127.0.0.1',
            '10.1.2.3',
            '172.16.0.1',
            '192.168.1.1',
            '169.254.1.1',
            '100.64.1.1',
            '::1',
            'fe80::1',
            'fc00::1',
            '::ffff:192.168.1.1',
            '',
            'not-an-ip',
            '81.2.69.142/32',
            '81.2.69.0/24',
            '2001:218::/128',
            '[2001:218::]',
        ] as $ip) {
            self::assertNull(GeoIP::country($ip), $ip);
        }
    }

    public function testRelativeDatabasePathUsesProjectRoot(): void
    {
        global $config;

        $config['geoip_database'] = self::DATABASE;
        $cwd = getcwd();
        chdir('tests');
        try {
            self::assertSame(['code' => 'jp', 'name' => 'Japan'], GeoIP::country('2001:218::'));
        } finally {
            chdir($cwd);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDatabaseErrorsAreLoggedOnceAndChangingPathRecovers(): void
    {
        global $config;

        $directory = 'tests/_output/geoip-' . bin2hex(random_bytes(6));
        mkdir($directory, 0777, true);
        $errorLog = ini_get('error_log');
        ini_set('error_log', getcwd() . '/' . $directory . '/errors.log');

        try {
            self::assertNull(GeoIP::country('8.8.8.8'));
            self::assertNull(GeoIP::country('5.83.124.1'));
            self::assertFileDoesNotExist($directory . '/errors.log');
            self::assertSame(['code' => 'gb', 'name' => 'United Kingdom'], GeoIP::country('81.2.69.142'));

            $config['geoip_database'] = $directory . '/missing.mmdb';
            self::assertNull(GeoIP::country('81.2.69.142'));
            self::assertNull(GeoIP::country('2001:218::'));

            file_put_contents($directory . '/corrupt.mmdb', 'not an MMDB database');
            $config['geoip_database'] = $directory . '/corrupt.mmdb';
            self::assertNull(GeoIP::country('81.2.69.142'));
            self::assertSame(1, substr_count(file_get_contents($directory . '/errors.log'), 'GeoIP country lookup failed:'));

            $config['geoip_database'] = self::DATABASE;
            self::assertSame(['code' => 'gb', 'name' => 'United Kingdom'], GeoIP::country('81.2.69.142'));
        } finally {
            ini_set('error_log', $errorLog);
            \rrmdir($directory);
        }
    }
}
