<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio\Tests\Unit;

use Contenir\Cache\Mezzio\ActiveOptions;
use Contenir\Cache\Mezzio\CachePolicy;
use Contenir\Cache\Mezzio\CachePolicyFactory;
use Contenir\Cache\Mezzio\ConfigProvider;
use Contenir\Cache\Mezzio\PageCacheMiddleware;
use Contenir\Cache\Mezzio\PageCacheMiddlewareFactory;
use Contenir\Cache\Mezzio\PageStore;
use Contenir\Cache\Mezzio\PageStoreFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_keys;

#[Group('unit')]
final class ConfigProviderTest extends TestCase
{
    #[Test]
    public function declaresTheMvcAdaptersOptionKeys(): void
    {
        static::assertSame(
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

    #[Test]
    public function exposesTheDependenciesForDirectUse(): void
    {
        static::assertSame(
            [
                'factories' => [
                    PageCacheMiddleware::class => PageCacheMiddlewareFactory::class,
                    CachePolicy::class         => CachePolicyFactory::class,
                    PageStore::class           => PageStoreFactory::class,
                ],
            ],
            (new ConfigProvider())->getDependencies(),
        );
    }

    #[Test]
    public function pageCachingIsOffByDefault(): void
    {
        $defaults = (new ConfigProvider())->getPageCacheDefaults();

        static::assertSame([null, false], [$defaults['cache'], ((array) $defaults['options'])['cache']]);
    }

    #[Test]
    public function registersAFactoryForEachService(): void
    {
        static::assertSame(
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

    #[Test]
    public function shipsTheDocumentedPageCacheDefaults(): void
    {
        static::assertSame(
            [
                'cache'          => null,
                'ttl'            => 300,
                'per_item_ttl'   => false,
                'cache_control'  => 'no-cache',
                'session_cookie' => 'PHPSESSID',
                'options'        => ActiveOptions::DEFAULTS,
                'routes'         => [],
                'bypass'         => null,
                'mutators'       => [],
                'file'           => null,
            ],
            (new ConfigProvider())->getPageCacheDefaults(),
        );
    }
}
