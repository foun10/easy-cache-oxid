<?php

declare(strict_types=1);

namespace foun10\EasyCache\Tests\Unit\Double;

class FakeBasket
{
    /** @var int */
    private $productCount;

    public function __construct(int $productCount)
    {
        $this->productCount = $productCount;
    }

    public function getProductsCount(): int
    {
        return $this->productCount;
    }
}
