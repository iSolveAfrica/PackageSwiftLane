<?php
/**
 * NewPackage - generated from docs/handover/artifact-source/NewPackage.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
        <div class="sl-head">
          <div><a href="<?= sl_route('SeaPackages') ?>" class="row g6 mut sm"><i class="ic ic-sm i-left" aria-hidden="true"></i>Sea Packages</a><h1 class="sl-title" style="margin-top: 4px">New Package</h1></div>
          <div class="row g8"><a class="sl-btn ghost" href="<?= sl_route('AirShipments') ?>">Save draft</a><a class="sl-btn" href="<?= sl_route('PackageDetail') ?>">Create package</a></div>
        </div>
        <div class="split">
          <div class="a col g16">
            <section class="sl-card col g16">
              <div class="row between wrap g12"><h2 class="sl-ct">Customer</h2><a class="sl-btn ghost sm" href="<?= sl_route('PreAlerts') ?>"><i class="ic ic-sm i-inbox" aria-hidden="true"></i>Import from pre-alert</a></div>
              <div class="grid cols2">
                <div class="sl-field"><span class="sl-lab">Customer</span><div class="sl-sel"><span class="row g8"><span class="sl-av sm">EO</span>Efua Owusu · SL-3318</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div></div>
                <div class="sl-field"><span class="sl-lab">Delivery address</span><div class="sl-sel"><span>6 Harbour Rd, Takoradi</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div></div>
              </div>
              <div class="sl-banner ok"><i class="ic i-check" aria-hidden="true"></i><span>Pre-alert matched: <strong>3 boxes from Walmart</strong> received Nov 3.</span></div>
            </section>
            <section class="sl-card col g16">
              <h2 class="sl-ct">Package details</h2>
              <div class="grid cols3">
                <div class="sl-field"><span class="sl-lab">Container</span><div class="sl-sel"><span>SLC-4472 · closes Nov 12</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div></div>
                <div class="sl-field"><span class="sl-lab">Package type</span><div class="sl-sel"><span>Boxes</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div></div>
                <div class="sl-field"><span class="sl-lab">Warehouse</span><div class="sl-sel"><span>Newark, NJ</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div></div>
                <div class="sl-field"><label class="sl-lab" for="n">Supplier tracking</label><input id="n" class="sl-in mono" value="9400111899223197428"></div>
                <div class="sl-field"><span class="sl-lab">Packaging</span><div class="sl-sel"><span>Standard carton</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div></div>
                <div class="sl-field"><span class="sl-lab">Incoterm</span><div class="sl-sel"><span>DAP</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div></div>
              </div>
            </section>
            <section class="sl-card flush">
              <div class="row between" style="padding: 16px 20px"><h2 class="sl-ct">Boxes</h2><button class="sl-btn ghost sm"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add box</button></div>
              <div class="sl-tw"><table class="sl-table"><thead><tr><th>#</th><th>Contents</th><th class="num">L × W × H (in)</th><th class="num">Weight (lb)</th><th class="num">Volume</th><th></th></tr></thead><tbody>
                <tr><td>1</td><td>Kitchen appliances</td><td class="num mono">24 × 18 × 16</td><td class="num">32</td><td class="num">4.0 ft³</td><td><button class="sl-ib sm" aria-label="Remove box"><i class="ic i-trash" aria-hidden="true"></i></button></td></tr>
                <tr><td>2</td><td>Clothing</td><td class="num mono">20 × 20 × 18</td><td class="num">18</td><td class="num">4.2 ft³</td><td><button class="sl-ib sm" aria-label="Remove box"><i class="ic i-trash" aria-hidden="true"></i></button></td></tr>
                <tr><td>3</td><td>Shoes</td><td class="num mono">18 × 12 × 10</td><td class="num">9</td><td class="num">1.3 ft³</td><td><button class="sl-ib sm" aria-label="Remove box"><i class="ic i-trash" aria-hidden="true"></i></button></td></tr>
              </tbody></table></div>
            </section>
            <section class="sl-card"><div class="sl-ch"><h2 class="sl-ct">Photos</h2></div><div class="sl-drop"><i class="ic ic-lg i-upload" aria-hidden="true"></i><span class="fw5" style="color: var(--ink)">Drop photos or browse</span><span class="sm">Add a photo of each box before sealing</span></div></section>
          </div>
          <aside class="b col g16">
            <section class="sl-card col g12"><h2 class="sl-ct">Price</h2>
              <div class="sl-kv"><div><span>Volume</span><span>9.5 ft³</span></div><div><span>Rate (Sea, US → Takoradi)</span><span>$14 / ft³</span></div><div><span>Freight</span><span>$133.00</span></div><div><span>Port handling</span><span>$18.00</span></div></div>
              <div class="row between"><span class="fw5">Total due</span><span class="big">$151.00</span></div>
              <a class="sl-btn block" href="<?= sl_route('PackageDetail') ?>">Create package</a>
            </section>
            <section class="sl-card col g10"><div class="row between"><span class="sl-ct">Container SLC-4472</span><span class="sl-badge warn">Filling</span></div><div class="sl-mv" style="margin: 0">1,420 / 2,000 <i>ft³ booked</i></div><div class="sl-ticks y"><i style="width: 71%"></i></div><p class="mut sm">Closes Nov 12 · ETA Takoradi Dec 21</p></section>
          </aside>
        </div>
      
