<?php
/**
 * LabelStudio - from docs/handover/artifact-source/LabelStudio.dc.html.
 * Data: views/sl/data/labels.php (sl_labelStudioData). Options are stored in
 * cdb_sl_settings (label_options, label_small_options) by ajax/sl/label_options_ajax.php
 * and used by print_label_ship.php.
 */
if (!isset($d)) { $d = []; }
$o = $d['o'];
$s = $d['s'];
$l = $d['l'];
$canEdit = $d['canEdit'];
$opt = function (string $k, string $label, string $sub) use ($o, $canEdit) {
    $on = !empty($o[$k]);
    return '<button type="button" class="ls-chk" role="checkbox" aria-checked="' . ($on ? 'true' : 'false') . '"' . ($canEdit ? ' data-ls-set="big" data-k="' . h($k) . '" data-v="' . ($on ? 0 : 1) . '"' : ' disabled') . '><span class="ls-box ' . ($on ? 'on' : '') . '"><i class="ic ic-sm i-check" aria-hidden="true"></i></span><span>' . h($label) . '<small>' . h($sub) . '</small></span></button>';
};
$pill = function (string $group, string $k, string $v, string $label, $cur) use ($canEdit) {
    return '<button type="button" class="' . ((string) $cur === $v ? 'on' : '') . '"' . ($canEdit ? ' data-ls-set="' . h($group) . '" data-k="' . h($k) . '" data-v="' . h($v) . '"' : ' disabled') . '>' . h($label) . '</button>';
};
$printUrl = $l ? 'print_label_ship.php?id=' . (int) $l['id'] : '';
?>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:20px">Label Studio</h1><p class="sl-lede"><?= $l ? h($l['track'] . ' · ' . ucwords(strtolower($l['name']))) . ' · ' : '' ?>choose what every label shows, preview it live, print</p></div><div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('PackageIntake') ?>"><i class="ic ic-sm i-left" aria-hidden="true"></i>Back to intake</a><?php if ($l): ?><a class="sl-btn ghost" href="<?= h($printUrl . '&size=12') ?>" target="_blank"><i class="ic ic-sm i-tag" aria-hidden="true"></i>Small 1 × 2 label</a><a class="sl-btn" href="<?= h($printUrl . '&size=46') ?>" target="_blank"><i class="ic ic-sm i-print" aria-hidden="true"></i>Print <?= (int) $l['pieces'] * (int) $o['copies'] ?></a><?php endif; ?></div></div>
<form method="get" class="row g8 wrap sm"><span class="mut">Preview with shipment:</span><span class="sl-search" style="max-width:260px"><i class="ic ic-sm i-search" aria-hidden="true"></i><input name="q" value="<?= h($d['q']) ?>" placeholder="Tracking number" aria-label="Shipment for the preview"></span><button class="sl-btn sm ghost" type="submit">Load</button><?php foreach ($d['recent'] as $r): ?><a class="sl-btn sm <?= $l && $r['id'] === $l['id'] ? '' : 'ghost' ?>" href="<?= h(sl_url(['id' => $r['id'], 'q' => null])) ?>"><?= h($r['track']) ?></a><?php endforeach; ?></form>
<?php if (!$canEdit): ?><div class="sl-banner warn">You can preview labels. Changing the label options needs the Settings permission.</div><?php endif; ?>
<div class="lsx">
<section class="ls-opt" aria-label="Label options"><div class="ls-oh"><b>Label Options</b><span class="sm" style="opacity:.8"><?= (int) $d['onCount'] ?> add-ons</span></div><div class="ls-ob">
<div class="ls-sec"><span>Default label size</span><div class="ls-pills"><?= $pill('big', 'size', '46', '4 × 6 in', $o['size']) ?><?= $pill('big', 'size', '44', '4 × 4 in', $o['size']) ?><?= $pill('big', 'size', '12', '1 × 2 in', $o['size']) ?></div></div>
<div class="ls-sec"><span>Preview</span><form method="get" class="ls-pills"><?php if ($l): ?><input type="hidden" name="id" value="<?= (int) $l['id'] ?>"><?php endif; ?><?php foreach (['46' => '4 × 6', '44' => '4 × 4', '12' => '1 × 2'] as $v => $t): ?><button type="submit" name="view" value="<?= $v ?>" class="<?= $d['view'] === (string) $v ? 'on' : '' ?>"><?= $t ?></button><?php endforeach; ?></form></div>
<div class="hr"></div>
<?= $opt('sender', 'Sender block', 'Hub name and address at the top') ?>
<?= $opt('phone', 'Recipient phone', 'Masked, last four digits') ?>
<?= $opt('qr', 'QR locator', 'Links to the public tracking page') ?>
<?= $opt('contents', 'Contents', 'Items from the shipment') ?>
<?= $opt('value', 'Declared value', 'With the contents line') ?>
<?= $opt('fragile', 'Fragile flag', 'Stamp on every label') ?>
<?= $opt('customs', 'CN22 customs box', 'For international parcels') ?>
<?= $opt('code', 'Pickup code note', 'Tells the customer the code comes by WhatsApp') ?>
<?= $opt('postage', 'Postage paid', 'Shows the shipment total') ?>
<?= $opt('care', 'This side up', 'Handle with care footer') ?>
<?= $opt('ret', 'Return label', 'Adds a prepaid return label') ?>
<div class="hr"></div>
<div class="row between"><div><div class="fw5 sm">Copies of each label</div><div class="mut" style="font-size:12px">One label per piece, numbered</div></div><div class="ls-step"><button type="button" aria-label="Fewer copies" <?= $canEdit ? 'data-ls-set="big" data-k="copies" data-v="' . max(1, (int) $o['copies'] - 1) . '"' : 'disabled' ?>>−</button><b><?= (int) $o['copies'] ?></b><button type="button" aria-label="More copies" <?= $canEdit ? 'data-ls-set="big" data-k="copies" data-v="' . min(10, (int) $o['copies'] + 1) . '"' : 'disabled' ?>>+</button></div></div>
<label class="sl-field"><span class="sl-lab">Printer name (shown to staff)</span><input class="sl-in" value="<?= h($o['printer']) ?>" maxlength="80" <?= $canEdit ? 'data-ls-text="big" data-k="printer"' : 'disabled' ?>></label>
<div class="hr"></div>
<div class="ls-sec"><span>Small label · big text</span><div class="ls-pills"><?= $pill('small', 'use', 'locker', 'Locker ID', $s['use']) ?><?= $pill('small', 'use', 'track', 'Tracking', $s['use']) ?><?= $pill('small', 'use', 'carrier', 'Barcode = carrier no.', $s['use']) ?></div></div>
<div class="ls-sec"><span>Small label · text size</span><div class="ls-pills"><?php foreach (['14', '17', '20'] as $z): ?><?= $pill('small', 'size', $z, $z . ' px', $s['size']) ?><?php endforeach; ?></div></div>
<div class="row g16 wrap sm"><?php foreach (['tag' => 'Mode tag', 'barcode' => 'Barcode', 'code' => 'Tracking line'] as $k => $t): $on = !empty($s[$k]); ?><label class="row g8" style="cursor:pointer"><button type="button" class="sl-toggle <?= $on ? 'on' : '' ?>" role="switch" data-static aria-checked="<?= $on ? 'true' : 'false' ?>" aria-label="<?= h($t) ?>" <?= $canEdit ? 'data-ls-set="small" data-k="' . h($k) . '" data-v="' . ($on ? 0 : 1) . '"' : 'disabled' ?>></button><?= h($t) ?></label><?php endforeach; ?></div>
</div></section>
<div class="ls-stage">
<div class="ls-canvas">
<?php if (!$l): ?><div class="ix-paper" style="padding:30px;color:#000">No shipment to preview yet. Create one or search above.</div>
<?php elseif ($d['view'] === '12'): ?>
<div class="col g12" style="align-items:center;width:100%"><div class="ix-paper" style="width:576px;height:288px;overflow:hidden"><div style="transform:scale(3);transform-origin:0 0"><div class="lb lb12"><div class="row between" style="align-items:baseline"><b style="font-size:<?= (int) $s['size'] ?>px;letter-spacing:-.01em;line-height:1"><?= h($s['use'] === 'track' ? $l['track'] : ($l['locker'] ?: $l['track'])) ?></b><?php if (!empty($s['tag'])): ?><span style="font-size:8px;font-weight:800;border:1.5px solid #000;border-radius:3px;padding:0 3px"><?= h($l['mode'] . ($l['bay'] !== '' ? ' · B' . $l['bay'] : '')) ?></span><?php endif; ?></div><div style="font-size:8.5px;font-weight:600;white-space:nowrap;overflow:hidden"><?= h($l['name'] . ' · ' . $l['weight'] . ' · 1/' . $l['pieces']) ?></div><?php if (!empty($s['barcode'])): ?><?= $d['bcSmall'] ?><?php endif; ?><?php if (!empty($s['code'])): ?><div style="font-size:9px;font-weight:800;letter-spacing:.2em"><?= h($l['track']) ?></div><?php endif; ?></div></div></div>
<span style="color:#fff;font-size:13px">Shown at 3× · prints at 1 × 2 in</span></div>
<?php else: $sq = $d['view'] === '44'; ?>
<div class="ix-paper"<?= $sq ? ' style="width:384px;height:384px;overflow:hidden"' : '' ?>><div<?= $sq ? ' style="transform:scale(.667);transform-origin:0 0"' : '' ?>><div class="lb lbx">
<div class="lx-top"><?php if (!empty($o['sender'])): ?><div class="lx-from"><b><?= h($l['hub']) ?></b><br><?= h($l['hubAddr']) ?></div><?php endif; ?><div class="lx-meta"><b><?= h($l['weight']) ?></b><span>1 / <?= (int) $l['pieces'] ?></span></div></div>
<div class="lx-svc"><span class="lx-code"><?= h(substr($l['mode'], 0, 1)) ?></span><div><b><?= h($l['mode'] === 'SEA' ? 'SEA FREIGHT' : 'AIR EXPRESS') ?></b><small><?= h(trim($l['pack'] . ' · ' . $l['svc'], ' ·')) ?></small></div><?php if (!empty($o['fragile'])): ?><span class="lx-flag">FRAGILE</span><?php endif; ?></div>
<div class="lx-to"><span class="lx-k">Ship to</span><b class="lx-name"><?= h($l['name']) ?></b><div><?= h($l['addr']) ?></div><b style="font-size:15px"><?= h($l['to']) ?></b><?php if (!empty($o['phone'])): ?><div><?= h($l['phone']) ?></div><?php endif; ?></div>
<div class="lx-route"><?php if (!empty($o['qr'])): ?><span class="lx-qr"><?= $d['qr'] ?></span><?php endif; ?><div class="grow"><div class="row between" style="align-items:baseline"><b style="font-size:26px;letter-spacing:-.01em"><?= h($l['locker'] ?: '—') ?></b><?php if ($l['bay'] !== ''): ?><span class="lx-bay">BAY <?= h($l['bay']) ?></span><?php endif; ?></div><?= $d['bcLocker'] ?></div></div>
<div class="lx-trk"><b><?= h($l['mode'] === 'SEA' ? 'SEA FREIGHT' : 'AIR EXPRESS') ?></b><span>TRACKING # <?= h($l['track']) ?><?= $l['carrierNo'] !== '' ? ' · ' . h(trim($l['carrier'] . ' ' . $l['carrierNo'])) : '' ?></span></div>
<div class="lx-bar"><?= $d['bc'] ?></div>
<?php if (!empty($o['contents'])): ?><div class="lx-cont"><b>CONTENTS</b> <?= h($l['contents']) ?><?php if (!empty($o['value'])): ?> · <b>VALUE <?= h($l['declared']) ?></b><?php endif; ?></div><?php endif; ?>
<?php if (!empty($o['customs'])): ?><div class="lx-cn22"><b>CN22 · CUSTOMS DECLARATION</b><span>Gift ☐ Sample ☐ Merchandise ☒ · declared <?= h($l['declared']) ?></span></div><?php endif; ?>
<div class="lx-foot"><span>BILLING: <?= $l['paid'] ? 'PREPAID' : 'COLLECT ON PICKUP' ?></span><?php if (!empty($o['code'])): ?><span>PICKUP CODE SENT BY WHATSAPP</span><?php endif; ?><?php if (!empty($o['postage'])): ?><span>POSTAGE PAID <?= h($l['total']) ?></span><?php endif; ?><?php if (!empty($o['care'])): ?><span>⬆ THIS SIDE UP · HANDLE WITH CARE</span><?php endif; ?></div>
</div></div></div>
<?php if (!empty($o['ret']) && !$sq): ?><div class="ix-paper"><div class="lb lbx" style="border-style:dashed">
<div class="lx-top"><div class="lx-from"><b><?= h($l['name']) ?></b><br><?= h($l['addr']) ?><br><?= h($l['to']) ?></div><div class="lx-meta"><b>RETURN</b><span>1 / <?= (int) $l['pieces'] ?></span></div></div>
<div class="lx-svc"><span class="lx-code">RS</span><div><b>RETURN SERVICE</b><small>Use within 30 days</small></div></div>
<div class="lx-to"><span class="lx-k">Return to</span><b class="lx-name"><?= h($l['hub']) ?></b><div><?= h($l['hubAddr']) ?></div></div>
<div class="lx-bar"><?= $d['bcRet'] ?></div>
<div class="lx-foot"><span>ORIGINAL <?= h($l['track']) ?></span></div>
</div></div><?php endif; ?>
<?php endif; ?>
</div>
<div class="row between wrap g8 sm"><span class="mut"><?= h($d['summary']) ?></span><span class="mut"><?= h($o['printer']) ?> · browser print to PDF or label printer</span></div>
</div>
</div>
<?php if ($canEdit): ?>
<script>
(function () {
  function save(group, k, v) {
    SL.post('ajax/sl/label_options_ajax.php', { group: group, key: k, value: v }).then(function (j) {
      if (j.success) location.reload(); else SL.toast(j.message || 'Not saved', true);
    });
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-ls-set]'); if (!b) return;
    e.preventDefault(); save(b.getAttribute('data-ls-set'), b.getAttribute('data-k'), b.getAttribute('data-v'));
  });
  document.querySelectorAll('[data-ls-text]').forEach(function (i) { i.addEventListener('change', function () { save(i.getAttribute('data-ls-text'), i.getAttribute('data-k'), i.value); }); });
})();
</script>
<?php endif; ?>
