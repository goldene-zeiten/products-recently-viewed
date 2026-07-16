:navigation-title: Configuration

..  include:: /Includes.rst.txt
..  _configuration:

=============
Configuration
=============

..  contents:: Table of contents
    :local:

..  _configuration-site-set:

Site set
========

Activate the :guilabel:`Products Recently Viewed` site set (``goldene-zeiten/products-recently-viewed``)
on every site that should track and show recently/most-viewed products, then adjust its settings
under :guilabel:`Site Management > Sites > Edit settings`.

..  confval:: products.recentlyViewed.limit
    :type: int
    :Default: 10

    Maximum number of products kept in a visitor's session-based recently-viewed list (the
    :rst:dir:`recent` plugin mode). The oldest entry is dropped once the list would exceed this
    size; viewing a product already on the list does not count against it, since it is moved to the
    front instead of added again.

..  confval:: products.mostViewed.limit
    :type: int
    :Default: 10

    Maximum number of products shown in the two persisted "most viewed" listings (the
    :rst:dir:`mostviewed` and :rst:dir:`mostviewedglobal` plugin modes). Applies to both listings
    independently — it is not a combined cap across the two.

..  note::
    A guest's session-based recently-viewed list is only written to the frontend session once the
    visitor's browser already carries a confirmed session cookie from an earlier request, when
    EXT:products_core's own `products.session.requireCookieConsent` site setting is enabled — off
    by default. This extension's :php:`GoldeneZeiten\Products\RecentlyViewed\Service\RecentlyViewedStorage`
    reads that core setting; it does not declare a setting of its own for it.

..  _configuration-plugin:

The plugin: Recently Viewed Products
======================================

Placing the :guilabel:`Recently Viewed Products` content element (:guilabel:`Insert Content
Element` wizard) shows one of three listings, picked with its :guilabel:`Recently Viewed Mode`
field:

..  confval:: recent

    :guilabel:`My recently viewed`, the default. The current visitor's own session-based
    recently-viewed list, most recently viewed first, capped by `products.recentlyViewed.limit`.
    Nothing is stored in the database; the list is lost when the session expires and is never
    shared across devices, even for a logged-in customer.

..  confval:: mostviewed

    :guilabel:`My most viewed` — the current logged-in visitor's own persisted most-viewed ranking,
    capped by `products.mostViewed.limit`. Always empty for anonymous visitors, since per-user view
    counters only exist for an identified frontend-user account.

..  confval:: mostviewedglobal

    :guilabel:`Site-wide most viewed` — every visitor's views count towards one shared ranking,
    capped by `products.mostViewed.limit`, independent of login state.

..  _configuration-storage:

How views are stored
=====================

*   The session-based recently-viewed list (:rst:dir:`recent` mode) lives entirely in the FE
    session — nothing in the database, nothing tied to a customer account. It is a plain FIFO list
    of product uids on the frontend user's session, keyed :code:`tx_products_recentlyViewed`.
*   Both "most viewed" rankings are persisted counters, incremented on every product view (via the
    core's :php:`ProductViewedEvent`, see :ref:`Introduction <introduction-tracking>`):
    :sql:`tx_products_visitedproduct` (site-wide, one row per product) and
    :sql:`tx_products_fe_users_visitedproduct` (per logged-in user, one row per product/user pair —
    never written for anonymous visitors). Both tables track a :sql:`view_count` and a
    :sql:`last_viewed` timestamp per row; ranking is by :sql:`view_count` only.
