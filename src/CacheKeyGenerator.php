<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

use function array_map;
use function get_object_vars;
use function hash;
use function is_array;
use function is_object;
use function ksort;
use function serialize;

/**
 * Builds the cache key for a request, or refuses to.
 *
 * This is the PSR-7 reading of the MVC CacheStrategy's makeCacheKey(): the key
 * starts from the URI path (plus scheme, host and port, so one storage can
 * serve several hostnames), then each request signal that is present is
 * checked against the active options:
 *
 * - present and `cache_with_{signal}` off: the request is not cacheable (null)
 * - present and `make_id_with_{signal}` on: its values vary the key
 * - otherwise it is ignored, so every variant shares the one cached page
 *
 * Signals map to PSR-7 as query => query params, post => parsed body,
 * files => uploaded files, cookie => cookie params, session => the session
 * (see SessionInspector). Values are normalised (sorted by key) so the same
 * query in a different order hits the same page.
 *
 * @internal
 */
final readonly class CacheKeyGenerator
{
    public const string PREFIX = 'pagecache_';

    public function __construct(
        private SessionInspector $session,
    ) {}

    /**
     * Uploaded files are reduced to what describes them, so the key never
     * depends on stream objects. PSR-7 guarantees the tree holds only uploaded
     * files and arrays of them.
     *
     * @param array<array-key, mixed> $files
     *
     * @return array<array-key, mixed>
     */
    private static function describeFiles(array $files): array
    {
        return array_map(
            /** @return array<array-key, mixed> */
            static fn(mixed $file): array => (
                $file instanceof UploadedFileInterface
                    ? [$file->getClientFilename(), $file->getClientMediaType(), $file->getSize(), $file->getError()]
                    : self::describeFiles(is_array($file) ? $file : [])
            ),
            $files,
        );
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    private static function normalise(array $values): array
    {
        ksort($values);

        return array_map(static fn(mixed $value): mixed => is_array($value)
            ? self::normalise($value)
            : $value, $values);
    }

    /**
     * @param null|array<array-key, mixed>|object $body
     *
     * @return array<array-key, mixed>
     */
    private static function parsedBody(array|object|null $body): array
    {
        if (is_object($body)) {
            return get_object_vars($body);
        }

        return $body ?? [];
    }

    public function generate(ServerRequestInterface $request, ActiveOptions $options): ?string
    {
        $uri   = $request->getUri();
        $parts = [
            'scheme' => $uri->getScheme(),
            'host'   => $uri->getHost(),
            'port'   => $uri->getPort(),
            'path'   => $uri->getPath(),
        ];

        foreach (ActiveOptions::SIGNALS as $signal) {
            $values = $this->signal($request, $signal);
            if ([] === $values) {
                continue;
            }

            if (! $options->allows($signal)) {
                return null;
            }

            if ($options->variesBy($signal)) {
                $parts[$signal] = self::normalise($values);
            }
        }

        return self::PREFIX . hash('xxh128', serialize($parts));
    }

    /**
     * @return array<array-key, mixed>
     */
    private function signal(ServerRequestInterface $request, string $signal): array
    {
        return match ($signal) {
            'query'   => $request->getQueryParams(),
            'post'    => self::parsedBody($request->getParsedBody()),
            'files'   => self::describeFiles($request->getUploadedFiles()),
            'session' => $this->session->values($request),
            default   => $request->getCookieParams(),
        };
    }
}
