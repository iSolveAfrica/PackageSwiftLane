<?php
/**
 * Printer settings form, used on the label print pages (as a drawer) and in Label Studio.
 * Expects $pp = sl_printerOptions(), $ppTeam (bool: may save the team default), $ppScope ('me' | 'team').
 * Saves through ajax/sl/printer_options_ajax.php; "Test print" opens a calibration label.
 */
$ppScope = $ppScope ?? 'me';
$ppTeam = $ppTeam ?? false;
$src = $ppScope === 'team' ? $pp['team'] : $pp;
$sel = fn($k, $v) => (string) $src[$k] === (string) $v ? ' selected' : '';
$chk = fn($k) => !empty($src[$k]) ? ' checked' : '';
?>
<form class="pp" data-pp data-scope="<?= h($ppScope) ?>">
  <div class="pp-head"><b><?= $ppScope === 'team' ? 'Team default printer' : 'My printer' ?></b><span class="pp-from"><?= $ppScope === 'team' ? 'Used by everyone who has not set their own' : ($pp['from'] === 'mine' ? 'Your own settings for this account' : 'Using the team default · change it here for your desk') ?></span></div>
  <div class="pp-grid">
    <label><span>Printer name</span><input name="name" value="<?= h($src['name']) ?>" maxlength="80" placeholder="e.g. Zebra ZD421 at front desk"></label>
    <label><span>Printer type</span><select name="type"><option value="thermal"<?= $sel('type', 'thermal') ?>>Thermal label printer</option><option value="laser"<?= $sel('type', 'laser') ?>>Laser / inkjet (sheets)</option></select></label>
    <label><span>Resolution</span><select name="dpi"><option value="203"<?= $sel('dpi', '203') ?>>203 dpi</option><option value="300"<?= $sel('dpi', '300') ?>>300 dpi</option><option value="600"<?= $sel('dpi', '600') ?>>600 dpi</option></select></label>
    <label><span>Shipping label paper</span><select name="big"><option value="46"<?= $sel('big', '46') ?>>4 × 6 in</option><option value="44"<?= $sel('big', '44') ?>>4 × 4 in</option><option value="a4"<?= $sel('big', 'a4') ?>>A4 sheet (label centred)</option></select></label>
    <label><span>Small label paper</span><select name="small"><option value="21"<?= $sel('small', '21') ?>>2 × 1 in</option><option value="12"<?= $sel('small', '12') ?>>1 × 2 in (portrait)</option><option value="5025"<?= $sel('small', '5025') ?>>50 × 25 mm</option></select></label>
    <label><span>Page margin (mm)</span><input type="number" name="margin" step="0.5" min="0" max="15" value="<?= h($src['margin']) ?>"></label>
    <label><span>Move right / left (mm)</span><input type="number" name="dx" step="0.5" min="-15" max="15" value="<?= h($src['dx']) ?>"></label>
    <label><span>Move down / up (mm)</span><input type="number" name="dy" step="0.5" min="-15" max="15" value="<?= h($src['dy']) ?>"></label>
    <label><span>Scale (%)</span><input type="number" name="scale" step="1" min="80" max="120" value="<?= h($src['scale']) ?>"></label>
  </div>
  <div class="pp-flags">
    <label><input type="checkbox" name="dark" value="1"<?= $chk('dark') ?>> Print darker (faded thermal heads)</label>
    <label><input type="checkbox" name="border" value="1"<?= $chk('border') ?>> Outline around the label</label>
    <label><input type="checkbox" name="logo" value="1"<?= $chk('logo') ?>> Swift logo on labels</label>
    <label><input type="checkbox" name="auto" value="1"<?= $chk('auto') ?>> Open the print dialog straight away</label>
  </div>
  <div class="pp-acts">
    <button class="sl-btn sm" type="submit"><i class="ic ic-sm i-check" aria-hidden="true"></i>Save</button>
    <a class="sl-btn ghost sm" href="print_label_ship.php?test=1" target="_blank" rel="noopener"><i class="ic ic-sm i-print" aria-hidden="true"></i>Test print 4 × 6</a>
    <a class="sl-btn ghost sm" href="print_label_ship.php?test=1&amp;size=12" target="_blank" rel="noopener">Test small label</a>
    <?php if ($ppScope === 'me' && $pp['from'] === 'mine'): ?><button class="sl-btn ghost sm" type="button" data-pp-reset>Use team default</button><?php endif; ?>
    <span class="pp-note">Tip: print the test label, measure how far the frame is off, and enter it in Move right / down.</span>
  </div>
</form>
<style>
.pp{display:flex;flex-direction:column;gap:12px;font-family:var(--font,Figtree,Arial,sans-serif);color:var(--ink,#161615)}
.pp-head{display:flex;flex-direction:column;gap:2px}.pp-head b{font-size:15px}.pp-from{font-size:12.5px;color:var(--muted,#6b6a65)}
.pp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px}
.pp-grid label{display:flex;flex-direction:column;gap:4px;font-size:12px;color:var(--muted,#6b6a65)}
.pp-grid input,.pp-grid select{font:inherit;font-size:14px;color:var(--ink,#161615);height:38px;padding:0 10px;border-radius:10px;border:1px solid var(--line-2,#d9d7d0);background:var(--field,#fff)}
.pp-flags{display:flex;flex-wrap:wrap;gap:8px 18px;font-size:13.5px}.pp-flags label{display:inline-flex;align-items:center;gap:6px}
.pp-acts{display:flex;flex-wrap:wrap;gap:8px;align-items:center}.pp-note{font-size:12px;color:var(--muted,#6b6a65)}
</style>
<script>
(function () {
  var f = document.currentScript.previousElementSibling.previousElementSibling;
  if (!f || !f.matches('[data-pp]')) return;
  function send(data) {
    var b = new FormData(); Object.keys(data).forEach(function (k) { b.append(k, data[k]); });
    var t = document.querySelector('meta[name="csrf-token"]'); if (t) b.append('csrf_token', t.content);
    return fetch('ajax/sl/printer_options_ajax.php', { method: 'POST', body: b, credentials: 'same-origin', headers: t ? { 'X-CSRF-Token': t.content } : {} }).then(function (r) { return r.json(); });
  }
  function done(j) { if (window.SL && SL.toast) SL.toast(j.message || (j.success ? 'Saved' : 'Not saved'), !j.success); if (j.success) setTimeout(function () { location.reload(); }, 500); }
  f.addEventListener('submit', function (e) {
    e.preventDefault();
    var data = { scope: f.getAttribute('data-scope') };
    new FormData(f).forEach(function (v, k) { data[k] = v; });
    ['dark', 'border', 'logo', 'auto'].forEach(function (k) { if (!(k in data)) data[k] = 0; });
    (window.SL && SL.post ? SL.post('ajax/sl/printer_options_ajax.php', data) : send(data)).then(done);
  });
  var r = f.querySelector('[data-pp-reset]');
  if (r) r.addEventListener('click', function () { (window.SL && SL.post ? SL.post('ajax/sl/printer_options_ajax.php', { scope: 'me', reset: 1 }) : send({ scope: 'me', reset: 1 })).then(done); });
})();
</script>
