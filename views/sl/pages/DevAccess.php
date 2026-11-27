<?php
/**
 * DevAccess - developer console access (dark DevOps theme).
 * mode "locked": the console is shut down; only the unlock form.
 * mode "manage": per-section grants for each assigned super admin, the access matrix, the locked shutdown.
 * Data: dev_access.php. Writes: ajax/sl/dev_access_ajax.php. Logic: helpers/dev_access.php.
 */
if (!isset($d)) { $d = []; }
$ajax = 'ajax/sl/dev_access_ajax.php';
if ($d['mode'] === 'locked'): ?>
<div class="da-lock">
  <section class="dvx-card da-lock-card" aria-labelledby="da-lock-t">
    <span class="da-lock-ic"><i class="ic i-lock" aria-hidden="true"></i></span>
    <h1 class="dvx-title" id="da-lock-t">Developer console is locked</h1>
    <p class="dvx-lede">Locked by <b><?= h($d['by']) ?></b> <span title="<?= h($d['at']) ?>"><?= h($d['ago']) ?></span>. Every developer page and endpoint is off, for every user, until the unlock code is entered.</p>
    <?php if ($d['note'] !== ''): ?><p class="da-note"><i class="ic ic-sm i-info" aria-hidden="true"></i><span><?= h($d['note']) ?></span></p><?php endif; ?>
    <form class="da-form" method="post" action="<?= $ajax ?>" data-sl-form autocomplete="off">
      <input type="hidden" name="op" value="unlock">
      <label class="da-field"><span>Unlock code</span><input class="da-in" type="password" name="unlock_secret" required minlength="8" maxlength="128" autocomplete="off" autofocus<?= $d['wait'] ? ' disabled' : '' ?>></label>
      <button type="submit" class="dvx-btn primary"<?= $d['wait'] ? ' disabled' : '' ?>><i class="ic ic-sm i-key" aria-hidden="true"></i>Unlock the console</button>
      <p class="dvx-cs"><?= $d['wait'] ? 'Too many wrong codes. Try again in ' . max(1, (int) ceil($d['wait'] / 60)) . ' min.' : (int) $d['left'] . ' attempts left in this 15-minute window. Every attempt is recorded in the activity log.' ?></p>
    </form>
    <details class="da-rec"><summary>Lost the code?</summary><p class="dvx-cs">Someone with shell access to the server runs <span class="mono">php scripts/dev_unlock.php</span> from the application folder. It asks for confirmation, removes the lock, clears the failed-attempt counters and writes the recovery to the activity log. There is no way to unlock from the browser without the code.</p></details>
    <?php if ($d['attempts']): ?>
    <div class="da-att"><h2 class="dvx-h3">Recent attempts</h2>
      <?php foreach ($d['attempts'] as $a): ?><div class="dvx-row"><span class="dvx-dot <?= $a['ok'] ? 'ok' : 'bad' ?>" aria-hidden="true"></span><span class="grow dvx-min"><b><?= h($a['who']) ?></b> <small><?= $a['ok'] ? 'unlocked' : 'wrong code' ?> · <span class="mono"><?= h($a['ip']) ?></span></small></span><small title="<?= h($a['at']) ?>"><?= h($a['when']) ?></small></div><?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>
</div>
<?php return; endif;
$dvxActive = 'access';
include CDP_ROOT . '/views/sl/pages/partials/dev_subnav.php';
?>
<div class="dvx-page da">
<header class="dvx-head">
  <div class="col g4"><h1 class="dvx-title">Developer access</h1><p class="dvx-lede">Who may open which part of the developer console. Only assigned super admins get in, and each sees only the sections granted here. Owner: <b><?= h($d['owner']) ?></b>.</p></div>
  <div class="row g8 wrap"><span class="dvx-pill ok"><i class="dvx-dot ok"></i>Console on</span><?php if ($d['canShutdown']): ?><a class="dvx-btn" href="#shutdown"><i class="ic ic-sm i-lock" aria-hidden="true"></i>Shutdown</a><?php endif; ?></div>
</header>

<section class="da-kpis" aria-label="Summary">
  <a class="mc-kpi da-kpi" href="#people"><span class="t"><i class="ic ic-sm i-users" aria-hidden="true"></i>Developers</span><b><?= (int) $d['devN'] ?> <small>/ <?= (int) $d['superN'] ?> super admins</small></b><small>Assigned to the console</small></a>
  <a class="mc-kpi da-kpi" href="#matrix"><span class="t"><i class="ic ic-sm i-key" aria-hidden="true"></i>Your sections</span><b><?= (int) $d['mineN'] ?> <small>/ <?= (int) $d['total'] ?></small></b><small><?= $d['isOwner'] ? 'Owner: every section' : 'Granted by the owner or an access admin' ?></small></a>
  <a class="mc-kpi da-kpi" href="#audit"><span class="t"><i class="ic ic-sm i-clock" aria-hidden="true"></i>Access changes</span><b><?= (int) $d['auditWeek'] ?> <small>last 7 days</small></b><small><?= $d['audit'] ? 'Last ' . h($d['audit'][0]['when']) : 'None yet' ?></small></a>
  <a class="mc-kpi da-kpi" href="#shutdown"><span class="t"><i class="ic ic-sm i-lock" aria-hidden="true"></i>Shutdown</span><b><?= $d['canShutdown'] ? 'Allowed' : 'Not yours' ?></b><small><?= $d['lastUnlock'] !== '' ? 'Last unlocked ' . h($d['lastUnlock']) : 'Never locked' ?></small></a>
</section>

<?php if ($d['canAccess']): ?>
<section class="dvx-card dvx-flush" id="matrix">
  <div class="dvx-ch pad"><div><h2 class="dvx-ct">Who sees what</h2><p class="dvx-cs">A section that is not granted disappears from the developer menu and its pages and endpoints answer 403.</p></div><span class="dvx-legend"><span><i style="background:var(--green)"></i>Granted</span><span><i style="background:var(--line-2)"></i>Not granted</span></span></div>
  <div class="dvx-tw">
    <table class="dvx-table da-mx">
      <thead><tr><th scope="col">Super admin</th><?php foreach ($d['sections'] as $k => $s): ?><th scope="col" class="da-mx-h" title="<?= h($s[0] . ': ' . $s[2]) ?>"><span><?= h($d['short'][$k] ?? $s[0]) ?></span></th><?php endforeach; ?></tr></thead>
      <tbody>
      <?php foreach ($d['rows'] as $r): ?>
        <tr><th scope="row"><a class="da-who" href="#u<?= (int) $r['id'] ?>"><span class="sl-av sm" aria-hidden="true"><?= h($r['ini']) ?></span><span class="dvx-min"><b class="dvx-ell"><?= h($r['name']) ?></b><small data-da-n="<?= (int) $r['id'] ?>"><?= $r['owner'] ? 'Owner' : ($r['assigned'] ? $r['n'] . ' sections' : 'Not assigned') ?></small></span></a></th>
        <?php foreach ($r['cells'] as $c): ?><td class="da-mx-c"><span class="da-mx-d<?= $c['on'] ? ' on' : '' ?>" data-da-dot="<?= (int) $r['id'] ?>-<?= h($c['k']) ?>" role="img" aria-label="<?= h($d['sections'][$c['k']][0]) ?>: <?= $c['on'] ? 'granted' : 'not granted' ?>"></span></td><?php endforeach; ?></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="da-people" id="people" aria-label="Developers">
<?php foreach ($d['rows'] as $r): ?>
  <article class="dvx-card da-user<?= $r['assigned'] ? '' : ' off' ?>" id="u<?= (int) $r['id'] ?>">
    <div class="dvx-ch">
      <div class="row g12 dvx-min"><span class="sl-av" aria-hidden="true"><?= h($r['ini']) ?></span><div class="dvx-min"><h2 class="dvx-ct"><?= h($r['name']) ?><?php if ($r['owner']): ?><span class="dvx-ovr">Owner</span><?php endif; ?><?php if ($r['me']): ?><span class="dvx-badge">You</span><?php endif; ?></h2><p class="dvx-cs"><span class="mono"><?= h($r['username']) ?></span><?= $r['email'] !== '' ? ' · ' . h($r['email']) : '' ?><?= $r['assigned'] ? ' · developer since ' . h($r['since']) . ($r['changedAgo'] !== '' ? ' · changed ' . h($r['changedAgo']) : '') : ' · super admin, not assigned' ?></p></div></div>
      <div class="row g8 wrap">
      <?php if (!$r['assigned']): ?>
        <button type="button" class="dvx-btn primary sm" data-sl-action="<?= $ajax ?>" data-p-op="assign" data-p-user_id="<?= (int) $r['id'] ?>" data-p-value="1" data-sl-reload data-sl-confirm="Give <?= h($r['name']) ?> access to the developer console? They start with the Overview section only.">Add to console</button>
      <?php elseif (!$r['owner'] && !$r['me']): ?>
        <span class="dvx-seg" role="group" aria-label="Presets for <?= h($r['name']) ?>">
          <button type="button" data-sl-action="<?= $ajax ?>" data-p-op="preset" data-p-preset="observe" data-p-user_id="<?= (int) $r['id'] ?>" data-sl-reload>Observe only</button>
          <button type="button" data-sl-action="<?= $ajax ?>" data-p-op="preset" data-p-preset="all" data-p-user_id="<?= (int) $r['id'] ?>" data-sl-reload data-sl-confirm="Grant <?= h($r['name']) ?> every section you are allowed to grant?">All</button>
          <button type="button" data-sl-action="<?= $ajax ?>" data-p-op="preset" data-p-preset="none" data-p-user_id="<?= (int) $r['id'] ?>" data-sl-reload data-sl-confirm="Revoke every section from <?= h($r['name']) ?>?">None</button>
        </span>
        <button type="button" class="dvx-btn sm ghost" data-sl-action="<?= $ajax ?>" data-p-op="assign" data-p-user_id="<?= (int) $r['id'] ?>" data-p-value="0" data-sl-reload data-sl-confirm="Remove <?= h($r['name']) ?> from the developer console? All their sections are revoked.">Remove</button>
      <?php endif; ?>
      </div>
    </div>
    <?php if ($r['assigned']): ?>
    <div class="da-groups">
    <?php foreach ($d['groups'] as $gName => $items): ?>
      <div class="da-grp"><span class="dvx-sub-g"><?= h($gName) ?></span>
      <?php foreach ($items as $it): $c = null; foreach ($r['cells'] as $x) { if ($x['k'] === $it['k']) { $c = $x; break; } } ?>
        <div class="da-sec<?= $c['on'] ? ' on' : '' ?>"><span class="dvx-min grow"><b><?= h($it['label']) ?><?php if (in_array($it['k'], $d['ownerOnly'], true)): ?> <span class="da-oo" title="Only the owner grants or revokes this section">owner grants</span><?php endif; ?></b><small><?= h($it['desc']) ?></small><?php if ($it['k'] === 'database'): ?><small class="da-warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i>An SQL import can rewrite the developer access tables, the lock and the owner, so this section is worth every other one.</small><?php endif; ?></span>
          <?php if ($c['lock'] !== ''): ?><span class="sl-toggle <?= $c['on'] ? 'on' : '' ?>" role="switch" aria-checked="<?= $c['on'] ? 'true' : 'false' ?>" aria-label="<?= h($it['label']) ?> for <?= h($r['name']) ?>" title="<?= h($c['lock']) ?>" data-static></span>
          <?php else: ?><span class="sl-toggle <?= $c['on'] ? 'on' : '' ?>" role="switch" aria-checked="<?= $c['on'] ? 'true' : 'false' ?>" aria-label="<?= h($it['label']) ?> for <?= h($r['name']) ?>" data-sl-save="<?= $ajax ?>" data-key="<?= h($it['k']) ?>" data-p-op="grant" data-p-user_id="<?= (int) $r['id'] ?>" data-da-sw="<?= (int) $r['id'] ?>-<?= h($it['k']) ?>"></span><?php endif; ?>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </article>
<?php endforeach; ?>
</section>
<?php else: ?>
<div class="dvx-note" role="note"><i class="ic i-info" aria-hidden="true"></i><span>You hold the Shutdown section but not Developer access, so you cannot see or change who has which section.</span></div>
<?php endif; ?>

<div class="dvx-grid dvx-g2">
<?php if ($d['canShutdown']): ?>
<section class="dvx-card da-shut" id="shutdown">
  <div class="dvx-ch"><div><h2 class="dvx-ct"><i class="ic ic-sm i-lock" aria-hidden="true"></i>Shut the developer console down</h2><p class="dvx-cs">Every developer page and endpoint goes off for every user, you included, and the developer menu shrinks to the unlock screen. The business modules keep running. Bringing it back needs the unlock code you set now.</p></div></div>
  <form class="da-form" method="post" action="<?= $ajax ?>" data-sl-form autocomplete="off">
    <input type="hidden" name="op" value="lock">
    <label class="da-field"><span>Unlock code <small>at least 8 characters</small></span><input class="da-in" type="password" name="unlock_secret" required minlength="8" maxlength="128" autocomplete="new-password"></label>
    <label class="da-field"><span>Repeat the unlock code</span><input class="da-in" type="password" name="unlock_secret2" required minlength="8" maxlength="128" autocomplete="new-password"></label>
    <label class="da-field"><span>Your password <small>to confirm it is you (5 tries per 15 minutes)</small></span><input class="da-in" type="password" name="password" required autocomplete="current-password"></label>
    <label class="da-field"><span>Reason <small>optional, shown on the lock screen</small></span><input class="da-in" type="text" name="note" maxlength="200" placeholder="e.g. Audit in progress"></label>
    <label class="da-check"><input type="checkbox" name="confirm" value="1" required><span>I understand the developer console stays off until someone enters this code, and that a lost code can only be cleared on the server.</span></label>
    <button type="submit" class="dvx-btn da-danger"><i class="ic ic-sm i-lock" aria-hidden="true"></i>Shut down and lock</button>
  </form>
</section>
<?php endif; ?>
<section class="dvx-card" id="audit">
  <div class="dvx-ch"><div><h2 class="dvx-ct">Access audit</h2><p class="dvx-cs">Grants, removals, shutdowns and unlock attempts, refused ones included.</p></div><a class="dvx-link" href="<?= h(sl_route('ActivityLogs')) ?>">Activity log</a></div>
  <div class="col">
  <?php foreach ($d['audit'] as $a): ?><div class="dvx-row"><span class="sl-av sm" aria-hidden="true"><?= h($a['ini']) ?></span><span class="grow dvx-min"><b class="dvx-ell"><?= h($a['who']) ?></b><small class="<?= $a['bad'] ? 'dvx-bad' : '' ?>"><?= h($a['what']) ?></small></span><small title="<?= h($a['at']) ?>"><?= h($a['when']) ?></small></div><?php endforeach; ?>
  <?php if (!$d['audit']): ?><div class="dvx-empty">No access changes yet.</div><?php endif; ?>
  </div>
  <details class="da-rec"><summary>Recovery when the unlock code is lost</summary><p class="dvx-cs">On the server, in the application folder: <span class="mono">php scripts/dev_unlock.php</span>. It shows who locked the console and when, asks you to type UNLOCK, removes the lock, clears the failed-attempt counters and records it in the activity log. If the owner's account was demoted, deactivated or deleted, <span class="mono">php scripts/dev_unlock.php --owner &lt;user id&gt;</span> makes another active super admin the owner. Both only run from the command line.</p></details>
</section>
</div>
</div>
<?= sl_dvxSubnavEnd() ?>
<script>
(function () {
  // Keep the "Who sees what" matrix in step with the switches below it (no reload needed).
  function sync(sw) {
    var key = sw.getAttribute('data-da-sw'), dot = document.querySelector('[data-da-dot="' + key + '"]');
    if (!dot) return;
    var on = sw.classList.contains('on');
    dot.classList.toggle('on', on);
    var lbl = dot.getAttribute('aria-label') || '';
    dot.setAttribute('aria-label', lbl.replace(/: (not )?granted$/, on ? ': granted' : ': not granted'));
    var uid = key.split('-')[0], n = document.querySelectorAll('[data-da-dot^="' + uid + '-"].on').length, small = document.querySelector('[data-da-n="' + uid + '"]');
    if (small && small.textContent !== 'Owner') small.textContent = n + ' sections';
    var card = sw.closest('.da-sec');
    if (card) card.classList.toggle('on', on);
  }
  var mo = new MutationObserver(function (list) { list.forEach(function (m) { sync(m.target); }); });
  document.querySelectorAll('[data-da-sw]').forEach(function (sw) { mo.observe(sw, { attributes: true, attributeFilter: ['class'] }); });
  // Phone: the developer sub-navigation is a horizontal strip; bring the current section into view.
  var act = document.querySelector('.dvx-sub [aria-current="page"]');
  for (var c = act && act.parentElement; c && c !== document.body; c = c.parentElement) {
    if (c.scrollWidth > c.clientWidth + 4 && getComputedStyle(c).overflowX !== 'visible') {
      var r = act.getBoundingClientRect(), cr = c.getBoundingClientRect();
      c.scrollLeft += (r.left - cr.left) - (cr.width - r.width) / 2;
      break;
    }
  }
})();
</script>
