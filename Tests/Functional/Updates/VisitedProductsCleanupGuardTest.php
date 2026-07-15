<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\RecentlyViewed\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Updates\Cleanup\LegacyCleanupGuardRegistry;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Proves the add-on's cleanup guard is picked up by the core registry and stops the core legacy-table
 * cleanup while this extension's visited-product migration has not run - the protection that keeps core
 * from dropping legacy data another extension still owes a migration for.
 */
final class VisitedProductsCleanupGuardTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-recently-viewed',
        'goldene-zeiten/products-legacy-fixture',
    ];

    #[Test]
    public function nothingBlocksCleanupWhenNoLegacyDataIsPending(): void
    {
        $this->assertSame([], $this->get(LegacyCleanupGuardRegistry::class)->blockingReasons());
    }

    #[Test]
    public function blocksCleanupWhileVisitedProductsAreUnmigrated(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/VisitedProductsCleanupGuardTest/unmigrated.csv');

        $reasons = $this->get(LegacyCleanupGuardRegistry::class)->blockingReasons();

        $this->assertCount(1, $reasons);
        $this->assertStringContainsString('products-recently-viewed', $reasons[0]);
    }
}
