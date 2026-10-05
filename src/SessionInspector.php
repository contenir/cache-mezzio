<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio;

use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function is_array;
use function is_object;
use function is_string;
use function method_exists;

/**
 * Reads the visitor's session off a PSR-7 request without depending on a
 * session library.
 *
 * The session is the `session` request attribute that mezzio-session (or any
 * middleware following its convention) sets: an array, or an object exposing
 * `toArray()` and optionally `has()`. A request also counts as having a
 * session when it carries the session cookie, which is how a session shows up
 * before (or without) a session middleware in the pipeline.
 *
 * @internal
 */
final readonly class SessionInspector
{
    public const string ATTRIBUTE = 'session';

    public function __construct(
        private string $cookieName = 'PHPSESSID',
    ) {}

    /**
     * The session values that identify this visitor's session, or an empty
     * array when there is no session. A session known only by its cookie is
     * identified by the cookie's value.
     *
     * @return array<array-key, mixed>
     */
    public function values(ServerRequestInterface $request): array
    {
        $data = self::data($request->getAttribute(self::ATTRIBUTE));
        if ([] !== $data) {
            return $data;
        }

        return self::cookie($request->getCookieParams()[$this->cookieName] ?? null);
    }

    /**
     * Whether the session holds a logged-in user (`user_id`), the convention
     * the Contenir CMS uses.
     */
    public function hasUser(ServerRequestInterface $request): bool
    {
        return self::holdsUser($request->getAttribute(self::ATTRIBUTE));
    }

    private static function holdsUser(mixed $session): bool
    {
        if (is_object($session) && method_exists($session, 'has')) {
            return true === $session->has('user_id');
        }

        $data = self::data($session);

        return array_key_exists('user_id', $data) && null !== $data['user_id'];
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function data(mixed $session): array
    {
        if (is_object($session) && method_exists($session, 'toArray')) {
            return self::data($session->toArray());
        }

        return is_array($session) ? $session : [];
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function cookie(mixed $id): array
    {
        return is_string($id) && '' !== $id ? ['id' => $id] : [];
    }
}
