<?php
declare(strict_types=1);

namespace foun10\EasyCache\Extension\Core;

use foun10\EasyCache\Core\EasyCache;
use OxidEsales\Eshop\Core\Registry;

class ShopControl extends ShopControl_parent
{
    protected $easyCacheKey;
    protected $easyCacheHit;
    protected $easyCacheEligible = false;
    protected $easyCacheInitMs;
    protected $easyCacheRenderMs;
    protected $easyCacheOutcome;

    /**
     * Times the DB/business-logic work init() always runs (hit or miss),
     * separately from render() below - reported via X-EasyCache-Timing.
     */
    protected function _initializeViewObject($class, $function, $parameters = null, $viewsChain = null)
    {
        $start = microtime(true);
        $view = parent::_initializeViewObject($class, $function, $parameters, $viewsChain);
        $this->easyCacheInitMs = (microtime(true) - $start) * 1000;

        return $view;
    }

    /**
     * Runs after the view has been initialized (init() already executed,
     * so FrontendController::getViewId() is reliable) but before the
     * requested action actually runs. On a cache hit this skips the
     * action entirely and formOutput() below short-circuits the render.
     */
    protected function executeAction($view, $functionName)
    {
        $easyCache = $this->getEasyCache();

        // $functionName is null (not '') when the request has no fnc= param
        // at all, e.g. a plain homepage load - normalize before the strict
        // string type on isRequestCacheable().
        if (!isAdmin() && $easyCache->isRequestCacheable($view, (string) $functionName)) {
            $key = $easyCache->buildKey($view);
            $cached = $easyCache->read($key);

            if ($cached !== null) {
                $this->easyCacheHit = $cached;
                $this->easyCacheOutcome = 'HIT';

                return;
            }

            $this->easyCacheKey = $key;
            $this->easyCacheEligible = true;
        } else {
            $this->easyCacheOutcome = 'BYPASS';
        }

        parent::executeAction($view, $functionName);
    }

    protected function formOutput($view)
    {
        if ($this->easyCacheHit !== null) {
            return $this->easyCacheHit;
        }

        $renderStart = microtime(true);
        $output = parent::formOutput($view);
        $this->easyCacheRenderMs = (microtime(true) - $renderStart) * 1000;

        if ($this->easyCacheEligible && $this->easyCacheKey) {
            $easyCache = $this->getEasyCache();
            $easyCache->write($this->easyCacheKey, $output, $easyCache->getTags($view));
            $this->easyCacheOutcome = 'MISS';
        }

        return $output;
    }

    protected function getEasyCache(): EasyCache
    {
        return Registry::get(EasyCache::class);
    }

    /**
     * X-EasyCache is always HIT, MISS, or BYPASS (never absent) - low
     * cardinality on purpose, so it can be counted/aggregated (e.g. via
     * Cloudflare Logpush or a Worker) without parsing anything apart.
     * Timing goes in a separate header instead of being folded into this
     * one, and is only meaningful (only sent) for HIT/MISS.
     *
     * Output::sendHeaders() (called just before this in _process()) only
     * sets Content-Type - headers stay modifiable via header() up until the
     * body is actually echoed, so this is the latest point that still lets
     * these headers reflect (almost) the full server-side time, not just up
     * to the point executeAction()/formOutput() returned.
     */
    protected function sendAdditionalHeaders($view)
    {
        parent::sendAdditionalHeaders($view);

        header('X-EasyCache: ' . $this->easyCacheOutcome);

        if ($this->easyCacheOutcome === 'BYPASS') {
            return;
        }

        $totalMs = $this->easyCacheSinceRequestStartMs();

        if ($this->easyCacheOutcome === 'HIT') {
            header(sprintf('X-EasyCache-Timing: initMs=%.1f; totalMs=%.1f', $this->easyCacheInitMs, $totalMs));
        } else {
            header(sprintf(
                'X-EasyCache-Timing: initMs=%.1f; renderMs=%.1f; totalMs=%.1f',
                $this->easyCacheInitMs,
                $this->easyCacheRenderMs,
                $totalMs
            ));
        }

        $this->registerStatsWrite($this->getEasyCache(), $this->easyCacheOutcome, (string) $view->getClassKey(), $totalMs);
    }

    /**
     * Elapsed time since PHP stamped REQUEST_TIME_FLOAT, i.e. before
     * bootstrap.php has even run - the only vantage point that covers the
     * full request, including whatever happens before our own timers start.
     */
    protected function easyCacheSinceRequestStartMs(): float
    {
        $requestStart = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));

        return (microtime(true) - $requestStart) * 1000;
    }

    /**
     * Deferred (for both HIT and MISS) so the stats DB write never adds
     * latency to the response - fastcgi_finish_request() (where available)
     * flushes and closes the client connection first, so this write
     * genuinely runs after the browser already has the response, unlike a
     * plain register_shutdown_function callback alone would (OXID never
     * calls fastcgi_finish_request() itself, so without it here the client
     * would still wait through this write). This also keeps the response
     * path free of any row-lock contention on foun10easycachestats when
     * many concurrent requests hit the same view class.
     */
    protected function registerStatsWrite(EasyCache $easyCache, string $outcome, string $viewClass, float $totalMs): void
    {
        register_shutdown_function(static function () use ($easyCache, $outcome, $viewClass, $totalMs) {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            if ($outcome === 'HIT') {
                $easyCache->recordHit($viewClass, $totalMs);
            } else {
                $easyCache->recordMiss($viewClass, $totalMs);
            }
        });
    }
}
