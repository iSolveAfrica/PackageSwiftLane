<?php
/**
 * Pickups (home pickups) - collect parcels from customers' homes in the US and bring them to the US warehouse.
 * Data: views/sl/data/pickups.php (sl_pickupsData). Actions: ajax/sl/pickup_ajax.php (driver, collected,
 * received, refuse, cancel, delete); accept keeps pickup_accept.php (pricing form).
 * Styles: assets/swiftlane/overview-hero.css (cards) + assets/swiftlane/pages/Pickups.css.
 */
if (!isset($d)) { $d = []; }
$can = $d['can'];
$steps = ['Requested', 'Scheduled', 'Collected', 'At warehouse', 'Shipped'];
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/overview-hero.css')) ?>">
        <div class="sl-head">
          <div><h1 class="sl-title"><?= $d['isClient'] ? 'My pickups' : 'Home pickups' ?></h1><p class="sl-lede"><?= h($d['lede']) ?></p></div>
          <div class="row g8 wrap"><?php if ($d['canAdd']): ?><a class="sl-btn" href="<?= $d['isClient'] ? 'pickup_add.php' : sl_route('SchedulePickup') ?>"><i class="ic ic-sm i-plus" aria-hidden="true"></i><?= $d['isClient'] ? 'Request a pickup' : 'Schedule pickup' ?></a><?php endif; ?></div>
        </div>

        <nav class="pk-flow" aria-label="Pickup stages">
          <?php foreach ($d['pipe'] as $i => $p): ?>
          <a class="pk-st s-<?= h($p['k']) ?><?= $d['filter'] === $p['k'] ? ' on' : '' ?>" href="<?= h(sl_url(['filter' => $d['filter'] === $p['k'] ? null : $p['k'], 'page' => null])) ?>"<?= $d['filter'] === $p['k'] ? ' aria-current="true"' : '' ?>>
            <span class="pk-ic"><i class="ic ic-sm <?= h($p['icon']) ?>" aria-hidden="true"></i></span>
            <b><?= sl_num($p['n']) ?></b><span class="fw5"><?= h($p['label']) ?></span><small title="<?= h($p['sub']) ?>"><?= h($p['sub']) ?></small>
          </a>
          <?php endforeach; ?>
        </nav>

        <div class="pk-cards">
          <?php foreach ($d['cards'] as $c): ?>
          <a class="ovh-card t-<?= h($c['tone']) ?>" href="<?= h(sl_url(['filter' => $c['f'], 'page' => null])) ?>">
            <div class="ovh-ch"><span class="ovh-eyebrow"><i class="ic ic-sm <?= h($c['icon']) ?>" aria-hidden="true"></i><?= h($c['eyebrow']) ?></span><i class="ic ic-sm i-expand ovh-go" aria-hidden="true"></i></div>
            <b class="ovh-big"><?= h($c['big']) ?></b>
            <span class="ovh-lede"><?= h($c['lede']) ?></span>
            <?php if ($c['alert']): ?><span class="ovh-alert pk-al"><i class="ic ic-sm i-alert" aria-hidden="true"></i><?= h($c['alert']) ?></span><?php endif; ?>
            <?php if (isset($c['meter'])): ?><div class="pk-meter" role="img" aria-label="<?= (int) $c['meter'] ?>%"><i style="width:<?= (int) $c['meter'] ?>%"></i></div><?php endif; ?>
            <?php if ($c['bars']): ?><div class="ovh-bars" role="img" aria-label="<?= h($c['foot']) ?>"><?php $nb = count($c['bars']); foreach ($c['bars'] as $bi => $b): ?><i class="<?= $bi === $nb - 1 ? 'now' : '' ?>" style="<?= h($b['s']) ?>" title="<?= h($b['t']) ?>"></i><?php endforeach; ?></div><?php endif; ?>
            <div class="ovh-foot"><span><?= h($c['foot']) ?></span></div>
          </a>
          <?php endforeach; ?>
        </div>

        <?php if (!$d['isClient']): ?>
        <div class="grid cols2">
          <section class="sl-card col g12"><div class="row between"><div><h2 class="sl-ct">Today's windows</h2><div class="mut sm">Pickups booked against each slot's capacity</div></div></div>
            <?php foreach ($d['slots'] as $s): ?><div class="col g6"><div class="row between sm"><span class="fw5"><?= h($s['w']) ?></span><span class="<?= $s['full'] ? 'pk-full' : 'mut' ?>"><?= h($s['txt']) ?></span></div><div class="pk-meter<?= $s['full'] ? ' full' : '' ?>"><i style="width:<?= (int) $s['pct'] ?>%"></i></div></div><?php endforeach; ?>
          </section>
          <section class="sl-card col g12"><div><h2 class="sl-ct">Riders on pickups today</h2><div class="mut sm">Collected out of the stops each rider has</div></div>
            <?php foreach ($d['drivers'] as $s): ?><div class="col g6"><div class="row between sm"><span class="fw5"><?= h($s['name']) ?></span><span class="mut"><?= h($s['txt']) ?></span></div><div class="pk-meter"><i style="width:<?= (int) $s['pct'] ?>%"></i></div></div><?php endforeach; ?>
            <?php if (!$d['drivers']): ?><div class="sl-empty sm"><span class="sl-tile"><i class="ic i-wheel" aria-hidden="true"></i></span>No rider has pickups today. Assign one from the list below.</div><?php endif; ?>
          </section>
        </div>
        <?php endif; ?>

        <div class="sl-card flush">
          <form method="get" class="pk-tools">
            <input type="hidden" name="when" value="<?= h($d['when']) ?>"><input type="hidden" name="filter" value="<?= h($d['filter']) ?>">
            <div class="sl-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input name="q" value="<?= h($d['q']) ?>" aria-label="Search pickups" placeholder="Search pickup, customer, phone or address"></div>
            <div class="sl-seg boxed"><?php foreach ($d['whens'] as $x): ?><a class="<?= $x['f'] === $d['when'] ? 'on' : '' ?>" href="<?= h(sl_url(['when' => $x['f'] === 'all' ? null : $x['f'], 'page' => null])) ?>"><?= h($x['label']) ?></a><?php endforeach; ?></div>
            <span class="grow"></span>
            <?php if (!$d['isClient']): ?><label class="sl-sel native" style="width: 170px"><select name="driver" aria-label="Rider" onchange="this.form.submit()"><option value="0">All riders</option><?php foreach ($d['driverList'] as $dr): ?><option value="<?= (int) $dr->id ?>" <?= (int) $dr->id === $d['driver'] ? 'selected' : '' ?>><?= h($dr->fname . ' ' . $dr->lname) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></label><?php endif; ?>
          </form>
          <div class="pk-tabs" role="tablist" aria-label="Stage"><?php foreach ($d['tabs'] as $x): ?><a role="tab" aria-selected="<?= $x['f'] === $d['filter'] ? 'true' : 'false' ?>" class="<?= $x['f'] === $d['filter'] ? 'on' : '' ?>" href="<?= h(sl_url(['filter' => $x['f'], 'page' => null])) ?>"><?= h($x['label']) ?></a><?php endforeach; ?></div>
          <div class="sl-tw"><table class="sl-table pk-table">
            <thead><tr><th>Pickup</th><?php if (!$d['isClient']): ?><th>Customer</th><?php endif; ?><th>Collect from (US)</th><th>When</th><th style="min-width:150px">Progress &amp; rider</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($d['rows'] as $r): ?>
                <tr>
                  <td><a class="id" href="courier_view.php?id=<?= (int) $r['id'] ?>"><?= h($r['trk']) ?></a><span class="sub"><?= (int) $r['items'] ?> item<?= (int) $r['items'] === 1 ? '' : 's' ?> · <?= h($r['kg']) ?><?= $d['money'] ? ' · ' . h($r['fee']) : '' ?></span></td>
                  <?php if (!$d['isClient']): ?><td><div class="sl-cell"><span class="sl-av sm"><?= h($r['ini']) ?></span><span><?= h($r['name']) ?><span class="sub"><?= h($r['phone'] ?: $r['locker']) ?></span></span></div></td><?php endif; ?>
                  <td><div class="pk-addr"><?= h($r['addr']) ?><span class="sub"><?= h($r['area']) ?></span></div></td>
                  <td><span class="fw5<?= $r['late'] ? ' pk-late' : '' ?>"><?= h($r['day']) ?></span><span class="sub"><?= h($r['win']) ?><?= $r['late'] ? ' · missed' : '' ?></span></td>
                  <td>
                    <span class="sl-pill <?= h($r['cls']) ?>"><?= h($r['st']) ?></span>
                    <?php if ($r['step'] !== -1): ?><div class="pk-steps" role="img" aria-label="Step <?= (int) $r['step'] + 1 ?> of 5: <?= h($r['st']) ?>"><?php foreach ($steps as $si => $sl): ?><i class="<?= $si < $r['step'] ? 'done' : ($si === $r['step'] ? 'now' : '') ?>" title="<?= h($sl) ?>"></i><?php endforeach; ?></div><?php endif; ?>
                    <span class="sub pk-rider"><i class="ic ic-sm i-wheel" aria-hidden="true"></i><?= $r['drv'] !== '' ? h($r['drv']) : ($r['open'] ? '<span class="pk-none">No rider yet</span>' : '—') ?></span>
                  </td>
                  <td><span class="row g4 pk-act">
                    <?php if ($r['requested'] && $can['accept']): ?><a class="sl-btn sm" href="pickup_accept.php?id=<?= (int) $r['id'] ?>">Accept</a>
                    <?php elseif ($r['open'] && $r['stage'] !== 'collected' && $r['drv'] === '' && $can['driver']): ?><button type="button" class="sl-btn sm ghost" data-sl-open="pk-driver" data-drv-id="<?= (int) $r['id'] ?>" data-drv-trk="<?= h($r['trk']) ?>">Assign rider</button>
                    <?php elseif ($r['stage'] === 'scheduled' && $can['collect']): ?><button type="button" class="sl-btn sm ghost" data-sl-open="pk-collect" data-col-id="<?= (int) $r['id'] ?>" data-col-trk="<?= h($r['trk']) ?>" data-col-name="<?= h($r['name']) ?>" title="Mark collected from the home">Collected</button>
                    <?php elseif ($r['stage'] === 'collected' && $can['receive']): ?><button type="button" class="sl-btn sm" data-sl-open="pk-receive" data-rcv-id="<?= (int) $r['id'] ?>" data-rcv-trk="<?= h($r['trk']) ?>" title="Receive at the US warehouse">Receive</button><?php endif; ?>
                  <details class="sl-menu" data-sl-pop><summary class="sl-ib sm" aria-label="Actions for <?= h($r['trk']) ?>"><i class="ic i-more" aria-hidden="true"></i></summary><div class="pop" role="menu">
                    <a role="menuitem" href="courier_view.php?id=<?= (int) $r['id'] ?>">View</a>
                    <?php if ($r['open'] && $can['driver']): ?><button type="button" role="menuitem" data-sl-open="pk-driver" data-drv-id="<?= (int) $r['id'] ?>" data-drv-trk="<?= h($r['trk']) ?>"><?= $r['drv'] !== '' ? 'Change rider' : 'Assign rider' ?></button><?php endif; ?>
                    <?php if (in_array($r['stage'], ['requested', 'scheduled'], true) && $can['collect']): ?><button type="button" role="menuitem" data-sl-open="pk-collect" data-col-id="<?= (int) $r['id'] ?>" data-col-trk="<?= h($r['trk']) ?>" data-col-name="<?= h($r['name']) ?>">Mark collected</button><?php endif; ?>
                    <?php if ($r['open'] && $can['receive']): ?><button type="button" role="menuitem" data-sl-open="pk-receive" data-rcv-id="<?= (int) $r['id'] ?>" data-rcv-trk="<?= h($r['trk']) ?>">Receive at US warehouse</button><?php endif; ?>
                    <?php if ($r['open'] && $can['code'] && !$r['requested']): ?><button type="button" role="menuitem" data-sl-open="sp-code" data-code-id="<?= (int) $r['id'] ?>" data-code-trk="<?= h($r['trk']) ?>" data-code-module="air">Pickup code</button><?php endif; ?>
                    <?php if ($can['label']): ?><a role="menuitem" target="_blank" href="print_label_ship.php?id=<?= (int) $r['id'] ?>">Print label</a><?php endif; ?>
                    <?php if ($can['print'] && $r['open']): ?><a role="menuitem" target="_blank" href="print_inv_ship.php?id=<?= (int) $r['id'] ?>">Print invoice</a><?php endif; ?>
                    <?php if ($r['requested'] && $can['refuse']): ?><button type="button" role="menuitem" data-sl-action="ajax/sl/pickup_ajax.php" data-p-op="refuse" data-p-id="<?= (int) $r['id'] ?>" data-sl-confirm="Refuse pickup <?= h($r['trk']) ?>?" data-sl-reload>Refuse</button><?php endif; ?>
                    <?php if (in_array($r['stage'], ['requested', 'scheduled'], true) && $can['cancel'] && (!$d['isClient'] || $r['requested'])): ?><button type="button" role="menuitem" data-sl-open="pk-cancel" data-cx-id="<?= (int) $r['id'] ?>" data-cx-trk="<?= h($r['trk']) ?>">Cancel</button><?php endif; ?>
                    <?php if ($can['delete']): ?><button type="button" role="menuitem" class="danger" data-sl-action="ajax/sl/pickup_ajax.php" data-p-op="delete" data-p-id="<?= (int) $r['id'] ?>" data-sl-confirm="Delete pickup <?= h($r['trk']) ?> for good?" data-sl-reload>Delete</button><?php endif; ?>
                  </div></details></span></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$d['rows']): ?><tr><td colspan="<?= $d['isClient'] ? 5 : 6 ?>"><div class="sl-empty"><span class="sl-tile"><i class="ic i-truck" aria-hidden="true"></i></span><?= $d['isClient'] ? 'No pickups yet. Request one and a rider will collect from your home.' : 'No pickups match.' ?><?php if ($d['canAdd'] && $d['isClient']): ?> <a class="sl-btn sm" href="pickup_add.php">Request a pickup</a><?php endif; ?></div></td></tr><?php endif; ?>
            </tbody>
          </table></div>
          <?= sl_pager($d['page'], $d['per'], $d['count'], 'pickups') ?>
        </div>

<?php if ($can['driver']): ?>
<div class="sl-dialog" id="pk-driver" hidden><form class="sl-modal" data-sl-form data-sl-reload action="ajax/sl/pickup_ajax.php" method="post" role="dialog" aria-label="Assign rider"><input type="hidden" name="op" value="driver"><input type="hidden" name="id" id="pk-drv-id">
  <b class="fw5">Assign a rider to <span class="mono" id="pk-drv-trk"></span></b>
  <label class="sl-field"><span class="sl-lab">Rider</span><span class="sl-sel native"><select name="driver_id" required><option value="">Choose</option><?php foreach ($d['driverList'] as $dr): ?><option value="<?= (int) $dr->id ?>"><?= h($dr->fname . ' ' . $dr->lname) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
  <div class="row g8"><button class="sl-btn" type="submit">Assign</button><button type="button" class="sl-btn ghost" data-sl-close>Cancel</button></div></form></div>
<?php endif; ?>
<?php if ($can['collect']): ?>
<div class="sl-dialog" id="pk-collect" hidden><form class="sl-modal" data-sl-form data-sl-reload action="ajax/sl/pickup_ajax.php" method="post" role="dialog" aria-label="Mark collected"><input type="hidden" name="op" value="collected"><input type="hidden" name="id" id="pk-col-id">
  <b class="fw5">Collected <span class="mono" id="pk-col-trk"></span> from the customer's home?</b>
  <p class="mut sm" style="margin:0">Riders usually do this in the Rider app. Use it here when the office records the collection.</p>
  <label class="sl-field"><span class="sl-lab">Handed over by</span><input class="sl-in" name="person" id="pk-col-name" required></label>
  <div class="row g8"><button class="sl-btn" type="submit">Mark collected</button><button type="button" class="sl-btn ghost" data-sl-close>Cancel</button></div></form></div>
<?php endif; ?>
<?php if ($can['receive']): ?>
<div class="sl-dialog" id="pk-receive" hidden><form class="sl-modal" data-sl-form data-sl-reload action="ajax/sl/pickup_ajax.php" method="post" role="dialog" aria-label="Receive at the US warehouse"><input type="hidden" name="op" value="received"><input type="hidden" name="id" id="pk-rcv-id">
  <b class="fw5">Receive <span class="mono" id="pk-rcv-trk"></span> at the US warehouse</b>
  <p class="mut sm" style="margin:0">The parcel becomes "In warehouse" and joins the normal flow: weigh it, consolidate it and ship it.</p>
  <label class="sl-field"><span class="sl-lab">Bay (optional)</span><input class="sl-in mono" name="bay" maxlength="12" placeholder="A-12"></label>
  <div class="row g8"><button class="sl-btn" type="submit">Receive</button><button type="button" class="sl-btn ghost" data-sl-close>Cancel</button></div></form></div>
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
  var set = function (id, v) { var el = document.getElementById(id); if (el) { if ('value' in el && el.tagName !== 'SPAN') el.value = v; else el.textContent = v; } };
  var b = e.target.closest('[data-drv-id]'); if (b) { set('pk-drv-id', b.getAttribute('data-drv-id')); set('pk-drv-trk', b.getAttribute('data-drv-trk')); }
  var c = e.target.closest('[data-cx-id]'); if (c) { set('pk-cx-id', c.getAttribute('data-cx-id')); set('pk-cx-trk', c.getAttribute('data-cx-trk')); }
  var k = e.target.closest('[data-col-id]'); if (k) { set('pk-col-id', k.getAttribute('data-col-id')); set('pk-col-trk', k.getAttribute('data-col-trk')); set('pk-col-name', k.getAttribute('data-col-name')); }
  var r = e.target.closest('[data-rcv-id]'); if (r) { set('pk-rcv-id', r.getAttribute('data-rcv-id')); set('pk-rcv-trk', r.getAttribute('data-rcv-trk')); }
});
</script>
