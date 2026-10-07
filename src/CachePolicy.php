<?php

declare(strict_types=1);

namespace Contenir\PageCache\Mezzio;

use Closure;
use Contenir\PageCache\CacheControlRepositoryInterface;
use Psr\Http\Message\ServerRequestInterface;

use function in_array;
use function strtoupper;

/**
 * Decides, per request, whether the page cache may be used and under which key.
 *
 * The admin's cache-control state is read from the repository on every call,
 * so a change made in the admin applies from the next request on. A request
 * gets no ticket (and bypasses the cache) when:
 *
 * - its method is not GET or HEAD
 * - the admin master switch is off, or the last matching route turns caching off
 * - it is authenticated: a `user` request attribute, a session holding
 *   `user_id`, or the bypass callable returning true
 * - it carries a query, body, files, session or cookies whose
 *   `cache_with_*` option is off
 *
 * @api
 */
final readonly class CachePolicy
{
    public const string USER_ATTRIBUTE = 'user';

    private SessionInspector $session;

    private CacheKeyGenerator $keys;

    /**
     * @param null|Closure(ServerRequestInterface): bool $bypass Returns true
     *     for requests that must never be served from or stored in the cache.
     * @param non-empty-string $sessionCookie The session cookie's name.
     */
    public function __construct(
        private CacheControlRepositoryInterface $repository,
        private ?Closure $bypass = null,
        string $sessionCookie = 'PHPSESSID',
    ) {
        $this->session = new SessionInspector($sessionCookie);
        $this->keys    = new CacheKeyGenerator($this->session);
    }

    /**
     * The cache entry this request reads and writes, or null when it must
     * bypass the cache.
     */
    public function ticketFor(ServerRequestInterface $request): ?CacheTicket
    {
        if (! in_array(strtoupper($request->getMethod()), ['GET', 'HEAD'], strict: true)) {
            return null;
        }

        $options = ActiveOptions::resolve($this->repository->get(), $request->getUri()->getPath());
        if (null === $options || $this->isAuthenticated($request)) {
            return null;
        }

        $key = $this->keys->generate($request, $options);

        return null === $key ? null : new CacheTicket($key, $options->ttl());
    }

    private function isAuthenticated(ServerRequestInterface $request): bool
    {
        if (null !== $request->getAttribute(self::USER_ATTRIBUTE) || $this->session->hasUser($request)) {
            return true;
        }

        return null !== $this->bypass && ($this->bypass)($request);
    }
}
