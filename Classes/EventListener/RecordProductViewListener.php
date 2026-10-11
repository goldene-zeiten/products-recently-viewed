<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\RecentlyViewed\EventListener;

use GoldeneZeiten\Products\Core\Event\ProductViewedEvent;
use GoldeneZeiten\Products\RecentlyViewed\Service\ProductViewTrackingService;
use GoldeneZeiten\Products\RecentlyViewed\Service\RecentlyViewedStorage;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Records a product view for both the per-visitor "recently viewed" list and the persisted view counters,
 * once the core detail controller has shown a product. Installing this extension is what makes the core's
 * {@see ProductViewedEvent} actually track anything.
 */
final readonly class RecordProductViewListener
{
    public function __construct(
        private RecentlyViewedStorage $recentlyViewedStorage,
        private ProductViewTrackingService $productViewTrackingService,
        private LoggerInterface $logger,
    ) {}

    #[AsEventListener]
    public function __invoke(ProductViewedEvent $event): void
    {
        $productUid = $event->getProduct()->getUid();
        if ($productUid === null) {
            return;
        }

        $this->recentlyViewedStorage->record($event->getRequest(), $productUid);
        try {
            $this->productViewTrackingService->record($event->getRequest(), $productUid);
        } catch (\Throwable $exception) {
            // A view counter is statistics; failing to count must never cost the visitor the product page.
            $this->logger->warning('Could not count a product view.', [
                'product' => $productUid,
                'exception' => $exception,
            ]);
        }
    }
}
