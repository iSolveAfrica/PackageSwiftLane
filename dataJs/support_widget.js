"use strict";

// ============================================================================
// The customer chat button. Nothing loads until the first click; after that
// the chat stays in the page. An unread badge is checked once a minute while
// the tab is visible, using the bootstrap call the chat needs anyway.
// ============================================================================

(function ($) {
    var CFG = window.CDP_CS_WIDGET || {};
    var CS = window.CS;
    var chat = null, $pop, $btn;

    $(function () {
        $btn = $('#cs_launch');
        $pop = $('#cs_pop');
        if (!$btn.length) { return; }
        $btn.on('click', toggle);
        $(document).on('keydown', function (e) { if (e.key === 'Escape' && $pop.hasClass('is-open')) { toggle(); } });
        badge();
        setInterval(function () { if (!document.hidden && !$pop.hasClass('is-open')) { badge(); } }, 60000);
    });

    function badge() {
        CS.get(CFG.url, { action: 'bootstrap' }).done(function (r) {
            if (!r || !r.ok) { return; }
            var n = (r.conversations || []).reduce(function (a, c) { return a + (c.unread || 0); }, 0)
                + (r.tickets || []).filter(function (t) { return t.waiting_on_you; }).length;
            $('#cs_launch_badge').text(n > 9 ? '9+' : n).toggleClass('is-on', n > 0);
            $btn.data('boot', r);
        });
    }

    function toggle() {
        var open = !$pop.hasClass('is-open');
        $pop.toggleClass('is-open', open);
        $btn.attr('aria-expanded', open ? 'true' : 'false');
        if (!open) { $btn.trigger('focus'); return; }
        $('#cs_launch_badge').removeClass('is-on');
        if (chat) { chat.focus(); return; }

        var start = function (r) {
            chat = new CS.Chat($pop, {
                url: CFG.url, king: r.king, hours: r.hours, closeButton: true, newButton: true,
                onClose: toggle, onChange: function () {}
            });
            var live = (r.conversations || []).filter(function (c) { return c.owner !== 'resolved'; })[0];
            if (live) { chat.open(live.id); } else { chat.startNew(); }
            $pop.append('<div class="swl-cs-small swl-cs-muted text-center p-2"><a href="' + CFG.page + '">Open Help &amp; Support</a> for your requests and help articles</div>');
            chat.focus();
        };
        var boot = $btn.data('boot');
        if (boot) { start(boot); return; }
        CS.get(CFG.url, { action: 'bootstrap' }).done(function (r) {
            if (!r || !r.ok) { $pop.html('<div class="card"><div class="card-body">' + CS.esc(r && r.error ? r.error : 'Support is not available right now.') + '</div></div>'); return; }
            start(r);
        }).fail(CS.fail);
    }
})(window.jQuery);
