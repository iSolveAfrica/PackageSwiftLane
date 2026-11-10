<?php
/**
 * ClientDashboard - from docs/handover/artifact-source/ClientDashboard.dc.html (scripts/sl_convert.py), bound to index.php (client branch).
 * Shell page: yes
 * Data contract ($d): see sl_clientDashboardData() in views/sl/data/portal.php
 *   first, locker, ready, active, year, finance, dueMoney, dueN, dueDate, duePct, atWh, addresses[], rows[], latest, tl[], latestSteps, aiOn, canShip, canPrealert
 */
if (!isset($d)) { $d = []; }
$latest = sl_get($d, 'latest');
$steps = sl_get($d, 'latestSteps');
?>
        <div class="sl-head">
          <div><h1 class="sl-title">Welcome back, <?= sl_v($d, 'first') ?></h1><p class="sl-lede">Locker <?= sl_v($d, 'locker') ?> · <?= (int) sl_get($d, 'ready') ?> parcel<?= (int) sl_get($d, 'ready') === 1 ? '' : 's' ?> ready for pickup</p></div>
          <div class="row g8 wrap"><?php if (sl_get($d, 'canPrealert')): ?><a class="sl-btn ghost" href="<?= sl_route('PreAlerts') ?>"><i class="ic ic-sm i-inbox" aria-hidden="true"></i>Add Pre-Alert</a><?php endif; ?><?php if (sl_get($d, 'canShip')): ?><a class="sl-btn" href="courier_add_client.php"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New Shipment</a><?php endif; ?></div>
        </div>
        <?php include __DIR__ . '/partials/mobile_dashboard.php'; ?>
        <div class="grid cols3">
          <a class="sl-card sl-metric" href="<?= sl_route('AirShipments') ?>" style="color:inherit"><div class="sl-ml"><span><i class="ic ic-sm i-plane" aria-hidden="true"></i>Active shipments</span></div><div class="sl-mv"><?= (int) sl_get($d, 'active') ?> <i>/ <?= (int) sl_get($d, 'year') ?> this year</i></div><div class="sl-ticks"><i style="width: <?= sl_pct(sl_get($d, 'active'), max(1, (int) sl_get($d, 'year'))) ?>%"></i></div></a>
          <?php if (sl_get($d, 'finance')): ?>
          <a class="sl-card sl-metric" href="<?= sl_route('MyBills') ?>" style="color:inherit"><div class="sl-ml"><span><i class="ic ic-sm i-dollar" aria-hidden="true"></i>Balance due</span></div><div class="sl-mv"><?= sl_v($d, 'dueMoney') ?> <i>(<?= (int) sl_get($d, 'dueN') ?> invoice<?= (int) sl_get($d, 'dueN') === 1 ? '' : 's' ?>)</i></div><div class="sl-ticks r"><i style="width: <?= (int) sl_get($d, 'duePct') ?>%"></i></div><?php if (sl_get($d, 'dueDate')): ?><div class="sl-mf">Due <?= sl_v($d, 'dueDate') ?></div><?php endif; ?></a>
          <?php endif; ?>
          <div class="sl-card sl-metric"><div class="sl-ml"><span><i class="ic ic-sm i-warehouse" aria-hidden="true"></i>Parcels at warehouse</span></div><div class="sl-mv"><?= (int) sl_get($d, 'atWh') ?> <i>/ <?= (int) sl_get($d, 'ready') ?> ready</i></div><div class="sl-ticks v"><i style="width: <?= sl_pct(sl_get($d, 'atWh'), max(1, (int) sl_get($d, 'active'))) ?>%"></i></div></div>
        </div>
        <?php include __DIR__ . '/partials/portal_addresses.php'; ?>
        <div class="split">
          <section class="a sl-card flush sl-desktop-shipments">
            <div class="row between" style="padding: 14px 16px"><h2 class="sl-ct">My shipments</h2><a href="<?= sl_route('UserHome') ?>" class="sm fw5 row g6">View all <i class="ic ic-sm i-right" aria-hidden="true"></i></a></div>
            <div class="sl-tw"><table class="sl-table"><thead><tr><th>Tracking</th><th>From</th><th class="num">Weight</th><th class="num">Cost</th><th>Status</th></tr></thead><tbody>
              <?php foreach ((array) sl_get($d, 'rows', []) as $r): ?><tr><td><a class="id" href="<?= sl_v($r, 'href') ?>"><?= sl_v($r, 'id') ?></a></td><td><?= sl_v($r, 'from') ?></td><td class="num"><?= sl_v($r, 'w') ?></td><td class="num"><?= sl_v($r, 'c') ?></td><td><span class="sl-pill <?= sl_v($r, 'cls') ?>"><?= sl_v($r, 'st') ?></span></td></tr><?php endforeach; ?>
              <?php if (!sl_get($d, 'rows')): ?><tr><td colspan="5"><div class="sl-empty mut sm">No shipments yet. Use your shipping address above when you shop online.</div></td></tr><?php endif; ?>
            </tbody></table></div>
          </section>
          <aside class="b col g16">
            <section class="sl-card col g12"><h2 class="sl-ct"><?= $latest ? 'Latest on ' . h($latest['id']) : 'Latest updates' ?></h2>
              <div class="sl-tl">
                <?php foreach ((array) sl_get($d, 'tl', []) as $t): ?><div class="sl-tl-i <?= sl_v($t, 'c') ?>"><b><?= sl_v($t, 't') ?></b><span><?= sl_v($t, 'w') ?></span></div><?php endforeach; ?>
                <?php if (!sl_get($d, 'tl')): ?><div class="sl-empty mut sm">No tracking updates yet.</div><?php endif; ?>
              </div>
            </section>
          </aside>
        </div>

        <div class="grid cols2" style="align-items:start">
          <section class="sl-card col g12" aria-label="Track a parcel"><div class="sl-ch" style="margin:0"><h2 class="sl-ct row g8"><i class="ic ic-sm i-track" aria-hidden="true"></i>Track a parcel</h2><a class="sm fw5" href="<?= sl_route('PublicTracking') ?>">Full tracking page →</a></div>
            <form class="row g8" method="get" action="<?= sl_route('PublicTracking') ?>"><div class="sl-search" style="max-width:none;flex:1"><i class="ic ic-sm i-search" aria-hidden="true"></i><input name="order_track" aria-label="Tracking number" class="mono" value="<?= h($latest['id'] ?? '') ?>" required></div><button class="sl-btn" type="submit">Track</button></form>
            <?php if ($latest && $steps): ?>
            <div class="row between"><span class="fw5 mono"><?= h($latest['id']) ?></span><span class="sl-pill <?= h($latest['cls']) ?>"><?= h($latest['st']) ?></span></div>
            <div class="sl-ticks lg"><i style="width:<?= (int) $steps['pct'] ?>%"></i></div>
            <div class="cd-trk"><?php foreach ($steps['steps'] as $s): ?><span class="<?= h($s['cls']) ?>"><i class="d <?= h($s['w']) ?>"><i class="ic ic-sm <?= $s['done'] && !$s['cls'] ? 'i-check' : h($s['i']) ?>" aria-hidden="true"></i></i><?= h($s['l']) ?></span><?php endforeach; ?></div>
            <div class="mut sm"><?= h(trim(($latest['from'] !== '—' ? $latest['from'] . ' · ' : '') . ($latest['w'] !== '—' ? $latest['w'] . ' · ' : '') . $latest['mode'] . ' · registered ' . $latest['date'])) ?></div>
            <?php endif; ?></section>
          <section class="sl-card col g12" aria-label="Support"><div class="sl-ch" style="margin:0"><h2 class="sl-ct row g8"><span class="sl-tile" style="width:28px;height:28px;background:var(--ink);color:var(--inverse)"><i class="ic ic-sm i-ai" aria-hidden="true"></i></span><?= sl_get($d, 'aiOn') ? 'AI support' : 'Support' ?></h2><?php if (sl_get($d, 'aiOn')): ?><span class="row g6 sm" style="color:var(--green-ink)"><span style="width:7px;height:7px;border-radius:50%;background:var(--green);display:inline-block"></span>Online now</span><?php endif; ?></div>
            <div class="cd-ai"><div class="cd-b">Hi <?= sl_v($d, 'first') ?>. Ask about a parcel, prices or pickup hours<?= sl_get($d, 'aiOn') ? ' and get an answer right away' : ' and our team will reply' ?>.</div></div>
            <form class="col g8" method="get" action="support.php"><div class="row g6 wrap"><button class="cd-chip" type="submit" name="q" value="Where is my parcel?"><i class="ic ic-sm i-spark" aria-hidden="true"></i>Where is my parcel?</button><button class="cd-chip" type="submit" name="q" value="What are your pickup hours?"><i class="ic ic-sm i-spark" aria-hidden="true"></i>Pickup hours</button><button class="cd-chip" type="submit" name="q" value="What can I ship?"><i class="ic ic-sm i-spark" aria-hidden="true"></i>What can I ship?</button></div>
            <div class="row g8"><div class="sl-search" style="max-width:none;flex:1"><i class="ic ic-sm i-message" aria-hidden="true"></i><input name="q" aria-label="Ask support" placeholder="Ask anything about your parcels"></div><button class="sl-btn" type="submit" aria-label="Send"><i class="ic ic-sm i-send" aria-hidden="true"></i></button></div></form>
            <a class="sm mut" href="support.php">Prefer a person? Open a ticket with customer service →</a></section>
        </div>
