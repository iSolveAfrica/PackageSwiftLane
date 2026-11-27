<?php
/**
 * M-LiveTrack (staff): riders on the road, flights and containers. $d is the web page's
 * (live_tracking.php: sl_trackConsoleData, ?f= and ?q=). Flights / Containers / Consolidations are the web page's
 * f=air|sea|con filters, drawn on the lane map from each record's own geometry. Riders (?tab=riders) come from
 * views/sl/data/riders.php sl_rdStats() for today, only for people who may open the riders list (view_user_list).
 * The design's turn-by-turn directions are left out: the system has no rider GPS positions.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['nav'] = 'staff';
$tc = $d['tc'];
$t = $tc['tally'];
$canRiders = $user->cdp_hasPermission('view_user_list');
$tab = ($_GET['tab'] ?? '') === 'riders' && $canRiders ? 'riders' : '';
if ($tab === '' && $canRiders && !isset($_GET['tab']) && !isset($_GET['f']) && !isset($_GET['q'])) $tab = 'riders';
$riders = [];
if ($canRiders) {
    require_once CDP_ROOT . '/views/sl/data/riders.php';
    foreach (sl_rdStats(date('Y-m-d'), date('Y-m-d')) as $rid => $s) {
        if (!$s['assigned'] || !(int) $s['rider']->active) continue;
        $riders[] = ['id' => $rid, 'name' => $s['rider']->name, 'zone' => (string) ($s['rider']->zone ?? ''), 'done' => $s['done'], 'n' => $s['assigned'], 'open' => $s['open'], 'failed' => $s['failed']];
    }
    usort($riders, fn($a, $b) => $b['open'] <=> $a['open']);
}
$onRoad = count(array_filter($riders, fn($r) => $r['open'] > 0));
$tabs = [];
if ($canRiders) $tabs[] = ['Riders ' . $onRoad, ['tab' => 'riders', 'f' => null, 'q' => null], $tab === 'riders'];
if (isset($t['air'])) $tabs[] = ['Flights ' . sl_num($t['air']), ['tab' => null, 'f' => 'air'], $tab === '' && $d['filter'] === 'air'];
if (isset($t['sea'])) $tabs[] = ['Containers ' . sl_num($t['sea']), ['tab' => null, 'f' => 'sea'], $tab === '' && $d['filter'] === 'sea'];
if (isset($t['air_con']) || isset($t['sea_con'])) $tabs[] = ['Consolidations ' . sl_num(($t['air_con'] ?? 0) + ($t['sea_con'] ?? 0)), ['tab' => null, 'f' => 'con'], $tab === '' && $d['filter'] === 'con'];
$tabs[] = ['Everything', ['tab' => $canRiders ? 'all' : null, 'f' => null], $tab === '' && $d['filter'] === 'all'];
$items = $tc['items'];
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-wh.css')) ?>">
<?= sl_phoneTop('Live tracking', '', ['back' => 'menu.php'], $canRiders ? sl_phoneIcon('wheel', 'Dispatch board', sl_route('RiderDispatch')) : '') ?>
<main class="mx-body">
<nav class="mx-seg" aria-label="Show"><?php foreach ($tabs as [$l, $p, $on]): ?><a class="<?= $on ? 'on' : '' ?>" href="<?= h(sl_url($p)) ?>"<?= $on ? ' aria-current="page"' : '' ?>><?= h($l) ?></a><?php endforeach; ?></nav>
<?php if ($tab === 'riders'): ?>
<?php if ($riders): ?>
<section class="mx-card mx-list">
<?php foreach ($riders as $r): ?>
<a class="mx-row" href="<?= h(sl_route('RiderDetail', ['id' => $r['id']])) ?>"><span class="mx-ava"><?= h(sl_initials($r['name'])) ?></span><span class="mx-rm"><b><?= h($r['name']) ?><?= $r['zone'] !== '' ? ' · ' . h($r['zone']) : '' ?></b><small><?= (int) $r['done'] ?> of <?= (int) $r['n'] ?> done today<?= $r['failed'] ? ' · ' . (int) $r['failed'] . ' failed' : '' ?></small></span><span class="mx-rr"><span class="sl-pill <?= $r['open'] ? 'transit' : 'delivered' ?>"><?= $r['open'] ? 'On route' : 'Done' ?></span></span></a>
<?php endforeach; ?>
</section>
<?php else: ?>
<?= sl_mxEmpty('scooter', 'No riders out today', 'Riders with jobs today show here.', 'Open the dispatch board', sl_route('RiderDispatch')) ?>
<?php endif; ?>
<?php else: ?>
<form method="get" class="mx-srch" role="search"><?php if ($d['filter'] !== 'all'): ?><input type="hidden" name="f" value="<?= h($d['filter']) ?>"><?php endif; ?><?php if ($canRiders): ?><input type="hidden" name="tab" value="all"><?php endif; ?><label><i class="ic i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($d['q']) ?>" aria-label="Search all tracking" placeholder="Tracking, customer, locker, container…" enterkeyhint="search"></label></form>
<?php if ($items): $moving = count(array_filter($items, fn($i) => !$i['arrived'])); ?>
<section class="mx-map" style="--mh:240px" aria-label="Map of <?= $moving ?> records on their way">
<span class="mx-ml"><?= $moving ?> on the way · <?= count($items) - $moving ?> arrived</span>
<div class="wh-lane"><svg viewBox="250 70 440 300" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
<?php $lanes = []; foreach ($items as $it) $lanes[$it['geo']['path']] = 1; foreach (array_keys($lanes) as $p): ?><path d="<?= h($p) ?>" fill="none" stroke="var(--line-2)" stroke-width="3" stroke-dasharray="6 6"/><?php endforeach; ?>
<?php foreach (array_slice($items, 0, 40) as $it): [$vx, $vy] = $it['geo']['veh']; ?><circle cx="<?= h($vx) ?>" cy="<?= h($vy) ?>" r="7" fill="<?= $it['late'] ? '#E5484D' : ($it['arrived'] ? '#0EAD78' : '#D9792B') ?>" stroke="#fff" stroke-width="2"/><?php endforeach; ?>
<?php $seen = []; foreach ($items as $it) foreach (['from', 'to'] as $e) { $k = $it[$e]['code']; if (isset($seen[$k])) continue; $seen[$k] = 1; [$x, $y] = sl_tcXY($it[$e]['lat'], $it[$e]['lon']); ?><circle cx="<?= $x ?>" cy="<?= $y ?>" r="5" fill="var(--ink)"/><text x="<?= $x + 9 ?>" y="<?= $y + 4 ?>" font-size="15" font-weight="600" fill="var(--ink)"><?= h($k) ?></text><?php } ?>
</svg></div>
</section>
<section class="mx-card mx-list">
<?php foreach ($items as $it):
    $ref = $it['mode'] === 'air' ? ($it['flight'] !== '' ? $it['flight'] : ($it['awb'] !== '' ? 'AWB ' . $it['awb'] : '')) : (!empty($it['freight']['container']) ? $it['freight']['container'] : '');
?>
<a class="mx-row" href="<?= h($it['href']) ?>"><span class="mx-li"><i class="ic i-<?= $it['kind'] === 'consolidation' ? 'layers' : ($it['mode'] === 'sea' ? 'ship' : 'plane') ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($ref !== '' ? $ref . ' · ' : '') ?><?= h($it['code']) ?> · <?= h($it['from']['code']) ?> → <?= h($it['to']['code']) ?></b><small><?= h($it['customer']) ?> · <?= $it['arrived'] ? 'in ' . h($it['to']['city']) : ($it['eta'] !== 'Not set' ? 'ETA ' . h($it['eta']) : h($it['where'])) ?></small></span><span class="mx-rr"><span class="sl-pill <?= $it['estCls'] === 'bad' ? 'overdue' : ($it['estCls'] === 'ok' ? 'delivered' : 'pending') ?>"><?= h($it['estimate']) ?></span></span></a>
<?php endforeach; ?>
</section>
<?php if ($tc['shown'] < $tc['total']): ?><p class="mx-note">Newest <?= sl_num($tc['shown']) ?> of <?= sl_num($tc['total']) ?>. Search to narrow.</p><?php endif; ?>
<?php else: ?>
<?= sl_mxEmpty('route', $d['q'] !== '' ? 'Nothing matches' : 'Nothing moving here', $d['q'] !== '' ? 'Try a tracking number, customer or container.' : '') ?>
<?php endif; ?>
<?php endif; ?>
</main>
