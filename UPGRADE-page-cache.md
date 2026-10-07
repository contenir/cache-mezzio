# Upgrading to contenir/contenir-page-cache-mezzio

The package is now `contenir/contenir-page-cache-mezzio` and the namespace is
`Contenir\PageCache\Mezzio\`, following the core package's rename to
`contenir/contenir-page-cache` (`Contenir\PageCache\`). The public API, the
`pagecache` configuration key and its options are otherwise unchanged. No
`class_alias` shims are provided, so imports must be updated.

The package declares `conflict` (any version) with `contenir/cache-mezzio`
and `contenir/contenir-cache-mezzio`. It does not replace them, because the
namespace change means it cannot stand in for either. Composer refuses to
install old and new together, so sites switch deliberately, and move to
`contenir/contenir-page-cache` in the same step.

## Composer

```bash
composer remove contenir/contenir-cache-mezzio contenir/contenir-cache \
  && composer require contenir/contenir-page-cache-mezzio
```

If you still require `contenir/cache-mezzio` or `contenir/cache`, remove
those instead.

## Configuration

`laminas/laminas-component-installer` registers the new
`Contenir\PageCache\Mezzio\ConfigProvider`. If you list config providers by
hand, replace `Contenir\Cache\Mezzio\ConfigProvider` in
`config/config.php`. Pipeline entries and container definitions that name
`PageCacheMiddleware` or other adapter classes change the same way.

## Imports

```diff
-use Contenir\Cache\Mezzio\PageCacheMiddleware;
-use Contenir\Cache\CacheControl;
+use Contenir\PageCache\Mezzio\PageCacheMiddleware;
+use Contenir\PageCache\CacheControl;
```

To cover fully qualified class names in code, strings and config across a
project in one pass:

```bash
grep -rlF 'Contenir\Cache\' src config tests | xargs sed -i 's/Contenir\\Cache\\/Contenir\\PageCache\\/g'
```

Configuration files may write the separator doubled (`Contenir\\Cache\\`),
so search for that form too.
