<?php

declare(strict_types=1);

namespace foun10\EasyCache\Tests\Unit\Core;

use foun10\EasyCache\Tests\Unit\Double\TestableEasyCache;
use PHPUnit\Framework\TestCase;

/**
 * Session tokens are swapped for placeholders before a page is stored and swapped back for the
 * current visitor's own values when it is served.
 *
 * This is what makes a shared cache safe at all: without it, the first visitor's session id and
 * CSRF token would be baked into the stored HTML and handed to everyone after them.
 */
class EasyCacheTokensTest extends TestCase
{
    private function easyCache(string $sid = 'session-id', string $stoken = 'challenge-token'): TestableEasyCache
    {
        $easyCache = new TestableEasyCache();
        $easyCache->session->id = $sid;
        $easyCache->session->challengeToken = $stoken;

        return $easyCache;
    }

    public function testTheSessionIdIsRemovedBeforeStoring(): void
    {
        $stripped = $this->easyCache()->stripTokens('<a href="index.php?sid=session-id">x</a>');

        $this->assertStringNotContainsString('session-id', $stripped);
    }

    public function testTheChallengeTokenIsRemovedBeforeStoring(): void
    {
        $stripped = $this->easyCache()->stripTokens('<input value="challenge-token">');

        $this->assertStringNotContainsString('challenge-token', $stripped);
    }

    /**
     * The whole point: what goes in for one visitor comes back out carrying the next visitor's
     * own values, not the ones that were captured.
     */
    public function testAStoredPageIsServedWithTheCurrentVisitorsTokens(): void
    {
        $html = '<input value="challenge-token"><a href="index.php?sid=session-id">x</a>';

        $stored = $this->easyCache()->stripTokens($html);

        $secondVisitor = $this->easyCache('other-session', 'other-token');
        $served = $secondVisitor->injectTokens($stored);

        $this->assertStringContainsString('other-session', $served);
        $this->assertStringContainsString('other-token', $served);
        $this->assertStringNotContainsString('session-id', $served);
        $this->assertStringNotContainsString('challenge-token', $served);
    }

    public function testHtmlWithoutTokensIsLeftAlone(): void
    {
        $html = '<p>nothing to see here</p>';

        $this->assertSame($html, $this->easyCache()->stripTokens($html));
    }

    /**
     * OXID 7 returns null from Session::getId() when no session was started, where the 6.x line
     * returned ''. The !== '' guard lets null through, and str_replace() then raises a TypeError
     * under strict_types - which took the whole start page down with an HTTP 500 on the first
     * cache write.
     */
    public function testANullSessionIdDoesNotBlowUp(): void
    {
        $easyCache = new TestableEasyCache();
        $easyCache->session->id = null;
        $easyCache->session->challengeToken = null;

        $html = '<p>page</p>';

        $this->assertSame($html, $easyCache->stripTokens($html));
        $this->assertSame($html, $easyCache->injectTokens($html));
    }

    /**
     * An empty token must not be handed to str_replace() as a search string either - replacing
     * '' would splice the placeholder between every character of the page.
     */
    public function testAnEmptySessionIdIsNotUsedAsASearchString(): void
    {
        $easyCache = $this->easyCache('', '');

        $html = '<p>page</p>';

        $this->assertSame($html, $easyCache->stripTokens($html));
    }

    /**
     * The other half of the empty case, and the one an admin would actually see go wrong: the
     * page was stored by a visitor who had a session, so it carries placeholders, and the next
     * visitor arrives without one. There is nothing to put back - so the placeholders have to
     * resolve to nothing rather than being served literally, which would put "%%EASYCACHE_SID%%"
     * straight into a link on every such request.
     *
     * @dataProvider noSessionProvider
     */
    public function testPlaceholdersResolveToNothingForAVisitorWithoutASession($sid, $stoken): void
    {
        $stored = $this->easyCache()->stripTokens(
            '<input value="challenge-token"><a href="index.php?sid=session-id">x</a>'
        );

        $noSession = new TestableEasyCache();
        $noSession->session->id = $sid;
        $noSession->session->challengeToken = $stoken;

        $this->assertSame(
            '<input value=""><a href="index.php?sid=">x</a>',
            $noSession->injectTokens($stored)
        );
    }

    public function noSessionProvider(): array
    {
        return [
            'null, as OXID 7 returns it' => [null, null],
            'empty string, as 6.x does'  => ['', ''],
        ];
    }
}
