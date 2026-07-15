<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\RecentlyViewed\Tests\Functional\Updates;

use GoldeneZeiten\Products\RecentlyViewed\Updates\TtProductsVisitedProductsUpgradeWizard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

final class TtProductsVisitedProductsUpgradeWizardTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-recently-viewed',
        'goldene-zeiten/products-legacy-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
    }

    private function subject(BufferedOutput $output): TtProductsVisitedProductsUpgradeWizard
    {
        $subject = $this->get(TtProductsVisitedProductsUpgradeWizard::class);
        $subject->setOutput($output);
        return $subject;
    }

    #[Test]
    public function updateIsNecessaryWithGlobalAndPerUserCounters(): void
    {
        $output = new BufferedOutput();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsVisitedProductsUpgradeWizardTest/visited_products.csv');
        $subject = $this->subject($output);
        $this->assertTrue($subject->updateNecessary());
    }

    #[Test]
    public function executeUpdateMigratesGlobalAndPerUserCountersWithRemappedProductUids(): void
    {
        $output = new BufferedOutput();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsVisitedProductsUpgradeWizardTest/visited_products.csv');
        $subject = $this->subject($output);
        $this->assertTrue($subject->executeUpdate());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/TtProductsVisitedProductsUpgradeWizardTest/Result/visited_products_migrated.csv');
    }

    #[Test]
    public function executeUpdateIsIdempotent(): void
    {
        $output = new BufferedOutput();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsVisitedProductsUpgradeWizardTest/visited_products.csv');
        $subject = $this->subject($output);
        $this->assertTrue($subject->executeUpdate());

        $this->assertFalse($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/TtProductsVisitedProductsUpgradeWizardTest/Result/visited_products_migrated.csv');
    }

    #[Test]
    public function existingNewTableRowGetsCountsMergedAdditively(): void
    {
        $output = new BufferedOutput();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsVisitedProductsUpgradeWizardTest/visited_products_with_existing.csv');
        $subject = $this->subject($output);
        $this->assertTrue($subject->executeUpdate());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/TtProductsVisitedProductsUpgradeWizardTest/Result/visited_products_merged.csv');
    }

    #[Test]
    public function legacyRowPointingAtUnmigratedProductIsSkipped(): void
    {
        $output = new BufferedOutput();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsVisitedProductsUpgradeWizardTest/visited_products_with_orphan.csv');
        $subject = $this->subject($output);
        $this->assertTrue($subject->executeUpdate());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/TtProductsVisitedProductsUpgradeWizardTest/Result/visited_products_orphan_skipped.csv');
        $this->assertStringContainsString('missing product uid 999', $output->fetch());
        $this->assertFalse($subject->updateNecessary());
    }

    #[Test]
    public function executeUpdateReturnsFalseWhenProductsArentMigratedYet(): void
    {
        $output = new BufferedOutput();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsVisitedProductsUpgradeWizardTest/visited_products.csv');
        $subject = $this->subject($output);
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsVisitedProductsUpgradeWizardTest/unmigrated_product.csv');

        $this->assertFalse($subject->executeUpdate());
        $this->assertStringContainsString('products_ttProductsProductMigration', $output->fetch());
    }
}
