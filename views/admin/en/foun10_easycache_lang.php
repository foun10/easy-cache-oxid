<?php

$sLangName = 'English';

$aLang = [
    'charset' => 'UTF-8',

    // Labels for OXID's own module settings tab. The module ships a dedicated
    // settings page (EC_* above), but the settings are declared in metadata.php
    // and therefore also appear on the standard tab - without these keys OXID
    // renders the raw identifiers and errors on the missing group heading.
    'SHOP_MODULE_GROUP_foun10EasyCache' => 'EasyCache',
    'SHOP_MODULE_foun10EasyCacheEnabled' => 'Enable caching',
    'SHOP_MODULE_foun10EasyCacheTTL' => 'Cache lifetime (TTL) in seconds',
    'SHOP_MODULE_foun10EasyCacheWhitelist' => 'Cached controllers (whitelist)',
    'SHOP_MODULE_foun10EasyCacheSaveStats' => 'Save statistics',
    'SHOP_MODULE_foun10EasyCacheGzip' => 'Compress cache files (gzip)',
    'SHOP_MODULE_foun10EasyCacheMinify' => 'Minify HTML (whitespace only)',

    'FOUN10_MODULES' => 'foun10 Modules',
    'FOUN10_EASYCACHE' => 'EasyCache',
    'FOUN10_EASYCACHE_SETTINGS' => 'Settings',
    'FOUN10_EASYCACHE_STATS' => 'Stats',
    'FOUN10_EASYCACHE_CLEAR' => 'Clear cache',

    'EC_INTRO_TEXT' => 'EasyCache stores a static HTML copy of selected shop pages and serves it directly on later requests, without running the full shop logic again. Only the controllers listed in the whitelist (e.g. start page, article lists, article details, content pages) are cached at all - every other page, such as checkout, account or orders, always runs normally. Caching also only applies to visitors who are not logged in and have an empty basket: as soon as a customer is logged in or has items in the basket, the page is always rendered live and never cached or served from the cache. The statistics below show how often a page was served from cache (hit) or freshly generated (miss).',

    'EC_STATUS_ENABLED' => 'Active',
    'EC_STATUS_DISABLED' => 'Inactive',

    'EC_SECTION_GENERAL' => 'General settings',
    'EC_LABEL_ENABLED' => 'Enable caching',
    'EC_LABEL_TTL' => 'Cache lifetime (TTL)',
    'EC_HINT_TTL' => 'How long a cache entry stays valid, in seconds, before it gets regenerated automatically (independent of clearing it by tag). Examples: 3600 = 1 hour, 14400 = 4 hours, 21600 = 6 hours, 43200 = 12 hours, 86400 = 24 hours.',
    'EC_LABEL_WHITELIST' => 'Cached controllers (whitelist)',
    'EC_HINT_WHITELIST' => 'Comma-separated list of controller class keys that may be cached. Anything not listed here is never cached, no matter what. Defaults to: start, alist, details, content. Clearing the field stops all caching without turning the module off.',
    'EC_HINT_DEPLOY' => 'Note: some setups deploy the module configuration from version control, in which case a deployment can overwrite whatever is set on this page. If that applies to this shop, maintain these values in the deployed configuration instead.',
    'EC_LABEL_SAVE_STATS' => 'Save statistics',
    'EC_HINT_SAVE_STATS' => 'Records a cache hit or miss per controller on every frontend request. Adds extra database writes - only enable when needed.',
    'EC_LABEL_GZIP' => 'Compress cache files (gzip)',
    'EC_HINT_GZIP' => 'Significantly reduces disk usage of the cache files (typically 75-85%). Toggling this needs no manual cache clear - old files simply stop being used and fade out over time.',
    'EC_HINT_GZIP_UNAVAILABLE' => 'The PHP "zlib" extension is not available on this server. This setting currently has no effect, even if enabled.',
    'EC_HINT_MINIFY_UNAVAILABLE' => 'The optional package "voku/html-min" is not installed. This setting currently has no effect, even if enabled - run "composer require voku/html-min" in the shop to use it.',
    'EC_LABEL_MINIFY' => 'Minify HTML (whitespace only)',
    'EC_HINT_MINIFY' => 'Strips redundant whitespace/line breaks from the cached HTML before storing it - meaningfully reduces file size on top of gzip, without changing any visible content, scripts or formatting. Toggling this needs no manual cache clear - already-stored pages simply get replaced by new ones over time.',
    'EC_BUTTON_SAVE' => 'Save',
    'EC_MSG_SAVED' => 'Settings saved.',

    'EC_SECTION_CACHE_MANAGEMENT' => 'Cache management',
    'EC_HINT_CLEAR_CACHE' => 'Removes all cached pages from disk and resets the statistics.',
    'EC_BUTTON_CLEAR_CACHE' => 'Clear cache',
    'EC_CONFIRM_CLEAR_CACHE' => 'Really clear the entire cache?',
    'EC_MSG_CACHE_CLEARED' => 'Cache cleared.',
    'EC_MSG_CACHE_CLEARED_FILES' => 'files removed',
    'EC_FILE_STATS_LABEL' => 'Currently cached',
    'EC_FILE_STATS_FILES' => 'files',
    'EC_HINT_COUNT_FILES' => 'Fully scans the cache directory on disk - can take a moment with very many files. Not run automatically when this page loads.',
    'EC_BUTTON_COUNT_FILES' => 'Count cache files',

    'EC_SECTION_STATS' => 'Cache statistics',
    'EC_STATS_DISABLED_HINT' => 'Stats collection is disabled. Enable "Save statistics" above to start collecting data.',
    'EC_STATS_EMPTY' => 'No stats data yet.',
    'EC_TABLE_VIEWCLASS' => 'View (FOUN10VIEWCLASS)',
    'EC_TABLE_REQUESTS' => 'Requests',
    'EC_TABLE_HITS' => 'Hits',
    'EC_TABLE_MISSES' => 'Misses',
    'EC_TABLE_RATIO' => 'Hit ratio',
    'EC_TABLE_AVG_HIT_MS' => 'Avg response time (hit)',
    'EC_TABLE_AVG_MISS_MS' => 'Avg response time (miss)',
    'EC_TABLE_TOTAL' => 'Total',
    'EC_BUTTON_RELOAD_STATS' => 'Reload',
    'EC_BUTTON_RESET_STATS' => 'Reset statistics',
    'EC_CONFIRM_RESET_STATS' => 'Really reset the statistics?',
    'EC_MSG_STATS_RESET' => 'Statistics have been reset.',

    'EC_SECTION_CLEAR_ALL' => 'Clear entire cache',
    'EC_SECTION_CLEAR_START' => 'Clear start page',
    'EC_HINT_CLEAR_START' => 'The start page has no product/category id of its own, so it is not automatically cleared by stock changes - clear it here manually and on demand.',
    'EC_BUTTON_CLEAR_START' => 'Clear start page',
    'EC_MSG_TAG_CLEARED' => 'Cache entries cleared for tag',

    'EC_SECTION_CLEAR_TAG' => 'Clear cache for a product/category/manufacturer',
    'EC_HINT_CLEAR_TAG' => 'Search by title and pick a result to clear every cached page that includes this product, category or manufacturer.',
    'EC_TAGTYPE_PRODUCT' => 'Product',
    'EC_TAGTYPE_CATEGORY' => 'Category',
    'EC_TAGTYPE_MANUFACTURER' => 'Manufacturer',
    'EC_SEARCH_PLACEHOLDER_PRODUCT' => 'Enter title or article number...',
    'EC_SEARCH_PLACEHOLDER_CATEGORY' => 'Enter category title...',
    'EC_SEARCH_PLACEHOLDER_MANUFACTURER' => 'Enter manufacturer title...',
    'EC_SEARCH_EMPTY' => 'No matches.',
    'EC_SELECTED_LABEL' => 'Selected',
    'EC_BUTTON_CLEAR_TAG' => 'Clear selected entry',
];
