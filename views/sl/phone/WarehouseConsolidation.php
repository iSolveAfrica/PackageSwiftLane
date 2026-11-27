<?php
/**
 * M-HandOut, by consolidation: warehouse_delivery.php?view=consolidations (list) and
 * warehouse_delivery_consolidation.php?id= (one consolidation). Same $d as the web page
 * (views/sl/data/ops_forms.php sl_wdConsolData); actions post to the same legacy endpoint
 * (ajax/courier/warehouse_delivery_ajax.php: deliver_package, deliver_user, undo_package) with the
 * pickup-code dialog of dataJs/sl/ops_forms.js (SL.pickupCode), exactly like the web page.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['scripts'][] = 'dataJs/sl/ops_forms.js';
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-wh.css')) ?>">
<?php if ($d['cid'] <= 0): $slPhone['nav'] = 'staff'; ?>
<?= sl_phoneTop('Hand out', 'By consolidation', ['back' => sl_route('WarehouseDelivery')]) ?>
<main class="mx-body">
<form method="get" class="mx-srch" role="search"><input type="hidden" name="view" value="consolidations"><label><i class="ic i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($d['q']) ?>" aria-label="Search" placeholder="Consolidation, customer, locker or package" enterkeyhint="search"></label></form>
<?php if ($d['list']): ?>
<section class="mx-card mx-list">
<?php foreach ($d['list'] as $c): ?>
<a class="mx-row" href="warehouse_delivery_consolidation.php?id=<?= (int) $c['id'] ?>"><span class="mx-li"><i class="ic i-layers" aria-hidden="true"></i></span><span class="mx-rm"><b class="mono"><?= h($c['ref']) ?></b><small><?= h($c['date']) ?> · <?= (int) $c['customers'] ?> customer<?= (int) $c['customers'] === 1 ? '' : 's' ?> · <?= h(sl_weight($c['weight'], 1)) ?></small><span class="mx-trk" role="img" aria-label="<?= (int) $c['delivered'] ?> of <?= (int) $c['total'] ?> handed over" style="margin-top:6px"><i class="ok" style="width:<?= (int) $c['pct'] ?>%"></i></span></span><span class="mx-rr"><b><?= (int) $c['delivered'] ?>/<?= (int) $c['total'] ?></b><?php if ($c['awaiting']): ?><span class="sl-pill pending"><?= (int) $c['awaiting'] ?> awaiting</span><?php endif; ?></span></a>
<?php endforeach; ?>
</section>
<?php else: ?>
<?= sl_mxEmpty('warehouse', $d['q'] !== '' ? 'Nothing matches' : 'Nothing waiting', $d['q'] !== '' ? '' : 'No consolidations have packages waiting right now.') ?>
<?php endif; ?>
</main>
<?php else: $s = $d['sum']; $slPhone['nav'] = 'none'; ?>
<?= sl_phoneTop($d['ref'], (int) $s['delivered'] . '/' . (int) $s['total'] . ' handed over · ' . (int) $s['customers'] . ' customers', ['back' => 'warehouse_delivery.php?view=consolidations']) ?>
<main class="mx-body" id="wc" data-cid="<?= (int) $d['cid'] ?>">
<?php if ($s['awaiting']): ?><div class="mx-ban warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span><?= (int) $s['awaiting'] ?> package<?= $s['awaiting'] === 1 ? '' : 's' ?> awaiting clearance by Accounts.</span></div><?php endif; ?>
<div class="mx-f"><small id="wc-kl">How is it handed over?</small><div class="mx-fseg" id="wc-kind" role="group" aria-labelledby="wc-kl"><button type="button" class="on" data-v="picked_up" aria-pressed="true">Picked up</button><button type="button" data-v="delivered" aria-pressed="false">Delivered</button></div></div>
<?php foreach ($d['customers'] as $c): $ready = array_values(array_filter($c['pkgs'], fn($p) => $p['state'] === 'ready')); ?>
<section class="mx-card mx-list">
<div class="mx-row"><span class="mx-ava"><?= h($c['ini']) ?></span><span class="mx-rm"><b><?= h($c['name']) ?></b><small><?= h($c['locker']) ?> · <?= count($c['pkgs']) ?> package<?= count($c['pkgs']) === 1 ? '' : 's' ?> · <?= (int) $c['ready'] ?> ready</small></span></div>
<?php foreach ($c['pkgs'] as $p): ?>
<div class="mx-row"><span class="mx-rm"><b class="mono"><?= h($p['track']) ?></b><small><?= h(implode(', ', array_map(fn($i) => $i['q'] . '× ' . $i['t'], $p['items'])) ?: $p['st']) ?> · <?= h($p['w']) ?></small></span><span class="mx-rr">
<?php if ($p['state'] === 'ready' && $d['canPkg']): ?><button type="button" class="mx-lnk" data-wc="deliver_package" data-no="<?= h($p['no']) ?>" data-ids="<?= (int) $p['oid'] ?>" data-owner="<?= h($c['name']) ?>">Hand over</button>
<?php elseif ($p['state'] === 'delivered'): ?><span class="sl-pill delivered"><?= h($p['label']) ?></span><?php if ($d['canUndo']): ?><button type="button" class="mx-lnk" data-wc="undo_package" data-no="<?= h($p['no']) ?>">Undo</button><?php endif; ?>
<?php elseif ($p['state'] === 'awaiting'): ?><span class="sl-pill pending">Awaiting clearance</span>
<?php else: ?><span class="sl-pill <?= h($p['pc']) ?>"><?= h($p['st']) ?></span><?php endif; ?>
</span></div>
<?php endforeach; ?>
<?php if ($d['canUser'] && $c['ready'] > 0): ?><div class="mx-row"><button type="button" class="mx-btn" data-wc="deliver_user" data-sid="<?= (int) $c['sid'] ?>" data-owner="<?= h($c['name']) ?>" data-ids="<?= h(implode(',', array_map(fn($p) => $p['oid'], $ready))) ?>">Hand over all ready (<?= (int) $c['ready'] ?>)</button></div><?php endif; ?>
</section>
<?php endforeach; ?>
<?php if (!$d['customers']): ?><?= sl_mxEmpty('box', 'No packages in this consolidation') ?><?php endif; ?>
</main>
<script>
(function () {
  var root = document.getElementById('wc'); if (!root) return;
  var cid = root.getAttribute('data-cid'), kind = 'picked_up', URL = 'ajax/courier/warehouse_delivery_ajax.php';
  var seg = document.getElementById('wc-kind');
  seg.addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; kind = b.getAttribute('data-v'); seg.querySelectorAll('button').forEach(function (x) { x.classList.toggle('on', x === b); x.setAttribute('aria-pressed', String(x === b)); }); });
  function run(b) {
    var act = b.getAttribute('data-wc'), data = { action: act, consolidate_id: cid, handover_type: kind };
    if (b.getAttribute('data-no')) data.order_no = b.getAttribute('data-no');
    if (b.getAttribute('data-sid')) data.sender_id = b.getAttribute('data-sid');
    b.disabled = true;
    SL.post(URL, data).then(function (j) {
      if (j.ok) { SL.toast(j.message || (act === 'undo_package' ? 'Reopened' : 'Handed over')); setTimeout(function () { location.reload(); }, 500); return; }
      if (j.pickup_code && SL.pickupCode) { SL.toast(j.message || 'Pickup code required', true); SL.pickupCode(j.pickup_code.module || 'air', (j.pickup_code.order_ids || []).map(String), b.getAttribute('data-owner') || '', function () { run(b); }); return; }
      SL.toast(j.message || 'Not saved', true);
    }).catch(function () { SL.toast('Network error', true); }).finally(function () { b.disabled = false; });
  }
  root.addEventListener('click', function (e) {
    var b = e.target.closest('[data-wc]'); if (!b) return;
    if (b.getAttribute('data-wc') === 'undo_package' && !confirm('Reopen this package? It goes back to the warehouse.')) return;
    if (b.getAttribute('data-wc') === 'deliver_user' && !confirm('Hand over every ready package for ' + b.getAttribute('data-owner') + '?')) return;
    run(b);
  });
})();
</script>
<?php endif; ?>
