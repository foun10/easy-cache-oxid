<?php
declare(strict_types=1);

namespace foun10\EasyCache\Core;

use OxidEsales\Eshop\Core\DatabaseProvider;

/**
 * Cumulative hit/miss counters and response-time sums per FOUN10VIEWCLASS
 * for the EasyCache admin stats page. Aggregate-only (one row per view
 * class, not per cache key) so a key's history survives its own
 * expiry/regeneration. $totalMs is the same "totalMs" value already sent in
 * the X-EasyCache-Timing debug header (see ShopControl), just accumulated
 * here instead of only logged per request.
 */
class EasyCacheStats
{
    public function recordMiss(string $viewClass, float $totalMs): void
    {
        $this->increment($viewClass, 'FOUN10MISSES', 'FOUN10LASTMISS', 'FOUN10MISSTIMEMS', $totalMs);
    }

    public function recordHit(string $viewClass, float $totalMs): void
    {
        $this->increment($viewClass, 'FOUN10HITS', 'FOUN10LASTHIT', 'FOUN10HITTIMEMS', $totalMs);
    }

    /**
     * $countColumn/$lastColumn/$timeColumn are always one of the fixed
     * column name literals above, never request-derived, so interpolating
     * them into the SQL here is safe.
     */
    protected function increment(
        string $viewClass,
        string $countColumn,
        string $lastColumn,
        string $timeColumn,
        float $totalMs
    ): void {
        $db = DatabaseProvider::getDb();

        $db->execute(
            "INSERT INTO foun10easycachestats (FOUN10VIEWCLASS, {$countColumn}, {$lastColumn}, {$timeColumn})
                VALUES (?, 1, NOW(), ?)
             ON DUPLICATE KEY UPDATE
                {$countColumn} = {$countColumn} + 1,
                {$lastColumn} = NOW(),
                {$timeColumn} = {$timeColumn} + VALUES({$timeColumn})",
            [$viewClass, $totalMs]
        );
    }

    public function clearAll(): void
    {
        DatabaseProvider::getDb()->execute('TRUNCATE TABLE foun10easycachestats');
    }

    /**
     * @return array<int, array{viewclass: string, requests: int, hits: int, misses: int, ratio: float, avgHitMs: ?float, avgMissMs: ?float}>
     */
    public function getStatsByViewClass(): array
    {
        $rows = DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC)->getAll(
            'SELECT FOUN10VIEWCLASS, FOUN10HITS, FOUN10MISSES, FOUN10HITTIMEMS, FOUN10MISSTIMEMS
               FROM foun10easycachestats
              ORDER BY (FOUN10HITS + FOUN10MISSES) DESC, FOUN10VIEWCLASS ASC'
        );

        $stats = [];
        foreach ($rows as $row) {
            $hits = (int) $row['FOUN10HITS'];
            $misses = (int) $row['FOUN10MISSES'];
            $requests = $hits + $misses;
            $hitTimeMs = (float) $row['FOUN10HITTIMEMS'];
            $missTimeMs = (float) $row['FOUN10MISSTIMEMS'];

            $stats[] = [
                'viewclass' => (string) $row['FOUN10VIEWCLASS'],
                'requests' => $requests,
                'hits' => $hits,
                'misses' => $misses,
                'ratio' => $requests > 0 ? round($hits / $requests * 100, 1) : 0.0,
                'hitTimeMs' => $hitTimeMs,
                'missTimeMs' => $missTimeMs,
                'avgHitMs' => $hits > 0 ? round($hitTimeMs / $hits, 1) : null,
                'avgMissMs' => $misses > 0 ? round($missTimeMs / $misses, 1) : null,
            ];
        }

        return $stats;
    }
}
