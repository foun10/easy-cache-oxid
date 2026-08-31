<?php

declare(strict_types=1);

namespace foun10\EasyCache\Tests\Unit\Double;

use OxidEsales\Eshop\Core\Controller\BaseController;

/**
 * A view as EasyCache sees it. The tag helpers duck-type their way to the concrete controllers
 * via method_exists(), so the entity accessors are only added when a test asks for them - a
 * view without getProduct() has to be a supported case, not a fatal.
 */
class FakeView extends BaseController
{
    /** @var string */
    private $classKey;

    /** @var string */
    private $viewId;

    public function __construct(string $classKey = 'start', string $viewId = 'viewid')
    {
        $this->classKey = $classKey;
        $this->viewId = $viewId;
    }

    public function getClassKey()
    {
        return $this->classKey;
    }

    public function getViewId()
    {
        return $this->viewId;
    }
}
