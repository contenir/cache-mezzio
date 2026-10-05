<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio\Test\Unit;

use Contenir\Cache\Mezzio\CacheTicket;
use Contenir\Cache\Mezzio\PageStoreFactory;
use Contenir\Cache\Mezzio\Test\TestAsset\Cache\InMemoryCache;
use Contenir\Cache\Mezzio\Test\TestAsset\Container\InMemoryContainer;
use Laminas\Diactoros\Response\HtmlResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

#[Group('unit')]
#[Group('factory')]
final class PageStoreFactoryTest extends TestCase
{
    public function testUsesThePsr16CacheTheConfigNames(): void
    {
        $cache = new InMemoryCache();
        $store = (new PageStoreFactory())(new InMemoryContainer([
            'config'      => ['pagecache' => ['cache' => 'cache.pages']],
            'cache.pages' => $cache,
        ]));

        $store->save(new CacheTicket('page'), new HtmlResponse('x'));

        self::assertArrayHasKey('page', $cache->items);
    }

    public function testPassesNoPerItemTtlToAPsr16CacheByDefault(): void
    {
        $cache = new InMemoryCache(honoursTtl: true);
        $store = (new PageStoreFactory())(new InMemoryContainer([
            'config'      => ['pagecache' => ['cache' => 'cache.pages', 'ttl' => 600]],
            'cache.pages' => $cache,
        ]));

        $store->save(new CacheTicket('page'), new HtmlResponse('x'));

        self::assertNull($cache->ttls['page']);
    }

    public function testPassesTheTtlToAPsr16CacheWhenPerItemTtlsAreSwitchedOn(): void
    {
        $cache = new InMemoryCache(honoursTtl: true);
        $store = (new PageStoreFactory())(new InMemoryContainer([
            'config'      => ['pagecache' => ['cache' => 'cache.pages', 'ttl' => 600, 'per_item_ttl' => true]],
            'cache.pages' => $cache,
        ]));

        $store->save(new CacheTicket('page'), new HtmlResponse('x'));

        self::assertSame(600, $cache->ttls['page']);
    }

    public function testHitsGetTheConfiguredCacheControl(): void
    {
        $store = (new PageStoreFactory())(new InMemoryContainer([
            'config'      => ['pagecache' => ['cache' => 'cache.pages', 'cache_control' => 'public, max-age=60']],
            'cache.pages' => new InMemoryCache(),
        ]));

        $store->save(new CacheTicket('page'), new HtmlResponse('x'));

        self::assertSame('public, max-age=60', $store->fetch(new CacheTicket('page'))?->getHeaderLine('Cache-Control'));
    }

    /**
     * @param array<string, mixed> $services
     */
    #[DataProvider('misconfigurationProvider')]
    public function testRefusesAMissingOrUnusableCache(array $services, string $message): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        (new PageStoreFactory())(new InMemoryContainer($services));
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function misconfigurationProvider(): array
    {
        return [
            'no cache configured' => [[], 'config[pagecache][cache] must be the service name'],
            'not a cache'         => [
                ['config' => ['pagecache' => ['cache' => 'cache.pages']], 'cache.pages' => new stdClass()],
                'the page cache service "cache.pages" is a stdClass',
            ],
        ];
    }
}
