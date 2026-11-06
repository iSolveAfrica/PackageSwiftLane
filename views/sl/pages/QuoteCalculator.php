<?php
/**
 * QuoteCalculator - generated from docs/handover/artifact-source/QuoteCalculator.dc.html by scripts/sl_convert.py.
 * Shell page: no (plain page)
 * Data contract ($d):
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="sl-app" style="min-height:100%">
<header class="row between wrap g12" style="padding:18px 32px;border-bottom:1px solid var(--line)"><a class="sl-brand" href="<?= sl_route('PublicTracking') ?>"><span class="sl-logo"><i></i></span>Swift Lane</a><nav class="row g16 sm"><a href="<?= sl_route('PublicTracking') ?>">Track</a><a class="fw5" href="<?= sl_route('QuoteCalculator') ?>">Rates</a><a href="<?= sl_route('Login') ?>">Sign in</a><a class="sl-btn sm" href="<?= sl_route('SignUp') ?>">Get a US address</a></nav></header>
<main style="max-width:1080px;margin:0 auto;padding:48px 24px;display:flex;flex-direction:column;gap:24px">
<div style="text-align:center"><div class="up">Public rate calculator</div><h1 style="font-size:40px;font-weight:600;letter-spacing:-.025em;margin-top:8px">What will it cost to ship?</h1><p class="mut" style="font-size:16px;margin-top:8px">Air or sea, from the US to Ghana. No account needed.</p></div>
<div class="split" style="align-items:stretch">
<section class="a sl-card col g16" style="padding:24px">
<div class="sl-seg boxed" style="align-self:flex-start"><button class="on">Air</button><button>Sea</button></div>
<div class="grid cols2"><div class="sl-field"><span class="sl-lab">Origin country</span><div class="sl-sel"><span>United States</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div></div><div class="sl-field"><span class="sl-lab">Destination country</span><div class="sl-sel"><span>Ghana · Accra</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div></div></div>
<div class="grid cols3"><div class="sl-field"><label class="sl-lab" for="w">Weight (lb)</label><input id="w" class="sl-in" value="12"></div><div class="sl-field"><label class="sl-lab" for="d">Size L × W × H (in)</label><input id="d" class="sl-in" value="16 × 12 × 8"></div><div class="sl-field"><label class="sl-lab" for="v">Declared value ($)</label><input id="v" class="sl-in" value="450"></div></div>
<div class="sl-field"><span class="sl-lab">What is in it?</span><div class="row g8 wrap"><span class="ov-pill">Electronics</span><span class="ov-pill">Clothing</span><span class="ov-pill">Cosmetics</span><span class="ov-pill" style="border:1px dashed var(--line-2);background:transparent">+ Other</span></div></div>
<button class="sl-btn" style="height:48px">Calculate</button>
</section>
<section class="b sl-card col g12" style="padding:24px"><div class="up">Your estimate · Air express</div><div style="font-size:44px;font-weight:600;letter-spacing:-.02em">$72.00</div><div class="mut sm">≈ ₵892.80 at USD 1 = GHS 12.40</div>
<div class="sl-ticks" role="img" aria-label="Chargeable weight 12 of 20 lb"><i style="width:60%"></i></div><div class="row between sm mut"><span>Chargeable weight 12 lb</span><span>Volumetric 9.2 lb</span></div>
<div class="ov-row sm"><span class="grow">Freight · 12 lb × $6.00 <span class="ov-pill warn">Promo</span></span><span class="mono">$72.00</span></div>
<div class="ov-row sm"><span class="grow">Handling</span><span class="mono">$0.00</span></div>
<div class="ov-row sm"><span class="grow">Customs and duties</span><span class="mut">Billed on arrival</span></div>
<div class="ov-row sm"><span class="grow fw5">Door to door</span><span class="fw5">5–7 days</span></div>
<a class="sl-btn" href="<?= sl_route('SignUp') ?>" style="margin-top:auto">Get a US address and ship</a></section></div>
<section class="sl-card col g8"><div class="sl-ch" style="margin:0"><h2 class="sl-ct">Air vs sea for this parcel</h2><span class="mut sm">Price by weight</span></div><svg class="ov-chart" viewBox="0 0 1040 200" style="width:100%;height:auto" role="img" aria-label="Air price by weight from 1 to 12 pounds"><line x1="44" y1="170.0" x2="1030" y2="170.0" stroke="var(--line)" stroke-dasharray="0"></line><text x="36" y="174.0" text-anchor="end">$0</text><line x1="44" y1="130.5" x2="1030" y2="130.5" stroke="var(--line)" stroke-dasharray="3 4"></line><text x="36" y="134.5" text-anchor="end">$20</text><line x1="44" y1="91.0" x2="1030" y2="91.0" stroke="var(--line)" stroke-dasharray="3 4"></line><text x="36" y="95.0" text-anchor="end">$40</text><line x1="44" y1="51.5" x2="1030" y2="51.5" stroke="var(--line)" stroke-dasharray="3 4"></line><text x="36" y="55.5" text-anchor="end">$60</text><line x1="44" y1="12.0" x2="1030" y2="12.0" stroke="var(--line)" stroke-dasharray="3 4"></line><text x="36" y="16.0" text-anchor="end">$80</text><path d="M44.0,158.2 C58.9,156.2 103.8,150.2 133.6,146.3 C163.5,142.3 193.4,138.4 223.3,134.4 C253.2,130.5 283.0,126.5 312.9,122.6 C342.8,118.6 372.7,114.7 402.5,110.8 C432.4,106.8 462.3,102.9 492.2,98.9 C522.1,95.0 551.9,91.0 581.8,87.0 C611.7,83.1 641.6,79.2 671.5,75.2 C701.3,71.2 731.2,67.3 761.1,63.3 C791.0,59.4 820.8,55.4 850.7,51.5 C880.6,47.6 910.5,43.6 940.4,39.7 C970.2,35.7 1015.1,29.8 1030.0,27.8" fill="none" stroke="#D9792B" stroke-width="2"></path><circle cx="1030.0" cy="27.8" r="4" fill="var(--card)" stroke="#161615" stroke-width="1.5"></circle><text x="44.0" y="192" text-anchor="middle">1</text><text x="312.9" y="192" text-anchor="middle">4</text><text x="581.8" y="192" text-anchor="middle">7</text><text x="850.7" y="192" text-anchor="middle">10</text><text x="1030.0" y="192" text-anchor="middle">12 lb</text></svg></section>
</main>
</div>
