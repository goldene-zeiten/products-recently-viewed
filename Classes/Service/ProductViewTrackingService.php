<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\RecentlyViewed\Service;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Core\Service\FrontendUserResolver;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Persisted view counters (site-wide and per-user). Per-user entries only for logged-in shoppers.
 */
final class ProductViewTrackingService
{
    private const TABLE_SITE_WIDE = 'tx_products_visitedproduct';
    private const TABLE_PER_USER = 'tx_products_fe_users_visitedproduct';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly ProductRepository $productRepository,
        private readonly FrontendUserResolver $frontendUserResolver
    ) {}

    public function record(ServerRequestInterface $request, int $productUid): void
    {
        $this->incrementSiteWide($productUid);
        $frontendUser = $this->frontendUserResolver->getUid($request);
        if ($frontendUser !== 0) {
            $this->incrementForUser($frontendUser, $productUid);
        }
    }

    /**
     * @return Product[]
     */
    public function getMostViewed(int $limit): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_SITE_WIDE);
        $result = $queryBuilder->select('product')
            ->from(self::TABLE_SITE_WIDE)
            ->orderBy('view_count', 'DESC')
            ->setMaxResults($limit)
            ->executeQuery();
        $productUids = [];
        while (($product = $result->fetchOne()) !== false) {
            $productUids[] = (int)$product;
        }
        return $this->resolveProducts($productUids);
    }

    /**
     * @return Product[]
     */
    public function getMostViewedByUser(ServerRequestInterface $request, int $limit): array
    {
        $frontendUser = $this->frontendUserResolver->getUid($request);
        if ($frontendUser === 0) {
            return [];
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_PER_USER);
        $result = $queryBuilder->select('product')
            ->from(self::TABLE_PER_USER)
            ->where($queryBuilder->expr()->eq('frontend_user', $queryBuilder->createNamedParameter($frontendUser, Connection::PARAM_INT)))
            ->orderBy('view_count', 'DESC')
            ->setMaxResults($limit)
            ->executeQuery();
        $productUids = [];
        while (($product = $result->fetchOne()) !== false) {
            $productUids[] = (int)$product;
        }
        return $this->resolveProducts($productUids);
    }

    private function incrementSiteWide(int $productUid): void
    {
        $this->increment(self::TABLE_SITE_WIDE, [
            'product' => $productUid,
        ]);
    }

    private function incrementForUser(int $frontendUser, int $productUid): void
    {
        $this->increment(self::TABLE_PER_USER, [
            'frontend_user' => $frontendUser,
            'product' => $productUid,
        ]);
    }

    /**
     * Counts a view on the counter row identified by $identity, creating the row on a first view. Two first
     * views of the same product can race: both find no row to update and both insert. The loser's insert
     * hits the primary key, and its view is counted on the row the winner created instead.
     *
     * @param array<string, int> $identity
     */
    private function increment(string $table, array $identity): void
    {
        if ($this->updateCounter($table, $identity) > 0) {
            return;
        }
        $connection = $this->connectionPool->getConnectionForTable($table);
        try {
            // Inside an outer transaction this becomes a savepoint, so the failed insert does not leave a
            // PostgreSQL transaction aborted for the update that follows.
            $connection->transactional(static fn(Connection $connection): int => (int)$connection->insert(
                $table,
                $identity + [
                    'view_count' => 1,
                    'last_viewed' => time(),
                ]
            ));
        } catch (UniqueConstraintViolationException) {
            $this->updateCounter($table, $identity);
        }
    }

    /**
     * @param array<string, int> $identity
     */
    private function updateCounter(string $table, array $identity): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->update($table)
            ->set('view_count', $queryBuilder->quoteIdentifier('view_count') . ' + 1', false)
            ->set('last_viewed', time());
        foreach ($identity as $field => $value) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq($field, $queryBuilder->createNamedParameter($value, Connection::PARAM_INT))
            );
        }

        return (int)$queryBuilder->executeStatement();
    }

    /**
     * @param int[] $productUids
     * @return Product[]
     */
    private function resolveProducts(array $productUids): array
    {
        $products = [];
        foreach ($productUids as $productUid) {
            $product = $this->productRepository->findByUid($productUid);
            if ($product instanceof Product) {
                $products[] = $product;
            }
        }
        return $products;
    }
}
