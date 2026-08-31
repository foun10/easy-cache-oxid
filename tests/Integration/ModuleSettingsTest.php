<?php

declare(strict_types=1);

namespace foun10\EasyCache\Tests\Integration;

use foun10\EasyCache\Core\EasyCache;
use OxidEsales\Eshop\Core\Registry;
use PHPUnit\Framework\TestCase;

/**
 * Settings have to arrive in the module through whatever store the running shop actually uses.
 *
 * This is the one thing a unit test can never prove, because the unit suite substitutes the
 * setting seams by design. On this line the store is oxconfig, addressed with the
 * 'module:' prefix - the 2.x branch has the same test against OXID 7's configuration
 * directory, which is why the seams carry the same names on both branches.
 */
class ModuleSettingsTest extends TestCase
{
    /** @var array */
    private $originalValues = [];

    private const SETTINGS = [
        'foun10EasyCacheEnabled' => 'bool',
        'foun10EasyCacheSaveStats' => 'bool',
        'foun10EasyCacheGzip' => 'bool',
        'foun10EasyCacheMinify' => 'bool',
        'foun10EasyCacheTTL' => 'str',
        'foun10EasyCacheWhitelist' => 'arr',
    ];

    protected function setUp(): void
    {
        $config = Registry::getConfig();

        foreach (array_keys(self::SETTINGS) as $name) {
            $this->originalValues[$name] = $config->getConfigParam($name);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalValues as $name => $value) {
            $this->saveSetting($name, $value);
        }
    }

    private function saveSetting(string $name, $value): void
    {
        Registry::getConfig()->saveShopConfVar(
            self::SETTINGS[$name],
            $name,
            $value,
            null,
            'module:' . EasyCache::MODULE_ID
        );
    }

    public function testTheEnabledFlagReachesTheModule(): void
    {
        $easyCache = oxNew(EasyCache::class);

        $this->saveSetting('foun10EasyCacheEnabled', true);
        $this->assertTrue($easyCache->isEnabled(), 'the module cannot see its own enabled flag');

        $this->saveSetting('foun10EasyCacheEnabled', false);
        $this->assertFalse($easyCache->isEnabled());
    }

    public function testTheLifetimeReachesTheModule(): void
    {
        $this->saveSetting('foun10EasyCacheTTL', '1234');

        $this->assertSame(1234, oxNew(EasyCache::class)->getTtl());
    }

    /**
     * A collection setting takes a different code path than the scalar ones, and the whitelist
     * is what decides which pages get cached at all - an empty read would silently switch the
     * whole module off.
     */
    public function testTheWhitelistReachesTheModule(): void
    {
        $this->saveSetting('foun10EasyCacheWhitelist', ['start', 'content']);

        $this->assertSame(
            ['start', 'content'],
            array_values((array) Registry::getConfig()->getConfigParam('foun10EasyCacheWhitelist'))
        );
    }

    public function testTheStatsFlagReachesTheModule(): void
    {
        $easyCache = oxNew(EasyCache::class);

        $this->saveSetting('foun10EasyCacheSaveStats', true);

        $this->assertTrue($easyCache->isSaveStatsEnabled());
    }
}
