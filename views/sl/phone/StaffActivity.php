<?php
/**
 * M-StaffAct: one staff member's day on a phone (staff_activity.php). Same $d as the web page
 * (views/sl/data/staff_activity.php sl_staffActivityData): day tabs (day), person (id), every action.
 */
require_once CDP_ROOT . '/views/sl/data/phone_people.php';
$slPhone['nav'] = 'staff';
$r = $d['dayRow'] ?? null;
$cur = $d['cur'] ?? null;
$pill = ['create' => ['New', 'delivered'], 'update' => ['Changed', 'pending'], 'delete' => ['Deleted', 'overdue']];
$acts = $user->cdp_hasPermission('view_staff_productivity') ? sl_phoneIcon('chart', 'Productivity report', sl_route('StaffProductivity')) : '';
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-people.css')) ?>">
<?= sl_phoneTop('Staff activity', $cur ? $cur['name'] . ' · ' . ($d['dayLabel'] ?? '') : '', ['back' => 'menu.php'], $acts) ?>
<main class="mx-body">
<nav class="mx-seg" aria-label="Day">
<?php foreach ($d['days'] as $x): ?>
  <a class="<?= $x['day'] === $d['day'] ? 'on' : '' ?>" href="<?= h(sl_url(['day' => $x['day'], 'seg' => null])) ?>"<?= $x['day'] === $d['day'] ? ' aria-current="true"' : '' ?>><?= h($x['label']) ?></a>
<?php endforeach; ?>
</nav>
<?php if (count($d['people']) > 1): ?>
<form method="get" class="mx-card mx-form">
  <div class="mx-two">
    <label class="mx-f"><small>Staff member</small><select class="mx-in" name="id" onchange="this.form.submit()"><?php foreach ($d['people'] as $p): ?><option value="<?= (int) $p['id'] ?>"<?= $p['id'] === $d['id'] ? ' selected' : '' ?>><?= h($p['name']) ?></option><?php endforeach; ?></select></label>
    <label class="mx-f"><small>Day</small><input class="mx-in" type="date" name="day" value="<?= h($d['day']) ?>" max="<?= date('Y-m-d') ?>" onchange="this.form.submit()"></label>
  </div>
  <noscript><button type="submit" class="mx-btn">Show</button></noscript>
</form>
<?php endif; ?>
<?php if (!$cur): ?>
<?= sl_mxEmpty('users', 'No staff you can view') ?>
<?php else: $n = $d['now']; ?>
<div class="mx-ban <?= $n['state'] === 'Online' ? 'ok' : 'info' ?>"><i class="ic ic-sm i-activity" aria-hidden="true"></i><span><b><?= h($n['state']) ?></b> · last on <?= h($n['page']) ?> · <?= h($n['ago']) ?> · <?= h($n['device']) ?> · <?= (int) $n['today'] ?> actions today</span></div>
<?php if ($r): ?>
<section class="mx-card mx-kv">
  <div><span>Checked in</span><b><?= h($r['checkIn']) ?> · <?= h($r['checkWhy']) ?></b></div>
  <div><span>Checked out</span><b><?= h($r['checkOut']) ?></b></div>
  <div><span>Active</span><b><?= h($r['active']) ?> (<?= h($r['activePct']) ?>)</b></div>
  <div><span>Idle</span><b><?= h($r['idle']) ?></b></div>
  <div><span>Actions</span><b><?= (int) $r['actions'] ?> · <?= h($r['pkgs']) ?></b></div>
</section>
<?php if ($r['acts']): ?>
<section class="mx-card mx-list">
<?php foreach ($r['acts'] as $a): $p = $pill[$a['verb']] ?? null; ?>
  <<?= $a['ref'] !== '' ? 'a href="' . h($a['href']) . '"' : 'div' ?> class="mx-row"><span class="mx-ava"><?= h($cur['ini']) ?></span><span class="mx-rm"><b><?= h($a['label']) ?></b><small><?= h($a['verbL']) ?><?= $a['ref'] !== '' ? ' · ' . h($a['ref']) : '' ?></small></span><span class="mx-rr"><?php if ($p): ?><span class="sl-pill <?= $p[1] ?>"><?= $p[0] ?></span><?php endif; ?><small><?= h($a['at']) ?></small></span></<?= $a['ref'] !== '' ? 'a' : 'div' ?>>
<?php endforeach; ?>
</section>
<?php else: ?>
<?= sl_mxEmpty('clock', 'No actions recorded', 'Nothing was created or changed on this day.') ?>
<?php endif; ?>
<?php else: ?>
<?= sl_mxEmpty('clock', 'No recorded activity on this day', $cur['name'] . ' did not check in.') ?>
<?php endif; ?>
<?php endif; ?>
</main>
