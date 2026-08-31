<?php

declare(strict_types=1);

namespace foun10\EasyCache\Tests\Unit\Double;

class FakeProductView extends FakeView
{
    /** @var mixed */
    private $entity;

    public function __construct(string $classKey, $entity, string $viewId = 'viewid')
    {
        parent::__construct($classKey, $viewId);
        $this->entity = $entity;
    }

    public function getProduct()
    {
        return $this->entity;
    }
}
