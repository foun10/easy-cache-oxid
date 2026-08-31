<?php
declare(strict_types=1);

namespace foun10\EasyCache\Extension\Application\Model;

use foun10\EasyCache\Core\EasyCache;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Registry;

/**
 * Article::onChange() is OXID's own documented extension point, called
 * whenever an article is saved/deleted or its stock changes (see the
 * core docblock - explicitly including after Article::reduceStock(), which
 * is what runs during checkout). That makes it the right place to purge
 * EasyCache entries for a product that just became unbuyable, instead of
 * waiting out the (now much longer) TTL.
 *
 * Only stock reaching zero-or-below triggers anything here - a product
 * that's still available doesn't need its cached pages touched at all.
 */
class Article extends Article_parent
{
    public function onChange($action = null, $articleId = null, $parentArticleId = null)
    {
        parent::onChange($action, $articleId, $parentArticleId);

        $this->invalidateEasyCacheIfSoldOut($articleId);
    }

    protected function invalidateEasyCacheIfSoldOut($articleId): void
    {
        $articleId = $articleId ?: $this->getId();

        if (!$articleId) {
            return;
        }

        $row = DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC)->getRow(
            'SELECT OXSTOCK, OXMANUFACTURERID, OXPARENTID FROM oxarticles WHERE OXID = ?',
            [$articleId]
        );

        // An empty row means the article is gone (e.g. ACTION_DELETE) -
        // nothing left to show as buyable or not, so no cache entries
        // reference it any more than they already would once their own
        // TTL passes.
        if (!$row || (float) $row['OXSTOCK'] > 0) {
            return;
        }

        $tags = ['product-' . $articleId];

        foreach ($this->getEasyCacheCategoryIds($articleId) as $categoryId) {
            $tags[] = 'category-' . $categoryId;
        }

        if (!empty($row['OXMANUFACTURERID'])) {
            $tags[] = 'manufacturer-' . $row['OXMANUFACTURERID'];
        }

        // A sold-out variant's parent details page shows the same variant
        // selector/stock state, so it needs invalidating right alongside the
        // variant itself, not just left to expire on its own TTL.
        if (!empty($row['OXPARENTID'])) {
            $tags[] = 'product-' . $row['OXPARENTID'];
        }

        Registry::get(EasyCache::class)->invalidateTags($tags);
    }

    /**
     * @return string[]
     */
    protected function getEasyCacheCategoryIds(string $articleId): array
    {
        return DatabaseProvider::getDb()->getCol(
            'SELECT OXCATNID FROM oxobject2category WHERE OXOBJECTID = ?',
            [$articleId]
        );
    }
}
