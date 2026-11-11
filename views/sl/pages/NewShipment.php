<?php
/**
 * NewShipment - from docs/handover/artifact-source/NewShipment.dc.html.
 * Data: views/sl/data/shipments.php (sl_newShipmentData).
 * Posts to the legacy endpoint (ajax/courier/add_courier_ajax.php, or
 * add_courier_client_ajax.php for customers) with its own field names, so
 * pricing, numbering, notifications, history and audit stay in one place.
 * Client behaviour: dataJs/sl/new_shipment.js.
 */
if (!isset($d)) { $d = []; }
$df = array_merge($d['defaults'], array_intersect_key($d['prefs'], $d['defaults']));
$client = $d['client'];
$sel = function (string $name, array $rows, string $labelKey, $cur, string $empty = '') {
    $o = $empty !== '' ? '<option value="">' . h($empty) . '</option>' : '';
    foreach ($rows as $r) {
        $id = (int) (is_array($r) ? $r['id'] : $r->id);
        $lab = is_array($r) ? $r[$labelKey] : ($r->$labelKey ?? '');
        $o .= '<option value="' . $id . '"' . ($id === (int) $cur ? ' selected' : '') . '>' . h(str_replace('_', ' ', (string) $lab)) . '</option>';
    }
    return '<span class="sl-sel native"><select name="' . h($name) . '" aria-label="' . h($name) . '">' . $o . '</select><i class="ic ic-sm i-down" aria-hidden="true"></i></span>';
};
$lab = fn($rows, $id, $key) => (function () use ($rows, $id, $key) { foreach ($rows as $r) { if ((int) $r->id === (int) $id) return str_replace('_', ' ', (string) $r->$key); } return '—'; })();
$pa = $d['prealert'];
?>
<form id="ns-form" class="col g16" action="<?= h($d['endpoint']) ?>" method="post" enctype="multipart/form-data" novalidate
  data-client="<?= $client ? '1' : '0' ?>" data-prefix="<?= h($d['prefix']) ?>" data-rates="<?= h(json_encode($d['rates'])) ?>" data-detail="<?= h(sl_route('ShipmentDetail')) ?>" data-new="<?= h(sl_self()) ?>">
        <input type="hidden" name="quick_load_v2" value="1">
        <div class="sl-head">
          <div><a href="<?= sl_route('AirShipments') ?>" class="row g6 mut sm"><i class="ic ic-sm i-left" aria-hidden="true"></i><?= $client ? 'My Shipments' : 'Air Shipments' ?></a><h1 class="sl-title" style="margin-top: 4px">New Shipment</h1></div>
          <div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('AirShipments') ?>">Cancel</a><button type="submit" class="sl-btn ghost" data-ns-next><i class="ic ic-sm i-plus" aria-hidden="true"></i>Save and add next</button><button type="submit" class="sl-btn">Create shipment</button></div>
        </div>
        <section class="sl-card ns-number"><div><span class="sl-lab">Shipment number</span><strong class="ns-number-value"><span data-ns-prefix><?= h($d['prefix']) ?></span><span data-ns-number><?= h($d['nextNo']) ?></span></strong><span class="sl-hint">Reserved when you save. The next free number is used if this one is taken.</span></div><?php if (!$client): ?><button type="button" class="sl-btn ghost sm" data-ns-number-change>Change country</button><div class="ns-number-edit" hidden><label class="sl-field"><span class="sl-lab">Number country</span><span class="sl-sel native"><select name="number_country" data-ns-country><option value="">Default</option><?php foreach ($d['numberCountries'] as $country): ?><option value="<?= h($country->iso2) ?>"><?= h($country->name) ?></option><?php endforeach; ?></select></span></label></div><?php endif; ?><input type="hidden" name="code_prefix" value="<?= h($d['prefix']) ?>"><input type="hidden" name="order_no" value="<?= h($d['nextNo']) ?>"></section>
<?php if (!$client): ?>
<section class="sl-card col g12 nd-bar">
          <div class="row between wrap g8"><div class="row g10"><span class="sl-tile"><i class="ic i-bolt" aria-hidden="true"></i></span><div class="col"><b class="fw5">Quick load with defaults</b><span class="mut sm">Carrier and status are ready. Adjust them below for this shipment.</span></div></div><div class="row g8 wrap"><button type="button" class="sl-btn ghost sm" data-ns-edit><i class="ic ic-sm i-sliders" aria-hidden="true"></i>Edit defaults</button></div></div>
          <div class="row wrap g6">
            <span class="nd-chip"><span class="mut">Carrier</span><?= h($lab($d['couriers'], $df['order_courier'], 'label')) ?></span>
            <span class="nd-chip"><span class="mut">Status</span><?= h($lab(array_map(fn($s) => (object) ['id' => $s->id, 'label' => $s->mod_style], $d['statuses']), $df['status_courier'], 'label')) ?></span>
          </div>
          <div class="row g8 sm nd-done" style="display:none" data-ns-saved><i class="ic ic-sm i-check" aria-hidden="true"></i>Saved as your defaults for the next shipment.</div>
        </section>
<?php endif; ?>
        <div class="split">
          <div class="a col g16">
            <section class="sl-card col g16">
              <div class="row between"><h2 class="sl-ct">Sender and recipient</h2><span class="sl-badge">Step 1 of 4</span></div>
              <div class="grid cols2">
                <div class="col g12 ns-party">
<?php if ($client): ?>
                  <input type="hidden" name="sender_id" value="<?= (int) $d['me']['id'] ?>">
                  <div class="sl-field"><span class="sl-lab">Sender</span><div class="sl-sel"><span class="row g8"><span class="sl-av sm"><?= h($d['me']['ini']) ?></span><?= h($d['me']['name']) ?><?php if ($d['me']['locker']): ?><span class="sl-badge mono" title="Locker ID"><?= h($d['me']['locker']) ?></span><?php endif; ?></span></div></div>
                  <label class="sl-field"><span class="sl-lab">Sender address</span><span class="sl-sel native"><select name="sender_address_id" data-ns-saddr required><?php foreach ($d['myAddresses'] as $a): ?><option value="<?= (int) $a['id'] ?>"><?= h($a['label']) ?></option><?php endforeach; ?><?php if (!$d['myAddresses']): ?><option value="">Add an address in My account first</option><?php endif; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
<?php else: ?>
                  <label class="sl-field"><span class="sl-lab">Locker ID or sender name</span><div class="row g6"><span class="sl-search" style="flex:1;max-width:none"><i class="ic ic-sm i-lock" aria-hidden="true"></i><input aria-label="Locker ID or sender name" data-ns-find placeholder="Locker ID, name, phone or e-mail" value="<?= h($pa['locker'] ?? '') ?>" autocomplete="off"></span><button type="button" class="sl-btn ghost sm" style="height:40px" data-ns-findbtn><i class="ic ic-sm i-search" aria-hidden="true"></i>Find</button></div></label>
                  <div class="ns-party-summary" data-ns-sender-summary>Search for a sender to load their name and address.</div><button type="button" class="sl-btn ghost sm" data-ns-change-sender hidden>Change sender or address</button>
                  <div data-ns-sender-fields hidden><label class="sl-field"><span class="sl-lab">Sender</span><span class="sl-sel native"><select name="sender_id" data-ns-sender required><?php if ($pa): ?><option value="<?= (int) $pa['customer_id'] ?>" selected><?= h($pa['customer']) ?></option><?php else: ?><option value="">Search above to pick a customer</option><?php endif; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label><label class="sl-field"><span class="sl-lab">Sender address</span><span class="sl-sel native"><select name="sender_address_id" data-ns-saddr required><option value="">Pick the sender first</option></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label></div>
<?php endif; ?>
                </div>
                <div class="col g12 ns-party recipient">
                  <input type="hidden" name="recipient_type" value="recipient" data-ns-rtype>
                  <label class="sl-field"><span class="sl-lab">Recipient</span><span class="sl-sel native"><select name="recipient_id" data-ns-recip required><option value="">Pick the sender first</option></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
                  <select name="recipient_address_id" data-ns-raddr required hidden><option value="">Pick the recipient first</option></select>
                </div>
              </div>
              <div class="ns-notify"><i class="ic ic-sm i-bell" aria-hidden="true"></i><span class="fw5 sm">Notify</span><label class="nt-ch <?= $d['notify']['whatsapp'] ? 'on' : '' ?>" title="WhatsApp sender"><input type="checkbox" name="notify_whatsapp_sender" value="1" <?= $d['notify']['whatsapp'] && ($d['prefs']['notify_whatsapp_sender'] ?? 1) ? 'checked' : '' ?> hidden data-ns-chip><i class="ic ic-sm i-message" aria-hidden="true"></i>WhatsApp</label><span class="nt-ch on" title="SMS sender"><i class="ic ic-sm i-phone" aria-hidden="true"></i>SMS</span><span class="nt-ch on" title="E-mail sender"><i class="ic ic-sm i-mail" aria-hidden="true"></i>E-mail</span><label class="nt-ch <?= !empty($d['prefs']['notify_sms_receiver']) ? 'on' : '' ?>" title="SMS recipient"><input type="checkbox" name="notify_sms_receiver" value="1" <?= !empty($d['prefs']['notify_sms_receiver']) ? 'checked' : '' ?> hidden data-ns-chip><i class="ic ic-sm i-user" aria-hidden="true"></i>Recipient</label><?php if (!$client): ?><details class="ns-notify-settings"><summary class="sl-ib sm" title="Notification defaults" aria-label="Notification defaults"><i class="ic ic-sm i-cog" aria-hidden="true"></i></summary><div class="ns-notify-pop"><b>Notification defaults</b><p class="mut sm">Use the buttons here for this shipment. Save their state for the next one.</p><button type="button" class="sl-btn ghost sm" data-ns-savedef>Save defaults</button><?php if ($user->cdp_hasPermission('view_tools')): ?><a class="sm fw5" href="<?= sl_route('NotificationCenter') ?>">All notification settings →</a><?php endif; ?></div></details><?php endif; ?></div>
<?php if (!$client): ?><div class="row g12 wrap sm"><a href="<?= sl_route('AddCustomer') ?>" class="fw5 row g6" target="_blank"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New customer</a><a href="<?= sl_route('Recipients') ?>" class="fw5 row g6" target="_blank"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New recipient</a></div><?php endif; ?>
<?php if (!$client): ?>
              <label class="sl-field"><span class="sl-lab">Linked pre-alert <span class="mut">(optional)</span></span><span class="sl-sel native"><select name="prealert_id" data-ns-prealert><option value="">None</option><?php if ($pa): ?><option value="<?= (int) $pa['id'] ?>" data-pa="<?= h(json_encode($pa)) ?>" selected><?= h($pa['tracking'] . ' · ' . $pa['customer']) ?></option><?php endif; ?><?php foreach ($d['prealerts'] as $p): if ($pa && $p['id'] === $pa['id']) continue; ?><option value="<?= (int) $p['id'] ?>" data-pa="<?= h(json_encode($p)) ?>"><?= h($p['label']) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
<?php endif; ?>
            </section>

            <section class="sl-card col g16">
              <div class="row between"><h2 class="sl-ct">Shipment details</h2><span class="sl-badge">Step 2 of 4</span></div>
              <input type="hidden" name="order_item_category" value="<?= (int) $df['order_item_category'] ?>">
              <p class="mut sm">Air shipment · received at office</p>
              <div class="grid cols3">
                <input type="hidden" name="agency" value="<?= (int) $df['agency'] ?>"><input type="hidden" name="origin_off" value="<?= (int) $df['origin_off'] ?>"><input type="hidden" name="order_package" value="<?= (int) $df['order_package'] ?>"><input type="hidden" name="order_payment_method" value="<?= (int) $df['order_payment_method'] ?>"><input type="hidden" name="order_deli_time" value="<?= (int) $df['order_deli_time'] ?>"><input type="hidden" name="order_service_options" value="<?= (int) $df['order_service_options'] ?>"><input type="hidden" name="order_date" value="<?= h($d['today']) ?>"><input type="hidden" name="estimated_eta" value="">
                <?php if (!$client): ?><label class="sl-field"><span class="sl-lab">Status</span><?= $sel('status_courier', $d['statuses'], 'mod_style', $df['status_courier']) ?></label><?php else: ?><input type="hidden" name="status_courier" value="<?= (int) $df['status_courier'] ?>"><?php endif; ?>
                <?php if (!$client): ?><input type="hidden" name="tariff_mode" value="1">
                <label class="sl-field"><span class="sl-lab">Price per <?= h($d['rates']['weight']) ?></span><span class="row g6"><input class="sl-in mono" name="price_lb" value="<?= h(number_format($d['rates']['price_lb'], 2, '.', '')) ?>" inputmode="decimal" aria-label="Price per weight unit" data-ns-calc readonly><button type="button" class="sl-ib" data-ns-edit-rate aria-label="Edit price per weight unit" title="Edit price"><i class="ic ic-sm i-edit" aria-hidden="true"></i></button></span></label><?php else: ?><input type="hidden" name="price_lb" value="<?= h($d['rates']['price_lb']) ?>"><?php endif; ?>
                <label class="sl-field"><span class="sl-lab">Courier company</span><?= $sel('order_courier', $d['couriers'], 'label', $pa['courier_id'] ?? $df['order_courier']) ?></label>
                <div class="sl-field"><label class="sl-lab" for="ns-t">Carrier tracking numbers</label><textarea id="ns-t" class="sl-in mono" name="tracking_number" rows="2" style="height:auto;padding:10px" placeholder="One per line or separated by commas"><?= h($pa['tracking'] ?? '') ?></textarea></div>
                <?php if ($client): ?><label class="sl-field"><span class="sl-lab">Payment method</span><?= $sel('order_pay_mode', $d['payModes'], 'label', $df['order_pay_mode']) ?></label><?php endif; ?>
                <?php if (!$client): ?><label class="sl-field"><span class="sl-lab">Assign rider</span><span class="sl-sel native"><select name="driver_id" aria-label="Rider"><option value="0">Unassigned</option><?php foreach ($d['drivers'] as $dr): ?><option value="<?= (int) $dr->id ?>"><?= h(trim($dr->fname . ' ' . $dr->lname)) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label><?php endif; ?>
              </div>
              <div class="row between wrap g12"><label class="row g8" style="cursor:pointer"><span class="sl-toggle" role="switch" aria-checked="false" aria-label="Contains dangerous goods"><input type="hidden" name="is_dangerous_good" value="0"></span>Contains dangerous goods</label><span class="sl-hint">Batteries, liquids and aerosols need an approved declaration.</span></div>
            </section>

            <section class="sl-card flush">
              <div class="row between" style="padding: 16px 20px"><div class="row g12"><h2 class="sl-ct">Items</h2><span class="sl-badge">Step 3 of 4</span></div><button type="button" class="sl-btn ghost sm" data-ns-add><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add item</button></div>
              <div class="sl-tw"><table class="sl-table">
                <thead><tr><th style="width:80px">Qty</th><th>Description</th><th class="num" style="width:110px">Weight (<?= h($d['rates']['weight']) ?>)</th><?php if (!$client): ?><th class="num" style="width:120px">Custom price</th><?php endif; ?><th class="num" style="width:130px">Declared value</th><th style="width:48px"></th></tr></thead>
                <tbody data-ns-items>
                  <tr data-ns-item><td><input class="sl-in mono" data-k="qty" value="1" inputmode="numeric" aria-label="Quantity"></td><td><input class="sl-in" data-k="description" placeholder="What is inside" aria-label="Description" value="<?= h($pa['desc'] ?? '') ?>"></td><td class="num"><input class="sl-in mono" data-k="weight" inputmode="decimal" placeholder="0.0" aria-label="Weight"></td><?php if (!$client): ?><td class="num"><input class="sl-in mono" data-k="custom_price" inputmode="decimal" placeholder="—" aria-label="Custom price" title="Price this item by a fixed amount instead of by weight"></td><?php endif; ?><td class="num"><input class="sl-in mono" data-k="declared_value" inputmode="decimal" placeholder="0.00" aria-label="Declared value" value="<?= $pa && $pa['price'] > 0 ? h(number_format($pa['price'], 2, '.', '')) : '' ?>"></td><td><button type="button" class="sl-ib sm" aria-label="Remove item" data-ns-rm><i class="ic i-trash" aria-hidden="true"></i></button></td></tr>
                </tbody></table></div>
              <div class="row between wrap g12" style="padding: 14px 20px; border-top: 1px solid var(--line)"><label class="row g8 sm"><span class="mut">Original total weight</span><input class="sl-in mono" name="package_total_weight" style="width:110px" inputmode="decimal" placeholder="auto" aria-label="Original total weight" data-ns-calc></label><span class="fw5" data-ns-wsum>0 <?= h($d['rates']['weight']) ?> · 0 pieces</span></div>
              <input type="hidden" name="packages" value="[]" data-ns-packages>
            </section>

            <section class="sl-card col g16">
              <div class="row between"><h2 class="sl-ct">Photos and documents</h2><span class="sl-badge">Step 4 of 4</span></div>
              <div class="grid cols2">
                <label class="sl-drop" style="cursor:pointer"><i class="ic ic-lg i-upload" aria-hidden="true"></i><span class="fw5" style="color: var(--ink)">Drop files or browse</span><span class="sm" data-ns-fname>Invoices, receipts, up to 10 MB each</span><input type="file" name="filesMultiple[]" multiple accept="image/*,application/pdf" hidden data-ns-files></label>
                <label class="sl-drop" style="cursor:pointer"><i class="ic ic-lg i-image" aria-hidden="true"></i><span class="fw5" style="color: var(--ink)">Take a photo</span><span class="sm">Capture the parcel with your camera</span><input type="file" name="filesMultiple[]" accept="image/*" capture="environment" hidden data-ns-files></label>
                <label class="sl-drop" style="cursor:pointer"><i class="ic ic-lg i-play" aria-hidden="true"></i><span class="fw5" style="color: var(--ink)">Record a video</span><span class="sm">Show the parcel's condition, up to 60 s</span><input type="file" name="filesVideo[]" accept="video/*" capture="environment" hidden data-ns-files></label>
                <div class="sl-drop"><i class="ic ic-lg i-scan" aria-hidden="true"></i><span class="fw5" style="color: var(--ink)">Photo of each item</span><span class="sm">Add photos above; they are kept with the shipment</span></div>
              </div>
              <label class="sl-field"><span class="sl-lab">Notes</span><textarea class="sl-in" name="courier_notes" style="height:84px;padding:10px 12px" aria-label="Notes" placeholder="Condition of the box, anything the next person should know"></textarea></label>
            </section>
          </div>

          <aside class="b col g16" aria-label="Quote summary">
            <section class="sl-card col g12" style="position:sticky;top:12px">
              <h2 class="sl-ct">Quote summary</h2>
              <div class="sl-kv">
                <div><span>Chargeable weight</span><span data-q="w">0</span></div>
                <div><span>Price per <?= h($d['rates']['weight']) ?></span><span data-q="rate"><?= h(sl_money($d['rates']['price_lb'])) ?></span></div>
                <div><span>Freight</span><span data-q="freight">—</span></div>
                <div><span>Declared value</span><span data-q="decl">—</span></div>
                <?php if (!$client): ?><div><span>Insurance · <?= $d['rates']['insurance_mode'] === 'fixed' ? h(sl_money($d['rates']['insurance_fixed'])) . ' fixed' : h($d['rates']['insurance']) . '%' ?></span><span class="row g6"><input class="sl-in mono" name="insured_value" value="0" style="width:90px;height:28px" inputmode="decimal" aria-label="Insured value" data-ns-calc><span data-q="ins">—</span></span></div>
                <div><span>Re-shipping fee</span><input class="sl-in mono" name="reexpedicion_value" value="0" style="width:90px;height:28px" inputmode="decimal" aria-label="Re-shipping fee" data-ns-calc></div><?php else: ?><input type="hidden" name="insured_value" value="0"><input type="hidden" name="reexpedicion_value" value="0"><?php endif; ?>
                <?php if (!$client): ?><div><span>Discount</span><span class="row g6"><span class="sl-seg boxed" style="height:26px" data-ns-dtype><button type="button" class="on" data-v="amount" style="height:22px;padding:0 8px">$</button><button type="button" data-v="percent" style="height:22px;padding:0 8px">%</button></span><input class="sl-in mono" name="discount_value" value="0" style="width:70px;height:28px" inputmode="decimal" aria-label="Discount" data-ns-calc></span></div><input type="hidden" name="discount_type" value="amount"><?php else: ?><input type="hidden" name="discount_value" value="0"><?php endif; ?>
              </div>
              <input type="hidden" name="tax_value" value="0"><input type="hidden" name="insurance_value" value="<?= h($d['rates']['insurance']) ?>"><input type="hidden" name="tariffs_value" value="0"><input type="hidden" name="declared_value_tax" value="0"><input type="hidden" name="distance_miles" value="0">
              <div class="row between" style="padding-top: 6px"><span class="fw5">Total due</span><span class="big" data-q="total">—</span></div>
              <?php if ($d['rates']['rate'] > 0): ?><div class="row between sm mut"><span>In cedis (rate <?= h(number_format($d['rates']['rate'], 2)) ?>)</span><span data-q="ghs">—</span></div><?php endif; ?>
              <div class="sm mut" data-q="note">Final price is confirmed on save<?= $client ? ' and when the parcel is weighed' : '' ?>.</div>
              <button type="submit" class="sl-btn block">Create shipment</button>
            </section>
          </aside>
        </div>
</form>
