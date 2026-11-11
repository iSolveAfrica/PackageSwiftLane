<?php
// *************************************************************************
// * Shared head/foot for the public communication pages (survey, delivery *
// * slot booking, notification preferences). Tokens only - no page        *
// * colours (docs/UI_PAGE_PATTERN.md).                                     *
// *************************************************************************

if (!function_exists('cdp_commPublicHead')) {
    function cdp_commPublicHead($title, $siteName)
    {
        $e = 'cdp_opsEsc';
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="robots" content="noindex"><title>' . $e($title) . ' | ' . $e($siteName) . '</title>'
            . '<link href="assets/css_main_swiftlane/css/swiftlane-tokens.css" rel="stylesheet"><style>'
            . 'body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--surface-page);color:var(--text-primary);font-family:var(--font-ui)}'
            . '.box{background:var(--surface-card);border-radius:var(--radius-24);box-shadow:var(--ring-default);padding:var(--space-32);max-width:520px;width:100%;margin:var(--space-16);box-sizing:border-box}'
            . 'h1{font-family:var(--font-display);font-size:var(--display-md-size);letter-spacing:var(--display-track);margin:0 0 var(--space-12)}'
            . 'p,li{line-height:1.5;color:var(--ink-700)} label{display:block;font-weight:var(--weight-semibold);margin:var(--space-12) 0 6px}'
            . 'select,textarea,input[type=text],input[type=tel]{width:100%;box-sizing:border-box;border-radius:var(--radius-12);border:0;box-shadow:var(--ring-control);padding:12px;font:inherit;background:var(--surface-card);color:var(--text-primary)}'
            . '.row{display:flex;gap:8px;flex-wrap:wrap}.chk{display:flex;align-items:center;gap:10px;margin:8px 0;font-weight:400}.chk input{width:20px;height:20px}'
            . 'button{border:0;border-radius:var(--radius-pill);background:var(--accent);color:var(--accent-ink);font-weight:var(--weight-semibold);padding:14px 28px;font-size:15px;cursor:pointer;margin-top:var(--space-16)}'
            . '.stars{display:flex;gap:8px;flex-wrap:wrap}.stars label{margin:0}.stars input{position:absolute;opacity:0}.stars span{display:inline-block;min-width:48px;text-align:center;padding:12px;border-radius:var(--radius-12);box-shadow:var(--ring-control);cursor:pointer}'
            . '.stars input:checked+span{background:var(--accent);color:var(--accent-ink)}.stars input:focus-visible+span{outline:2px solid var(--ink-900)}'
            . '.muted{color:var(--text-muted);font-size:13px}.err{color:var(--status-danger-ink, var(--red-500))}'
            . 'fieldset{border:0;padding:0;margin:var(--space-12) 0 0}legend{font-weight:var(--weight-semibold);padding:0;margin-bottom:6px}'
            . '</style></head><body><main class="box">';
    }

    function cdp_commPublicFoot()
    {
        echo '</main></body></html>';
    }
}
