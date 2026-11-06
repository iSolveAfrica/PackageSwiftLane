<?php
/**
 * ShipmentForm - the NewShipment / NewPackage artboards (docs/handover/artifact-source/NewShipment.dc.html,
 * NewPackage.dc.html) reused for every other shipment/package form:
 *   edit (courier_edit.php), accept (courier_accept.php), pickup (pickup_accept.php),
 *   multiple (courier_add_multiple.php), package_edit (customer_package_edit.php),
 *   prealert (customer_packages_add_from_prealert.php), package_multiple (customer_packages_multiple.php).
 * Data: views/sl/data/ops_forms.php (sl_shipFormData). Posts the legacy field names to the
 * legacy endpoint in $d['endpoint'] so pricing, numbering, notifications and audit stay there.
 * Client behaviour: dataJs/sl/shipment_form.js.
 */
if (!isset($d)) { $d = []; }
$v = $d['vals'];
$pkg = $d['kind'] === 'package';
$multi = in_array($d['mode'], ['multiple', 'package_multiple'], true);
$sel = function (string $name, array $rows, string $labelKey, $cur, string $empty = '') {
    $o = $empty !== '' ? '<option value="">' . h($empty) . '</option>' : '';
    foreach ($rows as $r) {
        $id = (int) (is_array($r) ? $r['id'] : $r->id);
        $lab = is_array($r) ? $r[$labelKey] : ($r->$labelKey ?? '');
        $o .= '<option value="' . $id . '"' . ($id === (int) $cur ? ' selected' : '') . '>' . h(str_replace('_', ' ', (string) $lab)) . '</option>';
    }
    return '<span class="sl-sel native"><select name="' . h($name) . '" aria-label="' . h($name) . '">' . $o . '</select><i class="ic ic-sm i-down" aria-hidden="true"></i></span>';
};
$step = 0; $steps = $d['hasRecipient'] ? 4 : 4;
$pre = $d['pre'];
?>
<form id="sf-form" class="col g16" action="<?= h($d['endpoint']) ?>" method="post" enctype="multipart/form-data" novalidate
  data-rates="<?= h(json_encode($d['rates'])) ?>" data-ok="<?= h($d['ok']) ?>" data-mode="<?= h($d['mode']) ?>" data-recipient="<?= $d['hasRecipient'] ? '1' : '0' ?>"
  data-pre="<?= h(json_encode($pre)) ?>" data-items="<?= h(json_encode($d['items'])) ?>" data-pickup-module="<?= $pkg ? 'sea' : 'air' ?>" data-pickup-ids="<?= (int) $d['id'] ?>">
<?php foreach ($d['hidden'] as $k => $hv): ?><input type="hidden" name="<?= h($k) ?>" value="<?= h($hv) ?>"><?php endforeach; ?>
  <div class="sl-head">
    <div><a href="<?= h($d['back']['href']) ?>" class="row g6 mut sm"><i class="ic ic-sm i-left" aria-hidden="true"></i><?= h($d['back']['label']) ?></a><h1 class="sl-title" style="margin-top: 4px"><?= h($d['title']) ?></h1></div>
    <div class="row g8 wrap"><a class="sl-btn ghost" href="<?= h($d['back']['href']) ?>">Cancel</a><button type="submit" class="sl-btn"><?= h($d['submit']) ?></button></div>
  </div>
<?php if ($multi): ?>
  <div class="sl-banner info"><i class="ic i-layers" aria-hidden="true"></i><span>Every item row below becomes its own <?= $pkg ? 'package' : 'shipment' ?> for the same <?= $d['hasRecipient'] ? 'sender and recipient' : 'customer' ?>, with the settings on this page. Numbers are assigned on save.</span></div>
<?php elseif ($d['mode'] === 'accept' || $d['mode'] === 'pickup'): ?>
  <div class="sl-banner"><i class="ic i-info" aria-hidden="true"></i><span><?= $d['mode'] === 'pickup' ? 'This pickup was requested by the customer. Check the details, weigh the items and accept it to turn it into a priced shipment.' : 'This shipment was created by the customer. Check the details, weigh the items and accept it to price it and notify the customer.' ?></span></div>
<?php elseif ($d['mode'] === 'prealert' && !empty($d['prealertInfo'])): $pi = $d['prealertInfo']; ?>
  <div class="sl-banner info"><i class="ic i-bell" aria-hidden="true"></i><span>Pre-alert <b class="mono"><?= h($pi['tracking']) ?></b> from <?= h($pi['customer']) ?> · <?= h($pi['shop']) ?><?= $pi['date'] ? ' · ' . h($pi['date']) : '' ?><?php if ($pi['invoice']): ?> · <a href="<?= h($pi['invoice']) ?>" target="_blank" rel="noopener">Invoice</a><?php endif; ?></span></div>
<?php endif; ?>
  <div class="split">
    <div class="a col g16">
      <section class="sl-card col g16">
        <div class="row between"><h2 class="sl-ct"><?= $d['hasRecipient'] ? 'Sender and recipient' : 'Customer' ?></h2><span class="sl-badge">Step <?= ++$step ?> of <?= $steps ?></span></div>
        <div class="grid cols2">
          <div class="col g12">
            <label class="sl-field"><span class="sl-lab">Find customer</span><div class="row g6"><span class="sl-search" style="flex:1;max-width:none"><i class="ic ic-sm i-lock" aria-hidden="true"></i><input class="mono" aria-label="Locker ID or name" data-sf-find placeholder="Locker ID, name, phone or e-mail" autocomplete="off"></span><button type="button" class="sl-btn ghost sm" style="height:40px" data-sf-findbtn><i class="ic ic-sm i-search" aria-hidden="true"></i>Find</button></div><span class="sl-hint">Only needed to change the customer</span></label>
            <label class="sl-field"><span class="sl-lab"><?= $d['hasRecipient'] ? 'Sender (customer)' : 'Customer' ?></span><span class="sl-sel native"><select name="sender_id" data-sf-sender required><?php if ($pre['sender']): ?><option value="<?= (int) $pre['sender']['id'] ?>" selected><?= h($pre['sender']['label']) ?></option><?php else: ?><option value="">Search above to pick a customer</option><?php endif; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
            <label class="sl-field"><span class="sl-lab"><?= $d['hasRecipient'] ? 'Sender address' : 'Customer address' ?></span><span class="sl-sel native"><select name="sender_address_id" data-sf-saddr required><option value="">Pick the customer first</option></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
          </div>
          <div class="col g12">
<?php if ($d['hasRecipient']): ?>
            <input type="hidden" name="recipient_type" value="<?= h($pre['rtype']) ?>" data-sf-rtype>
            <label class="sl-field"><span class="sl-lab">Recipient</span><span class="sl-sel native"><select name="recipient_id" data-sf-recip required><option value="">Pick the sender first</option></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
            <label class="sl-field"><span class="sl-lab">Recipient address</span><span class="sl-sel native"><select name="recipient_address_id" data-sf-raddr required><option value="">Pick the recipient first</option></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
<?php else: ?>
            <span class="sl-hint" style="margin-top:28px">Packages are held for the customer; the recipient is chosen when the package ships.</span>
<?php endif; ?>
            <div class="row g12 wrap sm"><a href="<?= sl_route('AddCustomer') ?>" class="fw5 row g6" target="_blank"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add new customer</a><?php if ($d['hasRecipient']): ?><a href="<?= sl_route('Recipients') ?>" class="fw5 row g6" target="_blank"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add recipient or address</a><?php endif; ?></div>
          </div>
        </div>
        <div class="col g10" style="padding: 12px 14px; border-radius: 12px; background: var(--active)">
          <span class="fw5 sm row g6"><i class="ic ic-sm i-bell" aria-hidden="true"></i>Notifications</span>
          <div class="row g12 wrap sm">
<?php if ($d['notify']['whatsapp'] && in_array($d['mode'], ['accept', 'pickup', 'multiple', 'prealert', 'package_multiple', 'package_edit'], true)): ?><label class="row g6"><input type="checkbox" name="notify_whatsapp_sender" value="1" checked>WhatsApp to the customer</label><?php endif; ?>
<?php if (in_array($d['mode'], ['edit', 'pickup', 'package_edit'], true)): ?><label class="row g6"><input type="checkbox" name="notify_sms_sender" value="1">SMS to the customer</label><?php endif; ?>
<?php if ($d['hasRecipient'] && in_array($d['mode'], ['edit', 'pickup', 'multiple'], true)): ?><label class="row g6"><input type="checkbox" name="notify_sms_receiver" value="1">SMS to the recipient</label><?php endif; ?>
          </div>
        </div>
      </section>

      <section class="sl-card col g16">
        <div class="row between"><h2 class="sl-ct"><?= $pkg ? 'Package details' : 'Shipment details' ?></h2><span class="sl-badge">Step <?= ++$step ?> of <?= $steps ?></span></div>
        <input type="hidden" name="order_item_category" value="<?= (int) $v['order_item_category'] ?>">
        <div class="grid cols3">
          <?php if ($d['agencyLocked']): ?><input type="hidden" name="agency" value="<?= (int) $v['agency'] ?>"><?php else: ?><label class="sl-field"><span class="sl-lab">Agency</span><?= $sel('agency', $d['agencies'], 'label', $v['agency']) ?></label><?php endif; ?>
          <label class="sl-field"><span class="sl-lab">Branch / office</span><?= $sel('origin_off', $d['offices'], 'label', $v['origin_off']) ?></label>
          <?php if (!empty($d['numberEditable'])): ?><label class="sl-field"><span class="sl-lab">Package number</span><input class="sl-in mono" name="order_no" value="<?= h($d['hidden']['order_no'] ?? '') ?>" required></label><?php endif; ?>
          <?php if ($d['mode'] !== 'prealert'): ?><label class="sl-field"><span class="sl-lab">Status</span><?= $sel('status_courier', $d['statuses'], 'mod_style', $v['status_courier']) ?></label><?php endif; ?>
          <label class="sl-field"><span class="sl-lab">Packaging</span><?= $sel('order_package', $d['packaging'], 'label', $v['order_package']) ?></label>
          <label class="sl-field"><span class="sl-lab">Courier company</span><?= $sel('order_courier', $d['couriers'], 'label', $v['order_courier']) ?></label>
          <label class="sl-field"><span class="sl-lab">Service</span><?= $sel('order_service_options', $d['services'], 'label', $v['order_service_options']) ?></label>
          <label class="sl-field"><span class="sl-lab">Delivery time</span><?= $sel('order_deli_time', $d['delitimes'], 'label', $v['order_deli_time']) ?></label>
          <?php if (!$pkg): ?><label class="sl-field"><span class="sl-lab">Payment terms</span><?= $sel('order_payment_method', $d['payTerms'], 'label', $v['order_payment_method']) ?></label><?php endif; ?>
          <label class="sl-field"><span class="sl-lab">Order date</span><input class="sl-in" type="date" name="order_date" value="<?= h($v['order_date']) ?>"></label>
          <?php if ($d['mode'] !== 'package_multiple'): ?><label class="sl-field"><span class="sl-lab">Estimated arrival</span><input class="sl-in" type="date" name="estimated_eta" value="<?= h(substr((string) $v['estimated_eta'], 0, 10)) ?>"></label><?php endif; ?>
          <label class="sl-field"><span class="sl-lab">Assign driver</span><span class="sl-sel native"><select name="driver_id" aria-label="Driver"><option value="0">Unassigned</option><?php foreach ($d['drivers'] as $dr): ?><option value="<?= (int) $dr['id'] ?>" <?= (int) $dr['id'] === (int) $v['driver_id'] ? 'selected' : '' ?>><?= h($dr['label']) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
          <?php if (!$pkg): ?><label class="sl-field"><span class="sl-lab">Carrier tracking number</span><input class="sl-in mono" name="tracking_number" value="<?= h($v['tracking_number']) ?>" placeholder="1Z999AA10123456784"></label>
          <label class="sl-field"><span class="sl-lab">Rate from</span><span class="sl-sel native"><select data-sf-ratemode aria-label="Rate from"><option value="manual" <?= $v['manual'] ? 'selected' : '' ?>>Manual · price per <?= h($d['rates']['weight']) ?></option><option value="tariff" <?= $d['hasTariffs'] ? '' : 'disabled' ?> <?= !$v['manual'] && $d['hasTariffs'] ? 'selected' : '' ?>>Tariff table<?= $d['hasTariffs'] ? '' : ' (none configured)' ?></option></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span><input type="checkbox" name="tariff_mode" value="1" hidden data-sf-tariffmode <?= $v['manual'] ? 'checked' : '' ?>></label><?php endif; ?>
          <label class="sl-field"><span class="sl-lab">Price per <?= h($d['rates']['weight']) ?></span><input class="sl-in mono" name="price_lb" value="<?= h(number_format((float) $v['price_lb'], 2, '.', '')) ?>" inputmode="decimal" data-sf-calc></label>
        </div>
<?php if ($pkg): ?>
        <div class="grid cols3">
          <label class="sl-field"><span class="sl-lab">Store tracking number</span><input class="sl-in mono" name="tracking_purchase" value="<?= h($v['tracking_purchase']) ?>"></label>
          <label class="sl-field"><span class="sl-lab">Store / provider</span><input class="sl-in" name="provider_purchase" value="<?= h($v['provider_purchase']) ?>"></label>
          <label class="sl-field"><span class="sl-lab">Purchase price</span><input class="sl-in mono" name="price_purchase" value="<?= h($v['price_purchase']) ?>" inputmode="decimal"></label>
        </div>
<?php endif; ?>
<?php if (!$pkg && !$multi): ?>
        <div class="row between wrap g12"><label class="row g8" style="cursor:pointer"><span class="sl-toggle <?= $v['is_dangerous_good'] ? 'on' : '' ?>" role="switch" aria-checked="<?= $v['is_dangerous_good'] ? 'true' : 'false' ?>" aria-label="Contains dangerous goods" data-sf-dg></span><input type="hidden" name="is_dangerous_good" value="<?= (int) $v['is_dangerous_good'] ?>">Contains dangerous goods</label><span class="sl-hint">Batteries, liquids and aerosols need an approved declaration.</span></div>
<?php endif; ?>
      </section>

      <section class="sl-card flush">
        <div class="row between" style="padding: 16px 20px"><div class="row g12"><h2 class="sl-ct"><?= $multi ? ($pkg ? 'Packages' : 'Shipments') . ' (one per row)' : 'Items' ?></h2><span class="sl-badge">Step <?= ++$step ?> of <?= $steps ?></span></div><button type="button" class="sl-btn ghost sm" data-sf-add><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add <?= $multi ? 'row' : 'item' ?></button></div>
        <div class="sl-tw"><table class="sl-table">
          <thead><tr><th style="width:70px">Qty</th><th>Description</th><th class="num" style="width:96px">Weight (<?= h($d['rates']['weight']) ?>)</th><th class="num" style="width:76px">L</th><th class="num" style="width:76px">W</th><th class="num" style="width:76px">H</th><?php if (!$pkg && !$multi): ?><th class="num" style="width:104px">Custom price</th><?php endif; ?><th class="num" style="width:110px">Declared value</th><th style="width:44px"></th></tr></thead>
          <tbody data-sf-items>
            <tr data-sf-item><td><input class="sl-in mono" data-k="qty" value="1" inputmode="numeric" aria-label="Quantity"></td><td><input class="sl-in" data-k="description" placeholder="What is inside" aria-label="Description"></td><td class="num"><input class="sl-in mono" data-k="weight" inputmode="decimal" placeholder="0.0" aria-label="Weight"></td><td class="num"><input class="sl-in mono" data-k="length" inputmode="decimal" placeholder="0" aria-label="Length"></td><td class="num"><input class="sl-in mono" data-k="width" inputmode="decimal" placeholder="0" aria-label="Width"></td><td class="num"><input class="sl-in mono" data-k="height" inputmode="decimal" placeholder="0" aria-label="Height"></td><?php if (!$pkg && !$multi): ?><td class="num"><input class="sl-in mono" data-k="custom_price" inputmode="decimal" placeholder="—" aria-label="Custom price"></td><?php endif; ?><td class="num"><input class="sl-in mono" data-k="declared_value" inputmode="decimal" placeholder="0.00" aria-label="Declared value"></td><td><button type="button" class="sl-ib sm" aria-label="Remove row" data-sf-rm><i class="ic i-trash" aria-hidden="true"></i></button></td></tr>
          </tbody></table></div>
        <div class="row between wrap g12" style="padding: 14px 20px; border-top: 1px solid var(--line)"><?php if (!$pkg && !$multi): ?><label class="row g8 sm"><span class="mut">Original total weight</span><input class="sl-in mono" name="package_total_weight" style="width:110px" inputmode="decimal" placeholder="auto" value="<?= h($v['package_total_weight']) ?>" data-sf-calc></label><?php else: ?><span class="mut sm">Volumetric weight uses L×W×H ÷ <?= h($d['rates']['meter'] ?: '—') ?></span><?php endif; ?><span class="fw5" data-sf-wsum>0 <?= h($d['rates']['weight']) ?> · 0 pieces</span></div>
        <input type="hidden" name="packages" value="[]" data-sf-packages>
      </section>

      <section class="sl-card col g16">
        <div class="row between"><h2 class="sl-ct">Photos, documents and notes</h2><span class="sl-badge">Step <?= ++$step ?> of <?= $steps ?></span></div>
<?php if ($d['files']): ?>
        <div class="col"><?php foreach ($d['files'] as $fl): ?><div class="row between g8 sm" style="padding:8px 0;border-top:1px solid var(--line)"><a class="row g8" href="<?= h($fl->url) ?>" target="_blank" rel="noopener"><i class="ic ic-sm i-paperclip" aria-hidden="true"></i><?= h($fl->name) ?></a><span class="row g8"><span class="mut"><?= h(sl_date($fl->date_file)) ?></span><button type="button" class="sl-ib sm" aria-label="Delete <?= h($fl->name) ?>" data-sl-action="<?= h($d['fileDel']) ?>" data-p-id="<?= (int) $fl->id ?>" data-sl-confirm="Delete <?= h($fl->name) ?>?" data-sl-reload><i class="ic ic-sm i-trash" aria-hidden="true"></i></button></span></div><?php endforeach; ?></div>
<?php endif; ?>
<?php if (!$multi): ?>
        <div class="grid cols2">
          <label class="sl-drop" style="cursor:pointer"><i class="ic ic-lg i-upload" aria-hidden="true"></i><span class="fw5" style="color: var(--ink)">Drop files or browse</span><span class="sm" id="sf-fname" data-empty="Invoices, receipts, photos">Invoices, receipts, photos</span><input type="file" name="filesMultiple[]" multiple accept="image/*,application/pdf" hidden data-ops-file="#sf-fname"></label>
          <label class="sl-drop" style="cursor:pointer"><i class="ic ic-lg i-image" aria-hidden="true"></i><span class="fw5" style="color: var(--ink)">Take a photo</span><span class="sm" id="sf-cname" data-empty="Capture the parcel with your camera">Capture the parcel with your camera</span><input type="file" name="<?= $pkg ? 'filesCapture[]' : 'filesMultiple[]' ?>" accept="image/*" capture="environment" hidden data-ops-file="#sf-cname"></label>
<?php if (in_array($d['mode'], ['edit', 'package_edit'], true)): ?>
          <label class="sl-drop" style="cursor:pointer"><i class="ic ic-lg i-play" aria-hidden="true"></i><span class="fw5" style="color: var(--ink)">Record a video</span><span class="sm" id="sf-vname" data-empty="Show the parcel's condition">Show the parcel's condition</span><input type="file" name="filesVideo[]" accept="video/*" capture="environment" hidden data-ops-file="#sf-vname"></label>
<?php endif; ?>
        </div>
<?php endif; ?>
<?php if (!$pkg && !$multi): ?><label class="sl-field"><span class="sl-lab">Notes</span><textarea class="sl-in" name="courier_notes" style="height:84px;padding:10px 12px" placeholder="Condition of the box, anything the next person should know"><?= h($v['courier_notes']) ?></textarea></label><?php endif; ?>
      </section>
    </div>

    <aside class="b col g16" aria-label="Quote summary">
<?php if ($d['sum']): $s = $d['sum']; ?>
      <section class="sl-card col g10"><div class="row between"><b class="mono fw5"><?= h($s['track']) ?></b><span class="sl-pill <?= h($s['pc']) ?>"><?= h($s['status']) ?></span></div><div class="sl-kv"><?php foreach (array_slice($s['kv'], 0, 4) as $kv): ?><div><span><?= h($kv[0]) ?></span><span><?= h($kv[1]) ?></span></div><?php endforeach; ?></div></section>
<?php endif; ?>
      <section class="sl-card col g12" style="position:sticky;top:12px">
        <h2 class="sl-ct">Quote summary<?= $multi ? ' · per row' : '' ?></h2>
        <div class="sl-kv">
          <div><span>Chargeable weight</span><span data-q="w">0</span></div>
          <div><span>Freight</span><span data-q="freight">—</span></div>
          <div><span>Declared value · tax <?= h($d['rates']['declared']) ?>%</span><span data-q="decl">—</span></div>
          <div><span>Insured value · <?= h($d['rates']['insurance']) ?>%</span><span class="row g6"><input class="sl-in mono" name="insured_value" value="<?= h($v['insured_value']) ?>" style="width:90px;height:28px" inputmode="decimal" aria-label="Insured value" data-sf-calc><span data-q="ins">—</span></span></div>
          <div><span>Re-shipping fee</span><input class="sl-in mono" name="reexpedicion_value" value="<?= h($v['reexpedicion_value']) ?>" style="width:90px;height:28px" inputmode="decimal" aria-label="Re-shipping fee" data-sf-calc></div>
          <div><span>Customs tax · <?= h($d['rates']['tariffs']) ?>%</span><span data-q="customs">—</span></div>
          <div><span>Tax · <?= h($d['rates']['tax']) ?>%</span><span data-q="tax">—</span></div>
          <div><span>Discount</span><span class="row g6"><span class="sl-seg boxed" style="height:26px" data-sf-dtype><button type="button" class="<?= $v['discount_type'] === 'amount' ? 'on' : '' ?>" data-v="amount" style="height:22px;padding:0 8px">$</button><button type="button" class="<?= $v['discount_type'] !== 'amount' ? 'on' : '' ?>" data-v="percent" style="height:22px;padding:0 8px">%</button></span><input class="sl-in mono" name="discount_value" value="<?= h($v['discount_value']) ?>" style="width:70px;height:28px" inputmode="decimal" aria-label="Discount" data-sf-calc></span></div>
        </div>
        <input type="hidden" name="discount_type" value="<?= h($v['discount_type']) ?>">
        <input type="hidden" name="tax_value" value="<?= h($d['rates']['tax']) ?>"><input type="hidden" name="insurance_value" value="<?= h($d['rates']['insurance']) ?>"><input type="hidden" name="tariffs_value" value="<?= h($d['rates']['tariffs']) ?>"><input type="hidden" name="declared_value_tax" value="<?= h($d['rates']['declared']) ?>"><input type="hidden" name="distance_miles" value="0">
        <div class="row between" style="padding-top: 6px"><span class="fw5">Total due</span><span class="big" data-q="total">—</span></div>
        <div class="sm mut" data-q="note">Final price is confirmed on save.</div>
        <button type="submit" class="sl-btn block"><?= h($d['submit']) ?></button>
      </section>
    </aside>
  </div>
</form>
