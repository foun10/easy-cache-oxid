<?php

declare(strict_types=1);

namespace foun10\EasyCache\Tests\Unit\Core;

use foun10\EasyCache\Core\FileCacheStorage;
use PHPUnit\Framework\TestCase;

/**
 * The storage backend, exercised against a real temporary directory rather than a virtual one:
 * expiry, gzip and tag invalidation all hinge on what actually lands on disk, and a virtual
 * filesystem would only prove the module agrees with itself.
 */
class FileCacheStorageTest extends TestCase
{
    /** @var string */
    private $cacheDir;

    /** @var FileCacheStorage */
    private $storage;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/easycache-test-' . bin2hex(random_bytes(6));
        $this->storage = new FileCacheStorage($this->cacheDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->cacheDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($dir);
    }

    private function future(): int
    {
        return time() + 3600;
    }

    public function testAStoredEntryComesBackUnchanged(): void
    {
        $this->storage->set('key', '<html>page</html>', $this->future());

        $this->assertSame('<html>page</html>', $this->storage->get('key'));
    }

    public function testAnUnknownKeyReturnsNull(): void
    {
        $this->assertNull($this->storage->get('never-written'));
    }

    /**
     * Expiry is what bounds how stale a page can get when nothing invalidates it explicitly,
     * so an entry past its time must not be served even though the file is still there.
     */
    public function testAnExpiredEntryIsNotServed(): void
    {
        $this->storage->set('key', 'stale', time() - 1);

        $this->assertNull($this->storage->get('key'));
    }

    public function testAnEntryIsServedRightUpToItsExpiry(): void
    {
        $this->storage->set('key', 'fresh', time() + 60);

        $this->assertSame('fresh', $this->storage->get('key'));
    }

    public function testGzippedEntriesRoundTrip(): void
    {
        $body = str_repeat('<p>compressible</p>', 50);

        $this->storage->set('key', $body, $this->future(), true);

        $this->assertSame($body, $this->storage->get('key', true));
    }

    public function testGzipActuallyShrinksTheStoredFile(): void
    {
        $body = str_repeat('<p>compressible</p>', 200);

        $this->storage->set('raw', $body, $this->future(), false);
        $rawSize = $this->storage->getStats()['sizeBytes'];

        $this->storage->clear();

        $this->storage->set('gz', $body, $this->future(), true);
        $gzipSize = $this->storage->getStats()['sizeBytes'];

        $this->assertLessThan($rawSize, $gzipSize);
    }

    public function testClearRemovesStoredPages(): void
    {
        $this->storage->set('one', 'a', $this->future());
        $this->storage->set('two', 'b', $this->future());

        $this->storage->clear();

        $this->assertNull($this->storage->get('one'));
        $this->assertNull($this->storage->get('two'));
    }

    /**
     * The cache directory lives under the shop directory, which is the document root on the
     * OXID 6 line - without this file every cached page is downloadable as a static file.
     * clear() must not take the protection with it.
     */
    public function testTheCacheDirectoryIsProtectedAgainstWebAccess(): void
    {
        $this->storage->set('key', 'body', $this->future());

        $htaccess = $this->cacheDir . '/.htaccess';

        $this->assertFileExists($htaccess);
        $this->assertStringContainsString('Deny from all', file_get_contents($htaccess));

        $this->storage->clear();

        $this->assertFileExists($htaccess, 'clearing the cache must not expose the directory again');
    }

    public function testStatsCountStoredPages(): void
    {
        $this->storage->set('one', 'a', $this->future());
        $this->storage->set('two', 'b', $this->future());

        $stats = $this->storage->getStats();

        $this->assertSame(2, $stats['count']);
        $this->assertGreaterThan(0, $stats['sizeBytes']);
    }

    public function testInvalidatingATagRemovesTheEntriesCarryingIt(): void
    {
        $this->storage->set('product-page', 'a', $this->future(), false, ['product-1']);
        $this->storage->set('other-page', 'b', $this->future(), false, ['product-2']);

        $removed = $this->storage->invalidateTags(['product-1']);

        $this->assertSame(1, $removed);
        $this->assertNull($this->storage->get('product-page'));
        $this->assertSame('b', $this->storage->get('other-page'), 'an unrelated page was dropped');
    }

    /**
     * A listing page carries both its category and, through the article extension, whatever
     * else invalidates it - so one entry has to be reachable under several tags.
     */
    public function testAnEntryIsInvalidatedByAnyOfItsTags(): void
    {
        $this->storage->set('page', 'a', $this->future(), false, ['product-1', 'category-9']);

        $this->assertSame(1, $this->storage->invalidateTags(['category-9']));
        $this->assertNull($this->storage->get('page'));
    }

    public function testInvalidatingAnUnusedTagRemovesNothing(): void
    {
        $this->storage->set('page', 'a', $this->future(), false, ['product-1']);

        $this->assertSame(0, $this->storage->invalidateTags(['product-does-not-exist']));
        $this->assertSame('a', $this->storage->get('page'));
    }
}
