<?php
/**
 * UserHome - generated from docs/handover/artifact-source/UserHome.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  heat                         as $h
 *   loop  rows                         as $r
 *   value heat, rows
 * sl-wrap extras in the design: style="max-width:1240px"
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
        <div class="row g8 sm mut"><i class="ic ic-sm i-user" aria-hidden="true"></i><span>My account</span><i class="ic ic-sm i-right" aria-hidden="true"></i><span style="color:var(--ink)">Overview</span></div>
        <div class="sl-head">
          <div><h1 class="sl-title" style="font-size:26px">Welcome back, Efua</h1><p class="sl-lede">Locker SL-3318 · Gold member · November 2026</p></div>
          <div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('Payments') ?>"><i class="ic ic-sm i-receipt" aria-hidden="true"></i>My bills</a><a class="sl-btn ghost" href="<?= sl_route('PublicTracking') ?>"><i class="ic ic-sm i-route" aria-hidden="true"></i>Track</a><a class="sl-btn" href="<?= sl_route('PreAlerts') ?>"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New pre-alert</a></div>
        </div>

        <section class="col g12" aria-label="My shipping addresses"><div class="row between wrap g8"><div><h2 class="sl-ct">My shipping addresses</h2><div class="mut sm">Paste one at checkout. Your suite number is the same in every country.</div></div><button class="sl-btn sm ghost"><i class="ic ic-sm i-copy" aria-hidden="true"></i>Copy full address</button></div>
<div class="ad-card"><div class="ad-main"><div class="ad-tabs"><span class="on"><i class="ic ic-sm i-plane" aria-hidden="true"></i>US · Air</span><span class=""><i class="ic ic-sm i-ship" aria-hidden="true"></i>US · Sea</span><span class=""><i class="ic ic-sm i-plane" aria-hidden="true"></i>UK</span><span class=""><i class="ic ic-sm i-ship" aria-hidden="true"></i>China</span><span class=""><i class="ic ic-sm i-plane" aria-hidden="true"></i>Dubai</span></div><div class="ad-lines"><div class="ad-ln"><small>Recipient</small><span class="fw5">Efua Owusu</span><button aria-label="Copy Recipient"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button></div><div class="ad-ln"><small>Address 1</small><span class="fw5">c/o Swift Lane · 410 Doremus Ave</span><button aria-label="Copy Address 1"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button></div><div class="ad-ln"><small>Address 2</small><span class="fw5">Suite SL-3318</span><button aria-label="Copy Address 2"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button></div><div class="ad-ln"><small>City</small><span class="fw5">Newark</span><button aria-label="Copy City"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button></div><div class="ad-ln"><small>State / ZIP</small><span class="fw5">NJ 07105</span><button aria-label="Copy State / ZIP"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button></div><div class="ad-ln"><small>Phone</small><span class="fw5">+1 (973) 555-0142</span><button aria-label="Copy Phone"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button></div></div></div>
<div class="ad-side"><div class="col g4"><small style="font-size:11px;letter-spacing:.06em;text-transform:uppercase;opacity:.6">Your suite</small><span class="ad-suite">SL-3318</span><span class="sm" style="opacity:.7">Air to Accra in 5 to 7 days</span></div><div class="ad-bc" aria-hidden="true"></div><span class="mono sm" style="opacity:.7">US · AIR · EWR → ACC</span></div></div>
<div class="ad-more"><div class="ad-m"><div class="row between"><span class="cc">US</span><button class="sl-ib" aria-label="Copy US · Sea address"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button></div><b class="fw5">US · Sea</b><span class="mut sm">Newark, NJ · 6–8 weeks</span></div><div class="ad-m"><div class="row between"><span class="cc">UK</span><button class="sl-ib" aria-label="Copy United Kingdom address"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button></div><b class="fw5">United Kingdom</b><span class="mut sm">London · 7–10 days</span></div><div class="ad-m"><div class="row between"><span class="cc">CN</span><button class="sl-ib" aria-label="Copy China address"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button></div><b class="fw5">China</b><span class="mut sm">Guangzhou · 35–45 days</span></div><div class="ad-m"><div class="row between"><span class="cc">AE</span><button class="sl-ib" aria-label="Copy Dubai address"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button></div><b class="fw5">Dubai</b><span class="mut sm">Dubai, UAE · 6–9 days</span></div></div><div class="mut sm">New addresses appear here as soon as Swift Lane adds them.</div></section>
<div class="split">
          <!-- Left: who I am -->
          <div class="b col g16" style="flex:1 1 380px">
            <section class="sl-card col g16">
              <div class="row g16 top">
                <span class="sl-av lg dark" style="width:72px;height:72px;font-size:22px">EO</span>
                <div class="grow col g4">
                  <div class="row g8 wrap"><h2 style="font-size:19px;font-weight:600">Efua Owusu</h2><span class="sl-badge">Gold</span></div>
                  <div class="mut sm">Online shopper · Accra, Greater Accra</div>
                  <div class="row g6 mut sm"><i class="ic ic-sm i-shield" aria-hidden="true"></i>ID verified</div>
                </div>
                <div class="ur-ring" style="background:conic-gradient(var(--green) 0 93%,var(--line) 93% 100%)" role="img" aria-label="Profile 93% complete"><b>93</b></div>
              </div>
              <div class="grid" style="grid-template-columns:repeat(3,minmax(0,1fr));gap:8px">
                <div><div class="up">Member since</div><div class="fw5 sm">17 Aug 2019</div></div>
                <div><div class="up">Last active</div><div class="fw5 sm">2 hours ago</div></div>
                <div><div class="up">Last shipment</div><div class="fw5 sm">Nov 3</div></div>
              </div>
              <div class="row g8 wrap">
                <a class="sl-btn sm ghost grow" href="<?= sl_route('CsInbox') ?>"><i class="ic ic-sm i-message" aria-hidden="true"></i>WhatsApp</a>
                <a class="sl-btn sm ghost grow" href="<?= sl_route('CsInbox') ?>"><i class="ic ic-sm i-mail" aria-hidden="true"></i>Email</a>
                <a class="sl-btn sm ghost grow" href="<?= sl_route('CsInbox') ?>"><i class="ic ic-sm i-phone" aria-hidden="true"></i>Call us</a>
              </div>
            </section>

            <section class="sl-card">
              <div class="sl-ch"><h2 class="sl-ct">My details</h2><a class="sl-ib" href="<?= sl_route('SettingsHub') ?>" aria-label="Edit details"><i class="ic ic-sm i-edit" aria-hidden="true"></i></a></div>
              <div class="ur-dl">
                <div><span class="k"><i class="ic ic-sm i-key" aria-hidden="true"></i>Locker ID</span><span class="v mono">SL-3318</span></div>
                <div><span class="k"><i class="ic ic-sm i-mail" aria-hidden="true"></i>Email</span><span class="v">efua.owusu@[email]</span></div>
                <div><span class="k"><i class="ic ic-sm i-phone" aria-hidden="true"></i>Phone</span><span class="v">Mobile +233 24 *** 5678<br>WhatsApp same number</span></div>
                <div><span class="k"><i class="ic ic-sm i-pin" aria-hidden="true"></i>Delivery address</span><span class="v">14 Palm Close<br>East Legon, Accra</span></div>
                <div><span class="k"><i class="ic ic-sm i-plane" aria-hidden="true"></i>Preferred mode</span><span class="v">Air express</span></div>
                <div><span class="k"><i class="ic ic-sm i-user" aria-hidden="true"></i>Account manager</span><span class="v row g8"><span class="sl-av sm">AS</span>Adwoa Sarpong</span></div>
                <div><span class="k"><i class="ic ic-sm i-bell" aria-hidden="true"></i>Alerts</span><span class="v row g8"><span class="sl-toggle on" aria-hidden="true"></span>WhatsApp and SMS</span></div>
                <div><span class="k"><i class="ic ic-sm i-tag" aria-hidden="true"></i>Interests</span><span class="v row g6 wrap"><span class="ur-chip">Electronics</span><span class="ur-chip">Fashion</span><span class="ur-chip">Beauty</span><a class="ur-chip" href="<?= sl_route('SettingsHub') ?>" style="background:transparent;border:1px dashed var(--line-2)"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add</a></span></div>
              </div>
            </section>

            <section class="sl-card col g16">
              <div class="sl-ch" style="margin:0"><div><h2 class="sl-ct">My shipper standing</h2><div class="mut sm">Better standing unlocks lower rates</div></div><span class="sl-badge ok">Strong</span></div>
              <div class="ur-score"><span>On-time payment</span><div class="sl-ticks" role="img" aria-label="96%"><i style="width:96%"></i></div><span class="mono sm" style="text-align:right">96%</span></div>
              <div class="ur-score"><span>Value accuracy</span><div class="sl-ticks" role="img" aria-label="88%"><i style="width:88%"></i></div><span class="mono sm" style="text-align:right">88%</span></div>
              <div class="ur-score"><span>Pickup on time</span><div class="sl-ticks y" role="img" aria-label="64%"><i style="width:64%"></i></div><span class="mono sm" style="text-align:right">64%</span></div>
              <div class="ur-score"><span>Pre-alert rate</span><div class="sl-ticks" role="img" aria-label="81%"><i style="width:81%"></i></div><span class="mono sm" style="text-align:right">81%</span></div>
              <p class="mut sm">Collect parcels within 3 days of arrival to reach Platinum.</p>
            </section>
          </div>

          <!-- Right: what I am shipping -->
          <div class="a col g16" style="flex:2 1 640px">
            

            <div class="cp" role="note" aria-label="Christmas air rate promotion"><div class="cp-l"><span class="up">Air rate</span><b>$6</b><span class="sm mut">per lb · <s>$9</s></span></div>
<div class="cp-m"><div class="row g8"><span class="sl-pill delivered">Promo on now</span><span class="mut sm">12 Oct – 31 Dec 2026</span></div><b style="font-size:18px;font-weight:500">Christmas air rate, applied automatically</b><span class="mut sm">Every air package checked in before Dec 31 ships at $6 per lb. Nothing to enter.</span></div>
<div class="cp-r" aria-label="Time left"><div class="cp-t"><b>55</b><small>days</small></div><div class="cp-t"><b>14</b><small>hours</small></div><div class="cp-t"><b>22</b><small>min</small></div></div></div>

            <div class="grid cols4">
              <div class="sl-card sl-metric"><div class="sl-ml"><span>My shipments</span></div><div class="big">12</div><div class="sl-ticks n"><i style="width:60%"></i></div><div class="sl-mf">▲ 3 vs last month</div></div>
              <div class="sl-card sl-metric"><div class="sl-ml"><span>This month</span></div><div class="big">4</div><div class="sl-ticks n"><i style="width:33%"></i></div><div class="sl-mf">22.4 kg shipped</div></div>
              <div class="sl-card sl-metric"><div class="sl-ml"><span>At warehouse</span></div><div class="big">2 <span class="mut" style="font-size:15px">/ 5 bays</span></div><div class="sl-ticks y"><i style="width:40%"></i></div><div class="sl-mf">14 free storage days left</div></div>
              <div class="sl-card sl-metric"><div class="sl-ml"><span>Balance due</span></div><div class="big">GHS 2,300</div><div class="sl-ticks r"><i style="width:18%"></i></div><div class="sl-mf">1 invoice · due Nov 8</div></div>
            </div>

            <section class="sl-card">
              <div class="sl-ch"><div><h2 class="sl-ct">My shipping rhythm</h2><div class="mut sm">Shipments per day, rolling year</div></div><div class="sl-peak" style="margin:0">Peak <b>3 a day (Mar 14)</b></div></div>
              <div class="sl-heatbox">
                <div class="sl-heatdays" style="padding-top:26px"><span></span><span>Mon</span><span></span><span>Wed</span><span></span><span>Fri</span><span></span></div>
                <div class="sl-heatcol">
                  <div class="sl-heatmonths"><span>Dec</span><span>Jan</span><span>Feb</span><span>Mar</span><span>Apr</span><span>May</span><span>Jun</span><span>Jul</span><span>Aug</span><span>Sep</span><span>Oct</span><span>Nov</span></div>
                  <div class="sl-heat" style="grid-template-columns:repeat(52,minmax(0,1fr))" role="img" aria-label="Shipments per day over the last year"><?php foreach ((array) sl_get($d, 'heat', []) as $__i => $h): ?><b class="<?= sl_v($h, 'c') ?>"></b><?php endforeach; ?></div>
                </div>
              </div>
              <div class="sl-legend"><span>Quiet</span><b style="background:var(--heat-0)"></b><b style="background:var(--heat-1)"></b><b style="background:var(--heat-2)"></b><b style="background:var(--heat-3)"></b><span>Busy</span></div>
            </section>

            <div class="grid cols4">
              <section class="sl-card col g12"><div class="row between"><div class="up row g6"><i class="ic ic-sm i-box" aria-hidden="true"></i>My packages</div><span class="mono fw5">12</span></div>
<div class="pk-seg" role="img" aria-label="2 in warehouse, 1 ready, 1 invoiced, 8 delivered"><i style="flex:2"></i><i style="flex:1"></i><i style="flex:1"></i><i style="flex:8"></i></div>
<div class="pk-tl"><a class="pk-st " href="<?= sl_route('PackageDetail') ?>" style="color:inherit"><span class="pk-dot"><i class="ic ic-sm i-warehouse" aria-hidden="true"></i></span><span class="col"><span class="fw5">In warehouse</span><span class="mut sm">Newark · 2 bays</span></span><b class="n">2</b></a><a class="pk-st now" href="<?= sl_route('PackageDetail') ?>" style="color:inherit"><span class="pk-dot"><i class="ic ic-sm i-check" aria-hidden="true"></i></span><span class="col"><span class="fw5">Ready for pickup</span><span class="mut sm">Accra Hub · code 4821</span></span><b class="n">1</b></a><a class="pk-st " href="<?= sl_route('PackageDetail') ?>" style="color:inherit"><span class="pk-dot"><i class="ic ic-sm i-receipt" aria-hidden="true"></i></span><span class="col"><span class="fw5">Invoiced</span><span class="mut sm">Pay to release</span></span><b class="n">1</b></a><a class="pk-st " href="<?= sl_route('PackageDetail') ?>" style="color:inherit"><span class="pk-dot"><i class="ic ic-sm i-box" aria-hidden="true"></i></span><span class="col"><span class="fw5">Delivered</span><span class="mut sm">Last on Oct 30</span></span><b class="n">8</b></a></div>
<a class="sl-btn sm" href="<?= sl_route('LockerSearch') ?>" style="align-self:flex-start"><i class="ic ic-sm i-check" aria-hidden="true"></i>Show my pickup code</a></section>
              <section class="sl-card col"><div class="up row g6"><i class="ic ic-sm i-inbox" aria-hidden="true"></i>Pre-alerts &amp; pickups</div>
                <div class="ur-stat"><span class="up">Shipment pre-alerts</span><b>2</b></div>
                <div class="ur-stat"><span class="up">Package pre-alerts</span><b>3</b></div>
                <div class="ur-stat"><span class="up">Pickup requests</span><b>1</b></div>
              </section>
              <section class="sl-card col"><div class="up row g6"><i class="ic ic-sm i-receipt" aria-hidden="true"></i>My account</div>
                <div class="ur-stat"><span class="up">Billed to date</span><b>GHS 18,420</b></div>
                <div class="ur-stat"><span class="up">Paid to date</span><b>GHS 16,120</b></div>
                <div class="ur-stat"><span class="up">Balance owed</span><b style="color:var(--red-ink)">GHS 2,300</b></div>
              </section>
              <section class="sl-card col"><div class="up row g6"><i class="ic ic-sm i-grid" aria-hidden="true"></i>At a glance</div>
                <div class="ur-stat"><span class="up">Total weight shipped</span><b>146.8 <span class="mut" style="font-size:13px">kg</span></b></div>
                <div class="ur-stat"><span class="up">My recipients</span><b>3</b></div>
                <div class="ur-stat"><span class="up">Saved with promos</span><b>GHS 640</b></div>
              </section>
            </div>
          </div>
        </div>

        <div class="split">
          <section class="a sl-card col g16">
            <div class="ur-tabs" role="tablist"><a class="on" href="<?= sl_route('UserHome') ?>">Activity</a><a href="<?= sl_route('AirShipments') ?>">Shipments</a><a href="<?= sl_route('PreAlerts') ?>">Pre-alerts</a><a href="<?= sl_route('Payments') ?>">Invoices</a><a href="<?= sl_route('Pickups') ?>">Pickups</a></div>
            <div class="row between wrap g12">
              <div class="dv-tabs"><button class="on">All</button><a href="<?= sl_route('AirShipments') ?>">Shipments 4</a><a href="<?= sl_route('MyBills') ?>">Payments 2</a><a href="<?= sl_route('Notifications') ?>">Messages 3</a><a href="<?= sl_route('Pickups') ?>">Pickups 1</a></div>
              <div class="sl-sel" style="width:150px;height:34px"><span class="sm">Last 30 days</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div>
            </div>
            <div class="col g16">
              <div class="ur-ev"><span class="ico"><i class="ic ic-sm i-plane" aria-hidden="true"></i></span><div class="body col g6"><div class="row between wrap g8"><span class="sm"><a class="mono fw5" href="<?= sl_route('ShipmentDetail') ?>">SL-100480</a> status changed to <span class="sl-pill sorting">At warehouse</span></span><span class="mut sm">Nov 3, 14:20</span></div><div class="mut sm">Amazon order · 5.6 kg · Accra warehouse, bay 14</div></div></div>
              <div class="ur-ev"><span class="ico"><i class="ic ic-sm i-receipt" aria-hidden="true"></i></span><div class="body col g6"><div class="row between wrap g8"><span class="sm">Invoice <span class="mono fw5">INV-20931</span> issued for GHS 2,300</span><span class="mut sm">Nov 3, 14:25</span></div><div class="row g8"><span class="sl-pill overdue">Due Nov 8</span><a class="sl-btn sm" href="<?= sl_route('Payments') ?>">Pay now</a></div></div></div>
              <div class="ur-ev"><span class="ico"><i class="ic ic-sm i-route" aria-hidden="true"></i></span><div class="body col g6"><div class="row between wrap g8"><span class="sm"><a class="mono fw5" href="<?= sl_route('ShipmentDetail') ?>">SL-100431</a> status changed to <span class="sl-pill transit">In transit</span></span><span class="mut sm">Nov 2, 22:40</span></div><div class="mut sm">Nike · Landed at Kotoka Airport</div></div></div>
              <div class="ur-ev"><span class="ico"><i class="ic ic-sm i-check" aria-hidden="true"></i></span><div class="body col g6"><div class="row between wrap g8"><span class="sm">Payment received for <span class="mono fw5">INV-20877</span></span><span class="mut sm">Oct 30, 10:05</span></div><div class="row g8 sm"><span class="sl-pill paid">Paid</span><span class="mut">GHS 1,180 · MTN Mobile Money</span></div></div></div>
              <div class="ur-ev"><span class="ico"><i class="ic ic-sm i-inbox" aria-hidden="true"></i></span><div class="body row g12"><span class="sl-tile"><i class="ic i-box" aria-hidden="true"></i></span><div class="grow col g4"><div class="row between wrap g8"><span class="sm fw5">New pre-alert · Apple iPad Air</span><span class="sm" style="color:var(--amber-ink)">Arrives Nov 9</span></div><span class="mut sm">Declared $599.00 · UPS 1Z999AA10123456784</span></div></div></div>
              <div class="ur-ev"><span class="ico"><i class="ic ic-sm i-phone" aria-hidden="true"></i></span><div class="col g4" style="padding-top:4px"><span class="sm fw5">Adwoa Sarpong called you</span><span class="mut sm">1 outbound call · 03:26 · Oct 28, 16:31</span></div></div>
            </div>
          </section>

          <section class="b sl-card col g12">
            <div class="sl-ch" style="margin:0"><div class="row g12"><span class="sl-av dark">AS</span><div><h2 class="sl-ct">Swift Lane support</h2><div class="mut sm">Adwoa usually replies in 6 min</div></div></div><span class="sl-badge ok">Online</span></div>
            <div class="thread">
              <div class="bub"><small>Adwoa · 11:02</small>Hi Efua, your Amazon parcel SL-100480 is at the Accra warehouse. Would you like home delivery or pickup?</div>
              <div class="bub me"><small>You · 11:20</small>Home delivery please, after 4pm if possible.</div>
              <div class="bub"><small>Adwoa · 11:24</small>Done. Driver slot booked for Nov 5, 16:00–18:00. You'll get a WhatsApp alert when he leaves.</div>
            </div>
            <div class="composer"><textarea aria-label="Write a message" placeholder="Write a message"></textarea><div class="tb"><button class="ib" aria-label="Attach file"><i class="ic ic-sm i-paperclip" aria-hidden="true"></i></button><span class="grow"></span><button class="sl-btn sm soft"><i class="ic ic-sm i-send" aria-hidden="true"></i>Send</button></div></div>
          </section>
        </div>

        <section class="sl-card flush">
          <div class="row between wrap g12" style="padding:14px 16px">
            <div class="sl-seg boxed"><button class="on">Shipments</button><a href="<?= sl_route('PreAlerts') ?>">Pre-alert list</a><a href="<?= sl_route('AirShipments') ?>">Registered packages</a></div>
            <div class="sl-search" style="max-width:280px"><i class="ic ic-sm i-search" aria-hidden="true"></i><input aria-label="Search my shipments" placeholder="Search my shipments"></div>
          </div>
          <div class="sl-tw"><table class="sl-table">
            <thead><tr><th>Tracking</th><th>Store</th><th>Mode</th><th class="num">Weight</th><th class="num">Cost</th><th>Progress</th><th>Status</th><th></th></tr></thead>
            <tbody>
              <?php foreach ((array) sl_get($d, 'rows', []) as $__i => $r): ?><tr><td><a class="id" href="<?= sl_route('ShipmentDetail') ?>"><?= sl_v($r, 'id') ?></a></td><td><?= sl_v($r, 'from') ?></td><td><span class="sl-badge"><?= sl_v($r, 'mode') ?></span></td><td class="num"><?= sl_v($r, 'w') ?></td><td class="num"><?= sl_v($r, 'c') ?></td><td style="min-width:120px"><div class="sl-ticks n"><i style="<?= sl_v($r, 'pg') ?>"></i></div></td><td><span class="sl-pill <?= sl_v($r, 'cls') ?>"><?= sl_v($r, 'st') ?></span></td><td><a class="sl-btn sm ghost" href="<?= sl_route('ShipmentDetail') ?>">View</a></td></tr><?php endforeach; ?>
            </tbody>
          </table></div>
          <div class="sl-pager"><span>Showing 1–5 of 12 shipments</span><div class="sl-pg"><a class="on" href="<?= sl_route('UserHome') ?>">1</a><a href="<?= sl_route('UserHome') ?>">2</a><a href="<?= sl_route('UserHome') ?>">3</a></div></div>
        </section>
      
