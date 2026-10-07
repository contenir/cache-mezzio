<?php

declare(strict_types=1);

namespace Contenir\PageCache\Mezzio\Tests\Unit;

use Contenir\PageCache\Mezzio\CacheTicket;
use Contenir\PageCache\Mezzio\PageStoreFactory;
use Contenir\PageCache\Mezzio\Tests\TestAsset\Cache\InMemoryCache;
use Contenir\PageCache\Mezzio\Tests\TestAsset\Container\InMemoryContainer;
use Laminas\Diactoros\Response\HtmlResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

#[Group('unit')]
#[Group('factory')]
final class PageStoreFactoryTest extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function misconfigurationProvider(): array
    {
        return [
            'no cache configured' => [
                [],
                'contenir/contenir-page-cache-mezzio: config[pagecache][cache] must be the service name of a'
                    . ' Psr\SimpleCache\CacheInterface or a Laminas\Cache\Storage\StorageInterface.',
            ],
            'not a cache'         => [
                ['config' => ['pagecache' => ['cache' => 'cache.pages']], 'cache.pages' => new stdClass()],
                'contenir/contenir-page-cache-mezzio: the page cache service "cache.pages" is a stdClass, not a'
                    . ' Psr\SimpleCache\CacheInterface or a Laminas\Cache\Storage\StorageInterface.',
            ],
        ];
    }

    #[Test]
    public function hitsGetTheConfiguredCacheControl(): void
    {
        $store = (new PageStoreFactory())(new InMemoryContainer([
            'config'      => ['pagecache' => ['cache' => 'cache.pages', 'cache_control' => 'public, max-age=60']],
            'cache.pages' => new InMemoryCache(),
        ]));

        $store->save(new CacheTicket('page'), new HtmlResponse('x'));

        static::assertSame(
            'public, max-age=60',
            $store->fetch(new CacheTicket('page'))?->getHeaderLine('Cache-Control'),
        );
    }

    #[Test]
    public function passesNoPerItemTtlToAPsr16CacheByDefault(): void
    {
        $cache = new InMemoryCache(honoursTtl: true);
        $store = (new PageStoreFactory())(new InMemoryContainer([
            'config'      => ['pagecache' => ['cache' => 'cache.pages', 'ttl' => 600]],
            'cache.pages' => $cache,
        ]));

        $store->save(new CacheTicket('page'), new HtmlResponse('x'));

        static::assertNull($cache->ttls['page']);
    }

    #[Test]
    public function passesTheTtlToAPsr16CacheWhenPerItemTtlsAreSwitchedOn(): void
    {
        $cache = new InMemoryCache(honoursTtl: true);
        $store = (new PageStoreFactory())(new InMemoryContainer([
            'config'      => ['pagecache' => ['cache' => 'cache.pages', 'ttl' => 600, 'per_item_ttl' => true]],
            'cache.pages' => $cache,
        ]));

        $store->save(new CacheTicket('page'), new HtmlResponse('x'));

        static::assertSame(600, $cache->ttls['page']);
    }

    /**
     * @param array<string, mixed> $services
     */
    #[Test]
    #[DataProvider('misconfigurationProvider')]
    public function refusesAMissingOrUnusableCache(array $services, string $message): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        (new PageStoreFactory())(new InMemoryContainer($services));
    }

    #[Test]
    public function usesThePsr16CacheTheConfigNames(): void
    {
        $cache = new InMemoryCache();
        $store = (new PageStoreFactory())(new InMemoryContainer([
            'config'      => ['pagecache' => ['cache' => 'cache.pages']],
            'cache.pages' => $cache,
        ]));

        $store->save(new CacheTicket('page'), new HtmlResponse('x'));

        static::assertArrayHasKey('page', $cache->items);
    }
}
