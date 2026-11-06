<?php
/**
 * PaymentReturn - outcome of a mobile-money checkout (payment_return.php). $d: state (success|pending|failed),
 * message and reference, established server-side by cdp_fsCompleteIntent() in payment_return.php.
 */
$map = [
    'success' => ['i-check', 'var(--green)', 'Payment confirmed', 'Thank you. Your packages are now cleared for delivery.', 'ok'],
    'pending' => ['i-clock', 'var(--amber-ink)', 'Payment pending', 'We have not had confirmation yet. If you completed the prompt on your phone, this clears shortly on its own: you do not need to pay again.', 'warn'],
    'failed'  => ['i-x', 'var(--red-ink)', 'Payment not confirmed', 'No money has been taken for this attempt. You can try again from My bills.', 'bad'],
];
$v = $map[$d['state']] ?? $map['failed'];
?>
<div class="row g8 sm mut"><i class="ic ic-sm i-receipt" aria-hidden="true"></i><a href="<?= h(sl_route('MyBills')) ?>">My bills</a><i class="ic ic-sm i-right" aria-hidden="true"></i><span style="color:var(--ink)">Payment</span></div>
<section class="sl-card col g16" style="max-width:560px;margin:32px auto;text-align:center;align-items:center;padding:32px">
  <span class="sl-tile" style="width:64px;height:64px;color:<?= h($v[1]) ?>"><i class="ic <?= h($v[0]) ?>" aria-hidden="true" style="width:30px;height:30px"></i></span>
  <h1 class="sl-title"><?= h($v[2]) ?></h1>
  <p class="sl-lede" style="max-width:420px"><?= h($v[3]) ?></p>
  <?php if ($d['message'] !== '' && $d['state'] !== 'success'): ?><div class="sl-banner <?= h($v[4]) ?>" style="text-align:left"><?= h($d['message']) ?></div><?php endif; ?>
  <?php if ($d['reference'] !== ''): ?><div class="mut sm">Reference <b class="mono"><?= h($d['reference']) ?></b></div><?php endif; ?>
  <div class="row g8 wrap" style="justify-content:center"><a class="sl-btn" href="<?= h(sl_route('MyBills')) ?>"><i class="ic ic-sm i-receipt" aria-hidden="true"></i>Back to my bills</a><a class="sl-btn ghost" href="index.php">Dashboard</a></div>
</section>
