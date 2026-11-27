<?php
/** M-DevAccess: who may open which developer section; unlock screen while the console is locked. Data: dev_access.php (same as the web DevAccess). Writes: ajax/sl/dev_access_ajax.php (existing ops). */
require_once CDP_ROOT . '/views/sl/data/dev_phone.php';
$ajax = 'ajax/sl/dev_access_ajax.php';
if (($d['mode'] ?? '') === 'locked'):
    $slPhone['nav'] = 'none';
    $slPhone['cls'] = 'dk';
?>
<?= sl_phoneTop('Developer console', 'Locked', ['back' => 'menu.php']) ?>
<main class="mx-body">
<div class="mx-ban warn"><i class="ic ic-sm i-lock" aria-hidden="true"></i><span>Locked by <?= h($d['by']) ?> <?= h($d['ago']) ?>. Every developer page is off until the unlock code is entered.</span></div>
<?php if ($d['note'] !== ''): ?><div class="mx-ban info"><i class="ic ic-sm i-info" aria-hidden="true"></i><span><?= h($d['note']) ?></span></div><?php endif; ?>
<form class="mx-card mx-form" method="post" action="<?= h($ajax) ?>" data-sl-form autocomplete="off"><input type="hidden" name="op" value="unlock">
<label class="mx-f"><small>Unlock code</small><input class="mx-in" type="password" name="unlock_secret" required minlength="8" maxlength="128" autocomplete="off"<?= $d['wait'] ? ' disabled' : '' ?>></label>
<small class="mx-fh"><?= $d['wait'] ? 'Too many wrong codes. Try again in ' . max(1, (int) ceil($d['wait'] / 60)) . ' min.' : (int) $d['left'] . ' attempts left in this 15-minute window. Every attempt is recorded.' ?></small>
<button type="submit" class="mx-btn"<?= $d['wait'] ? ' disabled' : '' ?>>Unlock the console</button></form>
<?php if ($d['attempts']): ?>
<?= sl_mxSec('Recent attempts') ?>
<section class="mx-card mx-list"><?php foreach ($d['attempts'] as $a): ?><?= sl_mxRow($a['ok'] ? 'check' : 'alert', $a['who'], ($a['ok'] ? 'Unlocked' : 'Wrong code') . ' · ' . $a['ip'] . ' · ' . $a['when']) ?><?php endforeach; ?></section>
<?php endif; ?>
</main>
<?php return; endif;
$slPhone['nav'] = 'dev';
$slPhone['tab'] = 'Console';
$total = (int) ($d['total'] ?? 0);
?>
<?= sl_phoneTop('Developer access', 'Owner: ' . ($d['owner'] ?? '—'), ['back' => sl_route('DevOverview')], sl_phDevMore()) ?>
<main class="mx-body">
<?php if (!empty($d['canAccess'])): ?>
<section class="mx-card mx-list">
<?php foreach ($d['rows'] as $r):
    $full = $r['owner'] || $r['n'] >= $total;
    $pill = !$r['assigned'] ? ['plain', 'Not added'] : ($r['owner'] ? ['delivered', 'Owner'] : ($full ? ['delivered', 'Full'] : ['pending', 'Limited']));
    $sub = $r['assigned'] ? ($r['owner'] ? 'Every section' : $r['n'] . ' of ' . $total . ' sections') . ($r['since'] !== '' ? ' · since ' . $r['since'] : '') : 'Super admin, not on the console';
?>
<details class="ph-da"<?= isset($_GET['u']) && (int) $_GET['u'] === (int) $r['id'] ? ' open' : '' ?>><summary class="mx-row" style="list-style:none;cursor:pointer"><span class="mx-ava"><?= h($r['ini']) ?></span><span class="mx-rm"><b><?= h($r['name'] . ($r['me'] ? ' (you)' : '')) ?></b><small><?= h($sub) ?></small></span><span class="mx-rr"><span class="sl-pill <?= h($pill[0]) ?>"><?= h($pill[1]) ?></span></span></summary>
<div style="padding:0 14px 12px">
<?php if (!$r['assigned']): ?>
<div class="mx-btns"><button type="button" class="mx-btn" data-sl-action="<?= h($ajax) ?>" data-p-op="assign" data-p-user_id="<?= (int) $r['id'] ?>" data-p-value="1" data-sl-reload data-sl-confirm="Give <?= h($r['name']) ?> access to the developer console? They start with the Overview section only.">Add to console</button></div>
<?php else: ?>
<div class="mx-tgs">
<?php foreach ($r['cells'] as $c): $label = $d['sections'][$c['k']][0] ?? $c['k']; ?>
<div class="mx-tg"><span class="mx-rm"><b><?= h($label) ?></b><?php if ($c['lock'] !== ''): ?><small><?= h($c['lock']) ?></small><?php elseif (in_array($c['k'], $d['ownerOnly'] ?? [], true)): ?><small>Only the owner grants this</small><?php endif; ?></span><button type="button" class="mx-sw<?= $c['on'] ? ' on' : '' ?>" role="switch" aria-checked="<?= $c['on'] ? 'true' : 'false' ?>" aria-label="<?= h($label . ' for ' . $r['name']) ?>"<?= $c['lock'] !== '' ? ' disabled aria-disabled="true"' : ' data-sl-save="' . h($ajax) . '" data-key="' . h($c['k']) . '" data-p-op="grant" data-p-user_id="' . (int) $r['id'] . '"' ?>></button></div>
<?php endforeach; ?>
</div>
<?php if (!$r['owner'] && !$r['me']): ?>
<div class="mx-btns" style="margin-top:10px"><button type="button" class="mx-btn sec" data-sl-action="<?= h($ajax) ?>" data-p-op="preset" data-p-preset="observe" data-p-user_id="<?= (int) $r['id'] ?>" data-sl-reload>Observe only</button><button type="button" class="mx-btn sec" data-sl-action="<?= h($ajax) ?>" data-p-op="assign" data-p-user_id="<?= (int) $r['id'] ?>" data-p-value="0" data-sl-reload data-sl-confirm="Remove <?= h($r['name']) ?> from the developer console? All their sections are revoked.">Remove from console</button></div>
<?php endif; ?>
<?php endif; ?>
</div>
</details>
<?php endforeach; ?>
<?php if (empty($d['rows'])): ?><?= sl_mxEmpty('users', 'No super admin found') ?><?php endif; ?>
</section>
<?php else: ?>
<div class="mx-ban info"><i class="ic ic-sm i-info" aria-hidden="true"></i><span>You hold the Shutdown section but not Developer access, so you cannot see who has which section.</span></div>
<?php endif; ?>
<?= sl_mxSec('Access audit', 'Activity log', sl_route('ActivityLogs')) ?>
<section class="mx-card mx-list">
<?php foreach (array_slice($d['audit'] ?? [], 0, 6) as $a): ?><div class="mx-row"><span class="mx-ava"><?= h($a['ini']) ?></span><span class="mx-rm"><b><?= h($a['who']) ?></b><small<?= $a['bad'] ? ' style="color:var(--red-ink)"' : '' ?>><?= h($a['what'] . ' · ' . $a['when']) ?></small></span></div><?php endforeach; ?>
<?php if (empty($d['audit'])): ?><?= sl_mxEmpty('clock', 'No access changes yet') ?><?php endif; ?>
</section>
<?php if (!empty($d['canShutdown'])): ?>
<details class="mx-card" style="padding:14px"><summary style="cursor:pointer;font-weight:600;min-height:28px">Shut the developer console down</summary>
<form class="mx-form" style="padding:12px 0 0" method="post" action="<?= h($ajax) ?>" data-sl-form autocomplete="off"><input type="hidden" name="op" value="lock">
<small class="mx-fh">Every developer page and endpoint goes off for every user, you included, until someone enters the unlock code you set now.</small>
<label class="mx-f"><small>Unlock code (at least 8 characters)</small><input class="mx-in" type="password" name="unlock_secret" required minlength="8" maxlength="128" autocomplete="new-password"></label>
<label class="mx-f"><small>Repeat the unlock code</small><input class="mx-in" type="password" name="unlock_secret2" required minlength="8" maxlength="128" autocomplete="new-password"></label>
<label class="mx-f"><small>Your password</small><input class="mx-in" type="password" name="password" required autocomplete="current-password"></label>
<label class="mx-f"><small>Reason (optional)</small><input class="mx-in" type="text" name="note" maxlength="200"></label>
<label class="mx-tg" style="padding:0"><input type="checkbox" name="confirm" value="1" required style="width:22px;height:22px"><span class="mx-rm"><small>I understand the console stays off until someone enters this code; a lost code can only be cleared on the server.</small></span></label>
<button type="submit" class="mx-btn">Shut down and lock</button></form></details>
<?php endif; ?>
</main>
