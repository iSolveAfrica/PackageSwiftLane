/* Account security and ID card (ProfileEdit, CustomerEdit, CustomerProfile; markup from
 * views/sl/data/account_edit.php sl_idSlotsHtml / sl_idDialogsHtml):
 *   [data-idv-open="front|back"]  opens the in-page ID viewer (#idv) on that side; [data-idv-side] switches
 *   [data-idu-open="front|back"]  opens the upload dialog (#idu) for that side, with a preview before saving
 *   [data-pe-reveal="id"]         shows / hides an element in place (password form, more devices)
 *   [data-pe-focus="id"]          scrolls to a field and focuses it
 * Saving uses the shared data-sl-form / data-sl-action handlers of assets/swiftlane/swiftlane.js. */
(function () {
  'use strict';
  if (window.slAccountSecurity) return;
  window.slAccountSecurity = true;
  var viewer = document.getElementById('idv'), upDialog = document.getElementById('idu');
  var upForm = upDialog ? upDialog.querySelector('form') : null;
  var MAX = 5 * 1048576, objectUrl = '';

  function label(side) { return side === 'back' ? 'Back' : 'Front'; }

  /* ---------- viewer ---------- */
  function showSide(side) {
    if (!viewer) return;
    var url = viewer.getAttribute('data-' + side) || '';
    viewer.querySelectorAll('[data-idv-side]').forEach(function (b) {
      var on = b.getAttribute('data-idv-side') === side;
      b.classList.toggle('on', on); b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    var img = viewer.querySelector('[data-idv-img]'), empty = viewer.querySelector('[data-idv-empty]');
    if (url) { img.src = url; img.alt = label(side) + ' of the ID card'; }
    img.hidden = !url; empty.hidden = !!url;
    empty.querySelector('[data-idv-empty-t]').textContent = label(side) + ' of ID not uploaded';
    var up = viewer.querySelector('[data-idu-open]');
    if (up) { up.setAttribute('data-idu-open', side); up.classList.toggle('ghost', !!url); up.querySelector('span').textContent = (url ? 'Replace ' : 'Upload ') + side; }
    viewer.setAttribute('data-side', side);
  }

  /* ---------- upload with preview ---------- */
  function note(text, bad) {
    var n = upForm.querySelector('[data-idu-note]');
    n.textContent = text || ''; n.style.color = bad ? 'var(--red-ink)' : 'var(--muted)';
  }
  function preview(src, caption) {
    var img = upForm.querySelector('[data-idu-prev]'), ph = upForm.querySelector('[data-idu-ph]');
    if (src) img.src = src;
    img.hidden = !src; ph.hidden = !!src;
    note(caption || '');
  }
  function openUpload(side) {
    if (!upForm) return;
    var file = upForm.querySelector('[data-idu-file]'), current = upForm.getAttribute('data-' + side) || '';
    file.value = '';
    file.name = upForm.getAttribute('data-field-' + side) || file.name;
    var sp = upForm.querySelector('[data-idu-side]'); if (sp) sp.value = side;
    upForm.querySelector('#idu-t').textContent = (current ? 'Replace the ' : 'Upload the ') + side + ' of the ID card';
    upForm.querySelector('[data-idu-save]').disabled = true;
    upForm.querySelector('[data-idu-pick]').hidden = true;
    if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = ''; }
    preview(current, current ? 'This is the photo on file now. Choose a new one to replace it.' : '');
    upForm.setAttribute('data-side', side);
    if (viewer) viewer.hidden = true;
    upDialog.hidden = false;
  }
  if (upForm) {
    upForm.querySelector('[data-idu-file]').addEventListener('change', function () {
      var f = this.files && this.files[0], save = upForm.querySelector('[data-idu-save]');
      if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = ''; }
      save.disabled = true;
      if (!f) { preview('', ''); return; }
      if (!/^image\/(jpeg|png|gif|webp)$/.test(f.type)) { this.value = ''; preview('', ''); note('Choose a JPEG, PNG, GIF or WEBP photo.', true); return; }
      if (f.size > MAX) { this.value = ''; preview('', ''); note('That photo is larger than 5 MB. Choose a smaller one.', true); return; }
      objectUrl = URL.createObjectURL(f);
      preview(objectUrl, 'Preview of ' + f.name + ': not saved yet. Check it is sharp, then save.');
      save.disabled = false;
      upForm.querySelector('[data-idu-pick]').hidden = false;
    });
    upForm.querySelector('[data-idu-pick]').addEventListener('click', function () { upForm.querySelector('[data-idu-file]').click(); });
    /* A file named .png that is not a photo: the browser cannot draw it, so it is refused before saving. */
    upForm.querySelector('[data-idu-prev]').addEventListener('error', function () {
      if (!objectUrl || this.getAttribute('src') !== objectUrl) return;
      URL.revokeObjectURL(objectUrl); objectUrl = '';
      upForm.querySelector('[data-idu-file]').value = '';
      upForm.querySelector('[data-idu-save]').disabled = true;
      upForm.querySelector('[data-idu-pick]').hidden = true;
      preview('', '');
      note('This file is not a readable photo. Choose a JPEG, PNG, GIF or WEBP photo of the card.', true);
    });
    /* A refused save also shows in the dialog, not only in the short toast. */
    upForm.addEventListener('sl:done', function (e) {
      var j = e.detail || {};
      if (!(j.success === true || j.status === 'success')) note(j.message || 'The photo could not be saved. Try again.', true);
    });
  }

  document.addEventListener('click', function (e) {
    var v = e.target.closest('[data-idv-open]');
    if (v && viewer) { e.preventDefault(); showSide(v.getAttribute('data-idv-open')); viewer.hidden = false; return; }
    var s = e.target.closest('[data-idv-side]');
    if (s) { e.preventDefault(); showSide(s.getAttribute('data-idv-side')); return; }
    var u = e.target.closest('[data-idu-open]');
    if (u && upForm) { e.preventDefault(); openUpload(u.getAttribute('data-idu-open')); return; }
    var r = e.target.closest('[data-pe-reveal]');
    if (r) {
      e.preventDefault();
      // The id comes from the attribute: box.id is shadowed on a form that has an input named "id".
      var boxId = r.getAttribute('data-pe-reveal'), box = document.getElementById(boxId); if (!box) return;
      box.hidden = !box.hidden;
      document.querySelectorAll('[data-pe-reveal="' + boxId + '"][aria-expanded]').forEach(function (b) {
        b.setAttribute('aria-expanded', box.hidden ? 'false' : 'true');
        var alt = b.getAttribute('data-alt'); if (alt) { b.setAttribute('data-alt', b.textContent); b.textContent = alt; }
      });
      if (!box.hidden) { var first = box.querySelector('input:not([type=hidden]),select,textarea'); if (first) first.focus(); }
      else {
        // Back to the button that opened it (not the Cancel button inside), once the box is hidden.
        var opener = Array.prototype.find.call(document.querySelectorAll('[data-pe-reveal="' + boxId + '"][aria-expanded]'), function (b) { return !box.contains(b); });
        if (opener) requestAnimationFrame(function () { opener.focus(); });
      }
      return;
    }
    var f = e.target.closest('[data-pe-focus]');
    if (f) {
      e.preventDefault();
      var el = document.getElementById(f.getAttribute('data-pe-focus')); if (!el) return;
      el.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'center' });
      el.focus({ preventScroll: true });
    }
  });
  /* A same-page link to #account-security or #details switches the tab (the shared runtime reads the hash on load only). */
  window.addEventListener('hashchange', function () {
    var name = location.hash.slice(1); if (!name) return;
    var t = Array.prototype.find.call(document.querySelectorAll('[data-sl-tab]'), function (el) { return el.getAttribute('data-sl-tab') === name; });
    if (t && !t.classList.contains('on')) { t.click(); var p = document.getElementById(name); if (p) p.scrollIntoView(); }
  });
  /* Arrow keys move between the two sides while the viewer is open. */
  document.addEventListener('keydown', function (e) {
    if (!viewer || viewer.hidden || (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight')) return;
    if (!viewer.querySelector('[data-idv-side]')) return;
    showSide(viewer.getAttribute('data-side') === 'back' ? 'front' : 'back');
  });
})();
