"use strict";

// Light / dark switch for the pages that call cdp_themeBoot() (helpers/theme.php).
// Fires $(document).trigger('swl:theme', [theme]) so charts can redraw.
(function ($) {
    function cur() { return document.documentElement.getAttribute('data-swl-theme') === 'dark' ? 'dark' : 'light'; }
    function paint() {
        var dark = cur() === 'dark';
        $('[data-swl-theme-toggle]').attr('aria-pressed', dark ? 'true' : 'false')
            .attr('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode')
            .find('iconify-icon').attr('icon', dark ? 'solar:sun-2-linear' : 'solar:moon-linear').end()
            .find('span').text(dark ? 'Light' : 'Dark');
    }
    window.SWL_THEME = { get: cur };
    $(function () {
        paint();
        $(document).on('click', '[data-swl-theme-toggle]', function () {
            var t = cur() === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-swl-theme', t);
            try { localStorage.setItem('swl-theme', t); } catch (e) { /* private mode: the choice lasts this page only */ }
            paint();
            $(document).trigger('swl:theme', [t]);
        });
    });
})(window.jQuery);
