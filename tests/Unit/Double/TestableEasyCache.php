<?php

declare(strict_types=1);

namespace foun10\EasyCache\Tests\Unit\Double;

use foun10\EasyCache\Core\CacheStorageInterface;
use foun10\EasyCache\Core\EasyCache;

/**
 * EasyCache with every shop seam substituted, so the eligibility rules, the cache key and the
 * tag mapping can be driven without a Registry, a database or a container.
 *
 * The seams are the ones introduced in the production class for exactly this purpose; nothing
 * here reaches around them.
 */
class TestableEasyCache extends EasyCache
{
    /** @var array<string, bool> */
    public $boolSettings = [];

    /** @var array<string, string> */
    public $stringSettings = [];

    /** @var array<string, array> */
    public $collectionSettings = [];

    /** @var bool */
    public $adminMode = false;

    /** @var array<string, string> */
    public $requestParameters = [];

    /** @var FakeSession */
    public $session;

    /** @var string */
    public $shopId = '1';

    /** @var bool */
    public $gzipAvailable = true;

    /** @var bool */
    public $minifyAvailable = false;

    public function __construct(?CacheStorageInterface $storage = null)
    {
        $this->session = new FakeSession();
        $this->storage = $storage;
    }

    protected function getModuleSettingBoolean(string $name): bool
    {
        return $this->boolSettings[$name] ?? false;
    }

    protected function getModuleSettingString(string $name): string
    {
        return $this->stringSettings[$name] ?? '';
    }

    protected function getModuleSettingCollection(string $name): array
    {
        return $this->collectionSettings[$name] ?? [];
    }

    protected function isAdminMode(): bool
    {
        return $this->adminMode;
    }

    protected function getShopId(): string
    {
        return $this->shopId;
    }

    protected function getRequestParameter(string $name): string
    {
        return $this->requestParameters[$name] ?? '';
    }

    protected function getSession()
    {
        return $this->session;
    }

    /**
     * The token swap is protected in production - exposed here so it can be tested directly
     * instead of only through a storage round-trip.
     */
    public function stripTokens(string $html): string
    {
        return $this->stripLiveTokens($html);
    }

    public function injectTokens(string $html): string
    {
        return $this->injectLiveTokens($html);
    }

    public function isGzipAvailable(): bool
    {
        return $this->gzipAvailable;
    }

    public function isMinifyAvailable(): bool
    {
        return $this->minifyAvailable;
    }
}
