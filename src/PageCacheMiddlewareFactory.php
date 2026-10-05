<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;

use function array_map;
use function array_values;
use function get_debug_type;
use function is_string;
use function sprintf;

/**
 * Builds the page-cache middleware from `config['pagecache']`.
 *
 * - `cache`: container service name of a PSR-16 cache, or of a laminas-cache
 *   StorageInterface (wrapped, with its storage-level TTL set to `ttl`); required
 * - `ttl`: seconds (default 300)
 * - `per_item_ttl`: pass a TTL with every set(); PSR-16 caches only (default false)
 * - `cache_control`: Cache-Control for hits whose page has none (default `no-cache`)
 * - `session_cookie`: the session cookie's name (default `PHPSESSID`)
 * - `options` / `routes`: the site's defaults, which the admin file overrides
 * - `bypass`: a callable(ServerRequestInterface): bool, or its service name
 * - `mutators`: service names of ResponseMutatorInterface instances, applied in order
 * - `file`: the admin's file (default `getcwd() . '/config/autoload/pagecache.local.php'`)
 *
 * See CachePolicyFactory and PageStoreFactory for the details.
 *
 * @api
 */
final readonly class PageCacheMiddlewareFactory
{
    /**
     * @throws RuntimeException
     */
    private static function mutator(mixed $mutator): ResponseMutatorInterface
    {
        if (! $mutator instanceof ResponseMutatorInterface) {
            throw new RuntimeException(sprintf(
                'contenir/cache-mezzio: page cache mutators must implement %s; got %s.',
                ResponseMutatorInterface::class,
                get_debug_type($mutator),
            ));
        }

        return $mutator;
    }

    /**
     * @throws RuntimeException When the configuration or a service it names is invalid.
     * @throws ContainerExceptionInterface When a service the configuration names cannot be built.
     */
    public function __invoke(ContainerInterface $container): PageCacheMiddleware
    {
        $mutators = array_map(
            /**
             * @throws RuntimeException
             * @throws ContainerExceptionInterface
             */
            static fn(mixed $serviceName): ResponseMutatorInterface => self::mutator(
                is_string($serviceName) ? $container->get($serviceName) : $serviceName,
            ),
            PageCacheConfig::fromContainer($container)->mutators(),
        );

        return new PageCacheMiddleware(
            (new CachePolicyFactory())($container),
            (new PageStoreFactory())($container),
            array_values($mutators),
        );
    }
}
