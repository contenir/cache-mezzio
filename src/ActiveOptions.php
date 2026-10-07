<?php

declare(strict_types=1);

namespace Contenir\PageCache\Mezzio;

use Contenir\PageCache\CacheControl;

use function filter_var;
use function is_numeric;
use function preg_match;
use function restore_error_handler;
use function set_error_handler;
use function str_replace;

use const FILTER_VALIDATE_BOOLEAN;

/**
 * The page-cache options in force for one request path.
 *
 * Resolution follows the Laminas MVC CacheStrategy: the overrides of the last
 * route regex that matches the path are laid over the admin's options, and any
 * option still unset reads as its {@see DEFAULTS} value. One deliberate difference: the
 * admin master switch is final. When it is off nothing is cached, whatever the
 * routes say, which is what the admin screen promises ("bypasses regardless of
 * its per-route configuration"). A route can still turn caching off (or back
 * on) while the master switch is on.
 *
 * @internal
 */
final readonly class ActiveOptions
{
    /**
     * The request signals, in the order they contribute to the cache key.
     */
    public const array SIGNALS = ['query', 'post', 'files', 'session', 'cookie'];

    public const array DEFAULTS = [
        'cache_with_query'     => false,
        'cache_with_post'      => false,
        'cache_with_session'   => false,
        'cache_with_files'     => false,
        'cache_with_cookie'    => false,
        'make_id_with_query'   => false,
        'make_id_with_post'    => false,
        'make_id_with_session' => false,
        'make_id_with_files'   => false,
        'make_id_with_cookie'  => false,
        'cache'                => false,
        'ttl'                  => null,
        'priority'             => null,
    ];

    /**
     * @param array<array-key, mixed> $options
     */
    private function __construct(
        private array $options,
    ) {}

    /**
     * The options for this path, or null when caching is off for it.
     */
    public static function resolve(CacheControl $control, string $path): ?self
    {
        if (! $control->enabled) {
            return null;
        }

        $override = [];
        foreach ($control->routes as $pattern => $overrides) {
            if (! self::matches($pattern, $path)) {
                continue;
            }

            $override = $overrides;
        }

        $options = [...$control->options, 'cache' => true, ...$override];

        return filter_var($options['cache'] ?? false, FILTER_VALIDATE_BOOLEAN) ? new self($options) : null;
    }

    /**
     * Patterns are admin-entered regexes without delimiters or anchors, wrapped
     * in backticks as the MVC strategy does. A pattern that does not compile is
     * treated as not matching rather than raising a warning on every request.
     */
    private static function matches(int|string $pattern, string $path): bool
    {
        set_error_handler(static fn(): bool => true);

        try {
            return 1 === preg_match(
                '`'
                    . str_replace(
                        search: '`',
                        replace: '\\`',
                        subject: (string) $pattern,
                    )
                    . '`',
                $path,
            );
        } finally {
            restore_error_handler();
        }
    }

    private static function seconds(mixed $ttl): ?int
    {
        return is_numeric($ttl) ? (int) $ttl : null;
    }

    /**
     * Whether a request carrying this signal may be cached at all
     * (`cache_with_{signal}`).
     */
    public function allows(string $signal): bool
    {
        return filter_var($this->options["cache_with_{$signal}"] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * The `ttl` option, in seconds, when one is set for this path.
     */
    public function ttl(): ?int
    {
        return self::seconds($this->options['ttl'] ?? null);
    }

    /**
     * Whether this signal's values become part of the cache key
     * (`make_id_with_{signal}`).
     */
    public function variesBy(string $signal): bool
    {
        return filter_var($this->options["make_id_with_{$signal}"] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
}
