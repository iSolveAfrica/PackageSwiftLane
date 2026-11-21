<?php
/**
 * Air Consolidations / Sea Consolidations - from docs/handover/artifact-source/Consolidations.dc.html.
 * Data: views/sl/data/consolidations.php (sl_consolidationsData). One mode per page:
 * consolidate_list.php = air (cdb_consolidate), consolidate_package_list.php = sea (cdb_consolidate_packages).
 */
if (!isset($d)) { $d = []; }
$sea = !empty($d['sea']);
?>
        <div class="row g8 mut sm"><a href="<?= h($d['parentHref']) ?>"><?= h($d['parentL']) ?></a><i class="ic ic-sm i-right" aria-hidden="true"></i><span style="color:var(--ink)"><?= h($d['title']) ?></span></div>
        <div class="sl-head">
          <div><h1 class="sl-title row g8" style="align-items:center"><?= h($d['title']) ?> <span class="sl-badge <?= $sea ? 'sea' : 'air' ?>"><?= $sea ? 'Sea' : 'Air' ?></span></h1><p class="sl-lede"><?= $sea ? 'Group sea packages into shared containers before they sail' : 'Group air shipments into shared flight loads before they fly' ?> · <?= sl_num($d['all']) ?> <?= h($d['units']) ?> on record<?php if ($d['cancelledN']): ?> (<?= sl_num($d['cancelledN']) ?> cancelled)<?php endif; ?></p></div>
          <div class="row g8 wrap"><?php if ($d['otherHref']): ?><a class="sl-btn ghost" href="<?= h($d['otherHref']) ?>"><i class="ic ic-sm <?= $sea ? 'i-plane' : 'i-ship' ?>" aria-hidden="true"></i><?= $sea ? 'Air Consolidations' : 'Sea Consolidations' ?></a><?php endif; ?><?php if ($d['canNew']): ?><a class="sl-btn" href="<?= sl_route('NewConsolidation', ['type' => $d['mode']]) ?>"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New <?= $sea ? 'Sea' : 'Air' ?> Consolidation</a><?php endif; ?></div>
        </div>
        <?php if (!empty($d['tracking']['items'])): $tc = $d['tracking']; $tcTitle = $sea ? 'Containers in motion' : 'Flight loads in motion'; $tcNoun = 'consolidations'; $tcExpand = ''; ?>
        <?php include __DIR__ . '/partials/track_console.php'; ?>
        <?php endif; ?>
        <div class="grid cols4">
          <?php foreach ($d['metrics'] as $m): $on = $d['filter'] === $m['f']; ?><a class="sl-card sl-metric" style="color:inherit<?= $on ? ';box-shadow:inset 0 0 0 2px var(--ink)' : '' ?>" href="<?= h(sl_url(['filter' => $on ? null : $m['f'], 'page' => null])) ?>"<?= $on ? ' aria-current="true"' : '' ?>><div class="sl-ml"><span><i class="ic ic-sm <?= h($m['icon']) ?>" aria-hidden="true"></i><?= h($m['l']) ?></span></div><div class="sl-mv"><b class="fw5" style="font-size:22px"><?= sl_num($m['n']) ?></b> <i>/ <?= sl_num($m['of']) ?> · <?= h($m['sub']) ?></i></div><div class="sl-ticks <?= h($m['cls']) ?>"><i style="width: <?= sl_pct($m['n'], $m['of']) ?>%"></i></div></a><?php endforeach; ?>
        </div>
        <div class="grid cols4">
          <?php foreach ($d['metrics2'] as $m): $on = $d['filter'] === $m['f']; ?><a class="sl-card sl-metric" style="color:inherit<?= $on ? ';box-shadow:inset 0 0 0 2px var(--ink)' : '' ?>" href="<?= h(sl_url(['filter' => $on ? null : $m['f'], 'page' => null])) ?>"<?= $on ? ' aria-current="true"' : '' ?>><div class="sl-ml"><span><i class="ic ic-sm <?= h($m['icon']) ?>" aria-hidden="true"></i><?= h($m['l']) ?></span><?php if ($m['warn']): ?><span class="sl-pill overdue" style="height:22px;font-size:12.5px">Check</span><?php endif; ?></div><div class="sl-mv"><b class="fw5" style="font-size:22px"><?= h($m['v']) ?></b> <i><?= h($m['sub']) ?></i></div></a><?php endforeach; ?>
        </div>
        <div class="sl-card flush">
          <form method="get" class="row wrap g12" style="padding: 14px 16px">
            <input type="hidden" name="filter" value="<?= h($d['filter']) ?>">
            <div class="sl-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input name="q" value="<?= h($d['q']) ?>" aria-label="Search <?= h(strtolower($d['title'])) ?>" placeholder="<?= $sea ? 'Search container, package number, B/L or seal' : 'Search consolidation, shipment number, MAWB or ULD' ?>"></div>
            <?php if ($d['filter'] !== ''): ?><a class="sl-badge" href="<?= h(sl_url(['filter' => null, 'page' => null])) ?>" title="Clear filter"><?= h($d['filterL']) ?> · <?= sl_num($d['count']) ?> <i class="ic ic-sm i-x" aria-hidden="true"></i></a><?php endif; ?>
            <div class="sl-seg boxed"><a class="<?= $d['filter'] === '' ? 'on' : '' ?>" href="<?= h(sl_url(['filter' => null, 'page' => null])) ?>">All</a><a class="<?= $d['filter'] === 'active' ? 'on' : '' ?>" href="<?= h(sl_url(['filter' => 'active', 'page' => null])) ?>">Active</a><a class="<?= $d['filter'] === 'cancelled' ? 'on' : '' ?>" href="<?= h(sl_url(['filter' => 'cancelled', 'page' => null])) ?>">Cancelled</a></div>
          </form>
          <div class="sl-tw"><table class="sl-table">
            <thead><tr><th><?= h($d['colId']) ?></th><th>Route</th><th><?= h($d['docL']) ?></th><th><?= h($d['carrierL']) ?></th><th style="min-width: 170px">Fill</th><th>Parcels</th><th><?= $sea ? 'Sails' : 'Departs' ?></th><th>ETA</th><th>Payment</th><th>Status</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($d['rows'] as $r): $a = $r['act']; ?>
                <tr><td><a class="id" href="<?= h($r['href']) ?>"><?= h($r['id']) ?></a></td><td><?= h($r['route']) ?></td><td><span class="row g6"><span class="mono"><?= h($r['awb']) ?></span><?php if ($a['print']): ?><a class="sl-ib sm" target="_blank" aria-label="Open document <?= h($r['awb']) ?>" href="<?= h($a['print']) ?>"><i class="ic ic-sm i-file" aria-hidden="true"></i></a><?php endif; ?></span><span class="sub<?= $r['noDoc'] ? ' bad' : '' ?>"<?= $r['noDoc'] ? ' style="color:var(--red,#c0392b)"' : '' ?>><?= h($r['awbType']) ?></span></td><td><?= h($r['carrier']) ?><span class="sub mono"><?= h($r['voy']) ?></span></td><td><div class="col g6"><div class="sl-ticks <?= h($r['tc']) ?>"><i style="<?= h($r['sty']) ?>"></i></div><span class="mut sm"><?= h($r['fill']) ?></span></div></td><td><span class="fw5"><?= (int) $r['n'] ?></span><span class="sub"><?= $sea ? h($r['vol']) . ' vol.' : 'parcels' ?></span></td><td><?= h($r['dep']) ?></td><td><?= h($r['eta']) ?><?php if ($r['late']): ?><span class="sub" style="color:var(--red,#c0392b)">Past ETA</span><?php endif; ?></td><td><span class="sl-pill <?= h($r['pay'][1]) ?>"><?= h($r['pay'][0]) ?></span></td><td><span class="sl-pill <?= h($r['cls']) ?>"><?= h($r['st']) ?></span></td>
                <td><details class="sl-menu" data-sl-pop><summary class="sl-ib sm" aria-label="Actions for <?= h($r['id']) ?>"><i class="ic i-more" aria-hidden="true"></i></summary><div class="pop" role="menu">
                  <a role="menuitem" href="<?= h($r['href']) ?>">View</a>
                  <?php if ($a['edit']): ?><a role="menuitem" href="<?= h($r['href']) ?>&add=1">Add packages</a><a role="menuitem" href="<?= h($a['edit']) ?>">Edit</a><?php endif; ?>
                  <?php if ($a['pay']): ?><a role="menuitem" href="<?= h($a['pay']) ?>">Record payment</a><?php endif; ?>
                  <?php if ($a['driver']): ?><button type="button" role="menuitem" data-sl-open="cn-driver" data-drv-id="<?= (int) $r['cid'] ?>" data-drv-type="<?= h($r['type']) ?>" data-drv-trk="<?= h($r['id']) ?>">Assign rider</button><?php endif; ?>
                  <?php if ($a['print']): ?><a role="menuitem" target="_blank" href="<?= h($a['print']) ?>">Print</a><?php endif; ?>
                  <?php if ($a['label']): ?><a role="menuitem" target="_blank" href="<?= h($a['label']) ?>">Print label</a><?php endif; ?>
                  <?php if ($a['packing']): ?><a role="menuitem" target="_blank" href="<?= h($a['packing']) ?>">Packing list</a><a role="menuitem" href="<?= h($a['packingX']) ?>">Packing list (Excel)</a><?php endif; ?>
                  <?php if ($a['track']): ?><a role="menuitem" href="<?= h($a['track']) ?>">Update status</a><?php endif; ?>
                  <?php if ($a['deliver']): ?><a role="menuitem" href="<?= h($a['deliver']) ?>">Hand over</a><?php endif; ?>
                  <?php if ($a['delete']): ?><button type="button" role="menuitem" class="danger" data-sl-action="ajax/sl/consolidation_ajax.php" data-p-op="delete" data-p-id="<?= (int) $r['cid'] ?>" data-p-type="<?= h($r['type']) ?>" data-sl-confirm="Cancel consolidation <?= h($r['id']) ?>? Its packages are released." data-sl-reload>Cancel consolidation</button><?php endif; ?>
                </div></details></td></tr>
              <?php endforeach; ?>
              <?php if (!$d['rows']): ?><tr><td colspan="11"><div class="sl-empty"><span class="sl-tile"><i class="ic i-layers" aria-hidden="true"></i></span>No <?= $sea ? 'sea' : 'air' ?> consolidations match.</div></td></tr><?php endif; ?>
            </tbody>
          </table></div>
          <?= sl_pager($d['page'], $d['per'], $d['count'], $sea ? 'sea consolidations' : 'air consolidations') ?>
        </div>
<div class="sl-dialog" id="cn-driver" hidden><form class="sl-modal" data-sl-form data-sl-reload action="ajax/sl/consolidation_ajax.php" method="post" role="dialog" aria-label="Assign rider"><input type="hidden" name="op" value="driver"><input type="hidden" name="id" id="cn-drv-id"><input type="hidden" name="type" id="cn-drv-type">
  <b class="fw5">Assign a rider to <span class="mono" id="cn-drv-trk"></span></b>
  <label class="sl-field"><span class="sl-lab">Rider</span><span class="sl-sel native"><select name="driver_id" required><option value="">Choose</option><?php foreach ($d['drivers'] as $dr): ?><option value="<?= (int) $dr->id ?>"><?= h($dr->fname . ' ' . $dr->lname) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
  <div class="row g8"><button class="sl-btn" type="submit">Assign</button><button type="button" class="sl-btn ghost" data-sl-close>Cancel</button></div></form></div>
<script>document.addEventListener('click', function (e) { var b = e.target.closest('[data-drv-id]'); if (b) { document.getElementById('cn-drv-id').value = b.getAttribute('data-drv-id'); document.getElementById('cn-drv-type').value = b.getAttribute('data-drv-type'); document.getElementById('cn-drv-trk').textContent = b.getAttribute('data-drv-trk'); } });</script>
