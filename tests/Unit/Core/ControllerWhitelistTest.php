<?php

declare(strict_types=1);

namespace foun10\EasyCache\Tests\Unit\Core;

use foun10\EasyCache\Core\ControllerWhitelist;
use PHPUnit\Framework\TestCase;

/**
 * The whitelist is the one setting where a parsing slip is silent and expensive: an entry that
 * survives with a stray space attached simply never matches a class key again, so the page it
 * was meant to cache quietly stops being cached and nothing anywhere reports a problem.
 */
class ControllerWhitelistTest extends TestCase
{
    public function testItSplitsTheDefaultCommaSeparatedForm(): void
    {
        $this->assertSame(
            ['start', 'alist', 'details', 'content'],
            ControllerWhitelist::parse('start, alist, details, content')
        );
    }

    /**
     * @dataProvider separatorProvider
     */
    public function testItAcceptsAnyMixOfCommasWhitespaceAndLineBreaks(string $raw): void
    {
        $this->assertSame(['start', 'alist'], ControllerWhitelist::parse($raw));
    }

    public function separatorProvider(): array
    {
        return [
            'commas only'        => ['start,alist'],
            'comma and space'    => ['start, alist'],
            'spaces only'        => ['start alist'],
            'one entry per line' => ["start\nalist"],
            'windows line break' => ["start\r\nalist"],
            'tabs'               => ["start\talist"],
            'repeated separator' => ['start,,,   alist'],
            'leading separator'  => ['  , start, alist'],
            'trailing separator' => ['start, alist, '],
        ];
    }

    public function testItDropsDuplicatesAndKeepsTheGivenOrder(): void
    {
        $this->assertSame(
            ['details', 'start', 'alist'],
            ControllerWhitelist::parse('details, start, alist, start, details')
        );
    }

    public function testAnEmptyInputProducesAnEmptyList(): void
    {
        $this->assertSame([], ControllerWhitelist::parse(''));
        $this->assertSame([], ControllerWhitelist::parse("  ,\n \t "));
    }

    public function testFormattingProducesTheFormTheFieldShows(): void
    {
        $this->assertSame('start, alist', ControllerWhitelist::format(['start', 'alist']));
        $this->assertSame('', ControllerWhitelist::format([]));
    }

    /**
     * What an admin edits has to survive being saved and shown again unchanged, otherwise the
     * field slowly rewrites the configuration every time somebody opens the page and saves.
     */
    public function testParsingAFormattedListIsStable(): void
    {
        $list = ['start', 'alist', 'details', 'content'];

        $this->assertSame($list, ControllerWhitelist::parse(ControllerWhitelist::format($list)));
    }
}
