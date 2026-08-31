<?php

declare(strict_types=1);

/**
 * Bootstrap for the unit suite: the module's autoloader plus stand-ins for the few OXID
 * classes the production code type-hints.
 *
 * EasyCache::isRequestCacheable() and buildKey() declare BaseController, so a unit test cannot
 * pass a double without that class existing. Loading a minimal stub keeps the fast suite able
 * to cover the eligibility rules - the part where a mistake serves one visitor's page to
 * somebody else - instead of pushing all of that into the integration suite.
 *
 * Guarded, so nothing is shadowed if this ever runs with a shop present.
 */
require __DIR__ . '/../vendor/autoload.php';

if (!class_exists(\OxidEsales\Eshop\Core\Controller\BaseController::class)) {
    require __DIR__ . '/Stub/BaseController.php';
}
