<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\RecentlyViewed\Tests\Functional\Service;

use GoldeneZeiten\Products\RecentlyViewed\Service\RecentlyViewedStorage;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class RecentlyViewedStorageTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-recently-viewed',
    ];

    /**
     * @param int[] $recordedUids
     * @param int[] $expectedOrder
     */
    #[Test]
    #[DataProvider('loadOrderProvider')]
    public function loadReflectsTheRecordedOrder(int $limit, array $recordedUids, array $expectedOrder): void
    {
        $storage = $this->get(RecentlyViewedStorage::class);
        $request = $this->request()->withAttribute('site', $this->site($limit, false));

        foreach ($recordedUids as $uid) {
            $storage->record($request, $uid);
        }

        $this->assertSame($expectedOrder, $storage->load($request));
    }

    public static function loadOrderProvider(): \Generator
    {
        yield 'empty by default' => ['limit' => 10, 'recordedUids' => [], 'expectedOrder' => []];
        yield 'recording a product adds it to the front' => ['limit' => 10, 'recordedUids' => [1, 2], 'expectedOrder' => [2, 1]];
        yield 'reviewing an already present product moves it to the front without duplicating' => ['limit' => 10, 'recordedUids' => [1, 2, 1], 'expectedOrder' => [1, 2]];
        yield 'the list is capped at the configured limit' => ['limit' => 2, 'recordedUids' => [1, 2, 3], 'expectedOrder' => [3, 2]];
    }

    #[Test]
    #[DataProvider('recordingIsSkippedProvider')]
    public function recordingIsSkippedInVariousScenarios(bool $requireCookieConsent, bool $sessionlessRequest): void
    {
        $storage = $this->get(RecentlyViewedStorage::class);
        $request = ($sessionlessRequest ? new ServerRequest('http://localhost/') : $this->request())
            ->withAttribute('site', $this->site(10, $requireCookieConsent));

        $storage->record($request, 1);

        $this->assertSame([], $storage->load($request));
    }

    public static function recordingIsSkippedProvider(): \Generator
    {
        yield 'guest without a session records nothing and crashes nothing' => ['requireCookieConsent' => false, 'sessionlessRequest' => true];
        yield 'recording is skipped when cookie consent is required but not yet confirmed' => ['requireCookieConsent' => true, 'sessionlessRequest' => false];
    }

    #[Test]
    public function recordingSucceedsWhenCookieConsentIsRequiredAndAlreadyConfirmed(): void
    {
        $storage = $this->get(RecentlyViewedStorage::class);
        $request = $this->requestWithConfirmedCookie()->withAttribute('site', $this->site(10, true));

        $storage->record($request, 1);

        $this->assertSame([1], $storage->load($request));
    }

    #[Test]
    public function withoutASiteTheDefaultLimitApplies(): void
    {
        $storage = $this->get(RecentlyViewedStorage::class);
        $request = $this->request();

        foreach (range(1, 12) as $uid) {
            $storage->record($request, $uid);
        }

        $this->assertSame([12, 11, 10, 9, 8, 7, 6, 5, 4, 3], $storage->load($request));
    }

    private function site(int $limit, bool $requireCookieConsent): Site
    {
        return new Site('products', 1, [
            'settings' => [
                'products' => [
                    'recentlyViewed' => ['limit' => $limit],
                    'session' => ['requireCookieConsent' => $requireCookieConsent],
                ],
            ],
        ]);
    }

    private function request(): ServerRequestInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        return (new ServerRequest('http://localhost/'))->withAttribute('frontend.user', $frontendUser);
    }

    private function requestWithConfirmedCookie(): ServerRequestInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        return (new ServerRequest('http://localhost/'))
            ->withAttribute('frontend.user', $frontendUser)
            ->withCookieParams([$frontendUser->name => 'existing-session-id']);
    }
}
