<?php
/**
 * ControlPanelsMobile3 - generated from docs/handover/artifact-source/ControlPanelsMobile3.dc.html by scripts/sl_convert.py.
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
    <div class="m-sec" style="margin-top:0"><h2>All modules</h2><span class="mut sm">3 of 3</span></div>
    <section class="m-card"><div class="m-ch"><span class="tl"><i class="ic i-message" aria-hidden="true"></i></span><div><b>Messages &amp; support</b><span>Every conversation with customers</span></div></div><a class="m-row" href="<?= sl_route('CsInbox') ?>"><span class="m-ri"><i class="ic ic-sm i-ticket" aria-hidden="true"></i></span><span class="nm"><span>Support inbox</span><small>Tickets and replies</small></span><span class="sl-badge mono">1,482</span><i class="ic ic-sm i-right ch" aria-hidden="true"></i></a><a class="m-row" href="<?= sl_route('Notifications') ?>"><span class="m-ri"><i class="ic ic-sm i-send" aria-hidden="true"></i></span><span class="nm"><span>Messaging</span><small>SMS, WhatsApp and email</small></span><i class="ic ic-sm i-right ch" aria-hidden="true"></i></a><a class="m-row" href="<?= sl_route('MessageLogs') ?>"><span class="m-ri"><i class="ic ic-sm i-clock" aria-hidden="true"></i></span><span class="nm"><span>Message logs</span><small>Delivery status of every message</small></span><i class="ic ic-sm i-right ch" aria-hidden="true"></i></a><a class="m-row" href="<?= sl_route('CsAI') ?>"><span class="m-ri"><i class="ic ic-sm i-spark" aria-hidden="true"></i></span><span class="nm"><span>AI assistant</span><small>Drafts and auto-replies</small></span><i class="ic ic-sm i-right ch" aria-hidden="true"></i></a></section><section class="m-card"><div class="m-ch"><span class="tl"><i class="ic i-activity" aria-hidden="true"></i></span><div><b>Insights &amp; audit</b><span>Reports and the record of who did what</span></div></div><a class="m-row" href="<?= sl_route('Reports') ?>"><span class="m-ri"><i class="ic ic-sm i-folder" aria-hidden="true"></i></span><span class="nm"><span>Reports</span><small>Revenue, volume and delivery</small></span><i class="ic ic-sm i-right ch" aria-hidden="true"></i></a><a class="m-row" href="<?= sl_route('ActivityLogs') ?>"><span class="m-ri"><i class="ic ic-sm i-activity" aria-hidden="true"></i></span><span class="nm"><span>Activity logs</span><small>Audit trail</small></span><span class="sl-badge mono">3.4k</span><i class="ic ic-sm i-right ch" aria-hidden="true"></i></a><a class="m-row" href="<?= sl_route('CsReports') ?>"><span class="m-ri"><i class="ic ic-sm i-chart" aria-hidden="true"></i></span><span class="nm"><span>Support reports</span><small>Resolution and satisfaction</small></span><i class="ic ic-sm i-right ch" aria-hidden="true"></i></a></section><section class="m-card"><div class="m-ch"><span class="tl"><i class="ic i-terminal" aria-hidden="true"></i></span><div><b>Platform</b><span>The system behind the workspace</span></div></div><a class="m-row" href="<?= sl_route('DevOverview') ?>"><span class="m-ri"><i class="ic ic-sm i-terminal" aria-hidden="true"></i></span><span class="nm"><span>Developer overview</span><small>Services, logs and deploys</small></span><span class="sl-badge mono">12</span><i class="ic ic-sm i-right ch" aria-hidden="true"></i></a><a class="m-row" href="<?= sl_route('DevBilling') ?>"><span class="m-ri"><i class="ic ic-sm i-card" aria-hidden="true"></i></span><span class="nm"><span>Usage &amp; billing</span><small>Credits and plan</small></span><i class="ic ic-sm i-right ch" aria-hidden="true"></i></a><a class="m-row" href="<?= sl_route('DevIntegrations') ?>"><span class="m-ri"><i class="ic ic-sm i-puzzle" aria-hidden="true"></i></span><span class="nm"><span>Integrations</span><small>Webhooks and API keys</small></span><i class="ic ic-sm i-right ch" aria-hidden="true"></i></a><a class="m-row" href="<?= sl_route('SettingsHub') ?>"><span class="m-ri"><i class="ic ic-sm i-cog" aria-hidden="true"></i></span><span class="nm"><span>Settings</span><small>Company, brand and system</small></span><i class="ic ic-sm i-right ch" aria-hidden="true"></i></a></section>
    <div class="m-step" aria-label="Screen 3 of 3"><i class=""></i><i class=""></i><i class="on"></i></div>
    <a class="sl-btn soft" href="<?= sl_route('ControlPanelsMobile') ?>" style="width:100%">Back to top<i class="ic ic-sm i-right" aria-hidden="true"></i></a>
  </main>
  <nav class="m-tabs" aria-label="App navigation">
    <a href="<?= sl_route('Main') ?>"><i class="ic i-grid" aria-hidden="true"></i>Home</a>
    <a href="<?= sl_route('AirShipments') ?>"><i class="ic i-plane" aria-hidden="true"></i>Shipments</a>
    <a class="fab" href="<?= sl_route('NewShipment') ?>" aria-label="New shipment"><span><i class="ic i-plus" aria-hidden="true"></i></span></a>
    <a href="<?= sl_route('CsInbox') ?>"><i class="ic i-ticket" aria-hidden="true"></i>Inbox</a>
    <a class="on" href="<?= sl_route('ControlPanelsMobile') ?>"><i class="ic i-layers" aria-hidden="true"></i>Panels</a>
  </nav>
</div>
