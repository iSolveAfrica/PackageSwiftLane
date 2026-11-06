<?php
/**
 * UserPermissions - per-person permission overrides (user_permissions.php?user=). Inherit follows the role,
 * Allow / Deny override it for this person only. Every change saves at once through the unchanged
 * ajax/users/users_overrides_save_ajax.php (user_id, changes[action_id] = inherit|allow|deny).
 */
$t = $d['target'];
?>
<div class="row g8 mut sm"><a href="<?= sl_route('StaffRoles') ?>">Staff and roles</a><i class="ic ic-sm i-right" aria-hidden="true"></i><a href="users_edit.php?user=<?= (int) $t->id ?>"><?= h($d['name']) ?></a><i class="ic ic-sm i-right" aria-hidden="true"></i><span style="color:var(--ink)">Permissions</span></div>
<div class="sl-head">
  <div><h1 class="sl-title">Permissions · <?= h($d['name']) ?></h1><p class="sl-lede">Role: <b><?= h($d['role']) ?></b>. Inherit follows the role; Allow or Deny changes it for this person only. Changes save instantly.</p></div>
  <a class="sl-btn ghost" href="<?= sl_route('StaffRoles') ?>"><i class="ic ic-sm i-left" aria-hidden="true"></i>Back</a>
</div>
<section class="col g16" data-sl-scope id="up-root" data-user="<?= (int) $t->id ?>">
  <div class="row wrap g12 between">
    <div class="sl-search" style="min-width:280px"><i class="ic ic-sm i-search" aria-hidden="true"></i><input data-sl-filter-q placeholder="Filter by name, code or module" aria-label="Filter permissions"></div>
    <div class="row g12 wrap"><div class="sl-seg boxed" data-sl-filter><button class="on" data-f="all">All</button><button data-f="ov">Overridden</button><button data-f="on">Allowed</button><button data-f="off">Not allowed</button></div><span class="mut sm"><b data-sl-count><?= array_sum(array_map('count', $d['modules'])) ?></b> shown</span></div>
  </div>
  <div class="grid cols2" style="gap:16px">
  <?php foreach ($d['modules'] as $module => $actions): ?>
    <section class="sl-card col g8" data-up-module>
      <div class="sl-ch" style="margin:0"><h2 class="sl-ct"><?= h($module ?: 'Module') ?></h2>
        <span class="row g4"><button type="button" class="sl-btn ghost sm" data-up-bulk="allow">Allow all</button><button type="button" class="sl-btn ghost sm" data-up-bulk="deny">Deny all</button><button type="button" class="sl-btn ghost sm" data-up-bulk="inherit">Inherit all</button></span></div>
      <?php foreach ($actions as $a):
          $eff = $a['state'] === 'allow' || ($a['state'] === 'inherit' && $a['roleOn']); ?>
      <div class="row g10 between wrap" data-sl-row data-f="<?= $a['state'] !== 'inherit' ? 'ov ' : '' ?><?= $eff ? 'on' : 'off' ?>" data-aid="<?= (int) $a['id'] ?>" data-role-on="<?= $a['roleOn'] ? 1 : 0 ?>" style="padding:8px 0;border-top:1px solid var(--line)">
        <span class="col grow" style="min-width:180px"><span class="sm fw5"><?= h($a['label']) ?></span><span class="row g6"><span class="mono mut" style="font-size:11.5px"><?= h($a['name']) ?></span><span data-up-badge><?= $a['state'] === 'allow' ? sl_pill('allowed', 'delivered') : ($a['state'] === 'deny' ? sl_pill('denied', 'overdue') : sl_pill($a['roleOn'] ? 'role: allowed' : 'role: none', $a['roleOn'] ? 'paid' : 'plain')) ?></span><span style="display:none"><?= h($module) ?></span></span></span>
        <span class="sl-seg boxed" role="radiogroup" aria-label="<?= h($a['label']) ?>">
          <?php foreach (['inherit' => 'Inherit', 'allow' => 'Allow', 'deny' => 'Deny'] as $v => $l): ?><button type="button" class="<?= $a['state'] === $v ? 'on' : '' ?>" data-up-set="<?= $v ?>" aria-pressed="<?= $a['state'] === $v ? 'true' : 'false' ?>"><?= $l ?></button><?php endforeach; ?>
        </span>
      </div>
      <?php endforeach; ?>
    </section>
  <?php endforeach; ?>
  </div>
  <div class="sl-empty mut sm" data-sl-empty hidden>No permissions match your filter.</div>
</section>
<script>
(function () {
  var root = document.getElementById('up-root'), uid = root.getAttribute('data-user');
  var pills = { allow: ['allowed', 'delivered'], deny: ['denied', 'overdue'] };
  function paint(row, state) {
    var roleOn = row.getAttribute('data-role-on') === '1';
    row.querySelectorAll('[data-up-set]').forEach(function (b) { var on = b.getAttribute('data-up-set') === state; b.classList.toggle('on', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); });
    var p = pills[state] || (roleOn ? ['role: allowed', 'paid'] : ['role: none', 'plain']);
    var badge = row.querySelector('[data-up-badge]'); badge.innerHTML = '<span class="sl-pill"></span>'; badge.firstChild.className = 'sl-pill ' + p[1]; badge.firstChild.textContent = p[0];
    var eff = state === 'allow' || (state === 'inherit' && roleOn);
    row.setAttribute('data-f', (state !== 'inherit' ? 'ov ' : '') + (eff ? 'on' : 'off'));
  }
  function save(rows, state) {
    var fd = new FormData(); fd.append('user_id', uid);
    rows.forEach(function (r) { fd.append('changes[' + r.getAttribute('data-aid') + ']', state); });
    var before = rows.map(function (r) { var b = r.querySelector('[data-up-set].on'); return b ? b.getAttribute('data-up-set') : 'inherit'; });
    rows.forEach(function (r) { paint(r, state); });
    SL.post('ajax/users/users_overrides_save_ajax.php', fd).then(function (j) {
      if (j.status === 'success') SL.toast(rows.length > 1 ? (j.count || rows.length) + ' permissions set to ' + state : 'Saved');
      else { rows.forEach(function (r, i) { paint(r, before[i]); }); SL.toast(j.message || 'Not saved', true); }
    }).catch(function () { rows.forEach(function (r, i) { paint(r, before[i]); }); SL.toast('Network error', true); });
  }
  root.addEventListener('click', function (e) {
    var b = e.target.closest('[data-up-set]');
    if (b) { if (!b.classList.contains('on')) save([b.closest('[data-aid]')], b.getAttribute('data-up-set')); return; }
    var k = e.target.closest('[data-up-bulk]');
    if (k) {
      var rows = Array.prototype.filter.call(k.closest('[data-up-module]').querySelectorAll('[data-aid]'), function (r) { return !r.hidden; });
      if (rows.length && confirm('Set ' + rows.length + ' permission' + (rows.length > 1 ? 's' : '') + ' to ' + k.getAttribute('data-up-bulk') + '?')) save(rows, k.getAttribute('data-up-bulk'));
    }
  });
})();
</script>
