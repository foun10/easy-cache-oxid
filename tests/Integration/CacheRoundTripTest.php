<?php

declare(strict_types=1);

namespace foun10\EasyCache\Tests\Integration;

use foun10\EasyCache\Core\EasyCache;
use PHPUnit\Framework\TestCase;

/**
 * A page written and read back through the module as the shop wires it up - real cache
 * directory, real storage, real settings.
 *
 * The unit suite already covers the storage rules against a temporary directory. What it cannot
 * cover is whether the directory the running shop hands the module is usable at all: a wrong
 * sShopDir, a path the web server cannot write to, or a storage that silently swallows its own
 * write would all pass the unit tests and still leave every request a MISS.
 */
class CacheRoundTripTest extends TestCase
{
    /** @var string */
    private $tag;

    /** @var EasyCache */
    private $easyCache;

    protected function setUp(): void
    {
        $this->easyCache = oxNew(EasyCache::class);
        $this->tag = 'integration-test-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        // Only ever removes entries this test wrote - the shop's own cache is left alone.
        $this->easyCache->invalidateTags([$this->tag]);
    }

    private function key(): string
    {
        return 'integration-test-key-' . $this->tag;
    }

    public function testAPageSurvivesAWriteAndReadThroughTheRealCacheDirectory(): void
    {
        $html = '<html><body>integration ' . $this->tag . '</body></html>';

        $this->easyCache->write($this->key(), $html, [$this->tag]);

        $this->assertSame(
            $html,
            $this->easyCache->read($this->key()),
            'the shop-provided cache directory is not usable - every request would be a MISS'
        );
    }

    public function testAnUnknownKeyReadsBackAsNull(): void
    {
        $this->assertNull($this->easyCache->read('never-written-' . $this->tag));
    }

    /**
     * Tag invalidation is what makes a stock change take effect before the TTL runs out, and it
     * depends on a second directory tree next to the entries themselves.
     */
    public function testInvalidatingTheTagDropsThePage(): void
    {
        $this->easyCache->write($this->key(), '<html>x</html>', [$this->tag]);
        $this->assertNotNull($this->easyCache->read($this->key()));

        $removed = $this->easyCache->invalidateTags([$this->tag]);

        $this->assertSame(1, $removed);
        $this->assertNull($this->easyCache->read($this->key()));
    }

    /**
     * The session token swap runs on every write and read. With no session started - which is
     * exactly the state a CLI test runs in - OXID 7 returns null from getId(), and an
     * unguarded str_replace() would take the write down with a TypeError.
     */
    public function testWritingWorksWithoutAStartedSession(): void
    {
        $this->easyCache->write($this->key(), '<html>no session here</html>', [$this->tag]);

        $this->assertNotNull($this->easyCache->read($this->key()));
    }
}
