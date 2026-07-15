<?php

declare(strict_types=1);

use GoldeneZeiten\Products\RecentlyViewed\Controller\RecentlyViewedController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

(static function (): void {
    ExtensionUtility::configurePlugin(
        'ProductsRecentlyViewed',
        'RecentlyViewed',
        [
            RecentlyViewedController::class => 'list, mostViewed, myMostViewed',
        ],
        [
            RecentlyViewedController::class => 'list, myMostViewed',
        ],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
    );
})();
