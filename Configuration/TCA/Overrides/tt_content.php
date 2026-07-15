<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

(static function (): void {
    ExtensionUtility::registerPlugin(
        'ProductsRecentlyViewed',
        'RecentlyViewed',
        'LLL:EXT:products_recently_viewed/Resources/Private/Language/locallang_be.xlf:plugin.recently_viewed',
        'EXT:products_core/Resources/Public/Icons/Extension.svg'
    );

    $newColumnsArray = [
        'tx_products_recentlyviewed_mode' => [
            'label' => 'LLL:EXT:products_recently_viewed/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_recentlyviewed_mode',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'label' => 'LLL:EXT:products_recently_viewed/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_recentlyviewed_mode.recent',
                        'value' => 'recent',
                    ],
                    [
                        'label' => 'LLL:EXT:products_recently_viewed/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_recentlyviewed_mode.mostviewed',
                        'value' => 'mostviewed',
                    ],
                    [
                        'label' => 'LLL:EXT:products_recently_viewed/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_recentlyviewed_mode.mostviewedglobal',
                        'value' => 'mostviewedglobal',
                    ],
                ],
                'default' => 'recent',
            ],
        ],
    ];

    ExtensionManagementUtility::addTCAcolumns('tt_content', $newColumnsArray);

    ExtensionManagementUtility::addToAllTCAtypes(
        'tt_content',
        'tx_products_recentlyviewed_mode',
        'productsrecentlyviewed_recentlyviewed',
    );
})();
