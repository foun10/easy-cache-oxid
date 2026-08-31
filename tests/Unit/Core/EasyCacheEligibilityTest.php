<?php

declare(strict_types=1);

namespace foun10\EasyCache\Tests\Unit\Core;

use foun10\EasyCache\Tests\Unit\Double\FakeView;
use foun10\EasyCache\Tests\Unit\Double\TestableEasyCache;
use PHPUnit\Framework\TestCase;

/**
 * The rules deciding whether a request may be served from, or written to, the shared cache.
 *
 * This is the part of the module where a mistake is not a performance regression but a data
 * leak: cache one logged-in customer's page and every later visitor gets it. Each rule
 * therefore gets its own test, and each one asserts the "not cacheable" direction, because
 * that is the direction that must never silently flip.
 */
class EasyCacheEligibilityTest extends TestCase
{
    /** @var array */
    private $originalServer = [];

    /** @var array */
    private $originalGet = [];

    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
        $this->originalGet = $_GET;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        $_GET = $this->originalGet;
    }

    /**
     * A cache that is switched on, whitelisted for the start page, and asked by an anonymous
     * visitor with an empty basket - the one combination that must come out true.
     */
    private function cacheable(): TestableEasyCache
    {
        $easyCache = new TestableEasyCache();
        $easyCache->boolSettings['foun10EasyCacheEnabled'] = true;
        $easyCache->collectionSettings['foun10EasyCacheWhitelist'] = ['start', 'alist'];

        return $easyCache;
    }

    public function testAPlainGuestPageViewIsCacheable(): void
    {
        $this->assertTrue($this->cacheable()->isRequestCacheable(new FakeView('start'), ''));
    }

    public function testNothingIsCacheableWhileTheModuleIsSwitchedOff(): void
    {
        $easyCache = $this->cacheable();
        $easyCache->boolSettings['foun10EasyCacheEnabled'] = false;

        $this->assertFalse($easyCache->isRequestCacheable(new FakeView('start'), ''));
    }

    public function testTheAdminAreaIsNeverCached(): void
    {
        $easyCache = $this->cacheable();
        $easyCache->adminMode = true;

        $this->assertFalse($easyCache->isRequestCacheable(new FakeView('start'), ''));
    }

    /**
     * The whitelist is the entire guest list. Checkout, account and basket controllers are
     * kept out simply by not being on it, so a controller that is not listed must be refused
     * even though nothing else about the request looks unusual.
     */
    public function testAControllerOutsideTheWhitelistIsNotCached(): void
    {
        $this->assertFalse($this->cacheable()->isRequestCacheable(new FakeView('basket'), ''));
    }

    public function testAnEmptyWhitelistCachesNothing(): void
    {
        $easyCache = $this->cacheable();
        $easyCache->collectionSettings['foun10EasyCacheWhitelist'] = [];

        $this->assertFalse($easyCache->isRequestCacheable(new FakeView('start'), ''));
    }

    /**
     * Any fnc= call changes shop state - adding to basket, logging in, submitting a form. It
     * has to run for real every time.
     */
    public function testARequestCarryingAFunctionCallIsNotCached(): void
    {
        $this->assertFalse($this->cacheable()->isRequestCacheable(new FakeView('start'), 'tobasket'));
    }

    public function testOnlyGetRequestsAreCached(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->assertFalse($this->cacheable()->isRequestCacheable(new FakeView('start'), ''));
    }

    /**
     * @dataProvider sessionCarryingParameters
     */
    public function testASessionCarryingUrlIsNotCached(string $parameter): void
    {
        $_GET[$parameter] = 'whatever';

        $this->assertFalse(
            $this->cacheable()->isRequestCacheable(new FakeView('start'), ''),
            $parameter . ' in the URL means a session-continuation request, which must not be shared'
        );
    }

    public function sessionCarryingParameters(): array
    {
        return [['sid'], ['stoken'], ['force_sid'], ['force_admin_sid']];
    }

    public function testALoggedInVisitorIsNeverServedFromTheSharedCache(): void
    {
        $easyCache = $this->cacheable();
        $easyCache->session->variables['usr'] = 'oxdefaultadmin';

        $this->assertFalse(
            $easyCache->isRequestCacheable(new FakeView('start'), ''),
            'a logged-in page would otherwise be written to the cache and served to everyone else'
        );
    }

    public function testAVisitorWithAFilledBasketIsNotCached(): void
    {
        $easyCache = $this->cacheable();
        $easyCache->session->basketProductCount = 2;

        $this->assertFalse($easyCache->isRequestCacheable(new FakeView('start'), ''));
    }

    /**
     * renderPartial is OXID's AJAX/widget entry point - a fragment, not a page, and it must not
     * end up stored under a page's cache key.
     */
    public function testAPartialRenderIsNotCached(): void
    {
        $easyCache = $this->cacheable();
        $easyCache->requestParameters['renderPartial'] = '1';

        $this->assertFalse($easyCache->isRequestCacheable(new FakeView('start'), ''));
    }
}
