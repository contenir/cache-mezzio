<?php

declare(strict_types=1);

namespace Contenir\PageCache\Mezzio;

use Closure;
use Contenir\PageCache\CacheControlRepositoryInterface;
use Contenir\PageCache\Repository\LayeredFileRepository;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function getcwd;
use function is_callable;
use function is_string;

/**
 * Builds the cache policy from `config['pagecache']`.
 *
 * The admin state comes from the container's CacheControlRepositoryInterface
 * when one is registered (it is then authoritative, and `options`, `routes` and
 * `file` are not used), otherwise from a LayeredFileRepository over `file`
 * with `options` and `routes` as the site defaults the admin file overrides.
 *
 * `bypass` is a callable(ServerRequestInterface): bool, or the service name of
 * one; a callable in config cannot survive Mezzio's config cache, so prefer
 * the service name.
 *
 * @api
 */
final readonly class CachePolicyFactory
{
    /**
     * Where the admin writes, relative to the working directory, which Mezzio
     * sets to the application root.
     */
    public const string DEFAULT_FILE = 'config/autoload/pagecache.local.php';

    /**
     * @return null|Closure(ServerRequestInterface): bool
     *
     * @throws RuntimeException
     * @throws ContainerExceptionInterface When a service the configuration names cannot be built.
     */
    private static function bypass(ContainerInterface $container, mixed $bypass): ?Closure
    {
        if (null === $bypass) {
            return null;
        }

        return self::closure(is_string($bypass) && $container->has($bypass) ? $container->get($bypass) : $bypass);
    }

    /**
     * @return Closure(ServerRequestInterface): bool
     *
     * @throws RuntimeException
     */
    private static function closure(mixed $callable): Closure
    {
        if (! is_callable($callable)) {
            throw new RuntimeException(
                'contenir/contenir-page-cache-mezzio: config[pagecache][bypass] must be a callable or the service name of one.',
            );
        }

        return static fn(ServerRequestInterface $request): bool => true === $callable($request);
    }

    /**
     * @throws ContainerExceptionInterface When a service the configuration names cannot be built.
     */
    private static function repository(
        ContainerInterface $container,
        PageCacheConfig $config,
    ): CacheControlRepositoryInterface {
        if ($container->has(CacheControlRepositoryInterface::class)) {
            return $container->get(CacheControlRepositoryInterface::class);
        }

        $cwd = getcwd();

        return LayeredFileRepository::withDefaults(
            $config->text('file', (false === $cwd ? '.' : $cwd) . '/' . self::DEFAULT_FILE),
            $config->value('options'),
            $config->value('routes'),
        );
    }

    /**
     * @throws RuntimeException When `bypass` is neither callable nor names a callable service.
     * @throws ContainerExceptionInterface When a service the configuration names cannot be built.
     */
    public function __invoke(ContainerInterface $container): CachePolicy
    {
        $config = PageCacheConfig::fromContainer($container);

        return new CachePolicy(
            self::repository($container, $config),
            self::bypass($container, $config->value('bypass')),
            $config->text('session_cookie', 'PHPSESSID'),
        );
    }
}
