<?php
/**
 * AirShipments - from docs/handover/artifact-source/AirShipments.dc.html.
 * Data: views/sl/data/shipments.php (sl_airShipmentsData).
 */
if (!isset($d)) { $d = []; }
$client = !empty($d['client']);
?>
        <div class="sl-head">
          <div><h1 class="sl-title"><?= $client ? 'My Shipments' : 'Air Shipments' ?></h1><p class="sl-lede row g8" style="align-items:center"><span class="chan v"><i class="ic ic-sm i-spark" aria-hidden="true"></i></span><span><b class="fw5">Insight</b> · <?= sl_num($d['month']) ?> registered this month<?php if ($d['attention']): ?> · <?= (int) $d['attention'] ?> need attention: <?php $bits = []; if ($d['customs']) $bits[] = '<a href="' . h(sl_url(['filter' => 'customs', 'page' => null])) . '">' . (int) $d['customs'] . ' held / at customs</a>'; if ($d['unpaid']) $bits[] = '<a href="' . h(sl_url(['filter' => 'unpaid', 'page' => null])) . '">' . (int) $d['unpaid'] . ' unpaid</a>'; if ($d['noCarrier']) $bits[] = '<a href="' . h(sl_url(['filter' => 'nocarrier', 'page' => null])) . '">' . (int) $d['noCarrier'] . ' missing a carrier number</a>'; echo implode(', ', $bits); ?><?php else: ?> · nothing needs attention<?php endif; ?></span></p></div>
          <div class="row g8 wrap"><?php if (!$client): ?><a class="sl-btn ghost" href="<?= sl_route('SeaPackages') ?>"><i class="ic ic-sm i-layers" aria-hidden="true"></i>Package management</a><a class="sl-btn ghost" href="<?= sl_route('StaffAI', ['q' => 'Which air shipments need attention today?']) ?>"><i class="ic ic-sm i-spark" aria-hidden="true"></i>Ask AI</a><?php endif; ?><?php if ($d['canImport']): ?><a class="sl-btn ghost" href="<?= sl_route('ImportData') ?>"><i class="ic ic-sm i-upload" aria-hidden="true"></i>Import Excel</a><?php endif; ?><a class="sl-btn ghost" href="<?= h(sl_url(['export' => 'csv'])) ?>"><i class="ic ic-sm i-download" aria-hidden="true"></i>Export</a><?php if ($d['canAdd']): ?><a class="sl-btn" href="<?= $client ? 'courier_add_client.php' : sl_route('NewShipment') ?>"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New Shipment</a><?php endif; ?></div>
        </div>

        <div class="grid cols4">
          <?php foreach ($d['metrics'] as $i => $m): $f = ['open', 'delivered', 'unpaid', 'consol'][$i]; ?>
          <a class="sl-card sl-metric" href="<?= h(sl_url(['filter' => $f, 'page' => null])) ?>"><div class="sl-ml"><span><?= h($m['label']) ?></span></div><div class="sl-mv"><?= sl_num($m['v']) ?> <i>/ <?= sl_num($d['all']) ?></i></div><div class="sl-ticks <?= h($m['cls']) ?>"><i style="width: <?= sl_pct($m['v'], $d['all']) ?>%"></i></div></a>
          <?php endforeach; ?>
        </div>

        <div class="sl-card flush" id="as-list">
          <form method="get" class="row wrap g12" style="padding: 14px 16px">
            <input type="hidden" name="filter" value="<?= h($d['filter']) ?>"><input type="hidden" name="period" value="<?= h($d['period']) ?>">
            <div class="sl-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input name="q" value="<?= h($d['q']) ?>" aria-label="Search shipments" placeholder="Search tracking, recipient or sender"></div>
            <label class="sl-sel native" style="width: 170px"><select name="status" aria-label="Status" onchange="this.form.submit()"><option value="">All statuses</option><?php foreach ($d['statuses'] as $s): ?><option value="<?= (int) $s->id ?>" <?= (int) $s->id === $d['status'] ? 'selected' : '' ?>><?= h(str_replace('_', ' ', $s->mod_style)) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></label>
            <?php if (!$client): ?><label class="sl-sel native" style="width: 160px"><select name="driver" aria-label="Rider" onchange="this.form.submit()"><option value="">All riders</option><?php foreach ($d['drivers'] as $dr): ?><option value="<?= (int) $dr->id ?>" <?= (int) $dr->id === $d['driver'] ? 'selected' : '' ?>><?= h(trim($dr->fname . ' ' . $dr->lname)) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></label><?php endif; ?>
            <div class="sl-seg boxed"><?php foreach ([['all', 'All time'], ['30d', '30d'], ['7d', '7d'], ['today', 'Today']] as $p): ?><a class="<?= $p[0] === $d['period'] ? 'on' : '' ?>" href="<?= h(sl_url(['period' => $p[0], 'page' => null])) ?>"><?= h($p[1]) ?></a><?php endforeach; ?></div>
            <div class="sl-seg boxed"><?php foreach ($d['filters'] as $x): ?><a class="<?= $x['f'] === $d['filter'] ? 'on' : '' ?>" href="<?= h(sl_url(['filter' => $x['f'], 'page' => null])) ?>"><?= h($x['label']) ?></a><?php endforeach; ?></div>
            <span class="grow"></span>
            <span class="mut sm">Rows</span><label class="sl-sel native" style="width: 80px"><select name="per" aria-label="Rows per page" onchange="this.form.submit()"><?php foreach ([25, 50, 100] as $n): ?><option <?= $n === $d['per'] ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></label>
          </form>
          <?php if ($d['canSelect']): ?>
          <div class="row g12 wrap" style="padding: 10px 16px; background: var(--active)" id="as-bulk" hidden style="display:none">
            <strong><span data-as-count>0</span> selected</strong><span class="grow"></span>
            <?php if ($d['canStatus']): ?><button type="button" class="sl-btn ghost sm" data-sl-open="as-status"><i class="ic ic-sm i-refresh" aria-hidden="true"></i>Update status</button><?php endif; ?>
            <?php if ($d['canAssign']): ?><button type="button" class="sl-btn ghost sm" data-sl-open="as-driver" data-as-bulk-driver><i class="ic ic-sm i-wheel" aria-hidden="true"></i>Assign rider</button><?php endif; ?>
            <?php if ($d['canLabel']): ?><button type="button" class="sl-btn ghost sm" data-as-labels><i class="ic ic-sm i-print" aria-hidden="true"></i>Print labels</button><?php endif; ?>
            <button type="button" class="sl-btn ghost sm" style="border-color: transparent; background: transparent" data-as-clear>Clear</button>
          </div>
          <?php endif; ?>
          <div class="sl-tw">
            <table class="sl-table">
              <thead><tr><?php if ($d['canSelect']): ?><th style="width: 40px"><label class="sl-chk" aria-label="Select all"><input type="checkbox" data-as-all style="position:absolute;opacity:0;width:0;height:0"></label></th><?php endif; ?><th>Swift tracking</th><th>Carrier tracking</th><th>Date &amp; time</th><?php if (!$client): ?><th>Sender</th><?php endif; ?><th>Recipient</th><th><abbr title="Estimated time of arrival">ETA</abbr></th><th class="num">Weight</th><th>Payment</th><th>Status</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($d['rows'] as $r): $cols = 11 - ($client ? 1 : 0) - ($d['canSelect'] ? 0 : 1); ?>
                  <tr data-as-row>
                    <?php if ($d['canSelect']): ?><td><?php if ($r['selectable']): ?><label class="sl-chk" aria-label="Select <?= h($r['track']) ?>"><input type="checkbox" value="<?= (int) $r['id'] ?>" data-as-pick data-track="<?= h($r['track']) ?>" style="position:absolute;opacity:0;width:0;height:0"></label><?php endif; ?></td><?php endif; ?>
                    <td><a class="id" href="<?= sl_route('ShipmentDetail', ['id' => $r['id']]) ?>"><?= h($r['track']) ?></a></td>
                    <td class="mono mut sm"><?= $r['carrier'] !== '' ? h($r['carrier']) : '—' ?></td>
                    <td class="nowrap"><?= h($r['date']) ?><span class="sub mono"><?= h($r['time'] ?: 'Time not recorded') ?></span></td>
                    <?php if (!$client): ?><td><div><a href="<?= sl_route('CustomerProfile', ['id' => $r['sender_id']]) ?>" style="color:inherit"><?= h($r['sender']) ?></a><span class="sub"><?= h($r['from']) ?></span></div></td><?php endif; ?>
                    <td><div class="sl-cell"><span class="sl-av sm"><?= h($r['ini']) ?></span><?= h($r['name']) ?></div></td>
                    <td><?= h($r['eta'] !== '' && $r['eta'] !== 'N/A' ? $r['eta'] : 'Not set') ?></td>
                    <td class="num"><?= h($r['kg']) ?></td>
                    <td><span class="col g4"><span class="sl-pill <?= h($r['payCls']) ?>"><?= h($r['pay']) ?></span><span class="mut mono" style="font-size:13.5px"><?= h($r['total']) ?></span></span></td>
                    <td><span class="col g4"><span class="sl-pill <?= h($r['stCls']) ?>"><?= h($r['st']) ?></span><?php foreach ($r['badges'] as $b): ?><span class="mut" style="font-size:13.5px"><?= h($b) ?></span><?php endforeach; ?></span></td>
                    <td class="as-dots"><button type="button" class="as-more" aria-label="More actions for <?= h($r['track']) ?>" aria-expanded="false" data-as-toggle><i class="ic i-more" aria-hidden="true"></i></button></td>
                  </tr>
                  <tr class="as-mrow" hidden><td colspan="<?= $cols ?>"><div class="as-menu" role="menu" aria-label="Actions for <?= h($r['track']) ?>"><div class="as-mh"><b class="fw5"><?= h($r['track']) ?></b><span class="mut sm"><?= h($r['name']) ?> · <?= h($r['dest']) ?></span><span class="grow"></span><button type="button" class="sl-ib" style="width:34px;height:34px" aria-label="Close" data-as-toggle><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div><div class="as-acts"><?php foreach ($r['acts'] as $a): ?><?php if (!empty($a['action'])): ?><button type="button" class="as-a <?= h($a['cls']) ?>" role="menuitem" data-sl-action="ajax/sl/shipment_action_ajax.php" data-p-op="<?= h($a['action']) ?>" data-p-id="<?= (int) $r['id'] ?>" data-sl-confirm="<?= $a['action'] === 'delete' ? 'Delete shipment ' . h($r['track']) . '? It is marked cancelled and hidden from lists.' : 'Cancel shipment ' . h($r['track']) . '?' ?>" data-sl-reload><i class="ic ic-sm <?= h($a['icon']) ?>" aria-hidden="true"></i><?= h($a['label']) ?></button><?php elseif (!empty($a['dialog'])): ?><button type="button" class="as-a" role="menuitem" data-sl-open="as-driver" data-as-driver-id="<?= (int) $r['id'] ?>" data-as-driver-cur="<?= (int) $r['driver_id'] ?>"><i class="ic ic-sm <?= h($a['icon']) ?>" aria-hidden="true"></i><?= h($a['label']) ?></button><?php else: ?><a class="as-a <?= h($a['cls']) ?>" role="menuitem" href="<?= h($a['href']) ?>"<?= !empty($a['blank']) ? ' target="_blank"' : '' ?>><i class="ic ic-sm <?= h($a['icon']) ?>" aria-hidden="true"></i><?= h($a['label']) ?></a><?php endif; ?><?php endforeach; ?></div></div></td></tr>
                <?php endforeach; ?>
                <?php if (!$d['rows']): ?><tr><td colspan="12"><div class="sl-empty"><span class="sl-tile"><i class="ic i-plane" aria-hidden="true"></i></span>No shipments match this search.</div></td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
          <?= sl_pager($d['page'], $d['per'], $d['count'], 'shipments') ?>
        </div>
<?php if ($d['canAssign']): ?>
<div class="sl-dialog" id="as-driver" hidden><form class="sl-modal" data-sl-form data-sl-reload action="ajax/sl/shipment_action_ajax.php" method="post" role="dialog" aria-label="Assign rider">
  <input type="hidden" name="op" value="driver"><input type="hidden" name="ids" id="as-driver-ids" value="">
  <div class="row g10"><span class="sl-tile"><i class="ic i-wheel" aria-hidden="true"></i></span><div class="col grow"><b class="fw5">Assign a rider</b><span class="mut sm" id="as-driver-sub">The driver is notified and the shipment appears on their route.</span></div></div>
  <label class="sl-field"><span class="sl-lab">Rider</span><span class="sl-sel native"><select name="driver_id" id="as-driver-sel" required><option value="">Choose a rider</option><?php foreach ($d['drivers'] as $dr): ?><option value="<?= (int) $dr->id ?>"><?= h(trim($dr->fname . ' ' . $dr->lname)) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
  <div class="row g8"><button type="submit" class="sl-btn">Assign</button><button type="button" class="sl-btn ghost" data-sl-close>Cancel</button></div>
</form></div>
<?php endif; ?>
<?php if ($d['canStatus'] && $d['canSelect']): ?>
<div class="sl-dialog" id="as-status" hidden><form class="sl-modal" data-sl-form data-sl-reload action="ajax/sl/shipment_action_ajax.php" method="post" role="dialog" aria-label="Update status">
  <input type="hidden" name="op" value="status_bulk"><input type="hidden" name="ids" id="as-status-ids" value="">
  <div class="row g10"><span class="sl-tile"><i class="ic i-refresh" aria-hidden="true"></i></span><div class="col grow"><b class="fw5">Update the status of the selected shipments</b><span class="mut sm">A tracking event is added to each one. Hand-over statuses need the pickup code, use the detail page for those.</span></div></div>
  <label class="sl-field"><span class="sl-lab">New status</span><span class="sl-sel native"><select name="status_courier" required><option value="">Choose a status</option><?php foreach ($d['statuses'] as $s): ?><option value="<?= (int) $s->id ?>"><?= h(str_replace('_', ' ', $s->mod_style)) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
  <label class="sl-field"><span class="sl-lab">Note (optional)</span><input class="sl-in" name="comments" maxlength="250" placeholder="Shown in the tracking history"></label>
  <div class="row g8"><button type="submit" class="sl-btn">Update</button><button type="button" class="sl-btn ghost" data-sl-close>Cancel</button></div>
</form></div>
<?php endif; ?>
<script>
(function () {
  var picks = function () { return Array.prototype.slice.call(document.querySelectorAll('[data-as-pick]:checked')); };
  var bulk = document.getElementById('as-bulk');
  function sync() {
    var n = picks().length;
    if (bulk) { bulk.hidden = n === 0; bulk.style.display = n ? "" : "none"; var c = bulk.querySelector('[data-as-count]'); if (c) c.textContent = n; }
    document.querySelectorAll('[data-as-pick]').forEach(function (i) { i.closest('.sl-chk').classList.toggle('on', i.checked); });
    var all = document.querySelector('[data-as-all]'); if (all) all.closest('.sl-chk').classList.toggle('on', all.checked);
  }
  document.addEventListener('change', function (e) {
    if (e.target.matches('[data-as-all]')) { document.querySelectorAll('[data-as-pick]').forEach(function (i) { i.checked = e.target.checked; }); }
    if (e.target.matches('[data-as-pick],[data-as-all]')) sync();
  });
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-as-toggle]');
    if (t) {
      var row = t.closest('tr'); var menu = row.classList.contains('as-mrow') ? row : row.nextElementSibling;
      var open = menu.hidden; document.querySelectorAll('.as-mrow').forEach(function (m) { m.hidden = true; }); document.querySelectorAll('.as-more').forEach(function (b) { b.classList.remove('on'); b.setAttribute('aria-expanded', 'false'); });
      if (open) { menu.hidden = false; var b = menu.previousElementSibling.querySelector('.as-more'); b.classList.add('on'); b.setAttribute('aria-expanded', 'true'); }
      return;
    }
    if (e.target.closest('[data-as-clear]')) { document.querySelectorAll('[data-as-pick],[data-as-all]').forEach(function (i) { i.checked = false; }); sync(); }
    var lb = e.target.closest('[data-as-labels]');
    if (lb) { var ids = picks().map(function (i) { return i.value; }); if (ids.length) window.open('print_label_ship.php?ids=' + ids.join(','), '_blank'); }
    var dv = e.target.closest('[data-as-driver-id]');
    if (dv) { document.getElementById('as-driver-ids').value = dv.getAttribute('data-as-driver-id'); var s = document.getElementById('as-driver-sel'); if (s) s.value = dv.getAttribute('data-as-driver-cur') || ''; document.getElementById('as-driver-sub').textContent = 'Shipment ' + dv.closest('.as-menu').querySelector('.as-mh b').textContent; }
    if (e.target.closest('[data-as-bulk-driver]')) { document.getElementById('as-driver-ids').value = picks().map(function (i) { return i.value; }).join(','); document.getElementById('as-driver-sub').textContent = picks().length + ' shipments selected'; }
    if (e.target.closest('[data-sl-open="as-status"]')) { document.getElementById('as-status-ids').value = picks().map(function (i) { return i.value; }).join(','); }
  });
  sync();
})();
</script>
