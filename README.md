# EasyCache

[![CI b-7.x](https://img.shields.io/github/actions/workflow/status/foun10/easy-cache-oxid/ci.yml?branch=b-7.x&label=CI%20b-7.x)](https://github.com/foun10/easy-cache-oxid/actions/workflows/ci.yml?query=branch%3Ab-7.x)
[![CI b-6.x](https://img.shields.io/github/actions/workflow/status/foun10/easy-cache-oxid/ci.yml?branch=b-6.x&label=CI%20b-6.x)](https://github.com/foun10/easy-cache-oxid/actions/workflows/ci.yml?query=branch%3Ab-6.x)
[![Latest Release](https://img.shields.io/github/v/release/foun10/easy-cache-oxid?sort=semver)](https://github.com/foun10/easy-cache-oxid/releases)
[![PHP](https://img.shields.io/badge/PHP-%5E8.0-777BB4?logo=php&logoColor=white)](#compatibility)
[![OXID eShop](https://img.shields.io/badge/OXID%20eShop-7.0%20%E2%80%93%207.5-e30613)](#compatibility)
[![License](https://img.shields.io/badge/license-GPL--3.0--only-blue)](LICENSE)

> A file-based full-page cache for OXID eShop. Selected storefront pages are stored as finished
> HTML and served on later requests without running the shop's business logic again.

---

## Compatibility

| Module version | Branch | OXID eShop | Template engine |
|---|---|---|---|
| 7.x | [`b-7.x`](https://github.com/foun10/easy-cache-oxid/tree/b-7.x) | 7.0 – 7.5 | Twig |
| 6.x | [`b-6.x`](https://github.com/foun10/easy-cache-oxid/tree/b-6.x) | 6.2 – 6.5 | Smarty |

Composer resolves the right line for your shop automatically.

### Tested combinations

Every row below is installed from scratch and exercised by the full test suite on every push.
This is not a statement of intent — if a combination is listed here, CI proves it.

<!-- ci-matrix:start -->

| OXID eShop | PHP |
|---|---|
| 7.0 | 8.0, 8.1 |
| 7.1 | 8.1, 8.2 |
| 7.2 | 8.2, 8.3 |
| 7.3 | 8.2, 8.3, 8.4 |
| 7.4 | 8.2, 8.3, 8.4 |
| 7.5 | 8.3, 8.4, 8.5 |

<!-- ci-matrix:end -->

## Features

- **Finished pages, served from disk.** A cached page skips the controller action and the
  template render entirely. On our test shop that is roughly 1100 ms down to 80 ms for the
  start page.
- **Tag-based invalidation.** Cached pages are filed under the products, categories,
  manufacturers and content pages they actually show, so a single article going out of stock
  clears exactly the pages that displayed it instead of the whole cache.
- **Automatic purge when an article sells out.** Hooked into OXID's own `Article::onChange()`,
  which also fires during checkout when stock is reduced.
- **Optional gzip and HTML minification**, both togglable without clearing the cache.
- **Per-controller statistics** — hits, misses, hit ratio and average response time for each
  view class, with a backend page to read them.
- **`X-EasyCache` response headers.** Every response carries `HIT`, `MISS` or `BYPASS`, plus a
  timing breakdown. Low cardinality on purpose, so it can be aggregated at the CDN or in log
  analysis without parsing anything apart.

## What gets cached — and what never does

This is the contract of the module, so it is worth stating plainly. A page is cached only when
**all** of the following hold:

- its controller is on the whitelist (`start`, `alist`, `details`, `content` by default);
- the request is a plain `GET` with no `fnc=` parameter — anything that changes shop state
  always runs for real;
- the visitor is **not logged in** and the basket is **empty**;
- the URL carries no `sid`, `stoken`, `force_sid` or `force_admin_sid`;
- it is not a `renderPartial` widget request.

Checkout, account, basket and every other controller are never cached, simply by not being on
the whitelist.

### Session ids and CSRF tokens are never stored

A rendered page contains the session id and the session challenge token (`stoken`) of whoever
triggered the render. Writing that to disk and handing it to the next visitor would be a bug with
teeth, so it does not happen: both values are replaced with fixed placeholders **before** the page
is stored, and on every cache hit the placeholders are replaced again — with the values read from
the *current* visitor's own session.

The file on disk therefore holds neither value, and the HTML a visitor receives carries their own
token rather than a stranger's. That is also what keeps forms on a cached page working: a cached
start page still ships a valid `stoken` for the person looking at it, so its search and
add-to-basket forms pass the shop's own CSRF check instead of failing on a stale token.

Two details worth knowing:

- The swap is a plain string replacement over the whole rendered page, so it covers the values
  wherever they turn up — links, form fields and inline JavaScript alike.
- A visitor without a session has nothing to strip on write, and the placeholders resolve to empty
  strings on read. That is the correct result, not a fallback: they have no session id to carry.

This mechanism restores identity, not personalisation — which is why caching stays limited to
guests with an empty basket. A placeholder swap can hand back the right token; it cannot rebuild a
basket widget for the wrong person.

## Not a reverse proxy

EasyCache sits **inside** the shop, not in front of it. A cache hit is still a full PHP request:
the web server hands it to PHP-FPM, OXID boots, the configuration is read, the session is
resolved, the module chain is assembled, and the view object's own `init()` runs — and only then
does the module return the stored HTML instead of executing the requested action. What a hit
skips is the controller action and the template render. That is the expensive part, but it is
not the whole request.

The `X-EasyCache-Timing` header is deliberate about this: `initMs` is reported on a hit as well,
and it is never zero.

```
X-EasyCache: HIT
X-EasyCache-Timing: initMs=18.7; totalMs=61.2
```

`totalMs` is measured from the moment PHP stamped the request, so it covers everything. Of those
61 ms, 19 ms went into initialising the view object; the rest is PHP starting up, the shop
booting and the module chain being assembled. None of that is skipped on a hit. It is still a
large win over the full render — and it is a different category of thing from a Varnish or nginx
cache, which answers the same request in single-digit milliseconds because PHP never starts at
all.

What follows from that:

- **Cached requests still occupy a PHP-FPM worker** for their whole duration, so cached and
  uncached traffic compete for the same pool. If your bottleneck is worker exhaustion under a
  traffic spike, a cache that lives inside PHP cannot fix it.
- **The database is still queried on every hit** — the shop configuration and whatever the view's
  `init()` loads. Far fewer queries than a full render, but not none.
- **Other modules still run.** Anything extending the early request path executes on a hit
  exactly as it would without EasyCache, including its own database access.
- **In exchange, the shop stays in charge.** Session handling, CSRF tokens and per-visitor
  eligibility are decided by the shop itself on every single request, which is why a logged-in
  visitor can never be served a guest's cached page. A proxy in front of the shop has to be
  taught all of that from the outside, and getting it wrong there leaks one customer's page to
  the next.

The two are not really alternatives, but different layers: if you already run a reverse proxy,
this module adds little; if you do not, it gets you most of the render savings without one.

## Installation

```bash
composer require foun10/easycache
```

Activate the module:

```bash
vendor/bin/oe-console oe:module:activate foun10EasyCache
```

Activation creates the `foun10easycachestats` table. Caching itself stays **off** until you
enable it in the backend.

The cache is written to `source/foun10cache`. The module drops a deny-all `.htaccess` there on
first write, because that directory sits inside the document root — on a server that ignores
`.htaccess` (nginx, for instance) block the path in your server config instead.

### Optional: HTML minification

Minification needs one extra package, deliberately not a hard dependency — it pulls in a DOM
parser that a cache module has no business forcing on shops that will not use it:

```bash
composer require voku/html-min
```

Without it the setting stays inert and the backend says so.

## Configuration

Backend: **foun10 Modules → EasyCache → Settings**.

| Setting | Default | Description |
|---|---|---|
| `foun10EasyCacheEnabled` | `false` | Master switch. Nothing is cached while this is off. |
| `foun10EasyCacheTTL` | `3600` | Lifetime of a cache entry in seconds. Invalid or zero values fall back to 3600. |
| `foun10EasyCacheWhitelist` | `start, alist, details, content` | Controllers that may be cached. Anything not listed never is. |
| `foun10EasyCacheSaveStats` | `false` | Records a hit/miss per controller on every request. Adds a database write per request. |
| `foun10EasyCacheGzip` | `false` | Compresses cache files on disk, typically by 75–85 %. |
| `foun10EasyCacheMinify` | `false` | Strips redundant whitespace from cached HTML. Requires `voku/html-min`. |

Toggling gzip or minification needs no cache clear: entries under the old setting simply stop
being used and age out.

## Cache maintenance

**Clear the cache once a day.** Tag invalidation and the TTL together cover the common cases,
but neither collects everything: pages whose controller has no tag mapping fall back to TTL
expiry only, tag marker files accumulate next to the entries themselves, and a long TTL on a
large catalogue means the directory tree only ever grows. A nightly clear keeps both the file
count and the amount of stale HTML bounded:

```cron
# Clear the EasyCache full-page cache every night at 03:30
30 3 * * * cd /var/www/shop && vendor/bin/oe-console foun10:easycache:clear >/dev/null
```

The same command is what the backend's **Clear cache** button runs.

## Known limitations

- **Only guests are cached.** As soon as a visitor logs in or puts something in the basket,
  every page renders live. On a shop where most traffic is logged in, the hit ratio — and the
  benefit — will be small.
- **The first request after a clear is the slowest.** Nothing is cached, so that visitor pays
  the full render. A nightly clear (above) puts that cost at your quietest hour.
- **The start page is never invalidated automatically.** It belongs to no single product or
  category, so it carries one fixed tag and is only cleared by TTL, by hand, or by the nightly
  job.
- **Cached HTML is exactly what was rendered.** Anything that must differ per visitor —
  a country-dependent price note, a "recently viewed" list — has to be loaded client-side, or
  the page must be kept off the whitelist.

## Extending the module

**Filter and facet modules.** Each whitelisted controller folds the request parameters that
change its output into `getViewId()`, and EasyCache builds its key from that. A third-party
module that varies a page by a parameter the controller knows nothing about — an attribute
filter on a listing page, say — would therefore collide with the unfiltered page's cache entry
and serve the wrong article set. Extend EasyCache through OXID's module chain and handle it in
one of two ways.

Declare the extension in your own module's `metadata.php`:

```php
$aModule = [
    // ...
    'extend' => [
        \foun10\EasyCache\Core\EasyCache::class => \MyVendor\MyModule\MyEasyCache::class,
    ],
];
```

Your class then extends the `_parent` alias the shop generates for it, exactly as an extension of
a shop class would:

```php
namespace MyVendor\MyModule;

use OxidEsales\Eshop\Core\Controller\BaseController;

class MyEasyCache extends MyEasyCache_parent
{
    // Either exclude such requests from caching entirely...
    public function isRequestCacheable(BaseController $view, string $functionName): bool
    {
        if ($this->getRequestParameter('myfilter') !== '') {
            return false;
        }

        return parent::isRequestCacheable($view, $functionName);
    }

    // ...or keep caching them, under a key that tells them apart:
    public function buildKey(BaseController $view): string
    {
        return md5(parent::buildKey($view) . '|' . $this->getRequestParameter('myfilter'));
    }
}
```

Two things are easy to get wrong here. The `_parent` alias is named after **your** class, not
after the one you are extending. And extending `\foun10\EasyCache\Core\EasyCache` directly with
plain PHP inheritance leaves your subclass as dead code: EasyCache is only ever obtained through
`Registry::get()`, which resolves through `oxNew()` and therefore through the module chain, so
the shop keeps handing out the unextended class and nothing reports an error.

**Hooking your own "clear cache" trigger.** If your shop or another module has its own cache
button, point it at the module's public entry point:

```php
\OxidEsales\Eshop\Core\Registry::get(\foun10\EasyCache\Core\EasyCache::class)->clearAll();
```

Both extension points are `public`/`protected` on purpose and covered by the test suite.

## Development & Testing

```bash
# Unit tests (no shop required)
composer tests-unit

# Integration tests (require an installed, activated OXID eShop)
composer tests-integration

# Mutation testing (needs the Infection PHAR on PATH, PHP 8.1+)
composer tests-mutation
```

The integration suite bootstraps a real shop. Point it at yours:

```bash
OXID_SHOP_BOOTSTRAP=/path/to/shop/source/bootstrap.php composer tests-integration
```

There is deliberately no `composer.lock` in this repository: the dependency tree is resolved
per PHP version, and a lock file would tie the whole supported range to one of them. Use
`composer update`, not `composer install`.

## Honest opinion

We wrote this module for our own customer projects, so here is where we would reach for it and
where we would not.

**✅ Use it when**

- You want a straightforward HTML cache that keeps response times low, without introducing a
  reverse proxy or a separate caching layer in front of the shop.
- You are fine with only anonymous visitors being cached. Guests with an empty basket are
  usually the bulk of catalogue traffic, and that is exactly the traffic this module serves
  from disk.

**❌ Do not use it when**

- You run an Enterprise Edition. Its built-in dynamic content caching is more capable than a
  file-based full-page cache and already integrated with the shop.
- Your web server has a file count or inode limit you are anywhere near. Every cached page is
  a file, and each one adds marker files for its tags on top — a large catalogue with a long
  TTL produces a lot of small files.
- You need to take load off PHP itself. A hit still boots the shop (see
  [Not a reverse proxy](#not-a-reverse-proxy)) — if you are trying to survive traffic spikes or
  free up PHP-FPM workers, put a real reverse proxy in front of the shop. This module is not a
  substitute for one.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

[GPL-3.0-only](LICENSE). You may use this module commercially, including in customer projects.
If you redistribute it, or a derivative of it, that has to happen under the GPL as well.

## Like this module?

If this module saves you time, a ⭐ on this repository genuinely makes our day — and helps other
OXID developers find it.

Found a bug or missing a feature? Open an
[issue](https://github.com/foun10/easy-cache-oxid/issues) — we read them.

And if you need a hand with this module or are wrestling with other OXID eShop challenges, feel
free to reach out at [foun10.de](https://www.foun10.de).
