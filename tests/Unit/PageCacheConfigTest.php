<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio\Tests\Unit;

use Contenir\Cache\Mezzio\PageCacheConfig;
use Contenir\Cache\Mezzio\Tests\TestAsset\Container\InMemoryContainer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
#[Group('factory')]
final class PageCacheConfigTest extends TestCase
{
    /**
     * @return array<string, array{mixed, int}>
     */
    public static function ttlProvider(): array
    {
        return [
            'integer'                    => [600, 600],
            'numeric string from an env' => ['600', 600],
            'zero'                       => [0, 0],
            'decimal string'             => ['1.5', 300],
            'fractional float'           => [90.5, 300],
            'boolean'                    => [true, 300],
            'word'                       => ['soon', 300],
        ];
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function unconfiguredProvider(): array
    {
        return [
            'no config service'      => [[]],
            'config is not an array' => [['config' => 'nonsense']],
            'no pagecache key'       => [['config' => ['dependencies' => []]]],
            'pagecache not an array' => [['config' => ['pagecache' => true]]],
            'unusable values'        => [[
                'config' => [
                    'pagecache' => [
                        'cache'         => '',
                        'ttl'           => 'ten minutes',
                        'per_item_ttl'  => 'nope',
                        'cache_control' => 42,
                        'mutators'      => 'app.mutator',
                    ],
                ],
            ]],
        ];
    }

    /**
     * @param array<string, mixed> $services
     */
    #[Test]
    #[DataProvider('unconfiguredProvider')]
    public function fallsBackToTheDefaultsWhenNothingIsConfigured(array $services): void
    {
        $config = PageCacheConfig::fromContainer(new InMemoryContainer($services));

        static::assertSame(
            [null, 300, false, 'no-cache', [], null],
            [
                $config->cacheService(),
                $config->ttl(),
                $config->perItemTtl(),
                $config->text('cache_control', 'no-cache'),
                $config->mutators(),
                $config->value('bypass'),
            ],
        );
    }

    #[Test]
    public function readsTheConfiguredSettings(): void
    {
        $config = PageCacheConfig::fromContainer(new InMemoryContainer([
            'config' => [
                'pagecache' => [
                    'cache'         => 'cache.pages',
                    'ttl'           => 600,
                    'per_item_ttl'  => true,
                    'cache_control' => 'public, max-age=60',
                    'mutators'      => ['app.mutator'],
                    'bypass'        => 'app.bypass',
                ],
            ],
        ]));

        static::assertSame(
            ['cache.pages', 600, true, 'public, max-age=60', ['app.mutator'], 'app.bypass'],
            [
                $config->cacheService(),
                $config->ttl(),
                $config->perItemTtl(),
                $config->text('cache_control', 'no-cache'),
                $config->mutators(),
                $config->value('bypass'),
            ],
        );
    }

    #[Test]
    #[DataProvider('ttlProvider')]
    public function readsTheTtlAsWholeSeconds(mixed $ttl, int $expected): void
    {
        $config = PageCacheConfig::fromContainer(new InMemoryContainer(['config' => ['pagecache' => ['ttl' => $ttl]]]));

        static::assertSame($expected, $config->ttl());
    }
}
