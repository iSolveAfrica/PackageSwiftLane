<?php
/**
 * Pickup ageing 14-day reminder: the button and its confirmation dialog.
 * Used by views/sl/pages/PickupAging.php and views/sl/pages/Warehouse.php.
 *
 * Expects $paNotify = ['can' => bool, 'packages' => int, 'customers' => int]
 * (sl_pickupAgingNotifyState() in views/sl/data/warehouse.php).
 *
 * Sending to every due customer is never one click: the dialog states how many
 * customers and packages it covers and only submits once the staff member types
 * the customer count. ajax/sl/warehouse_ajax.php (op=aging_notify) checks that
 * number again against what it would send right now and refuses a mismatch.
 */
if (empty($paNotify['can']) || empty($paNotify['packages'])) return;
$paC = (int) $paNotify['customers'];
$paP = (int) $paNotify['packages'];
?>
<button type="button" class="sl-btn ghost" data-sl-open="pa-notify"><i class="ic ic-sm i-send" aria-hidden="true"></i>Remind <?= $paC ?> customer<?= $paC === 1 ? '' : 's' ?>…</button>
<div class="sl-dialog" id="pa-notify" hidden><form class="sl-modal col g12" data-sl-form data-sl-reload action="ajax/sl/warehouse_ajax.php" method="post" role="dialog" aria-label="Send the 14-day reminder">
  <input type="hidden" name="op" value="aging_notify">
  <div class="row g10"><span class="sl-tile"><i class="ic i-alert" aria-hidden="true"></i></span><div class="col grow"><b class="fw5">Message <?= $paC ?> customer<?= $paC === 1 ? '' : 's' ?>?</b><span class="mut sm">This sends the 14-day pickup reminder by WhatsApp and e-mail to <b><?= $paC ?> customer<?= $paC === 1 ? '' : 's' ?></b> about <b><?= $paP ?> package<?= $paP === 1 ? '' : 's' ?></b>, and sets those packages to Pending Collection. It cannot be undone.</span></div></div>
  <label class="sl-field"><span class="sl-lab">Type <?= $paC ?> to confirm</span><input class="sl-in" name="confirm_customers" inputmode="numeric" autocomplete="off" required pattern="<?= $paC ?>" title="Type <?= $paC ?> to confirm, or press Cancel" placeholder="Number of customers"></label>
  <div class="row g8"><button type="button" class="sl-btn ghost" data-sl-close>Cancel</button><button class="sl-btn" type="submit">Send to <?= $paC ?> customer<?= $paC === 1 ? '' : 's' ?></button></div>
</form></div>
