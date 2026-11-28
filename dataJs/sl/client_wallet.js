/* Customer dashboard: warehouse addresses as a wallet card stack (views/sl/pages/partials/client_overview.php).
   The chosen country's card sits in front, the next two peek out behind it. A round flag, a click on a
   peeking card, the arrow keys or a swipe brings a card to the front. Without this script the first card shows. */
(function () {
  'use strict';
  function init(w) {
    var cards = Array.prototype.slice.call(w.querySelectorAll('[data-co-card]'));
    var flags = Array.prototype.slice.call(w.querySelectorAll('[data-co-pick]'));
    var n = cards.length;
    if (n < 2) return;
    var on = 0;
    cards.forEach(function (c, i) { if (c.getAttribute('data-pos') === '0') on = i; });

    function pick(i, focusFlag) {
      on = (i + n) % n;
      cards.forEach(function (c, k) {
        var pos = (k - on + n) % n;
        c.style.setProperty('--pos', pos);
        c.setAttribute('data-pos', Math.min(pos, 3));
        if (pos) c.setAttribute('aria-hidden', 'true');
        else c.removeAttribute('aria-hidden');
      });
      flags.forEach(function (f, k) {
        f.setAttribute('aria-selected', k === on ? 'true' : 'false');
        f.tabIndex = k === on ? 0 : -1;
        if (k === on && focusFlag) f.focus();
      });
    }

    flags.forEach(function (f, k) {
      f.addEventListener('click', function () { pick(k); });
      f.addEventListener('keydown', function (e) {
        var step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[e.key];
        if (e.key === 'Home') { e.preventDefault(); pick(0, true); }
        else if (e.key === 'End') { e.preventDefault(); pick(n - 1, true); }
        else if (step) { e.preventDefault(); pick(on + step, true); }
      });
    });

    // A card peeking out behind the front one comes forward when clicked.
    var stack = w.querySelector('.co-stack');
    stack.addEventListener('click', function (e) {
      var c = e.target.closest('[data-co-card]');
      if (c && c.getAttribute('data-pos') !== '0') { e.preventDefault(); pick(cards.indexOf(c)); }
    });

    // Swipe left / right on touch screens.
    var x0 = null, y0 = 0;
    stack.addEventListener('pointerdown', function (e) { if (e.pointerType !== 'mouse') { x0 = e.clientX; y0 = e.clientY; } });
    stack.addEventListener('pointerup', function (e) {
      if (x0 === null) return;
      var dx = e.clientX - x0, dy = e.clientY - y0;
      x0 = null;
      if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy) * 1.5) pick(on + (dx < 0 ? 1 : -1));
    });
    stack.addEventListener('pointercancel', function () { x0 = null; });
  }

  function ready() {
    document.querySelectorAll('[data-co-wallet]').forEach(init);
    // Share the referral link with the phone's share sheet; elsewhere the button copies it (data-sl-copy).
    document.querySelectorAll('[data-co-share]').forEach(function (b) {
      if (!navigator.share) return;
      b.addEventListener('click', function (e) {
        e.stopPropagation();
        navigator.share({ text: b.getAttribute('data-co-share') }).catch(function () {});
      });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready); else ready();
})();
