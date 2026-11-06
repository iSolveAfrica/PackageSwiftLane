<?php
/**
 * WarehouseConsolidation - warehouse delivery by consolidation (look of
 * docs/handover/artifact-source/WarehouseDelivery.dc.html). Replaces the old consolidation tree
 * (old warehouse_delivery.php?legacy=1 view and warehouse_delivery_consolidation.php).
 * List: warehouse_delivery.php?view=consolidations. One consolidation: warehouse_delivery_consolidation.php?id=.
 * Data: views/sl/data/ops_forms.php (sl_wdConsolData). Actions post to the legacy
 * ajax/courier/warehouse_delivery_ajax.php (deliver_package, deliver_user, undo_package);
 * pickup codes through the dialog in dataJs/sl/ops_forms.js.
 */
if (!isset($d)) { $d = []; }
?>
<?php if ($d['cid'] <= 0): ?>
<div class="sl-head"><div><a href="<?= sl_route('WarehouseDelivery') ?>" class="row g6 mut sm"><i class="ic ic-sm i-left" aria-hidden="true"></i>Warehouse Delivery</a><h1 class="sl-title" style="font-size:20px;margin-top:4px">By consolidation</h1><p class="sl-lede"><?= $d['finance'] ? 'Consolidations with packages Accounts has cleared for delivery.' : 'Consolidations with packages waiting in Ghana.' ?></p></div><form method="get" class="sl-search" style="min-width:280px"><input type="hidden" name="view" value="consolidations"><i class="ic ic-sm i-search" aria-hidden="true"></i><input name="q" value="<?= h($d['q']) ?>" placeholder="Consolidation, customer, locker or package" aria-label="Search"></form></div>
<section class="sl-card flush"><div class="sl-tw"><table class="sl-table"><thead><tr><th>Consolidation</th><th>Date</th><th class="num">Weight</th><th class="num">Customers</th><th>Handed over</th><th>Awaiting clearance</th><th></th></tr></thead><tbody>
<?php foreach ($d['list'] as $c): ?><tr><td><a class="id mono" href="warehouse_delivery_consolidation.php?id=<?= (int) $c['id'] ?>"><?= h($c['ref']) ?></a></td><td class="sm"><?= h($c['date']) ?></td><td class="num mono"><?= h(sl_weight($c['weight'], 2)) ?></td><td class="num mono"><?= (int) $c['customers'] ?></td><td><div class="row g8"><div class="sl-ticks" style="width:90px" role="img" aria-label="<?= (int) $c['pct'] ?>%"><i style="width:<?= (int) $c['pct'] ?>%"></i></div><span class="mono sm"><?= (int) $c['delivered'] ?>/<?= (int) $c['total'] ?></span><?php if ($c['total'] > 0 && $c['delivered'] >= $c['total']): ?><span class="sl-pill delivered">Handed over</span><?php endif; ?></div></td><td><?php if ($c['awaiting']): ?><span class="sl-pill pending"><?= (int) $c['awaiting'] ?> awaiting</span><?php else: ?><span class="mut sm">—</span><?php endif; ?></td><td><a class="sl-btn ghost sm" href="warehouse_delivery_consolidation.php?id=<?= (int) $c['id'] ?>">Open<i class="ic ic-sm i-right" aria-hidden="true"></i></a></td></tr><?php endforeach; ?>
</tbody></table></div><?php if (!$d['list']): ?><div class="sl-empty"><i class="ic ic-lg i-warehouse" aria-hidden="true"></i><?= $d['q'] !== '' ? 'Nothing matches "' . h($d['q']) . '".' : 'No consolidations have packages waiting right now.' ?></div><?php endif; ?></section>
<?php return; endif; $s = $d['sum']; ?>
<div class="sl-head"><div><a href="warehouse_delivery.php?view=consolidations" class="row g6 mut sm"><i class="ic ic-sm i-left" aria-hidden="true"></i>All consolidations</a><h1 class="sl-title mono" style="font-size:20px;margin-top:4px"><?= h($d['ref']) ?></h1><p class="sl-lede"><?= h($d['date']) ?> · <?= h(sl_weight($s['weight'], 2)) ?> · <?= (int) $s['customers'] ?> customers</p></div>
<div class="row g8 wrap"><span class="sl-badge"><?= (int) $s['delivered'] ?>/<?= (int) $s['total'] ?> handed over</span><?php if ($s['awaiting']): ?><span class="sl-pill pending"><?= (int) $s['awaiting'] ?> awaiting clearance</span><?php endif; ?><div class="sl-seg boxed" id="wc-kind"><button type="button" class="on" data-v="picked_up">Picked up</button><button type="button" data-v="delivered">Delivered</button></div></div></div>
<div class="col g12" id="wc" data-cid="<?= (int) $d['cid'] ?>">
<?php foreach ($d['customers'] as $c): ?>
<section class="sl-card col g10">
  <div class="row between wrap g8"><div class="row g10"><span class="sl-av"><?= h($c['ini']) ?></span><div class="col"><b class="fw5"><?= h($c['name']) ?></b><span class="mut sm"><?= h($c['locker']) ?> · <?= count($c['pkgs']) ?> package(s) · <?= (int) $c['delivered'] ?> handed over · <?= (int) $c['ready'] ?> ready</span></div></div>
  <?php if ($d['canUser'] && $c['ready'] > 0): ?><button type="button" class="sl-btn sm" data-wc="deliver_user" data-sid="<?= (int) $c['sid'] ?>" data-owner="<?= h($c['name']) ?>" data-ids="<?= h(implode(',', array_map(fn($p) => $p['oid'], array_filter($c['pkgs'], fn($p) => $p['state'] === 'ready')))) ?>"><i class="ic ic-sm i-check" aria-hidden="true"></i>Hand over all ready (<?= (int) $c['ready'] ?>)</button><?php endif; ?></div>
  <div class="sl-tw"><table class="sl-table"><tbody>
  <?php foreach ($c['pkgs'] as $p): ?><tr><td><a class="id mono" href="<?= sl_route('ShipmentDetail', ['id' => $p['oid']]) ?>"><?= h($p['track']) ?></a><?php if ($p['carrier']): ?><div class="mut mono" style="font-size:12px"><?= h($p['carrier']) ?></div><?php endif; ?></td><td class="sm"><?= h(implode(', ', array_map(fn($i) => $i['q'] . '× ' . $i['t'], $p['items']))) ?></td><td class="num mono"><?= h($p['w']) ?></td><td><span class="sl-pill <?= h($p['pc']) ?>"><?= h($p['st']) ?></span></td>
  <td><?php if ($p['state'] === 'delivered'): ?><span class="sl-pill delivered"><?= h($p['label']) ?></span><?php elseif ($p['state'] === 'ready'): ?><span class="sl-pill paid">Cleared · ready</span><?php elseif ($p['state'] === 'awaiting'): ?><span class="sl-pill pending">Awaiting clearance</span><?php endif; ?></td>
  <td class="num"><?php if ($p['state'] === 'ready' && $d['canPkg']): ?><button type="button" class="sl-btn ghost sm" data-wc="deliver_package" data-no="<?= h($p['no']) ?>" data-ids="<?= (int) $p['oid'] ?>" data-owner="<?= h($c['name']) ?>">Hand over</button><?php elseif ($p['state'] === 'delivered' && $d['canUndo']): ?><button type="button" class="sl-btn ghost sm" data-wc="undo_package" data-no="<?= h($p['no']) ?>">Undo</button><?php endif; ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>
<?php endforeach; ?>
<?php if (!$d['customers']): ?><div class="sl-card sl-empty">No packages in this consolidation.</div><?php endif; ?>
</div>
<script>
(function () {
  var root = document.getElementById('wc'); if (!root) return;
  var cid = root.getAttribute('data-cid'), kind = 'picked_up', URL = 'ajax/courier/warehouse_delivery_ajax.php';
  var seg = document.getElementById('wc-kind');
  seg.addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; kind = b.getAttribute('data-v'); seg.querySelectorAll('button').forEach(function (x) { x.classList.toggle('on', x === b); }); });
  function run(b) {
    var act = b.getAttribute('data-wc'), data = { action: act, consolidate_id: cid, handover_type: kind };
    if (b.getAttribute('data-no')) data.order_no = b.getAttribute('data-no');
    if (b.getAttribute('data-sid')) data.sender_id = b.getAttribute('data-sid');
    b.classList.add('loading'); b.disabled = true;
    SL.post(URL, data).then(function (j) {
      if (j.ok) { SL.toast(j.message || (act === 'undo_package' ? 'Reopened' : 'Handed over')); setTimeout(function () { location.reload(); }, 500); return; }
      if (j.pickup_code) { SL.toast(j.message || 'Pickup code required', true); SL.pickupCode(j.pickup_code.module || 'air', (j.pickup_code.order_ids || []).map(String), b.getAttribute('data-owner') || '', function () { run(b); }); return; }
      SL.toast(j.message || 'Not saved', true);
    }).catch(function () { SL.toast('Network error', true); }).finally(function () { b.classList.remove('loading'); b.disabled = false; });
  }
  root.addEventListener('click', function (e) {
    var b = e.target.closest('[data-wc]'); if (!b) return;
    if (b.getAttribute('data-wc') === 'undo_package' && !confirm('Reopen this package? It goes back to the warehouse.')) return;
    if (b.getAttribute('data-wc') === 'deliver_user' && !confirm('Hand over every ready package for ' + b.getAttribute('data-owner') + '?')) return;
    run(b);
  });
})();
</script>
