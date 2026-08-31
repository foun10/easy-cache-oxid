<?php

declare(strict_types=1);

namespace foun10\EasyCache\Tests\Unit\Double;

/**
 * Stands in for OXID's session. getId() deliberately defaults to null rather than '': that is
 * what the OXID 7 line returns for a session that was never started, and it is what broke
 * stripLiveTokens() in production.
 */
class FakeSession
{
    /** @var string|null */
    public $id;

    /** @var string|null */
    public $challengeToken = '';

    /** @var array */
    public $variables = [];

    /** @var int */
    public $basketProductCount = 0;

    public function getId()
    {
        return $this->id;
    }

    public function getSessionChallengeToken()
    {
        return $this->challengeToken;
    }

    public function getVariable(string $name)
    {
        return $this->variables[$name] ?? null;
    }

    public function getBasket(): FakeBasket
    {
        return new FakeBasket($this->basketProductCount);
    }
}
