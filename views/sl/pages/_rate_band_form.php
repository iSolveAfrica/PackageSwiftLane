<?php
/** The route price form, shared by the web page and the phone screen. $e = the band, $d = sl_bandsData(), $phone = bool. */
$f = fn($v) => $v === '' || $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
$inCls = $phone ? 'mx-in' : 'sl-in';
$lab = fn(string $t, string $inner) => $phone ? '<label class="mx-f"><small>' . h($t) . '</small>' . $inner . '</label>' : '<label class="sl-field"><span class="sl-lab">' . h($t) . '</span>' . $inner . '</label>';
$sel = function (string $name, array $opts, $cur, string $empty) use ($inCls) {
    $o = '<select class="' . $inCls . '" name="' . h($name) . '" required><option value="">' . h($empty) . '</option>';
    foreach ($opts as [$v, $l]) $o .= '<option value="' . (int) $v . '"' . ((int) $cur === (int) $v ? ' selected' : '') . '>' . h($l) . '</option>';
    return $o . '</select>';
};
$countries = array_map(fn($c) => [$c->id, $c->name . ((int) $c->is_active ? '' : ' (not offered)')], $d['countries']);
$cats = array_merge([[0, 'Any category']], array_map(fn($c) => [$c->id, trim(html_entity_decode((string) $c->name_item, ENT_QUOTES))], $d['cats']));
$catSel = str_replace(' required', '', $sel('category', $cats, (int) ($e->order_item_category ?? 0), 'Any category'));
?>
<form <?= $phone ? 'class="mx-card mx-form"' : 'class="sl-card col g16"' ?> data-sl-form action="ajax/sl/rate_bands_ajax.php" method="post">
<input type="hidden" name="op" value="save"><input type="hidden" name="id" value="<?= (int) $e->id ?>">
<?= $lab('From (country)', $sel('origin', $countries, $e->origin, 'Where parcels leave from')) ?>
<?= $lab('To (country)', $sel('destiny', $countries, $e->destiny, 'Where parcels go to')) ?>
<?= $lab('Category', $catSel) ?>
<div class="<?= $phone ? 'mx-two' : 'grid cols2' ?>">
<?= $lab('Lowest weight (' . $d['unit'] . ')', '<input class="' . $inCls . '" name="from" type="number" inputmode="decimal" step="0.01" min="0" required value="' . h($f($e->initial_range)) . '">') ?>
<?= $lab('Highest weight (' . $d['unit'] . ')', '<input class="' . $inCls . '" name="to" type="number" inputmode="decimal" step="0.01" min="0" required value="' . h($f($e->final_range)) . '">') ?>
</div>
<div class="<?= $phone ? 'mx-two' : 'grid cols2' ?>">
<?= $lab('Price per ' . $d['unit'], '<input class="' . $inCls . '" name="price" type="number" inputmode="decimal" step="0.01" min="0.01" required value="' . h($f($e->price)) . '">') ?>
<?= $lab('Minimum charge (optional)', '<input class="' . $inCls . '" name="min_charge" type="number" inputmode="decimal" step="0.01" min="0" value="' . h($f($e->min_charge ?? '')) . '">') ?>
</div>
<?= $lab('Volumetric divisor (L × W × H ÷ this)', '<input class="' . $inCls . '" name="divisor" type="number" inputmode="numeric" min="100" max="10000" required value="' . (int) $e->volumetric_percentage . '">') ?>
<div class="<?= $phone ? 'mx-two' : 'grid cols2' ?>">
<?= $lab('Valid from (optional)', '<input class="' . $inCls . '" name="valid_from" type="date" value="' . h((string) ($e->valid_from ?? '')) . '">') ?>
<?= $lab('Valid until (optional)', '<input class="' . $inCls . '" name="valid_until" type="date" value="' . h((string) ($e->valid_until ?? '')) . '">') ?>
</div>
<div class="<?= $phone ? 'mx-btns' : 'row g8 wrap' ?>">
<button type="submit" class="<?= $phone ? 'mx-btn' : 'sl-btn' ?>"><?= $e->id ? 'Save route price' : 'Add route price' ?></button>
<?php if ($e->id): ?><button type="button" class="<?= $phone ? 'mx-btn sec' : 'sl-btn ghost' ?>" data-sl-action="ajax/sl/rate_bands_ajax.php" data-p-op="delete" data-p-id="<?= (int) $e->id ?>" data-sl-confirm="Delete this route price? The calculator stops quoting this weight range.">Delete</button><?php endif; ?>
<?php if (!$phone): ?><a class="sl-btn ghost" href="rate_bands.php">Cancel</a><?php endif; ?>
</div>
</form>
