# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- Infection mutation testing in CI, MSI 99%.

## [2.0.0] - Unreleased

The public API is unchanged. The major version aligns the package with the
other Contenir 2.x packages: the same supported PHP versions, the shared
php-db QA toolchain and CI. See [UPGRADE-2.0.md](UPGRADE-2.0.md).

### Changed

- Requires PHP 8.3, 8.4 or 8.5 (`~8.3.0 || ~8.4.0 || ~8.5.0`; was `^8.3`).
- `contenir/config` is required at `^0.2 || ^2.0` (was `^0.1`, which excluded
  the current 0.2 release). `contenir/cache` accepts `^0.1 || ^2.0`.
- `LICENSE` names Contenir as the copyright holder, in line with the other
  Contenir packages.
- The local path and VCS repository entries are gone from `composer.json`;
  everything resolves from Packagist.
- The detailed documentation moved from the README into `docs/`.

### Fixed

- A numeric-string `pagecache.ttl` (such as `'600'` from an environment
  variable) was ignored and the 300-second default used. It is now read as
  whole seconds, as the per-path `ttl` option already was.

### Added

- Continuous integration through `php-db/phpdb-qa-tools` on PHP 8.3, 8.4 and
  8.5 against lowest, locked and latest dependencies, with coverage reported
  to Codecov. `composer.lock` is committed.
- 100% line and branch coverage across the unit and integration suites.

### Removed

- The package's own `quality.yml` workflow, replaced by the shared one.

## [0.1.0] - 2026-10-05

- Initial release: `PageCacheMiddleware`, `CachePolicy`, `PageStore`, their
  factories, `LayeredFileRepository` and `ConfigProvider`. A full-page cache
  controlled by the admin's Page Cache screen, with the MVC adapter's
  `cache_with_*` / `make_id_with_*` options and route overrides, response
  vetoes and mutators, and PSR-16 or laminas-cache storage.
