# Changelog

All notable changes to this module are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the module uses
[semantic versioning](https://semver.org/spec/v2.0.0.html).

Each OXID line has its own release series: `7.x` on the `b-7.x` branch for OXID 7, `6.x` on the
`b-6.x` branch for OXID 6 - the major version tracks the OXID line it targets, not a generation
of the module. The two are developed in parallel, so a fix usually appears in both.

## [6.0.0] - 2026-08-31

First public release for OXID 6. The module existed internally before this; the public history
and the version numbering start here.

### Added

- A file-based full-page cache: whitelisted storefront pages are stored as finished HTML and
  served on later requests without running the controller action or the template render again.
- Tag-based invalidation. Cached pages are filed under the products, categories, manufacturers
  and content pages they actually show, so a single article change clears exactly the pages that
  displayed it instead of the whole cache.
- Automatic purge when an article sells out, hooked into OXID's own `Article::onChange()` - which
  also fires during checkout, when stock is reduced.
- Optional gzip storage and optional whitespace-only HTML minification, both togglable without
  clearing the cache. Minification uses `voku/html-min`, kept as a suggested rather than a
  required dependency; without it the setting stays inert and the backend says so.
- Per-controller statistics - hits, misses, hit ratio and average response time per view class -
  with a backend page to read and reset them. Off by default, because it costs a write per
  request.
- `X-EasyCache` and `X-EasyCache-Timing` response headers on every request, carrying `HIT`,
  `MISS` or `BYPASS` plus a timing breakdown. Deliberately low cardinality, so they can be
  aggregated at a CDN or in log analysis without parsing anything apart.
- A backend settings page for the master switch, the TTL, the controller whitelist and the gzip,
  minify and statistics toggles.
- A backend cache page to clear everything, clear the start page, or clear the pages belonging to
  one product, category or manufacturer found by name.
- `foun10:easycache:clear`, the same clear the backend button runs, for use from cron.
- Session ids and CSRF tokens are replaced with placeholders before a page is stored and
  re-injected from the current visitor's own session when it is served, so no visitor is ever
  handed another visitor's token.
- A deny-all `.htaccess` written into the cache directory on first use, because that directory
  sits inside the document root.

### Fixed

- `foun10:easycache:clear` returned `void` from `execute()` where Symfony Console expects an
  `int`. The console component shipped with OXID 6.2 coerces that silently, but the newer ones on
  the upper half of this line raise a `TypeError` - the cache would be cleared and the command
  would still exit non-zero, so a nightly cron mailed a fatal every night despite having done its
  job.
- Session values were passed to `str_replace()` unchecked. The guard against that is shared with
  the `7.x` line, where an unset session id is `null` rather than an empty string; keeping both
  lines identical here avoids a divergence that only shows up on one of them.

### Known limitations

- Only guests are cached. As soon as a visitor logs in or puts something in the basket, every
  page renders live. On a shop where most traffic is logged in, the benefit will be small.
- This is not a reverse proxy. A cache hit is still a full PHP request - the shop boots, the
  session is resolved and the view object initialises before the stored HTML is returned. It
  skips the render, not the framework.
- The start page belongs to no single product or category, so it carries one fixed tag and is
  cleared only by TTL, by hand, or by the nightly job.
- Cached HTML is exactly what was rendered. Anything that must differ per visitor has to be
  loaded client-side, or its page kept off the whitelist.
