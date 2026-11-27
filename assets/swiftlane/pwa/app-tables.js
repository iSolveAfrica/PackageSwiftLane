/* Phone app view: data tables become cards. Each cell gets its column name (data-label) and the table the
   class pwa-cards; assets/swiftlane/pwa/app-shell.css lays it out. Tables added later (page scripts that
   draw their lists after loading) are picked up too. A table with class "pwa-keep" stays a table. */
(function () {
  'use strict';
  var phone = matchMedia('(max-width:767px), (max-height:500px) and (max-width:950px) and (pointer:coarse)');
  var role = document.body && document.body.dataset.mobileRole;
  if (!role) return;
  function label(table) {
    if (table.classList.contains('pwa-keep') || table.closest('.pwa-keep, .sl-dialog .sl-cal, [data-sl-chart]')) return;
    var head = table.tHead && table.tHead.rows[table.tHead.rows.length - 1];
    if (!head) return;
    var names = [];
    Array.prototype.forEach.call(head.cells, function (th) {
      var span = th.colSpan || 1, t = (th.textContent || '').replace(/\s+/g, ' ').trim();
      for (var i = 0; i < span; i++) names.push(t);
    });
    if (names.length < 2) return;
    Array.prototype.forEach.call(table.tBodies, function (tb) {
      Array.prototype.forEach.call(tb.rows, function (tr) {
        if (tr.dataset.pwaDone) return;
        tr.dataset.pwaDone = '1';
        var col = 0, titled = false;
        Array.prototype.forEach.call(tr.cells, function (td) {
          if (!td.hasAttribute('data-label')) td.setAttribute('data-label', td.colSpan > 1 ? '' : (names[col] || ''));
          col += td.colSpan || 1;
          var text = (td.textContent || '').replace(/\s+/g, ' ').trim();
          var controls = td.querySelectorAll('button, a, details, select, input:not([type=checkbox])').length;
          if (!text && td.querySelector('input[type=checkbox]') && !controls) { td.classList.add('pwa-check'); return; }  // row selection box -> corner
          if (!titled && text && td.colSpan < 2) { td.classList.add('pwa-title'); titled = true; return; }          // first real value -> card title
          if (controls && (td.getAttribute('data-label') === '' || text.length <= 3)) td.classList.add('pwa-act');   // actions (e.g. the "…" menu) -> bottom row
        });
      });
    });
    table.classList.add('pwa-cards');
  }
  function scan(root) { (root.querySelectorAll ? root : document).querySelectorAll('table').forEach(label); }
  function start() {
    if (!phone.matches) return;
    scan(document);
    var pending = false;
    new MutationObserver(function () {
      if (pending) return; pending = true;
      requestAnimationFrame(function () { pending = false; scan(document); });
    }).observe(document.body, { childList: true, subtree: true });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
