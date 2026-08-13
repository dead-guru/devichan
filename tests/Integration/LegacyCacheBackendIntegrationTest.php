<?php

declare(strict_types=1);

namespace {
    if (!class_exists('Memcached')) {
        final class Memcached
        {
            private array $values = [];

            public function addServers(array $servers): bool
            {
                return $servers !== [];
            }

            public function get(string $key): mixed
            {
                return $this->values[$key] ?? false;
            }

            public function set(string $key, mixed $value, int $expires): bool
            {
                $this->values[$key] = $value;
                return $expires > 0;
            }

            public function delete(string $key): bool
            {
                unset($this->values[$key]);
                return true;
            }

            public function flush(): bool
            {
                $this->values = [];
                return true;
            }
        }
    }

    $e2eLegacyCaches = ['apc' => [], 'apcu' => [], 'xcache' => []];

    if (!function_exists('apc_fetch')) {
        function apc_fetch(string $key): mixed
        {
            global $e2eLegacyCaches;
            return $e2eLegacyCaches['apc'][$key] ?? false;
        }

        function apc_store(string $key, mixed $value, int $expires): bool
        {
            global $e2eLegacyCaches;
            $e2eLegacyCaches['apc'][$key] = $value;
            return $expires > 0;
        }

        function apc_delete(string $key): bool
        {
            global $e2eLegacyCaches;
            unset($e2eLegacyCaches['apc'][$key]);
            return true;
        }

        function apc_clear_cache(string $type): bool
        {
            global $e2eLegacyCaches;
            $e2eLegacyCaches['apc'] = [];
            return $type === 'user';
        }
    }

    if (!function_exists('apcu_fetch')) {
        function apcu_fetch(string $key): mixed
        {
            global $e2eLegacyCaches;
            return $e2eLegacyCaches['apcu'][$key] ?? false;
        }

        function apcu_store(string $key, mixed $value, int $expires): bool
        {
            global $e2eLegacyCaches;
            $e2eLegacyCaches['apcu'][$key] = $value;
            return $expires > 0;
        }

        function apcu_delete(string $key): bool
        {
            global $e2eLegacyCaches;
            unset($e2eLegacyCaches['apcu'][$key]);
            return true;
        }

        function apcu_clear_cache(string $type): bool
        {
            global $e2eLegacyCaches;
            $e2eLegacyCaches['apcu'] = [];
            return $type === 'user';
        }
    }

    if (!function_exists('xcache_get')) {
        function xcache_get(string $key): mixed
        {
            global $e2eLegacyCaches;
            return $e2eLegacyCaches['xcache'][$key] ?? false;
        }

        function xcache_set(string $key, mixed $value, int $expires): bool
        {
            global $e2eLegacyCaches;
            $e2eLegacyCaches['xcache'][$key] = $value;
            return $expires > 0;
        }

        function xcache_unset(string $key): bool
        {
            global $e2eLegacyCaches;
            unset($e2eLegacyCaches['xcache'][$key]);
            return true;
        }
    }
}

namespace DevichanE2E\Integration {
    use PHPUnit\Framework\TestCase;

    final class LegacyCacheBackendIntegrationTest extends TestCase
    {
        private array $originalConfig;

        protected function setUp(): void
        {
            global $config;

            $this->originalConfig = $config;
            $config['cache']['prefix'] = 'e2e-adapter-';
            $config['cache']['timeout'] = 60;
            $config['cache']['memcached'] = [['memcached', 11211]];
            $config['cache']['redis'] = ['credis', 6379, 'devichan_e2e', 1];
            $config['debug'] = false;
        }

        protected function tearDown(): void
        {
            global $config;

            $config = $this->originalConfig;
        }

        public function testOptionalCacheAdapterContractsRoundTripValues(): void
        {
            global $config;

            foreach (['memcached', 'apc', 'apcu', 'xcache'] as $backend) {
                $config['cache']['enabled'] = $backend;
                \Cache::init();
                self::assertFalse(\Cache::get('missing'));
                \Cache::set('key', ['backend' => $backend], 30);
                self::assertSame(['backend' => $backend], \Cache::get('key'));
                \Cache::delete('key');
                self::assertFalse(\Cache::get('key'));

                if ($backend === 'xcache') {
                    self::assertFalse(\Cache::flush());
                } else {
                    self::assertTrue(\Cache::flush());
                }
            }
        }

        public function testNetworkCacheAdaptersInitializeLazilyForEveryOperation(): void
        {
            global $config;

            foreach (['memcached', 'redis'] as $backend) {
                $config['cache']['enabled'] = $backend;

                $this->resetCacheAdapter();
                self::assertSame(
                    $backend === 'redis' ? null : false,
                    \Cache::get('lazy-missing-' . $backend),
                );

                $this->resetCacheAdapter();
                \Cache::set('lazy-set-' . $backend, 'value', 30);

                $this->resetCacheAdapter();
                \Cache::delete('lazy-delete-' . $backend);

                $this->resetCacheAdapter();
                self::assertTrue(\Cache::flush());
            }
        }

        private function resetCacheAdapter(): void
        {
            $property = new \ReflectionProperty(\Cache::class, 'cache');
            $property->setAccessible(true);
            $property->setValue(null, null);
        }
    }
}
