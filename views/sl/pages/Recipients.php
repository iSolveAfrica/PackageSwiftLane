<?php
/**
 * Recipients - generated from docs/handover/artifact-source/Recipients.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  rows                         as $r
 *   value rows
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
        <div class="sl-head">
          <div><h1 class="sl-title">Recipients</h1><p class="sl-lede">People and businesses that receive parcels in Ghana</p></div>
          <div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('Documents') ?>"><i class="ic ic-sm i-download" aria-hidden="true"></i>Export</a><a class="sl-btn" href="<?= sl_route('AddCustomer') ?>"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add Recipient</a></div>
        </div>
        <div class="split">
          <div class="a sl-card flush">
            <div class="row wrap g12" style="padding: 14px 16px">
              <div class="sl-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input aria-label="Search recipients" placeholder="Search name, phone or address"></div>
              <div class="sl-sel" style="width: 150px"><span>All regions</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div>
            </div>
            <div class="sl-tw"><table class="sl-table"><thead><tr><th>Recipient</th><th>Phone</th><th>Region</th><th>Addresses</th><th>Added by</th><th class="num">Parcels</th><th></th></tr></thead><tbody>
              <?php foreach ((array) sl_get($d, 'rows', []) as $__i => $r): ?><tr><td><div class="sl-cell"><span class="sl-av sm"><?= sl_v($r, 'ini') ?></span><span class="fw5"><?= sl_v($r, 'name') ?></span></div></td><td><?= sl_v($r, 'phone') ?></td><td><?= sl_v($r, 'region') ?></td><td><?= sl_v($r, 'addr') ?></td><td><?= sl_v($r, 'by') ?></td><td class="num"><?= sl_v($r, 'n') ?></td><td><a class="sl-ib sm" aria-label="Edit recipient" href="<?= sl_route('AddCustomer') ?>"><i class="ic i-edit" aria-hidden="true"></i></a></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <div class="sl-pager"><span>Showing 1–8 of 942 recipients</span><div class="sl-pg"><a class="on" href="<?= sl_route('Recipients') ?>">1</a><a href="<?= sl_route('Recipients') ?>">2</a><a href="<?= sl_route('Recipients') ?>">3</a></div></div>
          </div>
          <aside class="b sl-card col g12">
            <h2 class="sl-ct">Add recipient</h2>
            <div class="sl-field"><label class="sl-lab" for="n">Full name</label><input id="n" class="sl-in" placeholder="Ama Boateng"></div>
            <div class="sl-field"><span class="sl-lab">Phone</span><div class="row g8"><div class="sl-sel" style="width: 96px; flex: none"><span>+233</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div><input class="sl-in" aria-label="Phone number" placeholder="24 000 0000"></div></div>
            <div class="sl-field"><span class="sl-lab">Region and city</span><div class="sl-sel"><span>Greater Accra · Accra</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div></div>
            <div class="sl-field"><label class="sl-lab" for="a">Street address</label><textarea id="a" class="sl-in" placeholder="House number, street, landmark"></textarea></div>
            <button class="sl-btn block">Save recipient</button>
          </aside>
        </div>
      
