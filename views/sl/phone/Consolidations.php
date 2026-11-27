<?php
/**
 * M-Consol: air loads and sea containers. Same $d as the web page (views/sl/data/consolidations.php
 * sl_consolidationsBoard). Tabs are links to the same page with the parameters the web page reads (mode, stage).
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['nav'] = 'staff';
$slPhone['tab'] = 'Shipments';
$tc = $d['tracking'];
$t = $tc['tally'] ?? [];
$modes = $d['modes'];
$new = '';
foreach ($modes as $m) { if ($m['new'] !== '') { $new = $m['new']; break; } }
$tabs = [];
if (isset($modes['air'])) $tabs[] = ['Air ' . sl_num($t['air_con'] ?? 0), ['mode' => 'air', 'stage' => null], $d['mode'] === 'air' && $d['stage'] === 'all'];
if (isset($modes['sea'])) $tabs[] = ['Sea ' . sl_num($t['sea_con'] ?? 0), ['mode' => 'sea', 'stage' => null], $d['mode'] === 'sea' && $d['stage'] === 'all'];
$tabs[] = ['Open', ['mode' => null, 'stage' => null], $d['mode'] === '' && $d['stage'] === 'all'];
$tabs[] = ['Closed', ['mode' => null, 'stage' => 'done'], $d['stage'] === 'done'];
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-wh.css')) ?>">
<?= sl_phoneTop('Consolidations', '', ['back' => 'menu.php'], sl_phoneIcon('route', 'Live tracking of consolidations', sl_route('LiveTracking', ['f' => 'con']))) ?>
<main class="mx-body">
<form method="get" class="mx-srch" role="search"><?php if ($d['mode'] !== ''): ?><input type="hidden" name="mode" value="<?= h($d['mode']) ?>"><?php endif; ?><?php if ($d['stage'] !== 'all'): ?><input type="hidden" name="stage" value="<?= h($d['stage']) ?>"><?php endif; ?><label><i class="ic i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($d['q']) ?>" aria-label="Search consolidations" placeholder="Code, customer or parcel inside" enterkeyhint="search"></label></form>
<?php if ($modes): ?>
<nav class="mx-seg" aria-label="Show"><?php foreach ($tabs as [$l, $p, $on]): ?><a class="<?= $on ? 'on' : '' ?>" href="<?= h(sl_url($p + ['q' => null])) ?>"<?= $on ? ' aria-current="page"' : '' ?>><?= h($l) ?></a><?php endforeach; ?></nav>
<?php endif; ?>
<?php if ($tc['items']): ?>
<section class="mx-card mx-list">
<?php foreach ($tc['items'] as $it):
    $ref = $it['mode'] === 'air' ? ($it['awb'] !== '' ? 'AWB ' . $it['awb'] : ($it['flight'] !== '' ? 'Flight ' . $it['flight'] : '')) : (!empty($it['freight']['container']) ? $it['freight']['container'] : (!empty($it['freight']['waybill']) ? 'B/L ' . $it['freight']['waybill'] : ''));
?>
<a class="mx-row" href="<?= h($it['href']) ?>"><span class="mx-rm"><b><?= h($it['code']) ?> · <?= h($it['from']['code']) ?> → <?= h($it['to']['code']) ?></b><small><?= h($it['customer']) ?> · <?= h($it['weight']) ?></small><?php if ($ref !== ''): ?><small class="mono"><?= h($ref) ?></small><?php endif; ?></span><span class="mx-rr"><span class="sl-pill <?= h($it['late'] ? 'overdue' : $it['cls']) ?>"><?= h($it['late'] ? 'Past ETA' : $it['st']) ?></span></span></a>
<?php endforeach; ?>
</section>
<?php if ($tc['shown'] < $tc['total']): ?><p class="mx-note">Newest <?= sl_num($tc['shown']) ?> of <?= sl_num($tc['total']) ?>. Search to narrow.</p><?php endif; ?>
<?php elseif (!$modes): ?>
<?= sl_mxEmpty('layers', 'No access', 'You do not have access to air or sea consolidations.') ?>
<?php else: ?>
<?= sl_mxEmpty('layers', $d['q'] !== '' || $d['mode'] !== '' || $d['stage'] !== 'all' ? 'No consolidations in this view' : 'No open consolidations', $new !== '' ? 'Start one with the + button.' : '') ?>
<?php endif; ?>
</main>
<?php if ($new !== ''): ?><a class="mx-fab" href="<?= h($new) ?>" aria-label="New consolidation"><i class="ic i-plus" aria-hidden="true"></i></a><?php endif; ?>
