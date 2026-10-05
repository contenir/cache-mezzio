# Storage and purging

Which caches the middleware can use, why it never passes per-item TTLs to laminas-cache, and how the admin purges pages.

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

[Back to the README](../README.md)
