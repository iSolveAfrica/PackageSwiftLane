<?php
/**
 * Overview (staff) - from docs/handover/artifact-source/Main.dc.html.
 * Data: views/sl/data/overview.php (sl_overviewData). Markup follows the approved design.
 */
if (!isset($d)) { $d = []; }
$wh = $d['warehouse']; $pk = $d['pickups']; $rv = $d['revenue'];
?>
        <h1 class="sl-title">Overview</h1>
<section class="sl-card dk-desk" aria-label="Your desk">
          <div class="dk-me">
            <div class="row g12"><span class="sl-av lg dark"><?= h($d['me']['ini']) ?></span><div><div class="fw5" style="font-size:16px"><?= h($d['me']['name']) ?></div><div class="mut sm"><?= h($slSub ?? '') ?></div></div></div>
            <div class="col g6"><div class="row between sm"><span class="mut"><?= h($d['shift']['label']) ?></span><span class="mono"><?= (int) $d['shift']['pct'] ?>%</span></div><div class="sl-ticks n"><i style="width:<?= (int) $d['shift']['pct'] ?>%"></i></div></div>
            <div class="row between sm"><span class="mut">Cleared today</span><span><b class="mono"><?= (int) $d['doneToday'] ?></b> <span class="mut">actions · <?= (int) $d['queueTotal'] ?> in queues</span></span></div>
            <div class="col g8" style="margin-top:auto">
              <a class="sl-btn sm" href="<?= sl_route('Warehouse') ?>"><i class="ic ic-sm i-scan" aria-hidden="true"></i>Scan package</a>
              <div class="row g8"><a class="sl-btn sm ghost grow" href="<?= sl_route('RiderDispatch') ?>"><i class="ic ic-sm i-truck" aria-hidden="true"></i>Dispatch</a><a class="sl-btn sm ghost grow" href="<?= sl_route('AirShipments') ?>"><i class="ic ic-sm i-print" aria-hidden="true"></i>Print AWBs</a></div>
              <div class="row g8"><a class="sl-btn sm ghost grow" href="<?= sl_route('Customers', ['filter' => 'unapproved']) ?>"><i class="ic ic-sm i-user" aria-hidden="true"></i>Approvals</a></div>
            </div>
          </div>
          <div class="dk-qs">
            <div class="row between" style="margin-bottom:6px"><div class="up row g8"><i class="ic ic-sm i-grid" aria-hidden="true"></i>Your desk · <?= (int) $d['queueTotal'] ?> items in your queues</div><span class="mut sm">Sorted by urgency</span></div>
            <div class="dk-grid"><?php foreach ($d['queues'] as $q): ?><a class="dk-q" href="<?= h($q['href']) ?>"><span class="sl-tile"><i class="ic ic-sm <?= h($q['icon']) ?>" aria-hidden="true"></i></span><span class="grow col"><span class="fw5"><?= h($q['label']) ?></span><span class="sm" style="color:<?= h($q['tone']) ?>"><?= h($q['sub']) ?></span></span><b class="mono"><?= (int) $q['n'] ?></b><i class="ic ic-sm i-right mut" aria-hidden="true"></i></a><?php endforeach; ?><?php if (!$d['queues']): ?><div class="sl-empty">No queues are assigned to your role.</div><?php endif; ?></div>
            <?php include __DIR__ . '/partials/overview_desk_stats.php'; ?>
          </div>
        </section>

        <div class="grid cols3">
          <?php if (cdp_financeEnabled() && cdp_canViewMoney($user, 'dashboard')): ?>
          <a class="sl-card sl-metric" href="<?= sl_route('FinancialOverview') ?>">
            <div class="sl-ml"><span><i class="ic ic-sm i-dollar" aria-hidden="true"></i>Revenue &amp; billing</span><i class="ic ic-sm i-right" aria-hidden="true"></i></div>
            <div class="sl-mv"><?= h(sl_money($rv['value'])) ?> <i><?= $rv['target'] > 0 ? '/ ' . h(sl_money($rv['target'])) . ' target' : 'this month' ?> (<?= sl_num($rv['parcels']) ?> parcels)</i></div>
            <div class="sl-ticks" role="img" aria-label="<?= (int) $rv['pct'] ?> percent of revenue target"><i style="width: <?= (int) $rv['pct'] ?>%"></i></div>
            <div class="sl-mf">Next settlement: <?= h($rv['next']) ?> <i class="ic ic-sm i-ext" aria-hidden="true"></i></div>
          </a>
          <?php else: ?>
          <a class="sl-card sl-metric" href="<?= sl_route('AirShipments') ?>">
            <div class="sl-ml"><span><i class="ic ic-sm i-plane" aria-hidden="true"></i>Shipments this month</span><i class="ic ic-sm i-right" aria-hidden="true"></i></div>
            <div class="sl-mv"><?= sl_num($rv['parcels']) ?> <i>registered since <?= h(date('M 1')) ?></i></div>
            <div class="sl-ticks" role="img" aria-label="Shipments registered this month"><i style="width: <?= sl_pct(date('j'), date('t')) ?>%"></i></div>
            <div class="sl-mf"><?= sl_pct(date('j'), date('t')) ?>% of the month gone <i class="ic ic-sm i-ext" aria-hidden="true"></i></div>
          </a>
          <?php endif; ?>
          <a class="sl-card sl-metric" href="<?= sl_route('Pickups') ?>">
            <div class="sl-ml"><span><i class="ic ic-sm i-truck" aria-hidden="true"></i>Pickups</span><i class="ic ic-sm i-right" aria-hidden="true"></i></div>
            <div class="sl-mv"><?= (int) $pk['done'] ?> / <?= (int) $pk['total'] ?> <i>completed today</i></div>
            <div class="sl-ticks y" role="img" aria-label="<?= (int) $pk['done'] ?> of <?= (int) $pk['total'] ?> pickups completed"><i style="width: <?= (int) $pk['pct'] ?>%"></i></div>
            <div class="sl-mf"><?= (int) $pk['drivers'] ?> drivers on route · <?= (int) $pk['unassigned'] ?> unassigned <i class="ic ic-sm i-ext" aria-hidden="true"></i></div>
          </a>
          <a class="sl-card sl-metric" href="<?= sl_route('Warehouse') ?>">
            <div class="sl-ml"><span><i class="ic ic-sm i-warehouse" aria-hidden="true"></i>Warehouse</span><i class="ic ic-sm i-right" aria-hidden="true"></i></div>
            <div class="sl-mv"><?= (int) $wh['used'] ?><?= $wh['capacity'] ? ' / ' . (int) $wh['capacity'] : '' ?> <i><?= $wh['capacity'] ? 'locker bays full (' . (int) $wh['pct'] . '%)' : 'parcels in the bays' ?></i></div>
            <div class="sl-ticks <?= $wh['pct'] >= 90 ? 'r' : ($wh['pct'] >= 70 ? 'y' : '') ?>" role="img" aria-label="<?= (int) $wh['pct'] ?> percent of bays used"><i style="width: <?= (int) $wh['pct'] ?>%"></i></div>
            <div class="sl-mf"><?= (int) $wh['arriving'] ?> arriving now · <?= (int) $wh['awaiting'] ?> awaiting pickup <i class="ic ic-sm i-ext" aria-hidden="true"></i></div>
          </a>
        </div>

        <section class="mn-brief" aria-label="Smart briefing" data-sl-scope><div class="mn-bh"><span class="mn-ai"><i class="ic i-ai" aria-hidden="true"></i></span><div class="col grow"><b class="fw5">Good <?= date('G') < 12 ? 'morning' : (date('G') < 17 ? 'afternoon' : 'evening') ?>, <?= h($userData->fname ?? '') ?>. <?= h($d['briefLead']) ?></b><span class="mut sm">Picked from shipments, payments, riders, messages and the warehouse · updated just now</span></div><a class="sl-btn ghost sm" href="<?= sl_route('StaffAI') ?>"><i class="ic ic-sm i-spark" aria-hidden="true"></i>Ask AI</a></div>
<div class="mn-sum" data-sl-filter-seg><?php foreach ($d['bfil'] as $f): ?><button type="button" class="mn-chip <?= h($f['cls']) ?>" data-f="<?= h($f['f']) ?>"><span class="mn-dot"></span><?= h($f['label']) ?></button><?php endforeach; ?></div>
<div class="mn-cards"><?php foreach ($d['brief'] as $b): ?><a class="mn-c <?= h($b['lvl']) ?>" href="<?= h($b['href']) ?>" data-sl-row data-f="<?= h($b['cat']) ?>"><div class="mn-ch"><span class="mn-ic"><i class="ic <?= h($b['icon']) ?>" aria-hidden="true"></i></span><span class="mn-tag"><?= h($b['tag']) ?></span><span class="mn-sev"><?= h($b['sev']) ?></span></div><div class="mn-fig"><b><?= h($b['big']) ?></b><span><?= h($b['bigS']) ?></span></div><div class="col g4"><span class="mn-t"><?= h($b['t']) ?></span><span class="mn-d"><?= h($b['d']) ?></span></div><span class="mn-go"><?= h($b['cta']) ?><i class="ic ic-sm i-right" aria-hidden="true"></i></span></a><?php endforeach; ?><div class="sl-empty" data-sl-empty hidden>Nothing in this category.</div></div></section>
<section class="mn-live"><div class="mn-lh"><span class="row g8"><span class="mn-pulse"></span><b class="fw5">Live now</b></span><span class="mut sm" data-sl-clock><?= h($d['clock']) ?></span></div><div class="mn-lk"><?php foreach ($d['pulse'] as $p): ?><a class="mn-k" href="<?= h($p['href']) ?>"><small><?= h($p['l']) ?></small><b class="<?= h($p['a']) ?>"><?= h($p['v']) ?></b><span class="mut" style="font-size:14px"><?= h($p['s']) ?></span></a><?php endforeach; ?></div>
<div class="mn-ticker" aria-live="polite"><?php foreach ($d['ticker'] as $x): ?><span class="mn-ti <?= h($x['cls']) ?>"><span class="sl-av sm" style="width:22px;height:22px;font-size:11px"><?= h($x['ini']) ?></span><b class="fw5"><?= h($x['who']) ?></b> <?= h($x['what']) ?> <span class="mono mut"><?= h($x['at']) ?></span></span><?php endforeach; ?><?php if (!$d['ticker']): ?><span class="mn-ti mut">No activity recorded yet today.</span><?php endif; ?></div></section>
<section class="sl-card col g12"><div class="sl-ch" style="margin:0"><div><h2 class="sl-ct">Next 14 days</h2><span class="mut sm">Expected parcels at the hub vs room in the bays</span></div><span class="sl-pill <?= h($d['fcPill']) ?>"><?= h($d['fcNote']) ?></span></div><div class="mn-fc" role="img" aria-label="Forecast of parcels for the next 14 days"><?php foreach ($d['fc'] as $f): ?><div class="mn-fd" title="<?= h($f['tip']) ?>"><i class="<?= h($f['c']) ?>" style="<?= h($f['h']) ?>"></i><small><?= h($f['l']) ?></small></div><?php endforeach; ?><span class="mn-cap" style="<?= h($d['capY']) ?>"><b><?= h($d['capacityLabel']) ?></b></span></div><div class="row wrap g8 sm"><a class="chipbtn" href="<?= sl_route('NewConsolidation') ?>">Plan an extra consolidation</a><a class="chipbtn" href="<?= sl_route('PickupAging') ?>">Clear unclaimed parcels</a><a class="chipbtn" href="<?= sl_route('Broadcast') ?>">Remind customers to collect</a></div></section>

        <?php if ($user->cdp_hasPermission('view_package_list')): ?>
        <section class="sl-card col g16" aria-label="US packages">
<div class="sl-ch" style="margin:0"><div><h2 class="sl-ct">US packages</h2><div class="mut sm">From the overseas warehouse to customers</div></div><div class="row g8"><a class="sl-btn sm ghost" href="<?= sl_route('PackageIntake') ?>"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Input package</a></div></div>
<div class="up7-pipe"><?php foreach ($d['pipe'] as $st): ?><div class="up7-st"><span class="up7-ic"><i class="ic ic-sm <?= h($st['icon']) ?>" aria-hidden="true"></i></span><b class="mono"><?= sl_num($st['n']) ?></b><span class="fw5 sm"><?= h($st['label']) ?></span><span class="mut" style="font-size:14px"><?= h($st['sub']) ?></span></div><?php endforeach; ?></div>
<div class="split"><div class="a col g8"><div class="row between"><span class="fw5">Collected vs input per day</span><span class="row g16 sm mut"><span class="row g6"><i style="width:10px;height:10px;border-radius:3px;background:#D6D2CA;display:inline-block"></i>Packages collected</span><span class="row g6"><i style="width:14px;height:2px;background:#E08A3C;display:inline-block"></i>Shipments input</span></span></div><svg <?= sl_chartAttrs($d['ciSeries'], $d['ciDays'], 'Packages collected and shipments input', 22.9, 617.1) ?> viewBox="0 0 640 174" preserveAspectRatio="none" style="width:100%;height:190px;display:block" role="img" aria-label="Packages collected and input per day for 14 days"><?= $d['ciBars'] ?><polyline points="<?= h($d['ciLine']) ?>" fill="none" stroke="#E08A3C" stroke-width="2.4" vector-effect="non-scaling-stroke"/><?= $d['ciDots'] ?></svg><div class="row between mut sm"><span><?= h($d['ciLabels'][0]) ?></span><span><?= h($d['ciLabels'][1]) ?></span><span><?= h($d['ciLabels'][2]) ?></span></div>
<div class="row g24 wrap sm"><span><b class="mono"><?= sl_num($d['ciTotals']['collected']) ?></b> <span class="mut">collected</span></span><span><b class="mono"><?= sl_num($d['ciTotals']['input']) ?></b> <span class="mut">input</span></span><span><b class="mono"><?= sl_num($d['ciTotals']['weight'], 1) ?> lb</b> <span class="mut">average weight</span></span></div></div>
<div class="b col g12"><span class="fw5">Collected by carrier</span><?php foreach ($d['carriers'] as $c): ?><div class="col g4"><div class="row between sm"><span><?= h($c['name']) ?></span><span class="mono"><?= (int) $c['pct'] ?>%</span></div><div class="up7-b"><i style="width:<?= (int) $c['w'] ?>%"></i></div></div><?php endforeach; ?><?php if (!$d['carriers']): ?><span class="mut sm">No packages in the last 30 days.</span><?php endif; ?>
<div class="col g8" style="margin-top:6px"><?php foreach ($d['departures'] as $dep): ?><div class="up7-dep"><i class="ic ic-sm <?= h($dep['icon']) ?>" aria-hidden="true"></i><div class="grow"><div class="fw5 sm"><?= h($dep['title']) ?></div><div class="mut" style="font-size:14px"><?= h($dep['sub']) ?></div></div><span class="mono sm"><?= (int) $dep['n'] ?> pkgs</span></div><?php endforeach; ?></div></div></div>
</section>
        <?php endif; ?>
<?php include __DIR__ . '/partials/overview_insights.php'; ?>

        <section class="sl-card" aria-label="Quick actions">
          <h2 class="sl-ct" style="margin-bottom: 12px">Quick Actions</h2>
          <div class="row wrap g12">
            <a class="sl-btn soft" href="<?= sl_route('PublicTracking') ?>"><i class="ic ic-sm i-search" aria-hidden="true"></i>Track Shipment <i class="ic ic-sm i-right" aria-hidden="true"></i></a>
            <a class="sl-btn ghost" style="border-color: transparent; background: transparent" href="<?= sl_route('Warehouse') ?>"><i class="ic ic-sm i-scan" aria-hidden="true"></i>Scan Package <i class="ic ic-sm i-right" aria-hidden="true"></i></a>
            <a class="sl-btn ghost" style="border-color: transparent; background: transparent" href="<?= sl_route('ComposeMessage') ?>"><i class="ic ic-sm i-send" aria-hidden="true"></i>Send Update <i class="ic ic-sm i-right" aria-hidden="true"></i></a>
            <span class="grow"></span>
            <?php if ($user->cdp_hasPermission('add_shipment')): ?><a class="sl-btn" href="<?= sl_route('NewShipment') ?>"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New Shipment</a><?php endif; ?>
          </div>
        </section>

        <?php if ($d['gateways'] && $user->cdp_hasPermission('manage_payment_modes')): ?>
        <div class="grid cols2">
          <?php foreach ($d['gateways'] as $g): ?>
          <section class="sl-card flush">
            <div style="padding: 18px 20px 14px"><div class="row g8"><i class="ic i-card" aria-hidden="true"></i><h2 class="sl-ct"><?= h($g['name']) ?></h2></div><p class="mut" style="margin-top: 6px"><?= h(mb_substr($g['desc'], 0, 90)) ?></p></div>
            <hr class="hr">
            <div class="row between" style="padding: 14px 20px; background: var(--hover)"><div><div class="fw5"><?= $g['active'] ? 'Live account' : 'Not active' ?></div><div class="mut sm"><?= $g['configured'] ? 'Keys configured' : 'No API keys yet' ?></div></div><a class="sl-btn ghost sm" href="<?= h($g['href']) ?>"><?= $g['configured'] ? 'Manage' : 'Connect' ?></a></div>
          </section>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
