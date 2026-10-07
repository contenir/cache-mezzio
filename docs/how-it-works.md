# How it works

What the middleware does with each request, and where the admin's cache-control state comes from.

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
contenir-page-cache's `Repository\LayeredFileRepository` resolves the two
layers the way the admin screen displays them:

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

A site can register its own `Contenir\PageCache\CacheControlRepositoryInterface`
service instead. It is then authoritative: `options`, `routes` and `file` are
not used.

[Back to the README](../README.md)
