<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\RecentlyViewed\Controller;

use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\RecentlyViewed\Service\ProductViewTrackingService;
use GoldeneZeiten\Products\RecentlyViewed\Service\RecentlyViewedStorage;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class RecentlyViewedController extends ActionController
{
    private const DEFAULT_MOST_VIEWED_LIMIT = 10;

    public function __construct(
        private readonly RecentlyViewedStorage $recentlyViewedStorage,
        private readonly ProductViewTrackingService $productViewTrackingService,
        private readonly ProductRepository $productRepository
    ) {}

    public function listAction(): ResponseInterface
    {
        $mode = (string)($this->request->getAttribute('currentContentObject')?->data['tx_products_recentlyviewed_mode'] ?? 'recent');
        $products = $this->resolveProductsByMode($mode);
        $this->view->assign('products', $products);
        return $this->htmlResponse();
    }

    /**
     * Site-wide "most viewed" listing - independent of session/login state.
     */
    public function mostViewedAction(): ResponseInterface
    {
        $this->view->assign('products', $this->getMostViewedProducts());
        return $this->htmlResponse();
    }

    /**
     * Per-user "most viewed" listing (empty for guests).
     */
    public function myMostViewedAction(): ResponseInterface
    {
        $this->view->assign('products', $this->getMyMostViewedProducts());
        return $this->htmlResponse();
    }

    /**
     * @return Product[]
     */
    private function resolveProductsByMode(string $mode): array
    {
        return match ($mode) {
            'mostviewed' => $this->getMyMostViewedProducts(),
            'mostviewedglobal' => $this->getMostViewedProducts(),
            default => $this->getRecentlyViewedProducts(),
        };
    }

    /**
     * @return Product[]
     */
    private function getRecentlyViewedProducts(): array
    {
        $products = [];
        foreach ($this->recentlyViewedStorage->load($this->request) as $productUid) {
            $product = $this->productRepository->findByUid($productUid);
            if ($product instanceof Product) {
                $products[] = $product;
            }
        }
        return $products;
    }

    /**
     * @return Product[]
     */
    private function getMyMostViewedProducts(): array
    {
        return $this->productViewTrackingService->getMostViewedByUser($this->request, $this->mostViewedLimit());
    }

    /**
     * @return Product[]
     */
    private function getMostViewedProducts(): array
    {
        return $this->productViewTrackingService->getMostViewed($this->mostViewedLimit());
    }

    private function mostViewedLimit(): int
    {
        $limit = $this->settings['mostViewed']['limit'] ?? self::DEFAULT_MOST_VIEWED_LIMIT;
        return max(1, (int)$limit);
    }
}
