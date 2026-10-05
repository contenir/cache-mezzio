<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio\Tests\TestAsset\Cache;

use Override;
use Psr\SimpleCache\CacheInterface;

use function array_key_exists;

/**
 * An in-memory PSR-16 cache that, like laminas' SimpleCacheDecorator over a
 * Filesystem storage, refuses any write that comes with a per-item TTL, unless
 * it is told it honours TTLs, in which case it records them.
 *
 * Parameters are untyped so the double fits psr/simple-cache 1, 2 and 3 alike.
 */
final class InMemoryCache implements CacheInterface
{
    /** @var array<string, mixed> */
    public array $items = [];

    /** @var array<string, mixed> */
    public array $ttls = [];

    public function __construct(
        private readonly bool $honoursTtl = false,
    ) {}

    #[Override]
    public function clear(): bool
    {
        $this->items = [];
        $this->ttls  = [];

        return true;
    }

    #[Override]
    public function delete($key): bool
    {
        unset($this->items[$key], $this->ttls[$key]);

        return true;
    }

    /**
     * @param iterable<string> $keys
     */
    #[Override]
    public function deleteMultiple($keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    #[Override]
    public function get($key, $default = null): mixed
    {
        return array_key_exists($key, $this->items) ? $this->items[$key] : $default;
    }

    /**
     * @param iterable<string> $keys
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function getMultiple($keys, $default = null): iterable
    {
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }

        return $values;
    }

    #[Override]
    public function has($key): bool
    {
        return array_key_exists($key, $this->items);
    }

    #[Override]
    public function set($key, $value, $ttl = null): bool
    {
        if (null !== $ttl && ! $this->honoursTtl) {
            return false;
        }

        $this->items[$key] = $value;
        $this->ttls[$key]  = $ttl;

        return true;
    }

    /**
     * @param iterable<string, mixed> $values
     */
    #[Override]
    public function setMultiple($values, $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            if (! $this->set($key, $value, $ttl)) {
                return false;
            }
        }

        return true;
    }
}
