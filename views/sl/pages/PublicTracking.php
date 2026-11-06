<?php
/**
 * PublicTracking - generated from docs/handover/artifact-source/PublicTracking.dc.html by scripts/sl_convert.py.
 * Shell page: no (plain page)
 * Data contract ($d):
 *   loop  events                       as $e
 *   value events, noop
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="sl-app">
  <header class="sl-top" style="height: 64px; padding: 0 24px">
    <a class="sl-brand" href="<?= sl_route('Main') ?>"><span class="sl-logo"><i></i></span>Swift Lane</a>
    <a class="fx-chip" href="<?= sl_route('SettingsHub') ?>" title="Exchange rate · set in Settings › General"><i class="ic ic-sm i-dollar" aria-hidden="true"></i><span class="mono">USD 1 = GHS 12.40</span><span class="fx-d">▲ 0.4%</span></a>
    <span class="grow"></span>
    <details class="aitop"><summary aria-label="Help with Swift Lane AI"><span class="o"><i class="ic i-ai" aria-hidden="true"></i></span>Help</summary><div class="pop" role="menu">
<div class="me"><span class="sl-av sm dark">HC</span><div class="grow"><div class="fw5 sm">Henry Cobblah</div><div class="mut" style="font-size:12px">Operations manager · Accra Hub</div></div><a class="sl-btn sm ghost" href="<?= sl_route('AiStudio') ?>">Edit</a></div>
<a class="ask" href="<?= sl_route('StaffAI') ?>"><i class="ic ic-sm i-spark" aria-hidden="true"></i>Ask AI for help on this page<b><i class="ic ic-sm i-send" aria-hidden="true"></i></b></a>
<div class="hd">Your profile with AI</div>
<a class="it" role="menuitem" href="<?= sl_route('AiStudio') ?>"><span class="t"><i class="ic ic-sm i-wand" aria-hidden="true"></i></span><span>Retouch my photo<small>Light, skin and background</small></span></a>
<a class="it" role="menuitem" href="<?= sl_route('AiStudio') ?>"><span class="t"><i class="ic ic-sm i-refresh" aria-hidden="true"></i></span><span>Reset images<small>Go back to the original photo</small></span></a>
<a class="it" role="menuitem" href="<?= sl_route('AiStudio') ?>"><span class="t"><i class="ic ic-sm i-edit" aria-hidden="true"></i></span><span>Change my name<small>AI fixes spelling and capitals</small></span></a>
<a class="it" role="menuitem" href="<?= sl_route('AiStudio') ?>"><span class="t"><i class="ic ic-sm i-user" aria-hidden="true"></i></span><span>Make my icon<small>Avatar from your photo or initials</small></span></a>
<div class="hd">Help</div>
<a class="it" role="menuitem" href="<?= sl_route('StaffAI') ?>"><span class="t"><i class="ic ic-sm i-help" aria-hidden="true"></i></span><span>Explain this page</span></a>
<a class="it" role="menuitem" href="<?= sl_route('CsInbox') ?>"><span class="t"><i class="ic ic-sm i-message" aria-hidden="true"></i></span><span>Talk to a person</span></a>
</div></details>
    <nav class="row g8 wrap"><a class="sl-btn ghost sm" href="<?= sl_route('Main') ?>">Get a quote</a><a class="sl-btn ghost sm" href="<?= sl_route('SignUp') ?>">Create account</a><a class="sl-btn sm" href="<?= sl_route('Login') ?>">Log in</a></nav>
  </header>
  <div class="sl-body" style="padding-top: 0">
    <main class="sl-main" style="padding: 56px 24px 64px">
      <div style="max-width: 860px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px">
        <div class="col g8" style="align-items: center; text-align: center">
          <span class="sl-badge">Public tracking</span>
          <h1 style="font-size: 38px; font-weight: 500; letter-spacing: -.02em; line-height: 1.1">Where is my parcel?</h1>
          <p class="mut" style="max-width: 480px">Enter your Swift Lane number or the carrier tracking number. No account needed.</p>
        </div>
        <form class="row wrap g8" data-sl-on="submit:noop">
          <div class="sl-search" style="max-width: none; height: 52px; flex: 1 1 320px"><i class="ic i-search" aria-hidden="true"></i><input aria-label="Tracking number" value="SL-100482" class="mono"></div>
          <button class="sl-btn" style="height: 52px; padding: 0 28px">Track parcel</button>
        </form>

        <section class="sl-card col g20" aria-label="Tracking result">
          <div class="row between wrap g12">
            <div><div class="up">Swift Lane number</div><div class="mono fw5" style="font-size: 20px; margin-top: 4px">SL-100482</div></div>
            <span class="sl-pill transit">In transit</span>
          </div>
          <div class="grid cols3">
            <div class="pt-kv"><span class="pt-ic"><i class="ic ic-sm i-pin" aria-hidden="true"></i></span><div><div class="up">From</div><div class="fw5">Newark, NJ, USA</div></div></div>
            <div class="pt-kv"><span class="pt-ic"><i class="ic ic-sm i-pin" aria-hidden="true"></i></span><div><div class="up">To</div><div class="fw5">Accra, Ghana</div></div></div>
            <div class="pt-kv"><span class="pt-ic"><i class="ic ic-sm i-clock" aria-hidden="true"></i></span><div><div class="up">Estimated delivery</div><div class="fw5">Nov 9, 2026</div></div></div>
          </div>
          <div class="pt-map" aria-label="Route from Newark to Accra"><svg viewBox="0 0 800 220" preserveAspectRatio="xMidYMid slice"><defs><pattern id="dots" width="14" height="14" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1.3" fill="#CFCBC2"/></pattern></defs><rect width="800" height="220" fill="url(#dots)"/>
<path class="rt" d="M110,90 Q400,-30 690,150"/><path class="done" d="M110,90 Q250,30 400,30" stroke-linecap="round"/>
<g><circle cx="110" cy="90" r="7" fill="var(--green)"/><text x="110" y="120" text-anchor="middle" font-size="13" font-family="Figtree" fill="#44433F">Newark · EWR</text></g>
<g><circle cx="690" cy="150" r="7" fill="#fff" stroke="#A9A59C" stroke-width="2"/><text x="690" y="180" text-anchor="middle" font-size="13" font-family="Figtree" fill="#44433F">Accra · ACC</text></g>
<g><circle r="16" fill="var(--green)" opacity=".18"><animate attributeName="r" values="12;22;12" dur="2s" repeatCount="indefinite"/></circle><circle r="15" fill="var(--green)"/><path d="M11,0 C11,-1.3 9.7,-1.7 8.4,-1.7 L3.2,-1.7 L-2.6,-9.4 L-5.2,-9.4 L-2,-1.7 L-6.6,-1.7 L-8.8,-4.4 L-10.6,-4.4 L-9.3,0 L-10.6,4.4 L-8.8,4.4 L-6.6,1.7 L-2,1.7 L-5.2,9.4 L-2.6,9.4 L3.2,1.7 L8.4,1.7 C9.7,1.7 11,1.3 11,0Z" fill="#fff" transform="scale(.95)"/><animateMotion dur="9s" repeatCount="indefinite" keyPoints="0.35;0.62;0.35" keyTimes="0;0.5;1" calcMode="linear" rotate="auto" path="M110,90 Q400,-30 690,150"/></g>
</svg><div style="position:absolute;left:16px;top:14px" class="row g8"><span class="sl-pill transit">In the air</span><span class="sm mut">Flight SL 2214 · 3,650 mi to go</span></div></div><div class="col g8"><div class="sl-ticks lg pt-fill" role="img" aria-label="Parcel is 60 percent of the way"><i style="width: 60%"></i></div><div class="pt-steps"><div class=""><span class="pt-ic lg "><i class="ic i-newdoc" aria-hidden="true"></i></span>Registered</div><div class=""><span class="pt-ic lg "><i class="ic i-warehouse" aria-hidden="true"></i></span>US warehouse</div><div class="now"><span class="pt-ic lg "><i class="ic i-flight" aria-hidden="true"></i></span>In transit</div><div class=""><span class="pt-ic lg w"><i class="ic i-pin" aria-hidden="true"></i></span>Accra hub</div><div class=""><span class="pt-ic lg w"><i class="ic i-truck" aria-hidden="true"></i></span>Delivered</div></div></div>
          <hr class="hr">
          <div class="sl-tl">
            <?php foreach ((array) sl_get($d, 'events', []) as $__i => $e): ?><div class="<?= sl_v($e, 'cls') ?> pt-ev" style="<?= sl_v($e, 'sty') ?>"><span class="dot"></span><div><div class="fw5 row g8"><span class="pt-ic <?= sl_v($e, 'w') ?>" style="width:28px;height:28px;box-shadow:none"><i class="ic ic-sm <?= sl_v($e, 'ic') ?>" aria-hidden="true"></i></span><?= sl_v($e, 't') ?></div><div class="mut sm"><?= sl_v($e, 's') ?></div></div></div><?php endforeach; ?>
          </div>
        </section>
        
        <section class="sl-card col g16" aria-label="Package details">
          <div class="row between wrap g8"><div><h2 class="sl-ct">Package details</h2><div class="mut sm">What was entered when your package arrived at our US warehouse</div></div><span class="row g6 sm mut"><i class="ic ic-sm i-check" aria-hidden="true"></i>Checked in Nov 4, 11:02 by Newark staff</span></div>
          <div class="row g16 wrap top">
            <div class="pt-thumb" style="width:150px;height:150px;border-radius:18px;flex:none" aria-label="Photo of the box taken at check-in"><svg viewBox="0 0 120 120" width="110" height="110" aria-hidden="true"><path d="M20 42 L60 24 L100 42 L100 92 L60 108 L20 92Z" fill="#C49A64"/><path d="M20 42 L60 58 L100 42 L60 24Z" fill="#D9B482"/><path d="M60 58 L60 108" stroke="#A47C4A" stroke-width="2"/><rect x="66" y="66" width="26" height="16" rx="2" fill="#fff" transform="skewY(-22) translate(0 30)"/></svg></div>
            <div class="pt-grid grow">
              <div><small>Contents</small><span class="fw5">Apple iPad Air and accessories</span></div>
              <div><small>Store</small><span class="fw5">Apple Store online</span></div>
              <div><small>Carrier to US warehouse</small><span class="fw5 row g6"><span class="sl-badge">UPS</span><span class="mono sm">1Z999AA10123456784</span></span></div>
              <div><small>Weight</small><span class="fw5">5.6 lb <span class="mut sm">· chargeable 6.1 lb</span></span></div>
              <div><small>Dimensions</small><span class="fw5">14 × 11 × 4 in</span></div>
              <div><small>Pieces</small><span class="fw5">1 box</span></div>
              <div><small>Service</small><span class="fw5">Air express</span></div>
              <div><small>Consolidation</small><span class="fw5 mono">CON-0214</span></div>
              <div><small>Air waybill</small><span class="fw5 mono">AWB 176-4421 8890</span></div>
              <div><small>Recipient</small><span class="fw5">E•••• O•••• · SL-3318</span></div>
              <div><small>Collect at</small><span class="fw5">Accra Hub, Spintex Rd</span></div>
              <div><small>Payment</small><span class="row g6"><span class="sl-pill delivered">Paid</span></span></div>
            </div>
          </div>
          <div class="col"><div class="up" style="margin-bottom:4px">Items in the box</div>
            <div class="pt-item" style="animation-delay:.2s"><span class="pt-thumb"><i class="ic i-box" aria-hidden="true"></i></span><span class="col"><span class="fw5">Apple iPad Air 11"</span><span class="mut sm">Electronics · has a battery, packed safely</span></span><span class="mono sm">× 1</span></div>
            <div class="pt-item" style="animation-delay:.35s"><span class="pt-thumb"><i class="ic i-edit" aria-hidden="true"></i></span><span class="col"><span class="fw5">Apple Pencil Pro</span><span class="mut sm">Accessories</span></span><span class="mono sm">× 1</span></div>
            <div class="pt-item" style="animation-delay:.5s"><span class="pt-thumb"><i class="ic i-link" aria-hidden="true"></i></span><span class="col"><span class="fw5">USB-C cable 2 m</span><span class="mut sm">Accessories</span></span><span class="mono sm">× 2</span></div></div>
        </section>
        <div class="sl-banner ok" style="align-items:center"><span class="pt-ic" style="width:30px;height:30px;box-shadow:none"><i class="ic ic-sm i-message" aria-hidden="true"></i></span><span>Want updates on WhatsApp? Log in and turn on alerts for this parcel.</span></div>
      </div>
    </main>
  </div>
</div>
