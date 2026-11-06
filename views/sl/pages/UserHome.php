<?php
/**
 * UserHome - from docs/handover/artifact-source/UserHome.dc.html (scripts/sl_convert.py), bound to customers_profile_edit.php.
 * Shell page: yes
 * Data contract ($d): see sl_userHomeData() in views/sl/data/portal.php
 */
if (!isset($d)) { $d = []; }
$fin = (bool) sl_get($d, 'finance');
$monthDelta = (int) sl_get($d, 'month') - (int) sl_get($d, 'prevMonth');
$total = max(1, (int) sl_get($d, 'total'));
$seg = [(int) sl_get($d, 'atWh'), (int) sl_get($d, 'ready'), (int) sl_get($d, 'invoiced'), (int) sl_get($d, 'delivered')];
?>
        <div class="row g8 sm mut"><i class="ic ic-sm i-user" aria-hidden="true"></i><span>My account</span><i class="ic ic-sm i-right" aria-hidden="true"></i><span style="color:var(--ink)">Overview</span></div>
        <div class="sl-head">
          <div><h1 class="sl-title" style="font-size:26px">Welcome back, <?= sl_v($d, 'first') ?></h1><p class="sl-lede">Locker <?= sl_v($d, 'locker') ?> · <?= h(date('F Y')) ?></p></div>
          <div class="row g8 wrap"><?php if ($fin): ?><a class="sl-btn ghost" href="<?= sl_route('MyBills') ?>"><i class="ic ic-sm i-receipt" aria-hidden="true"></i>My bills</a><?php endif; ?><a class="sl-btn ghost" href="<?= sl_route('PublicTracking') ?>"><i class="ic ic-sm i-route" aria-hidden="true"></i>Track</a><?php if (sl_get($d, 'canPrealert')): ?><a class="sl-btn" href="<?= sl_route('PreAlerts') ?>"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New pre-alert</a><?php endif; ?></div>
        </div>

        <?php include __DIR__ . '/partials/portal_addresses.php'; ?>
<div class="split">
          <div class="b col g16" style="flex:1 1 380px">
            <section class="sl-card col g16">
              <div class="row g16 top">
                <span class="sl-av lg dark" style="width:72px;height:72px;font-size:22px;<?= sl_get($d, 'avatar') ? 'background-image:url(' . h(sl_get($d, 'avatar')) . ');background-size:cover;color:transparent' : '' ?>"><?= sl_v($d, 'ini') ?></span>
                <div class="grow col g4">
                  <div class="row g8 wrap"><h2 style="font-size:19px;font-weight:600"><?= sl_v($d, 'name') ?></h2></div>
                  <div class="mut sm"><?= sl_get($d, 'addressCity') ? sl_v($d, 'addressCity') : 'Customer' ?></div>
                  <div class="row g6 mut sm"><i class="ic ic-sm i-shield" aria-hidden="true"></i><?= sl_get($d, 'idVerified') ? 'ID verified' : 'ID not verified yet' ?></div>
                </div>
                <?php $pc = (int) sl_get($d, 'complete'); ?><div class="ur-ring" style="background:conic-gradient(var(--green) 0 <?= $pc ?>%,var(--line) <?= $pc ?>% 100%)" role="img" aria-label="Profile <?= $pc ?>% complete"><b><?= $pc ?></b></div>
              </div>
              <div class="grid" style="grid-template-columns:repeat(3,minmax(0,1fr));gap:8px">
                <div><div class="up">Member since</div><div class="fw5 sm"><?= sl_v($d, 'created') ?></div></div>
                <div><div class="up">Last active</div><div class="fw5 sm"><?= sl_v($d, 'lastLogin') ?></div></div>
                <div><div class="up">Last shipment</div><div class="fw5 sm"><?= sl_v($d, 'lastShip') ?></div></div>
              </div>
              <div class="row g8 wrap">
                <a class="sl-btn sm ghost grow" href="support.php"><i class="ic ic-sm i-message" aria-hidden="true"></i>Message us</a>
                <a class="sl-btn sm ghost grow" href="<?= sl_route('AiStudio') ?>"><i class="ic ic-sm i-user" aria-hidden="true"></i>Photo and name</a>
                <a class="sl-btn sm ghost grow" href="<?= sl_route('PasswordReset') ?>" id="password"><i class="ic ic-sm i-lock" aria-hidden="true"></i>Password</a>
              </div>
            </section>

            <section class="sl-card">
              <div class="sl-ch"><h2 class="sl-ct">My details</h2><a class="sl-ib" href="<?= sl_route('UserHome', ['edit' => 1]) ?>" aria-label="Edit details"><i class="ic ic-sm i-edit" aria-hidden="true"></i></a></div>
              <div class="ur-dl">
                <div><span class="k"><i class="ic ic-sm i-key" aria-hidden="true"></i>Locker ID</span><span class="v mono"><?= sl_v($d, 'locker') ?></span></div>
                <div><span class="k"><i class="ic ic-sm i-mail" aria-hidden="true"></i>Email</span><span class="v"><?= sl_v($d, 'email') ?></span></div>
                <div><span class="k"><i class="ic ic-sm i-phone" aria-hidden="true"></i>Phone</span><span class="v"><?= sl_get($d, 'phone') ? sl_v($d, 'phoneMasked') . '<br>WhatsApp same number' : '<a href="' . h(sl_route('UserHome', ['edit' => 1])) . '">Add your phone</a>' ?></span></div>
                <div><span class="k"><i class="ic ic-sm i-pin" aria-hidden="true"></i>Delivery address</span><span class="v"><?= sl_get($d, 'address') ? sl_v($d, 'address') . '<br>' . sl_v($d, 'addressCity') : '—' ?></span></div>
                <div><span class="k"><i class="ic ic-sm i-user" aria-hidden="true"></i>Recipients</span><span class="v"><a href="<?= sl_route('Recipients') ?>"><?= (int) sl_get($d, 'recips') ?> saved</a></span></div>
                <div><span class="k"><i class="ic ic-sm i-bell" aria-hidden="true"></i>Alerts</span><span class="v row g8"><a href="<?= sl_route('CustomerNotifications') ?>"><?= sl_v($d, 'alerts') ?></a></span></div>
              </div>
            </section>
          </div>

          <div class="a col g16" style="flex:2 1 640px">
            <div class="grid cols4">
              <div class="sl-card sl-metric"><div class="sl-ml"><span>My shipments</span></div><div class="big"><?= (int) sl_get($d, 'total') ?></div><div class="sl-ticks n"><i style="width:<?= sl_pct(sl_get($d, 'month'), $total) ?>%"></i></div><div class="sl-mf"><?= $monthDelta >= 0 ? '▲ ' : '▼ ' ?><?= abs($monthDelta) ?> vs last month</div></div>
              <div class="sl-card sl-metric"><div class="sl-ml"><span>This month</span></div><div class="big"><?= (int) sl_get($d, 'month') ?></div><div class="sl-ticks n"><i style="width:<?= sl_pct(sl_get($d, 'month'), $total) ?>%"></i></div><div class="sl-mf"><?= sl_v($d, 'monthKg') ?> <?= h($GLOBALS['core']->weight_p ?? 'kg') ?> shipped</div></div>
              <div class="sl-card sl-metric"><div class="sl-ml"><span>At warehouse</span></div><div class="big"><?= (int) sl_get($d, 'atWh') ?></div><div class="sl-ticks y"><i style="width:<?= sl_pct(sl_get($d, 'atWh'), $total) ?>%"></i></div><div class="sl-mf"><?= (int) sl_get($d, 'ready') ?> ready for pickup</div></div>
              <?php if ($fin): ?><a class="sl-card sl-metric" href="<?= sl_route('MyBills') ?>" style="color:inherit"><div class="sl-ml"><span>Balance due</span></div><div class="big"><?= sl_v($d, 'dueMoney') ?></div><div class="sl-ticks r"><i style="width:<?= sl_pct(sl_get($d, 'dueN'), $total) ?>%"></i></div><div class="sl-mf"><?= (int) sl_get($d, 'dueN') ?> invoice<?= (int) sl_get($d, 'dueN') === 1 ? '' : 's' ?><?= sl_get($d, 'dueDate') ? ' · due ' . sl_v($d, 'dueDate') : '' ?></div></a><?php endif; ?>
            </div>

            <section class="sl-card">
              <div class="sl-ch"><div><h2 class="sl-ct">My shipping rhythm</h2><div class="mut sm">Shipments per day, rolling year</div></div><div class="sl-peak" style="margin:0">Peak <b><?= sl_v($d, 'peak') ?></b></div></div>
              <div class="sl-heatbox">
                <div class="sl-heatdays" style="padding-top:26px"><span></span><span>Mon</span><span></span><span>Wed</span><span></span><span>Fri</span><span></span></div>
                <div class="sl-heatcol">
                  <div class="sl-heatmonths"><?php foreach ((array) sl_get($d, 'months', []) as $m): ?><span><?= h($m) ?></span><?php endforeach; ?></div>
                  <div class="sl-heat" style="grid-template-columns:repeat(52,minmax(0,1fr))" role="img" aria-label="Shipments per day over the last year"><?php foreach ((array) sl_get($d, 'heat', []) as $h): ?><b class="<?= sl_v($h, 'c') ?>"></b><?php endforeach; ?></div>
                </div>
              </div>
              <div class="sl-legend"><span>Quiet</span><b style="background:var(--heat-0)"></b><b style="background:var(--heat-1)"></b><b style="background:var(--heat-2)"></b><b style="background:var(--heat-3)"></b><span>Busy</span></div>
            </section>

            <div class="grid cols4">
              <section class="sl-card col g12"><div class="row between"><div class="up row g6"><i class="ic ic-sm i-box" aria-hidden="true"></i>My parcels</div><span class="mono fw5"><?= (int) sl_get($d, 'total') ?></span></div>
<div class="pk-seg" role="img" aria-label="<?= $seg[0] ?> in warehouse, <?= $seg[1] ?> ready, <?= $seg[2] ?> invoiced, <?= $seg[3] ?> delivered"><?php foreach ($seg as $v): ?><i style="flex:<?= max(0, $v) ?>"></i><?php endforeach; ?></div>
<div class="pk-tl"><a class="pk-st" href="<?= sl_route('SeaPackages') ?>" style="color:inherit"><span class="pk-dot"><i class="ic ic-sm i-warehouse" aria-hidden="true"></i></span><span class="col"><span class="fw5">In warehouse</span><span class="mut sm">Being processed</span></span><b class="n"><?= $seg[0] ?></b></a><a class="pk-st <?= $seg[1] ? 'now' : '' ?>" href="<?= sl_route('AirShipments') ?>" style="color:inherit"><span class="pk-dot"><i class="ic ic-sm i-check" aria-hidden="true"></i></span><span class="col"><span class="fw5">Ready for pickup</span><span class="mut sm">Bring your ID</span></span><b class="n"><?= $seg[1] ?></b></a><?php if ($fin): ?><a class="pk-st" href="<?= sl_route('MyBills') ?>" style="color:inherit"><span class="pk-dot"><i class="ic ic-sm i-receipt" aria-hidden="true"></i></span><span class="col"><span class="fw5">Invoiced</span><span class="mut sm">Pay to release</span></span><b class="n"><?= $seg[2] ?></b></a><?php endif; ?><a class="pk-st" href="<?= sl_route('AirShipments') ?>" style="color:inherit"><span class="pk-dot"><i class="ic ic-sm i-box" aria-hidden="true"></i></span><span class="col"><span class="fw5">Delivered</span><span class="mut sm"><?= sl_get($d, 'lastDelivered') ? 'Last on ' . sl_v($d, 'lastDelivered') : 'None yet' ?></span></span><b class="n"><?= $seg[3] ?></b></a></div></section>
              <section class="sl-card col"><div class="up row g6"><i class="ic ic-sm i-inbox" aria-hidden="true"></i>Pre-alerts &amp; pickups</div>
                <div class="ur-stat"><span class="up">Shipment pre-alerts</span><b><?= (int) sl_get($d, 'preShip') ?></b></div>
                <div class="ur-stat"><span class="up">Package pre-alerts</span><b><?= (int) sl_get($d, 'prePkg') ?></b></div>
                <div class="ur-stat"><span class="up">Pickup requests</span><b><?= (int) sl_get($d, 'pickups') ?></b></div>
              </section>
              <?php if ($fin): ?><section class="sl-card col"><div class="up row g6"><i class="ic ic-sm i-receipt" aria-hidden="true"></i>My account</div>
                <div class="ur-stat"><span class="up">Billed to date</span><b><?= sl_v($d, 'billed') ?></b></div>
                <div class="ur-stat"><span class="up">Paid to date</span><b><?= sl_v($d, 'paid') ?></b></div>
                <div class="ur-stat"><span class="up">Balance owed</span><b style="color:var(--red-ink)"><?= sl_v($d, 'dueMoney') ?></b></div>
              </section><?php endif; ?>
              <section class="sl-card col"><div class="up row g6"><i class="ic ic-sm i-grid" aria-hidden="true"></i>At a glance</div>
                <div class="ur-stat"><span class="up">Total weight shipped</span><b><?= sl_v($d, 'totalKg') ?> <span class="mut" style="font-size:13px"><?= h($GLOBALS['core']->weight_p ?? 'kg') ?></span></b></div>
                <div class="ur-stat"><span class="up">My recipients</span><b><?= (int) sl_get($d, 'recips') ?></b></div>
                <div class="ur-stat"><span class="up">Profile complete</span><b><?= (int) sl_get($d, 'complete') ?>%</b></div>
              </section>
            </div>
          </div>
        </div>

        <section class="sl-card col g16">
          <div class="ur-tabs" role="tablist"><a class="on" href="<?= sl_route('UserHome') ?>">Activity</a><a href="<?= sl_route('AirShipments') ?>">Shipments</a><a href="<?= sl_route('PreAlerts') ?>">Pre-alerts</a><?php if ($fin): ?><a href="<?= sl_route('MyBills') ?>">Invoices</a><?php endif; ?><a href="<?= sl_route('Pickups') ?>">Pickups</a></div>
          <div class="col g16">
            <?php foreach ((array) sl_get($d, 'activity', []) as $a): ?>
            <?php if (!empty($a['pre'])): ?>
            <div class="ur-ev"><span class="ico"><i class="ic ic-sm i-inbox" aria-hidden="true"></i></span><div class="body row g12"><span class="sl-tile"><i class="ic i-box" aria-hidden="true"></i></span><div class="grow col g4"><div class="row between wrap g8"><span class="sm fw5">Pre-alert · <?= h($a['shop'] ?: $a['trk']) ?></span><?php if ($a['eta']): ?><span class="sm" style="color:var(--amber-ink)">Expected <?= h($a['eta']) ?></span><?php endif; ?></div><span class="mut sm">Declared <?= h($a['price']) ?> · <?= h($a['trk']) ?> · <?= h($a['at']) ?></span></div></div></div>
            <?php else: ?>
            <div class="ur-ev"><span class="ico"><i class="ic ic-sm <?= h($a['icon']) ?>" aria-hidden="true"></i></span><div class="body col g6"><div class="row between wrap g8"><span class="sm"><a class="mono fw5" href="<?= h(sl_route('PublicTracking', ['order_track' => $a['trk']])) ?>"><?= h($a['trk']) ?></a> status changed to <span class="sl-pill <?= h($a['cls']) ?>"><?= h($a['st']) ?></span></span><span class="mut sm"><?= h($a['at']) ?></span></div><?php if ($a['sub']): ?><div class="mut sm"><?= h($a['sub']) ?></div><?php endif; ?></div></div>
            <?php endif; ?>
            <?php endforeach; ?>
            <?php if (!sl_get($d, 'activity')): ?><div class="sl-empty mut sm">No activity yet.</div><?php endif; ?>
          </div>
        </section>

        <section class="sl-card flush">
          <form class="row between wrap g12" style="padding:14px 16px" method="get" action="<?= sl_route('UserHome') ?>">
            <div class="sl-seg boxed"><a class="on" href="<?= sl_route('UserHome') ?>">Shipments</a><a href="<?= sl_route('PreAlerts') ?>">Pre-alert list</a><a href="<?= sl_route('SeaPackages') ?>">Registered packages</a></div>
            <div class="sl-search" style="max-width:280px"><i class="ic ic-sm i-search" aria-hidden="true"></i><input name="q" value="<?= sl_v($d, 'q') ?>" aria-label="Search my shipments" placeholder="Search my shipments"></div>
          </form>
          <div class="sl-tw"><table class="sl-table">
            <thead><tr><th>Tracking</th><th>Store</th><th>Mode</th><th class="num">Weight</th><th class="num">Cost</th><th>Progress</th><th>Status</th><th></th></tr></thead>
            <tbody>
              <?php foreach ((array) sl_get($d, 'rows', []) as $r): ?><tr><td><a class="id" href="<?= sl_v($r, 'href') ?>"><?= sl_v($r, 'id') ?></a></td><td><?= sl_v($r, 'from') ?></td><td><span class="sl-badge"><?= sl_v($r, 'mode') ?></span></td><td class="num"><?= sl_v($r, 'w') ?></td><td class="num"><?= sl_v($r, 'c') ?></td><td style="min-width:120px"><div class="sl-ticks n"><i style="<?= sl_v($r, 'pg') ?>"></i></div></td><td><span class="sl-pill <?= sl_v($r, 'cls') ?>"><?= sl_v($r, 'st') ?></span></td><td><a class="sl-btn sm ghost" href="<?= sl_v($r, 'href') ?>">View</a></td></tr><?php endforeach; ?>
              <?php if (!sl_get($d, 'rows')): ?><tr><td colspan="8"><div class="sl-empty mut sm"><?= sl_get($d, 'q') ? 'No shipment matches your search.' : 'No shipments yet.' ?></div></td></tr><?php endif; ?>
            </tbody>
          </table></div>
          <?= sl_pager((int) sl_get($d, 'page', 1), (int) sl_get($d, 'per', 5), (int) sl_get($d, 'count', 0), 'shipments') ?>
        </section>
