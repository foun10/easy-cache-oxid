<?php

declare(strict_types=1);

namespace foun10\EasyCache\Tests\Integration;

use foun10\EasyCache\Command\CacheClearCommand;
use foun10\EasyCache\Core\EasyCache;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The clear command, driven the way oe-console drives it: through Command::run(), not by
 * calling execute() directly.
 *
 * That distinction is the whole point of this test. execute() on its own does its job and
 * returns; it is run() that enforces the int return type Symfony requires, and getting that
 * wrong kills the process *after* the cache is already gone - so the command looks like it
 * worked, exits 255, and the nightly cron the README recommends mails a fatal every night.
 * Nothing in the unit suite can catch it, because the console component comes from the shop
 * and its behaviour differs across the OXID versions in the matrix.
 *
 * Note this clears the whole cache of whatever shop it runs against - which is what the
 * command is for, and the shops it runs against are throwaway test installations.
 */
class CacheClearCommandTest extends TestCase
{
    /** @var EasyCache */
    private $easyCache;

    /** @var string */
    private $tag;

    protected function setUp(): void
    {
        $this->easyCache = oxNew(EasyCache::class);
        $this->tag = 'integration-clear-' . bin2hex(random_bytes(6));
    }

    private function key(): string
    {
        return 'integration-clear-key-' . $this->tag;
    }

    public function testTheCommandReportsSuccessToTheShell(): void
    {
        $tester = new CommandTester(new CacheClearCommand());

        $exitCode = $tester->execute([]);

        $this->assertSame(
            0,
            $exitCode,
            'a non-zero exit turns the documented nightly cron entry into a nightly error mail'
        );
    }

    public function testTheCommandRemovesACachedPage(): void
    {
        $this->easyCache->write($this->key(), '<html>x</html>', [$this->tag]);
        $this->assertNotNull($this->easyCache->read($this->key()));

        (new CommandTester(new CacheClearCommand()))->execute([]);

        $this->assertNull($this->easyCache->read($this->key()));
    }

    public function testTheCommandSaysHowManyFilesItRemoved(): void
    {
        $this->easyCache->write($this->key(), '<html>x</html>', [$this->tag]);

        $tester = new CommandTester(new CacheClearCommand());
        $tester->execute([]);

        $this->assertMatchesRegularExpression(
            '/Removed \d+ cached file\(s\)\./',
            $tester->getDisplay()
        );
    }
}
