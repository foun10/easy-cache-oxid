<?php

declare(strict_types=1);

namespace foun10\EasyCache\Tests\Unit\Double;

/**
 * A product, category or manufacturer as the tag helpers see it: an id, and for variants a
 * parent id.
 */
class FakeEntity
{
    /** @var string */
    private $id;

    /** @var string */
    private $parentId;

    public function __construct(string $id, string $parentId = '')
    {
        $this->id = $id;
        $this->parentId = $parentId;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getParentId(): string
    {
        return $this->parentId;
    }
}
