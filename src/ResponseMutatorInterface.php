<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A per-request tweak applied to every response the page cache returns.
 *
 * Mutators run after the response has been stored, on hits and misses alike
 * (and on bypassed responses, so a placeholder never leaks when caching is off),
 * which keeps per-request values out of the cache: inject a CSRF token or a
 * nonce, replace `{{HOST}}`-style placeholders, append a build stamp, add a
 * diagnostic header.
 *
 * Mutators decide for themselves which responses they touch; check the
 * Content-Type before rewriting a body.
 *
 * @api
 */
interface ResponseMutatorInterface
{
    public function mutate(
        ResponseInterface $response,
        ServerRequestInterface $request,
        CacheResult $result,
    ): ResponseInterface;
}
