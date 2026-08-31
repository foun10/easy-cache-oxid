<?php

declare(strict_types=1);

namespace OxidEsales\Eshop\Core\Controller;

/**
 * Minimal stand-in for the shop's BaseController, loaded by the unit bootstrap only when the
 * real one is absent. Carries just the two methods EasyCache calls on a view; the concrete
 * controller extras (getProduct(), getActiveCategory(), getContentId()) are duck-typed in
 * production via method_exists(), so the doubles add them as needed.
 */
class BaseController
{
    public function getClassKey()
    {
        return '';
    }

    public function getViewId()
    {
        return '';
    }
}
