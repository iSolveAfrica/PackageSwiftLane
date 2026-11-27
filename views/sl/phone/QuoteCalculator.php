<?php
/**
 * M-CQuote (cotizar.php, view QuoteCalculator): the public rate calculator. GET to the same page with the
 * web form's fields (cat, origin, destiny, weight, l, w, h, value); result and price-by-weight bars from
 * sl_quoteData() (public tariffs only).
 */
$slPhone['nav'] = 'none';
$in = !empty($d['loggedIn']);
$num = fn($v) => (float) $v > 0 ? rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.') : '';
$cats = [];
foreach (array_slice((array) ($d['cats'] ?? []), 0, 3) as $c) {
    if (preg_match('/^[\p{L}\p{N} .\-]+$/u', (string) $c->name_item)) {
        $cats[(int) $c->id] = preg_replace('/\s*Freight$/i', '', (string) $c->name_item);
    }
}
$cat = (int) ($d['cat'] ?? 0);
$svc = $cats[$cat] ?? 'Any service';
$r = $d['result'] ?? null;
$unit = (string) ($d['unit'] ?? 'kg');
$customer = $in && function_exists('sl_userIs') && sl_userIs('client');
// Price by weight: up to 8 bars from the chart, always including the asked weight.
$bars = [];
if ($r && !empty($d['chart']['values'])) {
    $vals = $d['chart']['values'];
    $n = count($vals);
    $cur = max(1, min($n, (int) ceil((float) $d['weight'])));
    $pick = $n <= 8 ? range(1, $n) : array_values(array_unique(array_map(fn($k) => (int) round(1 + ($n - 1) * $k / 7), range(0, 7))));
    if (!in_array($cur, $pick, true)) {
        // swap the sampled weight nearest to the asked one for the asked one
        $near = 0;
        foreach ($pick as $i => $x) { if (abs($x - $cur) < abs($pick[$near] - $cur)) $near = $i; }
        $pick[$near] = $cur;
        sort($pick);
    }
    $top = max(1, (float) max($vals));
    foreach ($pick as $w) {
        $bars[] = ['w' => $w, 'v' => (float) $vals[$w - 1], 'pct' => round((float) $vals[$w - 1] / $top * 100, 1), 'cur' => $w === $cur];
    }
}
$charge = $r ? (float) str_replace(',', '', $r['charge']) : 0;
$actual = (float) ($d['weight'] ?? 0);
$vol = $r ? (float) str_replace(',', '', $r['vol']) : 0;
$big = max($actual, $vol, 0.01);
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-auth.css')) ?>">
<?= sl_phoneTop('Get a quote', '', ['back' => $in ? sl_route('Main') : sl_route('PublicTracking')]) ?>
<main class="mx-body">
<form method="get" action="<?= h(sl_route('QuoteCalculator')) ?>" id="quote-form">
<input type="hidden" name="cat" id="q-cat" value="<?= $cat ?>">
<div class="mx-f"><small>Service</small><div class="mx-fseg" data-mx-pick="#q-cat" role="group" aria-label="Service"><button type="button" class="<?= $cat === 0 ? 'on' : '' ?>" data-value="0" aria-pressed="<?= $cat === 0 ? 'true' : 'false' ?>">Any</button><?php foreach ($cats as $id => $label): ?><button type="button" class="<?= $cat === $id ? 'on' : '' ?>" data-value="<?= $id ?>" aria-pressed="<?= $cat === $id ? 'true' : 'false' ?>"><?= h($label) ?></button><?php endforeach; ?></div></div>
<div class="mx-sec"><h2>Your parcel</h2></div>
<section class="mx-card mx-form">
<div class="mx-two"><label class="mx-f"><small>Origin country</small><select class="mx-in mx-inp" name="origin" required><option value="">Select</option><?php foreach ((array) ($d['countries'] ?? []) as $c): ?><option value="<?= (int) $c->id ?>"<?= (int) ($d['origin'] ?? 0) === (int) $c->id ? ' selected' : '' ?>><?= h($c->name) ?></option><?php endforeach; ?></select></label>
<label class="mx-f"><small>Destination</small><select class="mx-in mx-inp" name="destiny" required><option value="">Select</option><?php foreach ((array) ($d['countries'] ?? []) as $c): ?><option value="<?= (int) $c->id ?>"<?= (int) ($d['destiny'] ?? 0) === (int) $c->id ? ' selected' : '' ?>><?= h($c->name) ?></option><?php endforeach; ?></select></label></div>
<div class="mx-two"><label class="mx-f"><small>Weight (<?= h($unit) ?>)</small><input class="mx-in mx-inp" name="weight" type="number" inputmode="decimal" min="0.01" step="0.01" required value="<?= h($num($d['weight'] ?? 0)) ?>"></label>
<label class="mx-f"><small>Declared value</small><input class="mx-in mx-inp" name="value" type="number" inputmode="decimal" min="0" step="0.01" value="<?= h($num($d['value'] ?? 0)) ?>"></label></div>
<div class="mx-f"><small>Size L × W × H (<?= h($d['dimUnit'] ?? 'cm') ?>)</small><div class="mx-two" style="grid-template-columns:1fr 1fr 1fr"><input class="mx-in mx-inp" name="l" type="number" inputmode="decimal" min="0" step="0.1" aria-label="Length" value="<?= h($num($d['l'] ?? 0)) ?>"><input class="mx-in mx-inp" name="w" type="number" inputmode="decimal" min="0" step="0.1" aria-label="Width" value="<?= h($num($d['w'] ?? 0)) ?>"><input class="mx-in mx-inp" name="h" type="number" inputmode="decimal" min="0" step="0.1" aria-label="Height" value="<?= h($num($d['h'] ?? 0)) ?>"></div></div>
</section>
<button class="b2-cta" type="submit"><i class="ic ic-sm i-refresh" aria-hidden="true"></i>Calculate</button>
</form>
<?php if (($d['error'] ?? '') !== ''): ?><div class="mx-ban warn" role="alert"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span><?= h($d['error']) ?></span></div><?php endif; ?>
<?php if ($r): ?>
<section class="b2-hd" aria-live="polite">
<div class="b2-hdt"><span class="mx-rm"><small>Estimate · <?= h($svc) ?></small><b><?= h($r['total']) ?></b></span><?php if ($cat > 0): ?><span class="b2-st <?= stripos($svc, 'air') !== false ? 'air' : 'sea' ?>"><?= h($svc) ?></span><?php endif; ?></div>
<?php if ($r['local'] !== ''): ?><div class="b2-rt"><?= h($r['local']) ?></div><?php endif; ?>
<div class="b2-cv">
<div class="b2-cvr"><span>Actual</span><div class="b2-trk"><i class="ink" style="width:<?= round($actual / $big * 100) ?>%"></i></div><b><?= h($num($actual)) ?> <?= h($unit) ?></b></div>
<div class="b2-cvr"><span>Volumetric</span><div class="b2-trk"><i style="width:<?= round($vol / $big * 100) ?>%"></i></div><b><?= $vol > 0 ? h($r['vol']) . ' ' . h($unit) : '—' ?></b></div>
<div class="b2-cvr"><span>Chargeable</span><div class="b2-trk"><i class="ok" style="width:<?= round($charge / $big * 100) ?>%"></i></div><b><?= h($r['charge']) ?> <?= h($unit) ?></b></div>
</div>
</section>
<section class="mx-card b2-ml">
<div><span>Weight band<small><?= h($r['band']) ?> <?= h($unit) ?></small></span><em><?= h($r['perUnit']) ?> / <?= h($unit) ?></em></div>
<div><span>Freight<small><?= h($r['charge']) ?> <?= h($unit) ?> × <?= h($r['perUnit']) ?></small></span><em><?= h($r['freight']) ?></em></div>
<div class="mu"><span>Customs, duties, insurance<small>Billed on arrival</small></span><em>—</em></div>
<div class="tot"><span>Estimate</span><em><?= h($r['total']) ?></em></div>
</section>
<?php if ($bars): $topV = max(array_column($bars, 'v')) ?: 1; ?>
<section class="b2-wk" aria-label="Price by weight">
<div class="b2-wkh"><span><b>Price by weight</b><small><?= h($svc) ?> · this route</small></span></div>
<div class="b2-wkb"><div class="b2-ya" aria-hidden="true"><?php for ($k = 4; $k >= 0; $k--): $v = $topV * $k / 4; ?><span><?= h($v >= 1000 ? round($v / 1000, 1) . 'k' : (string) round($v)) ?></span><?php endfor; ?></div>
<div class="b2-bs"><?php foreach ($bars as $b): ?><span class="<?= $b['cur'] ? 'cur' : '' ?>" title="<?= (int) $b['w'] ?> <?= h($unit) ?>: <?= h(sl_money($b['v'])) ?>"><i style="height:<?= max(2, round($b['v'] / $topV * 85, 1)) ?>%"></i><small><?= (int) $b['w'] ?></small></span><?php endforeach; ?></div></div>
</section>
<?php endif; ?>
<p class="mx-note">This is an estimate. The final price is set when we weigh the parcel at the warehouse.</p>
<?php if ($customer): ?><section class="mx-card mx-list"><a class="mx-row" href="prealert_add.php"><span class="mx-li"><i class="ic i-inbox" aria-hidden="true"></i></span><span class="mx-rm"><b>Pre-alert this parcel</b><small>So we match it fast at the warehouse</small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a></section><?php endif; ?>
<?php endif; ?>
</main>
<?php if ($r): ?>
<div class="mx-foot"><div class="b2-ft"><span><small>Estimate · <?= h($svc) ?></small><b><?= h($r['total']) ?></b></span><?php if ($in): ?><a class="b2-cta" href="<?= h(sl_route('MyAccount')) ?>"><i class="ic ic-sm i-pin" aria-hidden="true"></i>My shipping address</a><?php else: ?><a class="b2-cta" href="<?= h(sl_route('SignUp')) ?>"><i class="ic ic-sm i-pin" aria-hidden="true"></i>Get a shipping address</a><?php endif; ?></div></div>
<?php endif; ?>
