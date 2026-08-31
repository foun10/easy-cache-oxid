<?php
declare(strict_types=1);

namespace foun10\EasyCache\Controller\Admin;

use foun10\EasyCache\Core\ControllerWhitelist;
use foun10\EasyCache\Core\EasyCache;
use OxidEsales\Eshop\Application\Controller\Admin\AdminController;
use OxidEsales\Eshop\Core\Registry;

/**
 * One-pager admin view for the EasyCache module's settings: the on/off
 * toggles (caching, stats collection, gzip storage, HTML minification),
 * the cache TTL and the controller whitelist. Stats and cache clearing live on their own SUBMENU
 * tabs (see StatsController/ClearController) - this page is settings only.
 */
class SettingsController extends AdminController
{
    protected $_sThisTemplate = 'foun10_easycache_settings.tpl';

    public function render()
    {
        parent::render();

        $config = Registry::getConfig();
        $easyCache = $this->getEasyCache();

        $this->_aViewData['easyCacheEnabled'] = (bool) $config->getConfigParam('foun10EasyCacheEnabled');
        $this->_aViewData['easyCacheTTL'] = $easyCache->getTtl();
        $this->_aViewData['easyCacheWhitelist'] = ControllerWhitelist::format($easyCache->getWhitelist());
        $this->_aViewData['easyCacheSaveStats'] = $easyCache->isSaveStatsEnabled();
        // The stored setting, not isGzipEnabled() - the checkbox must reflect
        // what the admin configured, not the effective (possibly overridden)
        // runtime value; unavailability is surfaced separately as a hint.
        $this->_aViewData['easyCacheGzip'] = (bool) $config->getConfigParam('foun10EasyCacheGzip');
        $this->_aViewData['easyCacheGzipAvailable'] = $easyCache->isGzipAvailable();
        $this->_aViewData['easyCacheMinify'] = (bool) $config->getConfigParam('foun10EasyCacheMinify');
        $this->_aViewData['easyCacheMinifyAvailable'] = $easyCache->isMinifyAvailable();
        $this->_aViewData['easyCacheLastAction'] = (string) Registry::getRequest()->getRequestEscapedParameter('fnc');

        return $this->_sThisTemplate;
    }

    /**
     * Saves the toggles. Stored the same way OXID stores any other module
     * setting (oxconfig, module scoped) so getConfigParam() keeps working
     * everywhere else unchanged.
     */
    public function save()
    {
        $request = Registry::getRequest();
        $config = Registry::getConfig();
        $shopId = $config->getShopId();

        $config->saveShopConfVar(
            'bool',
            'foun10EasyCacheEnabled',
            (bool) $request->getRequestEscapedParameter('foun10EasyCacheEnabled'),
            $shopId,
            'module:foun10EasyCache'
        );

        // Only saved when a positive number was actually submitted - a
        // blank/zero/non-numeric value is ignored rather than overwriting
        // a working TTL with something EasyCache::getTtl() would just fall
        // back to 3600 from anyway.
        $ttl = (int) $request->getRequestEscapedParameter('foun10EasyCacheTTL');
        if ($ttl > 0) {
            $config->saveShopConfVar(
                'str',
                'foun10EasyCacheTTL',
                (string) $ttl,
                $shopId,
                'module:foun10EasyCache'
            );
        }

        // Saved exactly as submitted, empty list included: clearing the field is
        // a legitimate way to stop caching everything without touching the
        // master switch, and the field always shows what is currently stored,
        // so an empty one is a deliberate edit rather than a slip.
        $config->saveShopConfVar(
            'arr',
            'foun10EasyCacheWhitelist',
            ControllerWhitelist::parse((string) $request->getRequestEscapedParameter('foun10EasyCacheWhitelist')),
            $shopId,
            'module:foun10EasyCache'
        );

        $config->saveShopConfVar(
            'bool',
            'foun10EasyCacheSaveStats',
            (bool) $request->getRequestEscapedParameter('foun10EasyCacheSaveStats'),
            $shopId,
            'module:foun10EasyCache'
        );

        $config->saveShopConfVar(
            'bool',
            'foun10EasyCacheGzip',
            (bool) $request->getRequestEscapedParameter('foun10EasyCacheGzip'),
            $shopId,
            'module:foun10EasyCache'
        );

        $config->saveShopConfVar(
            'bool',
            'foun10EasyCacheMinify',
            (bool) $request->getRequestEscapedParameter('foun10EasyCacheMinify'),
            $shopId,
            'module:foun10EasyCache'
        );
    }

    protected function getEasyCache(): EasyCache
    {
        return Registry::get(EasyCache::class);
    }
}
