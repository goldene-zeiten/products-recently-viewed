<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\RecentlyViewed\Updates;

use GoldeneZeiten\Products\Core\Updates\LegacyMigrationHelper;
use GoldeneZeiten\Products\Core\Updates\Prerequisites\ProductMigrationPrerequisite;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
// TODO: Migrate to TYPO3\CMS\Core\Attribute\UpgradeWizard once v13 support is dropped.
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\ChattyInterface;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\RepeatableInterface;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Migrates `sys_products_visited_products` (global view counts) and
 * `sys_products_fe_users_mm_visited_products` (per-user view counts) to
 * `tx_products_visitedproduct` and `tx_products_fe_users_visitedproduct`, remapping product uids.
 *
 * Public, because the Install Tool instantiates upgrade wizards through makeInstance, which only
 * injects dependencies into a public service.
 */
#[Autoconfigure(public: true)]
#[UpgradeWizard('products_ttProductsVisitedProductsMigration')]
final class TtProductsVisitedProductsUpgradeWizard implements UpgradeWizardInterface, ChattyInterface, RepeatableInterface
{
    private const LEGACY_GLOBAL_TABLE = 'sys_products_visited_products';
    private const LEGACY_MM_TABLE = 'sys_products_fe_users_mm_visited_products';
    private const LOCAL_GLOBAL_TABLE = 'tx_products_visitedproduct';
    private const LOCAL_MM_TABLE = 'tx_products_fe_users_visitedproduct';
    private const LEGACY_PRODUCT_TABLE = 'tt_products';
    private const LOCAL_PRODUCT_TABLE = 'tx_products_domain_model_product';

    private OutputInterface $output;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly LegacyMigrationHelper $migrationHelper,
        private readonly ProductMigrationPrerequisite $productMigrationPrerequisite,
    ) {}

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function getTitle(): string
    {
        return 'Migrate tt_products visited-product counters';
    }

    public function getDescription(): string
    {
        return 'Copies legacy visit-count data from sys_products_visited_products and '
            . 'sys_products_fe_users_mm_visited_products into the new extension\'s '
            . 'tx_products_visitedproduct and tx_products_fe_users_visitedproduct tables, '
            . 'remapping legacy product uids to migrated ones.';
    }

    public function updateNecessary(): bool
    {
        if ($this->migrationHelper->tablesExist(self::LEGACY_GLOBAL_TABLE)) {
            if ($this->migrationHelper->countUnmigrated(self::LEGACY_GLOBAL_TABLE, self::LOCAL_GLOBAL_TABLE, false) > 0) {
                return true;
            }
        }
        if ($this->migrationHelper->tablesExist(self::LEGACY_MM_TABLE)) {
            if ($this->migrationHelper->countUnmigrated(self::LEGACY_MM_TABLE, self::LOCAL_MM_TABLE) > 0) {
                return true;
            }
        }
        return false;
    }

    public function executeUpdate(): bool
    {
        if (!$this->productMigrationPrerequisite->isFulfilled()) {
            $this->output->writeln(
                '<error>tt_products still has unmigrated rows; run the product migration '
                    . 'wizard (products_ttProductsProductMigration) first.</error>'
            );
            return false;
        }

        if ($this->migrationHelper->tablesExist(self::LEGACY_GLOBAL_TABLE)) {
            while (($rows = $this->migrationHelper->fetchUnmigratedBatch(self::LEGACY_GLOBAL_TABLE, self::LOCAL_GLOBAL_TABLE, false)) !== []) {
                foreach ($rows as $row) {
                    $this->migrateGlobalRow($row);
                }
            }
        }

        if ($this->migrationHelper->tablesExist(self::LEGACY_MM_TABLE)) {
            while (($rows = $this->migrationHelper->fetchUnmigratedBatch(self::LEGACY_MM_TABLE, self::LOCAL_MM_TABLE)) !== []) {
                foreach ($rows as $row) {
                    $this->migrateMmRow($row);
                }
            }
        }

        return true;
    }

    /**
     * @return string[]
     */
    public function getPrerequisites(): array
    {
        return [DatabaseUpdatedPrerequisite::class, ProductMigrationPrerequisite::class];
    }

    /**
     * The legacy global table has no own identity: its `uid` column holds the product uid it counts.
     *
     * @param array<string, mixed> $legacyRow
     */
    private function migrateGlobalRow(array $legacyRow): void
    {
        $legacyProductUid = (int)$legacyRow['uid'];
        $productLocalUid = $this->migrationHelper->resolveLocalUid(
            self::LEGACY_PRODUCT_TABLE,
            $legacyProductUid,
            self::LOCAL_PRODUCT_TABLE
        );
        if ($productLocalUid === null) {
            $this->output->writeln(sprintf(
                '<comment>sys_products_visited_products counted missing product uid %d, skipped.</comment>',
                $legacyProductUid
            ));
            $this->migrationHelper->recordMapping(self::LEGACY_GLOBAL_TABLE, $legacyProductUid, self::LOCAL_GLOBAL_TABLE, 0);
            return;
        }
        $this->upsertGlobalRow($productLocalUid, (int)$legacyRow['qty'], (int)$legacyRow['tstamp']);
        $this->migrationHelper->recordMapping(self::LEGACY_GLOBAL_TABLE, $legacyProductUid, self::LOCAL_GLOBAL_TABLE, $productLocalUid);
    }

    /**
     * @param array<string, mixed> $legacyRow
     */
    private function migrateMmRow(array $legacyRow): void
    {
        $legacyMmUid = (int)$legacyRow['uid'];
        $feUserUid = (int)$legacyRow['uid_local'];
        if ($feUserUid === 0) {
            $this->output->writeln(sprintf(
                '<comment>sys_products_fe_users_mm_visited_products uid %d has no frontend user, skipped.</comment>',
                $legacyMmUid
            ));
            $this->migrationHelper->recordMapping(self::LEGACY_MM_TABLE, $legacyMmUid, self::LOCAL_MM_TABLE, 0);
            return;
        }
        $legacyProductUid = (int)$legacyRow['uid_foreign'];
        $productLocalUid = $this->migrationHelper->resolveLocalUid(
            self::LEGACY_PRODUCT_TABLE,
            $legacyProductUid,
            self::LOCAL_PRODUCT_TABLE
        );
        if ($productLocalUid === null) {
            $this->output->writeln(sprintf(
                '<comment>sys_products_fe_users_mm_visited_products uid %d referenced missing product uid %d, skipped.</comment>',
                $legacyMmUid,
                $legacyProductUid
            ));
            $this->migrationHelper->recordMapping(self::LEGACY_MM_TABLE, $legacyMmUid, self::LOCAL_MM_TABLE, 0);
            return;
        }
        $this->upsertMmRow($feUserUid, $productLocalUid, (int)$legacyRow['qty'], (int)$legacyRow['tstamp']);
        $this->migrationHelper->recordMapping(self::LEGACY_MM_TABLE, $legacyMmUid, self::LOCAL_MM_TABLE, $productLocalUid);
    }

    private function upsertGlobalRow(int $productUid, int $legacyQty, int $legacyTstamp): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::LOCAL_GLOBAL_TABLE);
        $existing = $queryBuilder->select('view_count', 'last_viewed')
            ->from(self::LOCAL_GLOBAL_TABLE)
            ->where($queryBuilder->expr()->eq(
                'product',
                $queryBuilder->createNamedParameter($productUid, Connection::PARAM_INT)
            ))
            ->executeQuery()
            ->fetchAssociative();

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::LOCAL_GLOBAL_TABLE);
        if ($existing !== false) {
            $queryBuilder->update(self::LOCAL_GLOBAL_TABLE)
                ->set('view_count', (int)$existing['view_count'] + $legacyQty)
                ->set('last_viewed', max((int)$existing['last_viewed'], $legacyTstamp))
                ->where($queryBuilder->expr()->eq(
                    'product',
                    $queryBuilder->createNamedParameter($productUid, Connection::PARAM_INT)
                ))
                ->executeStatement();
        } else {
            $queryBuilder->insert(self::LOCAL_GLOBAL_TABLE)
                ->values([
                    'product' => $productUid,
                    'view_count' => $legacyQty,
                    'last_viewed' => $legacyTstamp,
                ])
                ->executeStatement();
        }
    }

    private function upsertMmRow(int $feUserUid, int $productUid, int $legacyQty, int $legacyTstamp): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::LOCAL_MM_TABLE);
        $existing = $queryBuilder->select('view_count', 'last_viewed')
            ->from(self::LOCAL_MM_TABLE)
            ->where(
                $queryBuilder->expr()->eq(
                    'frontend_user',
                    $queryBuilder->createNamedParameter($feUserUid, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'product',
                    $queryBuilder->createNamedParameter($productUid, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAssociative();

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::LOCAL_MM_TABLE);
        if ($existing !== false) {
            $queryBuilder->update(self::LOCAL_MM_TABLE)
                ->set('view_count', (int)$existing['view_count'] + $legacyQty)
                ->set('last_viewed', max((int)$existing['last_viewed'], $legacyTstamp))
                ->where(
                    $queryBuilder->expr()->eq(
                        'frontend_user',
                        $queryBuilder->createNamedParameter($feUserUid, Connection::PARAM_INT)
                    ),
                    $queryBuilder->expr()->eq(
                        'product',
                        $queryBuilder->createNamedParameter($productUid, Connection::PARAM_INT)
                    )
                )
                ->executeStatement();
        } else {
            $queryBuilder->insert(self::LOCAL_MM_TABLE)
                ->values([
                    'frontend_user' => $feUserUid,
                    'product' => $productUid,
                    'view_count' => $legacyQty,
                    'last_viewed' => $legacyTstamp,
                ])
                ->executeStatement();
        }
    }
}
