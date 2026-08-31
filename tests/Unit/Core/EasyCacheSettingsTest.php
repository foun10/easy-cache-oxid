<?php

declare(strict_types=1);

namespace foun10\EasyCache\Tests\Unit\Core;

use foun10\EasyCache\Tests\Unit\Double\TestableEasyCache;
use PHPUnit\Framework\TestCase;

/**
 * How the admin settings are interpreted. The two "available" checks matter most: a toggle the
 * server cannot honour has to stay inert rather than produce broken cache files.
 */
class EasyCacheSettingsTest extends TestCase
{
    private function easyCache(): TestableEasyCache
    {
        return new TestableEasyCache();
    }

    public function testTheModuleIsOffUntilItIsSwitchedOn(): void
    {
        $this->assertFalse($this->easyCache()->isEnabled());
    }

    public function testAConfiguredLifetimeIsUsed(): void
    {
        $easyCache = $this->easyCache();
        $easyCache->stringSettings['foun10EasyCacheTTL'] = '600';

        $this->assertSame(600, $easyCache->getTtl());
    }

    /**
     * An unset, zero or non-numeric lifetime must not turn into "expires immediately" - every
     * page would then be a miss and the module would quietly cost more than it saves.
     *
     * @dataProvider uselessTtlValues
     */
    public function testAUselessLifetimeFallsBackToAnHour(string $configured): void
    {
        $easyCache = $this->easyCache();
        $easyCache->stringSettings['foun10EasyCacheTTL'] = $configured;

        $this->assertSame(3600, $easyCache->getTtl());
    }

    public function uselessTtlValues(): array
    {
        return [
            'empty' => [''],
            'zero' => ['0'],
            'negative' => ['-1'],
            'not a number' => ['soon'],
        ];
    }

    /**
     * Toggling gzip must never mutate the stored setting - the admin's choice stays as it was,
     * the server's capability just overrides the effect. Otherwise moving the shop to a host
     * with zlib would leave the setting silently switched off.
     */
    public function testGzipStaysInertWithoutZlib(): void
    {
        $easyCache = $this->easyCache();
        $easyCache->boolSettings['foun10EasyCacheGzip'] = true;
        $easyCache->gzipAvailable = false;

        $this->assertFalse($easyCache->isGzipEnabled());
    }

    public function testGzipIsUsedWhenAvailableAndSwitchedOn(): void
    {
        $easyCache = $this->easyCache();
        $easyCache->boolSettings['foun10EasyCacheGzip'] = true;
        $easyCache->gzipAvailable = true;

        $this->assertTrue($easyCache->isGzipEnabled());
    }

    /**
     * voku/html-min is an optional dependency, so the same rule applies: switched on without
     * the package present is a no-op, not a fatal.
     */
    public function testMinifyStaysInertWithoutTheOptionalPackage(): void
    {
        $easyCache = $this->easyCache();
        $easyCache->boolSettings['foun10EasyCacheMinify'] = true;
        $easyCache->minifyAvailable = false;

        $this->assertFalse($easyCache->isMinifyEnabled());
    }

    public function testMinifyIsUsedWhenThePackageIsPresent(): void
    {
        $easyCache = $this->easyCache();
        $easyCache->boolSettings['foun10EasyCacheMinify'] = true;
        $easyCache->minifyAvailable = true;

        $this->assertTrue($easyCache->isMinifyEnabled());
    }

    public function testStatsCollectionIsOffByDefault(): void
    {
        $this->assertFalse($this->easyCache()->isSaveStatsEnabled());
    }
}
