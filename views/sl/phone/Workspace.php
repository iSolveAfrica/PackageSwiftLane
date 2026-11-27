<?php
/**
 * M-Workspace: My workspace on a phone. The web page fills itself from ajax/staffops/staffops_ajax.php?action=workspace;
 * the phone screen prints the same data (cdp_staffWorkspace(), the signed-in person's own workspace) and posts its
 * buttons to the same endpoint (dataJs/sl/phone/workspace.js).
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
require_once CDP_ROOT . '/helpers/staff_workspace.php';
require_once CDP_ROOT . '/helpers/sl_nav.php';
$slPhone['tab'] = 'Home';
$slPhone['scripts'][] = 'dataJs/sl/phone/workspace.js';
$ws = !empty($d['ready']) ? cdp_staffWorkspace() : null;
$o = fn($x, $k, $def = '') => is_object($x) ? ($x->$k ?? $def) : (is_array($x) ? ($x[$k] ?? $def) : $def);
$hm = fn($t) => substr((string) $t, 0, 5);
$s = $ws['shift'] ?? null;
$st = (string) $o($s, 'status');
$kpis = [];
foreach ($ws['panels'] ?? [] as $p) foreach ($p['kpis'] ?? [] as $k) $kpis[] = $k;
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-ops.css')) ?>">
<?= sl_phoneTop('My workspace', $ws ? $ws['me']['role_label'] . ($ws['me']['branch_names'] ? ' · ' . implode(', ', $ws['me']['branch_names']) : '') : '', ['back' => sl_route('Main')]) ?>
<main class="mx-body">
<?php if (!$ws): ?>
<?= sl_mxEmpty('alert', 'The workspace is not ready', 'The staff tables could not be created. An administrator needs to finish the setup.') ?>
<?php else: ?>
<?php if ($kpis): ?>
<section class="mx-k c2">
<?php foreach (array_slice($kpis, 0, 4) as $k): ?>
<div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-activity" aria-hidden="true"></i></span><small><?= h($o($k, 'label')) ?></small></div><b><?= h(is_numeric($o($k, 'value')) ? sl_num($o($k, 'value')) : $o($k, 'value')) ?></b><?php if ($o($k, 'sub') !== ''): ?><em class=""><?= h($o($k, 'sub')) ?></em><?php endif; ?></div>
<?php endforeach; ?>
</section>
<?php endif; ?>
<section class="mx-hero"><div class="mx-hk">Shift<?= $o($s, 'branch_name') !== '' ? ' · ' . h($o($s, 'branch_name')) : '' ?><?php if ($st === 'checked_in'): ?><span class="mx-tag">On shift</span><?php elseif ($st === 'scheduled'): ?><span class="mx-tag">Planned</span><?php endif; ?></div>
<?php if ($st === 'checked_in'): $from = strtotime((string) $o($s, 'check_in_at')); $mins = max(0, (int) ((time() - $from) / 60)); $len = max(1, (strtotime($o($s, 'end_time')) - strtotime($o($s, 'start_time'))) / 60); ?>
<b class="mx-hv">Checked in <?= h(date('H:i', $from)) ?></b><small>On shift <?= h(floor($mins / 60) . ' h ' . ($mins % 60) . ' min') ?><?= $o($s, 'end_time') !== '' ? ' · until ' . h($hm($o($s, 'end_time'))) : '' ?></small><div class="mx-prog"><i style="width:<?= (int) min(100, round($mins / $len * 100)) ?>%"></i></div>
<?php elseif ($st === 'scheduled'): ?>
<b class="mx-hv"><?= h($hm($o($s, 'start_time')) . '–' . $hm($o($s, 'end_time'))) ?></b><small>Not checked in yet</small>
<?php elseif ($st === 'completed'): ?>
<b class="mx-hv">Shift done today</b><small>Thank you</small>
<?php else: ?>
<b class="mx-hv">No shift planned today</b><small>Your manager plans shifts in Staff &amp; Branches</small>
<?php endif; ?>
</section>
<section class="mx-act c4">
<?php if ($st === 'checked_in'): ?><a href="#" role="button" data-ws-op="clock" data-p-op="out"><span><i class="ic i-check" aria-hidden="true"></i></span><small>Check out</small></a>
<?php elseif ($st === 'scheduled'): ?><a href="#" role="button" data-ws-op="clock" data-p-op="in"><span><i class="ic i-clock" aria-hidden="true"></i></span><small>Check in</small></a><?php endif; ?>
<?php if (sl_navAllowed('ops:staff', [])): ?><a href="<?= h(sl_route('StaffHub')) ?>"><span><i class="ic i-calendar" aria-hidden="true"></i></span><small>Shifts</small></a><?php endif; ?>
<a href="staff_hub.php#approvals"><span><i class="ic i-send" aria-hidden="true"></i></span><small>Ask approval</small></a>
<a href="<?= h(sl_route('Notifications')) ?>"><span><i class="ic i-bell" aria-hidden="true"></i></span><small>Alerts</small></a>
</section>
<?php foreach ($ws['announcements'] as $a): if ($o($a, 'read_at') && !((int) $o($a, 'requires_ack') && !$o($a, 'ack_at'))) continue; ?>
<section class="mx-card" style="padding:14px 16px;display:flex;flex-direction:column;gap:6px"><b><?= h($o($a, 'title')) ?></b><small class="mx-mut"><?= h($o($a, 'author') . ' · ' . sl_ago($o($a, 'created_at'))) ?></small><span style="white-space:pre-line"><?= h($o($a, 'body')) ?></span><button type="button" class="mx-btn sec" data-ws-op="read_announcement" data-p-id="<?= (int) $o($a, 'id') ?>" data-p-ack="<?= (int) $o($a, 'requires_ack') ? 1 : 0 ?>"><?= (int) $o($a, 'requires_ack') ? 'I have read and understood this' : 'Mark as read' ?></button></section>
<?php endforeach; ?>
<div class="mx-sec"><h2>My tasks</h2></div>
<?php if ($ws['tasks']): ?>
<section class="mx-card mx-list">
<?php foreach ($ws['tasks'] as $t): $over = (int) $o($t, 'overdue'); ?>
<div class="mx-row"><span class="mx-li"><i class="ic i-check" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($o($t, 'title')) ?></b><small><?= h(implode(' · ', array_filter([$o($t, 'entity_ref'), $o($t, 'branch_name'), $o($t, 'due_at') ? 'Due ' . sl_date($o($t, 'due_at'), 'M j, H:i') : '']))) ?></small></span><span class="mx-rr"><?php if ($over): ?><span class="sl-pill overdue">Overdue</span><?php endif; ?><?php if ($o($t, 'status') === 'open'): ?><button type="button" class="mx-chipbtn" data-ws-op="task_status" data-p-id="<?= (int) $o($t, 'id') ?>" data-p-status="in_progress">Start</button><?php endif; ?><button type="button" class="mx-chipbtn on" data-ws-op="task_status" data-p-id="<?= (int) $o($t, 'id') ?>" data-p-status="done">Done</button></span></div>
<?php endforeach; ?>
</section>
<?php else: ?>
<?= sl_mxEmpty('check', 'No open tasks', 'Tasks your manager gives you show up here.') ?>
<?php endif; ?>
<?php foreach ($ws['panels'] as $p): if (empty($p['table']['rows']) && empty($p['links']) && empty($p['note']) && ($p['action'] ?? '') !== 'driver_arriving') continue; ?>
<div class="mx-sec"><h2><?= h($p['title']) ?></h2></div>
<?php if (!empty($p['note'])): ?><div class="mx-ban info"><i class="ic ic-sm i-info" aria-hidden="true"></i><span><?= h($p['note']) ?></span></div><?php endif; ?>
<?php if (!empty($p['table']['rows'])): $cols = $p['table']['cols']; ?>
<section class="mx-card mx-list">
<?php foreach (array_slice($p['table']['rows'], 0, 8) as $r): $vals = array_map(fn($c) => str_replace('_', ' ', (string) $o($r, $c[0])), $cols); ?>
<div class="mx-row"><span class="mx-rm"><b><?= h($vals[0] ?? '') ?></b><small><?= h(implode(' · ', array_filter(array_slice($vals, 1), fn($v) => $v !== ''))) ?></small></span></div>
<?php endforeach; ?>
</section>
<?php endif; ?>
<?php if (($p['action'] ?? '') === 'driver_arriving'): ?>
<form class="mx-card" id="ws-arrive" style="padding:14px 16px;display:flex;flex-direction:column;gap:10px"><label class="mx-fld"><span>Tracking number</span><input name="tracking" required autocomplete="off" autocapitalize="characters"></label><label class="mx-fld"><span>Minutes away</span><input name="eta" type="number" inputmode="numeric" min="0" max="600" value="15"></label><button class="mx-btn" type="submit">Tell the customer I am arriving</button></form>
<?php endif; ?>
<?php if (!empty($p['links'])): ?>
<section class="mx-card mx-list"><?php foreach ($p['links'] as $l): ?><a class="mx-row" href="<?= h($o($l, 'href')) ?>"><span class="mx-rm"><b><?= h($o($l, 'label')) ?></b></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a><?php endforeach; ?></section>
<?php endif; ?>
<?php endforeach; ?>
<?php if ($ws['week']): ?>
<div class="mx-sec"><h2>My week</h2></div>
<section class="mx-card mx-list">
<?php foreach ($ws['week'] as $x): ?>
<div class="mx-row"><span class="mx-li"><i class="ic i-calendar" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h(sl_date($o($x, 'shift_date'), 'D, M j')) ?></b><small><?= h($hm($o($x, 'start_time')) . '–' . $hm($o($x, 'end_time')) . ($o($x, 'branch_name') !== '' ? ' · ' . $o($x, 'branch_name') : '')) ?></small></span><span class="mx-rr"><small><?= h(ucfirst(str_replace('_', ' ', (string) $o($x, 'status')))) ?></small></span></div>
<?php endforeach; ?>
</section>
<?php endif; ?>
<?php if ($ws['to_decide']): ?>
<div class="mx-sec"><h2>Waiting for my decision</h2><a href="staff_hub.php#approvals">Decide</a></div>
<section class="mx-card mx-list">
<?php foreach ($ws['to_decide'] as $a): ?><a class="mx-row" href="staff_hub.php#approvals"><span class="mx-li"><i class="ic i-send" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($o($a, 'title')) ?></b><small><?= h($o($a, 'requested_name')) ?></small></span><?php if ($o($a, 'amount', null) !== null): ?><span class="mx-rr"><b><?= h(sl_num($o($a, 'amount'), 2)) ?></b></span><?php endif; ?></a><?php endforeach; ?>
</section>
<?php endif; ?>
<?php if ($ws['requests']): ?>
<div class="mx-sec"><h2>My requests</h2></div>
<section class="mx-card mx-list">
<?php foreach (array_slice($ws['requests'], 0, 8) as $a): $rs = (string) $o($a, 'status'); ?><div class="mx-row"><span class="mx-rm"><b><?= h($o($a, 'title')) ?></b></span><span class="mx-rr"><span class="sl-pill <?= $rs === 'approved' ? 'delivered' : ($rs === 'rejected' ? 'overdue' : 'pending') ?>"><?= h(ucfirst($rs)) ?></span></span></div><?php endforeach; ?>
</section>
<?php endif; ?>
<?php endif; ?>
</main>
