<?php
declare(strict_types=1);

namespace foun10\EasyCache\Core;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Sharded file cache. Entries live under two levels of hash-prefix
 * subdirectories to keep any single directory from accumulating enough
 * files to slow down filesystem lookups.
 *
 * File layout: <cacheDir>/<hash[0:2]>/<hash[2:4]>/<hash>.cache
 * File content: one JSON header line, then the cached body - gzip-encoded
 * when $useGzip is true. Callers must pass the same $useGzip value used to
 * write a given key when reading it back; EasyCache::buildKey() already
 * folds the gzip setting into the key itself, so a key computed under one
 * setting never resolves to a file written under the other.
 *
 * Tag index: a reverse lookup from tag to cache-entry hash, so
 * invalidateTags() can delete exactly the entries a tag refers to without
 * scanning the whole cache. Tag directories are themselves sharded by
 * hash(tag) using the same two-level scheme as cache entries - a shop with
 * thousands of "product-<id>" tags would otherwise turn a flat tags/<tag>/
 * layout into thousands of directories in one place, the exact problem the
 * entry-sharding above already exists to avoid. Layout:
 * <cacheDir>/tags/<hash(tag)[0:2]>/<hash(tag)[2:4]>/<hash(tag)>/<entryHash>
 * (empty marker file, named by the cache entry's hash).
 */
class FileCacheStorage implements CacheStorageInterface
{
    protected $cacheDir;

    public function __construct(string $cacheDir)
    {
        $this->cacheDir = rtrim($cacheDir, '/\\');
    }

    /**
     * The cache directory sits under the shop directory, which on OXID 6 is the
     * document root - without this, every cached page is downloadable as a
     * static file (verified: HTTP 200 on the raw .cache path). The content is
     * only ever a guest view of a public page, but serving it straight off disk
     * bypasses the shop entirely and exposes the cache layout, so it gets the
     * same deny-all treatment OXID gives its own tmp/ directory.
     *
     * Written on the first write rather than on activation, so a manually
     * deleted cache directory gets its protection back on its own. clear()
     * already knows to keep .htaccess in place.
     */
    protected function protectCacheDir(): void
    {
        $file = $this->cacheDir . '/.htaccess';

        if (is_file($file)) {
            return;
        }

        if (!is_dir($this->cacheDir) && !@mkdir($this->cacheDir, 0755, true) && !is_dir($this->cacheDir)) {
            return;
        }

        @file_put_contents($file, "# disabling file access
"
            . "<FilesMatch .*>
"
            . "    <IfModule mod_authz_core.c>
"
            . "        Require all denied
"
            . "    </IfModule>
"
            . "    <IfModule !mod_authz_core.c>
"
            . "        Order allow,deny
"
            . "        Deny from all
"
            . "    </IfModule>
"
            . "</FilesMatch>
"
            . "
"
            . "Options -Indexes
");
    }

    public function get(string $key, bool $useGzip = false): ?string
    {
        $path = $this->getPathFromHash(md5($key));

        if (!is_file($path)) {
            return null;
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        $headerLine = fgets($handle);
        $header = $headerLine !== false ? json_decode($headerLine, true) : null;

        if (!is_array($header) || !isset($header['expires']) || $header['expires'] < time()) {
            fclose($handle);

            return null;
        }

        $body = stream_get_contents($handle);
        fclose($handle);

        if ($body === false) {
            return null;
        }

        if (!$useGzip) {
            return $body;
        }

        // The cache key already differs between gzip on/off (see EasyCache::
        // buildKey()), so a file found under a gzip-flavoured key is always
        // gzip-encoded - gzdecode() only returns false on genuine corruption.
        $decoded = @gzdecode($body);

        return $decoded === false ? null : $decoded;
    }

    public function set(string $key, string $body, int $expiresAt, bool $useGzip = false, array $tags = []): void
    {
        $hash = md5($key);
        $path = $this->getPathFromHash($hash);
        $dir = dirname($path);

        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return;
        }

        $this->protectCacheDir();

        if ($useGzip) {
            $body = gzencode($body);
        }

        $header = json_encode(['expires' => $expiresAt]);
        $content = $header . "\n" . $body;

        $tmpFile = @tempnam($dir, 'tmp_');
        if ($tmpFile === false) {
            return;
        }

        if (file_put_contents($tmpFile, $content, LOCK_EX) === false) {
            @unlink($tmpFile);

            return;
        }

        if (!@rename($tmpFile, $path)) {
            @unlink($tmpFile);

            return;
        }

        foreach (array_unique($tags) as $tag) {
            $this->addTagMarker($tag, $hash);
        }
    }

    /**
     * Deletes every cache entry referenced by any of the given tags, plus
     * the tag's own index directory. Entries tagged more than once in the
     * same call are only ever deleted once.
     *
     * @param string[] $tags
     * @return int number of cache entries invalidated
     */
    public function invalidateTags(array $tags): int
    {
        $removedHashes = [];

        foreach (array_unique($tags) as $tag) {
            $tagDir = $this->getTagDir($tag);

            if (!is_dir($tagDir)) {
                continue;
            }

            foreach (scandir($tagDir) ?: [] as $entryHash) {
                if ($entryHash === '.' || $entryHash === '..') {
                    continue;
                }

                if (!isset($removedHashes[$entryHash]) && @unlink($this->getPathFromHash($entryHash))) {
                    $removedHashes[$entryHash] = true;
                }

                @unlink($tagDir . '/' . $entryHash);
            }

            @rmdir($tagDir);
        }

        return count($removedHashes);
    }

    /**
     * Removes every cached entry (and now-empty shard directories), keeping
     * the directory itself and its .htaccess/.gitkeep protection files.
     * This also wipes the tags/ index tree, since it lives under $cacheDir.
     *
     * @return int number of cache files removed
     */
    public function clear(): int
    {
        if (!is_dir($this->cacheDir)) {
            return 0;
        }

        $removed = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->cacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $fileInfo) {
            $path = $fileInfo->getPathname();

            if ($fileInfo->isDir()) {
                @rmdir($path);
                continue;
            }

            if (in_array($fileInfo->getFilename(), ['.htaccess', '.gitkeep'], true)) {
                continue;
            }

            // Tag marker files don't count towards the reported "cache files
            // removed" figure - only real *.cache entries do.
            if (@unlink($path) && $fileInfo->getExtension() === 'cache') {
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Walks the whole cache directory to count entries and total size - a
     * real filesystem scan (same cost as clear()), so callers must only run
     * this on demand, never on every page load. Only counts real *.cache
     * entries, not tag index marker files.
     *
     * @return array{count: int, sizeBytes: int}
     */
    public function getStats(): array
    {
        if (!is_dir($this->cacheDir)) {
            return ['count' => 0, 'sizeBytes' => 0];
        }

        $count = 0;
        $sizeBytes = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->cacheDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'cache') {
                continue;
            }

            $count++;
            $sizeBytes += $fileInfo->getSize();
        }

        return ['count' => $count, 'sizeBytes' => $sizeBytes];
    }

    protected function addTagMarker(string $tag, string $entryHash): void
    {
        $tagDir = $this->getTagDir($tag);

        if (!is_dir($tagDir) && !@mkdir($tagDir, 0755, true) && !is_dir($tagDir)) {
            return;
        }

        @touch($tagDir . '/' . $entryHash);
    }

    protected function getPathFromHash(string $hash): string
    {
        return $this->cacheDir . '/' . substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash . '.cache';
    }

    protected function getTagDir(string $tag): string
    {
        $hash = md5($tag);

        return $this->cacheDir . '/tags/' . substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash;
    }
}
