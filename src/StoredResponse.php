<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio;

use Laminas\Diactoros\Response;
use Laminas\Diactoros\StreamFactory;
use Psr\Http\Message\ResponseInterface;

use function array_filter;
use function array_map;
use function array_values;
use function is_array;
use function is_string;

use const ARRAY_FILTER_USE_KEY;

/**
 * A page as the cache keeps it: status, headers, body and when it was stored.
 * PageCodec turns it into the string that is actually written.
 *
 * @internal
 */
final readonly class StoredResponse
{
    /**
     * @param array<non-empty-string, list<string>> $headers
     */
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
        public int $storedAt,
    ) {}

    /**
     * @param list<string> $excludeHeaders Header names (any case) left out of
     *     the stored copy.
     */
    public static function fromResponse(ResponseInterface $response, int $storedAt, array $excludeHeaders = []): self
    {
        foreach ($excludeHeaders as $name) {
            $response = $response->withoutHeader($name);
        }

        return new self(
            $response->getStatusCode(),
            self::headerLines($response->getHeaders()),
            (string) $response->getBody(),
            $storedAt,
        );
    }

    /**
     * Keeps the well-formed header lines: non-empty string names with at
     * least one string value.
     *
     * @return array<non-empty-string, list<string>>
     */
    public static function headerLines(mixed $headers): array
    {
        $lines = array_map(
            /** @return list<string> */
            static fn(mixed $values): array => array_values(array_filter(
                is_array($values) ? $values : [],
                is_string(...),
            )),
            array_filter(
                is_array($headers) ? $headers : [],
                static fn(mixed $name): bool => is_string($name) && '' !== $name,
                ARRAY_FILTER_USE_KEY,
            ),
        );

        /** @var array<non-empty-string, list<string>> */
        return array_filter(
            $lines,
            /** @param list<string> $values */
            static fn(array $values): bool => [] !== $values,
        );
    }

    public function toResponse(): ResponseInterface
    {
        return new Response((new StreamFactory())->createStream($this->body), $this->status, $this->headers);
    }
}
