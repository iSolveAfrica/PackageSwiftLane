<?php
/**
 * Warehouse - from docs/handover/artifact-source/Warehouse.dc.html.
 * Data: views/sl/data/warehouse.php (sl_warehouseData). Receive / move bay: ajax/sl/warehouse_ajax.php.
 * Pickup codes are never displayed to staff, only whether one was sent or verified.
 */
if (!isset($d)) { $d = []; }
?>
        <div class="sl-head">
          <div><h1 class="sl-title">Warehouse</h1><p class="sl-lede"><?= h($d['lede']) ?></p></div>
          <div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('LabelStudio') ?>"><i class="ic ic-sm i-print" aria-hidden="true"></i>Print bay labels</a><a class="sl-btn" href="<?= sl_route('LockerSearch') ?>"><i class="ic ic-sm i-search" aria-hidden="true"></i>Locker search</a></div>
        </div>
        <?php if ($d['canReceive']): ?>
        <section class="sl-card col g12" aria-label="Scan">
          <div class="sl-seg boxed" style="align-self: flex-start" id="wh-mode"><button type="button" class="on" data-m="receive"><i class="ic ic-sm i-scan" aria-hidden="true"></i>Receive</button><?php if ($d['canHand']): ?><a href="<?= sl_route('HandOut') ?>"><i class="ic ic-sm i-truck" aria-hidden="true"></i>Hand over</a><?php endif; ?><button type="button" data-m="move">Move bay</button></div>
          <form class="row wrap g8" data-sl-form action="ajax/sl/warehouse_ajax.php" method="post" id="wh-scan"><input type="hidden" name="op" value="receive" id="wh-op">
            <div class="sl-search" style="max-width: none; height: 52px; flex: 1 1 320px"><i class="ic i-scan" aria-hidden="true"></i><input class="mono" name="tracking" aria-label="Scan or type a tracking number" placeholder="Scan or type a tracking number" autofocus autocomplete="off"></div>
            <label class="sl-sel native" style="width: 130px; height: 52px"><select name="bay" aria-label="Bay"><option value="">No bay</option><?php foreach ($d['bayList'] as $b): ?><option><?= h($b) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></label>
            <button class="sl-btn" type="submit" style="height: 52px; padding: 0 28px" id="wh-go">Receive parcel</button></form>
          <div class="sl-banner ok" id="wh-res" hidden><i class="ic i-check" aria-hidden="true"></i><div></div></div>
        </section>
        <?php endif; ?>
        <div class="split">
          <section class="a sl-card">
            <div class="sl-ch"><h2 class="sl-ct">Locker bays</h2><div class="sl-mv" style="margin: 0; font-size: 14px"><?= (int) $d['full'] ?> / <?= (int) $d['bayN'] ?> <i>full (<?= (int) $d['fullPct'] ?>%)</i></div></div>
            <div class="sl-ticks <?= $d['fullPct'] >= 90 ? 'r' : ($d['fullPct'] >= 70 ? 'y' : '') ?>" style="margin-bottom: 18px"><i style="width: <?= (int) $d['fullPct'] ?>%"></i></div>
            <div class="sl-heat" style="grid-template-rows: repeat(<?= (int) ceil($d['bayN'] / 10) ?>, 40px); grid-template-columns: repeat(10, minmax(0, 1fr)); grid-auto-flow: row" role="img" aria-label="Locker bay map, <?= (int) $d['full'] ?> of <?= (int) $d['bayN'] ?> bays occupied">
              <?php foreach ($d['bays'] as $b): ?><b class="<?= h($b['c']) ?>" title="<?= h($b['t']) ?>" style="width: 30px; height: 30px; border-radius: 8px; opacity: 1"></b><?php endforeach; ?>
            </div>
            <div class="row g16 wrap mut sm" style="margin-top: 14px"><span class="row g6"><b style="width: 12px; height: 12px; border-radius: 3px; background: var(--heat-0)"></b>Free (<?= (int) $d['free'] ?>)</span><span class="row g6"><b style="width: 12px; height: 12px; border-radius: 3px; background: var(--heat-3)"></b>Occupied (<?= (int) $d['busy'] ?>)</span><span class="row g6"><b style="width: 12px; height: 12px; border-radius: 3px; background: var(--heat-1)"></b>Awaiting pickup (<?= (int) $d['wait'] ?>)</span></div>
            <?php if (!$d['hasBay']): ?><p class="mut sm">Bays are recorded once migrations/2026_11_06_warehouse_packages.sql is applied.</p><?php endif; ?>
          </section>
          <aside class="b sl-card">
            <div class="sl-ch"><h2 class="sl-ct">Recent scans</h2></div>
            <div class="sl-feed">
              <?php foreach ($d['feed'] as $f): ?><div><span class="sl-tile"><i class="ic <?= h($f['icon']) ?>" aria-hidden="true"></i></span><div class="grow"><div class="fw5"><?= h($f['t']) ?></div><div class="mut sm"><?= h($f['s']) ?></div></div><span class="mut sm nw"><?= h($f['when']) ?></span></div><?php endforeach; ?>
              <?php if (!$d['feed']): ?><div class="mut sm">No scans yet.</div><?php endif; ?>
            </div>
          </aside>
        </div>
        <section class="sl-card flush">
          <form method="get" class="row between wrap g12" style="padding: 16px 20px"><h2 class="sl-ct">Ready for hand over · <?= sl_num($d['count']) ?></h2><div class="sl-search" style="min-width: 200px"><i class="ic ic-sm i-search" aria-hidden="true"></i><input name="q" value="<?= h($d['q']) ?>" aria-label="Filter" placeholder="Name, locker, phone or tracking"></div></form>
          <div class="sl-tw"><table class="sl-table"><thead><tr><th>Parcel</th><th>Customer</th><th>Bay</th><th>Waiting</th><th>Pickup code</th><th></th></tr></thead><tbody>
            <?php foreach ($d['rows'] as $r): ?>
            <tr><td><a class="id" href="<?= h($r['href']) ?>"><?= h($r['id']) ?></a><span class="sub"><?= $r['module'] === 'sea' ? 'Sea' : 'Air' ?> · <?= h($r['w']) ?></span></td><td><div class="sl-cell"><span class="sl-av sm"><?= h($r['ini']) ?></span><span><?= h($r['cust']) ?><span class="sub mono"><?= h($r['locker']) ?></span></span></div></td><td class="mono sm"><?= h($r['bay']) ?><?php if ($d['canReceive']): ?> <button type="button" class="sl-ib sm" data-sl-open="wh-move" data-mv-id="<?= (int) $r['oid'] ?>" data-mv-m="<?= h($r['module']) ?>" data-mv-trk="<?= h($r['id']) ?>" aria-label="Move <?= h($r['id']) ?>"><i class="ic ic-sm i-edit" aria-hidden="true"></i></button><?php endif; ?></td><td><?php if ($r['dn'] >= 7): ?><span class="sl-pill pending"><?= h($r['days']) ?></span><?php else: ?><?= h($r['days']) ?><?php endif; ?></td><td class="fw5"><?= h($r['code']) ?><span class="sub"><?= h($r['cs']) ?></span></td>
              <td><span class="row g4"><?php if ($d['canHand'] && $r['cleared']): ?><a class="sl-btn sm" href="<?= sl_route('HandOut', ['customer' => $r['sender']]) ?>">Hand over</a><?php elseif (!$r['cleared']): ?><span class="sl-badge">Awaiting clearance</span><?php endif; ?><?php if ($d['canCode'] && !$r['verified']): ?><button type="button" class="sl-btn ghost sm" data-sl-open="sp-code" data-code-id="<?= (int) $r['oid'] ?>" data-code-trk="<?= h($r['id']) ?>" data-code-module="<?= h($r['module']) ?>"><?= $r['dn'] >= 7 ? 'Send reminder' : 'Pickup code' ?></button><?php endif; ?></span></td></tr>
            <?php endforeach; ?>
            <?php if (!$d['rows']): ?><tr><td colspan="6"><div class="sl-empty"><span class="sl-tile"><i class="ic i-box" aria-hidden="true"></i></span>Nothing is waiting to be handed over.</div></td></tr><?php endif; ?>
          </tbody></table></div>
          <?= sl_pager($d['page'], $d['per'], $d['count'], 'parcels') ?>
        </section>
<?php if ($d['canReceive']): ?>
<div class="sl-dialog" id="wh-move" hidden><form class="sl-modal" data-sl-form data-sl-reload action="ajax/sl/warehouse_ajax.php" method="post" role="dialog" aria-label="Move bay"><input type="hidden" name="op" value="move"><input type="hidden" name="id" id="wh-mv-id"><input type="hidden" name="module" id="wh-mv-m">
  <b class="fw5">Move <span class="mono" id="wh-mv-trk"></span></b>
  <label class="sl-field"><span class="sl-lab">New bay</span><span class="sl-sel native"><select name="bay" required><?php foreach ($d['bayList'] as $b): ?><option><?= h($b) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
  <div class="row g8"><button class="sl-btn" type="submit">Move</button><button type="button" class="sl-btn ghost" data-sl-close>Cancel</button></div></form></div>
<script>
(function () {
  var f = document.getElementById('wh-scan'), res = document.getElementById('wh-res');
  document.getElementById('wh-mode').addEventListener('click', function (e) { var b = e.target.closest('button[data-m]'); if (!b) return; this.querySelectorAll('button').forEach(function (x) { x.classList.toggle('on', x === b); }); document.getElementById('wh-op').value = b.getAttribute('data-m') === 'move' ? 'receive' : 'receive'; document.getElementById('wh-go').textContent = b.getAttribute('data-m') === 'move' ? 'Set bay' : 'Receive parcel'; });
  f.addEventListener('sl:done', function (e) { var j = e.detail || {}; res.hidden = false; res.className = 'sl-banner ' + (j.success ? 'ok' : 'bad'); res.querySelector('div').textContent = j.message || ''; if (j.success) { f.tracking.value = ''; f.tracking.focus(); } if (!j.success && j.redirect) setTimeout(function () { location.href = j.redirect; }, 1200); });
  document.addEventListener('click', function (e) { var b = e.target.closest('[data-mv-id]'); if (b) { document.getElementById('wh-mv-id').value = b.getAttribute('data-mv-id'); document.getElementById('wh-mv-m').value = b.getAttribute('data-mv-m'); document.getElementById('wh-mv-trk').textContent = b.getAttribute('data-mv-trk'); } });
})();
</script>
<?php endif; ?>
<?php if ($d['canCode']) { $slCodeModule = 'air'; include CDP_ROOT . '/views/sl/inc/pickup_code_dialog.php'; } ?>
