<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\RecentlyViewed\Tests\Functional\Controller;

use GoldeneZeiten\Products\RecentlyViewed\Service\ProductViewTrackingService;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Session\SessionManager;
use TYPO3\CMS\Core\Session\UserSession;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

final class RecentlyViewedControllerTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'en' => [
            'id' => 0,
            'title' => 'English',
            'locale' => 'en_US.UTF-8',
        ],
    ];

    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-recently-viewed',
    ];

    protected array $coreExtensionsToLoad = [
        'fluid_styled_content',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(self::sharedFixture('shop.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/recently_viewed_modes.csv');

        // record() bumps the site-wide counter regardless of login state (a logged-in
        // view still counts globally), so recording product 6 for fe_user 1 also makes it
        // show up site-wide - recording product 5 more often keeps it strictly ahead in
        // the site-wide ranking, and the "mostviewedglobal" test below limits to the top
        // result to isolate that ranking from the per-user one.
        $viewTrackingService = $this->get(ProductViewTrackingService::class);
        $viewTrackingService->record($this->requestFor(0), 5);
        $viewTrackingService->record($this->requestFor(0), 5);
        $viewTrackingService->record($this->requestFor(0), 5);
        $viewTrackingService->record($this->requestFor(1), 6);
        $viewTrackingService->record($this->requestFor(1), 6);

        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1),
            [
                $this->buildDefaultLanguageConfiguration('en', '/'),
            ]
        );
        $this->setUpFrontendRootPage(1, [
            'constants' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/constants.typoscript',
                'EXT:products_core/Configuration/TypoScript/constants.typoscript',
            ],
            'setup' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/setup.typoscript',
                'EXT:products_core/Configuration/TypoScript/setup.typoscript',
                'EXT:products_recently_viewed/Configuration/TypoScript/setup.typoscript',
                'EXT:products_recently_viewed/Tests/Functional/Fixtures/TypoScript/plugin_content_rendering.typoscript',
            ],
        ]);
        $this->addTypoScriptToTemplateRecord(1, '
            plugin.tx_productscore.persistence.storagePid = 2
        ');
    }

    #[Test]
    public function listActionWithRecentModeShowsNeitherMostViewedCounter(): void
    {
        // No session-based "recently viewed" data is seeded here (that storage is
        // session-only and already covered by RecentlyViewedStorageTest) - this asserts
        // the "recent" mode renders the empty state and never leaks the DB-backed
        // most-viewed counters seeded in setUp().
        $request = $this->contentRequest(300);
        $response = $this->executeFrontendSubRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringNotContainsString('Product 5', $body);
        $this->assertStringNotContainsString('Product 6', $body);
    }

    #[Test]
    public function listActionWithMostviewedModeShowsThePerUserCounterForTheLoggedInUser(): void
    {
        $request = $this->contentRequest(301)
            ->withCookieParams(['fe_typo_user' => $this->loginFrontendUser(1)]);
        $response = $this->executeFrontendSubRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringContainsString('Product 6', $body);
        $this->assertStringNotContainsString('Product 5', $body);
    }

    #[Test]
    public function listActionWithMostviewedglobalModeShowsTheSiteWideCounterRegardlessOfLogin(): void
    {
        $request = $this->contentRequest(302);
        // Limit to the top result: product 6 also has a (lower) site-wide count of its
        // own (see the comment in setUp()), so without a limit both would render -
        // constraining to the top-1 isolates "which product ranks first", which is what
        // this test is actually about.
        $this->addTypoScriptToTemplateRecord(1, '
            plugin.tx_productsrecentlyviewed.settings.mostViewed.limit = 1
        ');
        $response = $this->executeFrontendSubRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringContainsString('Product 5', $body);
        $this->assertStringNotContainsString('Product 6', $body);
    }

    /**
     * Renders the real tt_content row with the given uid through the normal CType dispatch,
     * so currentContentObject carries that row's actual tx_products_recentlyviewed_mode -
     * a hardcoded "page.10 = USER" plugin call never populates currentContentObject and
     * can't exercise per-element mode configuration.
     */
    private function contentRequest(int $contentUid): InternalRequest
    {
        $this->addTypoScriptToTemplateRecord(1, sprintf('
            page = PAGE
            page.10 = CONTENT
            page.10 {
                table = tt_content
                select.where = uid = %d
            }
        ', $contentUid));

        return new InternalRequest('http://localhost/shop');
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

    /**
     * @return string the value to use for the `fe_typo_user` cookie
     */
    private function loginFrontendUser(int $frontendUserUid): string
    {
        $sessionId = bin2hex(random_bytes(16));
        $sessionBackend = $this->get(SessionManager::class)->getSessionBackend('FE');
        $sessionBackend->set($sessionId, [
            'ses_iplock' => '[DISABLED]',
            'ses_userid' => $frontendUserUid,
        ]);

        return UserSession::createFromRecord($sessionId, [])->getJwt();
    }
}
