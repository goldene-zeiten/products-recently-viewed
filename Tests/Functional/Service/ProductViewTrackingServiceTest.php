<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\RecentlyViewed\Tests\Functional\Service;

use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\RecentlyViewed\Service\ProductViewTrackingService;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class ProductViewTrackingServiceTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-recently-viewed',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/product_view_tracking.csv');
    }

    #[Test]
    public function recordIncrementsTheSiteWideCounterAcrossAnonymousVisitors(): void
    {
        $subject = $this->get(ProductViewTrackingService::class);

        $subject->record($this->requestFor(0), 1);
        $subject->record($this->requestFor(0), 1);
        $subject->record($this->requestFor(0), 2);

        $this->assertSame(['Product 1', 'Product 2'], $this->titlesOf($subject->getMostViewed(10)));
    }

    #[Test]
    public function mostViewedOrdersByDescendingViewCount(): void
    {
        $subject = $this->get(ProductViewTrackingService::class);

        $subject->record($this->requestFor(0), 2);
        $subject->record($this->requestFor(0), 1);
        $subject->record($this->requestFor(0), 1);

        $this->assertSame(['Product 1', 'Product 2'], $this->titlesOf($subject->getMostViewed(10)));
    }

    #[Test]
    public function anonymousVisitorsNeverGetAPerUserRecord(): void
    {
        $subject = $this->get(ProductViewTrackingService::class);

        $subject->record($this->requestFor(0), 1);

        $this->assertSame([], $subject->getMostViewedByUser($this->requestFor(0), 10));
    }

    #[Test]
    public function identifiedShoppersGetTheirOwnMostViewedListing(): void
    {
        $subject = $this->get(ProductViewTrackingService::class);

        $subject->record($this->requestFor(5), 1);
        $subject->record($this->requestFor(5), 1);
        $subject->record($this->requestFor(5), 2);

        $this->assertSame(['Product 1', 'Product 2'], $this->titlesOf($subject->getMostViewedByUser($this->requestFor(5), 10)));
    }

    #[Test]
    public function perUserListingsAreIndependentBetweenShoppers(): void
    {
        $subject = $this->get(ProductViewTrackingService::class);

        $subject->record($this->requestFor(5), 1);
        $subject->record($this->requestFor(6), 2);

        $this->assertSame(['Product 1'], $this->titlesOf($subject->getMostViewedByUser($this->requestFor(5), 10)));
        $this->assertSame(['Product 2'], $this->titlesOf($subject->getMostViewedByUser($this->requestFor(6), 10)));
    }

    #[Test]
    public function getMostViewedRespectsTheLimit(): void
    {
        $subject = $this->get(ProductViewTrackingService::class);

        $subject->record($this->requestFor(0), 1);
        $subject->record($this->requestFor(0), 2);

        $this->assertCount(1, $subject->getMostViewed(1));
    }

    /**
     * @param Product[] $products
     * @return string[]
     */
    private function titlesOf(array $products): array
    {
        return array_map(static fn(Product $product): string => $product->getTitle(), $products);
    }

    private function requestFor(int $frontendUserUid): ServerRequestInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        if ($frontendUserUid > 0) {
            $frontendUser->user = ['uid' => $frontendUserUid];
        }
        return (new ServerRequest('http://localhost/'))->withAttribute('frontend.user', $frontendUser);
    }
}
