<?php
/**
 * NewShipment - from docs/handover/artifact-source/NewShipment.dc.html.
 * Data: views/sl/data/shipments.php (sl_newShipmentData).
 * Posts to the legacy endpoint (ajax/courier/add_courier_ajax.php, or
 * add_courier_client_ajax.php for customers) with its own field names, so
 * pricing, numbering, notifications, history and audit stay in one place.
 * Client behaviour: dataJs/sl/new_shipment.js.
 *
 * Layout: the top strip holds the shipment number, country change and quick
 * load. Then four steps: 1 sender and recipient, 2 tracking and items,
 * 3 pricing and insurance (side panel), 4 photos and notes.
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
    return '<span class="sl-sel native"><select name="' . h($name) . '">' . $o . '</select><i class="ic ic-sm i-down" aria-hidden="true"></i></span>';
};
$lab = fn($rows, $id, $key) => (function () use ($rows, $id, $key) { foreach ($rows as $r) { if ((int) $r->id === (int) $id) return str_replace('_', ' ', (string) $r->$key); } return '—'; })();
$pa = $d['prealert'];
$unit = h($d['rates']['weight']);
?>
<?php if ($client): require_once CDP_ROOT . '/helpers/sl_setup_checks.php'; if (!sl_setupHasPaymentMethod()): ?><div class="sl-banner warn" role="status">Booking is not open yet: we have not switched on a way to pay. Please message us and we will book it for you.</div><?php endif; endif; ?>
<form id="ns-form" class="col g16 ns-form" action="<?= h($d['endpoint']) ?>" method="post" enctype="multipart/form-data" novalidate
  data-client="<?= $client ? '1' : '0' ?>" data-prefix="<?= h($d['prefix']) ?>" data-rates="<?= h(json_encode($d['rates'])) ?>" data-detail="<?= h(sl_route('ShipmentDetail')) ?>" data-new="<?= h(sl_self()) ?>" data-labels="<?= h(sl_route('LabelStudio')) ?>">
  <input type="hidden" name="quick_load_v2" value="1">

  <div class="sl-head ns-head">
    <div>
      <a href="<?= sl_route('AirShipments') ?>" class="row g6 mut sm"><i class="ic ic-sm i-left" aria-hidden="true"></i><?= $client ? 'My Shipments' : 'Air Shipments' ?></a>
      <h1 class="sl-title" style="margin-top: 4px">New Shipment</h1>
    </div>
    <div class="row g8 wrap ns-actions">
      <a class="sl-btn ghost" href="<?= sl_route('AirShipments') ?>">Cancel</a>
      <button type="submit" class="sl-btn ghost" data-ns-next>Save and add next</button>
      <button type="submit" class="sl-btn ns-create">Create shipment</button>
    </div>
  </div>

  <!-- Top strip: number, country and quick load -->
  <section class="sl-card ns-top">
    <div class="ns-num">
      <span class="sl-lab">Shipment number</span>
      <strong class="ns-number-value"><span data-ns-prefix><?= h($d['prefix']) ?></span><span data-ns-number><?= h($d['nextNo']) ?></span></strong>
      <span class="sl-hint">Reserved when you save. The next free number is used if this one is taken.</span>
      <?php if (!$client): ?>
      <div class="row g8 wrap ns-country-row">
        <button type="button" class="sl-btn ghost sm" data-ns-number-change aria-expanded="false"><i class="ic ic-sm i-pin" aria-hidden="true"></i>Change country</button>
        <span class="ns-country-tag" data-ns-country-tag hidden></span>
      </div>
      <div class="ns-number-edit" hidden>
        <label class="sl-field"><span class="sl-lab">Number country</span><span class="sl-sel native"><select name="number_country" data-ns-country><option value="">Default</option><?php foreach ($d['numberCountries'] as $country): ?><option value="<?= h($country->iso2) ?>"><?= h($country->name) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
      </div>
      <?php endif; ?>
      <input type="hidden" name="code_prefix" value="<?= h($d['prefix']) ?>">
      <input type="hidden" name="order_no" value="<?= h($d['nextNo']) ?>">
    </div>

    <?php if (!$client): ?>
    <div class="ns-quick">
      <div class="row between wrap g8">
        <span class="fw5"><i class="ic ic-sm i-bolt" aria-hidden="true"></i> Quick load</span>
        <div class="row g8 wrap">
          <button type="button" class="sl-btn ghost sm" data-ns-edit>Edit defaults</button>
          <button type="button" class="sl-btn ghost sm" data-ns-savedef>Save as defaults</button>
        </div>
      </div>
      <input type="hidden" name="tariff_mode" value="1">
      <div class="ns-quick-grid">
        <label class="sl-field"><span class="sl-lab">Status</span><?= $sel('status_courier', $d['statuses'], 'mod_style', $df['status_courier']) ?></label>
        <label class="sl-field"><span class="sl-lab">Price per <?= $unit ?></span>
          <span class="row g6"><input class="sl-in mono" name="price_lb" value="<?= h(number_format($d['rates']['price_lb'], 2, '.', '')) ?>" inputmode="decimal" aria-label="Price per weight unit" data-ns-calc readonly><button type="button" class="sl-ib" data-ns-edit-rate aria-label="Edit price per weight unit" title="Edit price"><i class="ic ic-sm i-edit" aria-hidden="true"></i></button></span>
        </label>
        <div class="sl-field"><span class="sl-lab">Notify</span>
          <div class="ns-notify" role="group" aria-label="Notifications">
            <label class="nt-ch <?= $d['notify']['whatsapp'] ? 'on' : '' ?>" title="Send a WhatsApp message to the sender"><input type="checkbox" class="ns-vh" name="notify_whatsapp_sender" value="1" <?= $d['notify']['whatsapp'] && ($d['prefs']['notify_whatsapp_sender'] ?? 1) ? 'checked' : '' ?> data-ns-chip><i class="ic ic-sm i-message" aria-hidden="true"></i>WhatsApp sender</label>
            <span class="nt-ch on fixed" title="Always sent: text message to the sender"><i class="ic ic-sm i-phone" aria-hidden="true"></i>SMS sender</span>
            <span class="nt-ch on fixed" title="Always sent: e-mail to the sender"><i class="ic ic-sm i-mail" aria-hidden="true"></i>Email sender</span>
            <label class="nt-ch <?= !empty($d['prefs']['notify_sms_receiver']) ? 'on' : '' ?>" title="Text message to the recipient"><input type="checkbox" class="ns-vh" name="notify_sms_receiver" value="1" <?= !empty($d['prefs']['notify_sms_receiver']) ? 'checked' : '' ?> data-ns-chip><i class="ic ic-sm i-user" aria-hidden="true"></i>SMS recipient</label>
          </div>
        </div>
      </div>
      <?php if ($user->cdp_hasPermission('view_tools')): ?><a class="sm fw5 ns-more-link" href="<?= sl_route('NotificationCenter') ?>">All notification settings →</a><?php endif; ?>
      <div class="sm nd-done" style="display:none" data-ns-saved><i class="ic ic-sm i-check" aria-hidden="true"></i>Saved as your defaults for the next shipment.</div>
    </div>
    <?php else: ?>
    <input type="hidden" name="status_courier" value="<?= (int) $df['status_courier'] ?>"><input type="hidden" name="price_lb" value="<?= h($d['rates']['price_lb']) ?>">
    <?php endif; ?>
  </section>

  <div class="split">
    <div class="a col g16">

      <!-- Step 1 -->
      <section class="sl-card col g16 ns-s1">
        <div class="row between"><h2 class="sl-ct">Sender and recipient</h2><span class="sl-badge">Step 1 of 4</span></div>
        <?php if ($client): ?>
        <div class="col g12 ns-party ns-sender">
          <input type="hidden" name="sender_id" value="<?= (int) $d['me']['id'] ?>">
          <div class="sl-field"><span class="sl-lab">Sender</span><div class="ns-selected"><span class="row g8"><span class="sl-av sm"><?= h($d['me']['ini']) ?></span><?= h($d['me']['name']) ?><?php if ($d['me']['locker']): ?><span class="sl-badge mono" title="Locker ID"><?= h($d['me']['locker']) ?></span><?php endif; ?></span></div></div>
          <label class="sl-field"><span class="sl-lab">Sender address</span><span class="sl-sel native"><select name="sender_address_id" data-ns-saddr required><?php foreach ($d['myAddresses'] as $a): ?><option value="<?= (int) $a['id'] ?>"><?= h($a['label']) ?></option><?php endforeach; ?><?php if (!$d['myAddresses']): ?><option value="">Add an address in My account first</option><?php endif; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
        </div>
        <?php else: ?>
        <div class="col g12 ns-party ns-sender">
          <div class="col g8" data-ns-search>
            <label class="sl-field"><span class="sl-lab">Find the sender</span>
              <span class="ns-find"><span class="sl-search grow"><i class="ic ic-sm i-lock" aria-hidden="true"></i><input aria-label="Locker ID, name, phone or e-mail" data-ns-find placeholder="Start typing a name, locker ID or phone" value="<?= h($pa['locker'] ?? '') ?>" autocomplete="off"></span><button type="button" class="sl-btn ghost" data-ns-findbtn>Search</button></span>
            </label>
            <div class="ns-results col" data-ns-results hidden></div>
          </div>
          <div class="col g8" data-ns-sender-fields hidden>
            <div class="ns-selected" data-ns-sender-summary></div>
            <label class="sl-field"><span class="sl-lab">Sender address</span><span class="sl-sel native"><select name="sender_address_id" data-ns-saddr required><option value="">Pick the sender first</option></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
            <div class="row g8 wrap"><button type="button" class="sl-btn ghost sm" data-ns-change-sender>Change customer</button></div>
            <select name="sender_id" data-ns-sender required hidden><?php if ($pa): ?><option value="<?= (int) $pa['customer_id'] ?>" selected><?= h($pa['customer']) ?></option><?php else: ?><option value="">Search above to pick a customer</option><?php endif; ?></select>
          </div>
        </div>
        <?php endif; ?>

        <div class="col g12 ns-party recipient" data-ns-recipient-panel hidden>
          <input type="hidden" name="recipient_type" value="recipient" data-ns-rtype>
          <div class="col g8" data-ns-rec-search>
            <span class="sl-lab">Recipient</span>
            <div class="ns-results col" data-ns-rec-results hidden></div>
            <p class="sm mut" data-ns-rec-empty hidden>No recipients saved for this customer yet.</p>
          </div>
          <div class="col g8" data-ns-recipient-fields hidden>
            <div class="ns-selected" data-ns-recipient-summary></div>
            <label class="sl-field"><span class="sl-lab">Delivery address</span><span class="sl-sel native"><select name="recipient_address_id" data-ns-raddr required><option value="">Pick the recipient first</option></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
            <div class="row g8 wrap"><button type="button" class="sl-btn ghost sm" data-ns-change-recip>Change recipient</button></div>
            <select name="recipient_id" data-ns-recip required hidden><option value="">Pick the sender first</option></select>
          </div>
        </div>
        <input type="hidden" name="prealert_id" value="<?= (int) ($pa['id'] ?? 0) ?>">
        <p class="sm mut" data-ns-prealert-note hidden></p>
      </section>

      <!-- Step 2 -->
      <section class="sl-card col g16 ns-s2">
        <div class="row between"><h2 class="sl-ct">Tracking and items</h2><span class="sl-badge">Step 2 of 4</span></div>
        <input type="hidden" name="order_item_category" value="<?= (int) $df['order_item_category'] ?>">
        <input type="hidden" name="agency" value="<?= (int) $df['agency'] ?>"><input type="hidden" name="origin_off" value="<?= (int) $df['origin_off'] ?>"><input type="hidden" name="order_package" value="<?= (int) $df['order_package'] ?>"><input type="hidden" name="order_payment_method" value="<?= (int) $df['order_payment_method'] ?>"><input type="hidden" name="order_deli_time" value="<?= (int) $df['order_deli_time'] ?>"><input type="hidden" name="order_service_options" value="<?= (int) $df['order_service_options'] ?>"><input type="hidden" name="order_date" value="<?= h($d['today']) ?>"><input type="hidden" name="estimated_eta" value="">
        <input type="hidden" name="driver_id" value="0">

        <div class="ns-row2">
          <label class="sl-field"><span class="sl-lab">Courier company</span><?= $sel('order_courier', $d['couriers'], 'label', $pa['courier_id'] ?? $df['order_courier']) ?></label>
          <?php if ($client): ?><label class="sl-field"><span class="sl-lab">Payment method</span><?= $sel('order_pay_mode', $d['payModes'], 'label', $df['order_pay_mode']) ?></label><?php endif; ?>
        </div>

        <div class="sl-field">
          <span class="sl-lab" id="ns-track-label">Tracking numbers</span>
          <div class="ns-chips" data-ns-track-box role="group" aria-labelledby="ns-track-label">
            <span class="ns-chip-list" data-ns-track-chips role="list" aria-live="polite"></span>
            <input class="ns-chip-input mono" data-ns-track-input placeholder="Scan or type a number, then press Enter" autocomplete="off" enterkeyhint="enter" aria-label="Add a tracking number" aria-describedby="ns-track-hint">
          </div>
          <span class="sl-hint" id="ns-track-hint">Press Enter after each number. A scanner that presses Enter adds it for you. Backspace in the empty box removes the last number.</span>
          <textarea name="tracking_number" data-ns-track-value hidden><?= h($pa['tracking'] ?? '') ?></textarea>
        </div>

        <div class="ns-danger" data-ns-danger-box>
          <input type="hidden" name="is_dangerous_good" value="0">
          <label class="ns-danger-main">
            <input type="checkbox" name="is_dangerous_good" value="1" data-ns-danger class="ns-check">
            <span class="ns-danger-text"><b>Contains dangerous goods</b><span class="sm">Tick this if the parcel holds batteries, liquids, aerosols, or anything flammable or magnetic. It needs an approved declaration before it can fly.</span></span>
          </label>
        </div>

        <div class="ns-total">
          <label class="sl-field ns-ow"><span class="sl-lab">Original total weight (<?= $unit ?>)</span><input class="sl-in mono" name="package_total_weight" inputmode="decimal" placeholder="Auto from items" aria-label="Original total weight" data-ns-calc></label>
          <div class="ns-wsum" data-ns-wsum>0 <?= $unit ?> · 0 pieces</div>
        </div>

        <div class="row between wrap g8"><h3 class="sl-ct">Items</h3><button type="button" class="sl-btn ghost sm" data-ns-add>Add item</button></div>
        <div class="sl-tw"><table class="sl-table ns-items">
          <thead><tr><th style="width:72px">Qty</th><th>Description</th><th class="num" style="width:110px">Weight (<?= $unit ?>)</th><?php if (!$client): ?><th class="num" style="width:120px">Custom price</th><?php endif; ?><th class="num" style="width:130px">Declared value</th><th style="width:48px"></th></tr></thead>
          <tbody data-ns-items>
            <tr data-ns-item><td><input class="sl-in mono" data-k="qty" value="1" inputmode="numeric" aria-label="Quantity"></td><td><input class="sl-in" data-k="description" placeholder="What is inside" aria-label="Description" value="<?= h($pa['desc'] ?? '') ?>"></td><td class="num"><input class="sl-in mono" data-k="weight" inputmode="decimal" placeholder="0.0" aria-label="Weight"></td><?php if (!$client): ?><td class="num"><input class="sl-in mono" data-k="custom_price" inputmode="decimal" placeholder="—" aria-label="Custom price" title="Price this item by a fixed amount instead of by weight"></td><?php endif; ?><td class="num"><input class="sl-in mono" data-k="declared_value" inputmode="decimal" placeholder="0.00" aria-label="Declared value" value="<?= $pa && $pa['price'] > 0 ? h(number_format($pa['price'], 2, '.', '')) : '' ?>"></td><td><button type="button" class="sl-ib sm" aria-label="Remove item" data-ns-rm><i class="ic i-trash" aria-hidden="true"></i></button></td></tr>
          </tbody>
        </table></div>
        <input type="hidden" name="packages" value="[]" data-ns-packages>
      </section>

      <!-- Step 4 (photos and notes) -->
      <section class="sl-card col g16 ns-s4">
        <div class="row between"><h2 class="sl-ct">Photos and notes</h2><span class="sl-badge">Step 4 of 4</span></div>
        <div class="ns-drops">
          <label class="sl-drop" style="cursor:pointer"><i class="ic ic-lg i-upload" aria-hidden="true"></i><span class="fw5" style="color: var(--ink)">Drop files or browse</span><span class="sm" data-ns-fname>Invoices, receipts, up to 10 MB each</span><input type="file" name="filesMultiple[]" multiple accept="image/*,application/pdf" hidden data-ns-files></label>
          <label class="sl-drop" style="cursor:pointer"><i class="ic ic-lg i-image" aria-hidden="true"></i><span class="fw5" style="color: var(--ink)">Take a photo</span><span class="sm">Capture the parcel with your camera</span><input type="file" name="filesMultiple[]" accept="image/*" capture="environment" hidden data-ns-files></label>
          <label class="sl-drop" style="cursor:pointer"><i class="ic ic-lg i-play" aria-hidden="true"></i><span class="fw5" style="color: var(--ink)">Record a video</span><span class="sm">Show the parcel's condition, up to 60 s</span><input type="file" name="filesVideo[]" accept="video/*" capture="environment" hidden data-ns-files></label>
        </div>
        <label class="sl-field"><span class="sl-lab">Notes</span><textarea class="sl-in" name="courier_notes" style="height:84px;padding:10px 12px" aria-label="Notes" placeholder="Condition of the box, anything the next person should know"></textarea></label>
      </section>
    </div>

    <!-- Step 3: pricing and insurance -->
    <aside class="b col g16" aria-label="Pricing and insurance">
      <section class="sl-card col g12 ns-price">
        <div class="row between"><h2 class="sl-ct">Pricing and insurance</h2><span class="sl-badge">Step 3 of 4</span></div>
        <div class="sl-kv">
          <div><span>Chargeable weight</span><span data-q="w">0</span></div>
          <div><span>Price per <?= $unit ?></span><span data-q="rate"><?= h(sl_money($d['rates']['price_lb'])) ?></span></div>
          <div><span>Freight</span><span data-q="freight">—</span></div>
          <div><span>Declared value</span><span data-q="decl">—</span></div>
          <?php if (!$client): ?>
          <div><span>Insurance · <?= $d['rates']['insurance_mode'] === 'fixed' ? h(sl_money($d['rates']['insurance_fixed'])) . ' fixed' : h($d['rates']['insurance']) . '%' ?></span><span class="row g6"><input class="sl-in mono" name="insured_value" value="0" style="width:90px;height:28px" inputmode="decimal" aria-label="Insured value" data-ns-calc><span data-q="ins">—</span></span></div>
          <div><span>Re-shipping fee</span><input class="sl-in mono" name="reexpedicion_value" value="0" style="width:90px;height:28px" inputmode="decimal" aria-label="Re-shipping fee" data-ns-calc></div>
          <div><span>Discount</span><span class="row g6"><span class="sl-seg boxed" style="height:26px" data-ns-dtype><button type="button" class="on" data-v="amount" style="height:22px;padding:0 8px">$</button><button type="button" data-v="percent" style="height:22px;padding:0 8px">%</button></span><input class="sl-in mono" name="discount_value" value="0" style="width:70px;height:28px" inputmode="decimal" aria-label="Discount" data-ns-calc></span></div>
          <input type="hidden" name="discount_type" value="amount">
          <?php else: ?>
          <input type="hidden" name="insured_value" value="0"><input type="hidden" name="reexpedicion_value" value="0"><input type="hidden" name="discount_value" value="0">
          <?php endif; ?>
        </div>
        <input type="hidden" name="tax_value" value="0"><input type="hidden" name="insurance_value" value="<?= h($d['rates']['insurance']) ?>"><input type="hidden" name="tariffs_value" value="0"><input type="hidden" name="declared_value_tax" value="0"><input type="hidden" name="distance_miles" value="0">
        <div class="row between" style="padding-top: 6px"><span class="fw5">Total due</span><span class="big" data-q="total">—</span></div>
        <?php if ($d['rates']['rate'] > 0): ?><div class="row between sm mut"><span>In cedis (rate <?= h(number_format($d['rates']['rate'], 2)) ?>)</span><span data-q="ghs">—</span></div><?php endif; ?>
        <div class="sm mut" data-q="note">Final price is confirmed on save<?= $client ? ' and when the parcel is weighed' : '' ?>.</div>
        <button type="submit" class="sl-btn ns-create block">Create shipment</button>
      </section>
    </aside>
  </div>
</form>

<?php if (!$client): ?>
<div class="sl-dialog" id="ns-labels" hidden>
  <div class="sl-modal ns-label-modal" role="dialog" aria-modal="true" aria-labelledby="ns-labels-title">
    <div class="row between wrap g8">
      <div class="col"><b class="fw5" id="ns-labels-title" style="font-size:18px">Shipment saved</b><span class="mut sm" data-ns-lsub>Choose a label size, then print.</span></div>
      <button type="button" class="sl-ib" data-ns-lclose aria-label="Close"><i class="ic ic-sm i-x" aria-hidden="true"></i></button>
    </div>
    <div class="row g8 wrap">
      <div class="sl-seg boxed" role="group" aria-label="Label size" data-ns-lsize-group><button type="button" class="on" data-v="46">Large · 4 × 6 in</button><button type="button" data-v="12">Small · 1 × 2 in</button></div>
      <span class="grow"></span>
      <button type="button" class="sl-btn ghost sm" data-ns-lsettings aria-pressed="false"><i class="ic ic-sm i-sliders" aria-hidden="true"></i><span>Label settings</span></button>
    </div>
    <iframe class="ns-label-frame" data-ns-lframe title="Label preview" src="about:blank"></iframe>
    <div class="row g8 wrap">
      <button type="button" class="sl-btn" data-ns-lprint><i class="ic ic-sm i-print" aria-hidden="true"></i>Print label</button>
      <button type="button" class="sl-btn ghost" data-ns-lnew>New shipment</button>
      <button type="button" class="sl-btn ghost" data-ns-lview>View shipment</button>
    </div>
  </div>
</div>
<?php endif; ?>
