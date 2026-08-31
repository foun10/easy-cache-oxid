<?php
declare(strict_types=1);

namespace foun10\EasyCache\Controller\Admin;

use foun10\EasyCache\Core\EasyCache;
use OxidEsales\Eshop\Application\Controller\Admin\AdminController;
use OxidEsales\Eshop\Application\Model\Article;
use OxidEsales\Eshop\Application\Model\Category;
use OxidEsales\Eshop\Application\Model\Manufacturer;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Registry;

/**
 * One-pager admin view for clearing the EasyCache file cache: a full clear
 * (same as foun10:easycache:clear), a one-click clear for the start page's
 * tag, and a search-driven clear for one specific product/category/
 * manufacturer - nobody knows these OXIDs by heart, so search() offers a
 * small title search (plain GET, read-only, called via fetch()) and its
 * result feeds a normal form POST (fnc=cleartag) to actually invalidate,
 * with the same session/csrf handling as every other action here.
 */
class ClearController extends AdminController
{
    protected $_sThisTemplate = 'foun10_easycache_clear.tpl';

    protected const SEARCHABLE_TYPES = ['product', 'category', 'manufacturer'];
    protected const MIN_SEARCH_LENGTH = 2;
    protected const MAX_SEARCH_RESULTS = 20;

    protected $easyCacheClearedCount;
    protected $easyCacheClearedTag;

    public function render()
    {
        parent::render();

        $this->_aViewData['easyCacheEnabled'] = (bool) Registry::getConfig()->getConfigParam('foun10EasyCacheEnabled');
        $this->_aViewData['easyCacheLastAction'] = (string) Registry::getRequest()->getRequestEscapedParameter('fnc');
        $this->_aViewData['easyCacheClearedCount'] = $this->easyCacheClearedCount;
        $this->_aViewData['easyCacheClearedTag'] = $this->easyCacheClearedTag;
        $this->_aViewData['easyCacheStartTag'] = EasyCache::START_TAG;

        return $this->_sThisTemplate;
    }

    /**
     * Same logic as foun10:easycache:clear (CacheClearCommand): removes the
     * cached files and truncates the stats table.
     */
    public function clearcache()
    {
        $this->easyCacheClearedCount = $this->getEasyCache()->clearAll();
    }

    /**
     * The start page has no entity id of its own (see EasyCache::getTags())
     * - START_TAG is a fixed, manually-triggered tag, this button is that
     * trigger.
     */
    public function clearstart()
    {
        $this->easyCacheClearedTag = EasyCache::START_TAG;
        $this->easyCacheClearedCount = $this->getEasyCache()->invalidateTags([EasyCache::START_TAG]);
    }

    /**
     * Invalidates a single product/category/manufacturer tag, chosen via
     * the search() box below - the raw OXID is never something an admin
     * would type by hand.
     */
    public function cleartag()
    {
        $request = Registry::getRequest();
        $type = (string) $request->getRequestEscapedParameter('tagtype');
        $id = (string) $request->getRequestEscapedParameter('tagid');

        if ($id === '' || !in_array($type, self::SEARCHABLE_TYPES, true)) {
            return;
        }

        $tag = $type . '-' . $id;
        $this->easyCacheClearedTag = $tag;
        $this->easyCacheClearedCount = $this->getEasyCache()->invalidateTags([$tag]);
    }

    /**
     * Read-only ajax title search (GET) backing the "search by name" box -
     * always a simple LIKE search over the active language's view, capped
     * at MAX_SEARCH_RESULTS. Terminates the request itself (raw JSON, no
     * admin template) since ShopControl::_process() always calls
     * $view->render() after any fnc, regardless of which one ran.
     */
    public function search()
    {
        $request = Registry::getRequest();
        $type = (string) $request->getRequestEscapedParameter('tagtype');
        $term = trim((string) $request->getRequestEscapedParameter('q'));

        header('Content-Type: application/json; charset=UTF-8');

        if (mb_strlen($term) < self::MIN_SEARCH_LENGTH || !in_array($type, self::SEARCHABLE_TYPES, true)) {
            echo json_encode([]);
            exit;
        }

        echo json_encode($this->searchEntities($type, $term));
        exit;
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    protected function searchEntities(string $type, string $term): array
    {
        switch ($type) {
            case 'product':
                return $this->searchProducts($term);
            case 'category':
                return $this->searchCategories($term);
            case 'manufacturer':
                return $this->searchByTitle(oxNew(Manufacturer::class)->getViewName(), $term);
            default:
                return [];
        }
    }

    /**
     * Products get their own query (not searchByTitle()) so the article
     * number can be shown alongside the title - titles alone are often not
     * unique across variants (e.g. "T-Shirt" in several colors/sizes).
     *
     * Restricted to OXPARENTID = '' - only parent-level articles are
     * searched at all, so a variant never surfaces here in the first
     * place. EasyCache::getProductTags() already tags a variant's own
     * details page with its parent's tag too, so clearing the parent
     * invalidates both; showing every color/size variant here separately
     * would just be noise for products with many of them. This does mean
     * searching by a variant-specific article number won't find its
     * parent - acceptable here since every parent article in this shop's
     * data has its own non-blank OXARTNUM too (verified against the
     * current DB), so title/artnum search still reliably surfaces it.
     *
     * @return array<int, array{id: string, label: string}>
     */
    protected function searchProducts(string $term): array
    {
        $viewName = oxNew(Article::class)->getViewName();
        $like = '%' . $term . '%';

        $rows = DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC)->getAll(
            "SELECT OXID, OXTITLE, OXARTNUM FROM {$viewName}
              WHERE (OXTITLE LIKE ? OR OXARTNUM LIKE ?)
                AND OXPARENTID = ''
              ORDER BY OXTITLE
              LIMIT " . self::MAX_SEARCH_RESULTS,
            [$like, $like]
        );

        return array_map(static function (array $row): array {
            $label = (string) $row['OXTITLE'];
            if ($row['OXARTNUM']) {
                $label .= ' (' . $row['OXARTNUM'] . ')';
            }

            return ['id' => (string) $row['OXID'], 'label' => $label];
        }, $rows);
    }

    /**
     * Categories get their own query (not searchByTitle()) so the full
     * ancestor path can be shown alongside the title - unlike products
     * (disambiguated above by article number) or manufacturers (always
     * flat), the same category title can legitimately exist more than
     * once in different branches of the tree (e.g. "Accessories" nested
     * under several different sports), so the id alone isn't enough to
     * tell two same-named results apart.
     *
     * @return array<int, array{id: string, label: string}>
     */
    protected function searchCategories(string $term): array
    {
        $viewName = oxNew(Category::class)->getViewName();

        $rows = DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC)->getAll(
            "SELECT OXID, OXTITLE, OXLEFT, OXRIGHT FROM {$viewName}
              WHERE OXTITLE LIKE ?
              ORDER BY OXTITLE
              LIMIT " . self::MAX_SEARCH_RESULTS,
            ['%' . $term . '%']
        );

        return array_map(function (array $row) use ($viewName): array {
            $path = $this->getCategoryAncestorPath($viewName, (int) $row['OXLEFT'], (int) $row['OXRIGHT']);
            $label = (string) $row['OXTITLE'];

            if ($path !== '') {
                $label .= ' (' . $path . ')';
            }

            return ['id' => (string) $row['OXID'], 'label' => $label];
        }, $rows);
    }

    /**
     * Nested-set ancestor lookup (OXLEFT/OXRIGHT) - same technique already
     * used by foun10\XmlExport\Export\Traits\CategoryPathTrait. Strict
     * inequalities so the category itself is excluded from its own path,
     * only real ancestors come back, root-to-leaf order via OXLEFT ASC.
     */
    protected function getCategoryAncestorPath(string $viewName, int $left, int $right): string
    {
        $titles = DatabaseProvider::getDb()->getCol(
            "SELECT OXTITLE FROM {$viewName}
              WHERE OXLEFT < ? AND OXRIGHT > ?
              ORDER BY OXLEFT ASC",
            [$left, $right]
        );

        return implode(' > ', $titles);
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    protected function searchByTitle(string $viewName, string $term): array
    {
        $rows = DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC)->getAll(
            "SELECT OXID, OXTITLE FROM {$viewName}
              WHERE OXTITLE LIKE ?
              ORDER BY OXTITLE
              LIMIT " . self::MAX_SEARCH_RESULTS,
            ['%' . $term . '%']
        );

        return array_map(static function (array $row): array {
            return ['id' => (string) $row['OXID'], 'label' => (string) $row['OXTITLE']];
        }, $rows);
    }

    protected function getEasyCache(): EasyCache
    {
        return Registry::get(EasyCache::class);
    }
}
