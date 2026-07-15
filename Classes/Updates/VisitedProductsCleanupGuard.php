<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\RecentlyViewed\Updates;

use GoldeneZeiten\Products\Core\Updates\Cleanup\LegacyCleanupGuardInterface;
use GoldeneZeiten\Products\Core\Updates\LegacyMigrationHelper;

/**
 * Stops the core's legacy-table cleanup from dropping the legacy visited-product tables while this
 * extension still has counters left to migrate into its own tables. Once {@see
 * \GoldeneZeiten\Products\RecentlyViewed\Updates\TtProductsVisitedProductsUpgradeWizard} has run, this
 * reports no objection and the cleanup proceeds.
 */
final readonly class VisitedProductsCleanupGuard implements LegacyCleanupGuardInterface
{
    private const LEGACY_GLOBAL_TABLE = 'sys_products_visited_products';
    private const LEGACY_MM_TABLE = 'sys_products_fe_users_mm_visited_products';
    private const LOCAL_GLOBAL_TABLE = 'tx_products_visitedproduct';
    private const LOCAL_MM_TABLE = 'tx_products_fe_users_visitedproduct';

    public function __construct(
        private LegacyMigrationHelper $migrationHelper,
    ) {}

    public function blockingReason(): ?string
    {
        $pending = 0;
        if ($this->migrationHelper->tablesExist(self::LEGACY_GLOBAL_TABLE)) {
            $pending += $this->migrationHelper->countUnmigrated(self::LEGACY_GLOBAL_TABLE, self::LOCAL_GLOBAL_TABLE, false);
        }
        if ($this->migrationHelper->tablesExist(self::LEGACY_MM_TABLE)) {
            $pending += $this->migrationHelper->countUnmigrated(self::LEGACY_MM_TABLE, self::LOCAL_MM_TABLE);
        }

        if ($pending === 0) {
            return null;
        }

        return sprintf(
            'the "goldene-zeiten/products-recently-viewed" extension still has %d visited-product counter(s) '
            . 'to migrate - run its "%s" upgrade wizard first.',
            $pending,
            'products_ttProductsVisitedProductsMigration',
        );
    }
}
