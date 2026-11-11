<?php
// *************************************************************************
// * Shared head/foot for the public communication pages (survey.php,     *
// * delivery_schedule.php, comms_preferences.php). Swift Lane tokens from *
// * assets/swiftlane/swiftlane.css; no page colours of their own.         *
// *************************************************************************

if (!function_exists('cdp_commPublicHead')) {
    function cdp_commPublicHead($title, $siteName)
    {
        require_once dirname(__DIR__, 2) . '/helpers/asset.php';
        $e = 'cdp_opsEsc';
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="robots" content="noindex"><title>' . $e($title) . ' | ' . $e($siteName) . '</title>'
            . '<link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">'
            . '<link rel="stylesheet" href="' . $e(cdp_asset('assets/swiftlane/swiftlane.css')) . '"><style>'
            . 'body{min-height:100vh;display:flex;align-items:center;justify-content:center}'
            . '.box{background:var(--card);border:1px solid var(--line);border-radius:var(--r-xl);padding:28px;max-width:520px;width:100%;margin:16px}'
            . 'h1{font-size:24px;font-weight:600;margin:0 0 10px}p,li{line-height:1.5;color:var(--ink-2);margin:0 0 10px}'
            . 'label{display:block;font-weight:500;margin:14px 0 6px;font-size:15px}'
            . 'select,textarea,input[type=tel]{width:100%;border:1px solid var(--line-2);border-radius:var(--r-sm);background:var(--field);padding:10px 12px;font:400 16px var(--font);color:var(--ink)}'
            . '.chk{display:flex;align-items:center;gap:10px;margin:8px 0;font-weight:400}.chk input{width:20px;height:20px;accent-color:var(--ink)}'
            . 'button{margin-top:18px;height:44px;padding:0 22px;border-radius:var(--r-sm);border:1px solid var(--ink);background:var(--ink);color:var(--inverse);font:500 16px var(--font);cursor:pointer}'
            . '.stars{display:flex;gap:8px;flex-wrap:wrap}.stars label{margin:0}.stars input{position:absolute;opacity:0}'
            . '.stars span{display:inline-block;min-width:48px;text-align:center;padding:12px;border-radius:var(--r-sm);border:1px solid var(--line-2);background:var(--field);cursor:pointer}'
            . '.stars input:checked+span{background:var(--ink);color:var(--inverse);border-color:var(--ink)}.stars input:focus-visible+span{outline:2px solid var(--violet);outline-offset:2px}'
            . '.muted{color:var(--muted);font-size:14px}.err{color:var(--red-ink)}'
            . 'fieldset{border:0;padding:0;margin:14px 0 0}legend{font-weight:600;padding:0;margin-bottom:6px}'
            . '</style></head><body><main class="box">';
    }

    function cdp_commPublicFoot()
    {
        echo '</main></body></html>';
    }
}
