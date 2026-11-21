<?php
/**
 * Overview (staff) - from docs/handover/artifact-source/Main.dc.html.
 * Data: views/sl/data/overview.php (sl_overviewData). Markup follows the approved design.
 */
if (!isset($d)) { $d = []; }
$wh = $d['warehouse']; $pk = $d['pickups']; $rv = $d['revenue'];
?>
        <?php include __DIR__ . '/partials/overview_hero.php'; ?>

        <?php if (!empty($d['tracking']['items'])): $tc = $d['tracking']; $tcTitle = 'Tracking list'; $tcNoun = 'shipments'; $tcExpand = $user->cdp_hasPermission('track_shipment') ? sl_route('ShipmentTracking') : ''; ?>
        <?php include __DIR__ . '/partials/track_console.php'; ?>
        <?php endif; ?>

<?php if ($d['queues'] || $d['deskStats']): ?>
<section class="sl-card dk-desk solo" aria-label="Your desk">
          <div class="dk-qs">
            <div class="row between" style="margin-bottom:6px"><div class="up row g8"><i class="ic ic-sm i-grid" aria-hidden="true"></i>Your desk · <?= (int) $d['queueTotal'] ?> items in your queues</div><span class="mut sm">Sorted by urgency</span></div>
            <div class="dk-grid"><?php foreach ($d['queues'] as $q): ?><a class="dk-q" href="<?= h($q['href']) ?>"><span class="sl-tile"><i class="ic ic-sm <?= h($q['icon']) ?>" aria-hidden="true"></i></span><span class="grow col"><span class="fw5"><?= h($q['label']) ?></span><span class="sm" style="color:<?= h($q['tone']) ?>"><?= h($q['sub']) ?></span></span><b class="mono"><?= (int) $q['n'] ?></b><i class="ic ic-sm i-right mut" aria-hidden="true"></i></a><?php endforeach; ?><?php if (!$d['queues']): ?><div class="sl-empty">No queues are assigned to your role.</div><?php endif; ?></div>
            <?php include __DIR__ . '/partials/overview_desk_stats.php'; ?>
          </div>
        </section>
<?php endif; ?>

        <?php include __DIR__ . '/partials/overview_insights.php'; ?>

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
