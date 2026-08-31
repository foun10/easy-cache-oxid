<?php

declare(strict_types=1);

namespace foun10\EasyCache\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Every setting declared in metadata.php needs a label in every admin language.
 *
 * Missing ones are not a crash: OXID renders the raw identifier, and for the group heading it
 * prints "ERROR: Translation for SHOP_MODULE_GROUP_... not found!" straight into the backend.
 * That is easy to ship and easy to miss, because the module's own settings page looks fine -
 * it uses its own labels, not these.
 *
 * The language directory differs per branch (views/admin on the Smarty line,
 * views/admin_twig on the Twig line), so it is discovered rather than hardcoded.
 */
class ModuleSettingLabelsTest extends TestCase
{
    /** @var array */
    private static $settings = [];

    public static function setUpBeforeClass(): void
    {
        $aModule = [];
        $sMetadataVersion = '';

        require __DIR__ . '/../../metadata.php';

        self::$settings = $aModule['settings'] ?? [];
    }

    public function testTheModuleDeclaresSettingsAtAll(): void
    {
        $this->assertNotEmpty(self::$settings, 'no settings found in metadata.php');
    }

    /**
     * @dataProvider languageFileProvider
     */
    public function testEverySettingHasALabel(string $languageFile): void
    {
        $labels = $this->loadLabels($languageFile);

        $missing = [];
        foreach (self::$settings as $setting) {
            $ident = 'SHOP_MODULE_' . $setting['name'];
            if (!isset($labels[$ident])) {
                $missing[] = $ident;
            }
        }

        $this->assertSame([], $missing, 'missing labels in ' . basename(dirname($languageFile)));
    }

    /**
     * @dataProvider languageFileProvider
     */
    public function testEverySettingGroupHasALabel(string $languageFile): void
    {
        $labels = $this->loadLabels($languageFile);

        $missing = [];
        foreach (self::$settings as $setting) {
            if (!isset($setting['group'])) {
                continue;
            }
            $ident = 'SHOP_MODULE_GROUP_' . $setting['group'];
            if (!isset($labels[$ident])) {
                $missing[] = $ident;
            }
        }

        $this->assertSame(
            [],
            array_unique($missing),
            'a missing group label prints "ERROR: Translation for ... not found!" in the backend'
        );
    }

    private function loadLabels(string $languageFile): array
    {
        $aLang = [];
        $sLangName = '';

        require $languageFile;

        return $aLang;
    }

    public function languageFileProvider(): array
    {
        $files = glob(__DIR__ . '/../../views/admin*/*/*_lang.php');

        $cases = [];
        foreach ($files as $file) {
            $cases[basename(dirname($file))] = [$file];
        }

        return $cases;
    }
}
