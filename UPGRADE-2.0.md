# Upgrading from 0.x to 2.0

2.0 keeps the 0.1 API, but the package is renamed to
`contenir/contenir-page-cache-mezzio` and the namespace moves from
`Contenir\Cache\Mezzio\` to `Contenir\PageCache\Mezzio\`. The platform and
dependency constraints change, and one configuration value is now read the
way it was documented.

| | 0.x | 2.0 |
| --- | --- | --- |
| PHP | ^8.3 | 8.3, 8.4 or 8.5 |
| `contenir/cache` | ^0.1 | `contenir/contenir-page-cache` ^2.0 |
| `contenir/config` | ^0.1 | `contenir/contenir-config` ^2.1 |

To upgrade, update the constraint:

```bash
composer remove contenir/cache-mezzio contenir/cache \
  && composer require contenir/contenir-page-cache-mezzio:^2.0@RC
```

Every public class, interface, enum and constant keeps its signature under
the new namespace. Update imports and the config provider as described in
[UPGRADE-page-cache.md](UPGRADE-page-cache.md).

## Final classes

Every concrete class is `final`, as it already was in 0.1. To change
behaviour, use the extension points instead of subclassing:

- `ResponseMutatorInterface` for per-request changes to every response,
- `Contenir\Cache\CacheControlRepositoryInterface` (registered in the
  container) for a different source of the admin's state,
- a PSR-16 `CacheInterface` (or laminas-cache `StorageInterface`) service for
  a different store,
- `pagecache.bypass` for requests that must never touch the cache.

## Behaviour change: numeric-string `ttl`

A `pagecache.ttl` given as a numeric string was ignored, so the default of
300 seconds applied:

```php
// 0.1: 'ttl' => getenv('PAGE_CACHE_TTL') ?: 300  with PAGE_CACHE_TTL=600
// stored pages for 300 seconds.
```

2.0 reads it as whole seconds, so the same configuration stores pages for 600
seconds. Integers behave as before. A value that is not a whole number
(`'1.5'`, `'soon'`, `true`) still falls back to 300.

## Dependency floors

`contenir/config` 0.1 is no longer accepted. If another package pins it to
`^0.1`, update that package (or its constraint) first. 0.2 reads files the
same way.

Projects that cannot move yet can stay on `^0.1`, which is maintained on the
`0.x` branch.

## Package renamed

2.0 is published as `contenir/contenir-page-cache-mezzio`. It declares
`conflict` with `contenir/cache-mezzio` and `contenir/contenir-cache-mezzio`,
so old and new can never be installed together. See
[UPGRADE-page-cache.md](UPGRADE-page-cache.md) for the Composer, config and
import changes.
