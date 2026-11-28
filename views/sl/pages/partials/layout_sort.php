<?php
/**
 * Drag-and-drop ordering for layout lists (Receipt Studio, Label Studio label layout).
 * Markup: <ol data-lo-list> of <li data-k="..."> rows with a [data-lo-grip] handle and optional
 * [data-lo-up] / [data-lo-down] buttons. Works with mouse, touch and pen (pointer events).
 * After every move the list fires a "lo:change" event; the page saves the new order.
 * Included once per page.
 */
?>
<style>
.lo-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:6px}
.lo-row{display:flex;align-items:center;gap:10px;padding:8px 10px;border:1px solid var(--line);border-radius:12px;background:var(--card);touch-action:auto;user-select:none}
.lo-row.off{opacity:.55}.lo-row.off .lo-t b{text-decoration:line-through}
.lo-row.drag{box-shadow:0 12px 28px rgba(0,0,0,.18);border-color:#0A3D2C;position:relative;z-index:3;background:var(--card)}
.lo-grip{width:28px;height:36px;display:grid;place-items:center;cursor:grab;touch-action:none;color:var(--muted);flex:none;border:0;background:transparent;font-size:18px;line-height:1}
.lo-grip:active{cursor:grabbing}
.lo-t{flex:1;min-width:0;display:flex;flex-direction:column;font-size:14px;line-height:1.3}
.lo-t small{color:var(--muted);font-size:12px}
.lo-mv{display:flex;gap:2px}
.lo-mv button{width:28px;height:28px;border-radius:8px;border:1px solid var(--line-2);background:var(--field);color:var(--ink);cursor:pointer;font-size:12px}
.lo-mv button:disabled{opacity:.3;cursor:default}
.lo-sw{width:40px;height:22px;border-radius:100px;background:var(--line-2);position:relative;flex:none;border:0;cursor:pointer;padding:0}
.lo-sw::after{content:"";position:absolute;top:3px;left:3px;width:16px;height:16px;border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.2);transition:left .12s}
.lo-sw[aria-checked="true"]{background:#0A7C57}.lo-sw[aria-checked="true"]::after{left:21px}
.lo-sw:disabled,.lo-mv button[disabled],.lo-grip[disabled]{cursor:not-allowed}
</style>
<script>
(function () {
  function rows(list) { return Array.prototype.slice.call(list.querySelectorAll(':scope > [data-k]')); }
  function refresh(list) {
    var r = rows(list);
    r.forEach(function (li, i) {
      var u = li.querySelector('[data-lo-up]'), d = li.querySelector('[data-lo-down]');
      if (u) u.disabled = i === 0 || list.hasAttribute('data-lo-ro');
      if (d) d.disabled = i === r.length - 1 || list.hasAttribute('data-lo-ro');
    });
  }
  function changed(list) {
    refresh(list);
    var r = rows(list);
    list.dispatchEvent(new CustomEvent('lo:change', { bubbles: true, detail: { order: r.map(function (li) { return li.getAttribute('data-k'); }) } }));
  }
  window.SLLayout = { order: function (list) { return rows(list).map(function (li) { return li.getAttribute('data-k'); }); } };
  document.querySelectorAll('[data-lo-list]').forEach(function (list) {
    if (list.hasAttribute('data-lo-ro')) { refresh(list); return; }
    list.addEventListener('click', function (e) {
      var up = e.target.closest('[data-lo-up]'), dn = e.target.closest('[data-lo-down]');
      if (!up && !dn) return;
      var li = e.target.closest('[data-k]');
      if (up && li.previousElementSibling) list.insertBefore(li, li.previousElementSibling);
      if (dn && li.nextElementSibling) list.insertBefore(li.nextElementSibling, li);
      (up || dn).focus();
      changed(list);
    });
    list.addEventListener('pointerdown', function (e) {
      var grip = e.target.closest('[data-lo-grip]');
      if (!grip || e.button > 0) return;
      e.preventDefault();
      var li = grip.closest('[data-k]'), startY = e.clientY, moved = false;
      li.classList.add('drag');
      // Listen on window: moving the row in the DOM drops pointer capture on the grip.
      function move(ev) {
        if (ev.pointerId !== e.pointerId) return;
        li.style.transform = 'translateY(' + (ev.clientY - startY) + 'px)';
        var others = rows(list).filter(function (x) { return x !== li; });
        for (var i = 0; i < others.length; i++) {
          var b = others[i].getBoundingClientRect(), mid = b.top + b.height / 2;
          var before = li.compareDocumentPosition(others[i]) & Node.DOCUMENT_POSITION_PRECEDING;
          if (before && ev.clientY < mid) { var t0 = li.getBoundingClientRect().top; list.insertBefore(li, others[i]); startY += li.getBoundingClientRect().top - t0; moved = true; break; }
          if (!before && ev.clientY > mid) { var t1 = li.getBoundingClientRect().top; list.insertBefore(li, others[i].nextSibling); startY += li.getBoundingClientRect().top - t1; moved = true; }
        }
        li.style.transform = 'translateY(' + (ev.clientY - startY) + 'px)';
      }
      function up() {
        window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); window.removeEventListener('pointercancel', up);
        li.classList.remove('drag'); li.style.transform = '';
        if (moved) changed(list);
      }
      window.addEventListener('pointermove', move); window.addEventListener('pointerup', up); window.addEventListener('pointercancel', up);
    });
    refresh(list);
  });
})();
</script>
