<?php

declare(strict_types=1);

namespace foun10\EasyCache\Tests\Unit\Core;

use foun10\EasyCache\Core\EasyCache;
use foun10\EasyCache\Tests\Unit\Double\FakeContentView;
use foun10\EasyCache\Tests\Unit\Double\FakeEntity;
use foun10\EasyCache\Tests\Unit\Double\FakeListView;
use foun10\EasyCache\Tests\Unit\Double\FakeProductView;
use foun10\EasyCache\Tests\Unit\Double\FakeView;
use foun10\EasyCache\Tests\Unit\Double\TestableEasyCache;
use PHPUnit\Framework\TestCase;

/**
 * The tags a cached page is filed under. They decide what a stock or price change actually
 * invalidates: too few and customers keep seeing a sold-out product as buyable, too many and
 * the cache clears itself for nothing.
 */
class EasyCacheTagsTest extends TestCase
{
    private function easyCache(): TestableEasyCache
    {
        return new TestableEasyCache();
    }

    public function testAProductPageIsTaggedWithItsProduct(): void
    {
        $view = new FakeProductView('details', new FakeEntity('product-id'));

        $this->assertSame(['product-product-id'], $this->easyCache()->getTags($view));
    }

    /**
     * A variant's page also renders parent-level markup, so anything invalidating the parent
     * has to take the variant's cached page with it.
     */
    public function testAVariantPageIsAlsoTaggedWithItsParent(): void
    {
        $view = new FakeProductView('details', new FakeEntity('variant-id', 'parent-id'));

        $this->assertSame(
            ['product-variant-id', 'product-parent-id'],
            $this->easyCache()->getTags($view)
        );
    }

    public function testACategoryListingIsTaggedWithItsCategory(): void
    {
        $view = new FakeListView('alist', new FakeEntity('category-id'));

        $this->assertSame(['category-category-id'], $this->easyCache()->getTags($view));
    }

    /**
     * ManufacturerListController overrides getActiveCategory() to return the manufacturer, so
     * the same accessor serves both listings - only the tag prefix tells them apart.
     */
    public function testAManufacturerListingIsTaggedWithItsManufacturer(): void
    {
        $view = new FakeListView('manufacturerlist', new FakeEntity('manufacturer-id'));

        $this->assertSame(['manufacturer-manufacturer-id'], $this->easyCache()->getTags($view));
    }

    public function testAContentPageIsTaggedWithItsContentId(): void
    {
        $this->assertSame(
            ['content-content-id'],
            $this->easyCache()->getTags(new FakeContentView('content-id'))
        );
    }

    /**
     * The start page belongs to no single entity, so it gets one fixed tag for manual clearing
     * and is never invalidated automatically.
     */
    public function testTheStartPageGetsItsOwnFixedTag(): void
    {
        $this->assertSame(
            [EasyCache::START_TAG],
            $this->easyCache()->getTags(new FakeView('start'))
        );
    }

    /**
     * Adding a class key to the whitelist without teaching getTags() about it must cost that
     * page its fine-grained invalidation - never break the write.
     */
    public function testAnUnknownControllerYieldsNoTagsInsteadOfFailing(): void
    {
        $this->assertSame([], $this->easyCache()->getTags(new FakeView('somethingelse')));
    }

    /**
     * The entity accessors are duck-typed via method_exists(), so a view that does not have
     * them at all has to be a supported case.
     */
    public function testAViewWithoutTheExpectedAccessorYieldsNoTags(): void
    {
        $this->assertSame([], $this->easyCache()->getTags(new FakeView('details')));
        $this->assertSame([], $this->easyCache()->getTags(new FakeView('alist')));
        $this->assertSame([], $this->easyCache()->getTags(new FakeView('content')));
    }
}
