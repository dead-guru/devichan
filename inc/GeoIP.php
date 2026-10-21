<?php

declare(strict_types=1);

namespace DeVichan;

use MaxMind\Db\Reader;
use MaxMind\Db\Reader\InvalidDatabaseException;

final class GeoIP
{
    private static ?Reader $reader = null;
    private static ?string $path = null;
    private static array $cache = [];
    private static bool $reported = false;

    /** @return array{code: string, name: string}|null */
    public static function country(string $ip): ?array
    {
        global $config;

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        $packed = inet_pton($ip);
        if (strlen($packed) === 16 && substr($packed, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
            $packed = substr($packed, 12);
        }
        $ip = inet_ntop($packed);
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE)) {
            return null;
        }

        $path = $config['geoip_database'];
        if (!str_starts_with($path, '/')) {
            $path = dirname(__DIR__) . '/' . $path;
        }

        try {
            if (self::$path !== $path) {
                self::$reader?->close();
                self::$reader = null;
                self::$path = $path;
                self::$cache = [];
                self::$reader = new Reader($path);
            }

            if (self::$reader === null) {
                return null;
            }
            if (array_key_exists($ip, self::$cache)) {
                return self::$cache[$ip];
            }

            $record = self::$reader->get($ip);
        } catch (\InvalidArgumentException | \RuntimeException | InvalidDatabaseException $e) {
            self::$reader?->close();
            self::$reader = null;
            if (!self::$reported) {
                error_log('GeoIP country lookup failed: ' . $e->getMessage());
                self::$reported = true;
            }
            return null;
        }

        $country = is_array($record) && isset($record['country']) && is_array($record['country'])
            ? $record['country'] : [];
        $code = $country['iso_code'] ?? null;
        $result = null;
        if (is_string($code) && preg_match('/^[A-Z]{2}$/D', $code) && !in_array($code, ['ZZ', 'EU', 'AP'], true)) {
            $name = is_array($country['names'] ?? null) ? ($country['names']['en'] ?? null) : null;
            $result = ['code' => strtolower($code), 'name' => is_string($name) && $name !== '' ? $name : $code];
        }

        if (count(self::$cache) >= 1024) {
            self::$cache = [];
        }
        return self::$cache[$ip] = $result;
    }
}
