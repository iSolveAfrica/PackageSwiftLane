<?php
/**
 * ClientDashboard - generated from docs/handover/artifact-source/ClientDashboard.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  rows                         as $r
 *   loop  tl                           as $t
 *   value rows, tl
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
        <div class="sl-head">
          <div><h1 class="sl-title">Welcome back, Efua</h1><p class="sl-lede">Locker SL-3318 · 2 parcels ready for pickup</p></div>
          <div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('PreAlerts') ?>"><i class="ic ic-sm i-inbox" aria-hidden="true"></i>Add Pre-Alert</a><a class="sl-btn" href="<?= sl_route('NewShipment') ?>"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New Shipment</a></div>
        </div>
        <div class="grid cols3">
          <div class="sl-card sl-metric"><div class="sl-ml"><span><i class="ic ic-sm i-plane" aria-hidden="true"></i>Active shipments</span></div><div class="sl-mv">4 <i>/ 12 this year</i></div><div class="sl-ticks"><i style="width: 33%"></i></div></div>
          <div class="sl-card sl-metric"><div class="sl-ml"><span><i class="ic ic-sm i-dollar" aria-hidden="true"></i>Balance due</span></div><div class="sl-mv">$185.50 <i>(1 invoice)</i></div><div class="sl-ticks r"><i style="width: 18%"></i></div><div class="sl-mf">Due Nov 8</div></div>
          <div class="sl-card sl-metric"><div class="sl-ml"><span><i class="ic ic-sm i-warehouse" aria-hidden="true"></i>Parcels at warehouse</span></div><div class="sl-mv">2 / 5</div><div class="sl-ticks v"><i style="width: 40%"></i></div><div class="sl-mf">14 free storage days left</div></div>
        </div>
        <section class="col g12" aria-label="My shipping addresses"><div class="row between wrap g8"><div><h2 class="sl-ct">My shipping addresses</h2><div class="mut sm">Paste one at checkout. Your suite number is the same in every country.</div></div><button class="sl-btn sm ghost"><i class="ic ic-sm i-copy" aria-hidden="true"></i>Copy full address</button></div>
<div class="ad-card"><div class="ad-main"><div class="ad-tabs"><span class="on"><i class="ic ic-sm i-plane" aria-hidden="true"></i>US · Air</span><span class=""><i class="ic ic-sm i-ship" aria-hidden="true"></i>US · Sea</span><span class=""><i class="ic ic-sm i-plane" aria-hidden="true"></i>UK</span><span class=""><i class="ic ic-sm i-ship" aria-hidden="true"></i>China</span><span class=""><i class="ic ic-sm i-plane" aria-hidden="true"></i>Dubai</span></div><div class="ad-lines"><div class="ad-ln"><small>Recipient</small><span class="fw5">Efua Owusu</span><button aria-label="Copy Recipient"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button></div><div class="ad-ln"><small>Address 1</small><span class="fw5">c/o Swift Lane · 410 Doremus Ave</span><button aria-label="Copy Address 1"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button></div><div class="ad-ln"><small>Address 2</small><span class="fw5">Suite SL-3318</span><button aria-label="Copy Address 2"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button></div><div class="ad-ln"><small>City</small><span class="fw5">Newark</span><button aria-label="Copy City"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button></div><div class="ad-ln"><small>State / ZIP</small><span class="fw5">NJ 07105</span><button aria-label="Copy State / ZIP"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button></div><div class="ad-ln"><small>Phone</small><span class="fw5">+1 (973) 555-0142</span><button aria-label="Copy Phone"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button></div></div></div>
<div class="ad-side"><div class="col g4"><small style="font-size:11px;letter-spacing:.06em;text-transform:uppercase;opacity:.6">Your suite</small><span class="ad-suite">SL-3318</span><span class="sm" style="opacity:.7">Air to Accra in 5 to 7 days</span></div><div class="ad-bc" aria-hidden="true"></div><span class="mono sm" style="opacity:.7">US · AIR · EWR → ACC</span></div></div>
<div class="ad-more"><div class="ad-m"><div class="row between"><span class="cc">US</span><button class="sl-ib" aria-label="Copy US · Sea address"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button></div><b class="fw5">US · Sea</b><span class="mut sm">Newark, NJ · 6–8 weeks</span></div><div class="ad-m"><div class="row between"><span class="cc">UK</span><button class="sl-ib" aria-label="Copy United Kingdom address"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button></div><b class="fw5">United Kingdom</b><span class="mut sm">London · 7–10 days</span></div><div class="ad-m"><div class="row between"><span class="cc">CN</span><button class="sl-ib" aria-label="Copy China address"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button></div><b class="fw5">China</b><span class="mut sm">Guangzhou · 35–45 days</span></div><div class="ad-m"><div class="row between"><span class="cc">AE</span><button class="sl-ib" aria-label="Copy Dubai address"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button></div><b class="fw5">Dubai</b><span class="mut sm">Dubai, UAE · 6–9 days</span></div></div><div class="mut sm">New addresses appear here as soon as Swift Lane adds them.</div></section>
        <div class="split">
          <section class="a sl-card flush">
            <div class="row between" style="padding: 14px 16px"><h2 class="sl-ct">My shipments</h2><a href="<?= sl_route('AirShipments') ?>" class="sm fw5 row g6">View all <i class="ic ic-sm i-right" aria-hidden="true"></i></a></div>
            <div class="sl-tw"><table class="sl-table"><thead><tr><th>Tracking</th><th>From</th><th class="num">Weight</th><th class="num">Cost</th><th>Status</th></tr></thead><tbody>
              <?php foreach ((array) sl_get($d, 'rows', []) as $__i => $r): ?><tr><td><a class="id" href="<?= sl_route('ShipmentDetail') ?>"><?= sl_v($r, 'id') ?></a></td><td><?= sl_v($r, 'from') ?></td><td class="num"><?= sl_v($r, 'w') ?></td><td class="num"><?= sl_v($r, 'c') ?></td><td><span class="sl-pill <?= sl_v($r, 'cls') ?>"><?= sl_v($r, 'st') ?></span></td></tr><?php endforeach; ?>
            </tbody></table></div>
          </section>
          <aside class="b col g16">
            <section class="sl-card col g12"><h2 class="sl-ct">Latest on SL-100480</h2>
              <div class="sl-tl">
                <?php foreach ((array) sl_get($d, 'tl', []) as $__i => $t): ?><div class="sl-tl-i <?= sl_v($t, 'c') ?>"><b><?= sl_v($t, 't') ?></b><span><?= sl_v($t, 'w') ?></span></div><?php endforeach; ?>
              </div>
            </section>
            
          </aside>
        </div>
      
        <div class="grid cols2" style="align-items:start">
          <section class="sl-card col g12" aria-label="Track a parcel"><div class="sl-ch" style="margin:0"><h2 class="sl-ct row g8"><i class="ic ic-sm i-track" aria-hidden="true"></i>Track a parcel</h2><a class="sm fw5" href="<?= sl_route('PublicTracking') ?>">Full tracking page →</a></div>
            <div class="row g8"><div class="sl-search" style="max-width:none;flex:1"><i class="ic ic-sm i-search" aria-hidden="true"></i><input aria-label="Tracking number" class="mono" value="SL-100480"></div><a class="sl-btn" href="<?= sl_route('PublicTracking') ?>">Track</a></div>
            <div class="row between"><span class="fw5 mono">SL-100480</span><span class="sl-pill sorting">At US warehouse</span></div>
            <div class="sl-ticks lg"><i style="width:40%"></i></div>
            <div class="cd-trk"><span><i class="d"><i class="ic ic-sm i-check" aria-hidden="true"></i></i>Registered</span><span class="now"><i class="d"><i class="ic ic-sm i-warehouse" aria-hidden="true"></i></i>US warehouse</span><span><i class="d w"><i class="ic ic-sm i-plane" aria-hidden="true"></i></i>In transit</span><span><i class="d w"><i class="ic ic-sm i-pin" aria-hidden="true"></i></i>Accra hub</span><span><i class="d w"><i class="ic ic-sm i-check" aria-hidden="true"></i></i>Picked up</span></div>
            <div class="mut sm">Amazon · 5.6 lb · next flight Fri Nov 7 · expected in Accra Nov 12</div></section>
          <section class="sl-card col g12" aria-label="AI support"><div class="sl-ch" style="margin:0"><h2 class="sl-ct row g8"><span class="sl-tile" style="width:28px;height:28px;background:var(--ink);color:var(--inverse)"><i class="ic ic-sm i-ai" aria-hidden="true"></i></span>AI support</h2><span class="row g6 sm" style="color:var(--green-ink)"><span style="width:7px;height:7px;border-radius:50%;background:var(--green);display:inline-block"></span>Online now</span></div>
            <div class="cd-ai"><div class="cd-b">Hi Efua. Your Amazon parcel SL-100480 is at our Newark warehouse and leaves on Friday's flight. Anything I can help with?</div><div class="cd-b me">How much will it cost?</div><div class="cd-b">5.6 lb at the Christmas rate of $6 per lb comes to about $36. You can pay by mobile money when it lands.</div></div>
            <div class="row g6 wrap"><span class="cd-chip"><i class="ic ic-sm i-spark" aria-hidden="true"></i>Where is my parcel?</span><span class="cd-chip"><i class="ic ic-sm i-spark" aria-hidden="true"></i>Pickup hours</span><span class="cd-chip"><i class="ic ic-sm i-spark" aria-hidden="true"></i>What can I ship?</span></div>
            <div class="row g8"><div class="sl-search" style="max-width:none;flex:1"><i class="ic ic-sm i-message" aria-hidden="true"></i><input aria-label="Ask AI support" placeholder="Ask anything about your parcels"></div><a class="sl-btn" href="<?= sl_route('CsAI') ?>" aria-label="Send"><i class="ic ic-sm i-send" aria-hidden="true"></i></a></div>
            <a class="sm mut" href="<?= sl_route('CsInbox') ?>">Prefer a person? Open a ticket with customer service →</a></section>
        </div>
        
