/* Live tracking console (views/sl/pages/LiveTracking.php):
   card <-> map selection, zoom buttons and drag-to-pan on the SVG map. */
(function () {
  'use strict';
  function init(root) {
    var svg = root.querySelector('[data-lv-svg]');
    if (!svg) return;
    var base = (svg.getAttribute('viewBox') || '0 0 1000 620').split(/\s+/).map(Number);
    var vb = base.slice();
    var apply = function () { svg.setAttribute('viewBox', vb.join(' ')); };

    function select(key, scrollCard) {
      root.querySelectorAll('[data-lv-key]').forEach(function (c) {
        var on = c.getAttribute('data-lv-key') === key;
        c.classList.toggle('on', on);
        c.setAttribute('aria-selected', on ? 'true' : 'false');
        if (on && scrollCard && c.scrollIntoView) c.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      });
      root.querySelectorAll('[data-lv-geo]').forEach(function (g) {
        g.classList.toggle('on', g.getAttribute('data-lv-geo') === key);
      });
      root.querySelectorAll('[data-lv-pin]').forEach(function (p) {
        p.classList.toggle('on', p.getAttribute('data-lv-pin') === key);
      });
    }

    root.querySelectorAll('[data-lv-key]').forEach(function (card) {
      var act = function (e) {
        if (e.target.closest('a')) return; // let call / message / open links work
        select(card.getAttribute('data-lv-key'), false);
      };
      card.addEventListener('click', act);
      card.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); act(e); }
      });
    });
    root.querySelectorAll('[data-lv-pin]').forEach(function (pin) {
      var act = function (e) { e.stopPropagation(); select(pin.getAttribute('data-lv-pin'), true); };
      pin.addEventListener('click', act);
      pin.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); act(e); }
      });
    });

    function zoom(factor) {
      var w = vb[2] / factor, h = vb[3] / factor;
      w = Math.max(base[2] / 6, Math.min(base[2] * 1.6, w));
      h = w * (base[3] / base[2]);
      vb[0] += (vb[2] - w) / 2;
      vb[1] += (vb[3] - h) / 2;
      vb[2] = w; vb[3] = h;
      apply();
    }
    root.querySelectorAll('[data-lv-zoom]').forEach(function (b) {
      b.addEventListener('click', function () {
        var m = b.getAttribute('data-lv-zoom');
        if (m === 'fit') { vb = base.slice(); apply(); } else zoom(m === 'in' ? 1.35 : 1 / 1.35);
      });
    });

    // drag to pan
    var drag = null;
    svg.addEventListener('pointerdown', function (e) {
      if (e.target.closest('[data-lv-pin]')) return;
      drag = { x: e.clientX, y: e.clientY, vx: vb[0], vy: vb[1] };
      svg.classList.add('dragging');
      svg.setPointerCapture(e.pointerId);
    });
    svg.addEventListener('pointermove', function (e) {
      if (!drag) return;
      var r = svg.getBoundingClientRect();
      vb[0] = drag.vx - (e.clientX - drag.x) * (vb[2] / r.width);
      vb[1] = drag.vy - (e.clientY - drag.y) * (vb[3] / r.height);
      apply();
    });
    ['pointerup', 'pointercancel'].forEach(function (ev) {
      svg.addEventListener(ev, function () { drag = null; svg.classList.remove('dragging'); });
    });
  }
  function boot() { document.querySelectorAll('[data-lv]').forEach(init); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
