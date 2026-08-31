<?php

declare(strict_types=1);

namespace foun10\EasyCache\Tests\Unit\Double;

class FakeListView extends FakeView
{
    /** @var mixed */
    private $entity;

    public function __construct(string $classKey, $entity, string $viewId = 'viewid')
    {
        parent::__construct($classKey, $viewId);
        $this->entity = $entity;
    }

    public function getActiveCategory()
    {
        return $this->entity;
    }
}
