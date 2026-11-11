<?php
/**
 * ShippingAddresses - from docs/handover/artifact-source/ShippingAddresses.dc.html.
 * Data: shipping_addresses.php. Writes: ajax/sl/shipping_addresses_ajax.php.
 */
if (!isset($d)) { $d = []; }
$ajax = 'ajax/sl/shipping_addresses_ajax.php';
$pv = $d['pv'];
?>
<div class="row g8 mut sm"><a href="<?= sl_route('SettingsHub') ?>">Settings</a><i class="ic ic-sm i-right" aria-hidden="true"></i><span style="color:var(--ink)">Shipping addresses</span></div>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:20px">Shipping addresses</h1><p class="sl-lede">Warehouse addresses customers ship to. Switch one on and it shows on every customer's account right away.</p></div><div class="row g8"><a class="sl-btn ghost" href="client_virtual_mail_box_addresses.php"><i class="ic ic-sm i-eye" aria-hidden="true"></i>See as customer</a><?php if ($d['canEdit']): ?><button class="sl-btn" type="button" data-sa-new><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add address</button><?php endif; ?></div></div>
<?php if (!$d['hasExtra']): ?><div class="sl-banner warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i>Label, shipping mode and the show/hide switch need the database upgrade <b>2026_11_06_messaging_settings</b>.</div><?php endif; ?>
<div class="split">
<div class="a col g12">
<form class="sl-card col g12" style="border-color:var(--ink);display:none" id="sa-form" data-sl-form action="<?= $ajax ?>" method="post"><input type="hidden" name="op" value="save"><input type="hidden" name="id" value="0"><div class="sl-ch" style="margin:0"><h2 class="sl-ct" id="sa-title">New address</h2><button class="sl-ib" type="button" aria-label="Close" data-sa-close><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div>
<div class="grid cols2"><label class="sl-field"><span class="sl-lab">Country</span><span class="sl-sel native"><select name="cdb_countries_id"><option value="">Pick a country</option><?php foreach ($d['countries'] as $c): ?><option value="<?= (int) $c->id ?>"><?= h($c->name) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label><label class="sl-field"><span class="sl-lab">Label</span><input class="sl-in" name="label" placeholder="Guangzhou air warehouse"></label><label class="sl-field"><span class="sl-lab">City</span><input class="sl-in" name="city" placeholder="Guangzhou"></label><label class="sl-field"><span class="sl-lab">Postcode or warehouse phone</span><input class="sl-in" name="postcode" placeholder="510000"></label><label class="sl-field" style="grid-column:1/-1"><span class="sl-lab">Address (write LOCKER ID where the customer's suite goes)</span><textarea class="sl-in" name="address" rows="4" placeholder="Street, building, (LOCKER ID)"></textarea></label><label class="sl-field"><span class="sl-lab">Transit time</span><input class="sl-in" name="transit_days" placeholder="7–10 days"></label></div>
<div class="row g12 wrap"><span class="sl-lab">Shipping mode</span><div class="sl-seg boxed" data-sa-mode><?php foreach (['air' => 'Air', 'sea' => 'Sea', 'express' => 'Express'] as $k => $l): ?><button type="button" class="<?= $k === 'air' ? 'on' : '' ?>" data-v="<?= $k ?>"><?= $l ?></button><?php endforeach; ?></div><input type="hidden" name="shipping_mode" value="air"><span class="grow"></span><button class="sl-btn ghost" type="button" data-sa-close>Cancel</button><button class="sl-btn" type="submit"><i class="ic ic-sm i-check" aria-hidden="true"></i>Save and show to customers</button></div></form>
<section class="sl-card flush"><div class="row between" style="padding:14px 16px"><h2 class="sl-ct">Addresses</h2><span class="mut sm"><?= (int) $d['onCount'] ?> of <?= (int) $d['count'] ?> showing to customers</span></div>
<?php foreach ($d['rows'] as $r): ?><div class="row g12" style="padding:12px 16px;border-top:1px solid var(--line)"><span class="sl-tile" style="font:600 14px var(--mono)"><?= h($r['cc']) ?></span><div class="grow col" style="min-width:0"><span class="fw5"><?= h($r['label']) ?> <span class="mut sm">· <?= h($r['mode']) ?></span></span><span class="mut sm"><?= h($r['line']) ?></span></div><span class="mut sm" style="width:110px"><?= h($r['transit']) ?></span><span class="sm <?= h($r['sc']) ?>" style="width:80px"><?= h($r['state']) ?></span><?php if ($d['canEdit'] && $d['hasExtra']): ?><span class="sl-toggle <?= $r['on'] ? 'on' : '' ?>" role="switch" aria-checked="<?= $r['on'] ? 'true' : 'false' ?>" aria-label="Show <?= h($r['label']) ?> to customers" data-sl-save="<?= $ajax ?>" data-p-op="toggle" data-p-id="<?= (int) $r['id'] ?>"></span><?php endif; ?><a class="sl-ib" aria-label="Preview" href="<?= h(sl_url(['id' => $r['id']])) ?>"><i class="ic ic-sm i-eye" aria-hidden="true"></i></a><?php if ($d['canEdit']): ?><button class="sl-ib" type="button" aria-label="Edit" data-sa-edit="<?= h(json_encode($r['raw'])) ?>"><i class="ic ic-sm i-edit" aria-hidden="true"></i></button><button class="sl-ib" aria-label="Delete" data-sl-action="<?= $ajax ?>" data-p-op="delete" data-p-id="<?= (int) $r['id'] ?>" data-sl-confirm="Delete this address? Customers stop seeing it." data-sl-reload><i class="ic ic-sm i-trash" aria-hidden="true"></i></button><?php endif; ?></div><?php endforeach; ?>
<?php if (!$d['rows']): ?><div class="sl-empty" style="padding:24px">No shipping addresses yet.</div><?php endif; ?></section>
</div>
<aside class="b col g12"><span class="up">What customers see</span>
<?php if ($pv): ?><div style="border-radius:20px;background:var(--ink);color:var(--inverse);padding:20px;display:flex;flex-direction:column;gap:10px"><span class="row g8"><span class="sl-tile" style="background:rgba(255,255,255,.1);color:inherit;font:600 14px var(--mono)"><?= h($pv['cc']) ?></span><b class="fw5"><?= h($pv['label']) ?> · <?= h($pv['mode']) ?></b></span>
<?php foreach ($pv['pv'] as $l): ?><div class="row between g12" style="border-top:1px dashed rgba(255,255,255,.18);padding-top:8px;font-size:15.5px"><span style="opacity:.55;font-size:13px;text-transform:uppercase;letter-spacing:.06em"><?= h($l['k']) ?></span><span style="text-align:right"><?= h($l['v']) ?></span></div><?php endforeach; ?>
<?php if ($pv['transit']): ?><span class="sm" style="opacity:.7"><?= h($pv['transit']) ?> to destination</span><?php endif; ?></div><?php endif; ?>
<div class="sl-card col g6"><span class="fw5 sm">Customer's suite number is added automatically</span><span class="mut sm">"LOCKER ID" in an address is replaced with each customer's locker. Changes are written to Activity Logs.</span></div></aside></div>
<script>
(function(){
  var f = document.getElementById('sa-form');
  function open(v){ v = v || {}; f.style.display = 'flex'; f.elements.id.value = v.id || 0; ['cdb_countries_id','label','city','postcode','address','transit_days'].forEach(function(k){ f.elements[k].value = v[k] || ''; }); setMode(v.shipping_mode || 'air'); document.getElementById('sa-title').textContent = v.id ? 'Edit address' : 'New address'; f.scrollIntoView({ behavior: 'smooth' }); }
  function setMode(m){ f.elements.shipping_mode.value = m; f.querySelectorAll('[data-sa-mode] button').forEach(function(b){ b.classList.toggle('on', b.getAttribute('data-v') === m); }); }
  document.addEventListener('click', function(e){ var b;
    if (e.target.closest('[data-sa-new]')) open();
    else if ((b = e.target.closest('[data-sa-edit]'))) open(JSON.parse(b.getAttribute('data-sa-edit')));
    else if (e.target.closest('[data-sa-close]')) f.style.display = 'none';
    else if ((b = e.target.closest('[data-sa-mode] button'))) setMode(b.getAttribute('data-v'));
  });
})();
</script>
