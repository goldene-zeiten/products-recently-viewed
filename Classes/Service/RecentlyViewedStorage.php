<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\RecentlyViewed\Service;

use GoldeneZeiten\Products\Core\Service\FrontendUserResolver;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * FE session only, no DB - a capped FIFO list of viewed product uids. Viewing a product already in
 * the list moves it to the front rather than duplicating it.
 */
final class RecentlyViewedStorage
{
    private const SESSION_KEY = 'tx_products_recentlyViewed';
    private const DEFAULT_LIMIT = 10;

    public function __construct(
        private readonly FrontendUserResolver $frontendUserResolver
    ) {}

    public function record(ServerRequestInterface $request, int $productUid): void
    {
        $productUids = $this->withoutUid($this->load($request), $productUid);
        array_unshift($productUids, $productUid);
        $this->save($request, array_slice($productUids, 0, $this->limit($request)));
    }

    /**
     * @return int[]
     */
    public function load(ServerRequestInterface $request): array
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if (!$frontendUser instanceof FrontendUserAuthentication) {
            return [];
        }

        $data = $frontendUser->getKey('ses', self::SESSION_KEY);
        if (empty($data)) {
            return [];
        }

        $productUids = json_decode((string)$data, true);
        return is_array($productUids) ? array_map('intval', $productUids) : [];
    }

    /**
     * @param int[] $productUids
     */
    private function save(ServerRequestInterface $request, array $productUids): void
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if (!$frontendUser instanceof FrontendUserAuthentication) {
            return;
        }
        if ($this->requiresCookieConsent($request) && !$this->frontendUserResolver->hasConfirmedSessionCookie($request)) {
            return;
        }

        $frontendUser->setKey('ses', self::SESSION_KEY, json_encode(array_values($productUids)));
        $frontendUser->storeSessionData();
    }

    private function requiresCookieConsent(ServerRequestInterface $request): bool
    {
        $site = $request->getAttribute('site');
        if (!$site instanceof SiteInterface) {
            return false;
        }
        return (bool)$site->getSettings()->get('products.session.requireCookieConsent', false);
    }

    /**
     * @param int[] $productUids
     * @return int[]
     */
    private function withoutUid(array $productUids, int $productUid): array
    {
        return array_values(array_filter($productUids, static fn(int $uid): bool => $uid !== $productUid));
    }

    private function limit(ServerRequestInterface $request): int
    {
        $site = $request->getAttribute('site');
        if (!$site instanceof SiteInterface) {
            return self::DEFAULT_LIMIT;
        }
        return max(1, (int)$site->getSettings()->get('products.recentlyViewed.limit', self::DEFAULT_LIMIT));
    }
}
