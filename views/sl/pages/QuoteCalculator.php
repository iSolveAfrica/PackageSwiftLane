<?php
/**
 * QuoteCalculator - from docs/handover/artifact-source/QuoteCalculator.dc.html (scripts/sl_convert.py), bound to cotizar.php.
 * Shell page: no (plain page)
 * Data contract ($d): countries[], cats[], origin, destiny, cat, weight, l, w, h, value, asked, error, result{perUnit,freight,total,local,charge,vol,band,fill,tariff}|null,
 *   chart{path,max,top,labels}, unit, dimUnit, loggedIn, site
 */
if (!isset($d)) { $d = []; }
$r = sl_get($d, 'result');
$num = fn($v) => (float) $v > 0 ? rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.') : '';
?>
<div class="sl-app" style="min-height:100%">
<header class="row between wrap g12" style="padding:18px 32px;border-bottom:1px solid var(--line)"><a class="sl-brand" href="<?= sl_route('PublicTracking') ?>"><span class="sl-logo"><i></i></span><?= sl_v($d, 'site') ?></a><nav class="row g16 sm"><a href="<?= sl_route('PublicTracking') ?>">Track</a><a class="fw5" href="<?= sl_route('QuoteCalculator') ?>">Rates</a><?php if (sl_get($d, 'loggedIn')): ?><a class="sl-btn sm" href="<?= sl_route('Main') ?>">My account</a><?php else: ?><a href="<?= sl_route('Login') ?>">Sign in</a><a class="sl-btn sm" href="<?= sl_route('SignUp') ?>">Get a shipping address</a><?php endif; ?></nav></header>
<main style="max-width:1080px;margin:0 auto;padding:48px 24px;display:flex;flex-direction:column;gap:24px">
<div style="text-align:center"><div class="up">Public rate calculator</div><h1 style="font-size:40px;font-weight:600;letter-spacing:-.025em;margin-top:8px">What will it cost to ship?</h1><p class="mut" style="font-size:16px;margin-top:8px">Pick the route and enter the weight. No account needed.</p></div>
<div class="split" style="align-items:stretch">
<form class="a sl-card col g16" style="padding:24px" method="get" action="<?= sl_route('QuoteCalculator') ?>">
<div class="sl-field"><span class="sl-lab">Service</span><div class="sl-seg boxed" style="align-self:flex-start"><?php $cats = (array) sl_get($d, 'cats', []); ?><label class="<?= (int) sl_get($d, 'cat') === 0 ? 'on' : '' ?>" style="cursor:pointer;padding:0 12px;display:inline-flex;align-items:center"><input type="radio" name="cat" value="0" <?= (int) sl_get($d, 'cat') === 0 ? 'checked' : '' ?> hidden>Any</label><?php foreach (array_slice($cats, 0, 3) as $c): if (!preg_match('/^[\p{L}\p{N} .\-]+$/u', (string) $c->name_item)) continue; ?><label class="<?= (int) sl_get($d, 'cat') === (int) $c->id ? 'on' : '' ?>" style="cursor:pointer;padding:0 12px;display:inline-flex;align-items:center"><input type="radio" name="cat" value="<?= (int) $c->id ?>" <?= (int) sl_get($d, 'cat') === (int) $c->id ? 'checked' : '' ?> hidden><?= h(preg_replace('/\s*Freight$/i', '', (string) $c->name_item)) ?></label><?php endforeach; ?></div></div>
<div class="grid cols2"><label class="sl-field"><span class="sl-lab">Origin country</span><select class="sl-in" name="origin" required><option value="">Select</option><?php foreach ((array) sl_get($d, 'countries', []) as $c): ?><option value="<?= (int) $c->id ?>" <?= (int) sl_get($d, 'origin') === (int) $c->id ? 'selected' : '' ?>><?= h($c->name) ?></option><?php endforeach; ?></select></label><label class="sl-field"><span class="sl-lab">Destination country</span><select class="sl-in" name="destiny" required><option value="">Select</option><?php foreach ((array) sl_get($d, 'countries', []) as $c): ?><option value="<?= (int) $c->id ?>" <?= (int) sl_get($d, 'destiny') === (int) $c->id ? 'selected' : '' ?>><?= h($c->name) ?></option><?php endforeach; ?></select></label></div>
<div class="grid cols3"><div class="sl-field"><label class="sl-lab" for="qw">Weight (<?= sl_v($d, 'unit') ?>)</label><input id="qw" name="weight" class="sl-in" type="number" min="0.01" step="0.01" required value="<?= h($num(sl_get($d, 'weight'))) ?>"></div><div class="sl-field"><span class="sl-lab">Size L × W × H (<?= sl_v($d, 'dimUnit') ?>)</span><div class="row g6"><input name="l" class="sl-in" type="number" min="0" step="0.1" aria-label="Length" value="<?= h($num(sl_get($d, 'l'))) ?>"><input name="w" class="sl-in" type="number" min="0" step="0.1" aria-label="Width" value="<?= h($num(sl_get($d, 'w'))) ?>"><input name="h" class="sl-in" type="number" min="0" step="0.1" aria-label="Height" value="<?= h($num(sl_get($d, 'h'))) ?>"></div></div><div class="sl-field"><label class="sl-lab" for="qv">Declared value</label><input id="qv" name="value" class="sl-in" type="number" min="0" step="0.01" value="<?= h($num(sl_get($d, 'value'))) ?>"></div></div>
<button class="sl-btn" style="height:48px" type="submit">Calculate</button>
<?php if (sl_get($d, 'error')): ?><div class="sl-banner warn" role="alert"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span><?= sl_v($d, 'error') ?></span></div><?php endif; ?>
</form>
<section class="b sl-card col g12" style="padding:24px" aria-live="polite">
<?php if ($r): ?>
<div class="up">Your estimate</div><div style="font-size:44px;font-weight:600;letter-spacing:-.02em"><?= h($r['total']) ?></div><?php if ($r['local']): ?><div class="mut sm">≈ <?= h($r['local']) ?></div><?php endif; ?>
<div class="sl-ticks" role="img" aria-label="Chargeable weight <?= h($r['charge']) ?> in band <?= h($r['band']) ?>"><i style="width:<?= (int) $r['fill'] ?>%"></i></div><div class="row between sm mut"><span>Chargeable weight <?= h($r['charge']) ?> <?= sl_v($d, 'unit') ?></span><span>Volumetric <?= h($r['vol'] ?: '0') ?> <?= sl_v($d, 'unit') ?></span></div>
<div class="ov-row sm"><span class="grow">Freight · <?= h($r['charge']) ?> <?= sl_v($d, 'unit') ?> × <?= h($r['perUnit']) ?></span><span class="mono"><?= h($r['freight']) ?></span></div>
<div class="ov-row sm"><span class="grow">Weight band</span><span class="mono"><?= h($r['band']) ?> <?= sl_v($d, 'unit') ?></span></div>
<div class="ov-row sm"><span class="grow">Customs, duties, insurance</span><span class="mut">Billed on arrival</span></div>
<div class="mut sm">Estimate only. The final price is set when our warehouse weighs the parcel.</div>
<?php else: ?>
<div class="up">Your estimate</div><div style="font-size:44px;font-weight:600;letter-spacing:-.02em;color:var(--faint)">—</div><div class="mut sm">Choose the route and the weight, then press Calculate.</div>
<?php endif; ?>
<a class="sl-btn" href="<?= sl_route(sl_get($d, 'loggedIn') ? 'Main' : 'SignUp') ?>" style="margin-top:auto"><?= sl_get($d, 'loggedIn') ? 'Go to my account' : 'Get a shipping address and ship' ?></a></section></div>
<?php if ($r && sl_get($d, 'chart.path')): $ch = sl_get($d, 'chart'); $mx = (int) $ch['max']; $top = (float) $ch['top']; ?>
<section class="sl-card col g8"><div class="sl-ch" style="margin:0"><h2 class="sl-ct">Price by weight on this route</h2><span class="mut sm">1 to <?= $mx ?> <?= sl_v($d, 'unit') ?></span></div><svg class="ov-chart" viewBox="0 0 1040 200" style="width:100%;height:auto" role="img" aria-label="Price by weight from 1 to <?= $mx ?>">
<?php for ($i = 0; $i <= 4; $i++): $y = 170 - $i * 39.5; ?><line x1="44" y1="<?= $y ?>" x2="1030" y2="<?= $y ?>" stroke="var(--line)" stroke-dasharray="<?= $i ? '3 4' : '0' ?>"></line><text x="36" y="<?= $y + 4 ?>" text-anchor="end"><?= h(sl_money($top * $i / 4)) ?></text><?php endfor; ?>
<path d="<?= h($ch['path']) ?>" fill="none" stroke="#D9792B" stroke-width="2"></path>
<?php foreach ($ch['labels'] as $lb): $x = 44 + ($mx > 1 ? ($lb - 1) / ($mx - 1) : 0) * 986; ?><text x="<?= round($x, 1) ?>" y="192" text-anchor="middle"><?= (int) $lb ?></text><?php endforeach; ?></svg></section>
<?php endif; ?>
</main>
</div>
<script>
document.querySelectorAll('.sl-seg label input[type=radio]').forEach(function (r) { r.addEventListener('change', function () { r.closest('.sl-seg').querySelectorAll('label').forEach(function (l) { l.classList.toggle('on', l.contains(r)); }); }); });
</script>
