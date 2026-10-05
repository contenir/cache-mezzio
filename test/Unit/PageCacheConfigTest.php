<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio\Test\Unit;

use Contenir\Cache\Mezzio\PageCacheConfig;
use Contenir\Cache\Mezzio\Test\TestAsset\Container\InMemoryContainer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
#[Group('factory')]
final class PageCacheConfigTest extends TestCase
{
    /**
     * @param array<string, mixed> $services
     */
    #[DataProvider('unconfiguredProvider')]
    public function testFallsBackToTheDefaultsWhenNothingIsConfigured(array $services): void
    {
        $config = PageCacheConfig::fromContainer(new InMemoryContainer($services));

        self::assertSame(
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
                        'ttl'           => '600',
                        'per_item_ttl'  => 'nope',
                        'cache_control' => 42,
                        'mutators'      => 'app.mutator',
                    ],
                ],
            ]],
        ];
    }

    public function testReadsTheConfiguredSettings(): void
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

        self::assertSame(
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
}
