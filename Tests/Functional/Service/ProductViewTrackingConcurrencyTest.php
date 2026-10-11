<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\RecentlyViewed\Tests\Functional\Service;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Core\Event\ProductViewedEvent;
use GoldeneZeiten\Products\RecentlyViewed\EventListener\RecordProductViewListener;
use GoldeneZeiten\Products\RecentlyViewed\Service\ProductViewTrackingService;
use GoldeneZeiten\Products\RecentlyViewed\Service\RecentlyViewedStorage;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Simulates a concurrent request with a database trigger, because two PHP requests cannot be interleaved
 * in a test. The trigger acts on the counter UPDATE, so it is only portable to platforms whose triggers can
 * skip or fail a row update: SQLite and PostgreSQL.
 */
final class ProductViewTrackingConcurrencyTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-recently-viewed',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $platform = $this->connection()->getDatabasePlatform();
        if (!$platform instanceof SQLitePlatform && !$platform instanceof PostgreSQLPlatform) {
            $this->markTestSkipped('Only SQLite and PostgreSQL triggers can skip a row update.');
        }
        $this->importCSVDataSet(__DIR__ . '/Fixtures/product_view_tracking.csv');
        $this->connection()->executeStatement('CREATE TABLE tx_race_flag (armed int)');
    }

    protected function tearDown(): void
    {
        $platform = $this->connection()->getDatabasePlatform();
        if ($platform instanceof SQLitePlatform || $platform instanceof PostgreSQLPlatform) {
            $this->connection()->executeStatement('DROP TRIGGER IF EXISTS tx_race_trigger' . ($platform instanceof PostgreSQLPlatform ? ' ON tx_products_visitedproduct' : ''));
            $this->connection()->executeStatement('DROP TABLE IF EXISTS tx_race_flag');
            if ($platform instanceof PostgreSQLPlatform) {
                $this->connection()->executeStatement('DROP FUNCTION IF EXISTS tx_race_trigger()');
            }
        }
        parent::tearDown();
    }

    #[Test]
    public function aFirstViewRacingAnotherFirstViewIsCountedOnTheRowTheOtherCreated(): void
    {
        $this->connection()->insert('tx_products_visitedproduct', [
            'product' => 1,
            'view_count' => 5,
            'last_viewed' => 0,
        ]);
        // The row exists, but the next counter UPDATE does not see it - exactly what happens when another
        // request inserts the row between this request's UPDATE and its INSERT.
        $this->connection()->insert('tx_race_flag', ['armed' => 1]);
        $this->createSkipFirstUpdateTrigger();

        $this->get(ProductViewTrackingService::class)->record($this->request(), 1);

        $this->assertSame(6, $this->viewCount(1));
    }

    #[Test]
    public function aFailingViewCounterDoesNotBreakTheProductPage(): void
    {
        $this->connection()->insert('tx_products_visitedproduct', [
            'product' => 1,
            'view_count' => 5,
            'last_viewed' => 0,
        ]);
        $this->createFailingUpdateTrigger();
        $product = $this->get(ProductRepository::class)->findByUid(1);
        \assert($product instanceof Product);
        $request = $this->request();

        $this->get(RecordProductViewListener::class)(new ProductViewedEvent($product, $request));

        $this->assertSame([1], $this->get(RecentlyViewedStorage::class)->load($request));
    }

    private function createSkipFirstUpdateTrigger(): void
    {
        if ($this->connection()->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->connection()->executeStatement(
                'CREATE FUNCTION tx_race_trigger() RETURNS trigger AS $$ BEGIN'
                . ' IF EXISTS (SELECT 1 FROM tx_race_flag) THEN DELETE FROM tx_race_flag; RETURN NULL; END IF;'
                . ' RETURN NEW; END; $$ LANGUAGE plpgsql'
            );
            $this->connection()->executeStatement(
                'CREATE TRIGGER tx_race_trigger BEFORE UPDATE ON tx_products_visitedproduct'
                . ' FOR EACH ROW EXECUTE PROCEDURE tx_race_trigger()'
            );
            return;
        }
        $this->connection()->executeStatement(
            'CREATE TRIGGER tx_race_trigger BEFORE UPDATE ON tx_products_visitedproduct'
            . ' WHEN EXISTS (SELECT 1 FROM tx_race_flag)'
            . ' BEGIN DELETE FROM tx_race_flag; SELECT RAISE(IGNORE); END'
        );
    }

    private function createFailingUpdateTrigger(): void
    {
        if ($this->connection()->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->connection()->executeStatement(
                'CREATE FUNCTION tx_race_trigger() RETURNS trigger AS $$ BEGIN'
                . ' RAISE EXCEPTION \'view counter unavailable\'; END; $$ LANGUAGE plpgsql'
            );
            $this->connection()->executeStatement(
                'CREATE TRIGGER tx_race_trigger BEFORE UPDATE ON tx_products_visitedproduct'
                . ' FOR EACH ROW EXECUTE PROCEDURE tx_race_trigger()'
            );
            return;
        }
        $this->connection()->executeStatement(
            'CREATE TRIGGER tx_race_trigger BEFORE UPDATE ON tx_products_visitedproduct'
            . ' BEGIN SELECT RAISE(ABORT, \'view counter unavailable\'); END'
        );
    }

    private function viewCount(int $productUid): int
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tx_products_visitedproduct');

        return (int)$queryBuilder
            ->select('view_count')
            ->from('tx_products_visitedproduct')
            ->where($queryBuilder->expr()->eq('product', $queryBuilder->createNamedParameter($productUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
    }

    private function connection(): Connection
    {
        return $this->getConnectionPool()->getConnectionForTable('tx_products_visitedproduct');
    }

    private function request(): ServerRequestInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();

        return (new ServerRequest('http://localhost/'))->withAttribute('frontend.user', $frontendUser);
    }
}
