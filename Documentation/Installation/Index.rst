:navigation-title: Installation

..  include:: /Includes.rst.txt
..  _installation:

============
Installation
============

..  _installation-requirements:

Requirements
============

*   TYPO3 13.4 LTS or 14.3
*   PHP 8.2, 8.3, 8.4 or 8.5
*   EXT:products_core (``goldene-zeiten/products-core``) ^1.0

..  _installation-composer:

Installation with Composer
===========================

..  code-block:: bash

    composer require goldene-zeiten/products-recently-viewed

Then activate the :guilabel:`Products Recently Viewed` site set (``goldene-zeiten/products-recently-viewed``)
on the site(s) that should track and show recently/most-viewed products, and place the
:guilabel:`Recently Viewed Products` content element wherever it should be shown — e.g. a sidebar,
independent of the :rst:dir:`ProductDetail` page. See :ref:`Configuration <configuration>` for the
element's mode field and the settings that cap how many products are kept/shown.

..  _installation-migrating:

Migrating from tt_products
============================

If the legacy ``tt_products`` extension's visited-product tables
(:sql:`sys_products_visited_products` and :sql:`sys_products_fe_users_mm_visited_products`) are
still present, this extension's own upgrade wizard
(``products_ttProductsVisitedProductsMigration``, :guilabel:`Admin Tools > Upgrade`) migrates their
counters into :sql:`tx_products_visitedproduct`/:sql:`tx_products_fe_users_visitedproduct`,
remapping legacy product uids to the ones already migrated by EXT:products_core's own wizards — run
the core's product migration wizard first. The wizard is idempotent (safe to run more than once) and
adds legacy counts to any already-migrated row instead of overwriting it.

..  note::
    An installation that upgraded EXT:products_core but does **not** have this extension installed
    still sees a reminder about these legacy tables, from the core's own
    :php:`GoldeneZeiten\Products\Core\Updates\RecentlyViewedMigrationNoticeUpgradeWizard`. That
    notice wizard steps aside — reports nothing to do — as soon as this extension is installed,
    since migrating the counters then becomes this extension's own responsibility.

The core's legacy-table cleanup wizard, which drops the ``tt_products`` tables once everything has
been migrated, refuses to drop the visited-product tables while this extension still has counters
left to migrate — via its own
:php:`GoldeneZeiten\Products\RecentlyViewed\Updates\VisitedProductsCleanupGuard`. Run this
extension's migration wizard, then the cleanup wizard.
