<?php
/**
 * PackageIntake - from docs/handover/artifact-source/PackageIntake.dc.html.
 * Data: views/sl/data/intake.php (sl_intakeData). Lookup: ajax/sl/package_intake_ajax.php
 * (pre-alert match in cdb_pre_alert; AI item reading via helpers/ai_assistant_llm.php only
 * when an AI provider is configured). Save: ajax/sl/package_ajax.php op=create (the shared
 * customer-package insert). Client behaviour: dataJs/sl/package_intake.js.
 */
if (!isset($d)) { $d = []; }
$df = $d['defaults'];
$sel = function (string $name, array $rows, $cur) {
    $o = '';
    foreach ($rows as $r) {
        $o .= '<option value="' . (int) $r->id . '"' . ((int) $r->id === (int) $cur ? ' selected' : '') . '>' . h(str_replace('_', ' ', (string) $r->label)) . '</option>';
    }
    return '<span class="sl-sel native"><select name="' . h($name) . '" aria-label="' . h($name) . '">' . $o . '</select><i class="ic ic-sm i-down" aria-hidden="true"></i></span>';
};
?>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:20px">Package Intake</h1><p class="sl-lede">Enter or scan a carrier tracking number. <?= $d['ai'] ? 'AI fills in the rest.' : 'The matching pre-alert fills in the rest.' ?></p></div><div class="row g8 wrap"><span class="ov-pill ok"><span data-ix-today><?= (int) $d['todayCount'] ?></span> added today</span><button type="button" class="sl-btn ghost" data-ix-focus><i class="ic ic-sm i-scan" aria-hidden="true"></i>Scan package</button><button type="button" class="sl-btn" data-ix-new><i class="ic ic-sm i-plus" aria-hidden="true"></i>New package</button></div></div>

<form class="ix" id="ix-form" action="ajax/sl/package_ajax.php" method="post" novalidate data-rates="<?= h(json_encode($d['rates'])) ?>">
<input type="hidden" name="op" value="create"><input type="hidden" name="code_prefix" value="<?= h($d['prefix']) ?>"><input type="hidden" name="prealert_id" value=""><input type="hidden" name="packages" value="[]">
<input type="hidden" name="order_service_options" value="<?= (int) $df['order_service_options'] ?>"><input type="hidden" name="order_item_category" value="27"><input type="hidden" name="notify_whatsapp" value="1">
<div class="ix-main">
  <section class="sl-card col g12">
    <div class="row between wrap g8"><h2 class="sl-ct">1 · Carrier and tracking number</h2><span class="ix-ai"><i class="ic ic-sm i-spark" aria-hidden="true"></i><?= $d['ai'] ? 'AI lookup' : 'Pre-alert lookup' ?></span></div>
    <input type="hidden" name="order_courier" value="<?= (int) $df['order_courier'] ?>">
    <div class="ix-carriers" role="radiogroup" aria-label="Carrier"><?php foreach ($d['carriers'] as $c): ?><button type="button" role="radio" aria-checked="<?= $c['id'] === $df['order_courier'] ? 'true' : 'false' ?>" class="<?= $c['id'] === $df['order_courier'] ? 'on' : '' ?>" data-ix-carrier="<?= (int) $c['id'] ?>" data-label="<?= h($c['label']) ?>"><?= h($c['label']) ?></button><?php endforeach; ?></div>
    <div class="ix-find">
      <label class="ix-track"><i class="ic i-search mut" aria-hidden="true"></i><input name="tracking_purchase" aria-label="Carrier tracking number" placeholder="Tracking number, e.g. 1Z999AA10123456784" autocomplete="off" data-ix-track autofocus></label>
      <button type="button" class="sl-btn" style="height:52px;padding:0 22px" data-ix-lookup><i class="ic ic-sm i-spark" aria-hidden="true"></i>Look up package</button>
    </div>
    <div class="ix-load" aria-live="polite" hidden data-ix-loading><div class="row g8 sm"><i class="ic ic-sm i-spark" aria-hidden="true"></i>Looking for a pre-alert<?= $d['ai'] ? ' and reading the items' : '' ?>…</div><i style="width:70%"></i><i style="width:90%"></i><i style="width:55%"></i></div>
    <div class="sm" hidden data-ix-msg></div>
  </section>

  <section class="sl-card col g16" hidden data-ix-details>
    <div class="row between wrap g8"><h2 class="sl-ct">2 · Package details</h2><span class="ix-ai"><i class="ic ic-sm i-check" aria-hidden="true"></i><span data-ix-conf>Check and edit</span></span></div>
    <div class="ix-grid">
      <div class="sl-field" style="grid-column:1/-1"><span class="sl-lab">Customer · find by locker, name or phone</span><div class="row g6"><span class="sl-search" style="flex:1;max-width:none"><i class="ic ic-sm i-lock" aria-hidden="true"></i><input data-ix-cq placeholder="SL-3200 or a name" autocomplete="off" aria-label="Find customer"></span><button type="button" class="sl-btn ghost sm" style="height:40px" data-ix-cfind>Find</button></div></div>
      <label class="sl-field"><span class="sl-lab">Customer</span><span class="sl-sel native"><select name="sender_id" data-ix-cust required><option value="">Look up or find a customer</option></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
      <label class="sl-field"><span class="sl-lab">Customer address</span><span class="sl-sel native"><select name="sender_address_id" data-ix-addr required><option value="">Pick the customer first</option></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
      <label class="sl-field"><span class="sl-lab">Shipped from (store)</span><input class="sl-in" name="provider_purchase" data-ix-shop placeholder="Amazon, Shein, eBay…" required></label>
      <label class="sl-field"><span class="sl-lab">Purchase price</span><input class="sl-in mono" name="price_purchase" data-ix-price inputmode="decimal" placeholder="0.00"></label>
      <label class="sl-field"><span class="sl-lab">Weight (<?= h($d['rates']['weight']) ?>)</span><input class="sl-in mono" data-ix-w inputmode="decimal" placeholder="0.0"></label>
      <label class="sl-field"><span class="sl-lab">Size L × W × H</span><input class="sl-in mono" data-ix-dims placeholder="12 × 8 × 4"></label>
      <?php if ($d['agencyLocked']): ?><input type="hidden" name="agency" value="<?= (int) $df['agency'] ?>"><?php else: ?><label class="sl-field"><span class="sl-lab">Agency</span><?= $sel('agency', $d['agencies'], $df['agency']) ?></label><?php endif; ?>
      <label class="sl-field"><span class="sl-lab">Warehouse</span><?= $sel('origin_off', $d['offices'], $df['origin_off']) ?></label>
      <label class="sl-field"><span class="sl-lab">Packaging</span><?= $sel('order_package', $d['packaging'], $df['order_package']) ?></label>
      <label class="sl-field"><span class="sl-lab">Delivery time</span><?= $sel('order_deli_time', $d['delitimes'], $df['order_deli_time']) ?></label>
      <label class="sl-field"><span class="sl-lab">Estimated arrival</span><input class="sl-in" type="date" name="estimated_eta" data-ix-eta></label>
    </div>
    <div>
      <div class="row between" style="margin-bottom:4px"><div class="up">Items in the package</div><button type="button" class="sl-btn sm ghost" data-ix-add><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add item</button></div>
      <div class="ix-it mut sm" style="border-top:0"><span>Item</span><span>Qty</span><span>Value (<?= h($d['rates']['currency']) ?>)</span><span></span></div>
      <div data-ix-items></div>
      <div class="row between sm" style="padding-top:8px;border-top:1px solid var(--line)"><span class="mut">Declared value</span><b class="mono" data-ix-declared>0.00</b></div>
    </div>
    <label class="sl-field"><span class="sl-lab">Notes</span><input class="sl-in" name="notes" maxlength="500" placeholder="Box dented, opened by customs…"></label>
    <div class="row g8 wrap"><button type="submit" class="sl-btn" style="height:48px;padding:0 24px"><i class="ic ic-sm i-check" aria-hidden="true"></i>Add package</button><button type="button" class="sl-btn ghost" data-ix-new>Clear</button><span class="mut sm">The customer gets a WhatsApp and SMS that it arrived; the pickup code goes out when it is ready.</span></div>
  </section>
</div>

<aside class="ix-side">
  <section class="sl-card ix-price">
    <div class="row between"><h2 class="sl-ct">Estimated price</h2><span class="ix-ai">Sea · per <?= h($d['rates']['weight']) ?></span></div>
    <div style="font-size:34px;font-weight:600;letter-spacing:-.02em" data-p="total">—</div>
    <?php if ($d['rates']['rate'] > 0): ?><div class="mut sm">≈ <span data-p="ghs">—</span> at USD 1 = GHS <?= h(number_format($d['rates']['rate'], 2)) ?></div><?php endif; ?>
    <div class="sl-ticks n" role="img" aria-label="Chargeable weight"><i style="width:0%" data-p="bar"></i></div>
    <div class="r"><span class="mut">Actual weight</span><span class="mono" data-p="actual">—</span></div>
    <div class="r"><span class="mut">Volumetric weight</span><span class="mono" data-p="vol">—</span></div>
    <div class="r"><span>Freight · <span data-p="charge">0</span> × <?= h(sl_money($d['rates']['price'])) ?></span><span class="mono" data-p="freight">—</span></div>
    <div class="r"><span>Insurance <?= h($d['rates']['ins']) ?>%</span><span class="mono" data-p="ins">—</span></div>
    <div class="r"><span class="mut">Customs</span><span class="mut">Billed on arrival</span></div>
  </section>
  <section class="sl-card col g8">
    <h2 class="sl-ct">Added today</h2>
    <?php foreach ($d['recent'] as $r): ?><a class="ov-row sm" href="<?= sl_route('PackageDetail', ['id' => $r['id']]) ?>" style="color:inherit"><span class="sl-tile"><i class="ic ic-sm i-box" aria-hidden="true"></i></span><span class="grow col"><span class="mono fw5"><?= h($r['track']) ?></span><span class="mut"><?= h($r['name']) ?></span></span><span class="mono"><?= h($r['w']) ?></span></a><?php endforeach; ?>
    <?php if (!$d['recent']): ?><span class="mut sm">Nothing registered today yet.</span><?php endif; ?>
  </section>
</aside>
</form>

<div class="sl-dialog" id="ix-done" hidden>
  <div class="ix-dlg" role="dialog" aria-label="Package added" style="max-height:92vh;overflow:auto">
    <div class="ix-dl">
      <span class="ix-ok"><i class="ic i-check" aria-hidden="true"></i></span>
      <div><h2 style="font-size:22px;font-weight:600;letter-spacing:-.01em">Package added</h2><p class="mut" style="margin-top:4px" data-ix-donesub></p></div>
      <div class="row g8 wrap sm"><span class="ov-pill ok">WhatsApp sent: registered</span><span class="ov-pill" data-ix-doneprice></span></div>
      <div class="up">What do you want to print?</div>
      <div class="ix-opt" role="radiogroup" aria-label="Print">
        <?php if ($d['canLabel']): ?><button type="button" role="radio" aria-checked="true" class="on" data-ix-print="label"><i class="ic i-tag" aria-hidden="true"></i><span class="grow">Label 4 × 6<small>Shipping label with barcode</small></span><span class="sl-chk on" aria-hidden="true"></span></button>
        <button type="button" role="radio" aria-checked="false" data-ix-print="small"><i class="ic i-tag" aria-hidden="true"></i><span class="grow">Shelf label 1 × 2<small>Bag and shelf label</small></span><span class="sl-chk" aria-hidden="true"></span></button><?php endif; ?>
        <?php if ($d['canReceipt']): ?><button type="button" role="radio" aria-checked="<?= $d['canLabel'] ? 'false' : 'true' ?>" class="<?= $d['canLabel'] ? '' : 'on' ?>" data-ix-print="receipt"><i class="ic i-receipt" aria-hidden="true"></i><span class="grow">Receipt<small>For the customer</small></span><span class="sl-chk <?= $d['canLabel'] ? '' : 'on' ?>" aria-hidden="true"></span></button><?php endif; ?>
      </div>
      <div class="row g8 wrap"><a class="sl-btn" style="flex:1" target="_blank" href="#" data-ix-printgo><i class="ic ic-sm i-print" aria-hidden="true"></i>Print</a><a class="sl-btn ghost" href="#" data-ix-open>Open package</a></div>
      <div class="hr"></div>
      <div class="row g8 wrap"><button type="button" class="sl-btn soft" style="flex:1" data-ix-new><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add new package</button><button type="button" class="sl-btn ghost" data-sl-close data-ix-reload>Close</button></div>
    </div>
    <div class="ix-dr"><div class="up">Print preview</div><iframe title="Print preview" data-ix-preview style="width:100%;min-height:470px;border:0;background:#fff;border-radius:4px"></iframe></div>
  </div>
</div>
