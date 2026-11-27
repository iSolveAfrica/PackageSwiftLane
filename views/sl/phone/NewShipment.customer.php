<?php
/**
 * M-CShip1 / M-CShip2 / M-CShip3: a customer's new shipment (courier_add_client.php, sl_newShipmentData(client=true)).
 * One form in three steps (addresses · items · price). It posts the web form's own fields to the same legacy
 * endpoint ($d['endpoint'] = ajax/courier/add_courier_client_ajax.php), so pricing, numbering and notifications stay
 * on the server; dataJs/sl/phone/new_shipment.js switches the steps and mirrors the web quote (dataJs/sl/new_shipment.js).
 */
$slPhone['nav'] = 'none';
$slPhone['scripts'][] = 'dataJs/sl/phone/new_shipment.js';
$df = array_merge((array) ($d['defaults'] ?? []), array_intersect_key((array) ($d['prefs'] ?? []), (array) ($d['defaults'] ?? [])));
$me = (array) ($d['me'] ?? []);
$pa = $d['prealert'] ?? null;
$rates = (array) ($d['rates'] ?? []);
$unit = (string) ($rates['weight'] ?? 'lbs');
$addr = (array) ($d['myAddresses'] ?? []);
$opts = function (array $rows, $cur): string {
    $o = '';
    foreach ($rows as $r) {
        $id = (int) (is_array($r) ? $r['id'] : $r->id);
        $lab = is_array($r) ? ($r['label'] ?? '') : ($r->label ?? '');
        $o .= '<option value="' . $id . '"' . ($id === (int) $cur ? ' selected' : '') . '>' . h(str_replace('_', ' ', (string) $lab)) . '</option>';
    }
    return $o;
};
$abbr = fn(string $s) => strtoupper(mb_substr(preg_replace('/[^A-Za-z0-9]/', '', $s) ?: '?', 0, 4));
$courierCur = (int) ($pa['courier_id'] ?? ($df['order_courier'] ?? 0));
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-cust.css')) ?>">
<form id="pns-form" action="<?= h($d['endpoint'] ?? 'ajax/courier/add_courier_client_ajax.php') ?>" method="post" enctype="multipart/form-data" novalidate class="pc-form"
  data-rates="<?= h(json_encode($rates)) ?>" data-uid="<?= (int) ($me['id'] ?? 0) ?>" data-detail="<?= h(sl_route('ShipmentDetail')) ?>" data-new="courier_add_client.php">
<header class="mx-top"><a class="mx-ib" href="<?= h(sl_route('MyParcels')) ?>" data-pns-back aria-label="Back"><i class="ic i-left" aria-hidden="true"></i></a><div class="mx-ttl"><h1>Create shipment</h1></div><a class="mx-ib" href="support.php" aria-label="Get help"><i class="ic i-help" aria-hidden="true"></i></a></header>
<main class="mx-body"><?php require_once CDP_ROOT . '/helpers/sl_setup_checks.php'; if (!sl_setupHasPaymentMethod()): ?><div class="mx-ban warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>Booking is not open yet: we have not switched on a way to pay. <a class="mx-lnk" href="support.php">Message us</a> and we will book it for you.</span></div><?php endif; ?>
<ol class="b1-steps" aria-label="Step 1 of 3" data-pns-steps><li class="cur"><i></i>1 · Addresses</li><li class=""><i></i>2 · Items</li><li class=""><i></i>3 · Price</li></ol>

<input type="hidden" name="quick_load_v2" value="1">
<input type="hidden" name="code_prefix" value="<?= h($d['prefix'] ?? '') ?>"><input type="hidden" name="order_no" value="<?= h($d['nextNo'] ?? '') ?>">
<input type="hidden" name="status_courier" value="<?= (int) ($df['status_courier'] ?? 0) ?>"><input type="hidden" name="price_lb" value="<?= h($rates['price_lb'] ?? 0) ?>">
<input type="hidden" name="sender_id" value="<?= (int) ($me['id'] ?? 0) ?>">
<input type="hidden" name="recipient_type" value="recipient" data-pns-rtype>
<input type="hidden" name="prealert_id" value="<?= (int) ($pa['id'] ?? 0) ?>">
<input type="hidden" name="order_item_category" value="<?= (int) ($df['order_item_category'] ?? 26) ?>">
<input type="hidden" name="agency" value="<?= (int) ($df['agency'] ?? 0) ?>"><input type="hidden" name="origin_off" value="<?= (int) ($df['origin_off'] ?? 0) ?>"><input type="hidden" name="order_payment_method" value="<?= (int) ($df['order_payment_method'] ?? 0) ?>"><input type="hidden" name="order_deli_time" value="<?= (int) ($df['order_deli_time'] ?? 0) ?>"><input type="hidden" name="order_date" value="<?= h($d['today'] ?? date('Y-m-d')) ?>"><input type="hidden" name="estimated_eta" value="">
<input type="hidden" name="driver_id" value="0">
<input type="hidden" name="insured_value" value="0"><input type="hidden" name="reexpedicion_value" value="0"><input type="hidden" name="discount_value" value="0"><input type="hidden" name="discount_type" value="amount">
<input type="hidden" name="tax_value" value="0"><input type="hidden" name="insurance_value" value="<?= h($rates['insurance'] ?? 0) ?>"><input type="hidden" name="tariffs_value" value="0"><input type="hidden" name="declared_value_tax" value="0"><input type="hidden" name="distance_miles" value="0">
<input type="hidden" name="packages" value="[]" data-pns-packages>
<textarea name="tracking_number" hidden data-pns-track-value><?= h($pa['tracking'] ?? '') ?></textarea>

<!-- Step 1 · addresses (M-CShip1) -->
<div class="pc-step" data-pns-step="1">
<section class="b1-card"><div class="b1-al">Pickup address</div>
<div class="b1-af"><i class="ic i-pin" aria-hidden="true"></i><span><b data-pns-from><?= h($addr[0]['label'] ?? 'No address saved yet') ?></b><small>Sender · <?= h($me['name'] ?? '') ?><?= !empty($me['locker']) ? ' · ' . h($me['locker']) : '' ?></small></span></div>
<div class="b1-al">Delivery address</div>
<div class="b1-af"><i class="ic i-pin" aria-hidden="true"></i><span><b data-pns-to>Pick the recipient below</b><small data-pns-to-who>Recipient</small></span></div></section>

<div class="b1-shd"><h2>Sender and recipient</h2></div>
<section class="b1-card"><div class="b1-ah"><span><?= h($me['ini'] ?? '') ?></span><div><b><?= h($me['name'] ?? '') ?><?= !empty($me['locker']) ? ' · ' . h($me['locker']) : '' ?></b><small>Sender · that’s you, fixed for your account</small></div><span class="b1-tag">Locked</span></div>
<label class="b1-dd"><small>Sender address</small><div><select name="sender_address_id" data-pns-saddr required><?php if ($addr): ?><?= $opts($addr, 0) ?><?php else: ?><option value="">Add an address in My account first</option><?php endif; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></div></label>
<small class="mx-note">Only addresses saved in your account appear here. <a class="mx-lnk" style="min-height:0" href="<?= h(sl_route('UserHome')) ?>">Add an address in My account</a></small>
<div class="b1-dd"><small>Recipient</small><div><select name="recipient_id" data-pns-recip required aria-label="Recipient"><option value="">Loading your recipients…</option></select><a class="b1-lk amb" href="recipients_add.php"><i class="ic ic-sm i-plus" aria-hidden="true"></i> New</a></div></div>
<label class="b1-dd"><small>Delivery address</small><div><select name="recipient_address_id" data-pns-raddr required><option value="">Pick the recipient first</option></select><i class="ic ic-sm i-down" aria-hidden="true"></i></div></label>
</section>

<div class="b1-shd"><h2>Package information</h2></div>
<section class="b1-card">
<label class="b1-dd"><small>Package type</small><div><select name="order_package"><?= $opts((array) ($d['packaging'] ?? []), $df['order_package'] ?? 0) ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></div></label>
<label class="b1-dd"><small>Original total weight (<?= h($unit) ?>)</small><div><input name="package_total_weight" inputmode="decimal" placeholder="As on the shop receipt" autocomplete="off" data-pns-calc><i class="ic ic-sm i-edit" aria-hidden="true"></i></div></label>
</section>

<?php if (!empty($d['services'])): ?>
<div class="b1-shd"><h2>Select service</h2><a href="<?= h(sl_route('QuoteCalculator')) ?>">Compare</a></div>
<div class="b1-svc" role="radiogroup" aria-label="Service" data-pns-radio="order_service_options">
<?php foreach ($d['services'] as $s): $on = (int) $s->id === (int) ($df['order_service_options'] ?? 0); $lab = str_replace('_', ' ', (string) $s->label); ?>
<button type="button" class="b1-sc<?= $on ? ' on' : '' ?>" role="radio" aria-checked="<?= $on ? 'true' : 'false' ?>" data-v="<?= (int) $s->id ?>"><i class="b1-rd" aria-hidden="true"></i><span class="b1-scb"><i class="ic i-<?= stripos($lab, 'sea') !== false || stripos($lab, 'ocean') !== false ? 'ship' : 'plane' ?>" aria-hidden="true"></i></span><b><?= h($lab) ?></b></button>
<?php endforeach; ?>
</div>
<?php endif; ?>
<input type="hidden" name="order_service_options" value="<?= (int) ($df['order_service_options'] ?? 0) ?>">
<p class="mx-note">We weigh every parcel at the warehouse, so the final price may change a little.</p>
</div>

<!-- Step 2 · items (M-CShip2) -->
<div class="pc-step" data-pns-step="2" hidden>
<?php if (!empty($d['couriers'])): ?>
<div class="b1-shd"><h2>Select courier</h2><a href="support.php">Not listed?</a></div>
<div class="b1-svc" role="radiogroup" aria-label="Courier company" data-pns-radio="order_courier">
<?php foreach ($d['couriers'] as $c): $on = (int) $c->id === $courierCur; ?>
<button type="button" class="b1-sc cr<?= $on ? ' on' : '' ?>" role="radio" aria-checked="<?= $on ? 'true' : 'false' ?>" data-v="<?= (int) $c->id ?>"><i class="b1-rd" aria-hidden="true"></i><span class="b1-scb"><?= h($abbr((string) $c->label)) ?></span><b><?= h(str_replace('_', ' ', (string) $c->label)) ?></b></button>
<?php endforeach; ?>
</div>
<p class="mx-note">The company bringing your parcel to our warehouse.</p>
<?php endif; ?>
<input type="hidden" name="order_courier" value="<?= $courierCur ?>">
<section class="b1-card">
<div class="b1-dd"><small id="pns-track-l">Tracking numbers · one per box</small><div class="b1-chipin" style="padding:8px 8px 8px 10px;min-height:56px" data-pns-chips role="group" aria-labelledby="pns-track-l"><input data-pns-track-input class="mono" placeholder="Enter or scan" autocomplete="off" autocapitalize="characters" enterkeyhint="enter" aria-label="Add a tracking number"><button type="button" class="pc-chipadd" data-pns-track-add aria-label="Add this tracking number"><i class="ic ic-sm i-plus" aria-hidden="true"></i></button></div></div>
<label class="b1-dd"><small>Payment method</small><div><select name="order_pay_mode"><?= !empty($d['payModes']) ? $opts((array) $d['payModes'], $df['order_pay_mode'] ?? 0) : '<option value="">No payment method is open yet</option>' ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></div></label>
</section>
<input type="hidden" name="is_dangerous_good" value="0">
<label class="b1-tick" data-pns-danger-box><input type="checkbox" name="is_dangerous_good" value="1" class="pc-vh" data-pns-danger><i aria-hidden="true"><i class="ic ic-sm i-check" aria-hidden="true"></i></i><span><b>Contains dangerous goods</b>Batteries on their own, perfume, aerosols, nail polish, power banks. Tick it so we route it safely; batteries inside a device are fine.</span></label>

<div class="b1-shd"><h2>Items</h2><a href="#pns-add" data-pns-focus-add>+ Add item</a></div>
<section class="b1-tb">
<div class="b1-tr" style="grid-template-columns:34px 1fr 54px 76px 36px"><span>Qty</span><span>Description</span><span>Weight</span><span class="r">Declared</span><span class="r"></span></div>
<div data-pns-rows></div>
<div class="b1-tr tot" style="grid-template-columns:34px 1fr 54px 76px 36px"><span data-pns-tq>0</span><span>Total</span><span class="r" data-pns-tw>0</span><span class="r" data-pns-td>—</span><span></span></div>
</section>
<section class="b1-card" id="pns-add" aria-label="Add an item">
<label class="b1-dd"><small>What is inside?</small><div><input data-pns-i="description" placeholder="e.g. Bluetooth speaker" autocomplete="off" value="<?= h($pa['desc'] ?? '') ?>"></div></label>
<div class="mx-two"><label class="b1-dd"><small>Qty</small><div><input data-pns-i="qty" value="1" inputmode="numeric" autocomplete="off"></div></label><label class="b1-dd"><small>Weight (<?= h($unit) ?>)</small><div><input data-pns-i="weight" inputmode="decimal" placeholder="0.0" autocomplete="off"></div></label></div>
<label class="b1-dd"><small>Declared value</small><div><input data-pns-i="declared_value" inputmode="decimal" placeholder="0.00" autocomplete="off" value="<?= $pa && ($pa['price'] ?? 0) > 0 ? h(number_format((float) $pa['price'], 2, '.', '')) : '' ?>"></div></label>
<button type="button" class="b1-wht" data-pns-additem><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add item</button>
</section>

<div class="b1-shd"><h2>Photos and video</h2></div>
<div class="b1-thumbs">
<div class="add"><label class="pc-fill"><i class="ic i-upload" aria-hidden="true"></i><small>Files</small><input type="file" name="filesMultiple[]" multiple accept="image/*,application/pdf" hidden data-pns-files></label></div>
<div class="add"><label class="pc-fill"><i class="ic i-image" aria-hidden="true"></i><small>Take photo</small><input type="file" name="filesMultiple[]" accept="image/*" capture="environment" hidden data-pns-files></label></div>
<div class="add"><label class="pc-fill"><i class="ic i-play" aria-hidden="true"></i><small>Video 60 s</small><input type="file" name="filesVideo[]" accept="video/*" capture="environment" hidden data-pns-files></label></div>
</div>
<p class="mx-note" data-pns-fname>Invoices, receipts or photos, up to 10 MB each.</p>
<label class="b1-dd"><small>Notes for the warehouse</small><div><textarea name="courier_notes" rows="2" placeholder="Anything we should know" class="pc-ta"></textarea></div></label>
</div>

<!-- Step 3 · price (M-CShip3) -->
<div class="pc-step" data-pns-step="3" hidden>
<section class="b1-card"><div class="b1-oh"><div><b class="b1-oid" style="font-size:20px">New shipment</b><span class="b1-rt" data-pns-route>—</span></div><span class="b1-st" data-pns-svc>—</span></div>
<div class="b1-grid3"><div><small>Items</small><b data-pns-sum-items>0</b></div><div><small>Courier</small><b data-pns-sum-courier>—</b></div><div><small>Recipient</small><b data-pns-sum-recip>—</b></div></div></section>
<div class="b1-shd"><h2>Price</h2></div>
<section class="mx-card mx-kv"><div><span>Chargeable weight</span><b data-q="w">0</b></div><div><span>Price per <?= h($unit) ?></span><b data-q="rate"><?= h(sl_money($rates['price_lb'] ?? 0)) ?></b></div><div><span>Freight</span><b data-q="freight">—</b></div><div><span>Declared value</span><b data-q="decl">—</b></div><div><span>Total</span><b data-q="total">—</b></div><?php if ((float) ($rates['rate'] ?? 0) > 0): ?><div><span>Total in cedis</span><b data-q="ghs">—</b></div><?php endif; ?></section>
<div class="b1-ins"><span><i class="ic ic-sm i-info" aria-hidden="true"></i></span><span>Final price is confirmed on save and again <b>when the parcel is weighed</b> at our warehouse.</span></div>
<p class="mx-note">Nothing to pay now. When the price is final it appears in My bills.</p>
<button type="submit" class="b1-wht" data-pns-next><i class="ic ic-sm i-plus" aria-hidden="true"></i>Save and add next</button>
</div>
</main>
<div class="mx-foot"><div class="b1-bar"><span><small>Total cost</small><b><span data-q="total">—</span><em>est.</em></b></span><button type="button" class="b1-amb" data-pns-go>Next: items<i class="ic ic-sm i-right" aria-hidden="true"></i></button><button type="submit" class="b1-amb" data-pns-book hidden>Book shipment</button></div></div>
</form>
