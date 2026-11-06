<?php
/**
 * LockerSearch - generated from docs/handover/artifact-source/LockerSearch.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   value noop
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
        <div class="sl-head"><div><h1 class="sl-title">Locker Search</h1><p class="sl-lede">Find a customer’s locker, virtual mailbox and parcels in one lookup</p></div></div>
        <form class="row wrap g8" data-sl-on="submit:noop">
          <div class="sl-search" style="max-width: none; height: 52px; flex: 1 1 320px"><i class="ic i-search" aria-hidden="true"></i><input aria-label="Search" value="SL-3318"></div>
          <button class="sl-btn" style="height: 52px; padding: 0 28px">Search</button>
        </form>
        <div class="split">
          <div class="a col g16">
            <section class="sl-card row g16 wrap">
              <span class="sl-av lg dark">EO</span>
              <div class="grow"><div class="row g8 wrap"><h2 class="sl-ct" style="font-size: 18px">Efua Owusu</h2><span class="sl-badge ok">Verified</span></div><div class="mut">SL-3318 · efua@example.com · +233 20 111 2233</div></div>
              <a class="sl-btn ghost" href="<?= sl_route('CustomerProfile') ?>">Open profile</a>
            </section>
            <section class="sl-card flush">
              <div class="row between" style="padding: 16px 20px"><h2 class="sl-ct">Parcels held for Efua</h2><span class="mut sm">3 in warehouse · 1 on the way</span></div>
              <div class="sl-tw"><table class="sl-table"><thead><tr><th>Parcel</th><th>Carrier tracking</th><th>Bay</th><th class="num">Weight</th><th>Status</th></tr></thead><tbody>
                <tr><td><a class="id" href="<?= sl_route('ShipmentDetail') ?>">SL-100480</a></td><td class="mono sm mut">1Z999AA10198765432</td><td class="mono sm">B-12</td><td class="num">5.6 kg</td><td><span class="sl-pill sorting">At warehouse</span></td></tr>
                <tr><td><a class="id" href="<?= sl_route('ShipmentDetail') ?>">SL-100455</a></td><td class="mono sm mut">9400111899220011009</td><td class="mono sm">B-30</td><td class="num">1.2 kg</td><td><span class="sl-pill sorting">At warehouse</span></td></tr>
                <tr><td><a class="id" href="<?= sl_route('PackageDetail') ?>">PK-20931</a></td><td class="mono sm mut">—</td><td class="mono sm">Sea</td><td class="num">14.2 ft³</td><td><span class="sl-pill transit">At sea</span></td></tr>
              </tbody></table></div>
            </section>
          </div>
          <aside class="b col g16">
            <section class="sl-card col g12"><div class="row between"><h2 class="sl-ct">Virtual mailbox</h2><button class="sl-ib sm" aria-label="Copy address"><i class="ic i-copy" aria-hidden="true"></i></button></div>
              <div class="sl-kv"><div><span>Name</span><span>Efua Owusu SL-3318</span></div><div><span>Street</span><span>[Warehouse street address]</span></div><div><span>City, state, ZIP</span><span>[City, ST ZIP]</span></div></div>
            </section>
            <section class="sl-card col g10"><div class="row between"><span class="sl-ct">Locker usage</span><span class="sl-badge ok">2 bays</span></div><div class="sl-mv" style="margin: 0">2 / 5 <i>free bays on plan</i></div><div class="sl-ticks"><i style="width: 40%"></i></div></section>
          </aside>
        </div>
      
