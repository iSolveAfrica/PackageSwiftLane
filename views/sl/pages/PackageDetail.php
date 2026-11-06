<?php
/**
 * PackageDetail - generated from docs/handover/artifact-source/PackageDetail.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  events                       as $e
 *   value events
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
        <div class="sl-head">
          <div><a href="<?= sl_route('SeaPackages') ?>" class="row g6 mut sm"><i class="ic ic-sm i-left" aria-hidden="true"></i>Sea Packages</a>
            <div class="row g12 wrap" style="margin-top: 4px"><h1 class="sl-title mono">PK-20931</h1><span class="sl-pill sorting">At sea</span><span class="sl-badge sea">Sea</span></div></div>
          <div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('Label4x6') ?>"><i class="ic ic-sm i-print" aria-hidden="true"></i>Label</a><a class="sl-btn ghost" href="<?= sl_route('ReceiptNotPaid') ?>"><i class="ic ic-sm i-receipt" aria-hidden="true"></i>Invoice</a><a class="sl-btn" href="<?= sl_route('ShipmentDetail') ?>"><i class="ic ic-sm i-refresh" aria-hidden="true"></i>Update status</a></div>
        </div>
        <section class="sl-card col g16">
          <div class="row between wrap g12"><div><div class="up">Estimated arrival, Takoradi</div><div class="big" style="margin-top: 4px">Dec 21, 2026</div></div><div class="col g4 tr"><span class="mut">Container SLC-4471 · MV Atlantic Crest</span><span class="mono sm mut">Departed Newark Nov 1</span></div></div>
          <div class="sl-steps" aria-label="Step 3 of 5"><i class="on"></i><i class="on"></i><i class="on"></i><i></i><i></i></div>
          <div class="row between wrap g8 mut sm"><span>At US warehouse</span><span>Loaded</span><span class="fw5" style="color: var(--ink)">At sea</span><span>Customs</span><span>Delivered</span></div>
        </section>
        <div class="split">
          <div class="a col g16">
            <section class="sl-card flush">
              <div class="row between" style="padding: 16px 20px"><h2 class="sl-ct">Boxes</h2><span class="mut sm">3 boxes · 9.5 ft³</span></div>
              <div class="sl-tw"><table class="sl-table"><thead><tr><th>#</th><th>Contents</th><th class="num">Dimensions</th><th class="num">Weight</th><th class="num">Volume</th></tr></thead><tbody>
                <tr><td>1</td><td>Kitchen appliances</td><td class="num mono">24 × 18 × 16 in</td><td class="num">32 lb</td><td class="num">4.0 ft³</td></tr>
                <tr><td>2</td><td>Clothing</td><td class="num mono">20 × 20 × 18 in</td><td class="num">18 lb</td><td class="num">4.2 ft³</td></tr>
                <tr><td>3</td><td>Shoes</td><td class="num mono">18 × 12 × 10 in</td><td class="num">9 lb</td><td class="num">1.3 ft³</td></tr>
              </tbody></table></div>
            </section>
            <div class="grid cols2">
              <section class="sl-card col g12"><h2 class="sl-ct">Customer</h2><div class="row g12"><a class="me-pic eo" href="<?= sl_route('AiStudio') ?>" aria-label="Your profile, Efua Owusu" style="width:34px;height:34px;margin-top:auto"></a><div><div class="fw5">Efua Owusu</div><div class="mut sm">SL-3318 · +233 20 111 2233</div></div></div><div class="sl-kv"><div><span>Deliver to</span><span>6 Harbour Rd, Takoradi</span></div><div><span>Supplier</span><span>Walmart</span></div></div></section>
              <section class="sl-card col g12"><h2 class="sl-ct">Payment</h2><div class="row between"><span class="big">$151.00</span><span class="sl-pill paid">Paid</span></div><div class="sl-kv"><div><span>Method</span><span>Stripe</span></div><div><span>Paid on</span><span>Nov 1, 2026</span></div></div></section>
            </div>
          </div>
          <aside class="b col g16">
            <section class="sl-card"><div class="sl-ch"><h2 class="sl-ct">Tracking</h2></div>
              <div class="sl-tl"><?php foreach ((array) sl_get($d, 'events', []) as $__i => $e): ?><div class="<?= sl_v($e, 'cls') ?>"><span class="dot"></span><div><div class="fw5"><?= sl_v($e, 't') ?></div><div class="mut sm"><?= sl_v($e, 's') ?></div></div></div><?php endforeach; ?></div>
            </section>
            <section class="sl-card col g10"><div class="row between"><span class="sl-ct">Container SLC-4471</span><span class="sl-badge sea">At sea</span></div><div class="sl-mv" style="margin: 0">1,960 / 2,000 <i>ft³</i></div><div class="sl-ticks r"><i style="width: 98%"></i></div><a href="<?= sl_route('ConsolidationDetail') ?>" class="sm fw5 row g6">View container <i class="ic ic-sm i-right" aria-hidden="true"></i></a></section>
          </aside>
        </div>
      
