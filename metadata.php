<?php

use foun10\EasyCache\Controller\Admin\ClearController;
use foun10\EasyCache\Controller\Admin\SettingsController;
use foun10\EasyCache\Controller\Admin\StatsController;
use foun10\EasyCache\Extension\Application\Model\Article;
use foun10\EasyCache\Events\ModuleEvent;
use foun10\EasyCache\Extension\Core\ShopControl;

$sMetadataVersion = '2.1';

/**
 * Metadata file for module
 */
$aModule = [
    'id' => 'foun10EasyCache',
    'title' => 'foun10 - EasyCache',
    'description' => [
        'de' => 'Dateibasierter Full-Page-Cache für ausgewählte Shop-Seiten.',
        'en' => 'File-based full-page cache for selected storefront pages.',
    ],
    'version' => '7.0.0',
    'author' => 'foun10 GmbH',
    'email' => 'info@foun10.de',
    'extend' => [
        \OxidEsales\Eshop\Core\ShopControl::class => ShopControl::class,
        \OxidEsales\Eshop\Application\Model\Article::class => Article::class,
    ],
    'events' => [
        'onActivate' => ModuleEvent::class . '::onActivate',
    ],
    'controllers' => [
        'foun10_easycache_settings' => SettingsController::class,
        'foun10_easycache_stats' => StatsController::class,
        'foun10_easycache_clear' => ClearController::class,
    ],
    // Empty on purpose: OXID 7 mounts views/twig/ as a Twig namespace under the
    // module id automatically, so templates are referenced as
    // '@foun10EasyCache/admin/<name>.html.twig' instead of being registered here.
    'templates' => [],
    'settings' => [
        [
            'group' => 'foun10EasyCache',
            'name' => 'foun10EasyCacheEnabled',
            'type' => 'bool',
            'value' => false,
        ],
        [
            'group' => 'foun10EasyCache',
            'name' => 'foun10EasyCacheTTL',
            'type' => 'str',
            'value' => '3600',
        ],
        [
            'group' => 'foun10EasyCache',
            'name' => 'foun10EasyCacheWhitelist',
            'type' => 'arr',
            'value' => ['start', 'alist', 'details', 'content'],
        ],
        [
            'group' => 'foun10EasyCache',
            'name' => 'foun10EasyCacheSaveStats',
            'type' => 'bool',
            'value' => false,
        ],
        [
            'group' => 'foun10EasyCache',
            'name' => 'foun10EasyCacheGzip',
            'type' => 'bool',
            'value' => false,
        ],
        [
            'group' => 'foun10EasyCache',
            'name' => 'foun10EasyCacheMinify',
            'type' => 'bool',
            'value' => false,
        ],
    ],
];
