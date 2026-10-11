# TYPO3 extension `products_recently_viewed`

> **This repository is READ-ONLY.**
> It is split automatically out of the [goldene-zeiten/products](https://github.com/goldene-zeiten/products)
> monorepo, which is the single source of truth. Pull requests and commits made
> here are overwritten by the next split — please open them in the monorepo instead.

Recently-viewed and most-viewed products for the [Products](https://github.com/goldene-zeiten/products-core)
shop system. Every product detail view is recorded twice: in a capped, session-only list of the visitor's
recently viewed products, and in persisted view counters per product and per logged-in customer. A content
element shows either the visitor's recently viewed products, their own most-viewed products or the site-wide
most-viewed ranking. An upgrade wizard migrates the visited-product counters of a legacy `tt_products`
installation.

## Installation

```shell
composer require goldene-zeiten/products-recently-viewed
```

Add the "Products Recently Viewed" site set to your site and place the "Recently Viewed Products" content
element wherever it should be shown.

## Documentation

See `Documentation/` (rendered on the TYPO3 documentation site) for installation, the element's modes and
its settings, and the migration from `tt_products`.

## Requirements

- TYPO3 13.4 LTS or 14.3 LTS
- PHP 8.2 or newer
- `goldene-zeiten/products-core`

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
