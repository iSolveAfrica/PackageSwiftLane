<?php
/**
 * SeaPackages - from docs/handover/artifact-source/SeaPackages.dc.html.
 * Data: views/sl/data/packages.php (sl_seaPackagesData).
 */
if (!isset($d)) { $d = []; }
$can = $d['can'];
?>
        <div class="sl-head">
          <div><h1 class="sl-title"><?= $d['isClient'] ? 'My Packages' : 'Sea Packages' ?></h1><p class="sl-lede"><?= h($d['lede']) ?></p></div>
          <div class="row g8 wrap"><a class="sl-btn ghost" href="<?= h(sl_url(['export' => 'csv'])) ?>"><i class="ic ic-sm i-download" aria-hidden="true"></i>Export</a><?php if (!$d['isClient']): ?><a class="sl-btn ghost" href="<?= sl_route('Consolidations', ['mode' => 'sea']) ?>"><i class="ic ic-sm i-layers" aria-hidden="true"></i>Containers</a><?php endif; ?><?php if ($d['canAdd']): ?><a class="sl-btn" href="<?= sl_route('NewPackage') ?>"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New Package</a><?php endif; ?></div>
        </div>
        <div class="grid cols4">
          <?php foreach ($d['kpi'] as $k): ?>
          <a class="sl-card sl-metric" href="<?= h($k['href']) ?>" style="color:inherit"><div class="sl-ml"><span><i class="ic ic-sm <?= h($k['icon']) ?>" aria-hidden="true"></i><?= h($k['label']) ?></span></div><div class="sl-mv"><?= sl_num($k['n']) ?> <i>/ <?= sl_num($d['total']) ?></i></div><div class="sl-ticks <?= h($k['cls']) ?>"><i style="width: <?= (int) $k['pct'] ?>%"></i></div></a>
          <?php endforeach; ?>
        </div>
        <div class="sl-card flush" data-sl-scope>
          <form method="get" class="row wrap g12" style="padding: 14px 16px" id="sp-filter">
            <input type="hidden" name="filter" value="<?= h($d['filter']) ?>"><input type="hidden" name="view" value="<?= h($d['view']) ?>">
            <div class="sl-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input name="q" value="<?= h($d['q']) ?>" aria-label="Search packages" placeholder="Search package, customer or container"></div>
            <label class="sl-sel native" style="width: 160px"><select name="status" aria-label="Status" onchange="this.form.submit()"><option value="0">All statuses</option><?php foreach ($d['statuses'] as $s): ?><option value="<?= (int) $s->id ?>" <?= (int) $s->id === $d['status'] ? 'selected' : '' ?>><?= h(str_replace('_', ' ', $s->mod_style)) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></label>
            <label class="sl-sel native" style="width: 170px"><select name="container" aria-label="Container" onchange="this.form.submit()"><option value="0">All containers</option><?php foreach ($d['containers'] as $c): ?><option value="<?= (int) $c->consolidate_id ?>" <?= (int) $c->consolidate_id === $d['container'] ? 'selected' : '' ?>><?= h($c->c_prefix . $c->c_no) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></label>
            <div class="sl-seg boxed"><?php foreach ($d['filters'] as $x): ?><a class="<?= $x['f'] === $d['filter'] ? 'on' : '' ?>" href="<?= h(sl_url(['filter' => $x['f'], 'page' => null])) ?>"><?= h($x['label']) ?></a><?php endforeach; ?></div>
            <span class="grow"></span>
            <div class="sl-seg boxed"><a class="<?= $d['view'] === 'list' ? 'on' : '' ?>" href="<?= h(sl_url(['view' => 'list', 'page' => null])) ?>">List</a><a class="<?= $d['view'] === 'container' ? 'on' : '' ?>" href="<?= h(sl_url(['view' => 'container', 'page' => null])) ?>">By container</a></div>
          </form>
          <?php if ($can['bulk']): ?>
          <div class="row wrap g8" style="padding: 0 16px 12px" id="sp-bulk" hidden><span class="sm mut"><b id="sp-n">0</b> selected</span>
            <?php if ($can['bulkStatus']): ?><label class="sl-sel native" style="width:190px"><select id="sp-bulk-status" aria-label="New status"><option value="">Change status…</option><?php foreach ($d['statuses'] as $s): ?><option value="<?= (int) $s->id ?>"><?= h(str_replace('_', ' ', $s->mod_style)) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></label><button type="button" class="sl-btn ghost sm" id="sp-bulk-go">Apply</button><?php endif; ?>
            <?php if ($can['driver']): ?><label class="sl-sel native" style="width:190px"><select id="sp-bulk-driver" aria-label="Driver"><option value="">Assign driver…</option><?php foreach ($d['drivers'] as $dr): ?><option value="<?= (int) $dr->id ?>"><?= h($dr->fname . ' ' . $dr->lname) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></label><button type="button" class="sl-btn ghost sm" id="sp-bulk-driver-go">Assign</button><?php endif; ?>
            <?php if ($can['label']): ?><button type="button" class="sl-btn ghost sm" id="sp-bulk-labels"><i class="ic ic-sm i-print" aria-hidden="true"></i>Print labels</button><?php endif; ?>
          </div>
          <?php endif; ?>
          <div class="sl-tw"><table class="sl-table">
            <thead><tr><?php if ($can['bulk']): ?><th style="width: 40px"><input type="checkbox" id="sp-all" aria-label="Select all"></th><?php endif; ?><th data-sort>Package</th><?php if (!$d['isClient']): ?><th data-sort>Customer</th><?php endif; ?><th>Container</th><th>Items</th><th class="num" data-sort>Weight</th><th class="num" data-sort>Total</th><th>Payment</th><th>Status</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($d['rows'] as $r): ?>
                <tr data-sl-row><?php if ($can['bulk']): ?><td><?php if (!$r['done'] && !$r['cancelled']): ?><input type="checkbox" class="sp-chk" value="<?= (int) $r['id'] ?>" data-trk="<?= h($r['trk']) ?>" aria-label="Select <?= h($r['trk']) ?>"><?php endif; ?></td><?php endif; ?>
                  <td><a class="id" href="<?= sl_route('PackageDetail', ['id' => $r['id']]) ?>"><?= h($r['trk']) ?></a><span class="sub"><?= h($r['date']) ?><?= $r['prealert'] ? ' · pre-alert' : '' ?></span></td>
                  <?php if (!$d['isClient']): ?><td><div class="sl-cell"><span class="sl-av sm"><?= h($r['ini']) ?></span><span><span class="fw5"><?= h($r['name']) ?></span><span class="sub mono"><?= h($r['locker']) ?></span></span></div></td><?php endif; ?>
                  <td class="mono sm"><?php if ($r['boxId']): ?><a href="<?= sl_route('ConsolidationDetail', ['id' => $r['boxId'], 'type' => 'sea']) ?>"><?= h($r['box']) ?></a><?php else: ?>—<?php endif; ?></td>
                  <td><?= (int) $r['items'] ?><span class="sub" style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block"><?= h($r['what']) ?></span></td>
                  <td class="num"><?= h($r['w']) ?></td><td class="num"><?= h($r['amt']) ?></td>
                  <td><span class="sl-pill <?= h($r['payCls']) ?>"><?= h($r['pay']) ?></span></td>
                  <td><span class="col"><span class="sl-pill <?= h($r['stCls']) ?>"><?= h($r['st']) ?></span><?php if ($r['inCon']): ?><span class="mut" style="font-size:11.5px">in consolidation</span><?php elseif ($r['driver'] !== '—'): ?><span class="mut" style="font-size:11.5px">Driver <?= h($r['driver']) ?></span><?php endif; ?></span></td>
                  <td><details class="sl-menu" data-sl-pop><summary class="sl-ib sm" aria-label="Actions for <?= h($r['trk']) ?>"><i class="ic i-more" aria-hidden="true"></i></summary><div class="pop" role="menu">
                    <?php if ($can['view'] || $d['isClient']): ?><a role="menuitem" href="<?= sl_route('PackageDetail', ['id' => $r['id']]) ?>">View details</a><?php endif; ?>
                    <?php if ($r['canCode']): ?><button type="button" role="menuitem" data-sl-open="sp-code" data-code-id="<?= (int) $r['id'] ?>" data-code-trk="<?= h($r['trk']) ?>">Pickup code</button><?php endif; ?>
                    <?php if ($d['isClient'] && $r['unpaid']): ?><a role="menuitem" href="add_payment_gateways_package.php?id_order=<?= (int) $r['id'] ?>">Pay now</a><?php endif; ?>
                    <?php if ($can['verify'] && $r['verify']): ?><a role="menuitem" href="<?= sl_route('PackageDetail', ['id' => $r['id']]) ?>#payment">Verify payment</a><?php endif; ?>
                    <?php if ($r['canEdit']): ?><a role="menuitem" href="customer_package_edit.php?id=<?= (int) $r['id'] ?>">Edit</a><?php endif; ?>
                    <?php if ($r['canDriver']): ?><button type="button" role="menuitem" data-sl-open="sp-driver" data-drv-id="<?= (int) $r['id'] ?>" data-drv-trk="<?= h($r['trk']) ?>">Assign driver</button><?php endif; ?>
                    <?php if ($can['print']): ?><a role="menuitem" target="_blank" href="print_invoice_package.php?id=<?= (int) $r['id'] ?>">Print invoice</a><?php endif; ?>
                    <?php if ($can['label']): ?><a role="menuitem" target="_blank" href="print_label_package.php?id=<?= (int) $r['id'] ?>">Print label</a><?php endif; ?>
                    <?php if ($r['canTrack'] && $r['paid']): ?><a role="menuitem" href="customer_package_tracking.php?id=<?= (int) $r['id'] ?>">Update status</a><?php endif; ?>
                    <?php if ($can['deliver'] && !$r['done']): ?><a role="menuitem" href="customer_package_deliver.php?id=<?= (int) $r['id'] ?>">Hand over</a><?php endif; ?>
                    <?php if ($can['email']): ?><button type="button" role="menuitem" data-sl-action="ajax/sl/package_ajax.php" data-p-op="email" data-p-id="<?= (int) $r['id'] ?>" data-sl-confirm="E-mail the invoice to <?= h($r['email']) ?>?">Send e-mail</button><?php endif; ?>
                    <?php if ($r['canDelete']): ?><button type="button" role="menuitem" class="danger" data-sl-action="ajax/sl/package_ajax.php" data-p-op="delete" data-p-id="<?= (int) $r['id'] ?>" data-sl-confirm="Delete package <?= h($r['trk']) ?>? This cannot be undone." data-sl-reload>Delete</button><?php endif; ?>
                  </div></details></td></tr>
              <?php endforeach; ?>
              <?php if (!$d['rows']): ?><tr><td colspan="10"><div class="sl-empty"><span class="sl-tile"><i class="ic i-ship" aria-hidden="true"></i></span>No packages match this search.</div></td></tr><?php endif; ?>
            </tbody>
          </table></div>
          <?= sl_pager($d['page'], $d['per'], $d['count'], 'packages') ?>
        </div>
<?php if ($can['driver']): ?>
<div class="sl-dialog" id="sp-driver" hidden><form class="sl-modal" data-sl-form data-sl-reload action="ajax/sl/package_ajax.php" method="post" role="dialog" aria-label="Assign driver">
  <input type="hidden" name="op" value="driver"><input type="hidden" name="id" id="sp-drv-id" value="">
  <div><b class="fw5">Assign a driver to <span id="sp-drv-trk" class="mono"></span></b><div class="mut sm">The driver and the customer are notified.</div></div>
  <label class="sl-field"><span class="sl-lab">Driver</span><span class="sl-sel native"><select name="driver_id" required><option value="">Choose a driver</option><?php foreach ($d['drivers'] as $dr): ?><option value="<?= (int) $dr->id ?>"><?= h($dr->fname . ' ' . $dr->lname) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
  <div class="row g8"><button type="submit" class="sl-btn">Assign</button><button type="button" class="sl-btn ghost" data-sl-close>Cancel</button></div>
</form></div>
<?php endif; ?>
<?php if ($can['code']): ?>
<?php include CDP_ROOT . '/views/sl/inc/pickup_code_dialog.php'; ?>
<?php endif; ?>
<script>
document.addEventListener('click', function (e) {
  var b = e.target.closest('[data-drv-id]');
  if (b) { document.getElementById('sp-drv-id').value = b.getAttribute('data-drv-id'); document.getElementById('sp-drv-trk').textContent = b.getAttribute('data-drv-trk'); }
});
(function () {
  var all = document.getElementById('sp-all'), bulk = document.getElementById('sp-bulk');
  function sel() { return Array.prototype.slice.call(document.querySelectorAll('.sp-chk:checked')); }
  function refresh() { var n = sel().length; if (bulk) { bulk.hidden = n === 0; document.getElementById('sp-n').textContent = n; } }
  if (all) all.addEventListener('change', function () { document.querySelectorAll('.sp-chk').forEach(function (c) { c.checked = all.checked; }); refresh(); });
  document.addEventListener('change', function (e) { if (e.target.classList.contains('sp-chk')) refresh(); });
  function post(data) { SL.post('ajax/sl/package_ajax.php', data).then(function (j) { SL.toast(j.message || (j.success ? 'Done' : 'Failed'), !j.success); if (j.success) setTimeout(function () { location.reload(); }, 400); }); }
  var g = document.getElementById('sp-bulk-go'); if (g) g.addEventListener('click', function () { var s = document.getElementById('sp-bulk-status').value; if (!s) return SL.toast('Pick a status', true); if (!confirm('Change the status of ' + sel().length + ' package(s)?')) return; post({ op: 'bulk_status', status: s, ids: sel().map(function (c) { return c.value; }).join(',') }); });
  var gd = document.getElementById('sp-bulk-driver-go'); if (gd) gd.addEventListener('click', function () { var s = document.getElementById('sp-bulk-driver').value; if (!s) return SL.toast('Pick a driver', true); post({ op: 'bulk_driver', driver_id: s, ids: sel().map(function (c) { return c.value; }).join(',') }); });
  var gl = document.getElementById('sp-bulk-labels'); if (gl) gl.addEventListener('click', function () { var ids = sel().map(function (c) { return c.value; }); if (ids.length) window.open('print_label_package_multiple.php?id=' + ids.join(','), '_blank'); });
})();
</script>
