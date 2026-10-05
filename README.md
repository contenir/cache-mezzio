# contenir/cache-mezzio

Mezzio (PSR-15) adapter for [`contenir/cache`](https://github.com/contenir/cache).

A full-page output cache middleware, controlled by the Contenir admin's
Page Cache screen. It is the sibling of
[`contenir/cache-laminas-mvc`](https://github.com/contenir/cache-laminas-mvc):
same `pagecache` config key, same `cache_with_*` / `make_id_with_*` options,
same route overrides, so a site's settings mean the same thing on either
framework.

## Install

```bash
composer require contenir/cache-mezzio
```

`laminas/laminas-component-installer` adds `Contenir\Cache\Mezzio\ConfigProvider`
to `config/config.php`. Without it, add the provider yourself:

```php
$aggregator = new ConfigAggregator([
    // …
    \Contenir\Cache\Mezzio\ConfigProvider::class,
    // …
]);
```

The provider registers `PageCacheMiddleware` (and the `CachePolicy` and
`PageStore` it is built from) and declares the `pagecache` defaults, with
caching off.

## Configure

Point the middleware at a cache service and set the site's defaults:

```php
// config/autoload/pagecache.global.php

return [
    'pagecache' => [
        // Service name of a Psr\SimpleCache\CacheInterface, or of a
        // Laminas\Cache\Storage\StorageInterface (wrapped for you).
        'cache'   => 'cache.pages',

        // Seconds. Applied to a laminas storage at storage level.
        'ttl'     => 300,

        // The site's defaults. The admin's pagecache.local.php overrides
        // them key by key; `cache` is the master switch.
        'options' => [
            'cache'              => true,
            'cache_with_query'   => true,
            'make_id_with_query' => true,
        ],

        // Regex => option overrides; the last matching pattern wins.
        'routes'  => [
            '/api.*' => ['cache' => false],
        ],
    ],
];
```

Every key:

| Key              | Default                                          | Meaning |
|------------------|--------------------------------------------------|---------|
| `cache`          | (required)                                       | Container service name of the cache (see [Storage](#storage)). |
| `ttl`            | `300`                                            | Seconds. Set on a laminas storage's options; passed per item only with `per_item_ttl`. |
| `per_item_ttl`   | `false`                                          | Pass a TTL with every `set()`. PSR-16 caches only; read [the TTL pitfall](#the-ttl-pitfall) first. |
| `cache_control`  | `no-cache`                                       | `Cache-Control` sent with a hit whose stored page has none. `no-cache` makes browsers and CDNs revalidate, so an admin purge or switch-off is seen at once. |
| `session_cookie` | `PHPSESSID`                                      | The session cookie's name. |
| `options`        | all off                                          | Site defaults for the options below. |
| `routes`         | `[]`                                             | Site default route overrides. |
| `bypass`         | `null`                                           | Service name of a `callable(ServerRequestInterface): bool`, or the callable itself; `true` keeps the request out of the cache. Prefer the service name: a closure cannot survive Mezzio's config cache. |
| `mutators`       | `[]`                                             | Service names of `ResponseMutatorInterface` instances, applied in order. |
| `file`           | `getcwd() . '/config/autoload/pagecache.local.php'` | The file the admin writes. |

## Pipe it

```php
// config/pipeline.php

$app->pipe(ErrorHandler::class);
$app->pipe(ServerUrlMiddleware::class);
$app->pipe(MaintenanceMiddleware::class);   // a 503 must never be cached, or served from cache
$app->pipe(SessionMiddleware::class);       // if the site has one: authenticated visitors then bypass
$app->pipe(\Contenir\Cache\Mezzio\PageCacheMiddleware::class);
$app->pipe(RouteMiddleware::class);
// … ImplicitHeadMiddleware, DispatchMiddleware, NotFoundHandler
```

- **After maintenance**, so a site in maintenance answers 503 rather than a
  cached page (and a 503 is never stored).
- **After session**, if the site pipes one, so the `session` attribute is
  there to recognise a logged-in user. Without it, the session cookie alone
  stands in for the session.
- **Before routing**, so a hit skips routing and dispatch altogether.

## How it works

On every request the middleware reads the admin's cache-control state, then:

1. **Bypasses** the cache (the handler runs, nothing is stored) when the
   method is not GET or HEAD; the admin master switch is off; the last
   matching route turns caching off; the request is authenticated (a `user`
   request attribute, a session holding `user_id`, or `bypass` returning
   `true`); or it carries a query, body, uploaded files, a session or cookies
   whose `cache_with_*` option is off.
2. **Serves a hit** from the cache, with `Age`, the configured
   `Cache-Control` if the page had none, and `X-PK-Cache: HIT`. A HEAD request
   is answered from the GET's entry, without a body.
3. **Stores a miss** when the response is cacheable: status 200, 203, 301 or
   308; no `Set-Cookie`; no `no-store` or `private` in `Cache-Control`; a body
   that can be replayed; and no veto. It is sent with `X-PK-Cache: MISS`.
   A HEAD miss is never stored.

Pages are stored as JSON strings (binary bodies base64-encoded), so any PSR-16
cache can hold them and an entry never unserializes into an object.

A cache backend that fails (an unwritable directory, an unreachable server)
degrades to uncached pages instead of failing the request.

### Why the admin file is read on every request

The admin's Page Cache screen writes `config/autoload/pagecache.local.php`.
Mezzio merges that file into its config, but in production it caches the
merged config (`data/cache/config-cache.php`), so an admin change read from
merged config would not be seen until the config cache was cleared. The
middleware therefore reads the file itself, on every request (it is a PHP
array, so opcache serves it, and the admin's writer invalidates opcache on
save), and the admin's switch takes effect on the next request.

The file holds only what the operator has overridden: a key that is absent
inherits the site default from `pagecache.options` / `pagecache.routes`.
`Repository\LayeredFileRepository` resolves the two layers the way the admin
screen displays them:

- `pagecache.options.cache` present in the file: it is the master switch;
  absent: the site default applies.
- any other `pagecache.options.*` key present overrides the site default of
  the same name.
- `pagecache.routes` present replaces the site's routes (the admin manages
  them as one list); absent: the site's routes apply.

One caveat: the site defaults themselves come from the merged config, which
in production also contains the admin file as it was when the config cache
was built. Taking an override *away* in the admin (so a key reverts to the
site default) therefore needs the config cache cleared; setting or changing
an override does not.

A site can register its own `Contenir\Cache\CacheControlRepositoryInterface`
service instead. It is then authoritative: `options`, `routes` and `file` are
not used.

## Options

| Option                 | Meaning in PSR-7 | Notes against the MVC adapter |
|------------------------|------------------|-------------------------------|
| `cache`                | Master switch. | In the MVC adapter a route can set `cache => true` and cache a path while the master switch is off. Here the master switch is final, as the admin screen promises; routes can turn caching off, or back on, only while it is on. |
| `cache_with_query`     | Cache requests with query parameters. | Same. |
| `cache_with_post`      | Cache requests with a parsed body. | Only GET and HEAD are ever cached, so this matters only for a GET that carries a body. |
| `cache_with_files`     | Cache requests with uploaded files. | As above. |
| `cache_with_cookie`    | Cache requests with cookies. | Same; the session cookie counts as a cookie. |
| `cache_with_session`   | Cache requests with a session: a non-empty `session` attribute, or the session cookie. | The MVC adapter never refuses on session; here it is checked like the other signals. Logged-in users bypass either way. |
| `make_id_with_query`   | Query parameters vary the cache key. | Same, except parameter order no longer matters. |
| `make_id_with_post`    | The parsed body varies the key. | Same. |
| `make_id_with_files`   | Uploaded files (name, type, size, error) vary the key. | Same. |
| `make_id_with_cookie`  | Cookies vary the key. | Same. |
| `make_id_with_session` | The session's data (or the session cookie) varies the key. | The MVC adapter varies by the user's role; logged-in users bypass here, so there is no role to vary by. |
| `ttl`                  | Per-path TTL in seconds. | Used only with `per_item_ttl`. |
| `priority`             | Unused. | Pipeline order replaces the MVC event priority. |

The key always includes the scheme, host, port and path, so one storage can
serve several hostnames. A signal that is present, allowed and not part of the
key is ignored: every variant shares one cached page.

## Routes

`routes` maps a regex to option overrides. Patterns are matched against the
URI path with no delimiters or anchors (they are wrapped in backticks, as the
MVC adapter does), and only the last matching pattern applies:

```php
'routes' => [
    '/api.*'        => ['cache' => false],
    '/api/public.*' => ['cache' => true],  // last match wins
    '^/search$'     => ['cache_with_query' => true, 'make_id_with_query' => true],
],
```

A pattern that does not compile is treated as not matching, so a typo in the
admin cannot take the site down.

## Opting a response out

A handler or middleware can stop one response from being stored, the PSR-15
counterpart of the MVC adapter's `pagecache.disable` event:

```php
use Contenir\Cache\Mezzio\PageCacheMiddleware;

return PageCacheMiddleware::veto($response);           // adds X-Page-Cache: off
return $response->withHeader('Cache-Control', 'no-store');
```

The middleware removes the `X-Page-Cache` header before the response is sent.

## Mutators

A mutator tweaks every response the middleware returns, after storage, so
per-request values never end up in the cache. It runs on hits, misses and
bypassed responses alike, so a placeholder never leaks when caching is off:

```php
use Contenir\Cache\Mezzio\CacheResult;
use Contenir\Cache\Mezzio\ResponseMutatorInterface;
use Laminas\Diactoros\StreamFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class PlaceholderMutator implements ResponseMutatorInterface
{
    public function mutate(
        ResponseInterface $response,
        ServerRequestInterface $request,
        CacheResult $result,
    ): ResponseInterface {
        if (! str_contains($response->getHeaderLine('Content-Type'), 'text/html')) {
            return $response;
        }

        $body = strtr((string) $response->getBody(), ['{{HOST}}' => $request->getUri()->getHost()]);

        return $response->withBody((new StreamFactory())->createStream($body));
    }
}
```

```php
'dependencies' => ['invokables' => [PlaceholderMutator::class => PlaceholderMutator::class]],
'pagecache'    => ['mutators' => [PlaceholderMutator::class]],
```

`CacheResult` is `Hit`, `Miss` or `Bypass`.

## Storage

`pagecache.cache` names a container service:

- a **PSR-16 cache** is used as it is. No TTL is passed per item unless
  `per_item_ttl` is on, so the cache's own default TTL applies.
- a **laminas-cache `StorageInterface`** (`^3.0 || ^4.0`, suggested, not
  required) has its storage-level TTL set to `ttl` and is wrapped in
  laminas' `SimpleCacheDecorator`. Per-item TTLs are never passed to it. The
  decorator requires the storage to have the `serializer` plugin when its
  adapter cannot store arrays (the Filesystem adapter in laminas-cache 3).

The setup the admin's purge button understands is a laminas-cache Filesystem
storage declared under `caches` (laminas-cache's abstract factory) in
`config/autoload/cache.global.php`, with its `cache_dir` under `data/cache`:

```php
// config/autoload/cache.global.php
return [
    'caches' => [
        'cache.pages' => [
            'adapter' => 'filesystem',
            'options' => [
                'cache_dir' => 'data/cache/pages',
                'namespace' => 'pages',
            ],
            'plugins' => [['name' => 'serializer']],
        ],
    ],
];

// config/autoload/pagecache.global.php
return ['pagecache' => ['cache' => 'cache.pages']];
```

### The TTL pitfall

laminas-cache 3's Filesystem adapter has no per-item TTL. Its
`SimpleCacheDecorator` reports that by returning `false` from any `set()` that
carries a TTL, without writing anything, so a page cache that passes TTLs to
it never caches a single page, silently. This package therefore never passes a
per-item TTL to a laminas storage, sets `ttl` on the storage itself instead,
and passes per-item TTLs to a PSR-16 cache only when `per_item_ttl` is on. The
integration suite runs against a real Filesystem storage to prove it.

## Purging

Purging is not the middleware's job: the admin talks to the storage directly.

- **Clear cache** (the admin's `ClearCache` registrar, run after many admin
  saves) empties every file under the site's `data/cache`. Pages stored in a
  Filesystem storage below `data/cache` are purged by it; the middleware
  treats the missing entries as misses.
- **Page Cache → Clear cache** (the `PurgeCacheHandler` operation) reads
  `pagecache.cache` from `config/autoload/pagecache.global.php`, finds that
  service under `caches` in `config/autoload/cache.global.php`, builds the same
  Filesystem storage (a relative `cache_dir` is resolved against the site
  root) and flushes it. It supports only the Filesystem adapter and only that
  `caches` layout.

So for both buttons to work, keep the page cache in a laminas-cache Filesystem
storage under `data/cache`, declared as shown under [Storage](#storage). A
different backend (Redis, a PSR-16 cache built by a custom factory) still
caches, but the admin cannot purge it; the TTL and the master switch are then
the only controls.
