<?php
/**
 * Pickup code dialog shared by the operations pages. Opened with
 * data-sl-open="sp-code" data-code-id="<order ids, comma>" data-code-trk="<tracking>" [data-code-module="air|sea"].
 * Talks to ajax/sl/warehouse_ajax.php (op=code_state / code_send / code_verify), which wraps helpers/pickup_code.php.
 */
$slCodeModule = $slCodeModule ?? 'sea';
// Warehouse / district administrators and finance may skip the code (permission pickup_code_skip).
$slCanSkipCode = function_exists('cdp_pickupCodeCanSkip') && isset($user) && cdp_pickupCodeCanSkip($user);
?>
<div class="sl-dialog" id="sp-code" hidden><div class="sl-modal" role="dialog" aria-label="Pickup code">
  <div class="row g10"><span class="sl-tile"><i class="ic i-key" aria-hidden="true"></i></span><div class="col grow"><b class="fw5">Pickup code · <span id="sp-code-trk" class="mono"></span></b><span class="mut sm" id="sp-code-owner">Loading…</span></div></div>
  <div id="sp-code-state" class="sl-banner info"><i class="ic i-info" aria-hidden="true"></i><span>Checking the customer's code…</span></div>
  <form class="col g10" id="sp-code-form" data-sl-form action="ajax/sl/warehouse_ajax.php" method="post">
    <input type="hidden" name="op" value="code_verify"><input type="hidden" name="module" value="<?= h($slCodeModule) ?>" id="sp-code-module"><input type="hidden" name="ids" id="sp-code-ids" value="">
    <label class="sl-field"><span class="sl-lab">Code the customer shows</span><input class="sl-in mono" name="code" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" placeholder="6 digits" autocomplete="off"></label>
    <div class="row g8 wrap"><button type="submit" class="sl-btn"><i class="ic ic-sm i-check" aria-hidden="true"></i>Verify code</button><button type="button" class="sl-btn ghost" id="sp-code-send"><i class="ic ic-sm i-send" aria-hidden="true"></i>Send code to customer</button><?php if (!empty($slCanSkipCode)): ?><button type="button" class="sl-btn ghost" id="sp-code-skip" style="color:var(--amber-ink)"><i class="ic ic-sm i-right" aria-hidden="true"></i>Skip code</button><?php endif; ?><button type="button" class="sl-btn ghost" data-sl-close>Close</button></div>
    <?php if (!empty($slCanSkipCode)): ?><div class="row g8 wrap" id="sp-skip-row" hidden><input class="sl-in grow" id="sp-skip-reason" maxlength="200" placeholder="Why is the code skipped? (e.g. customer phone lost, ID checked)"><button type="button" class="sl-btn" id="sp-skip-go">Skip and allow handover</button></div><?php endif; ?>
    <span class="mut sm">One resend a minute · 5 wrong entries void the code · a verified code allows handover for 30 minutes.</span>
  </form>
</div></div>
<script>
(function () {
  var dlg = document.getElementById('sp-code'); if (!dlg) return;
  var state = document.getElementById('sp-code-state');
  function setState(cls, text) { state.className = 'sl-banner ' + cls; state.querySelector('span').textContent = text; }
  function load() {
    SL.post('ajax/sl/warehouse_ajax.php', { op: 'code_state', module: document.getElementById('sp-code-module').value, ids: document.getElementById('sp-code-ids').value }).then(function (j) {
      document.getElementById('sp-code-owner').textContent = j.owner ? j.owner : '';
      if (!j.success) return setState('bad', j.message || 'Could not read the code state');
      if (j.verified) setState('ok', 'Code verified. The package can be handed over within 30 minutes.');
      else if (j.live) setState('info', 'The customer holds a code sent ' + (j.live.sent_at || '') + (j.live.channels && j.live.channels.length ? ' by ' + j.live.channels.join(', ') : '') + '. Ask them for it.');
      else setState('', 'No code yet. Send one: it goes by SMS, WhatsApp and e-mail.');
    });
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-code-id]');
    if (!b) return;
    document.getElementById('sp-code-ids').value = b.getAttribute('data-code-id');
    document.getElementById('sp-code-trk').textContent = b.getAttribute('data-code-trk') || '';
    if (b.getAttribute('data-code-module')) document.getElementById('sp-code-module').value = b.getAttribute('data-code-module');
    dlg.querySelector('[name=code]').value = '';
    setState('info', 'Checking the customer\'s code…');
    load();
  });
  document.getElementById('sp-code-send').addEventListener('click', function () {
    var btn = this; btn.disabled = true;
    SL.post('ajax/sl/warehouse_ajax.php', { op: 'code_send', module: document.getElementById('sp-code-module').value, ids: document.getElementById('sp-code-ids').value }).then(function (j) { SL.toast(j.message || '', !j.success); load(); }).finally(function () { btn.disabled = false; });
  });
  var sk = document.getElementById('sp-code-skip'), skGo = document.getElementById('sp-skip-go');
  if (sk) sk.addEventListener('click', function () { var r = document.getElementById('sp-skip-row'); r.hidden = !r.hidden; if (!r.hidden) document.getElementById('sp-skip-reason').focus(); });
  if (skGo) skGo.addEventListener('click', function () {
    var reason = document.getElementById('sp-skip-reason').value.trim(); if (reason.length < 4) return SL.toast('Say why the code is skipped', true);
    skGo.disabled = true;
    SL.post('ajax/sl/warehouse_ajax.php', { op: 'code_skip', module: document.getElementById('sp-code-module').value, ids: document.getElementById('sp-code-ids').value, reason: reason }).then(function (j) {
      SL.toast(j.message || '', !j.success);
      if (j.success) { document.getElementById('sp-skip-row').hidden = true; load(); document.dispatchEvent(new CustomEvent('sl:code-verified', { detail: j })); }
    }).finally(function () { skGo.disabled = false; });
  });
  document.getElementById('sp-code-form').addEventListener('sl:done', function (e) { if (e.detail && e.detail.success) { load(); document.dispatchEvent(new CustomEvent('sl:code-verified', { detail: e.detail })); } });
})();
</script>
