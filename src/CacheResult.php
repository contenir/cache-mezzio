<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio;

/**
 * How the page cache dealt with a request, handed to every response mutator.
 *
 * - Hit: the response was served from the cache; the handler did not run.
 * - Miss: the handler ran and its response has just been stored.
 * - Bypass: the handler ran and nothing was stored, because caching is off for
 *   this request (admin master switch, route override, method, authentication,
 *   request signals the options refuse) or the response was not cacheable.
 *
 * @api
 */
enum CacheResult: string
{
    case Hit    = 'HIT';
    case Miss   = 'MISS';
    case Bypass = 'BYPASS';
}
