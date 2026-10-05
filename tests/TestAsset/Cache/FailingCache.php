<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio\Tests\TestAsset\Cache;

use Override;
use Psr\SimpleCache\CacheInterface;

/**
 * A PSR-16 cache whose backend is down: every operation throws.
 *
 * Parameters are untyped so the double fits psr/simple-cache 1, 2 and 3 alike.
 */
final class FailingCache implements CacheInterface
{
    #[Override]
    public function clear(): bool
    {
        throw new CacheFailure('The cache backend is unavailable.');
    }

    #[Override]
    public function delete($key): bool
    {
        throw new CacheFailure('The cache backend is unavailable.');
    }

    #[Override]
    public function deleteMultiple($keys): bool
    {
        throw new CacheFailure('The cache backend is unavailable.');
    }

    #[Override]
    public function get($key, $default = null): mixed
    {
        throw new CacheFailure('The cache backend is unavailable.');
    }

    #[Override]
    public function getMultiple($keys, $default = null): iterable
    {
        throw new CacheFailure('The cache backend is unavailable.');
    }

    #[Override]
    public function has($key): bool
    {
        throw new CacheFailure('The cache backend is unavailable.');
    }

    #[Override]
    public function set($key, $value, $ttl = null): bool
    {
        throw new CacheFailure('The cache backend is unavailable.');
    }

    #[Override]
    public function setMultiple($values, $ttl = null): bool
    {
        throw new CacheFailure('The cache backend is unavailable.');
    }
}
