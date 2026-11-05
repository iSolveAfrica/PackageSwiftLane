<?php
// *************************************************************************
// * Light and dark theme for the pages that offer both (Customer Service, *
// * Developer). cdp_themeBoot() goes in <head> so the page never flashes  *
// * the wrong theme; cdp_themeToggle() is the header button. The choice   *
// * is kept per browser (localStorage "swl-theme"). Styles: section 26 of *
// * swiftlane-ds.css; behaviour: dataJs/swl_theme.js.                     *
// *************************************************************************

if (!function_exists('cdp_themeBoot')) {

    /** @param string $default 'light' or 'dark' when the viewer has not chosen yet. */
    function cdp_themeBoot($default = 'light')
    {
        $d = $default === 'dark' ? 'dark' : 'light';
        echo "<script>(function(){var t='" . $d . "';try{var s=localStorage.getItem('swl-theme');if(s==='dark'||s==='light'){t=s;}}catch(e){}"
            . "document.documentElement.setAttribute('data-swl-theme',t);window.CDP_THEME_DEFAULT='" . $d . "';})();</script>\n";
    }

    function cdp_themeToggle()
    {
        return '<button type="button" class="btn btn-secondary btn-sm swl-theme-toggle" data-swl-theme-toggle aria-pressed="false">'
            . '<iconify-icon icon="solar:moon-linear" aria-hidden="true"></iconify-icon><span>Dark</span></button>';
    }
}
