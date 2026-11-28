<?php
/**
 * MailboxAddresses - the customer's virtual mailbox addresses (client_virtual_mail_box_addresses.php).
 * Data: sl_mailboxData() — Core::cdp_getVirtualMailboxes() with cdp_mailboxAddressHtml() filling in the locker.
 */
?>
<div class="row g8 sm mut"><i class="ic ic-sm i-user" aria-hidden="true"></i><a href="<?= h(sl_route('UserHome')) ?>">My account</a><i class="ic ic-sm i-right" aria-hidden="true"></i><span style="color:var(--ink)">Shipping addresses</span></div>
<div class="sl-head">
  <div><h1 class="sl-title">My shipping addresses</h1><p class="sl-lede">Shop in any of these countries and ship to the address there. Your locker ID tells us the parcel is yours.</p></div>
  <form method="get" class="row g8"><label class="sl-sel native" style="min-width:220px"><select name="filter_virtual_mail_boxes" aria-label="Country" onchange="this.form.submit()"><option value="">All countries</option><?php foreach ($d['all'] as $mb): ?><option value="<?= (int) $mb->id ?>" <?= (int) $mb->id === $d['filter'] ? 'selected' : '' ?>><?= h($mb->country) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></label></form>
</div>
<?php $laAddr = $d['addr']; $laLocker = $d['locker']; $laName = $d['name']; $laSteps = true; include __DIR__ . '/partials/locker_addresses.php'; ?>
