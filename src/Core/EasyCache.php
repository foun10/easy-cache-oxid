<?php
declare(strict_types=1);

namespace foun10\EasyCache\Core;

use foun10\DeepL\Core\DeepL;
use OxidEsales\Eshop\Core\Controller\BaseController;
use OxidEsales\Eshop\Core\Registry;
use Throwable;
use voku\helper\HtmlMin;

/**
 * Eligibility, cache-key and read/write coordination for the EasyCache
 * full-page file cache.
 *
 * Cache key: FrontendController::getViewId() is combined with the resolved
 * controller class key. Each whitelisted controller's own generateViewId()/
 * getViewId() override already folds in whatever request parameter actually
 * changes its output - ArticleListController adds cnid/page/sorting/display
 * type, ArticleDetailsController adds anid, ContentController adds oxcid -
 * so no separate handling of $_GET is needed for those. One exception is
 * handled here: mnid on manufacturerlist. ManufacturerListController
 * extends ArticleListController and doesn't override generateViewId() at
 * all, so it inherits ArticleListController's version, which only hashes
 * cnid/page/sorting/display type; mnid never enters the hash even though
 * getActManufacturer() reads it straight from the request. Without folding
 * it in here manually, every manufacturer's listing at the same
 * page/sorting/display type would collide on one cache key.
 *
 * The same trap applies to any third-party module that varies a whitelisted
 * page by a request parameter its controller's generateViewId() knows
 * nothing about - a filter or facet module on the listing pages is the
 * usual case. Such a parameter must either be folded into buildKey() or
 * excluded from caching in isRequestCacheable(); see the README section on
 * extending the module.
 *
 * details deliberately does NOT fold the selected delivery country itself
 * into the key (it used to - see git history). Any country-dependent
 * markup on a whitelisted page (e.g. the article details template's
 * shipping cost section) is expected to be loaded client-side (XHR/AJAX)
 * by the theme instead, using whatever country is already in the
 * visitor's session - that's theme/shop-specific presentation,
 * deliberately kept out of this module so it stays usable on any shop
 * installation regardless of theme.
 *
 * The one thing country still legitimately changes on any whitelisted page
 * (not just details - any of them can embed a product price via a
 * teaser/recommendation widget) is the displayed price, via VAT - not
 * addressable by an XHR patch-up the way the details page's shipping cost
 * is, since it can be the primary price shown on the page. This module
 * doesn't need to do anything about that itself, though: customGetViewId()
 * (modules/functions.php) is OXID's own documented extension point
 * (#0004798), already called unconditionally by FrontendController::
 * generateViewId() for every whitelisted controller, so $view->getViewId()
 * used above is already VAT-aware by the time it gets here - it collapses
 * country down to just the resulting VAT percentage, a much smaller
 * keyspace than raw country since many countries share the same VAT
 * treatment.
 */
class EasyCache
{
    public const MODULE_ID = 'foun10EasyCache';

    protected const PLACEHOLDER_STOKEN = '%%EASYCACHE_STOKEN%%';
    protected const PLACEHOLDER_SID = '%%EASYCACHE_SID%%';

    protected const EXCLUDED_PARAMS = ['sid', 'stoken', 'force_sid', 'force_admin_sid'];
    protected const ALIST_CLASS_KEY = 'alist';
    protected const MANUFACTURER_LIST_CLASS_KEY = 'manufacturerlist';
    protected const DETAILS_CLASS_KEY = 'details';
    protected const CONTENT_CLASS_KEY = 'content';
    protected const START_CLASS_KEY = 'start';

    // Not resolved from any entity id - the start page has no single
    // product/category/manufacturer it belongs to, so this is a fixed tag
    // for manual/admin-triggered invalidation only. Nothing invalidates it
    // automatically (e.g. a product selling out does NOT clear this tag),
    // since most sold-out products never even appear on the homepage.
    public const START_TAG = 'start';

    /** @var CacheStorageInterface|null */
    protected $storage;

    /** @var EasyCacheStats|null */
    protected $stats;

    /**
     * Collaborators and every shop lookup below sit behind seams so the class can be
     * constructed and driven without a running shop. Nothing is resolved in the
     * constructor - a `new EasyCache()` in a unit test must not need a Registry.
     *
     * The seam names are identical on the 2.x branch; only the settings implementation
     * differs there, because OXID 7 keeps module settings in var/configuration rather
     * than in oxconfig. Keeping the shape the same lets both branches share the whole
     * test suite unchanged.
     */
    protected function getStorage(): CacheStorageInterface
    {
        if ($this->storage === null) {
            $this->storage = new FileCacheStorage($this->getCacheDir());
        }

        return $this->storage;
    }

    protected function getCacheDir(): string
    {
        return Registry::getConfig()->getConfigParam('sShopDir') . 'foun10cache';
    }

    protected function getStatsStore(): EasyCacheStats
    {
        if ($this->stats === null) {
            $this->stats = new EasyCacheStats();
        }

        return $this->stats;
    }

    protected function getModuleSettingBoolean(string $name): bool
    {
        return (bool) Registry::getConfig()->getConfigParam($name);
    }

    protected function getModuleSettingString(string $name): string
    {
        return (string) Registry::getConfig()->getConfigParam($name);
    }

    protected function getModuleSettingCollection(string $name): array
    {
        return (array) Registry::getConfig()->getConfigParam($name);
    }

    protected function isAdminMode(): bool
    {
        return (bool) Registry::getConfig()->isAdmin();
    }

    protected function getShopId(): string
    {
        return (string) Registry::getConfig()->getShopId();
    }

    protected function getRequestParameter(string $name): string
    {
        return (string) Registry::getRequest()->getRequestEscapedParameter($name);
    }

    protected function getSession()
    {
        return Registry::getSession();
    }

    public function isEnabled(): bool
    {
        return $this->getModuleSettingBoolean('foun10EasyCacheEnabled');
    }

    public function getTtl(): int
    {
        $ttl = (int) $this->getModuleSettingString('foun10EasyCacheTTL');

        return $ttl > 0 ? $ttl : 3600;
    }

    public function isSaveStatsEnabled(): bool
    {
        return $this->getModuleSettingBoolean('foun10EasyCacheSaveStats');
    }

    /**
     * True only when the admin toggle is on AND the server can actually do
     * gzip - never mutates the stored setting itself, so an admin flipping
     * the toggle on a server without zlib just gets a no-op (see
     * isGzipAvailable() for the admin-page hint explaining why).
     */
    public function isGzipEnabled(): bool
    {
        return $this->getModuleSettingBoolean('foun10EasyCacheGzip') && $this->isGzipAvailable();
    }

    /**
     * function_exists() is a simple in-process reflection call - no I/O, no
     * shared state, nothing that could race - so this is safe to call on
     * every read()/write() without any caching or locking.
     */
    public function isGzipAvailable(): bool
    {
        return function_exists('gzencode') && function_exists('gzdecode');
    }

    /**
     * Deliberately a server/admin-panel setting, same as foun10EasyCacheGzip
     * - kept out of any configuration that gets rolled out from version
     * control, so a deployment can't silently flip it back for a server
     * where it has been tested and left on (or off).
     */
    public function isMinifyEnabled(): bool
    {
        return $this->getModuleSettingBoolean('foun10EasyCacheMinify') && $this->isMinifyAvailable();
    }

    /**
     * voku/html-min is an optional dependency: it drags in simple_html_dom and
     * symfony/css-selector, which a performance module has no business forcing
     * on shops that don't want minification - and on OXID 6.2 that tree also
     * collides with the shop's own test toolchain. Same shape as
     * isGzipAvailable(): the stored setting is never mutated, an admin who
     * turns minification on without the package present just gets a no-op plus
     * the hint on the settings page.
     */
    public function isMinifyAvailable(): bool
    {
        return class_exists(HtmlMin::class);
    }

    /**
     * Whether the current request may be served from / written to cache.
     * Only whitelisted controllers, requested as a plain GET page view by
     * a guest with an empty basket, qualify - any fnc= call changes shop
     * state and must always run for real. Account/checkout/basket and any
     * other non-whitelisted controller is never cached.
     *
     * $view is typed to BaseController, not FrontendController: views that
     * extend BaseController directly (older or hand-rolled AJAX controllers
     * from other modules, for instance) are dispatched through the same
     * ShopControl::executeAction(), so a FrontendController type hint here
     * would throw a TypeError for them. getClassKey()/getViewId() used
     * below are already declared on BaseController.
     */
    public function isRequestCacheable(BaseController $view, string $functionName): bool
    {
        if (!$this->isEnabled() || $this->isAdminMode()) {
            return false;
        }

        $classKey = (string) $view->getClassKey();

        if (!$this->isControllerWhitelisted($classKey)) {
            return false;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            return false;
        }

        if ($functionName !== '') {
            return false;
        }

        if ($this->hasExcludedParams()) {
            return false;
        }

        if ($this->getRequestParameter('renderPartial') !== '') {
            return false;
        }

        $session = $this->getSession();

        if ($session->getVariable('usr')) {
            return false;
        }

        if ($session->getBasket()->getProductsCount() > 0) {
            return false;
        }

        return true;
    }

    protected function isControllerWhitelisted(string $classKey): bool
    {
        return in_array($classKey, $this->getWhitelist(), true);
    }

    /**
     * Public because the settings page renders it back to the admin - the
     * stored value and the one the eligibility check uses have to be the same
     * list, or the page would show something the module does not act on.
     *
     * @return string[]
     */
    public function getWhitelist(): array
    {
        return $this->getModuleSettingCollection('foun10EasyCacheWhitelist');
    }

    /**
     * If sid/stoken/force_sid/force_admin_sid are present in the URL at
     * all, this is a session-continuation request (e.g. cookies disabled,
     * or a link carrying a challenge token) - it must run for real and
     * must never be served from or written to the shared cache.
     */
    protected function hasExcludedParams(): bool
    {
        foreach (self::EXCLUDED_PARAMS as $name) {
            if (isset($_GET[$name])) {
                return true;
            }
        }

        return false;
    }

    public function buildKey(BaseController $view): string
    {
        $shopId = $this->getShopId();
        $classKey = (string) $view->getClassKey();
        $viewId = (string) $view->getViewId();

        // DeepL's on-demand translation is a post-render pass applied on top
        // of the shop's base-language HTML (see foun10\DeepL\Extension\Core\
        // ShopControl/Output) - getViewId() knows nothing about it, so two
        // visitors with different langOnDemand cookies would otherwise
        // collide on the same key and get served each other's language.
        $key = $shopId . '|' . $classKey . '|' . $viewId . '|' . $this->getDeepLLanguage();

        // Folding the gzip setting into the key itself means toggling
        // foun10EasyCacheGzip needs no manual cache clear: it simply starts
        // writing/reading a disjoint set of keys, so a raw-written file can
        // never be handed to gzdecode() (or vice versa) - old entries under
        // the other setting just become dead weight until they expire or an
        // admin clears the cache.
        $key .= '|' . ($this->isGzipEnabled() ? 'gz' : 'raw');

        if ($classKey === self::MANUFACTURER_LIST_CLASS_KEY) {
            $key .= '|' . $this->getManufacturerId();
        }

        return md5($key);
    }

    protected function getManufacturerId(): string
    {
        return $this->getRequestParameter('mnid');
    }

    protected function getDeepLLanguage(): string
    {
        if (!class_exists(DeepL::class)) {
            return '';
        }

        try {
            return (string) Registry::get(DeepL::class)->getActiveLanguageOnDemand();
        } catch (Throwable $exception) {
            return '';
        }
    }

    /**
     * False while DeepL's per-request translation time budget was exceeded
     * mid-render, i.e. the page currently being rendered has some fields
     * left untranslated. Caching such a page would freeze the incomplete
     * state and stop it from ever finishing translation on later visits -
     * so write() must skip persisting it and let the next request retry.
     * Defaults to true (safe to cache) when DeepL isn't installed at all,
     * since there is nothing that could be partially translated; defaults
     * to false (skip caching) if the throttle state itself can't be read,
     * to stay on the conservative side.
     */
    protected function isFullyTranslated(): bool
    {
        if (!class_exists(DeepL::class)) {
            return true;
        }

        try {
            return !Registry::get(DeepL::class)->isTranslationThrottled();
        } catch (Throwable $exception) {
            return false;
        }
    }

    public function read(string $key): ?string
    {
        $body = $this->getStorage()->get($key, $this->isGzipEnabled());

        return $body === null ? null : $this->injectLiveTokens($body);
    }

    /**
     * @param string[] $tags
     */
    public function write(string $key, string $output, array $tags = []): void
    {
        if (!$this->isFullyTranslated()) {
            return;
        }

        $ttl = $this->getTtl();

        $this->getStorage()->set(
            $key,
            $this->stripLiveTokens($this->minify($output)),
            time() + $ttl,
            $this->isGzipEnabled(),
            $tags
        );
    }

    /**
     * Whitespace-only minification via voku/html-min (optional dependency -
     * see isMinifyAvailable()), deliberately using the
     * library's own DOM-based whitespace handling (doSumUpWhitespace,
     * doRemoveWhitespaceAroundTags, doRemoveSpacesBetweenTags) rather than a
     * hand-rolled regex - a naive ">\s+<" collapse breaks visually
     * significant whitespace between inline elements (e.g. "<span>A</span>
     * <span>B</span>"), which is exactly the class of bug this library
     * exists to avoid (it understands which tags are inline vs block, and
     * leaves <pre>/<code>/<script>/<style>/<textarea> untouched).
     *
     * Every non-whitespace feature the library ships is explicitly turned
     * off below, even where that means overriding an enabled-by-default
     * option - this must stay a size optimization, never a markup rewrite.
     * doRemoveOmittedHtmlTags in particular defaults to true and silently
     * drops </html>/</head>/</body> (legal per HTML5, but not something
     * we're choosing to do); doRemoveOmittedQuotes defaults to true and
     * turns e.g. lang="en" into lang=en. Both were caught by actually
     * inspecting a real cached page after enabling this, not by assumption.
     *
     * Never allowed to break caching itself: any exception (malformed
     * markup the parser can't handle, a future library regression, ...)
     * just skips minification for this one write and falls back to the
     * original HTML - a bigger cache file beats a broken or lost cache
     * entry.
     */
    protected function minify(string $output): string
    {
        if (!$this->isMinifyEnabled()) {
            return $output;
        }

        try {
            $minifier = new HtmlMin();

            // Whitespace normalization - the entire point of this method.
            $minifier->doRemoveWhitespaceAroundTags();
            $minifier->doRemoveSpacesBetweenTags();
            $minifier->doSumUpWhitespace();

            // Everything below is NOT whitespace and must stay disabled.
            $minifier->doRemoveComments(false);
            $minifier->doRemoveOmittedHtmlTags(false);
            $minifier->doRemoveOmittedQuotes(false);
            $minifier->doSortCssClassNames(false);
            $minifier->doSortHtmlAttributes(false);
            $minifier->doRemoveDeprecatedAnchorName(false);
            $minifier->doRemoveDeprecatedScriptCharsetAttribute(false);
            $minifier->doRemoveDeprecatedTypeFromScriptTag(false);
            $minifier->doRemoveDeprecatedTypeFromStylesheetLink(false);
            $minifier->doRemoveDeprecatedTypeFromStyleAndLinkTag(false);
            $minifier->doRemoveDefaultMediaTypeFromStyleAndLinkTag(false);
            $minifier->doRemoveEmptyAttributes(false);
            $minifier->doRemoveValueFromEmptyInput(false);

            return $minifier->minify($output);
        } catch (Throwable $exception) {
            return $output;
        }
    }

    /**
     * @return int number of cache entries invalidated
     */
    public function invalidateTags(array $tags): int
    {
        return $this->getStorage()->invalidateTags($tags);
    }

    /**
     * Tags describing which product/category/manufacturer/content entities
     * a cached page's HTML actually depends on, so a stock or price change
     * on one product can invalidate exactly the cache entries that show it
     * instead of waiting out the TTL or clearing everything.
     *
     * Every class key in foun10EasyCacheWhitelist must resolve here, but an
     * unhandled one falls through to the default empty-tags case rather
     * than throwing - adding a class key to the whitelist without also
     * adding it here just means that page loses fine-grained invalidation
     * (falls back to TTL expiry), never a broken write().
     */
    public function getTags(BaseController $view): array
    {
        switch ((string) $view->getClassKey()) {
            case self::DETAILS_CLASS_KEY:
                return $this->getProductTags($view);

            case self::ALIST_CLASS_KEY:
                return $this->getListTags($view, 'category');

            case self::MANUFACTURER_LIST_CLASS_KEY:
                return $this->getListTags($view, 'manufacturer');

            case self::CONTENT_CLASS_KEY:
                return $this->getContentTags($view);

            case self::START_CLASS_KEY:
                return [self::START_TAG];

            default:
                return [];
        }
    }

    /**
     * Guarded with method_exists() rather than an instanceof check against
     * the concrete OXID controller: this only ever runs for the 'details'
     * class key, whichever controller class the shop's extension chain
     * currently resolves that to, so duck-typing keeps it correct even if
     * that chain changes.
     */
    protected function getProductTags(BaseController $view): array
    {
        if (!method_exists($view, 'getProduct')) {
            return [];
        }

        $product = $view->getProduct();

        if (!is_object($product) || !method_exists($product, 'getId')) {
            return [];
        }

        $tags = ['product-' . $product->getId()];

        // A variant's details page also shows parent-level markup (e.g.
        // shared description/attributes), so a change that invalidates the
        // parent (see Article extension) must invalidate this variant's
        // cached page too, not just the parent's own details page.
        if (method_exists($product, 'getParentId') && $product->getParentId()) {
            $tags[] = 'product-' . $product->getParentId();
        }

        return $tags;
    }

    /**
     * Covers both 'alist' and 'manufacturerlist': ManufacturerListController
     * extends ArticleListController and overrides getActiveCategory() to
     * return the active manufacturer instead of a real category, so the
     * same call works for both - $prefix is what tells the two apart in the
     * resulting tag. Deliberately does NOT tag individual listed articles:
     * a product can also surface outside its own category/manufacturer
     * listing (home page widgets, cross-sells, ...), so there's no page-
     * level tag set that could ever be complete. The category/manufacturer
     * tag is the invalidation unit instead - when a product changes, every
     * cached page of the categories/manufacturers it belongs to gets
     * invalidated wholesale rather than trying to track exactly which list
     * pages happened to render it.
     */
    protected function getListTags(BaseController $view, string $prefix): array
    {
        if (!method_exists($view, 'getActiveCategory')) {
            return [];
        }

        $category = $view->getActiveCategory();

        if (!is_object($category) || !method_exists($category, 'getId')) {
            return [];
        }

        return [$prefix . '-' . $category->getId()];
    }

    protected function getContentTags(BaseController $view): array
    {
        if (!method_exists($view, 'getContentId')) {
            return [];
        }

        $contentId = $view->getContentId();

        return $contentId ? ['content-' . $contentId] : [];
    }

    public function recordHit(string $viewClass, float $totalMs): void
    {
        if ($this->isSaveStatsEnabled()) {
            $this->getStatsStore()->recordHit($viewClass, $totalMs);
        }
    }

    public function recordMiss(string $viewClass, float $totalMs): void
    {
        if ($this->isSaveStatsEnabled()) {
            $this->getStatsStore()->recordMiss($viewClass, $totalMs);
        }
    }

    /**
     * @return int number of cache files removed
     */
    public function clearAll(): int
    {
        $removed = $this->getStorage()->clear();
        $this->resetStats();

        return $removed;
    }

    /**
     * @return array<int, array{viewclass: string, requests: int, hits: int, misses: int, ratio: float, avgHitMs: ?float, avgMissMs: ?float}>
     */
    public function getStats(): array
    {
        return $this->getStatsStore()->getStatsByViewClass();
    }

    public function resetStats(): void
    {
        $this->getStatsStore()->clearAll();
    }

    /**
     * Real filesystem scan of the cache directory - not backed by the DB,
     * so it must stay opt-in (see SettingsController) rather than run on
     * every admin page load.
     *
     * @return array{count: int, sizeBytes: int}
     */
    public function getFileStats(): array
    {
        return $this->getStorage()->getStats();
    }

    protected function stripLiveTokens(string $html): string
    {
        $session = $this->getSession();

        // Cast, don't trust: the session getters are not contractually strings -
        // on the OXID 7 line getId() returns null, and str_replace() would raise a
        // TypeError under strict_types. Harmless here, identical to the 2.x branch.
        $stoken = (string) $session->getSessionChallengeToken();
        if ($stoken !== '') {
            $html = str_replace($stoken, self::PLACEHOLDER_STOKEN, $html);
        }

        $sid = (string) $session->getId();
        if ($sid !== '') {
            $html = str_replace($sid, self::PLACEHOLDER_SID, $html);
        }

        return $html;
    }

    protected function injectLiveTokens(string $html): string
    {
        $session = $this->getSession();

        $html = str_replace(self::PLACEHOLDER_STOKEN, (string) $session->getSessionChallengeToken(), $html);
        $html = str_replace(self::PLACEHOLDER_SID, (string) $session->getId(), $html);

        return $html;
    }
}
