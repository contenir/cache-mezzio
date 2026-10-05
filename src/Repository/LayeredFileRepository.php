<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio\Repository;

use Contenir\Cache\CacheControl;
use Contenir\Cache\CacheControlRepositoryInterface;
use Contenir\Cache\Repository\FileRepository;
use Contenir\Config\Reader\PhpArray;
use Override;

use function array_filter;
use function array_key_exists;
use function array_map;
use function filter_var;
use function is_array;
use function is_string;

use const ARRAY_FILTER_USE_KEY;
use const FILTER_VALIDATE_BOOLEAN;

/**
 * The admin's pagecache.local.php laid over the site's own defaults.
 *
 * The Contenir admin writes only the settings the operator has overridden to
 * pagecache.local.php, and an override is keyed by presence: a setting absent
 * from the file inherits the site default. contenir/cache's FileRepository
 * cannot express that (a missing `cache` key reads as "disabled"), so a site
 * whose admin has only overridden, say, the cookie options would lose its page
 * cache altogether. This repository resolves the effective state the way the
 * admin screen displays it:
 *
 * - `pagecache.options.cache` present: it is the master switch; absent: the
 *   site default applies.
 * - every other `pagecache.options.*` key present overrides the site default
 *   of the same name.
 * - `pagecache.routes` present: it replaces the site's routes (the admin
 *   manages routes as one list); absent: the site's routes apply.
 *
 * The file is read on every get(), so admin changes take effect on the next
 * request. A missing or unreadable file yields the defaults.
 *
 * save() writes the full state through FileRepository, the same writer the
 * admin's legacy registrar uses.
 *
 * @api
 */
final readonly class LayeredFileRepository implements CacheControlRepositoryInterface
{
    public function __construct(
        private string $filePath,
        private CacheControl $defaults = new CacheControl(false),
    ) {}

    /**
     * Builds the repository from `pagecache`-shaped defaults: `$options` may
     * hold the `cache` master switch alongside the other options.
     */
    public static function withDefaults(string $filePath, mixed $options, mixed $routes): self
    {
        $options = self::stringKeyed($options);
        $enabled = filter_var($options['cache'] ?? false, FILTER_VALIDATE_BOOLEAN);
        unset($options['cache']);

        return new self($filePath, new CacheControl($enabled, $options, self::routes($routes)));
    }

    /**
     * A pattern made only of digits is an integer key in PHP; it is kept, and
     * the matcher reads it back as a string.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function routes(mixed $routes): array
    {
        /** @var array<string, array<string, mixed>> */
        return array_map(self::stringKeyed(...), array_filter(is_array($routes) ? $routes : [], is_array(...)));
    }

    /**
     * @return array<string, mixed>
     */
    private static function stringKeyed(mixed $values): array
    {
        /** @var array<string, mixed> */
        return is_array($values) ? array_filter($values, is_string(...), ARRAY_FILTER_USE_KEY) : [];
    }

    #[Override]
    public function get(): CacheControl
    {
        $section = self::stringKeyed(PhpArray::fromFile($this->filePath)['pagecache'] ?? null);

        $options = self::stringKeyed($section['options'] ?? null);
        $enabled = array_key_exists('cache', $options)
            ? filter_var($options['cache'], FILTER_VALIDATE_BOOLEAN)
            : $this->defaults->enabled;
        unset($options['cache']);

        $routes = is_array($section['routes'] ?? null) ? self::routes($section['routes']) : $this->defaults->routes;

        return new CacheControl($enabled, [...$this->defaults->options, ...$options], $routes);
    }

    #[Override]
    public function save(CacheControl $state): void
    {
        (new FileRepository($this->filePath))->save($state);
    }
}
