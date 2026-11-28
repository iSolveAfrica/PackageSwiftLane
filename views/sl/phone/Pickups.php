<?php
/**
 * M-Pickups: home pickups. Same $d as the web page (views/sl/data/pickups.php sl_pickupsData); tabs use the
 * web page's filter / when parameters; assigning a rider posts to ajax/sl/pickup_ajax.php (op=driver, id, driver_id)
 * like the web dialog. The design's live map is left out: pickups carry no coordinates.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['nav'] = sl_userIs('client') ? 'customer' : 'staff';
$can = $d['can'];
$rows = $d['rows'];
$c0 = $d['cards'][0] ?? null; $c1 = $d['cards'][1] ?? null; $c3 = $d['cards'][3] ?? null;
$open = array_values(array_filter($rows, fn($r) => in_array($r['stage'], ['requested', 'scheduled'], true)));
$next = $open[0] ?? null;
$unassigned = array_values(array_filter($open, fn($r) => $r['drv'] === ''));
$assignFor = $unassigned[0] ?? $next;
$tabs = [['In progress', ['filter' => 'active', 'when' => null]], ['Today', ['filter' => 'active', 'when' => 'today']], ['Tomorrow', ['filter' => 'active', 'when' => 'tomorrow']], ['Requested', ['filter' => 'requested', 'when' => null]], ['Not collected', ['filter' => 'issues', 'when' => null]]];
$digits = fn($p) => preg_replace('/\D/', '', (string) $p);
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-wh.css')) ?>">
<?= sl_phoneTop($d['isClient'] ? 'My pickups' : 'Home pickups', '', ['back' => 'menu.php'], $d['canAdd'] ? sl_phoneIcon('plus', $d['isClient'] ? 'Request a pickup' : 'Schedule a pickup', $d['isClient'] ? 'pickup_add.php' : sl_route('SchedulePickup')) : '') ?>
<main class="mx-body">
<?php if (!$d['isClient'] && $c0): ?>
<section class="mx-k c3">
<div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-truck" aria-hidden="true"></i></span><small>Today</small></div><b><?= h($c0['big']) ?></b><em><?= h(explode(' · ', $c0['lede'])[0]) ?></em><?php if ($c0['bars']): ?><div class="mx-sp" aria-hidden="true"><?php $nb = count($c0['bars']); foreach ($c0['bars'] as $i => $b): ?><i class="<?= $i === $nb - 1 ? 'now' : '' ?>" style="<?= h($b['s']) ?>"></i><?php endforeach; ?></div><?php endif; ?></div>
<div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-clock" aria-hidden="true"></i></span><small>To collect</small></div><b><?= h($c1['big'] ?? '—') ?></b><em class="<?= !empty($c1['alert']) ? 'warn' : '' ?>"><?= h(($c1['alert'] ?? '') ?: 'avg. wait') ?></em><?php if (!empty($c1['bars'])): ?><div class="mx-sp" aria-hidden="true"><?php $nb = count($c1['bars']); foreach ($c1['bars'] as $i => $b): ?><i class="<?= $i === $nb - 1 ? 'now' : '' ?>" style="<?= h($b['s']) ?>"></i><?php endforeach; ?></div><?php endif; ?></div>
<div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-check" aria-hidden="true"></i></span><small>This month</small></div><b><?= h($c3['big'] ?? '—') ?></b><em class="up"><?= (int) ($c3['meter'] ?? 0) ?>% at warehouse</em></div>
</section>
<?php endif; ?>
<nav class="mx-seg" aria-label="Show"><?php foreach ($tabs as [$l, $p]): $on = $d['filter'] === $p['filter'] && $d['when'] === ($p['when'] ?? 'all'); ?><a class="<?= $on ? 'on' : '' ?>" href="<?= h(sl_url($p + ['page' => null])) ?>"<?= $on ? ' aria-current="page"' : '' ?>><?= h($l) ?></a><?php endforeach; ?></nav>
<?php if (!$d['isClient']): ?>
<?= sl_mxSec('Riders on pickups today', $can['driver'] ? 'Dispatch' : '', sl_route('RiderDispatch')) ?>
<?php if ($d['drivers']): ?>
<div class="mx-pc mx-rs"><?php foreach ($d['drivers'] as $r): ?><a class="mx-rsc" href="<?= h(sl_url(['q' => null, 'filter' => 'all', 'when' => 'today'])) ?>"><div class="mx-rsh"><span class="mx-ava"><?= h(sl_initials($r['name'])) ?></span><span class="mx-rm"><b><?= h($r['name']) ?></b><small><?= h($r['txt']) ?></small></span><span class="sl-pill <?= $r['pct'] >= 100 ? 'delivered' : 'transit' ?>"><?= $r['pct'] >= 100 ? 'Done' : 'On route' ?></span></div><span class="mx-trk"><i class="or" style="width:<?= (int) $r['pct'] ?>%"></i></span></a><?php endforeach; ?></div>
<?php else: ?><?= sl_mxEmpty('wheel', 'No rider has pickups today', $can['driver'] ? 'Assign one below.' : '') ?><?php endif; ?>
<?php endif; ?>
<?php if ($next): ?>
<?= sl_mxSec('Next pickup') ?>
<section class="mx-card mx-np"><div class="mx-rch"><span class="mx-ava"><?= h($next['ini']) ?></span><span class="mx-rm"><b><?= h($next['name']) ?><?= $next['area'] !== '' ? ' · ' . h($next['area']) : '' ?></b><small><?= h($next['trk']) ?> · <?= $next['drv'] !== '' ? h($next['drv']) : 'no rider yet' ?></small></span><span class="sl-pill <?= $next['late'] ? 'overdue' : h($next['cls']) ?>"><?= $next['late'] ? 'Late' : h($next['st']) ?></span></div>
<div class="mx-rcb"><div><small>Window</small><span><?= h($next['day'] . ' · ' . $next['win']) ?></span></div><div><small>Items</small><span><?= (int) $next['items'] ?> item<?= $next['items'] === 1 ? '' : 's' ?> · <?= h($next['kg']) ?></span></div><?php if ($d['money']): ?><div><small>Fee</small><span><?= h($next['fee']) ?></span></div><?php endif; ?><div><small>Address</small><span><?= h($next['addr']) ?></span></div></div></section>
<section class="mx-act c4">
<?php if ($next['phone'] !== ''): ?><a href="tel:<?= h($digits($next['phone'])) ?>"><span><i class="ic i-phone" aria-hidden="true"></i></span><small>Call</small></a><a href="https://wa.me/<?= h($digits($next['phone'])) ?>" target="_blank" rel="noopener"><span><i class="ic i-message" aria-hidden="true"></i></span><small>WhatsApp</small></a><?php endif; ?>
<?php if ($next['addr'] !== '—'): ?><a href="https://www.google.com/maps/dir/?api=1&amp;destination=<?= h(rawurlencode($next['addr'] . ($next['area'] !== '' ? ', ' . $next['area'] : ''))) ?>" data-maps-dest="<?= h($next['addr'] . ($next['area'] !== '' ? ', ' . $next['area'] : '')) ?>" target="_blank" rel="noopener"><span><i class="ic i-route" aria-hidden="true"></i></span><small>Navigate</small></a><?php endif; ?>
<a href="courier_view.php?id=<?= (int) $next['id'] ?>"><span><i class="ic i-eye" aria-hidden="true"></i></span><small>Open</small></a>
</section>
<?php endif; ?>
<?= sl_mxSec($d['when'] === 'tomorrow' ? 'Tomorrow, by time' : ($d['when'] === 'today' ? 'Today, by time' : 'By time'), $d['count'] > count($rows) ? sl_num($d['count']) . ' in all' : '') ?>
<?php if ($rows): $cur = false; ?>
<section class="mx-card"><ol class="mx-tl">
<?php foreach ($rows as $r):
    $cls = $r['late'] || $r['stage'] === 'issues' ? 'bad' : (!$r['open'] || $r['stage'] === 'collected' ? 'done' : (!$cur ? 'cur' : ''));
    if ($cls === 'cur') $cur = true;
    $ic = $cls === 'bad' ? 'alert' : ($cls === 'done' ? 'check' : 'truck');
?>
<li class="<?= $cls ?>"><span class="mx-td"><i class="ic ic-sm i-<?= $ic ?>" aria-hidden="true"></i></span><div><b><a href="courier_view.php?id=<?= (int) $r['id'] ?>" style="color:inherit"><?= h($r['name']) ?><?= $r['area'] !== '' ? ' · ' . h($r['area']) : '' ?></a></b><small><?= h($r['day'] . ' ' . $r['win']) ?> · <?= h($r['st']) ?> · <?= $r['drv'] !== '' ? h($r['drv']) : ($r['open'] ? 'no rider yet' : '—') ?></small></div></li>
<?php endforeach; ?>
</ol></section>
<?php if ($d['count'] > $d['per']): $pages = (int) ceil($d['count'] / $d['per']); ?><nav class="wh-pg" aria-label="Pages"><?php if ($d['page'] > 1): ?><a href="<?= h(sl_url(['page' => $d['page'] - 1])) ?>">Previous</a><?php else: ?><span></span><?php endif; ?><span>Page <?= (int) $d['page'] ?> of <?= $pages ?></span><?php if ($d['page'] < $pages): ?><a href="<?= h(sl_url(['page' => $d['page'] + 1])) ?>">Next</a><?php else: ?><span></span><?php endif; ?></nav><?php endif; ?>
<?php else: ?>
<?= sl_mxEmpty('truck', 'No pickups here', $d['isClient'] ? 'Request one and a rider will collect from your home.' : '') ?>
<?php endif; ?>
<?php if ($assignFor && $can['driver'] && $d['driverList']): ?>
<form class="mx-card mx-form" data-sl-form data-sl-reload action="ajax/sl/pickup_ajax.php" method="post" id="pk-assign"><input type="hidden" name="op" value="driver"><input type="hidden" name="id" value="<?= (int) $assignFor['id'] ?>">
<label class="mx-f"><small><?= $assignFor['drv'] !== '' ? 'Change the rider for' : 'Assign a rider to' ?> <?= h($assignFor['name']) ?> · <?= h($assignFor['trk']) ?></small><span class="wh-selw"><select class="mx-in" name="driver_id" required><option value="">Choose a rider</option><?php foreach ($d['driverList'] as $dr): ?><option value="<?= (int) $dr->id ?>"><?= h(trim($dr->fname . ' ' . $dr->lname)) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
<button class="mx-btn" type="submit">Assign <?= h(explode(' ', $assignFor['name'])[0]) ?>’s pickup</button>
</form>
<?php endif; ?>
<?php if ($d['canAdd']): ?><div class="mx-btns"><a class="mx-btn sec" href="<?= h($d['isClient'] ? 'pickup_add.php' : sl_route('SchedulePickup')) ?>"><?= $d['isClient'] ? 'Request a pickup' : 'Book a new pickup' ?></a></div><?php endif; ?>
</main>
