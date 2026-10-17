<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\RecentlyViewed\Tests\Functional\Configuration;

use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Installations without site sets include the TypoScript through a sys_template record, whose
 * "Include static" selector stores the value registered here.
 */
final class StaticTemplateRegistrationTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-recently-viewed',
    ];

    #[Test]
    public function staticTemplateIsSelectableUnderTheExtensionKey(): void
    {
        $values = array_column($GLOBALS['TCA']['sys_template']['columns']['include_static_file']['config']['items'], 'value');

        $this->assertContains('EXT:products_recently_viewed/Configuration/TypoScript', $values);
    }
}
