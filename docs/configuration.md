# Configuration

Every `pagecache` key the factories read, and the cache-control options and route overrides the admin and the site share.

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
| `cache`          | (required)                                       | Container service name of the cache (see [Storage](storage.md)). |
| `ttl`            | `300`                                            | Whole seconds, as an integer or a numeric string such as `'600'`; anything else falls back to `300`. Set on a laminas storage's options; passed per item only with `per_item_ttl`. |
| `per_item_ttl`   | `false`                                          | Pass a TTL with every `set()`. PSR-16 caches only; read [the TTL pitfall](storage.md#the-ttl-pitfall) first. |
| `cache_control`  | `no-cache`                                       | `Cache-Control` sent with a hit whose stored page has none. `no-cache` makes browsers and CDNs revalidate, so an admin purge or switch-off is seen at once. |
| `session_cookie` | `PHPSESSID`                                      | The session cookie's name. |
| `options`        | all off                                          | Site defaults for the options below. |
| `routes`         | `[]`                                             | Site default route overrides. |
| `bypass`         | `null`                                           | Service name of a `callable(ServerRequestInterface): bool`, or the callable itself; `true` keeps the request out of the cache. Prefer the service name: a closure cannot survive Mezzio's config cache. |
| `mutators`       | `[]`                                             | Service names of `ResponseMutatorInterface` instances, applied in order. |
| `file`           | `getcwd() . '/config/autoload/pagecache.local.php'` | The file the admin writes. |


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

[Back to the README](../README.md)
