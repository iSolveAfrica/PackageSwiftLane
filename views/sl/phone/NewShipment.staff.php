<?php
/**
 * M-NewShip (staff): new air shipment on a phone. $d = views/sl/data/shipments.php sl_newShipmentData().
 * Same form, field names and data-ns-* hooks as views/sl/pages/NewShipment.php, so dataJs/sl/new_shipment.js
 * drives it and it posts to the same endpoint ($d['endpoint']). After saving, the phone opens the shipment
 * (the label pop-up of the web page is left out; Label is one tap on the shipment screen).
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['nav'] = 'none';
$slPhone['scripts'][] = 'dataJs/sl/new_shipment.js';
$df = array_merge($d['defaults'], array_intersect_key($d['prefs'], $d['defaults']));
$pa = $d['prealert'];
$unit = $d['rates']['weight'];
$opts = function (array $rows, string $labelKey, $cur, string $empty = ''): string {
    $o = $empty !== '' ? '<option value="">' . h($empty) . '</option>' : '';
    foreach ($rows as $r) {
        $id = (int) (is_array($r) ? $r['id'] : $r->id);
        $lab = is_array($r) ? $r[$labelKey] : ($r->$labelKey ?? '');
        $o .= '<option value="' . $id . '"' . ($id === (int) $cur ? ' selected' : '') . '>' . h(str_replace('_', ' ', (string) $lab)) . '</option>';
    }
    return $o;
};
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-ops.css')) ?>">
<?= sl_phoneTop('New shipment', $d['prefix'] . $d['nextNo'], ['back' => sl_route('AirShipments')]) ?>
<main class="mx-body">
<form id="ns-form" class="mx-nsform" action="<?= h($d['endpoint']) ?>" method="post" enctype="multipart/form-data" novalidate
  data-client="0" data-prefix="<?= h($d['prefix']) ?>" data-rates="<?= h(json_encode($d['rates'])) ?>" data-detail="<?= h(sl_route('ShipmentDetail')) ?>" data-new="<?= h(sl_self()) ?>" data-labels="<?= h(sl_route('LabelStudio')) ?>">
<input type="hidden" name="quick_load_v2" value="1">
<input type="hidden" name="code_prefix" value="<?= h($d['prefix']) ?>"><input type="hidden" name="order_no" value="<?= h($d['nextNo']) ?>">
<input type="hidden" name="tariff_mode" value="1">
<nav class="mx-seg" aria-label="Steps"><a class="mx-segl on" href="#ns-s1">1 Customer</a><a class="mx-segl" href="#ns-s2">2 Package</a><a class="mx-segl" href="#ns-s3">3 Notify &amp; price</a></nav>
<div class="mx-ban info"><i class="ic ic-sm i-sliders" aria-hidden="true"></i><span>Defaults loaded from Settings and your saved choices. Number <b data-ns-prefix><?= h($d['prefix']) ?></b><b data-ns-number><?= h($d['nextNo']) ?></b> is reserved when you save.</span></div>

<div class="mx-sec" id="ns-s1"><h2>Customer</h2></div>
<section class="mx-card mx-form">
<div class="mx-nsgrp" data-ns-search>
<label class="mx-fld"><span>Find the sender</span><span class="mx-nsfind"><input type="search" aria-label="Locker ID, name, phone or e-mail" data-ns-find placeholder="Name, locker ID or phone" value="<?= h($pa['locker'] ?? '') ?>" autocomplete="off" enterkeyhint="search"><button type="button" class="mx-chipbtn" data-ns-findbtn>Search</button></span></label>
<div class="ns-results" data-ns-results hidden></div>
</div>
<div class="mx-nsgrp" data-ns-sender-fields hidden>
<div class="ns-selected" data-ns-sender-summary></div>
<label class="mx-fld"><span>Sender address</span><select name="sender_address_id" data-ns-saddr required><option value="">Pick the sender first</option></select></label>
<button type="button" class="mx-chipbtn" data-ns-change-sender>Change customer</button>
<select name="sender_id" data-ns-sender required hidden><?php if ($pa): ?><option value="<?= (int) $pa['customer_id'] ?>" selected><?= h($pa['customer']) ?></option><?php else: ?><option value="">Search above to pick a customer</option><?php endif; ?></select>
</div>
<div class="mx-nsgrp" data-ns-recipient-panel hidden>
<input type="hidden" name="recipient_type" value="recipient" data-ns-rtype>
<div class="mx-nsgrp" data-ns-rec-search><span class="mx-fldl">Recipient</span><div class="ns-results" data-ns-rec-results hidden></div><p class="mx-note" data-ns-rec-empty hidden>No recipients saved for this customer yet.</p></div>
<div class="mx-nsgrp" data-ns-recipient-fields hidden>
<div class="ns-selected" data-ns-recipient-summary></div>
<label class="mx-fld"><span>Delivery address</span><select name="recipient_address_id" data-ns-raddr required><option value="">Pick the recipient first</option></select></label>
<button type="button" class="mx-chipbtn" data-ns-change-recip>Change recipient</button>
<select name="recipient_id" data-ns-recip required hidden><option value="">Pick the sender first</option></select>
</div>
</div>
<input type="hidden" name="prealert_id" value="<?= (int) ($pa['id'] ?? 0) ?>">
<p class="mx-note" data-ns-prealert-note hidden></p>
<label class="mx-fld"><span>Number country</span><select name="number_country" data-ns-country><option value="">Default</option><?php foreach ($d['numberCountries'] as $country): ?><option value="<?= h($country->iso2) ?>"><?= h($country->name) ?></option><?php endforeach; ?></select></label>
<button type="button" hidden data-ns-number-change aria-expanded="false"></button><span data-ns-country-tag hidden></span><div class="ns-number-edit" hidden></div>
</section>

<div class="mx-sec" id="ns-s2"><h2>Package</h2></div>
<section class="mx-card mx-form">
<input type="hidden" name="order_item_category" value="<?= (int) $df['order_item_category'] ?>">
<input type="hidden" name="agency" value="<?= (int) $df['agency'] ?>"><input type="hidden" name="origin_off" value="<?= (int) $df['origin_off'] ?>"><input type="hidden" name="order_package" value="<?= (int) $df['order_package'] ?>"><input type="hidden" name="order_payment_method" value="<?= (int) $df['order_payment_method'] ?>"><input type="hidden" name="order_deli_time" value="<?= (int) $df['order_deli_time'] ?>"><input type="hidden" name="order_service_options" value="<?= (int) $df['order_service_options'] ?>"><input type="hidden" name="order_date" value="<?= h($d['today']) ?>"><input type="hidden" name="estimated_eta" value="">
<input type="hidden" name="driver_id" value="0">
<label class="mx-fld"><span>Courier company</span><select name="order_courier"><?= $opts($d['couriers'], 'label', $pa['courier_id'] ?? $df['order_courier']) ?></select></label>
<div class="mx-fld"><span id="ns-track-label">Tracking from store</span>
<div class="ns-chips mx-nschips" data-ns-track-box role="group" aria-labelledby="ns-track-label"><span class="ns-chip-list" data-ns-track-chips role="list" aria-live="polite"></span><input class="ns-chip-input mono" data-ns-track-input placeholder="Scan or type, then Enter" autocomplete="off" autocapitalize="characters" enterkeyhint="enter" aria-label="Add a tracking number"></div>
<textarea name="tracking_number" data-ns-track-value hidden><?= h($pa['tracking'] ?? '') ?></textarea></div>
<div class="mx-nsitems"><table><tbody data-ns-items>
<tr data-ns-item><td class="mx-nsdesc"><label class="mx-fld"><span>Description</span><input data-k="description" placeholder="What is inside" value="<?= h($pa['desc'] ?? '') ?>"></label></td><td><label class="mx-fld"><span>Pieces</span><input class="mono" data-k="qty" value="1" inputmode="numeric"></label></td><td><label class="mx-fld"><span>Weight (<?= h($unit) ?>)</span><input class="mono" data-k="weight" inputmode="decimal" placeholder="0.0"></label></td><td><label class="mx-fld"><span>Declared value</span><input class="mono" data-k="declared_value" inputmode="decimal" placeholder="0.00" value="<?= $pa && $pa['price'] > 0 ? h(number_format($pa['price'], 2, '.', '')) : '' ?>"></label></td><td><label class="mx-fld"><span>Custom price</span><input class="mono" data-k="custom_price" inputmode="decimal" placeholder="—" title="Price this item by a fixed amount instead of by weight"></label></td><td class="mx-nsrm"><button type="button" class="mx-chipbtn" data-ns-rm aria-label="Remove item"><i class="ic ic-sm i-trash" aria-hidden="true"></i></button></td></tr>
</tbody></table></div>
<button type="button" class="mx-btn sec" data-ns-add><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add item</button>
<input type="hidden" name="packages" value="[]" data-ns-packages>
<label class="mx-fld"><span>Original total weight (<?= h($unit) ?>)</span><input class="mono" name="package_total_weight" inputmode="decimal" placeholder="Auto from items" data-ns-calc></label>
<p class="mx-note" data-ns-wsum>0 <?= h($unit) ?> · 0 pieces</p>
<div class="mx-nsdanger" data-ns-danger-box><input type="hidden" name="is_dangerous_good" value="0"><label class="mx-chk"><input type="checkbox" name="is_dangerous_good" value="1" data-ns-danger> Contains dangerous goods (batteries, liquids, aerosols)</label></div>
<label class="mx-fld"><span>Photo</span><input type="file" name="filesMultiple[]" accept="image/*" capture="environment" data-ns-files></label>
<label class="mx-fld"><span>Invoices or receipts</span><input type="file" name="filesMultiple[]" multiple accept="image/*,application/pdf" data-ns-files></label>
<input type="file" name="filesVideo[]" accept="video/*" hidden data-ns-files><span data-ns-fname hidden></span>
<label class="mx-fld"><span>Notes</span><textarea name="courier_notes" placeholder="Condition of the box, anything the next person should know"></textarea></label>
</section>

<div class="mx-sec" id="ns-s3"><h2>Notify the customer</h2><?php if ($user->cdp_hasPermission('view_tools')): ?><a href="<?= h(sl_route('NotificationCenter')) ?>">Settings</a><?php endif; ?></div>
<section class="mx-card mx-form">
<?php if ($d['notify']['whatsapp']): ?><label class="mx-chk nt-ch"><input type="checkbox" name="notify_whatsapp_sender" value="1"<?= ($d['prefs']['notify_whatsapp_sender'] ?? 1) ? ' checked' : '' ?> data-ns-chip> WhatsApp to the sender</label><?php else: ?><input type="checkbox" name="notify_whatsapp_sender" value="1" hidden><?php endif; ?>
<label class="mx-chk nt-ch"><input type="checkbox" name="notify_sms_receiver" value="1"<?= !empty($d['prefs']['notify_sms_receiver']) ? ' checked' : '' ?> data-ns-chip> SMS to the recipient</label>
<p class="mx-note">SMS and e-mail to the sender are always sent.</p>
<label class="mx-fld"><span>Status</span><select name="status_courier"><?= $opts($d['statuses'], 'mod_style', $df['status_courier']) ?></select></label>
<div class="mx-btns" style="flex-direction:row"><button type="button" class="mx-btn sec" style="flex:1" data-ns-savedef>Save as my defaults</button><button type="button" hidden data-ns-edit></button></div>
<p class="mx-note" style="display:none" data-ns-saved>Saved as your defaults for the next shipment.</p>
</section>
<section class="mx-card mx-kv">
<div><span>Price per <?= h($unit) ?></span><b><input class="mx-nsnum mono" name="price_lb" value="<?= h(number_format($d['rates']['price_lb'], 2, '.', '')) ?>" inputmode="decimal" aria-label="Price per weight unit" data-ns-calc readonly><button type="button" class="mx-chipbtn" data-ns-edit-rate aria-label="Edit price per weight unit"><i class="ic ic-sm i-edit" aria-hidden="true"></i></button></b></div>
<div><span>Chargeable weight</span><b data-q="w">0</b></div>
<div><span>Freight</span><b data-q="freight">—</b></div>
<div><span>Declared value</span><b data-q="decl">—</b></div>
<div><span>Insured value · <?= $d['rates']['insurance_mode'] === 'fixed' ? h(sl_money($d['rates']['insurance_fixed'])) . ' fixed' : h($d['rates']['insurance']) . '%' ?></span><b><input class="mx-nsnum mono" name="insured_value" value="0" inputmode="decimal" aria-label="Insured value" data-ns-calc> <span data-q="ins">—</span></b></div>
<div><span>Re-shipping fee</span><b><input class="mx-nsnum mono" name="reexpedicion_value" value="0" inputmode="decimal" aria-label="Re-shipping fee" data-ns-calc></b></div>
<div><span>Discount</span><b><span class="mx-nsdt" data-ns-dtype><button type="button" class="mx-chipbtn on" data-v="amount" aria-label="Discount as an amount">$</button><button type="button" class="mx-chipbtn" data-v="percent" aria-label="Discount as a percentage">%</button></span><input class="mx-nsnum mono" name="discount_value" value="0" inputmode="decimal" aria-label="Discount" data-ns-calc></b></div>
<input type="hidden" name="discount_type" value="amount">
<input type="hidden" name="tax_value" value="0"><input type="hidden" name="insurance_value" value="<?= h($d['rates']['insurance']) ?>"><input type="hidden" name="tariffs_value" value="0"><input type="hidden" name="declared_value_tax" value="0"><input type="hidden" name="distance_miles" value="0">
<div><span>Estimated charge</span><b data-q="total">—</b></div>
<?php if ($d['rates']['rate'] > 0): ?><div><span>In cedis (rate <?= h(number_format($d['rates']['rate'], 2)) ?>)</span><b data-q="ghs">—</b></div><?php endif; ?>
</section>
<p class="mx-note" data-q="note">Final price is confirmed on save.</p>
<div class="mx-btns"><button type="submit" class="mx-btn or">Create shipment</button><button type="submit" class="mx-btn sec" data-ns-next>Save and add next</button></div>
</form>
</main>
