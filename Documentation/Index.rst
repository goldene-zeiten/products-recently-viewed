..  _start:

=======================
Products Recently Viewed
=======================

:Extension key:
    products_recently_viewed

:Package name:
    goldene-zeiten/products-recently-viewed

:Version:
    |release|

:Language:
    en

:Author:
    Markus Hofmann

:License:
    This document is published under the
    `Creative Commons BY 4.0 <https://creativecommons.org/licenses/by/4.0/>`__
    license.

----

Recently-viewed and most-viewed product tracking for the Products shop system.

----

What it does
============

Once installed, this extension listens for the core's product-detail view and records it. It then offers a
plugin with three modes, selected in the :guilabel:`Recently Viewed Mode` field of the content element:

..  confval:: recent

    The products the current visitor looked at most recently, newest first, kept per visitor in the
    session (up to :confval:`products.recentlyViewed.limit`).

..  confval:: mostviewed

    The site-wide most-viewed products, ranked by a persisted view counter across all visitors.

..  confval:: mostviewedglobal

    The most-viewed products of the logged-in visitor, from a per-user view counter.

Without this extension the core detail page still works; it simply tracks nothing.

Installation
============

..  code-block:: bash

    composer require goldene-zeiten/products-recently-viewed

Add the :guilabel:`Products Recently Viewed` site set to your site, then place the plugin on a page.

Migrating from tt_products
==========================

Legacy ``sys_products_visited_products`` view counters are migrated by this extension's own upgrade
wizard. An installation that upgraded the Products core without this extension is reminded of that by a
notice wizard in the core, which steps aside once this extension is installed.

Settings
========

..  confval:: products.recentlyViewed.limit
    :type: int
    :Default: 10

    Maximum number of recently-viewed products remembered per visitor.

..  confval:: products.mostViewed.limit
    :type: int
    :Default: 10

    Maximum number of products shown in the "most viewed" listings.
