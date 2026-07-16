:navigation-title: Introduction

..  include:: /Includes.rst.txt
..  _introduction:

============
Introduction
============

EXT:products_recently_viewed adds recently-viewed and most-viewed product tracking to
EXT:products_core. Installed on its own it does nothing visible; it only becomes active once a
visitor is shown a product's detail page and its own plugin is placed somewhere on the site.

..  contents:: Table of contents
    :local:

..  _introduction-tracking:

How viewing is tracked
========================

EXT:products_core's product detail action (:php:`GoldeneZeiten\Products\Core\Controller\ProductController::showAction()`)
dispatches a :php:`GoldeneZeiten\Products\Core\Event\ProductViewedEvent` once a product has actually
been shown to the visitor — the core ships this event but has no listener for it itself, so without
this extension nothing is recorded. This extension's
:php:`GoldeneZeiten\Products\RecentlyViewed\EventListener\RecordProductViewListener` listens for
that event and feeds two independent tracking mechanisms:

*   :php:`GoldeneZeiten\Products\RecentlyViewed\Service\RecentlyViewedStorage` — a capped, most-recent-
    first list of product uids kept in the visitor's frontend session only. Nothing is written to the
    database, and nothing is tied to a logged-in account; the list is lost when the session expires.
    Viewing a product already on the list moves it to the front instead of duplicating it.
*   :php:`GoldeneZeiten\Products\RecentlyViewed\Service\ProductViewTrackingService` — persisted,
    cross-session view counters in two database tables: :sql:`tx_products_visitedproduct` (one row
    per product, incremented on every view from any visitor) and
    :sql:`tx_products_fe_users_visitedproduct` (one row per product/frontend-user pair, only written
    when the visitor is logged in).

..  _introduction-plugin:

The plugin and its three modes
================================

The extension registers one content element plugin, :guilabel:`Recently Viewed Products`
(CType :code:`productsrecentlyviewed_recentlyviewed`), placeable anywhere on the site — a sidebar,
a footer, or its own page — independent of the ``ProductDetail`` page (EXT:products_core) it
tracks views from. A :guilabel:`Recently Viewed Mode` field on the element picks which of the two
tracking mechanisms above it renders: the visitor's own session-based recently-viewed list (the
default), their own persisted most-viewed ranking, or the site-wide most-viewed ranking. See
:ref:`Configuration <configuration-plugin>` for the field's three values and the settings that cap
how many products each mode shows.

..  _introduction-when-to-use:

When to use this extension
============================

Install it whenever a shop wants to show visitors the products they recently looked at, or wants a
"most viewed" ranking (site-wide, or per logged-in customer) anywhere on the site. Without it, the
core detail page still works exactly the same for shoppers; it simply tracks nothing, and no
:guilabel:`Recently Viewed Products` element is available in the "insert content element" wizard.
