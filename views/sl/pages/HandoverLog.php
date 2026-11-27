<?php
/**
 * Hand-out Log (Warehouse menu). Data: views/sl/data/handover_log.php (sl_handoverLogData), source cdb_warehouse_handovers.
 * Period presets or a custom range, filters by staff, code method and mode; staff table with today / week / month totals;
 * every hand-over with who did it and whether the pickup code was checked or skipped (and why). CSV export.
 */
if (!isset($d)) { $d = []; }
$keep = fn(array $x) => h(sl_url($x + ['page' => null, 'export' => null]));
?>
<div class="sl-head"><div><h1 class="sl-title">Hand-out Log</h1><p class="sl-lede">Every package handed over at the warehouse · <?= h($d['rangeL']) ?><?= $d['all'] ? '' : ' · your hand-outs' ?></p></div>
  <div class="row g8 wrap"><a class="sl-btn ghost" href="<?= $keep(['export' => 'csv']) ?>"><i class="ic ic-sm i-download" aria-hidden="true"></i>Export CSV</a><a class="sl-btn" href="<?= sl_route('HandOut') ?>"><i class="ic ic-sm i-box" aria-hidden="true"></i>Hand out packages</a></div></div>
<?php if (!$d['ok']): ?><div class="sl-card"><div class="sl-empty">The hand-out log table could not be created. Run migrations/2026_11_21_warehouse_handover_log.sql.</div></div><?php else: ?>
<form method="get" class="sl-card row wrap g12" style="padding:14px 16px;align-items:end">
  <div class="sl-seg boxed"><?php foreach ($d['ranges'] as [$k, $l]): ?><a class="<?= $d['range'] === $k ? 'on' : '' ?>" href="<?= $keep(['range' => $k, 'from' => null, 'to' => null]) ?>"><?= h($l) ?></a><?php endforeach; ?></div>
  <input type="hidden" name="range" value="custom">
  <label class="sl-field" style="width:150px"><span class="sl-lab">From</span><input class="sl-in" type="date" name="from" value="<?= h($d['from']) ?>"></label>
  <label class="sl-field" style="width:150px"><span class="sl-lab">To</span><input class="sl-in" type="date" name="to" value="<?= h($d['to']) ?>" max="<?= date('Y-m-d') ?>"></label>
  <?php if ($d['all']): ?><label class="sl-field" style="width:190px"><span class="sl-lab">Staff</span><span class="sl-sel native"><select name="staff"><option value="">Everyone</option><?php foreach ($d['staffOpts'] as $o): ?><option value="<?= (int) $o['id'] ?>" <?= $o['id'] === $d['staff'] ? 'selected' : '' ?>><?= h($o['name']) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label><?php endif; ?>
  <label class="sl-field" style="width:160px"><span class="sl-lab">Pickup code</span><span class="sl-sel native"><select name="method"><option value="">Any</option><?php foreach (['code' => 'Code checked', 'skipped' => 'Code skipped', 'legacy' => 'Before the log'] as $k => $l): ?><option value="<?= $k ?>" <?= $d['method'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
  <label class="sl-field" style="width:120px"><span class="sl-lab">Mode</span><span class="sl-sel native"><select name="mode"><option value="">Air + sea</option><option value="air" <?= $d['mode'] === 'air' ? 'selected' : '' ?>>Air</option><option value="sea" <?= $d['mode'] === 'sea' ? 'selected' : '' ?>>Sea</option></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
  <div class="sl-search" style="flex:1;min-width:200px"><i class="ic ic-sm i-search" aria-hidden="true"></i><input name="q" value="<?= h($d['q']) ?>" aria-label="Search" placeholder="Tracking, customer or locker"></div>
  <button class="sl-btn" type="submit">Apply</button>
</form>
<div class="grid cols4">
  <?php foreach ($d['kpi'] as $m): ?><<?= !empty($m['f']) ? 'a href="' . $keep(['method' => $d['method'] === $m['f'] ? null : $m['f']]) . '"' : 'div' ?> class="sl-card sl-metric" style="color:inherit"><div class="sl-ml"><span><i class="ic ic-sm <?= h($m['icon']) ?>" aria-hidden="true"></i><?= h($m['l']) ?></span><?php if (!empty($m['warn'])): ?><span class="sl-pill overdue" style="height:22px;font-size:12.5px">Review</span><?php endif; ?></div><div class="sl-mv"><b class="fw5" style="font-size:24px"><?= h($m['v']) ?></b></div><span class="mut sm"><?= h($m['s']) ?></span></<?= !empty($m['f']) ? 'a' : 'div' ?>><?php endforeach; ?>
</div>
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(min(420px,100%),1fr));gap:16px">
  <section class="sl-card col g10"><h2 class="sl-ct">Hand-outs per day</h2>
    <div class="hl-bars" role="img" aria-label="Hand-outs per day"><?php $few = count($d['days']) <= 16; foreach ($d['days'] as $x): ?><div class="hl-bar" title="<?= h($x['tip']) ?>"><span class="mono"><?= $few && $x['n'] ? $x['n'] : '' ?></span><i style="height:<?= max(2, $x['h']) ?>%"></i><small><?= h($x['l']) ?></small></div><?php endforeach; ?></div></section>
  <section class="sl-card col g10"><h2 class="sl-ct">Busy hours</h2>
    <div class="hl-bars" role="img" aria-label="Hand-outs per hour"><?php foreach ($d['hours'] as $x): ?><div class="hl-bar" title="<?= h($x['l']) ?>:00 · <?= (int) $x['n'] ?> handed out"><span class="mono"><?= $x['n'] ?: '' ?></span><i style="height:<?= max(2, $x['h']) ?>%"></i><small><?= h($x['l']) ?></small></div><?php endforeach; ?></div></section>
</div>
<section class="sl-card flush"><div class="row between wrap g8" style="padding:14px 16px"><h2 class="sl-ct" style="margin:0">Who handed out · <?= h($d['rangeL']) ?></h2><span class="mut sm">Click a name to see only their hand-outs</span></div>
  <div class="sl-tw"><table class="sl-table"><thead><tr><th>Staff member</th><th style="min-width:160px">Packages (period)</th><th>Customers</th><th>Per day</th><th>Today</th><th>This week</th><th>This month</th><th>Code skipped</th><th>Unpaid</th><th>Last hand-out</th></tr></thead><tbody>
  <?php foreach ($d['staffRows'] as $s): ?><tr<?= $s['on'] ? ' style="background:var(--active)"' : '' ?>><td><a class="row g8" style="color:inherit;align-items:center" href="<?= $keep(['staff' => $s['on'] ? null : $s['id']]) ?>"><span class="sl-av"><?= h($s['ini']) ?></span><b class="fw5"><?= h($s['name']) ?></b></a></td><td><div class="col g4"><span class="fw5"><?= sl_num($s['n']) ?> <span class="mut sm">· <?= (int) $s['share'] ?>%</span></span><div class="sl-ticks"><i style="width:<?= (int) $s['bar'] ?>%"></i></div></div></td><td><?= sl_num($s['cust']) ?></td><td class="mono"><?= h($s['perDay']) ?></td><td class="mono"><?= sl_num($s['today']) ?></td><td class="mono"><?= sl_num($s['week']) ?></td><td class="mono"><?= sl_num($s['month']) ?></td><td><?= $s['sk'] ? '<span class="sl-pill overdue">' . (int) $s['sk'] . '</span>' : '<span class="mut">0</span>' ?></td><td><?= sl_num($s['unpaid']) ?></td><td class="mut sm"><?= h($s['last']) ?></td></tr><?php endforeach; ?>
  <?php if (!$d['staffRows']): ?><tr><td colspan="10"><div class="sl-empty">No hand-outs in this period.</div></td></tr><?php endif; ?>
  </tbody></table></div></section>
<section class="sl-card flush"><div class="row between wrap g8" style="padding:14px 16px"><h2 class="sl-ct" style="margin:0">Every hand-out</h2><span class="mut sm"><?= sl_num($d['count']) ?> package<?= $d['count'] === 1 ? '' : 's' ?></span></div>
  <div class="sl-tw"><table class="sl-table"><thead><tr><th>When</th><th>Tracking</th><th>Customer</th><th>Handed over by</th><th>Pickup code</th><th>Paid</th><th>Weight</th></tr></thead><tbody>
  <?php foreach ($d['rows'] as $r): ?><tr><td class="mono sm"><?= h($r['when']) ?></td><td><a class="id" href="<?= h($r['href']) ?>"><?= h($r['trk']) ?></a><span class="sub"><?= $r['module'] === 'sea' ? 'Sea' : 'Air' ?> · <?= h($r['kind']) ?></span></td><td><?= h($r['cust']) ?><span class="sub"><?= h($r['lock'] ?: '—') ?></span></td><td><a style="color:inherit" href="<?= $keep(['staff' => $r['byId'] ?: null]) ?>"><?= h($r['by']) ?></a></td><td><span class="sl-pill <?= $r['method'] === 'skipped' ? 'overdue' : ($r['method'] === 'code' ? 'paid' : 'plain') ?>"><?= h($r['methodL']) ?></span><?php if ($r['reason'] !== ''): ?><span class="sub"><?= h($r['reason']) ?></span><?php endif; ?></td><td><span class="sl-pill <?= $r['paid'] ? 'paid' : 'pending' ?>"><?= $r['paid'] ? 'Paid' : 'Collected at counter' ?></span></td><td class="mono sm"><?= h($r['w']) ?></td></tr><?php endforeach; ?>
  <?php if (!$d['rows']): ?><tr><td colspan="7"><div class="sl-empty">No hand-outs match.</div></td></tr><?php endif; ?>
  </tbody></table></div>
  <?= sl_pager($d['page'], $d['per'], $d['count'], 'hand-outs') ?></section>
<?php endif; ?>
