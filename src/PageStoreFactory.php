<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio;

use Laminas\Cache\Psr\SimpleCache\SimpleCacheDecorator;
use Laminas\Cache\Storage\StorageInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;

use function get_debug_type;
use function sprintf;

/**
 * Builds the page store from `config['pagecache']`.
 *
 * `cache` names a container service: a PSR-16 cache is used as it is (no TTL is
 * passed per item unless `per_item_ttl` is on); a laminas-cache
 * StorageInterface has its storage-level TTL set to `ttl` and is wrapped in a
 * SimpleCacheDecorator, never with per-item TTLs, which a Filesystem storage
 * would refuse.
 *
 * @api
 */
final readonly class PageStoreFactory
{
    /**
     * @throws RuntimeException
     */
    private static function store(mixed $service, string $serviceName, PageCacheConfig $config): PageStore
    {
        if ($service instanceof StorageInterface) {
            $service->getOptions()->setTtl($config->ttl());

            return new PageStore(
                new SimpleCacheDecorator($service),
                new SystemClock(),
                perItemTtl: false,
                ttl: $config->ttl(),
                cacheControl: $config->text('cache_control', 'no-cache'),
            );
        }

        if ($service instanceof CacheInterface) {
            return new PageStore(
                $service,
                new SystemClock(),
                perItemTtl: $config->perItemTtl(),
                ttl: $config->ttl(),
                cacheControl: $config->text('cache_control', 'no-cache'),
            );
        }

        throw new RuntimeException(sprintf(
            'contenir/contenir-cache-mezzio: the page cache service "%s" is a %s, not a'
                . ' Psr\SimpleCache\CacheInterface or a Laminas\Cache\Storage\StorageInterface.',
            $serviceName,
            get_debug_type($service),
        ));
    }

    /**
     * @throws RuntimeException When `cache` is missing or names an unusable service.
     * @throws ContainerExceptionInterface When a service the configuration names cannot be built.
     */
    public function __invoke(ContainerInterface $container): PageStore
    {
        $config      = PageCacheConfig::fromContainer($container);
        $serviceName = $config->cacheService();
        if (null === $serviceName) {
            throw new RuntimeException(
                'contenir/contenir-cache-mezzio: config[pagecache][cache] must be the service name of a'
                    . ' Psr\SimpleCache\CacheInterface or a Laminas\Cache\Storage\StorageInterface.',
            );
        }

        return self::store($container->get($serviceName), $serviceName, $config);
    }
}
