<?php
/**
 * ControlPanelsMobile2 - generated from docs/handover/artifact-source/ControlPanelsMobile2.dc.html by scripts/sl_convert.py.
 * Shell page: no (plain page)
 * Data contract ($d):
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="sl-app dk m">
  <header class="m-bar">
    <button class="sl-ib" aria-label="Open menu"><i class="ic i-sidebar" aria-hidden="true"></i></button>
    <a class="sw" href="<?= sl_route('Main') ?>"><small>SL</small>Accra Hub<i class="ic ic-sm i-down" aria-hidden="true"></i></a>
    <a class="fx-chip" style="height:30px;padding:0 9px;font-size:13px;gap:4px" href="<?= sl_route('SettingsHub') ?>" title="Exchange rate · set in Settings › General"><span class="mono">USD 1 = GHS 12.40</span></a>
    <span class="grow"></span>
    <a class="sl-ib" aria-label="Notifications" href="<?= sl_route('Notifications') ?>"><i class="ic i-bell" aria-hidden="true"></i></a>
    <span class="sl-av sm">H</span>
  </header>
  <main class="m-body">
    <div class="m-sec" style="margin-top:0"><h2>All modules</h2><span class="mut sm">2 of 3</span></div>
    <section class="m-card"><div class="m-ch"><span class="tl"><i class="ic i-warehouse" aria-hidden="true"></i></span><div><b>Warehouse &amp; dispatch</b><span>Bays, lockers, riders and routes</span></div></div><a class="m-row" href="<?= sl_route('Warehouse') ?>"><span class="m-ri"><i class="ic ic-sm i-warehouse" aria-hidden="true"></i></span><span class="nm"><span>Warehouse</span><small>Bays and scanning</small></span><span class="sl-badge mono">46 / 50</span><i class="ic ic-sm i-right ch" aria-hidden="true"></i></a><a class="m-row" href="<?= sl_route('LockerSearch') ?>"><span class="m-ri"><i class="ic ic-sm i-search" aria-hidden="true"></i></span><span class="nm"><span>Locker search</span><small>Find a parcel by locker</small></span><i class="ic ic-sm i-right ch" aria-hidden="true"></i></a><a class="m-row" href="<?= sl_route('Pickups') ?>"><span class="m-ri"><i class="ic ic-sm i-truck" aria-hidden="true"></i></span><span class="nm"><span>Pickups</span><small>Today’s pickup slots</small></span><span class="sl-badge mono">12 / 20</span><i class="ic ic-sm i-right ch" aria-hidden="true"></i></a><a class="m-row" href="<?= sl_route('Drivers') ?>"><span class="m-ri"><i class="ic ic-sm i-wheel" aria-hidden="true"></i></span><span class="nm"><span>Riders</span><small>On shift right now</small></span><span class="sl-badge mono">8 / 14</span><i class="ic ic-sm i-right ch" aria-hidden="true"></i></a></section><section class="m-card"><div class="m-ch"><span class="tl"><i class="ic i-users" aria-hidden="true"></i></span><div><b>Customers &amp; people</b><span>Who ships with us and who works here</span></div></div><a class="m-row" href="<?= sl_route('Customers') ?>"><span class="m-ri"><i class="ic ic-sm i-users" aria-hidden="true"></i></span><span class="nm"><span>Customers</span><small>Profiles and shipping marks</small></span><span class="sl-badge mono">1,284</span><i class="ic ic-sm i-right ch" aria-hidden="true"></i></a><a class="m-row" href="<?= sl_route('Recipients') ?>"><span class="m-ri"><i class="ic ic-sm i-user" aria-hidden="true"></i></span><span class="nm"><span>Recipients</span><small>People receiving parcels</small></span><i class="ic ic-sm i-right ch" aria-hidden="true"></i></a><a class="m-row" href="<?= sl_route('StaffRoles') ?>"><span class="m-ri"><i class="ic ic-sm i-shield" aria-hidden="true"></i></span><span class="nm"><span>Staff &amp; roles</span><small>Permissions and access</small></span><span class="sl-badge mono">46</span><i class="ic ic-sm i-right ch" aria-hidden="true"></i></a></section><section class="m-card"><div class="m-ch"><span class="tl"><i class="ic i-dollar" aria-hidden="true"></i></span><div><b>Money</b><span>What comes in, what is owed, what it costs</span></div></div><a class="m-row" href="<?= sl_route('Payments') ?>"><span class="m-ri"><i class="ic ic-sm i-card" aria-hidden="true"></i></span><span class="nm"><span>Payments</span><small>Gateway settlements</small></span><i class="ic ic-sm i-right ch" aria-hidden="true"></i></a><a class="m-row" href="<?= sl_route('AccountsReceivable') ?>"><span class="m-ri"><i class="ic ic-sm i-receipt" aria-hidden="true"></i></span><span class="nm"><span>Accounts receivable</span><small>Overdue invoices</small></span><span class="sl-badge mono">23</span><i class="ic ic-sm i-right ch" aria-hidden="true"></i></a><a class="m-row" href="<?= sl_route('FinancialOverview') ?>"><span class="m-ri"><i class="ic ic-sm i-chart" aria-hidden="true"></i></span><span class="nm"><span>Financial overview</span><small>Revenue and margin</small></span><i class="ic ic-sm i-right ch" aria-hidden="true"></i></a></section>
    <div class="m-step" aria-label="Screen 2 of 3"><i class=""></i><i class="on"></i><i class=""></i></div>
    <a class="sl-btn soft" href="<?= sl_route('ControlPanelsMobile3') ?>" style="width:100%">More modules<i class="ic ic-sm i-right" aria-hidden="true"></i></a>
  </main>
  <nav class="m-tabs" aria-label="App navigation">
    <a href="<?= sl_route('Main') ?>"><i class="ic i-grid" aria-hidden="true"></i>Home</a>
    <a href="<?= sl_route('AirShipments') ?>"><i class="ic i-plane" aria-hidden="true"></i>Shipments</a>
    <a class="fab" href="<?= sl_route('NewShipment') ?>" aria-label="New shipment"><span><i class="ic i-plus" aria-hidden="true"></i></span></a>
    <a href="<?= sl_route('CsInbox') ?>"><i class="ic i-ticket" aria-hidden="true"></i>Inbox</a>
    <a class="on" href="<?= sl_route('ControlPanelsMobile') ?>"><i class="ic i-layers" aria-hidden="true"></i>Panels</a>
  </nav>
</div>
