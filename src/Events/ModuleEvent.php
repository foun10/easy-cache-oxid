<?php
declare(strict_types=1);

namespace foun10\EasyCache\Events;

use OxidEsales\Eshop\Core\DatabaseProvider;

/**
 * Creates the table backing the EasyCache statistics page.
 *
 * Aggregate-only: one row per view class, keyed on FOUN10VIEWCLASS itself
 * rather than an OXID column, because EasyCacheStats writes with
 * INSERT ... ON DUPLICATE KEY UPDATE and never supplies an id of its own.
 * The counter columns default to 0 and both datetime columns are nullable,
 * so a request that only touches the hit side leaves the miss side alone
 * instead of failing the insert.
 *
 * The timing columns were added to this table by a later ALTER in the
 * module's internal history; for a fresh install there is no reason to
 * replay that, so they are part of the CREATE here.
 */
class ModuleEvent
{
    /**
     * Event called on module activation
     */
    public static function onActivate()
    {
        $database = DatabaseProvider::getDb();

        $database->execute("
            CREATE TABLE IF NOT EXISTS foun10easycachestats (
                FOUN10VIEWCLASS varchar(64) not null primary key,
                FOUN10HITS int default 0 not null,
                FOUN10MISSES int default 0 not null,
                FOUN10LASTHIT datetime null,
                FOUN10LASTMISS datetime null,
                FOUN10HITTIMEMS double default 0 not null,
                FOUN10MISSTIMEMS double default 0 not null
            );
        ");
    }
}
