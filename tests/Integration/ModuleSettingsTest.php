<?php

declare(strict_types=1);

namespace foun10\EasyCache\Tests\Integration;

use foun10\EasyCache\Core\EasyCache;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Facade\ModuleSettingServiceInterface;
use PHPUnit\Framework\TestCase;

/**
 * Settings have to arrive in the module through whatever store the running shop actually uses.
 *
 * This is the one thing a unit test can never prove, because the unit suite substitutes the
 * setting seams by design. The failure it guards against is completely silent: read module
 * settings through Config::getConfigParam() and the OXID 7 line hands back null for every one
 * of them - no error, no log entry, the cache simply never engages and every request reports
 * BYPASS forever. It is the same bug that once made the DeepL module's API key unreachable.
 */
class ModuleSettingsTest extends TestCase
{
    /** @var ModuleSettingServiceInterface */
    private $settingService;

    /** @var array */
    private $originalBooleans = [];

    /** @var string */
    private $originalTtl = '';

    /** @var array */
    private $originalWhitelist = [];

    private const BOOLEAN_SETTINGS = [
        'foun10EasyCacheEnabled',
        'foun10EasyCacheSaveStats',
        'foun10EasyCacheGzip',
        'foun10EasyCacheMinify',
    ];

    protected function setUp(): void
    {
        $this->settingService = ContainerFactory::getInstance()
            ->getContainer()
            ->get(ModuleSettingServiceInterface::class);

        foreach (self::BOOLEAN_SETTINGS as $name) {
            $this->originalBooleans[$name] = $this->settingService->getBoolean($name, EasyCache::MODULE_ID);
        }

        $this->originalTtl = (string) $this->settingService->getString('foun10EasyCacheTTL', EasyCache::MODULE_ID);
        $this->originalWhitelist = (array) $this->settingService->getCollection(
            'foun10EasyCacheWhitelist',
            EasyCache::MODULE_ID
        );
    }

    protected function tearDown(): void
    {
        foreach ($this->originalBooleans as $name => $value) {
            $this->settingService->saveBoolean($name, $value, EasyCache::MODULE_ID);
        }

        $this->settingService->saveString('foun10EasyCacheTTL', $this->originalTtl, EasyCache::MODULE_ID);
        $this->settingService->saveCollection(
            'foun10EasyCacheWhitelist',
            $this->originalWhitelist,
            EasyCache::MODULE_ID
        );
    }

    public function testTheEnabledFlagReachesTheModule(): void
    {
        $easyCache = oxNew(EasyCache::class);

        $this->settingService->saveBoolean('foun10EasyCacheEnabled', true, EasyCache::MODULE_ID);
        $this->assertTrue($easyCache->isEnabled(), 'the module cannot see its own enabled flag');

        $this->settingService->saveBoolean('foun10EasyCacheEnabled', false, EasyCache::MODULE_ID);
        $this->assertFalse($easyCache->isEnabled());
    }

    public function testTheLifetimeReachesTheModule(): void
    {
        $this->settingService->saveString('foun10EasyCacheTTL', '1234', EasyCache::MODULE_ID);

        $this->assertSame(1234, oxNew(EasyCache::class)->getTtl());
    }

    /**
     * A collection setting takes a different code path than the scalar ones, and the whitelist
     * is what decides which pages get cached at all - an empty read would silently switch the
     * whole module off.
     */
    public function testTheWhitelistReachesTheModule(): void
    {
        $this->settingService->saveCollection(
            'foun10EasyCacheWhitelist',
            ['start', 'content'],
            EasyCache::MODULE_ID
        );

        $this->assertSame(
            ['start', 'content'],
            array_values(
                (array) $this->settingService->getCollection('foun10EasyCacheWhitelist', EasyCache::MODULE_ID)
            )
        );
    }

    public function testTheStatsFlagReachesTheModule(): void
    {
        $easyCache = oxNew(EasyCache::class);

        $this->settingService->saveBoolean('foun10EasyCacheSaveStats', true, EasyCache::MODULE_ID);

        $this->assertTrue($easyCache->isSaveStatsEnabled());
    }
}
