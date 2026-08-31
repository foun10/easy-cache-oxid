<?php

declare(strict_types=1);

namespace foun10\EasyCache\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Verifies that the module is actually wired into the running shop.
 *
 * None of this is reachable from a unit test: the *_parent classes only exist once OXID has
 * built the extension chain for an activated module. These tests exist because of a failure
 * mode that produces no error at all - an override whose method name no longer matches the
 * parent. OXID 7 removed the underscore prefix from protected methods, so an override still
 * called _initializeViewObject() is simply never invoked. No exception, no log line; here it
 * would mean the timing header silently reporting nonsense.
 */
class ModuleExtensionsTest extends TestCase
{
    /**
     * @dataProvider extendedClassProvider
     */
    public function testShopClassIsExtendedByTheModule(string $shopClass, string $moduleClass): void
    {
        $instance = oxNew($shopClass);

        $this->assertInstanceOf(
            $moduleClass,
            $instance,
            $shopClass . ' is not extended - check the extend map in metadata.php'
        );
    }

    public function extendedClassProvider(): array
    {
        $cases = [
            \OxidEsales\Eshop\Core\ShopControl::class => \foun10\EasyCache\Extension\Core\ShopControl::class,
            \OxidEsales\Eshop\Application\Model\Article::class => \foun10\EasyCache\Extension\Application\Model\Article::class,
        ];

        $data = [];
        foreach ($cases as $shopClass => $moduleClass) {
            $data[$shopClass] = [$shopClass, $moduleClass];
        }

        return $data;
    }

    /**
     * The override guard, derived by reflection rather than from a list.
     *
     * Every method a module extension declares is either an override - it hooks into the shop
     * by replacing a parent method - or a helper the module added itself. The dangerous case is
     * a method that was an override and quietly stopped being one, because the shop then never
     * calls it: no error, no log entry, the feature simply does nothing.
     *
     * Anything genuinely new must be named below, which makes adding one a deliberate act.
     *
     * @dataProvider extensionClassProvider
     */
    public function testEveryMethodEitherOverridesSomethingOrIsDeclaredNew(string $moduleClass): void
    {
        $parent = get_parent_class($moduleClass);
        $this->assertNotFalse($parent, $moduleClass . ' has no parent - is the module activated?');

        $reflection = new \ReflectionClass($moduleClass);
        $unexpected = [];

        foreach ($reflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $moduleClass) {
                continue;
            }
            if (method_exists($parent, $method->getName())) {
                continue;
            }
            if (in_array($method->getName(), self::METHODS_THE_MODULE_ADDS, true)) {
                continue;
            }
            $unexpected[] = $method->getName();
        }

        $this->assertSame(
            [],
            $unexpected,
            sprintf(
                '%s declares method(s) that override nothing in %s. Either the shop renamed them '
                . '(then the hook is dead and the feature silently stopped working), or they are '
                . 'new helpers and belong in METHODS_THE_MODULE_ADDS.',
                $moduleClass,
                $parent
            )
        );
    }

    /**
     * Methods the module adds on purpose.
     */
    private const METHODS_THE_MODULE_ADDS = [
        // ShopControl helpers
        'getEasyCache',
        'easyCacheSinceRequestStartMs',
        'registerStatsWrite',
        // Article invalidation helpers
        'invalidateEasyCacheIfSoldOut',
        'getEasyCacheCategoryIds',
    ];

    public function extensionClassProvider(): array
    {
        $cases = [];

        foreach (glob(__DIR__ . '/../../src/Extension/Core/*.php') as $file) {
            $class = 'foun10\\EasyCache\\Extension\\Core\\' . basename($file, '.php');
            $cases[$class] = [$class];
        }

        foreach (glob(__DIR__ . '/../../src/Extension/Application/Model/*.php') as $file) {
            $class = 'foun10\\EasyCache\\Extension\\Application\\Model\\' . basename($file, '.php');
            $cases[$class] = [$class];
        }

        foreach (glob(__DIR__ . '/../../src/Extension/Application/Controller/Admin/*.php') as $file) {
            $class = 'foun10\\EasyCache\\Extension\\Application\\Controller\\Admin\\' . basename($file, '.php');
            $cases[$class] = [$class];
        }

        return $cases;
    }
}
