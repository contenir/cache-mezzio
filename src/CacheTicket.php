<?php

declare(strict_types=1);

namespace Contenir\PageCache\Mezzio;

/**
 * The cache entry one request reads and writes: its key, and the `ttl` option
 * in force for its path (used only when per-item TTLs are enabled).
 *
 * @api
 */
final readonly class CacheTicket
{
    public function __construct(
        public string $key,
        public ?int $ttl = null,
    ) {}
}
