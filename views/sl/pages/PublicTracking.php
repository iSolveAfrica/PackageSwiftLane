<?php
/**
 * PublicTracking - from docs/handover/artifact-source/PublicTracking.dc.html (scripts/sl_convert.py), bound to track.php.
 * Shell page: no (plain page). Public: recipient names are masked, no addresses or phones are shown.
 * Data contract ($d): code, searched, found, throttled, number, st, cls, air, mode, from, to, eta, steps[], pct, events[],
 *   registered, contents, store, carrierNo, weight, dims, pieces, consolidation, recipient, pay, payCls, items[], flight, loggedIn, site, rate
 */
if (!isset($d)) { $d = []; }
$found = (bool) sl_get($d, 'found');
?>
<div class="sl-app">
  <header class="sl-top" style="height: 64px; padding: 0 24px">
    <a class="sl-brand" href="<?= sl_route(sl_get($d, 'loggedIn') ? 'Main' : 'Login') ?>"><span class="sl-logo"><i></i></span><?= sl_v($d, 'site') ?></a>
    <?php if ((float) sl_get($d, 'rate') > 0): ?><span class="fx-chip" title="Exchange rate used for local prices"><i class="ic ic-sm i-dollar" aria-hidden="true"></i><span class="mono">USD 1 = GHS <?= h(number_format((float) sl_get($d, 'rate'), 2)) ?></span></span><?php endif; ?>
    <span class="grow"></span>
    <nav class="row g8 wrap"><a class="sl-btn ghost sm" href="<?= sl_route('QuoteCalculator') ?>">Get a quote</a><?php if (sl_get($d, 'loggedIn')): ?><a class="sl-btn sm" href="<?= sl_route('Main') ?>">My account</a><?php else: ?><a class="sl-btn ghost sm" href="<?= sl_route('SignUp') ?>">Create account</a><a class="sl-btn sm" href="<?= sl_route('Login') ?>">Log in</a><?php endif; ?></nav>
  </header>
  <div class="sl-body" style="padding-top: 0">
    <main class="sl-main" style="padding: 56px 24px 64px">
      <div style="max-width: 860px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px">
        <div class="col g8" style="align-items: center; text-align: center">
          <span class="sl-badge">Public tracking</span>
          <h1 style="font-size: 38px; font-weight: 500; letter-spacing: -.02em; line-height: 1.1">Where is my parcel?</h1>
          <p class="mut" style="max-width: 480px">Enter your <?= sl_v($d, 'site') ?> tracking number. No account needed.</p>
        </div>
        <form class="row wrap g8" method="get" action="<?= sl_route('PublicTracking') ?>">
          <div class="sl-search" style="max-width: none; height: 52px; flex: 1 1 320px"><i class="ic i-search" aria-hidden="true"></i><input name="order_track" aria-label="Tracking number" value="<?= sl_v($d, 'code') ?>" placeholder="e.g. SL-100482" class="mono" required pattern="[A-Za-z0-9-]+"></div>
          <button class="sl-btn" style="height: 52px; padding: 0 28px" type="submit">Track parcel</button>
        </form>

        <?php if (sl_get($d, 'throttled')): ?>
        <div class="sl-banner warn" role="alert"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>Too many searches in a short time. Please wait a minute and try again.</span></div>
        <?php elseif (sl_get($d, 'searched') && !$found): ?>
        <section class="sl-card sl-empty col g8" style="align-items:center;text-align:center;padding:40px 24px"><span class="sl-tile"><i class="ic i-search" aria-hidden="true"></i></span><h2 class="sl-ct">No parcel found for <span class="mono"><?= sl_v($d, 'code') ?></span></h2><p class="mut sm" style="max-width:420px">Check the number for typos. A parcel appears here once our warehouse has registered it.</p></section>
        <?php elseif ($found): ?>
        <section class="sl-card col g20" aria-label="Tracking result">
          <div class="row between wrap g12">
            <div><div class="up">Tracking number</div><div class="mono fw5" style="font-size: 20px; margin-top: 4px"><?= sl_v($d, 'number') ?></div></div>
            <span class="sl-pill <?= sl_v($d, 'cls') ?>"><?= sl_v($d, 'st') ?></span>
          </div>
          <div class="grid cols3">
            <div class="pt-kv"><span class="pt-ic"><i class="ic ic-sm i-pin" aria-hidden="true"></i></span><div><div class="up">From</div><div class="fw5"><?= sl_v($d, 'from') ?></div></div></div>
            <div class="pt-kv"><span class="pt-ic"><i class="ic ic-sm i-pin" aria-hidden="true"></i></span><div><div class="up">To</div><div class="fw5"><?= sl_v($d, 'to') ?></div></div></div>
            <div class="pt-kv"><span class="pt-ic"><i class="ic ic-sm i-clock" aria-hidden="true"></i></span><div><div class="up">Estimated delivery</div><div class="fw5"><?= sl_v($d, 'eta') ?></div></div></div>
          </div>
          <?php $pct = (int) sl_get($d, 'pct', 0); $t = max(0.05, min(0.95, $pct / 100)); $bx = (1 - $t) * (1 - $t) * 110 + 2 * (1 - $t) * $t * 400 + $t * $t * 690; $by = (1 - $t) * (1 - $t) * 90 + 2 * (1 - $t) * $t * -30 + $t * $t * 150; ?>
          <div class="pt-map" aria-label="Route from <?= sl_v($d, 'from') ?> to <?= sl_v($d, 'to') ?>"><svg viewBox="0 0 800 220" preserveAspectRatio="xMidYMid slice"><defs><pattern id="dots" width="14" height="14" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1.3" fill="#CFCBC2"/></pattern></defs><rect width="800" height="220" fill="url(#dots)"/>
<path class="rt" d="M110,90 Q400,-30 690,150"/>
<g><circle cx="110" cy="90" r="7" fill="var(--green)"/><text x="110" y="120" text-anchor="middle" font-size="13" font-family="Figtree" fill="#44433F"><?= sl_v($d, 'from') ?></text></g>
<g><circle cx="690" cy="150" r="7" fill="<?= $pct >= 100 ? 'var(--green)' : '#fff' ?>" stroke="#A9A59C" stroke-width="2"/><text x="690" y="180" text-anchor="middle" font-size="13" font-family="Figtree" fill="#44433F"><?= sl_v($d, 'to') ?></text></g>
<g transform="translate(<?= round($bx, 1) ?>,<?= round($by, 1) ?>)"><circle r="16" fill="var(--green)" opacity=".18"><animate attributeName="r" values="12;22;12" dur="2s" repeatCount="indefinite"/></circle><circle r="15" fill="var(--green)"/><?php if (sl_get($d, 'air')): ?><path d="M11,0 C11,-1.3 9.7,-1.7 8.4,-1.7 L3.2,-1.7 L-2.6,-9.4 L-5.2,-9.4 L-2,-1.7 L-6.6,-1.7 L-8.8,-4.4 L-10.6,-4.4 L-9.3,0 L-10.6,4.4 L-8.8,4.4 L-6.6,1.7 L-2,1.7 L-5.2,9.4 L-2.6,9.4 L3.2,1.7 L8.4,1.7 C9.7,1.7 11,1.3 11,0Z" fill="#fff" transform="scale(.95)"/><?php else: ?><rect x="-7" y="-6" width="14" height="12" rx="2" fill="#fff"/><?php endif; ?></g>
</svg><div style="position:absolute;left:16px;top:14px" class="row g8"><span class="sl-pill <?= sl_v($d, 'cls') ?>"><?= sl_v($d, 'mode') ?></span><?php if (sl_get($d, 'flight')): ?><span class="sm mut">Flight <?= sl_v($d, 'flight') ?></span><?php endif; ?></div></div>
          <div class="col g8"><div class="sl-ticks lg pt-fill" role="img" aria-label="Parcel is <?= $pct ?> percent of the way"><i style="width: <?= $pct ?>%"></i></div><div class="pt-steps"><?php foreach ((array) sl_get($d, 'steps', []) as $s): ?><div class="<?= h($s['cls']) ?>"><span class="pt-ic lg <?= h($s['w']) ?>"><i class="ic <?= h($s['i']) ?>" aria-hidden="true"></i></span><?= h($s['l']) ?></div><?php endforeach; ?></div></div>
          <hr class="hr">
          <div class="sl-tl">
            <?php foreach ((array) sl_get($d, 'events', []) as $e): ?><div class="<?= sl_v($e, 'cls') ?> pt-ev" style="<?= sl_v($e, 'sty') ?>"><span class="dot"></span><div><div class="fw5 row g8"><span class="pt-ic <?= sl_v($e, 'w') ?>" style="width:28px;height:28px;box-shadow:none"><i class="ic ic-sm <?= sl_v($e, 'ic') ?>" aria-hidden="true"></i></span><?= sl_v($e, 't') ?></div><div class="mut sm"><?= sl_v($e, 's') ?></div></div></div><?php endforeach; ?>
            <?php if (!sl_get($d, 'events')): ?><div class="sl-empty mut sm">No tracking events yet. Registered on <?= sl_v($d, 'registered') ?>.</div><?php endif; ?>
          </div>
        </section>

        <section class="sl-card col g16" aria-label="Package details">
          <div class="row between wrap g8"><div><h2 class="sl-ct">Package details</h2><div class="mut sm">What was entered when your package was registered</div></div><span class="row g6 sm mut"><i class="ic ic-sm i-check" aria-hidden="true"></i>Registered <?= sl_v($d, 'registered') ?></span></div>
          <div class="pt-grid grow">
            <div><small>Contents</small><span class="fw5"><?= sl_v($d, 'contents') ?></span></div>
            <div><small>Store</small><span class="fw5"><?= sl_v($d, 'store') ?></span></div>
            <div><small>Carrier tracking</small><span class="fw5 mono sm"><?= sl_get($d, 'carrierNo') ? sl_v($d, 'carrierNo') : '—' ?></span></div>
            <div><small>Weight</small><span class="fw5"><?= sl_v($d, 'weight') ?></span></div>
            <div><small>Dimensions</small><span class="fw5"><?= sl_v($d, 'dims') ?></span></div>
            <div><small>Pieces</small><span class="fw5"><?= sl_v($d, 'pieces') ?></span></div>
            <div><small>Service</small><span class="fw5"><?= sl_v($d, 'mode') ?></span></div>
            <div><small>Consolidation</small><span class="fw5 mono"><?= sl_v($d, 'consolidation') ?></span></div>
            <div><small>Recipient</small><span class="fw5"><?= sl_v($d, 'recipient') ?></span></div>
            <div><small>Payment</small><span class="row g6"><span class="sl-pill <?= sl_v($d, 'payCls') ?>"><?= sl_v($d, 'pay') ?></span></span></div>
          </div>
          <?php if (sl_get($d, 'items')): ?>
          <div class="col"><div class="up" style="margin-bottom:4px">Items in the box</div>
            <?php foreach ((array) sl_get($d, 'items', []) as $i => $it): ?><div class="pt-item" style="animation-delay:<?= number_format(.2 + .15 * $i, 2) ?>s"><span class="pt-thumb"><i class="ic i-box" aria-hidden="true"></i></span><span class="col"><span class="fw5"><?= h($it['n']) ?></span><span class="mut sm"><?= h($it['w']) ?></span></span><span class="mono sm">× <?= (int) $it['q'] ?></span></div><?php endforeach; ?></div>
          <?php endif; ?>
        </section>
        <div class="sl-banner ok" style="align-items:center"><span class="pt-ic" style="width:30px;height:30px;box-shadow:none"><i class="ic ic-sm i-message" aria-hidden="true"></i></span><span>Want updates on WhatsApp? <a class="fw5" href="<?= sl_route(sl_get($d, 'loggedIn') ? 'CustomerNotifications' : 'Login') ?>" style="text-decoration:underline"><?= sl_get($d, 'loggedIn') ? 'Choose your alerts' : 'Log in' ?></a> and turn on alerts for your parcels.</span></div>
        <?php endif; ?>
      </div>
    </main>
  </div>
</div>
