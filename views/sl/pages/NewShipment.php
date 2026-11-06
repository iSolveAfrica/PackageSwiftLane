<?php
/**
 * NewShipment - generated from docs/handover/artifact-source/NewShipment.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  chips                        as $c
 *   loop  opts                         as $o
 *   loop  o.list                       as $x
 *   loop  flags                        as $g
 *   if    editOpen
 *   if    loaded
 *   value chips, editLabel, editOpen, false, flags, loaded, loadedMsg, ndSub, nextLabel, opts, quickAria, quickCls, saveNext, toggleEdit, toggleQuick
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
        <div class="sl-head">
          <div><a href="<?= sl_route('AirShipments') ?>" class="row g6 mut sm"><i class="ic ic-sm i-left" aria-hidden="true"></i>Air Shipments</a><h1 class="sl-title" style="margin-top: 4px">New Shipment</h1></div>
          <div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('AirShipments') ?>">Save draft</a><button class="sl-btn ghost" data-sl-on="click:saveNext"><i class="ic ic-sm i-plus" aria-hidden="true"></i><?= sl_v($d, 'nextLabel') ?></button><a class="sl-btn" href="<?= sl_route('ShipmentDetail') ?>">Create shipment</a></div>
        </div>
<section class="sl-card col g12 nd-bar">
          <div class="row between wrap g8"><div class="row g10"><span class="sl-tile"><i class="ic i-bolt" aria-hidden="true"></i></span><div class="col"><b class="fw5">Quick load with defaults</b><span class="mut sm"><?= sl_v($d, 'ndSub') ?></span></div></div><div class="row g8 wrap"><span class="row g8 sm"><button class="sl-toggle <?= sl_v($d, 'quickCls') ?>" role="switch" aria-checked="<?= sl_v($d, 'quickAria') ?>" aria-label="Quick load" data-sl-on="click:toggleQuick"></button>On</span><button class="sl-btn ghost sm" data-sl-on="click:toggleEdit"><i class="ic ic-sm i-sliders" aria-hidden="true"></i><?= sl_v($d, 'editLabel') ?></button><a class="sl-btn ghost sm" href="<?= sl_route('SettingsHub') ?>"><i class="ic ic-sm i-cog" aria-hidden="true"></i>Settings</a></div></div>
          <div class="row wrap g6"><?php foreach ((array) sl_get($d, 'chips', []) as $__i => $c): ?><span class="nd-chip"><span class="mut"><?= sl_v($c, 'k') ?></span><?= sl_v($c, 'v') ?></span><?php endforeach; ?></div>
          <?php if (sl_get($d, 'editOpen')): ?><div class="nd-ed"><?php foreach ((array) sl_get($d, 'opts', []) as $__i => $o): ?><div class="sl-field"><span class="sl-lab"><?= sl_v($o, 'k') ?></span><div class="sl-seg boxed"><?php foreach ((array) sl_get($o, 'list', []) as $__i => $x): ?><button class="<?= sl_v($x, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($x, 'label') ?></button><?php endforeach; ?></div></div><?php endforeach; ?>
            <div class="col g8" style="grid-column:1/-1"><?php foreach ((array) sl_get($d, 'flags', []) as $__i => $g): ?><span class="row g10 sm"><button class="sl-toggle <?= sl_v($g, 'cls') ?>" role="switch" aria-checked="<?= sl_v($g, 'aria') ?>" aria-label="<?= sl_v($g, 't') ?>" data-sl-on="click:flip"></button><span class="col"><span><?= sl_v($g, 't') ?></span><small class="mut"><?= sl_v($g, 'd') ?></small></span></span><?php endforeach; ?></div>
            <div class="row g8" style="grid-column:1/-1"><button class="sl-btn sm" data-sl-on="click:toggleEdit">Save as my defaults</button><span class="mut sm">Applies to every new package you load. Admins set branch defaults in Settings.</span></div></div><?php endif; ?>
          <?php if (sl_get($d, 'loaded')): ?><div class="row g8 sm nd-done"><i class="ic ic-sm i-check" aria-hidden="true"></i><?= sl_v($d, 'loadedMsg') ?></div><?php endif; ?>
        </section>
        <div class="split">
          <div class="a col g16">
            <section class="sl-card col g16">
              <div class="row between"><h2 class="sl-ct">Sender and recipient</h2><span class="sl-badge">Step 1 of 4</span></div>
              <div class="grid cols2">
                <div class="col g12">
                  <label class="sl-field"><span class="sl-lab">Locker ID</span><div class="row g6"><span class="sl-search" style="flex:1;max-width:none"><i class="ic ic-sm i-lock" aria-hidden="true"></i><input class="mono" aria-label="Locker ID" value="SL-2041" style="font-weight:500"></span><a class="sl-btn ghost sm" href="<?= sl_route('LockerSearch') ?>" style="height:40px"><i class="ic ic-sm i-search" aria-hidden="true"></i>Find</a></div><span class="sl-hint">Type the locker ID to fill the sender, or pick the customer below</span></label>
                  <div class="sl-field"><span class="sl-lab">Sender (customer)</span><div class="sl-sel"><span class="row g8"><span class="sl-av sm">KM</span>Kwame Mensah<span class="sl-badge mono" title="Locker ID">SL-2041</span></span><i class="ic ic-sm i-down" aria-hidden="true"></i></div></div>
                  <div class="sl-field"><span class="sl-lab">Sender address</span><div class="sl-sel"><span>1200 Commerce Dr, Newark, NJ 07105</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div></div>
                </div>
                <div class="col g12">
                  <div class="sl-field"><span class="sl-lab">Recipient</span><div class="sl-sel"><span class="row g8"><span class="sl-av sm">AB</span>Ama Boateng<span class="sl-badge mono" title="Locker ID">SL-1187</span></span><i class="ic ic-sm i-down" aria-hidden="true"></i></div></div>
                  <div class="sl-field"><span class="sl-lab">Recipient address</span><div class="sl-sel"><span>14 Palm Close, East Legon, Accra</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div></div>
                </div>
              </div>
              <div class="col g10" style="padding: 12px 14px; border-radius: 12px; background: var(--active)">
<div class="row between wrap g8"><span class="fw5 sm row g6"><i class="ic ic-sm i-bell" aria-hidden="true"></i>Notifications for this shipment</span><a href="<?= sl_route('NotificationCenter') ?>" class="sm fw5 row g6"><i class="ic ic-sm i-cog" aria-hidden="true"></i>Default settings</a></div>
<div class="nt-row"><div class="col"><b class="fw5 sm">Notify sender</b><span class="mut" style="font-size:12px">+1 973 555 0142 · kwame@example.com</span></div><div class="row g6 wrap"><span class="nt-ch on"><i class="ic ic-sm i-message" aria-hidden="true"></i>WhatsApp</span><span class="nt-ch on"><i class="ic ic-sm i-phone" aria-hidden="true"></i>SMS</span><span class="nt-ch on"><i class="ic ic-sm i-mail" aria-hidden="true"></i>E-mail</span></div></div>
<div class="row between wrap g8 sm"><span class="mut">Defaults from Settings › Notifications: sender WhatsApp + SMS + e-mail. Each message costs: WhatsApp ₵0.12 · SMS ₵0.03 · e-mail ₵0.002.</span><span class="row g8"><span class="sl-toggle"></span>Save as my default</span></div>
<div class="row g12 wrap sm"><a href="<?= sl_route('Customers') ?>" class="fw5 row g6"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add new customer</a><a href="<?= sl_route('Recipients') ?>" class="fw5 row g6"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add new recipient or address</a></div></div><div class="sl-field"><span class="sl-lab">Linked pre-alert <span class="mut">(optional)</span></span><div class="sl-sel"><span class="row g8"><span class="mono">PA-20931</span><span class="mut">UPS · Apple Store · matched by tracking number</span></span><i class="ic ic-sm i-down" aria-hidden="true"></i></div></div>
            </section>

            <section class="sl-card col g16">
              <div class="row between"><h2 class="sl-ct">Shipment details</h2><span class="sl-badge">Step 2 of 4</span></div>
              <div class="grid cols2">
                <div class="sl-opt on"><span class="sl-radio on"></span><i class="ic i-plane" aria-hidden="true"></i><div><div class="fw5">Air freight</div><div class="mut sm">5–7 days · per kg</div></div></div>
                <div class="sl-opt"><span class="sl-radio"></span><i class="ic i-ship" aria-hidden="true"></i><div><div class="fw5">Sea freight</div><div class="mut sm">35–45 days · per cubic foot</div></div></div>
              </div>
              <div class="grid cols3">
                <div class="sl-field"><span class="sl-lab">Branch / office</span><div class="sl-sel"><span>Newark warehouse → Accra Hub</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div></div>
                <div class="sl-field"><span class="sl-lab">Shipment number</span><div class="row g6"><div class="sl-sel" style="width:84px;flex:none"><span class="mono">SL-</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div><input class="sl-in mono" value="100483" aria-label="Shipment number"></div><span class="sl-hint">Next free number. Prefix can be changed.</span></div>
                <div class="sl-field"><span class="sl-lab">Status</span><div class="sl-sel"><span>Received at US warehouse</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div></div>
                <div class="sl-field"><span class="sl-lab">Item category</span><div class="sl-sel"><span>Electronics</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div></div>
                <div class="sl-field"><span class="sl-lab">Rate from</span><div class="sl-sel"><span>Tariff table · per lb</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div></div>
                <div class="sl-field"><span class="sl-lab">Price per lb</span><input class="sl-in mono" value="$6.00" aria-label="Price per lb"></div>
                <div class="sl-field"><span class="sl-lab">Estimated arrival</span><div class="sl-sel"><span>Nov 12, 2026</span><i class="ic ic-sm i-calendar" aria-hidden="true"></i></div></div>
                <div class="sl-field"><span class="sl-lab">Courier company</span><div class="sl-sel"><span>UPS</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div></div>
                <div class="sl-field"><label class="sl-lab" for="t">Carrier tracking number</label><input id="t" class="sl-in mono" value="1Z999AA10123456784"></div>
                <div class="sl-field"><span class="sl-lab">Payment method</span><div class="sl-sel"><span>Paystack</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div></div>
                <div class="sl-field"><span class="sl-lab">Order date</span><div class="sl-sel"><span>Nov 5, 2026</span><i class="ic ic-sm i-calendar" aria-hidden="true"></i></div></div>
                <div class="sl-field"><span class="sl-lab">Delivery time</span><div class="sl-sel"><span>5–7 business days</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div></div>
                <div class="sl-field"><span class="sl-lab">Assign driver</span><div class="sl-sel"><span>Unassigned</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div></div>
              </div>
              <div class="row between wrap g12"><span class="row g8"><span class="sl-toggle"></span>Contains dangerous goods</span><span class="sl-hint">Batteries, liquids and aerosols need an approved declaration.</span></div>
            </section>

            <section class="sl-card flush">
              <div class="row between" style="padding: 16px 20px"><div class="row g12"><h2 class="sl-ct">Items</h2><span class="sl-badge">Step 3 of 4</span></div><button class="sl-btn ghost sm"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add item</button></div>
              <div class="sl-tw"><table class="sl-table">
                <thead><tr><th>Qty</th><th>Description</th><th>Category</th><th class="num">L × W × H (in)</th><th class="num">Weight (lb)</th><th class="num">Vol. weight (lb)</th><th class="num">Declared value</th><th></th></tr></thead>
                <tbody>
                  <tr><td>1</td><td>Samsung Galaxy A55</td><td>Electronics</td><td class="num mono">7 × 4 × 2</td><td class="num">2.0</td><td class="num mut">0.4</td><td class="num">$320.00</td><td><button class="sl-ib sm" aria-label="Remove item"><i class="ic i-trash" aria-hidden="true"></i></button></td></tr>
                  <tr><td>2</td><td>Cotton bedsheet set</td><td>Home</td><td class="num mono">16 × 12 × 5</td><td class="num">5.1</td><td class="num mut">6.9</td><td class="num">$64.00</td><td><button class="sl-ib sm" aria-label="Remove item"><i class="ic i-trash" aria-hidden="true"></i></button></td></tr>
                </tbody></table></div>
              <div class="row between wrap g12" style="padding: 14px 20px; border-top: 1px solid var(--line)"><span class="mut">Total weight</span><span class="fw5">7.1 lb · volumetric 7.3 lb · chargeable 7.3 lb</span></div>
            </section>

            <section class="sl-card col g16">
              <div class="row between"><h2 class="sl-ct">Photos and documents</h2><span class="sl-badge">Step 4 of 4</span></div>
              <div class="grid cols2">
                <div class="sl-drop"><i class="ic ic-lg i-upload" aria-hidden="true"></i><span class="fw5" style="color: var(--ink)">Drop files or browse</span><span class="sm">Invoices, receipts, up to 10 MB each</span></div>
                <div class="sl-drop"><i class="ic ic-lg i-image" aria-hidden="true"></i><span class="fw5" style="color: var(--ink)">Take a photo</span><span class="sm">Capture the parcel with your camera</span></div>
                <div class="sl-drop"><i class="ic ic-lg i-play" aria-hidden="true"></i><span class="fw5" style="color: var(--ink)">Record a video</span><span class="sm">Show the parcel's condition, up to 60 s</span></div>
                <div class="sl-drop"><i class="ic ic-lg i-scan" aria-hidden="true"></i><span class="fw5" style="color: var(--ink)">Photo of each item</span><span class="sm">Attach a picture to an item row</span></div>
              </div>
              <label class="sl-field"><span class="sl-lab">Notes</span><textarea class="sl-in" style="height:84px;padding:10px 12px" aria-label="Notes">Box slightly dented on one corner. Contents checked, all fine.</textarea></label>
            </section>
          </div>

          <aside class="b col g16" aria-label="Quote summary">
            <section class="sl-card col g12">
              <h2 class="sl-ct">Quote summary</h2>
              <div class="sl-kv"><div><span>Chargeable weight</span><span>7.3 lb</span></div><div><span>Price per lb (Air)</span><span>$6.00</span></div><div><span>Freight</span><span>$43.80</span></div><div><span>Declared value $384 · tax 2%</span><span>$7.68</span></div><div><span>Insurance (insured $384)</span><span>$3.84</span></div><div><span>Re-shipping fee</span><span>$5.00</span></div><div><span>Customs tax</span><span>$0.00</span></div><div><span>Discount</span><span class="row g6"><span class="sl-seg boxed" style="height:26px"><button class="on" style="height:22px;padding:0 8px">$</button><button style="height:22px;padding:0 8px">%</button></span>−$2.00</span></div><div><span>Subtotal</span><span>$58.32</span></div></div>
              <div class="row between" style="padding-top: 6px"><span class="fw5">Total due</span><span class="big">$58.32</span></div>
              <div class="row between sm mut"><span>In cedis (rate 12.40)</span><span>₵723.17</span></div>
              <div class="row g8"><span class="sl-toggle on"></span><span class="sm">Create invoice when saved</span></div>
              <a class="sl-btn block" href="<?= sl_route('ShipmentDetail') ?>">Create shipment</a>
              <a class="sl-btn ghost block" href="<?= sl_route('AirShipments') ?>">Save draft</a>
            </section>
            
          </aside>
        </div>
      
