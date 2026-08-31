<?php

declare(strict_types=1);

namespace foun10\EasyCache\Tests\Unit\Core;

use foun10\EasyCache\Tests\Unit\Double\FakeView;
use foun10\EasyCache\Tests\Unit\Double\TestableEasyCache;
use PHPUnit\Framework\TestCase;

/**
 * The cache key. A collision here serves the wrong page to a real visitor, so these tests are
 * written as "these two requests must not share a key" rather than pinning the exact hash -
 * the format is free to change, the distinctions are not.
 */
class EasyCacheKeyTest extends TestCase
{
    private function easyCache(): TestableEasyCache
    {
        return new TestableEasyCache();
    }

    public function testTheKeyIsAHash(): void
    {
        $key = $this->easyCache()->buildKey(new FakeView('start', 'abc'));

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $key);
    }

    public function testTheSameRequestAlwaysProducesTheSameKey(): void
    {
        $easyCache = $this->easyCache();

        $this->assertSame(
            $easyCache->buildKey(new FakeView('start', 'abc')),
            $easyCache->buildKey(new FakeView('start', 'abc'))
        );
    }

    /**
     * getViewId() is where each controller folds in the request parameters that actually change
     * its output, so two different view ids must never collapse onto one key.
     */
    public function testDifferentViewIdsProduceDifferentKeys(): void
    {
        $easyCache = $this->easyCache();

        $this->assertNotSame(
            $easyCache->buildKey(new FakeView('alist', 'category-1')),
            $easyCache->buildKey(new FakeView('alist', 'category-2'))
        );
    }

    public function testDifferentControllersProduceDifferentKeys(): void
    {
        $easyCache = $this->easyCache();

        $this->assertNotSame(
            $easyCache->buildKey(new FakeView('start', 'same')),
            $easyCache->buildKey(new FakeView('content', 'same'))
        );
    }

    public function testDifferentShopsDoNotShareKeys(): void
    {
        $shopOne = $this->easyCache();
        $shopTwo = $this->easyCache();
        $shopTwo->shopId = '2';

        $this->assertNotSame(
            $shopOne->buildKey(new FakeView('start', 'abc')),
            $shopTwo->buildKey(new FakeView('start', 'abc'))
        );
    }

    /**
     * Gzip is part of the key so that flipping the setting needs no cache clear: the module
     * simply starts reading and writing a disjoint set of keys, and a raw file can never be
     * handed to gzdecode().
     */
    public function testGzipAndRawEntriesUseSeparateKeys(): void
    {
        $raw = $this->easyCache();
        $gzip = $this->easyCache();
        $gzip->boolSettings['foun10EasyCacheGzip'] = true;

        $this->assertNotSame(
            $raw->buildKey(new FakeView('start', 'abc')),
            $gzip->buildKey(new FakeView('start', 'abc'))
        );
    }

    /**
     * ManufacturerListController inherits ArticleListController::generateViewId(), which hashes
     * cnid/page/sorting but never mnid - so without folding it in here every manufacturer's
     * listing would collide on one key.
     */
    public function testTheManufacturerIdIsFoldedIntoTheManufacturerListKey(): void
    {
        $first = $this->easyCache();
        $first->requestParameters['mnid'] = 'manufacturer-a';

        $second = $this->easyCache();
        $second->requestParameters['mnid'] = 'manufacturer-b';

        $this->assertNotSame(
            $first->buildKey(new FakeView('manufacturerlist', 'same-viewid')),
            $second->buildKey(new FakeView('manufacturerlist', 'same-viewid')),
            'two manufacturers would otherwise share one cached listing'
        );
    }

    /**
     * The parts of the key are separated on purpose. Without a separator the fields run into
     * each other, and two genuinely different requests collapse onto one key: controller "ab"
     * with view id "c" would be indistinguishable from controller "a" with view id "bc" - one
     * visitor served the other's page.
     */
    public function testAdjacentKeyPartsCannotBlurIntoEachOther(): void
    {
        $easyCache = $this->easyCache();

        $this->assertNotSame(
            $easyCache->buildKey(new FakeView('ab', 'c')),
            $easyCache->buildKey(new FakeView('a', 'bc'))
        );
    }

    /**
     * Same hazard on the shop boundary: shop 1 with controller "1start" must not collide with
     * shop 11 with controller "start".
     */
    public function testTheShopIdCannotBlurIntoTheControllerKey(): void
    {
        $shopOne = $this->easyCache();
        $shopOne->shopId = '1';

        $shopEleven = $this->easyCache();
        $shopEleven->shopId = '11';

        $this->assertNotSame(
            $shopOne->buildKey(new FakeView('1start', 'v')),
            $shopEleven->buildKey(new FakeView('start', 'v'))
        );
    }

    /**
     * mnid is meaningless anywhere else, and folding it in regardless would split the cache of
     * unrelated pages for no reason.
     */
    public function testTheManufacturerIdIsIgnoredOnOtherControllers(): void
    {
        $withMnid = $this->easyCache();
        $withMnid->requestParameters['mnid'] = 'manufacturer-a';

        $this->assertSame(
            $this->easyCache()->buildKey(new FakeView('alist', 'same-viewid')),
            $withMnid->buildKey(new FakeView('alist', 'same-viewid'))
        );
    }
}
