<?php
declare(strict_types=1);

namespace foun10\EasyCache\Controller\Admin;

use foun10\EasyCache\Core\EasyCache;
use OxidEsales\Eshop\Application\Controller\Admin\AdminController;
use OxidEsales\Eshop\Core\Registry;

/**
 * One-pager admin view for EasyCache's read-only numbers: on-disk file
 * count/size (real filesystem scan, opt-in) and the cache hit/miss stats
 * grouped by view class. Split out from SettingsController so the toggles
 * page stays settings-only; clearing the cache lives on its own tab
 * (ClearController).
 */
class StatsController extends AdminController
{
    protected $_sThisTemplate = '@foun10EasyCache/admin/foun10_easycache_stats.html.twig';

    public function render()
    {
        parent::render();

        $config = Registry::getConfig();
        $easyCache = $this->getEasyCache();

        $this->_aViewData['easyCacheEnabled'] = (bool) $config->getConfigParam('foun10EasyCacheEnabled');
        $this->_aViewData['easyCacheSaveStats'] = $easyCache->isSaveStatsEnabled();
        $this->_aViewData['easyCacheStats'] = $easyCache->getStats();
        $this->_aViewData['easyCacheLastAction'] = (string) Registry::getRequest()->getRequestEscapedParameter('fnc');

        // Real filesystem scan (see FileCacheStorage::getStats()) - only run
        // when explicitly requested via the "count files" button, never on
        // a plain page load, so this page stays cheap for large caches.
        $this->_aViewData['easyCacheFileStats'] = null;
        if (Registry::getRequest()->getRequestEscapedParameter('filecount')) {
            $this->_aViewData['easyCacheFileStats'] = $easyCache->getFileStats();
        }

        return $this->_sThisTemplate;
    }

    public function resetstats()
    {
        $this->getEasyCache()->resetStats();
    }

    protected function getEasyCache(): EasyCache
    {
        return Registry::get(EasyCache::class);
    }
}
