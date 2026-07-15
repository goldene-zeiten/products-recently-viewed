<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\RecentlyViewed\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Updates\RecentlyViewedMigrationNoticeUpgradeWizard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * The other half of the core-side notice's contract: once this add-on is installed - which is the case
 * throughout this suite - the core's {@see RecentlyViewedMigrationNoticeUpgradeWizard} steps aside even
 * while legacy data is still present, leaving the real migration to this add-on's own wizard.
 */
final class CoreNoticeSteppedAsideTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-recently-viewed',
        'goldene-zeiten/products-legacy-fixture',
    ];

    #[Test]
    public function coreNoticeIsNotNecessaryWhenThisAddonIsInstalled(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CoreNoticeSteppedAsideTest/legacy_visited_products.csv');

        $subject = $this->get(RecentlyViewedMigrationNoticeUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $this->assertFalse($subject->updateNecessary());
    }
}
