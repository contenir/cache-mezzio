<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

use function filter_var;
use function is_array;
use function is_numeric;
use function is_string;

use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_BOOLEAN;
use const FILTER_VALIDATE_INT;

/**
 * Typed reads of `config['pagecache']`, with the documented defaults.
 *
 * @internal
 */
final readonly class PageCacheConfig
{
    public const int DEFAULT_TTL = 300;

    /**
     * @param array<array-key, mixed> $section
     */
    private function __construct(
        private array $section,
    ) {}

    /**
     * @throws ContainerExceptionInterface When the config service cannot be built.
     */
    public static function fromContainer(ContainerInterface $container): self
    {
        $config = self::asArray($container->has('config') ? $container->get('config') : null);

        return new self(self::asArray($config['pagecache'] ?? null));
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function asArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @return null|non-empty-string
     */
    private static function nonEmpty(mixed $value): ?string
    {
        return is_string($value) && '' !== $value ? $value : null;
    }

    /**
     * The container service name of the cache (`cache`), or null when unset.
     */
    public function cacheService(): ?string
    {
        return self::nonEmpty($this->section['cache'] ?? null);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function mutators(): array
    {
        return self::asArray($this->section['mutators'] ?? null);
    }

    public function perItemTtl(): bool
    {
        return filter_var($this->section['per_item_ttl'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * A string setting, or the default when it is unset or empty.
     *
     * @param non-empty-string $default
     *
     * @return non-empty-string
     */
    public function text(string $key, string $default): string
    {
        return self::nonEmpty($this->section[$key] ?? null) ?? $default;
    }

    /**
     * The `ttl` setting in whole seconds: an integer, or a numeric string
     * holding one (as config read from the environment is). Anything else
     * gives the default.
     */
    public function ttl(): int
    {
        $ttl = is_numeric($this->section['ttl'] ?? null)
            ? $this->section['ttl']
            : null;

        return filter_var($ttl, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? self::DEFAULT_TTL;
    }

    /**
     * A setting as configured, for the caller to validate.
     */
    public function value(string $key): mixed
    {
        return $this->section[$key] ?? null;
    }
}
