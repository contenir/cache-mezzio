<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio\Tests\Unit;

use Contenir\Cache\Mezzio\CachePolicy;
use Contenir\Cache\Mezzio\CachePolicyFactory;
use Contenir\Cache\Mezzio\ConfigProvider;
use Contenir\Cache\Mezzio\PageCacheMiddleware;
use Contenir\Cache\Mezzio\PageCacheMiddlewareFactory;
use Contenir\Cache\Mezzio\PageStore;
use Contenir\Cache\Mezzio\PageStoreFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function array_keys;

#[Group('unit')]
final class ConfigProviderTest extends TestCase
{
    public function testDeclaresTheMvcAdaptersOptionKeys(): void
    {
        self::assertSame(
            [
                'cache_with_query',
                'cache_with_post',
                'cache_with_session',
                'cache_with_files',
                'cache_with_cookie',
                'make_id_with_query',
                'make_id_with_post',
                'make_id_with_session',
                'make_id_with_files',
                'make_id_with_cookie',
                'cache',
                'ttl',
                'priority',
            ],
            array_keys((array) (new ConfigProvider())()['pagecache']['options']),
        );
    }

    public function testPageCachingIsOffByDefault(): void
    {
        $defaults = (new ConfigProvider())->getPageCacheDefaults();

        self::assertSame([null, false], [$defaults['cache'], ((array) $defaults['options'])['cache']]);
    }

    public function testRegistersAFactoryForEachService(): void
    {
        self::assertSame(
            [
                'factories' => [
                    PageCacheMiddleware::class => PageCacheMiddlewareFactory::class,
                    CachePolicy::class         => CachePolicyFactory::class,
                    PageStore::class           => PageStoreFactory::class,
                ],
            ],
            (new ConfigProvider())()['dependencies'],
        );
    }
}
