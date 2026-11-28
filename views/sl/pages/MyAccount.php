<?php
/**
 * My account (design: M-CAccount). Data: sl_caAccountData() in views/sl/data/customer_app.php.
 */
if (!isset($d)) { $d = []; }
$caRows = function (array $rows): string {
    $out = '';
    foreach ($rows as [$icon, $label, $href, $count]) {
        $out .= '<a class="mx-row" href="' . h($href) . '"><span class="mx-li"><i class="ic i-' . h($icon) . '" aria-hidden="true"></i></span><span class="mx-rm"><b>' . h($label) . '</b></span>'
            . ($count > 0 ? '<span class="mx-cnt">' . sl_num($count) . '</span>' : '') . '<i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>';
    }
    return $out;
};
$sub = array_filter([$d['locker'] !== '' ? 'Locker ' . $d['locker'] : '', $d['since'] !== '' ? 'Member since ' . $d['since'] : '']);
?>
<div class="mxp wide ca">
  <?= sl_mxHead('My account', '', sl_route('Main'), sl_mxIcon('bell', $d['unread'] ? $d['unread'] . ' unread notifications' : 'Notifications', sl_route('MyNotifications'), (int) $d['unread'])) ?>
  <div class="mxp-grid">
    <div class="mxp-col">
      <section class="mx-card ca-id">
        <?php if ($d['avatar'] !== ''): ?><img class="ca-ava" src="<?= h($d['avatar']) ?>" alt="" width="64" height="64"><?php else: ?><span class="mx-av ca-ava" aria-hidden="true"><?= h($d['ini']) ?></span><?php endif; ?>
        <div class="ca-idm">
          <b><?= h($d['name']) ?></b>
          <?php if ($sub): ?><small><?= h(implode(' · ', $sub)) ?></small><?php endif; ?>
          <?php if ($d['email'] !== ''): ?><small><?= h($d['email']) ?></small><?php endif; ?>
        </div>
        <?php if ($d['tier'] !== ''): ?><a class="ca-tier" href="<?= h(sl_route('MyRewards')) ?>"><i class="ic ic-sm i-star" aria-hidden="true"></i><?= h($d['tier']) ?> · <?= sl_num($d['points']) ?> pts</a><?php endif; ?>
      </section>
      <?php if ($d['locker'] !== ''): ?>
      <section class="mx-card mx-kv">
        <div><span>Locker number</span><b class="mono"><?= h($d['locker']) ?> <button type="button" class="ca-copy" data-sl-copy="<?= h($d['locker']) ?>" aria-label="Copy locker number"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button></b></div>
        <?php if ($d['phone'] !== ''): ?><div><span>Phone</span><b><?= h($d['phone']) ?></b></div><?php endif; ?>
      </section>
      <?php endif; ?>
      <?= sl_mxSec('My shipping addresses') ?>
      <section class="mx-card mx-list">
        <?php foreach ($d['addresses'] as $a): ?>
        <div class="mx-row"><span class="mx-li"><i class="ic i-pin" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($a['label']) ?></b><small><?= h($a['line']) ?></small></span><span class="mx-rr"><button type="button" class="mx-lnk" data-sl-copy="<?= h($a['copy']) ?>" aria-label="Copy the <?= h($a['label']) ?> address">Copy</button></span></div>
        <?php endforeach; ?>
      </section>
      <p class="mx-note">Give this address to the shop when you buy. Your locker number tells us the parcel is yours.</p>
    </div>
    <div class="mxp-col">
      <details class="mx-grp" open><summary><span>My shipping</span><small><?= count($d['shipping']) ?></small><i class="ic ic-sm i-down" aria-hidden="true"></i></summary>
        <section class="mx-card mx-list"><?= $caRows($d['shipping']) ?></section>
      </details>
      <details class="mx-grp" open><summary><span>Account</span><small><?= count($d['account']) ?></small><i class="ic ic-sm i-down" aria-hidden="true"></i></summary>
        <section class="mx-card mx-list"><?= $caRows($d['account']) ?></section>
      </details>
      <div class="mx-btns"><a class="mx-btn sec" href="logout.php"><i class="ic ic-sm i-logout" aria-hidden="true"></i>Sign out</a></div>
    </div>
  </div>
</div>
