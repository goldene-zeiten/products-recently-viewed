<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\RecentlyViewed\Service;

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
        $productUids = $queryBuilder->select('product')
            ->from(self::TABLE_SITE_WIDE)
            ->orderBy('view_count', 'DESC')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchFirstColumn();
        return $this->resolveProducts(array_map('intval', $productUids));
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
        $productUids = $queryBuilder->select('product')
            ->from(self::TABLE_PER_USER)
            ->where($queryBuilder->expr()->eq('frontend_user', $queryBuilder->createNamedParameter($frontendUser, Connection::PARAM_INT)))
            ->orderBy('view_count', 'DESC')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchFirstColumn();
        return $this->resolveProducts(array_map('intval', $productUids));
    }

    private function incrementSiteWide(int $productUid): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_SITE_WIDE);
        $affectedRows = $queryBuilder->update(self::TABLE_SITE_WIDE)
            ->set('view_count', $queryBuilder->quoteIdentifier('view_count') . ' + 1', false)
            ->set('last_viewed', time())
            ->where($queryBuilder->expr()->eq('product', $queryBuilder->createNamedParameter($productUid, Connection::PARAM_INT)))
            ->executeStatement();
        if ($affectedRows === 0) {
            $this->connectionPool->getConnectionForTable(self::TABLE_SITE_WIDE)->insert(self::TABLE_SITE_WIDE, [
                'product' => $productUid,
                'view_count' => 1,
                'last_viewed' => time(),
            ]);
        }
    }

    private function incrementForUser(int $frontendUser, int $productUid): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_PER_USER);
        $affectedRows = $queryBuilder->update(self::TABLE_PER_USER)
            ->set('view_count', $queryBuilder->quoteIdentifier('view_count') . ' + 1', false)
            ->set('last_viewed', time())
            ->where(
                $queryBuilder->expr()->eq('frontend_user', $queryBuilder->createNamedParameter($frontendUser, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('product', $queryBuilder->createNamedParameter($productUid, Connection::PARAM_INT))
            )
            ->executeStatement();
        if ($affectedRows === 0) {
            $this->connectionPool->getConnectionForTable(self::TABLE_PER_USER)->insert(self::TABLE_PER_USER, [
                'frontend_user' => $frontendUser,
                'product' => $productUid,
                'view_count' => 1,
                'last_viewed' => time(),
            ]);
        }
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
