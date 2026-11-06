<?php
/**
 * PackageIntake - generated from docs/handover/artifact-source/PackageIntake.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  carriers                     as $c
 *   loop  modes                        as $m
 *   loop  items                        as $it
 *   loop  recent                       as $r
 *   loop  prints                       as $o
 *   if    scanning
 *   if    loading
 *   if    filled
 *   if    done
 *   if    is46
 *   if    is12
 *   if    isRc
 *   value add, addItem, carrierLabel, carriers, closeDone, conf, declared, done, false, filled, is12, is46, isRc, items, loading, lookup, modeLabel, modes, newPkg, onDims, onName, onTracking, onWeight, printLabel, prints, recent, scanning, startScan, todayCount, tracking, true
 * sl-wrap extras in the design: style="max-width:1120px"
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:20px">Package Intake</h1><p class="sl-lede">Enter or scan a carrier tracking number. AI fills in the rest.</p></div><div class="row g8 wrap"><span class="ov-pill ok"><?= sl_v($d, 'todayCount') ?> added today</span><button class="sl-btn ghost" data-sl-on="click:startScan"><i class="ic ic-sm i-scan" aria-hidden="true"></i>Scan package</button><button class="sl-btn" data-sl-on="click:newPkg"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New package</button></div></div>

<div class="ix">
<div class="ix-main">
  <section class="sl-card col g12">
    <div class="row between wrap g8"><h2 class="sl-ct">1 · Carrier and tracking number</h2><span class="ix-ai"><i class="ic ic-sm i-spark" aria-hidden="true"></i>AI lookup</span></div>
    <div class="ix-carriers" role="radiogroup" aria-label="Carrier"><?php foreach ((array) sl_get($d, 'carriers', []) as $__i => $c): ?><button role="radio" aria-checked="<?= sl_v($c, 'sel') ?>" class="<?= sl_v($c, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($c, 'label') ?></button><?php endforeach; ?></div>
    <div class="ix-find">
      <label class="ix-track"><i class="ic i-search mut" aria-hidden="true"></i><input aria-label="Carrier tracking number" placeholder="Tracking number, e.g. 1Z999AA10123456784" value="<?= sl_v($d, 'tracking') ?>" data-sl-on="change:onTracking"><button class="sl-ib" data-sl-on="click:startScan" aria-label="Scan barcode"><i class="ic ic-sm i-scan" aria-hidden="true"></i></button></label>
      <button class="sl-btn" style="height:52px;padding:0 22px" data-sl-on="click:lookup"><i class="ic ic-sm i-spark" aria-hidden="true"></i>Look up package</button>
    </div>
    <?php if (sl_get($d, 'scanning')): ?>
      <div class="ix-scan" aria-label="Camera scanning"><span class="sm" style="color:#fff;position:relative;z-index:1;margin-top:96px">Point the camera at the barcode…</span></div>
    <?php endif; ?>
    <?php if (sl_get($d, 'loading')): ?>
      <div class="ix-load" aria-live="polite"><div class="row g8 sm"><i class="ic ic-sm i-spark" aria-hidden="true" style="height:auto;width:auto;background:none;animation:none"></i>Reading <?= sl_v($d, 'carrierLabel') ?> tracking, order e-mail and invoice…</div><i style="width:70%"></i><i style="width:90%"></i><i style="width:55%"></i></div>
    <?php endif; ?>
  </section>

  <?php if (sl_get($d, 'filled')): ?>
    <section class="sl-card col g16">
      <div class="row between wrap g8"><h2 class="sl-ct">2 · Package details</h2><span class="ix-ai"><i class="ic ic-sm i-check" aria-hidden="true"></i>Filled by AI · <?= sl_v($d, 'conf') ?> sure · check and edit</span></div>
      <div class="ix-grid">
        <div class="sl-field" style="grid-column:1/-1"><label class="sl-lab" for="ix-n">Package name</label><input id="ix-n" class="sl-in" value="<?= sl_v($d, 'd.name') ?>" data-sl-on="change:onName"></div>
        <div class="sl-field"><span class="sl-lab">Customer · matched from locker</span><div class="sl-sel"><span><b class="mono"><?= sl_v($d, 'd.locker') ?></b> · <?= sl_v($d, 'd.customer') ?></span><i class="ic ic-sm i-down" aria-hidden="true"></i></div></div>
        <div class="sl-field"><span class="sl-lab">Shipped from</span><div class="sl-sel"><span><?= sl_v($d, 'd.store') ?></span><i class="ic ic-sm i-down" aria-hidden="true"></i></div></div>
        <div class="sl-field"><label class="sl-lab" for="ix-w">Weight (lb)</label><input id="ix-w" class="sl-in mono" value="<?= sl_v($d, 'd.weight') ?>" data-sl-on="change:onWeight"></div>
        <div class="sl-field"><label class="sl-lab" for="ix-dm">Size L × W × H (in)</label><input id="ix-dm" class="sl-in mono" value="<?= sl_v($d, 'd.dims') ?>" data-sl-on="change:onDims"></div>
        <div class="sl-field"><span class="sl-lab">Carrier status</span><div class="sl-sel"><span><?= sl_v($d, 'd.status') ?></span><span class="ov-pill ok">Delivered to us</span></div></div>
        <div class="sl-field"><span class="sl-lab">Mode</span><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'modes', []) as $__i => $m): ?><button class="<?= sl_v($m, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($m, 'label') ?></button><?php endforeach; ?></div></div>
      </div>
      <div>
        <div class="row between" style="margin-bottom:4px"><div class="up">Items in the package</div><button class="sl-btn sm ghost" data-sl-on="click:addItem"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add item</button></div>
        <div class="ix-it mut sm" style="border-top:0"><span>Item</span><span>Qty</span><span>Value ($)</span><span></span></div>
        <?php foreach ((array) sl_get($d, 'items', []) as $__i => $it): ?>
          <div class="ix-it"><input class="sl-in" aria-label="Item name" value="<?= sl_v($it, 'name') ?>" data-sl-on="change:onName"><input class="sl-in mono" aria-label="Quantity" value="<?= sl_v($it, 'qty') ?>" data-sl-on="change:onQty"><input class="sl-in mono" aria-label="Value" value="<?= sl_v($it, 'value') ?>" data-sl-on="change:onValue"><button class="sl-ib" data-sl-on="click:remove" aria-label="Remove item"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div>
        <?php endforeach; ?>
        <div class="row between sm" style="padding-top:8px;border-top:1px solid var(--line)"><span class="mut">Declared value</span><b class="mono"><?= sl_v($d, 'declared') ?></b></div>
      </div>
      <div class="row g8 wrap"><button class="sl-btn" style="height:48px;padding:0 24px" data-sl-on="click:add"><i class="ic ic-sm i-check" aria-hidden="true"></i>Add package</button><button class="sl-btn ghost" data-sl-on="click:newPkg">Clear</button><span class="mut sm">A pickup code and WhatsApp go out when it is ready</span></div>
    </section>
  <?php endif; ?>
</div>

<aside class="ix-side">
  <section class="sl-card ix-price">
    <div class="row between"><h2 class="sl-ct">Estimated price</h2><span class="ix-ai"><?= sl_v($d, 'modeLabel') ?></span></div>
    <div style="font-size:34px;font-weight:600;letter-spacing:-.02em"><?= sl_v($d, 'p.total') ?></div>
    <div class="mut sm">≈ <?= sl_v($d, 'p.ghs') ?> at USD 1 = GHS 12.40</div>
    <div class="sl-ticks n" role="img" aria-label="Chargeable weight"><i style="<?= sl_v($d, 'p.bar') ?>"></i></div>
    <div class="r"><span class="mut">Actual weight</span><span class="mono"><?= sl_v($d, 'p.actual') ?></span></div>
    <div class="r"><span class="mut">Volumetric weight</span><span class="mono"><?= sl_v($d, 'p.vol') ?></span></div>
    <div class="r"><span>Freight · <?= sl_v($d, 'p.charge') ?> × <?= sl_v($d, 'p.rate') ?></span><span class="mono"><?= sl_v($d, 'p.freight') ?></span></div>
    <div class="r"><span>Insurance 1%</span><span class="mono"><?= sl_v($d, 'p.ins') ?></span></div>
    <div class="r"><span class="mut">Customs</span><span class="mut">Billed on arrival</span></div>
  </section>
  <section class="sl-card col g8">
    <h2 class="sl-ct">Added today</h2>
    <?php foreach ((array) sl_get($d, 'recent', []) as $__i => $r): ?><div class="ov-row sm"><span class="sl-tile"><i class="ic ic-sm i-box" aria-hidden="true"></i></span><span class="grow col"><span class="mono fw5"><?= sl_v($r, 'id') ?></span><span class="mut"><?= sl_v($r, 'name') ?></span></span><span class="mono"><?= sl_v($r, 'w') ?></span></div><?php endforeach; ?>
  </section>
</aside>
</div>

<?php if (sl_get($d, 'done')): ?>
  <div class="ix-modal">
    <div class="ix-dlg" role="dialog" aria-label="Package added">
      <div class="ix-dl">
        <span class="ix-ok"><i class="ic i-check" aria-hidden="true"></i></span>
        <div><h2 style="font-size:22px;font-weight:600;letter-spacing:-.01em">Package added</h2><p class="mut" style="margin-top:4px"><b class="mono" style="color:var(--ink)">SL-100483</b> · <?= sl_v($d, 'd.name') ?> · for <?= sl_v($d, 'd.customer') ?> (<?= sl_v($d, 'd.locker') ?>)</p></div>
        <div class="row g8 wrap sm"><span class="ov-pill ok">WhatsApp sent: registered</span><span class="ov-pill">Bay 14</span><span class="ov-pill"><?= sl_v($d, 'p.total') ?> estimated</span></div>
        <div class="up">What do you want to print?</div>
        <div class="ix-opt" role="radiogroup" aria-label="Print">
          <?php foreach ((array) sl_get($d, 'prints', []) as $__i => $o): ?><button role="radio" aria-checked="<?= sl_v($o, 'sel') ?>" class="<?= sl_v($o, 'cls') ?>" data-sl-on="click:pick"><i class="ic <?= sl_v($o, 'icon') ?>" aria-hidden="true"></i><span class="grow"><?= sl_v($o, 'label') ?><small><?= sl_v($o, 'sub') ?></small></span><span class="sl-chk <?= sl_v($o, 'chk') ?>" aria-hidden="true"></span></button><?php endforeach; ?>
        </div>
        <div class="row g8 wrap"><button class="sl-btn" style="flex:1"><i class="ic ic-sm i-print" aria-hidden="true"></i>Print <?= sl_v($d, 'printLabel') ?></button><button class="sl-btn ghost"><i class="ic ic-sm i-mail" aria-hidden="true"></i>E-mail receipt</button></div>
        <div class="hr"></div>
        <div class="row g8 wrap"><button class="sl-btn soft" style="flex:1" data-sl-on="click:newPkg"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add new package</button><button class="sl-btn soft" style="flex:1" data-sl-on="click:startScan"><i class="ic ic-sm i-scan" aria-hidden="true"></i>Scan next</button><button class="sl-btn ghost" data-sl-on="click:closeDone">Close</button></div>
      </div>
      <div class="ix-dr">
        <div class="up">Print preview · <?= sl_v($d, 'printLabel') ?></div>
        <?php if (sl_get($d, 'is46')): ?><div class="ix-paper" style="width:288px;height:432px;overflow:hidden"><div style="transform:scale(.75);transform-origin:0 0"><div class="lb lb46">
<div class="row between"><div class="row g8"><span style="width:30px;height:30px;border-radius:7px;background:#000;color:#fff;display:grid;place-items:center;font-weight:800;font-size:12px">SL</span><b style="font-size:16px">Swift Lane</b></div><div style="text-align:right"><div style="font-size:22px;font-weight:800;line-height:1">AIR</div><div class="k">Express · 5–7 days</div></div></div>
<div class="bx" style="display:flex;justify-content:space-between;align-items:center"><div><div class="k">Locker</div><div style="font-size:40px;font-weight:800;letter-spacing:-.02em;line-height:1">SL-3318</div></div><div style="text-align:right"><div class="k">Bay</div><div style="font-size:28px;font-weight:800;line-height:1">14</div></div></div>
<div class="bx"><div class="k">Ship to</div><div style="font-size:18px;font-weight:700">EFUA OWUSU</div><div style="font-size:12px">14 Palm Close, East Legon, Accra · +233 24 *** 5678</div></div>
<div class="row g8" style="font-size:12px;font-weight:700"><span class="bx" style="flex:1;text-align:center;padding:6px">NEWARK, NJ</span><span style="font-size:18px">→</span><span class="bx" style="flex:1;text-align:center;padding:6px;background:#000;color:#fff">ACCRA, GH</span></div>
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px;font-size:11px"><div class="bx" style="padding:5px 7px"><div class="k">Weight</div><b style="font-size:15px">5.6 lb</b></div><div class="bx" style="padding:5px 7px"><div class="k">Pieces</div><b style="font-size:15px">1 / 1</b></div><div class="bx" style="padding:5px 7px"><div class="k">Received</div><b style="font-size:15px">Nov 5</b></div></div>
<div style="font-size:10.5px;line-height:1.35"><b>Contents:</b> Apple iPad Air 11" ×1, Apple Pencil Pro ×1, USB-C cable ×2 · declared $799.00</div>
<div style="margin-top:auto;display:flex;flex-direction:column;align-items:center;gap:2px"><svg viewBox="0 0 350 88" width="350" height="88" role="img" aria-label="Barcode SL-100483"><rect x="0.00" y="0" width="4.24" height="71" fill="#000"></rect><rect x="6.36" y="0" width="2.12" height="71" fill="#000"></rect><rect x="12.73" y="0" width="2.12" height="71" fill="#000"></rect><rect x="31.82" y="0" width="8.48" height="71" fill="#000"></rect><rect x="44.55" y="0" width="2.12" height="71" fill="#000"></rect><rect x="48.79" y="0" width="4.24" height="71" fill="#000"></rect><rect x="55.15" y="0" width="4.24" height="71" fill="#000"></rect><rect x="63.64" y="0" width="6.36" height="71" fill="#000"></rect><rect x="72.12" y="0" width="2.12" height="71" fill="#000"></rect><rect x="76.36" y="0" width="2.12" height="71" fill="#000"></rect><rect x="80.61" y="0" width="8.48" height="71" fill="#000"></rect><rect x="97.58" y="0" width="4.24" height="71" fill="#000"></rect><rect x="108.18" y="0" width="6.36" height="71" fill="#000"></rect><rect x="116.67" y="0" width="4.24" height="71" fill="#000"></rect><rect x="123.03" y="0" width="4.24" height="71" fill="#000"></rect><rect x="135.76" y="0" width="4.24" height="71" fill="#000"></rect><rect x="142.12" y="0" width="6.36" height="71" fill="#000"></rect><rect x="171.82" y="0" width="12.73" height="71" fill="#000"></rect><rect x="186.67" y="0" width="8.48" height="71" fill="#000"></rect><rect x="201.52" y="0" width="4.24" height="71" fill="#000"></rect><rect x="214.24" y="0" width="8.48" height="71" fill="#000"></rect><rect x="226.97" y="0" width="21.21" height="71" fill="#000"></rect><rect x="258.79" y="0" width="10.61" height="71" fill="#000"></rect><rect x="273.64" y="0" width="10.61" height="71" fill="#000"></rect><rect x="303.33" y="0" width="6.36" height="71" fill="#000"></rect><rect x="311.82" y="0" width="4.24" height="71" fill="#000"></rect><rect x="322.42" y="0" width="4.24" height="71" fill="#000"></rect><rect x="333.03" y="0" width="6.36" height="71" fill="#000"></rect><rect x="341.52" y="0" width="2.12" height="71" fill="#000"></rect><rect x="345.76" y="0" width="4.24" height="71" fill="#000"></rect><text x="175.0" y="87" text-anchor="middle" style="font:13px JetBrains Mono,monospace;fill:#000;letter-spacing:.08em">SL-100483</text></svg><div style="font-size:9.5px" class="m">UPS 1Z999AA10123456784</div></div>
</div></div></div><?php endif; ?>
        <?php if (sl_get($d, 'is12')): ?><div class="ix-paper" style="width:384px;height:192px;overflow:hidden;margin-top:60px"><div style="transform:scale(2);transform-origin:0 0"><div class="lb lb12"><div class="row between" style="align-items:baseline"><b style="font-size:17px;letter-spacing:-.01em;line-height:1">SL-3318</b><span style="font-size:8px;font-weight:800;border:1.5px solid #000;border-radius:3px;padding:0 3px">AIR · B14</span></div><div style="font-size:8.5px;font-weight:600;white-space:nowrap;overflow:hidden">EFUA OWUSU · 5.6 lb · 1/1</div><svg viewBox="0 0 178 40" width="178" height="40" role="img" aria-label="Barcode 1Z999AA10123456784"><rect x="0.00" y="0" width="2.14" height="29.5" fill="#000"></rect><rect x="3.22" y="0" width="1.07" height="29.5" fill="#000"></rect><rect x="6.43" y="0" width="1.07" height="29.5" fill="#000"></rect><rect x="12.87" y="0" width="4.29" height="29.5" fill="#000"></rect><rect x="23.59" y="0" width="1.07" height="29.5" fill="#000"></rect><rect x="27.88" y="0" width="3.22" height="29.5" fill="#000"></rect><rect x="32.17" y="0" width="2.14" height="29.5" fill="#000"></rect><rect x="36.46" y="0" width="1.07" height="29.5" fill="#000"></rect><rect x="38.60" y="0" width="6.43" height="29.5" fill="#000"></rect><rect x="47.18" y="0" width="7.51" height="29.5" fill="#000"></rect><rect x="55.76" y="0" width="2.14" height="29.5" fill="#000"></rect><rect x="60.05" y="0" width="15.01" height="29.5" fill="#000"></rect><rect x="80.42" y="0" width="12.87" height="29.5" fill="#000"></rect><rect x="94.36" y="0" width="2.14" height="29.5" fill="#000"></rect><rect x="99.72" y="0" width="4.29" height="29.5" fill="#000"></rect><rect x="107.23" y="0" width="1.07" height="29.5" fill="#000"></rect><rect x="111.52" y="0" width="3.22" height="29.5" fill="#000"></rect><rect x="121.17" y="0" width="3.22" height="29.5" fill="#000"></rect><rect x="129.75" y="0" width="8.58" height="29.5" fill="#000"></rect><rect x="142.61" y="0" width="4.29" height="29.5" fill="#000"></rect><rect x="147.98" y="0" width="2.14" height="29.5" fill="#000"></rect><rect x="155.48" y="0" width="2.14" height="29.5" fill="#000"></rect><rect x="159.77" y="0" width="3.22" height="29.5" fill="#000"></rect><rect x="164.06" y="0" width="2.14" height="29.5" fill="#000"></rect><rect x="169.42" y="0" width="3.22" height="29.5" fill="#000"></rect><rect x="173.71" y="0" width="1.07" height="29.5" fill="#000"></rect><rect x="175.86" y="0" width="2.14" height="29.5" fill="#000"></rect><text x="89.0" y="39" text-anchor="middle" style="font:6.5px JetBrains Mono,monospace;fill:#000;letter-spacing:.08em">1Z999AA10123456784</text></svg></div></div></div><p class="mut sm">Shown at 2× · prints at 1 × 2 in</p><?php endif; ?>
        <?php if (sl_get($d, 'isRc')): ?><div class="ix-paper" style="width:242px;height:432px;overflow:hidden"><div style="transform:scale(.8);transform-origin:0 0"><div class="rc">
<div style="text-align:center;display:flex;flex-direction:column;align-items:center;gap:4px"><span style="width:36px;height:36px;border-radius:9px;background:#161615;color:#fff;display:grid;place-items:center;font-weight:800">SL</span><b style="font-size:16px">Swift Lane</b><span style="color:#6B6A65;font-size:11px">Accra Hub · [Hub address] · [Hub phone]</span></div>
<div class="dash"></div>
<div style="text-align:center"><div style="font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:#6B6A65">Package receipt</div><b class="m" style="font-size:18px">SL-100483</b><div style="color:#6B6A65;font-size:11px">Nov 5, 2026 · 09:46 · Adwoa S.</div></div>
<div class="dash"></div>
<div class="r"><span style="color:#6B6A65">Customer</span><b>Efua Owusu</b></div>
<div class="r"><span style="color:#6B6A65">Locker</span><span class="m">SL-3318</span></div>
<div class="r"><span style="color:#6B6A65">Carrier</span><span>UPS</span></div>
<div class="r"><span style="color:#6B6A65">Carrier tracking</span><span class="m" style="font-size:10.5px">1Z999AA10123456784</span></div>
<div class="r"><span style="color:#6B6A65">Mode</span><span>Air express · 5–7 days</span></div>
<div class="dash"></div>
<div style="font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:#6B6A65">Items</div><div class="r"><span>Apple iPad Air 11" <span style="color:#6B6A65">×1</span></span><span class="m">$599.00</span></div><div class="r"><span>Apple Pencil Pro <span style="color:#6B6A65">×1</span></span><span class="m">$129.00</span></div><div class="r"><span>USB-C cable 2 m <span style="color:#6B6A65">×2</span></span><span class="m">$71.00</span></div>
<div class="r"><span style="color:#6B6A65">Declared value</span><span class="m">$799.00</span></div>
<div class="dash"></div>
<div class="r"><span style="color:#6B6A65">Weight · chargeable</span><span class="m">5.6 lb</span></div>
<div class="r"><span>Freight 5.6 lb × $6.00</span><span class="m">$33.60</span></div>
<div class="r"><span>Insurance 1%</span><span class="m">$7.99</span></div>
<div class="r" style="font-size:15px;font-weight:700;padding-top:4px"><span>Estimated total</span><span class="m">$41.59</span></div>
<div class="r" style="color:#6B6A65"><span></span><span>≈ ₵515.72 at 12.40</span></div>
<div class="dash"></div>
<div style="display:flex;justify-content:center"><svg viewBox="0 0 240 56" width="240" height="56" role="img" aria-label="Barcode SL-100483"><rect x="0.00" y="0" width="2.91" height="42" fill="#000"></rect><rect x="4.36" y="0" width="1.45" height="42" fill="#000"></rect><rect x="8.73" y="0" width="1.45" height="42" fill="#000"></rect><rect x="21.82" y="0" width="5.82" height="42" fill="#000"></rect><rect x="30.55" y="0" width="1.45" height="42" fill="#000"></rect><rect x="33.45" y="0" width="2.91" height="42" fill="#000"></rect><rect x="37.82" y="0" width="2.91" height="42" fill="#000"></rect><rect x="43.64" y="0" width="4.36" height="42" fill="#000"></rect><rect x="49.45" y="0" width="1.45" height="42" fill="#000"></rect><rect x="52.36" y="0" width="1.45" height="42" fill="#000"></rect><rect x="55.27" y="0" width="5.82" height="42" fill="#000"></rect><rect x="66.91" y="0" width="2.91" height="42" fill="#000"></rect><rect x="74.18" y="0" width="4.36" height="42" fill="#000"></rect><rect x="80.00" y="0" width="2.91" height="42" fill="#000"></rect><rect x="84.36" y="0" width="2.91" height="42" fill="#000"></rect><rect x="93.09" y="0" width="2.91" height="42" fill="#000"></rect><rect x="97.45" y="0" width="4.36" height="42" fill="#000"></rect><rect x="117.82" y="0" width="8.73" height="42" fill="#000"></rect><rect x="128.00" y="0" width="5.82" height="42" fill="#000"></rect><rect x="138.18" y="0" width="2.91" height="42" fill="#000"></rect><rect x="146.91" y="0" width="5.82" height="42" fill="#000"></rect><rect x="155.64" y="0" width="14.55" height="42" fill="#000"></rect><rect x="177.45" y="0" width="7.27" height="42" fill="#000"></rect><rect x="187.64" y="0" width="7.27" height="42" fill="#000"></rect><rect x="208.00" y="0" width="4.36" height="42" fill="#000"></rect><rect x="213.82" y="0" width="2.91" height="42" fill="#000"></rect><rect x="221.09" y="0" width="2.91" height="42" fill="#000"></rect><rect x="228.36" y="0" width="4.36" height="42" fill="#000"></rect><rect x="234.18" y="0" width="1.45" height="42" fill="#000"></rect><rect x="237.09" y="0" width="2.91" height="42" fill="#000"></rect><text x="120.0" y="55" text-anchor="middle" style="font:10px JetBrains Mono,monospace;fill:#000;letter-spacing:.08em">SL-100483</text></svg></div>
<div style="text-align:center;color:#6B6A65;font-size:10.5px;line-height:1.4">Final price is confirmed when the parcel is weighed in Accra. Track at swiftlane.gh/track · Pickup code is sent when it is ready.</div>
</div></div></div><?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>
