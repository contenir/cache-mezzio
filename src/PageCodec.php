<?php

declare(strict_types=1);

namespace Contenir\PageCache\Mezzio;

use JsonException;

use function base64_decode;
use function base64_encode;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function preg_match;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Writes pages to the cache as JSON strings and reads them back.
 *
 * A string rather than a PHP array or object works with any PSR-16 cache,
 * including a laminas-cache Filesystem storage, and an entry can never
 * unserialize into an arbitrary object. A body that is not valid UTF-8 (an
 * image streamed by a handler, say) is stored base64-encoded.
 *
 * @internal
 */
final readonly class PageCodec
{
    /**
     * The page in a cache entry, or null when the entry is not one encode()
     * wrote.
     */
    public static function decode(mixed $value): ?StoredResponse
    {
        $data = self::asArray(is_string($value) ? json_decode($value, associative: true) : null);
        if (! is_int($data['status'] ?? null) || ! is_int($data['storedAt'] ?? null)) {
            return null;
        }

        $body = self::body($data['body'] ?? null, $data['body64'] ?? null);
        if (null === $body) {
            return null;
        }

        return new StoredResponse(
            $data['status'],
            StoredResponse::headerLines($data['headers'] ?? null),
            $body,
            $data['storedAt'],
        );
    }

    /**
     * @throws JsonException When a header is not valid UTF-8.
     */
    public static function encode(StoredResponse $page): string
    {
        $body = 1 === preg_match('//u', $page->body)
            ? ['body' => $page->body]
            : ['body64' => base64_encode($page->body)];

        return json_encode(
            [
                'status'  => $page->status,
                'headers' => $page->headers,
                ...$body,
                'storedAt' => $page->storedAt,
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function asArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private static function body(mixed $plain, mixed $encoded): ?string
    {
        if (is_string($plain)) {
            return $plain;
        }

        $decoded = is_string($encoded) ? base64_decode($encoded, strict: true) : false;

        return false === $decoded ? null : $decoded;
    }
}
