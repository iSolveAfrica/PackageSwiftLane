<?php
/**
 * AiChatMobile - generated from docs/handover/artifact-source/AiChatMobile.dc.html by scripts/sl_convert.py.
 * Shell page: no (plain page)
 * Data contract ($d):
 *   loop  msgs                         as $m
 *   loop  chips                        as $c
 *   if    closed
 *   if    isOpen
 *   if    m.ai
 *   if    m.card
 *   if    m.me
 *   if    typing
 *   value bgHidden, chips, close, closed, draft, false, isOpen, msgs, onDraft, open, send, true, typing
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="sl-app dk ai">
  <div class="ai-bg" aria-hidden="<?= sl_v($d, 'bgHidden') ?>">
    <header class="ai-top">
      <button class="sl-ib" aria-label="Open menu"><i class="ic i-sidebar" aria-hidden="true"></i></button>
      <a class="sw" href="<?= sl_route('Main') ?>"><small>SL</small>Accra Hub</a>
    <a class="fx-chip" style="height:30px;padding:0 9px;font-size:13px;gap:4px" href="<?= sl_route('SettingsHub') ?>" title="Exchange rate · set in Settings › General"><span class="mono">USD 1 = GHS 12.40</span></a>
      <span class="grow"></span>
      <a class="sl-ib" aria-label="Notifications" href="<?= sl_route('Notifications') ?>"><i class="ic i-bell" aria-hidden="true"></i></a>
      <span class="sl-av sm">H</span>
    </header>
    <div class="ai-bgb">
      <div><div class="up">Good morning, Henry</div><div style="font-size:24px;font-weight:600;letter-spacing:-.02em">Today at Accra Hub</div></div>
      <div class="ai-g">
        <div class="ai-k"><span class="up">Held parcels</span><b>11</b><span class="sm" style="color:var(--amber-ink)">▲ 3 vs yesterday</span></div>
        <div class="ai-k"><span class="up">Open tickets</span><b>186</b><span class="mut sm">▼ 22 this week</span></div>
        <div class="ai-k"><span class="up">Pickups</span><b>12 / 20</b><span class="mut sm">3 unassigned</span></div>
        <div class="ai-k"><span class="up">On time</span><b>94.2%</b><span class="mut sm">Target 95%</span></div>
      </div>
    </div>
    <nav class="ai-tabs" aria-label="App navigation">
      <a href="<?= sl_route('Main') ?>"><i class="ic i-grid" aria-hidden="true"></i>Home</a>
      <a href="<?= sl_route('AirShipments') ?>"><i class="ic i-plane" aria-hidden="true"></i>Shipments</a>
      <a href="<?= sl_route('CsInbox') ?>"><i class="ic i-ticket" aria-hidden="true"></i>Inbox</a>
      <a class="on" href="<?= sl_route('AiChatMobile') ?>"><i class="ic i-spark" aria-hidden="true"></i>Assistant</a>
      <a href="<?= sl_route('ControlPanelsMobile') ?>"><i class="ic i-layers" aria-hidden="true"></i>Panels</a>
    </nav>
  </div>

  <?php if (sl_get($d, 'closed')): ?>
    <button class="ai-fab" data-sl-on="click:open" aria-label="Open Swift Lane AI"><span class="o"><i class="ic i-spark" aria-hidden="true"></i></span>Ask Swift Lane AI<em aria-hidden="true"></em></button>
  <?php endif; ?>

  <?php if (sl_get($d, 'isOpen')): ?>
    <div class="ai-dim" data-sl-on="click:close"></div>
    <section class="ai-sheet" role="dialog" aria-label="Swift Lane AI assistant">
      <div class="ai-handle" aria-hidden="true"></div>
      <div class="ai-head">
        <span class="ai-orb"><i class="ic i-spark" aria-hidden="true"></i></span>
        <div class="grow"><b>Swift Lane AI</b><small><span class="ai-live"></span>Online · knows your shipments, tickets and rates</small></div>
        <a class="sl-ib" href="<?= sl_route('CsAI') ?>" aria-label="Open full assistant"><i class="ic ic-sm i-expand" aria-hidden="true"></i></a>
        <button class="sl-ib" data-sl-on="click:close" aria-label="Close assistant"><i class="ic ic-sm i-x" aria-hidden="true"></i></button>
      </div>
      <div class="ai-msgs" aria-live="polite">
        <span class="ai-day">Today</span>
        <?php foreach ((array) sl_get($d, 'msgs', []) as $__i => $m): ?>
          <?php if (sl_get($m, 'ai')): ?>
            <div class="ai-row"><span class="ai-av"><i class="ic ic-sm i-spark" aria-hidden="true"></i></span><div><div class="ai-b"><?= sl_v($m, 'text') ?>
              <?php if (sl_get($m, 'card')): ?>
                <div class="ai-card">
                  <div class="row between"><a class="mono fw5" href="<?= sl_route('ShipmentDetail') ?>">SL-100480</a><span class="sl-pill sorting">At warehouse</span></div>
                  <div class="ai-steps" aria-label="4 of 5 steps done"><i class="on"></i><i class="on"></i><i class="on"></i><i class="on"></i><i></i></div>
                  <div class="row between sm mut"><span>Efua Owusu · 5.6 kg</span><span>Bay 14</span></div>
                  <div class="row g8"><a class="sl-btn sm" href="<?= sl_route('ShipmentDetail') ?>" style="flex:1">Open shipment</a><a class="sl-btn sm ghost" style="flex:1" href="<?= sl_route('ComposeMessage') ?>">Notify customer</a></div>
                </div>
              <?php endif; ?>
            </div><div class="ai-t"><?= sl_v($m, 't') ?></div></div></div>
          <?php endif; ?>
          <?php if (sl_get($m, 'me')): ?>
            <div class="ai-row me"><div style="display:flex;flex-direction:column;align-items:flex-end"><div class="ai-b"><?= sl_v($m, 'text') ?></div><div class="ai-t"><?= sl_v($m, 't') ?></div></div></div>
          <?php endif; ?>
        <?php endforeach; ?>
        <?php if (sl_get($d, 'typing')): ?>
          <div class="ai-row"><span class="ai-av"><i class="ic ic-sm i-spark" aria-hidden="true"></i></span><div class="ai-b ai-typing" aria-label="Assistant is typing"><i></i><i></i><i></i></div></div>
        <?php endif; ?>
      </div>
      <div class="ai-chips" aria-label="Suggested questions">
        <?php foreach ((array) sl_get($d, 'chips', []) as $__i => $c): ?><button data-sl-on="click:ask"><i class="ic ic-sm <?= sl_v($c, 'icon') ?>" aria-hidden="true"></i><?= sl_v($c, 'label') ?></button><?php endforeach; ?>
      </div>
      <div class="ai-comp">
        <label class="ai-in"><input aria-label="Ask the assistant" placeholder="Ask about a parcel, rate or ticket" value="<?= sl_v($d, 'draft') ?>" data-sl-on="change:onDraft"><button class="ai-ib" aria-label="Attach a photo or file"><i class="ic ic-sm i-paperclip" aria-hidden="true"></i></button></label>
        <button class="ai-send" data-sl-on="click:send" aria-label="Send"><i class="ic i-send" aria-hidden="true"></i></button>
      </div>
    </section>
  <?php endif; ?>
</div>
