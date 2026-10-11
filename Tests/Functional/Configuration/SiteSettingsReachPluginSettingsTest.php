<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\RecentlyViewed\Tests\Functional\Configuration;

use GoldeneZeiten\Products\Testing\AbstractSiteSettingsMappingTestCase;

/**
 * The recently-viewed settings reach the core catalog settings the recently-viewed plugin inherits.
 */
final class SiteSettingsReachPluginSettingsTest extends AbstractSiteSettingsMappingTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-recently-viewed',
    ];

    protected static function definitionsFile(): string
    {
        return __DIR__ . '/../../../Configuration/Sets/RecentlyViewed/settings.definitions.yaml';
    }

    protected static function siteSets(): array
    {
        return [
            'goldene-zeiten/products-recently-viewed',
        ];
    }

    protected static function staticTemplate(): array
    {
        return [
            'constants' => [
                'EXT:products_recently_viewed/Configuration/TypoScript/constants.typoscript',
            ],
            'setup' => [
                'EXT:products_recently_viewed/Configuration/TypoScript/setup.typoscript',
            ],
        ];
    }
}
