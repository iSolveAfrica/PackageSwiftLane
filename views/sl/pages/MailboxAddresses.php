<?php
/**
 * MailboxAddresses - the customer's virtual mailbox addresses (client_virtual_mail_box_addresses.php).
 * Data: sl_mailboxData() — Core::cdp_getVirtualMailboxes() with cdp_mailboxAddressHtml() filling in the locker.
 */
?>
<div class="row g8 sm mut"><i class="ic ic-sm i-user" aria-hidden="true"></i><a href="<?= h(sl_route('UserHome')) ?>">My account</a><i class="ic ic-sm i-right" aria-hidden="true"></i><span style="color:var(--ink)">Shipping addresses</span></div>
<div class="sl-head">
  <div><h1 class="sl-title">My shipping addresses</h1><p class="sl-lede">Use these addresses when you shop online. Your locker <b class="mono"><?= h($d['locker']) ?></b> tells us the parcel is yours.</p></div>
  <form method="get" class="row g8"><label class="sl-sel native" style="min-width:220px"><select name="filter_virtual_mail_boxes" aria-label="Country" onchange="this.form.submit()"><option value="">All countries</option><?php foreach ($d['all'] as $mb): ?><option value="<?= (int) $mb->id ?>" <?= (int) $mb->id === $d['filter'] ? 'selected' : '' ?>><?= h($mb->country) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></label></form>
</div>
<?php if (!$d['list']): ?>
<div class="sl-card sl-empty"><span class="sl-tile"><i class="ic i-pin" aria-hidden="true"></i></span>No warehouse address is set up yet. New addresses appear here as soon as we add them.</div>
<?php else: ?>
<div class="grid cols2" style="gap:16px">
<?php foreach ($d['list'] as $a): ?>
  <section class="sl-card col g12">
    <div class="sl-ch" style="margin:0"><div class="row g10"><span class="sl-tile mono"><?= h($a['cc'] ?: '··') ?></span><h2 class="sl-ct"><?= h($a['country']) ?></h2></div><button class="sl-btn ghost sm" type="button" data-sl-copy="<?= h($a['plain']) ?>"><i class="ic ic-sm i-copy" aria-hidden="true"></i>Copy</button></div>
    <div class="sl-kv">
      <div><span>Address</span><span style="text-align:right"><?= $a['html'] /* cdp_mailboxAddressHtml() escapes plain addresses; HTML ones are admin-authored */ ?></span></div>
      <div><span>Locker ID</span><span class="mono row g6"><?= h($d['locker']) ?><button class="sl-ib sm" type="button" aria-label="Copy locker" data-sl-copy="<?= h($d['locker']) ?>"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button></span></div>
      <div><span>City</span><span><?= h($a['city']) ?: '—' ?></span></div>
      <div><span>Postcode</span><span class="mono"><?= h($a['postcode']) ?: '—' ?></span></div>
    </div>
  </section>
<?php endforeach; ?>
</div>
<?php endif; ?>
