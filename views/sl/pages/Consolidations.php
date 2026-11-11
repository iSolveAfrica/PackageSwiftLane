<?php
/**
 * Consolidations - from docs/handover/artifact-source/Consolidations.dc.html.
 * Data: views/sl/data/consolidations.php (sl_consolidationsData). Air (cdb_consolidate) and sea (cdb_consolidate_packages) in one list with the design's tabs.
 */
if (!isset($d)) { $d = []; }
?>
        <div class="sl-head">
          <div><h1 class="sl-title">Consolidations</h1><p class="sl-lede">Group parcels into containers and flights before they leave the US · <?= sl_num($d['tot']) ?> in the list</p></div>
          <div class="row g8 wrap"><?php if ($d['canNew']): ?><a class="sl-btn" href="<?= sl_route('NewConsolidation', ['type' => $d['mode'] === 'sea' || !$d['canAir'] ? 'sea' : 'air']) ?>"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New Consolidation</a><?php endif; ?></div>
        </div>
        <div class="grid cols3">
          <?php foreach ($d['metrics'] as $m): ?><a class="sl-card sl-metric" style="color:inherit" href="<?= h(sl_url(['filter' => $d['filter'] === $m['f'] ? null : $m['f'], 'page' => null])) ?>"><div class="sl-ml"><span><i class="ic ic-sm <?= h($m['icon']) ?>" aria-hidden="true"></i><?= h($m['l']) ?></span></div><div class="sl-mv"><?= sl_num($m['n']) ?> / <?= sl_num($d['tot']) ?> <i><?= h($m['sub']) ?></i></div><div class="sl-ticks <?= h($m['cls']) ?>"><i style="width: <?= sl_pct($m['n'], $d['tot']) ?>%"></i></div></a><?php endforeach; ?>
        </div>
        <div class="sl-card flush">
          <form method="get" class="row wrap g12" style="padding: 14px 16px">
            <input type="hidden" name="mode" value="<?= h($d['mode']) ?>"><input type="hidden" name="filter" value="<?= h($d['filter']) ?>">
            <div class="sl-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input name="q" value="<?= h($d['q']) ?>" aria-label="Search consolidations" placeholder="Search container, shipping number, AWB or B/L"></div>
            <div class="sl-seg boxed"><?php if ($d['canAir'] && $d['canSea']): ?><a class="<?= $d['mode'] === 'all' ? 'on' : '' ?>" href="<?= h(sl_url(['mode' => 'all', 'page' => null])) ?>">All</a><?php endif; ?><?php if ($d['canAir']): ?><a class="<?= $d['mode'] === 'air' ? 'on' : '' ?>" href="<?= h(sl_url(['mode' => 'air', 'page' => null])) ?>">Air</a><?php endif; ?><?php if ($d['canSea']): ?><a class="<?= $d['mode'] === 'sea' ? 'on' : '' ?>" href="<?= h(sl_url(['mode' => 'sea', 'page' => null])) ?>">Sea</a><?php endif; ?></div>
          </form>
          <div class="sl-tw"><table class="sl-table">
            <thead><tr><th>Container</th><th>Mode</th><th>Route</th><th>Shipping number</th><th>Air waybill / B/L</th><th>Flight / vessel</th><th style="min-width: 170px">Fill</th><th>Parcels</th><th>Departs</th><th>ETA</th><th>Status</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($d['rows'] as $r): $a = $r['act']; ?>
                <tr><td><a class="id" href="<?= h($r['href']) ?>"><?= h($r['id']) ?></a></td><td><span class="sl-badge <?= h($r['mode']) ?>"><?= h($r['modeL']) ?></span></td><td><?= h($r['route']) ?></td><td><span class="mono fw5"><?= h($r['ship']) ?></span></td><td><span class="row g6"><span class="mono"><?= h($r['awb']) ?></span><?php if ($a['print']): ?><a class="sl-ib sm" target="_blank" aria-label="Open document <?= h($r['awb']) ?>" href="<?= h($a['print']) ?>"><i class="ic ic-sm i-file" aria-hidden="true"></i></a><?php endif; ?></span><span class="sub"><?= h($r['awbType']) ?></span></td><td><?= h($r['carrier']) ?><span class="sub mono"><?= h($r['voy']) ?></span></td><td><div class="col g6"><div class="sl-ticks <?= h($r['tc']) ?>"><i style="<?= h($r['sty']) ?>"></i></div><span class="mut sm"><?= h($r['fill']) ?></span></div></td><td><span class="fw5"><?= (int) $r['n'] ?></span><span class="sub">parcels</span></td><td><?= h($r['dep']) ?></td><td><?= h($r['eta']) ?></td><td><span class="sl-pill <?= h($r['cls']) ?>"><?= h($r['st']) ?></span></td>
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
              <?php if (!$d['rows']): ?><tr><td colspan="12"><div class="sl-empty"><span class="sl-tile"><i class="ic i-layers" aria-hidden="true"></i></span>No consolidations match.</div></td></tr><?php endif; ?>
            </tbody>
          </table></div>
          <?= sl_pager($d['page'], $d['per'], $d['count'], 'consolidations') ?>
        </div>
<div class="sl-dialog" id="cn-driver" hidden><form class="sl-modal" data-sl-form data-sl-reload action="ajax/sl/consolidation_ajax.php" method="post" role="dialog" aria-label="Assign rider"><input type="hidden" name="op" value="driver"><input type="hidden" name="id" id="cn-drv-id"><input type="hidden" name="type" id="cn-drv-type">
  <b class="fw5">Assign a rider to <span class="mono" id="cn-drv-trk"></span></b>
  <label class="sl-field"><span class="sl-lab">Rider</span><span class="sl-sel native"><select name="driver_id" required><option value="">Choose</option><?php foreach ($d['drivers'] as $dr): ?><option value="<?= (int) $dr->id ?>"><?= h($dr->fname . ' ' . $dr->lname) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
  <div class="row g8"><button class="sl-btn" type="submit">Assign</button><button type="button" class="sl-btn ghost" data-sl-close>Cancel</button></div></form></div>
<script>document.addEventListener('click', function (e) { var b = e.target.closest('[data-drv-id]'); if (b) { document.getElementById('cn-drv-id').value = b.getAttribute('data-drv-id'); document.getElementById('cn-drv-type').value = b.getAttribute('data-drv-type'); document.getElementById('cn-drv-trk').textContent = b.getAttribute('data-drv-trk'); } });</script>
