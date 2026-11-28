<?php
/** M-CAccount: the customer's menu. Data: sl_caAccountData() (views/sl/data/customer_app.php). */
$slPhone['tab'] = 'Menu';
$caRows = function (array $rows): string {
    $out = '';
    foreach ($rows as [$icon, $label, $href, $count]) {
        $out .= '<a class="mx-row" href="' . h($href) . '"><span class="mx-li"><i class="ic i-' . h($icon) . '" aria-hidden="true"></i></span><span class="mx-rm"><b>' . h($label) . '</b></span>'
            . ($count > 0 ? '<span class="mx-cnt">' . sl_num($count) . '</span>' : '') . '<i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>';
    }
    return $out;
};
$sub = implode(' · ', array_filter([$d['locker'] !== '' ? 'Locker ' . $d['locker'] : '', $d['tier'] !== '' ? $d['tier'] . ' member' : '']));
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pages/CustomerApp.css')) ?>">
<?= sl_phoneTop('Menu', trim($d['name'] . ($d['locker'] !== '' ? ' · ' . $d['locker'] : '')), ['avatar' => $d['ini'], 'href' => sl_route('UserHome')], sl_phoneIcon('search', 'Track a parcel', sl_route('PublicTracking')) . sl_phoneIcon('bell', 'Notifications', sl_route('MyNotifications'), (int) $d['unread'])) ?>
<main class="mx-body ca">
<a class="b3-me" href="<?= h(sl_route('UserHome')) ?>"><span class="b3-av lg" aria-hidden="true"><?= h($d['ini']) ?></span><span class="mx-rm"><b><?= h($d['name']) ?></b><?php if ($sub !== ''): ?><small><?= h($sub) ?></small><?php endif; ?><span class="b3-me2"><?php if ($d['idVerified']): ?><span class="b3-st ok">ID verified</span><?php endif; ?><?php if ($d['since'] !== ''): ?><span class="b3-st">Since <?= h($d['since']) ?></span><?php endif; ?><?php if ($d['points'] !== null): ?><span class="b3-st"><?= sl_num($d['points']) ?> points</span><?php endif; ?></span></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
<section class="b3-sc">
<a href="<?= h(sl_route('UserHome')) ?>"><span><i class="ic i-user" aria-hidden="true"></i></span><small>My account</small></a>
<?php if ($d['canPickup']): ?><a href="pickup_add.php"><span><i class="ic i-truck" aria-hidden="true"></i></span><small>Request pickup</small></a>
<?php else: ?><a href="<?= h(sl_route('DeliveryRequest')) ?>"><span><i class="ic i-truck" aria-hidden="true"></i></span><small>Deliver to my door</small></a><?php endif; ?>
<a href="<?= h(sl_route('UserHome')) ?>#addresses"><span><i class="ic i-pin" aria-hidden="true"></i></span><small>Shipping addresses</small></a>
<a href="<?= h(sl_route('AccountSecurity')) ?>"><span><i class="ic i-lock" aria-hidden="true"></i></span><small>Password and sign-in</small></a>
<a href="<?= h(sl_route('PickupCodes')) ?>"><span><i class="ic i-key" aria-hidden="true"></i></span><small>Pickup codes</small></a>
<a href="<?= h(sl_route('CustomerNotifications')) ?>"><span><i class="ic i-bell" aria-hidden="true"></i></span><small>Notification settings</small></a>
</section>
<section class="b3-cd b3-th"><span class="mx-li"><i class="ic i-sun" aria-hidden="true"></i></span><span class="mx-rm"><b>Theme</b><small>Light or dark, saved on your account</small></span><div class="b3-mini" role="group" aria-label="Theme"><button type="button" data-sl-set-theme="light">Light</button><button type="button" data-sl-set-theme="dark">Dark</button></div></section>
<?php if ($d['addresses']): ?>
<div class="mx-sec"><h2>My shipping addresses</h2><a href="<?= h(sl_route('UserHome')) ?>#addresses">All</a></div>
<section class="mx-card mx-list">
<?php foreach ($d['addresses'] as $a): ?>
<div class="mx-row"><span class="mx-li"><i class="ic i-pin" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($a['label']) ?></b><small><?= h($a['line']) ?></small></span><span class="mx-rr"><button type="button" class="mx-lnk" data-sl-copy="<?= h($a['copy']) ?>" aria-label="Copy the <?= h($a['label']) ?> address">Copy</button></span></div>
<?php endforeach; ?>
</section>
<?php endif; ?>
<details class="mx-grp" open><summary><span>My shipping</span><small><?= count($d['shipping']) ?></small><i class="ic ic-sm i-down" aria-hidden="true"></i></summary><section class="mx-card mx-list"><?= $caRows($d['shipping']) ?></section></details>
<details class="mx-grp" open><summary><span>Account</span><small><?= count($d['account']) ?></small><i class="ic ic-sm i-down" aria-hidden="true"></i></summary><section class="mx-card mx-list"><?= $caRows($d['account']) ?></section></details>
<a class="b3-cd b3-help" href="support.php"><span class="b3-oi"><i class="ic i-help" aria-hidden="true"></i></span><span class="mx-rm"><b>Help and support</b><small>Chat, requests and the help centre</small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
<a class="mx-btn sec" href="logout.php"><i class="ic ic-sm i-logout" aria-hidden="true"></i>Sign out</a>
<p class="b3-cap"><?= h($core->site_name ?? 'Swift Lane') ?> customer app · <a href="<?= h(sl_route('Main')) ?>?view=web">view the full website</a></p>
</main>
