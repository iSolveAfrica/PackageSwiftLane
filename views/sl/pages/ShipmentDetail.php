<?php
/**
 * ShipmentDetail - from docs/handover/artifact-source/ShipmentDetail.dc.html.
 * Data: views/sl/data/shipments.php (sl_shipmentDetailData).
 */
if (!isset($d)) { $d = []; }
if (!empty($d['missing'])): ?>
        <div class="sl-head"><div><a href="<?= sl_route('AirShipments') ?>" class="row g6 mut sm"><i class="ic ic-sm i-left" aria-hidden="true"></i>Air Shipments</a><h1 class="sl-title" style="margin-top:4px">Shipment not found</h1></div></div>
        <div class="sl-card"><div class="sl-empty"><span class="sl-tile"><i class="ic i-plane" aria-hidden="true"></i></span>This shipment does not exist or is outside your scope.</div></div>
<?php return; endif; ?>
<?php
$ch = $d['ch'];
$stageNames = $d['steps'];
$stageCls = ['s1', 's2', 's3', 's4', 's5'];
$fmtDur = function (int $sec): string {
    if ($sec <= 0) return '—';
    if ($sec < 3600) return max(1, (int) round($sec / 60)) . ' min';
    if ($sec < 86400) return round($sec / 3600, 1) . ' h';
    return round($sec / 86400, 1) . ' days';
};
$stageTotal = max(1, array_sum($ch['stage']));
$cmpMax = max(1, $ch['days'], (float) ($ch['laneAvg'] ?? 0));
$chargeSum = max(0.01, array_sum(array_map(fn($c) => $c['v'], $ch['charges'])));
$mPeak = max(1, max($ch['months']));
?>
        <div class="sl-head sdx-head">
          <div><a href="<?= sl_route('AirShipments') ?>" class="row g6 mut sm"><i class="ic ic-sm i-left" aria-hidden="true"></i>Air Shipments</a>
            <div class="row g12 wrap" style="align-items:center;margin-top:8px"><h1 class="sdx-track mono"><?= h($d['track']) ?></h1><span class="sl-pill <?= h($d['stCls']) ?>" style="height:30px;font-size:15px;padding:0 14px"><?= h($d['status']) ?></span><span class="sl-badge <?= $d['mode'] === 'Air' ? 'air' : 'sea' ?>" style="height:30px;font-size:15px;padding:0 12px"><?= h($d['mode']) ?></span><?php if ($d['dangerous']): ?><span class="sl-pill overdue">Dangerous goods</span><?php endif; ?><?php if ($d['consol']): ?><a class="sl-badge" href="<?= sl_route('ConsolidationDetail', ['id' => $d['consol']['id']]) ?>">Consolidated · <?= h($d['consol']['code']) ?></a><?php endif; ?></div>
          </div>
          <div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('ShipmentTracking', ['id' => $d['id']]) ?>"><i class="ic ic-sm i-route" aria-hidden="true"></i>Full journey</a><?php if ($d['canLabel']): ?><a class="sl-btn ghost" href="<?= sl_route('Label4x6', ['id' => $d['id']]) ?>" target="_blank"><i class="ic ic-sm i-print" aria-hidden="true"></i>Label</a><?php endif; ?><?php if ($d['canPrint']): ?><a class="sl-btn ghost" href="<?= sl_route('ReceiptNotPaid', ['id' => $d['id'], 'type' => 'air']) ?>" target="_blank"><i class="ic ic-sm i-receipt" aria-hidden="true"></i>Invoice</a><?php endif; ?><?php if ($d['canEdit']): ?><a class="sl-btn ghost" href="courier_edit.php?id=<?= (int) $d['id'] ?>"><i class="ic ic-sm i-edit" aria-hidden="true"></i>Edit</a><?php endif; ?><?php if ($d['canPay']): ?><a class="sl-btn" href="add_payment_gateways_courier.php?id_order=<?= (int) $d['id'] ?>"><i class="ic ic-sm i-dollar" aria-hidden="true"></i>Pay now</a><?php endif; ?><?php if ($d['canTrack']): ?><button type="button" class="sl-btn" data-sl-open="sd-status"><i class="ic ic-sm i-refresh" aria-hidden="true"></i>Update status</button><?php endif; ?></div>
        </div>

        <section class="sdx-hero" aria-label="Journey">
          <div class="sdx-hero-main">
            <div class="sdx-route"><div><small>From</small><b><?= h($d['from']) ?></b></div><span class="sdx-arrow" aria-hidden="true"><i class="ic <?= $d['mode'] === 'Air' ? 'i-plane' : 'i-ship' ?>"></i></span><div class="tr"><small>To</small><b><?= h($d['to']) ?></b></div></div>
            <?php if (!empty($d['scene'])): $sc = $d['scene']; ?>
            <figure class="sdx-scene<?= $sc['agent'] ? ' agent' : '' ?>">
              <img src="<?= h(cdp_asset($sc['img'])) ?>" alt="<?= h($sc['title'] . ': ' . $sc['text']) ?>" loading="lazy" decoding="async">
              <figcaption><span class="sdx-live"><i></i><?= ($ch['done'] ?? false) ? 'Done' : 'Now' ?></span><b><?= h($sc['title']) ?></b><span><?= h($sc['text']) ?></span></figcaption>
            </figure>
            <?php endif; ?>
            <ol class="sdx-steps" aria-label="Step <?= (int) $d['step'] ?> of 5: <?= h($stageNames[$d['step'] - 1] ?? '') ?>" style="--p:<?= $ch['done'] ? 1 : round(($d['step'] - 1) / 4, 3) ?>">
              <?php foreach ($stageNames as $i => $sn): $n = $i + 1; $cls = $n < $d['step'] || ($n === 5 && $ch['done']) ? 'done' : ($n === $d['step'] ? 'now' : ''); ?>
              <li class="<?= $cls ?>"><span class="sdx-dot"><?php if ($cls === 'done'): ?><i class="ic ic-sm i-check" aria-hidden="true"></i><?php else: ?><?= $n ?><?php endif; ?></span><b><?= h($sn) ?></b><small><?= h($ch['reached'][$n] ?? ($cls === 'now' ? 'now' : '—')) ?></small></li>
              <?php endforeach; ?>
            </ol>
            <div class="sdx-meta"><span><i class="ic ic-sm i-clock" aria-hidden="true"></i><?= $ch['done'] ? 'Delivered in <b>' . h($ch['days']) . ' days</b>' : '<b>' . h($ch['days']) . ' days</b> on the way' ?></span><span><i class="ic ic-sm i-calendar" aria-hidden="true"></i>Estimated delivery <b><?= h($d['eta']) ?></b></span><span><i class="ic ic-sm i-truck" aria-hidden="true"></i><?= h($d['carrier'] ?: 'No carrier') ?> <b class="mono"><?= h($d['carrierNo'] ?: '—') ?></b></span></div>
          </div>
          <div class="sdx-scan">
            <div class="sdx-bc"><?= $ch['bc'] ?><span class="mono"><?= h($d['track']) ?></span></div>
            <div class="sdx-qr"><?= $ch['qr'] ?><small>Scan to track</small></div>
          </div>
        </section>

        <div class="sdx-kpis">
          <div><span>Weight</span><b><?= h($d['kg']) ?></b><small><?= $ch['rate'] > 0 ? h(sl_money($ch['rate'])) . ' per ' . h($core->weight_p ?? 'lb') : 'rate not set' ?></small></div>
          <div><span>Pieces</span><b><?= sl_num($ch['pieces']) ?></b><small><?= count($d['items']) ?> item line<?= count($d['items']) === 1 ? '' : 's' ?></small></div>
          <div><span>Declared value</span><b><?= h(sl_money($ch['declared'])) ?></b><small>customs value of the items</small></div>
          <div><span>Total</span><b><?= h($d['total']) ?></b><small><span class="sl-pill <?= h($d['payCls']) ?>" style="height:20px;font-size:11.5px"><?= h($d['pay']) ?></span></small></div>
          <div><span><?= $ch['done'] ? 'Took' : 'On the way' ?></span><b><?= h($ch['days']) ?> <small>days</small></b><small><?php if ($ch['laneAvg'] !== null): $diff = round($ch['days'] - $ch['laneAvg'], 1); ?><?= $diff > 0.5 ? '<span class="sdx-slow">' . h($diff) . ' days slower</span> than usual' : ($diff < -0.5 ? '<span class="sdx-fast">' . h(abs($diff)) . ' days faster</span> than usual' : 'about usual') ?><?php else: ?>no comparison yet<?php endif; ?></small></div>
        </div>

        <div class="sdx-charts">
          <section class="sl-card col g12" aria-labelledby="sdx-time-h">
            <div class="row between wrap g8"><div><h2 class="sl-ct" id="sdx-time-h">Time in each step</h2><span class="mut sm"><?= h($ch['startLabel']) ?> → <?= h($ch['endLabel']) ?> · from <?= (int) $d['eventCount'] ?> tracking update<?= (int) $d['eventCount'] === 1 ? '' : 's' ?></span></div></div>
            <div class="sdx-stack" <?= sl_chartAttrs([['name' => 'Time', 'values' => array_map(fn($v) => round($v / 86400, 2), array_values($ch['stage'])), 'unit' => 'days']], $stageNames, 'Days in each step') ?> role="img" aria-label="Time in each step">
              <?php foreach ($ch['stage'] as $n => $sec): if ($sec <= 0) continue; ?><i class="<?= $stageCls[$n - 1] ?>" style="flex:<?= round($sec / $stageTotal * 1000) ?>" title="<?= h($stageNames[$n - 1] . ': ' . $fmtDur($sec)) ?>"></i><?php endforeach; ?>
            </div>
            <ul class="sdx-legend">
              <?php foreach ($ch['stage'] as $n => $sec): ?><li class="<?= $sec ? '' : 'zero' ?>"><i class="<?= $stageCls[$n - 1] ?>"></i><span><?= h($stageNames[$n - 1]) ?></span><b><?= h($fmtDur($sec)) ?></b><small><?= $sec ? sl_pct($sec, $stageTotal) . '%' : '' ?></small></li><?php endforeach; ?>
            </ul>
            <div class="sdx-cmp">
              <div class="row between sm"><span class="fw5">Days on the way</span><span class="mut"><?= $ch['laneAvg'] !== null ? 'usual from ' . sl_num($ch['laneN']) . ' recent ' . strtolower($d['mode']) . ' deliveries' : 'not enough recent deliveries to compare' ?></span></div>
              <div class="sdx-cmp-row"><span>This shipment</span><span class="sdx-cmp-bar"><i class="me" style="width:<?= round(100 * $ch['days'] / $cmpMax, 1) ?>%"></i></span><b><?= h($ch['days']) ?> d</b></div>
              <?php if ($ch['laneAvg'] !== null): ?><div class="sdx-cmp-row"><span>Usual</span><span class="sdx-cmp-bar"><i style="width:<?= round(100 * $ch['laneAvg'] / $cmpMax, 1) ?>%"></i></span><b><?= h($ch['laneAvg']) ?> d</b></div><?php endif; ?>
            </div>
          </section>
          <section class="sl-card col g12" aria-labelledby="sdx-chg-h">
            <div class="row between wrap g8"><div><h2 class="sl-ct" id="sdx-chg-h">Charges</h2><span class="mut sm">What makes up the total</span></div><b class="sdx-total"><?= h($d['total']) ?></b></div>
            <?php if ($ch['charges']): ?>
            <div class="sdx-stack chg" <?= sl_chartAttrs([['name' => 'Amount', 'values' => array_map(fn($c) => round($c['v'], 2), $ch['charges'])]], array_map(fn($c) => $c['l'], $ch['charges']), 'Charges') ?> role="img" aria-label="Charges split">
              <?php foreach ($ch['charges'] as $i => $c): ?><i class="c<?= $i % 6 ?>" style="flex:<?= max(1, round($c['v'] / $chargeSum * 1000)) ?>" title="<?= h($c['l'] . ': ' . sl_money($c['v'])) ?>"></i><?php endforeach; ?>
            </div>
            <ul class="sdx-legend">
              <?php foreach ($ch['charges'] as $i => $c): ?><li><i class="c<?= $i % 6 ?>"></i><span><?= h($c['l']) ?></span><b><?= h(sl_money($c['v'])) ?></b><small><?= sl_pct($c['v'], $chargeSum) ?>%</small></li><?php endforeach; ?>
              <?php if ($ch['discount'] > 0): ?><li><i class="disc"></i><span>Discount</span><b>−<?= h(sl_money($ch['discount'])) ?></b><small></small></li><?php endif; ?>
            </ul>
            <?php else: ?><p class="mut sm" style="margin:0">No charges calculated yet.</p><?php endif; ?>
            <div class="sl-kv sdx-paykv"><div><span>Payment</span><span><span class="sl-pill <?= h($d['payCls']) ?>"><?= h($d['pay']) ?></span></span></div><div><span>Method</span><span><?= h($d['payMethod']) ?></span></div><div><span><?= $d['pay'] === 'Paid' ? 'Paid on' : 'Due' ?></span><span><?= h($d['pay'] === 'Paid' ? $d['paidOn'] : $d['due']) ?></span></div></div>
          </section>
        </div>

        <div class="split">
          <div class="a col g16">
            <section class="sl-card flush">
              <div class="row between" style="padding: 16px 20px"><h2 class="sl-ct">Items</h2><span class="mut sm"><?= count($d['items']) ?> lines · <?= h($d['kg']) ?></span></div>
              <div class="sl-tw"><table class="sl-table"><thead><tr><th>Qty</th><th>Description</th><th class="num">Weight</th><th style="width:22%">Share of weight</th><th class="num">Custom price</th><th class="num">Declared value</th></tr></thead><tbody>
                <?php foreach ($d['items'] as $ii => $it): $sh = $ch['iwTotal'] > 0 ? sl_pct($ch['iw'][$ii] ?? 0, $ch['iwTotal']) : 0; ?><tr><td><?= h($it['qty']) ?></td><td><?= h($it['desc']) ?></td><td class="num"><?= h($it['kg']) ?></td><td><span class="sdx-share" title="<?= h($it['desc'] . ': ' . $sh . '% of the weight') ?>"><i style="width:<?= $sh ?>%"></i></span><small class="mut"><?= $ch['iwTotal'] > 0 ? $sh . '%' : '—' ?></small></td><td class="num"><?= h($it['custom']) ?></td><td class="num"><?= h($it['val']) ?></td></tr><?php endforeach; ?>
                <?php if (!$d['items']): ?><tr><td colspan="6"><div class="sl-empty">No items recorded on this shipment.</div></td></tr><?php endif; ?>
              </tbody></table></div>
              <?php if ($d['notes'] !== ''): ?><div style="padding: 12px 20px; border-top: 1px solid var(--line)" class="sm"><span class="mut">Notes</span> · <?= h($d['notes']) ?></div><?php endif; ?>
            </section>
            <div class="grid cols2">
              <section class="sl-card col g12"><div class="row between"><h2 class="sl-ct">Sender</h2><i class="ic i-user mut" aria-hidden="true"></i></div>
                <div class="row g12"><span class="sl-av"><?= h($d['sender']['ini']) ?></span><div><div class="fw5"><?php if (!$d['client']): ?><a href="<?= sl_route('CustomerProfile', ['id' => $d['sender']['id']]) ?>" style="color:inherit"><?= h($d['sender']['name']) ?></a><?php else: ?><?= h($d['sender']['name']) ?><?php endif; ?></div><div class="mut sm"><?= h($d['sender']['mail']) ?></div></div></div>
                <div class="sl-kv"><div><span>Locker</span><span class="mono"><?= h($d['sender']['locker'] ?: '—') ?></span></div><div><span>Address</span><span><?= h($d['sender']['addr']) ?></span></div><div><span>Phone</span><span><?= h($d['sender']['phone']) ?></span></div></div>
                <div class="sdx-hist"><div class="row between sm"><span class="mut"><?= $d['client'] ? 'Your' : 'Their' ?> shipment <b style="color:var(--ink)">#<?= sl_num($ch['senderNo']) ?></b> of <?= sl_num($ch['senderAll']) ?></span><span class="mut"><?= sl_num(array_sum($ch['months'])) ?> in 12 months</span></div>
                  <div class="sdx-hbars" <?= sl_chartAttrs([['name' => 'Shipments', 'values' => array_values($ch['months'])]], array_map(fn($k) => date('M Y', strtotime($k . '-01')), array_keys($ch['months'])), 'Shipments per month by this sender') ?> role="img" aria-label="Shipments per month by this sender, last 12 months"><?php foreach (array_values($ch['months']) as $mi => $mv): ?><span><i class="<?= $mi === 11 ? 'now' : '' ?>" style="height:<?= $mv ? max(6, round(100 * $mv / $mPeak)) : 0 ?>%"></i></span><?php endforeach; ?></div></div></section>
              <section class="sl-card col g12"><div class="row between"><h2 class="sl-ct">Recipient</h2><i class="ic i-pin mut" aria-hidden="true"></i></div>
                <div class="row g12"><span class="sl-av"><?= h($d['recip']['ini']) ?></span><div><div class="fw5"><?= h($d['recip']['name']) ?></div><div class="mut sm"><?= h($d['recip']['mail']) ?></div></div></div>
                <div class="sl-kv"><div><span>Address</span><span><?= h($d['recip']['addr']) ?></span></div><div><span>Phone</span><span><?= h($d['recip']['phone']) ?></span></div></div></section>
            </div>
            <section class="sl-card col g12">
              <div class="sl-ch" style="margin-bottom: 0"><h2 class="sl-ct">Photos and documents</h2><?php if ($d['canEdit']): ?><a class="sl-btn ghost sm" href="courier_edit.php?id=<?= (int) $d['id'] ?>#files"><i class="ic ic-sm i-upload" aria-hidden="true"></i>Upload</a><?php endif; ?></div>
              <div class="grid cols4"><?php foreach ($d['files'] as $f): ?><a class="sl-drop" style="padding: 22px" href="<?= h($f['url']) ?>" target="_blank"><i class="ic <?= $f['img'] ? 'i-image' : 'i-file' ?>" aria-hidden="true"></i><span class="sm" style="word-break:break-all"><?= h($f['name']) ?></span></a><?php endforeach; ?><?php if (!$d['files']): ?><div class="mut sm" style="grid-column:1/-1">No photos or documents attached.</div><?php endif; ?></div>
            </section>
          </div>
          <aside class="b col g16">
            <section class="sl-card col g12" id="payment"><div class="row between"><h2 class="sl-ct">Payment</h2><span class="sl-pill <?= h($d['payCls']) ?>"><?= h($d['pay']) ?></span></div>
              <div class="sl-kv"><div><span>Terms</span><span><?= h($d['terms'] ?: '—') ?></span></div><div><span>Reference</span><span class="mono sm"><?= h($d['payRef']) ?></span></div></div>
              <?php if ($d['canVerify']): ?><a class="sl-btn ghost sm" href="courier_list.php?verify=<?= (int) $d['id'] ?>"><i class="ic ic-sm i-dollar" aria-hidden="true"></i>Verify payment</a><?php endif; ?>
              <?php if ($d['canPay']): ?><a class="sl-btn block" href="add_payment_gateways_courier.php?id_order=<?= (int) $d['id'] ?>">Pay now</a><?php endif; ?>
            </section>
            <?php if (!$d['client']):
              $pk = $d['pickup'];
              $pkState = empty($pk['assigned']) ? ['Not assigned', 'is-off'] : (!empty($pk['verified']) ? ['Verified', 'is-ok'] : (!empty($pk['sent']) ? ['Sent', 'is-sent'] : ['Waiting to send', 'is-wait']));
              // "SMS ****5009, WhatsApp ****5009, E-mail he***@x.com" -> one row per channel
              $pkCh = [];
              foreach (array_filter(array_map('trim', explode(',', (string) ($pk['channels'] ?? '')))) as $c) {
                  [$name, $to] = array_pad(explode(' ', $c, 2), 2, '');
                  $k = strtolower(preg_replace('/[^a-z]/i', '', $name));
                  $pkCh[] = ['name' => $name, 'to' => trim($to), 'k' => $k, 'ic' => ['sms' => 'i-message', 'whatsapp' => 'i-phone', 'email' => 'i-mail'][$k] ?? 'i-send'];
              }
              $pkSteps = [['Assigned', $pk['created'] ?? '', 'i-key'], ['Sent', $pk['sent'] ?? '', 'i-send'], ['Verified', $pk['verified'] ?? '', 'i-check']]; ?>
            <section class="sl-card col g12 pk-card" id="pickup-code" aria-labelledby="pk-title">
              <div class="row between"><h2 class="sl-ct row g8" id="pk-title"><i class="ic ic-sm i-key" aria-hidden="true"></i>Pickup code</h2><span class="pk-state <?= h($pkState[1]) ?>"><i aria-hidden="true"></i><?= h($pkState[0]) ?></span></div>
              <?php if (!empty($pk['assigned'])): ?>
                <div class="pk-code"><b class="mono"><span class="sd-vh">Code </span><?= h(trim(chunk_split((string) $pk['code'], 3, ' '))) ?></b><button type="button" class="sl-ib sm" data-sl-copy="<?= h($pk['code']) ?>" aria-label="Copy pickup code"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button></div>
                <ol class="pk-steps" aria-label="Pickup code progress">
                  <?php foreach ($pkSteps as [$l, $t, $ic]): ?><li class="<?= $t !== '' ? 'done' : '' ?>"><span class="pk-dot"><i class="ic ic-sm <?= $t !== '' ? 'i-check' : $ic ?>" aria-hidden="true"></i></span><b><?= h($l) ?></b><small><?= $t !== '' ? implode('', array_map(fn($x) => '<span>' . h(trim($x)) . '</span>', explode('·', $t, 2))) : 'Not yet' ?></small></li><?php endforeach; ?>
                </ol>
                <?php if (empty($pk['sent'])): ?><p class="pk-note"><i class="ic ic-sm i-clock" aria-hidden="true"></i>Sent automatically when the package is ready for pickup.</p><?php endif; ?>
                <?php if ($pkCh): ?>
                <div class="pk-ch">
                  <span class="pk-lab"><?= empty($pk['sent']) ? 'Will be sent to' : 'Sent to' ?></span>
                  <ul>
                    <?php foreach ($pkCh as $c): ?><li class="pk-<?= h($c['k']) ?>"><span class="pk-ico"><i class="ic ic-sm <?= h($c['ic']) ?>" aria-hidden="true"></i></span><b><?= h($c['name']) ?></b><span class="mono"><?= h($c['to']) ?></span></li><?php endforeach; ?>
                  </ul>
                </div>
                <?php endif; ?>
              <?php else: ?>
                <p class="mut sm">No code yet. A code is assigned when the package is created and sent when it is ready for pickup.</p>
              <?php endif; ?>
            </section>
            <?php endif; ?>
            <section class="sl-card">
              <div class="sl-ch"><h2 class="sl-ct">Tracking</h2><a href="<?= h($d['publicUrl']) ?>" target="_blank" class="sm fw5 row g6">Public page <i class="ic ic-sm i-ext" aria-hidden="true"></i></a></div>
              <div class="sl-tl">
                <?php foreach ($d['events'] as $e): ?><div class="<?= h($e['cls']) ?>"><span class="dot"></span><div><div class="fw5 row g8"><i class="ic ic-sm <?= h($e['ic']) ?>" aria-hidden="true"></i><?= h($e['t']) ?></div><div class="mut sm"><?= h($e['s']) ?></div></div></div><?php endforeach; ?>
                <?php if (!$d['events']): ?><div class="mut sm">No tracking events yet.</div><?php endif; ?>
              </div>
              <?php if ($d['client'] && (!empty($d['pickupCode']['live']) || !empty($d['pickupCode']['verified']))): ?><div class="row between sm" style="margin-top:10px;padding-top:10px;border-top:1px solid var(--line)"><span class="mut">Pickup code</span><b><?= !empty($d['pickupCode']['verified']) ? 'Verified at hand-over' : 'Sent to the customer' ?></b></div><?php endif; ?>
            </section>
            <section class="sl-card col g12"><div class="row between"><h2 class="sl-ct">Assigned driver</h2><?php if ($d['canAssign']): ?><button type="button" class="sl-btn ghost sm" data-sl-open="sd-driver"><?= $d['driver'] ? 'Change' : 'Assign' ?></button><?php endif; ?></div>
              <?php if ($d['driver']): ?><div class="row g12"><span class="sl-av dark"><?= h($d['driver']['ini']) ?></span><div class="grow"><div class="fw5"><?= h($d['driver']['name']) ?></div><div class="mut sm"><?= h($d['driver']['veh']) ?></div></div><?php if ($d['driver']['phone']): ?><a class="sl-ib sm" aria-label="Call driver" href="tel:<?= h($d['driver']['phone']) ?>"><i class="ic i-phone" aria-hidden="true"></i></a><?php endif; ?></div><?php else: ?><div class="mut sm">No rider assigned yet.</div><?php endif; ?>
            </section>
          </aside>
        </div>

        <?php if (!$d['client']): ?>
        <section class="sl-card col g12 act-card" id="activity" aria-labelledby="act-title">
          <div class="row between wrap g12 act-head">
            <div class="col g4"><h2 class="sl-ct" id="act-title">Activity on this package</h2><span class="mut sm">Every edit, status update, view, print, pickup code and customer action, newest first.</span></div>
            <div class="sl-seg boxed act-filter" role="group" aria-label="Show activity" aria-controls="act-list">
              <?php foreach (['all' => 'All', 'Shipment' => 'Shipment', 'Package' => 'Package', 'Customer' => 'Customer', 'Pickup code' => 'Pickup code'] as $k => $lbl): ?>
              <button type="button" class="<?= $k === 'all' ? 'on' : '' ?>" data-act-f="<?= h($k) ?>" aria-pressed="<?= $k === 'all' ? 'true' : 'false' ?>"><?= h($lbl) ?> <span class="mono act-n"><?= (int) ($d['activity']['counts'][$k] ?? 0) ?></span></button>
              <?php endforeach; ?>
            </div>
          </div>
          <?php
          $actIcon = function (array $a): string {
              $w = strtolower($a['what']);
              foreach (['view' => 'i-eye', 'print' => 'i-print', 'label' => 'i-print', 'creat' => 'i-plus', 'status' => 'i-refresh', 'pay' => 'i-dollar', 'invoice' => 'i-receipt',
                        'delet' => 'i-trash', 'cancel' => 'i-ban', 'sent' => 'i-send', 'verif' => 'i-check', 'assign' => 'i-user', 'edit' => 'i-edit', 'updat' => 'i-edit', 'chang' => 'i-edit'] as $k => $ic) {
                  if (strpos($w, $k) !== false) return $a['kind'] === 'Pickup code' && $ic === 'i-plus' ? 'i-key' : $ic;
              }
              return ['Pickup code' => 'i-key', 'Customer' => 'i-user', 'Package' => 'i-box'][$a['kind']] ?? 'i-file';
          };
          $actTone = ['Shipment' => 'blue', 'Package' => 'violet', 'Customer' => 'amber', 'Pickup code' => 'green'];
          $actDay = function (int $ts): string {
              if (!$ts) return 'Earlier';
              $d = date('Y-m-d', $ts);
              return $d === date('Y-m-d') ? 'Today' : ($d === date('Y-m-d', strtotime('-1 day')) ? 'Yesterday' : date('l, M j, Y', $ts));
          };
          $lastDay = null;
          ?>
          <div class="act-list" id="act-list">
            <?php foreach ($d['activity']['rows'] as $a): $day = $actDay((int) $a['ts']); $out = strtolower($a['outcome']); $bad = in_array($out, ['failure', 'failed', 'denied', 'error'], true); ?>
            <?php if ($day !== $lastDay): if ($lastDay !== null) echo '</ol>'; $lastDay = $day; ?>
            <div class="act-day" data-act-day><?= h($day) ?></div>
            <ol class="act-tl">
            <?php endif; ?>
              <li class="act-row t-<?= h($actTone[$a['kind']] ?? 'blue') ?><?= $bad ? ' bad' : '' ?>" data-act-kind="<?= h($a['kind']) ?>">
                <span class="act-dot" aria-hidden="true"><i class="ic ic-sm <?= h($bad ? 'i-alert' : $actIcon($a)) ?>"></i></span>
                <div class="act-body">
                  <div class="act-top"><b><?= h($a['what']) ?></b><time class="act-time"><?= h(preg_replace('/^.*·\s*/', '', $a['when'])) ?></time></div>
                  <?php if ($a['detail'] !== ''): ?><span class="act-detail"><?= h($a['detail']) ?></span><?php endif; ?>
                  <div class="act-meta"><span class="act-kind"><?= h($a['kind']) ?></span><span class="act-who"><i class="ic ic-sm i-user" aria-hidden="true"></i><?= h($a['who']) ?></span><?php if ($out !== '' && $out !== 'ok' && $out !== 'success'): ?><span class="act-out<?= $bad ? ' bad' : '' ?>"><?= h(ucfirst($a['outcome'])) ?></span><?php endif; ?></div>
                </div>
              </li>
            <?php endforeach; ?>
            <?php if ($lastDay !== null) echo '</ol>'; ?>
            <?php if (empty($d['activity']['rows'])): ?><div class="sl-empty">Nothing recorded on this package yet.</div><?php endif; ?>
            <div class="sl-empty" data-act-none hidden>Nothing of this kind recorded on this package.</div>
          </div>
          <p class="sd-vh" role="status" data-act-status></p>
        </section>
        <script>
        (function () {
          var card = document.getElementById('activity'); if (!card) return;
          var btns = card.querySelectorAll('[data-act-f]'), rows = card.querySelectorAll('.act-row');
          var none = card.querySelector('[data-act-none]'), status = card.querySelector('[data-act-status]');
          btns.forEach(function (b) {
            b.addEventListener('click', function () {
              var f = b.getAttribute('data-act-f'), shown = 0;
              btns.forEach(function (x) { var on = x === b; x.classList.toggle('on', on); x.setAttribute('aria-pressed', on ? 'true' : 'false'); });
              rows.forEach(function (r) { var hide = f !== 'all' && r.getAttribute('data-act-kind') !== f; r.hidden = hide; if (!hide) shown++; });
              card.querySelectorAll('.act-tl').forEach(function (l) { var vis = l.querySelectorAll('.act-row:not([hidden])'); l.hidden = !vis.length; l.previousElementSibling.hidden = !vis.length; l.querySelectorAll('.act-row').forEach(function (x) { x.classList.toggle('end', x === vis[vis.length - 1]); }); });
              if (none) none.hidden = shown > 0 || rows.length === 0;
              if (status) status.textContent = shown + ' ' + (shown === 1 ? 'entry' : 'entries') + ' shown';
            });
          });
        })();
        </script>
        <?php endif; ?>
<?php if ($d['canTrack']): ?>
<div class="sl-dialog" id="sd-status" hidden><form class="sl-modal" data-sl-form action="ajax/courier/add_courier_tracking.php" method="post" role="dialog" aria-label="Update status">
  <input type="hidden" name="shipment_id" value="<?= (int) $d['id'] ?>">
  <div class="row g10"><span class="sl-tile"><i class="ic i-refresh" aria-hidden="true"></i></span><div class="col grow"><b class="fw5">Update status of <?= h($d['track']) ?></b><span class="mut sm">A tracking event is added and the customer is notified by the channels enabled in Settings.</span></div></div>
  <div class="grid cols2" style="gap:10px">
    <label class="sl-field"><span class="sl-lab">New status</span><span class="sl-sel native"><select name="status_courier" required><?php foreach ($d['statuses'] as $s): ?><option value="<?= (int) $s->id ?>" <?= (int) $s->id === $d['statusId'] ? 'selected' : '' ?>><?= h(str_replace('_', ' ', $s->mod_style)) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
    <label class="sl-field"><span class="sl-lab">Date</span><input class="sl-in" type="date" name="t_date" value="<?= date('Y-m-d') ?>" required></label>
    <label class="sl-field"><span class="sl-lab">Office</span><span class="sl-sel native"><select name="office" required><?php foreach ($d['offices'] as $o): ?><option value="<?= (int) $o->id ?>" <?= (int) $o->id === $d['office'] ? 'selected' : '' ?>><?= h($o->name_off) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
    <label class="sl-field"><span class="sl-lab">Country</span><span class="sl-sel native"><select name="country" required><?php foreach ($d['countries'] as $c): ?><option value="<?= h($c->name) ?>"><?= h($c->name) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
    <label class="sl-field" style="grid-column:1/-1"><span class="sl-lab">Location / address</span><input class="sl-in" name="address" placeholder="Where the parcel is now" required></label>
    <label class="sl-field"><span class="sl-lab">Flight number</span><input class="sl-in mono" name="flight_no" placeholder="KQ 101"></label>
    <label class="sl-field"><span class="sl-lab">AWB number</span><input class="sl-in mono" name="awb_no" placeholder="176-4421 8890"></label>
    <label class="sl-field" style="grid-column:1/-1"><span class="sl-lab">Comment</span><input class="sl-in" name="comments" maxlength="250" placeholder="Shown to the customer"></label>
  </div>
  <div class="row g12 wrap sm"><label class="row g6"><input type="checkbox" name="notify_whatsapp_sender" value="1" checked>WhatsApp sender</label><label class="row g6"><input type="checkbox" name="notify_sms_sender" value="1">SMS sender</label><label class="row g6"><input type="checkbox" name="notify_sms_receiver" value="1">SMS recipient</label></div>
  <div class="row g8"><button type="submit" class="sl-btn">Save status</button><button type="button" class="sl-btn ghost" data-sl-close>Cancel</button></div>
</form></div>
<?php endif; ?>
<?php if ($d['canAssign']): ?>
<div class="sl-dialog" id="sd-driver" hidden><form class="sl-modal" data-sl-form data-sl-reload action="ajax/sl/shipment_action_ajax.php" method="post" role="dialog" aria-label="Assign rider">
  <input type="hidden" name="op" value="driver"><input type="hidden" name="ids" value="<?= (int) $d['id'] ?>">
  <div class="row g10"><span class="sl-tile"><i class="ic i-wheel" aria-hidden="true"></i></span><div class="col grow"><b class="fw5">Assign a rider</b><span class="mut sm">The driver is notified and the shipment appears on their route.</span></div></div>
  <label class="sl-field"><span class="sl-lab">Rider</span><span class="sl-sel native"><select name="driver_id" required><option value="">Choose a rider</option><?php foreach ($d['drivers'] as $dr): ?><option value="<?= (int) $dr->id ?>" <?= $d['driver'] && (int) $dr->id === $d['driver']['id'] ? 'selected' : '' ?>><?= h(trim($dr->fname . ' ' . $dr->lname)) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
  <div class="row g8"><button type="submit" class="sl-btn">Assign</button><button type="button" class="sl-btn ghost" data-sl-close>Cancel</button></div>
</form></div>
<?php endif; ?>
<script>
// The legacy tracking endpoint answers {success, messages[] | errors{}}; reload on success.
document.addEventListener('sl:done', function (e) {
  var f = e.target; if (!f.matches || !f.matches('form[action$="add_courier_tracking.php"]')) return;
  var j = e.detail || {};
  if (j.success) { SL.toast((j.messages || ['Status updated']).join(' ')); setTimeout(function () { location.reload(); }, 400); }
  else if (j.errors) { var m = Object.keys(j.errors).map(function (k) { return j.errors[k]; }).join(' '); if (m) SL.toast(m, true); }
}, true); // sl:done does not bubble: listen in the capture phase
</script>

<?php if (!empty($d['id'])) { require_once CDP_ROOT . "/helpers/delivery_service.php"; $deliveryLink=dl_parcelLink("air", (int)$d['id']); if ($deliveryLink): ?>
<a class="sl-btn ghost" href="<?= h($deliveryLink['href']) ?>">Delivery history and proof · <?= h($deliveryLink['state']) ?></a>
<?php endif; } ?>
