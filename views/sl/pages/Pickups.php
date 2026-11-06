<?php
/**
 * Pickups - from docs/handover/artifact-source/Pickups.dc.html.
 * Data: views/sl/data/pickups.php (sl_pickupsData). Actions: ajax/sl/pickup_ajax.php; accept keeps pickup_accept.php.
 */
if (!isset($d)) { $d = []; }
$can = $d['can'];
?>
        <div class="sl-head">
          <div><h1 class="sl-title">Pickups</h1><p class="sl-lede"><?= h($d['lede']) ?></p></div>
          <div class="row g8 wrap"><?php if ($d['canAging']): ?><a class="sl-btn ghost" href="<?= sl_route('PickupAging') ?>"><i class="ic ic-sm i-clock" aria-hidden="true"></i>Pickup aging</a><?php endif; ?><?php if ($d['canAdd']): ?><a class="sl-btn" href="<?= $d['isClient'] ? 'pickup_add.php' : sl_route('SchedulePickup') ?>"><i class="ic ic-sm i-plus" aria-hidden="true"></i><?= $d['isClient'] ? 'Request pickup' : 'Schedule Pickup' ?></a><?php endif; ?></div>
        </div>
        <div class="grid cols4"><?php foreach ($d['kpi'] as $k): ?><a class="dv-kpi" href="<?= h(sl_url(['filter' => $k['f'], 'page' => null])) ?>" style="color:inherit"><div class="t"><?= h($k['t']) ?></div><div class="v"><?= sl_num($k['v']) ?><small> <?= h($k['small']) ?></small></div><div class="d <?= h($k['dc']) ?>"><?= h($k['d']) ?></div><svg viewBox="0 0 240 46" preserveAspectRatio="none" aria-hidden="true"><path d="<?= h($k['area']) ?>" fill="#6B6A65" opacity=".14"></path><path d="<?= h($k['line']) ?>" fill="none" stroke="#6B6A65" stroke-width="1.8" vector-effect="non-scaling-stroke"></path></svg></a><?php endforeach; ?></div>
        <div class="grid cols3"><section class="sl-card col g12"><div><h2 class="sl-ct">Slots by window</h2><div class="mut sm">Booked vs capacity, today</div></div><?php foreach ($d['slots'] as $s): ?><div class="col g6"><div class="row between sm"><span class="fw5"><?= h($s['w']) ?></span><span class="mut"><?= h($s['txt']) ?></span></div><div class="sl-ticks"><i style="width:<?= (int) $s['pct'] ?>%;background:#6B6A65"></i></div></div><?php endforeach; ?></section>
        <section class="sl-card col g12"><div><h2 class="sl-ct">Driver workload</h2><div class="mut sm">Stops today</div></div><?php foreach ($d['drivers'] as $s): ?><div class="col g6"><div class="row between sm"><span class="fw5"><?= h($s['name']) ?></span><span class="mut"><?= h($s['txt']) ?></span></div><div class="sl-ticks"><i style="width:<?= (int) $s['pct'] ?>%;background:#6B6A65"></i></div></div><?php endforeach; ?><?php if (!$d['drivers']): ?><span class="mut sm">No driver has pickups today.</span><?php endif; ?></section>
        <section class="sl-card col g12"><div><h2 class="sl-ct">By area</h2><div class="mut sm">Pickups this week · avg wait</div></div><?php foreach ($d['areas'] as $a): ?><div class="dv-row"><span class="grow fw5"><?= h($a['city']) ?></span><span class="mut"><?= (int) $a['n'] ?></span><span class="sl-badge"><?= h($a['wait']) ?></span></div><?php endforeach; ?><?php if (!$d['areas']): ?><span class="mut sm">No pickups this week.</span><?php endif; ?></section></div>
        <div class="grid cols2"><section class="sl-card col g12"><div><h2 class="sl-ct">Pickup volume</h2><div class="mut sm">Per day this week</div></div><div class="row g6" style="align-items:flex-end;height:120px"><?php foreach ($d['week'] as $w): ?><div class="grow" title="<?= (int) $w['n'] ?> pickups" style="height:<?= (int) $w['h'] ?>%;<?= $w['now'] ? 'background:var(--active);box-shadow:inset 0 0 0 1px var(--line-2)' : 'background:#E9E7E1' ?>;border-radius:8px 8px 3px 3px"></div><?php endforeach; ?></div><div class="row between mbf"><?php foreach ($d['week'] as $w): ?><span><?= h($w['l']) ?></span><?php endforeach; ?></div></section>
        <section class="sl-card col g12"><div><h2 class="sl-ct">Pickup aging</h2><div class="mut sm">Hours since request, still requested</div></div><div class="row g6" style="align-items:flex-end;height:120px"><?php foreach ($d['aging'] as $i => $w): ?><div class="grow" title="<?= (int) $w['n'] ?> pickups" style="height:<?= (int) $w['h'] ?>%;<?= $i === 5 ? 'background:var(--active);box-shadow:inset 0 0 0 1px var(--line-2)' : 'background:#E9E7E1' ?>;border-radius:8px 8px 3px 3px"></div><?php endforeach; ?></div><div class="row between mbf"><?php foreach ($d['aging'] as $w): ?><span><?= h($w['l']) ?></span><?php endforeach; ?></div></section></div>
        <div class="sl-card flush">
          <form method="get" class="row wrap g12" style="padding: 14px 16px">
            <input type="hidden" name="when" value="<?= h($d['when']) ?>"><input type="hidden" name="filter" value="<?= h($d['filter']) ?>">
            <div class="sl-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input name="q" value="<?= h($d['q']) ?>" aria-label="Search pickups" placeholder="Search pickup, customer or address"></div>
            <div class="sl-seg boxed"><?php foreach ($d['whens'] as $x): ?><a class="<?= $x['f'] === $d['when'] ? 'on' : '' ?>" href="<?= h(sl_url(['when' => $x['f'], 'page' => null])) ?>"><?= h($x['label']) ?></a><?php endforeach; ?></div>
            <span class="grow"></span><?php if (!$d['isClient']): ?><label class="sl-sel native" style="width: 170px"><select name="driver" aria-label="Driver" onchange="this.form.submit()"><option value="0">All drivers</option><?php foreach ($d['driverList'] as $dr): ?><option value="<?= (int) $dr->id ?>" <?= (int) $dr->id === $d['driver'] ? 'selected' : '' ?>><?= h($dr->fname . ' ' . $dr->lname) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></label><?php endif; ?>
          </form>
          <div class="sl-tw"><table class="sl-table">
            <thead><tr><th>Pickup</th><th>Customer</th><th>Address</th><th>Window</th><th class="num">Items</th><th class="num">Est. kg</th><th class="num">Fee</th><th>Driver</th><th>Status</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($d['rows'] as $r): ?>
                <tr><td class="id"><a href="courier_view.php?id=<?= (int) $r['id'] ?>"><?= h($r['trk']) ?></a></td><td><div class="sl-cell"><span class="sl-av sm"><?= h($r['ini']) ?></span><span><?= h($r['name']) ?><span class="sub"><?= h($r['phone']) ?></span></span></div></td><td><div><?= h($r['addr']) ?><span class="sub"><?= h($r['area']) ?></span></div></td><td><?= h($r['win']) ?><span class="sub"><?= h($r['day']) ?></span></td><td class="num"><?= (int) $r['items'] ?></td><td class="num"><?= h($r['kg']) ?></td><td class="num"><?= h($r['fee']) ?></td><td><?= h($r['drv']) ?></td><td><span class="sl-pill <?= h($r['cls']) ?>"><?= h($r['st']) ?></span></td>
                  <td><span class="row g4"><?php if ($r['requested'] && $can['accept']): ?><a class="sl-btn ghost sm" href="pickup_accept.php?id=<?= (int) $r['id'] ?>">Accept</a><?php endif; ?>
                  <details class="sl-menu" data-sl-pop><summary class="sl-ib sm" aria-label="Actions for <?= h($r['trk']) ?>"><i class="ic i-more" aria-hidden="true"></i></summary><div class="pop" role="menu">
                    <a role="menuitem" href="courier_view.php?id=<?= (int) $r['id'] ?>">View</a>
                    <?php if ($r['open'] && $can['code'] && !$r['requested']): ?><button type="button" role="menuitem" data-sl-open="sp-code" data-code-id="<?= (int) $r['id'] ?>" data-code-trk="<?= h($r['trk']) ?>" data-code-module="air">Pickup code</button><?php endif; ?>
                    <?php if ($r['open'] && $can['driver']): ?><button type="button" role="menuitem" data-sl-open="pk-driver" data-drv-id="<?= (int) $r['id'] ?>" data-drv-trk="<?= h($r['trk']) ?>">Assign driver</button><?php endif; ?>
                    <?php if ($can['label']): ?><a role="menuitem" target="_blank" href="print_label_ship.php?id=<?= (int) $r['id'] ?>">Print label</a><?php endif; ?>
                    <?php if ($can['print'] && $r['open']): ?><a role="menuitem" target="_blank" href="print_inv_ship.php?id=<?= (int) $r['id'] ?>">Print invoice</a><?php endif; ?>
                    <?php if ($r['requested'] && $can['refuse']): ?><button type="button" role="menuitem" data-sl-action="ajax/sl/pickup_ajax.php" data-p-op="refuse" data-p-id="<?= (int) $r['id'] ?>" data-sl-confirm="Refuse pickup <?= h($r['trk']) ?>?" data-sl-reload>Refuse</button><?php endif; ?>
                    <?php if ($r['open'] && $can['cancel']): ?><button type="button" role="menuitem" data-sl-open="pk-cancel" data-cx-id="<?= (int) $r['id'] ?>" data-cx-trk="<?= h($r['trk']) ?>">Cancel</button><?php endif; ?>
                    <?php if ($can['delete']): ?><button type="button" role="menuitem" class="danger" data-sl-action="ajax/sl/pickup_ajax.php" data-p-op="delete" data-p-id="<?= (int) $r['id'] ?>" data-sl-confirm="Delete pickup <?= h($r['trk']) ?> for good?" data-sl-reload>Delete</button><?php endif; ?>
                  </div></details></span></td></tr>
              <?php endforeach; ?>
              <?php if (!$d['rows']): ?><tr><td colspan="10"><div class="sl-empty"><span class="sl-tile"><i class="ic i-truck" aria-hidden="true"></i></span>No pickups match.</div></td></tr><?php endif; ?>
            </tbody>
          </table></div>
          <?= sl_pager($d['page'], $d['per'], $d['count'], 'pickups') ?>
        </div>
<?php if ($can['driver']): ?>
<div class="sl-dialog" id="pk-driver" hidden><form class="sl-modal" data-sl-form data-sl-reload action="ajax/sl/pickup_ajax.php" method="post" role="dialog" aria-label="Assign driver"><input type="hidden" name="op" value="driver"><input type="hidden" name="id" id="pk-drv-id">
  <b class="fw5">Assign a driver to <span class="mono" id="pk-drv-trk"></span></b>
  <label class="sl-field"><span class="sl-lab">Driver</span><span class="sl-sel native"><select name="driver_id" required><option value="">Choose</option><?php foreach ($d['driverList'] as $dr): ?><option value="<?= (int) $dr->id ?>"><?= h($dr->fname . ' ' . $dr->lname) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
  <div class="row g8"><button class="sl-btn" type="submit">Assign</button><button type="button" class="sl-btn ghost" data-sl-close>Cancel</button></div></form></div>
<?php endif; ?>
<?php if ($can['cancel']): ?>
<div class="sl-dialog" id="pk-cancel" hidden><form class="sl-modal" data-sl-form data-sl-reload action="ajax/sl/pickup_ajax.php" method="post" role="dialog" aria-label="Cancel pickup"><input type="hidden" name="op" value="cancel"><input type="hidden" name="id" id="pk-cx-id">
  <b class="fw5">Cancel pickup <span class="mono" id="pk-cx-trk"></span>?</b>
  <label class="sl-field"><span class="sl-lab">Reason</span><input class="sl-in" name="reason" required placeholder="Customer not home"></label>
  <div class="row g8"><button class="sl-btn" type="submit">Cancel pickup</button><button type="button" class="sl-btn ghost" data-sl-close>Keep it</button></div></form></div>
<?php endif; ?>
<?php if ($can['code']) { $slCodeModule = 'air'; include CDP_ROOT . '/views/sl/inc/pickup_code_dialog.php'; } ?>
<script>
document.addEventListener('click', function (e) {
  var b = e.target.closest('[data-drv-id]'); if (b) { document.getElementById('pk-drv-id').value = b.getAttribute('data-drv-id'); document.getElementById('pk-drv-trk').textContent = b.getAttribute('data-drv-trk'); }
  var c = e.target.closest('[data-cx-id]'); if (c) { document.getElementById('pk-cx-id').value = c.getAttribute('data-cx-id'); document.getElementById('pk-cx-trk').textContent = c.getAttribute('data-cx-trk'); }
});
</script>
