<?php

/**
 * Cache-busting asset URLs.
 *
 * Returns a LOCAL asset path with a `?v=<filemtime>` stamp appended, so the
 * browser re-fetches the file the moment it changes on disk (i.e. on the next
 * deploy that touches it) instead of serving a stale cached copy until the user
 * manually clears their browser cache.
 *
 * Only use for first-party files under the app root (assets/…, dataJs/…). Leave
 * CDN URLs untouched — those are already versioned by the CDN.
 *
 *   <link href="<?= cdp_asset('assets/template/dist/css/style.min.css') ?>" rel="stylesheet">
 */
if (!function_exists('cdp_asset')) {
    function cdp_asset(string $path): string
    {
        static $cache = [];
        if (preg_match('#^(?:[a-z][a-z0-9+.-]*:|//)#i', $path)) return $path;

        // Strip any existing query string before stat-ing the file on disk.
        $rel = ltrim(strtok($path, '?'), '/');
        if (defined('SL_PWA_ROOT_URL')) {
            $prefix = trim(SL_PWA_ROOT_URL, '/');
            if ($prefix !== '' && str_starts_with($rel, $prefix . '/')) $rel = substr($rel, strlen($prefix) + 1);
            $path = SL_PWA_ROOT_URL . $rel . (str_contains($path, '?') ? '?' . explode('?', $path, 2)[1] : '');
        }

        if (!array_key_exists($rel, $cache)) {
            // __DIR__ keeps resolution stable no matter which page includes us.
            $mtime = @filemtime(__DIR__ . '/../' . $rel);
            $cache[$rel] = ($mtime !== false) ? (string) $mtime : null;
        }

        // File can't be stat-ed (missing/typo) → return the URL untouched.
        if ($cache[$rel] === null) {
            return $path;
        }

        $sep = (strpos($path, '?') === false) ? '?' : '&';
        return $path . $sep . 'v=' . $cache[$rel];
    }
}
