<?php
/** M-CNotifs: the customer's notification feed. Data: sl_caNotificationsData(). Mark read: ajax/sl/notifications_ajax.php. */
$slPhone['scripts'][] = 'dataJs/sl/customer_app.js';
$chip = fn(string $f, string $label) => '<a class="ca-chip' . ($d['filter'] === $f ? ' on' : '') . '" href="' . h(sl_url(['filter' => $f === 'all' ? null : $f, 'page' => null])) . '"' . ($d['filter'] === $f ? ' aria-current="true"' : '') . '>' . h($label) . '</a>';
$pages = (int) ceil($d['count'] / max(1, $d['per']));
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pages/CustomerApp.css')) ?>">
<?= sl_phoneTop('Notifications', 'Parcels, money and account', ['back' => sl_route('MyAccount')], sl_phoneIcon('sliders', 'Notification settings', sl_route('CustomerNotifications'))) ?>
<main class="mx-body ca">
<div class="b3-nh"><span><b><?= $d['unread'] ? sl_num($d['unread']) . ' unread' : 'All read' ?></b><small><?= sl_num($d['count']) ?> <?= $d['filter'] === 'unread' ? 'unread' : 'in total' ?></small></span><?php if ($d['unread']): ?><button type="button" class="b3-gh sm" data-sl-action="ajax/sl/notifications_ajax.php" data-p-op="read_all" data-sl-reload><i class="ic ic-sm i-check" aria-hidden="true"></i>Mark all read</button><?php endif; ?></div>
<div class="b3-chs" role="group" aria-label="Filter"><?= $chip('all', 'All') ?><?= $chip('unread', 'Unread' . ($d['unread'] ? ' ' . sl_num($d['unread']) : '')) ?><?= $chip('shipments', 'Shipments') ?></div>
<?php if ($d['items']): ?>
<section class="mx-card mx-list">
<?php foreach ($d['items'] as $n): ?>
<div class="mx-row b3-nr<?= $n['read'] ? '' : ' un' ?>"><span class="mx-li"><i class="ic i-<?= h($n['icon']) ?>" aria-hidden="true"></i></span>
<?php if ($n['href'] !== ''): ?><a class="mx-rm" href="<?= h($n['href']) ?>"<?= $n['read'] ? '' : ' data-ca-read="' . (int) $n['id'] . '"' ?>><b><?= h($n['title']) ?></b><small><?= h($n['sub']) ?></small></a>
<?php else: ?><span class="mx-rm"><b><?= h($n['title']) ?></b><small><?= h($n['sub']) ?></small></span><?php endif; ?>
<span class="b3-nw"><small><?= h($n['when']) ?></small><?php if (!$n['read']): ?><button type="button" class="b3-ud on" aria-label="Mark as read" data-sl-action="ajax/sl/notifications_ajax.php" data-p-op="read" data-p-id="<?= (int) $n['id'] ?>" data-sl-reload></button><?php else: ?><span class="b3-ud" aria-hidden="true"></span><?php endif; ?></span></div>
<?php endforeach; ?>
</section>
<?php if ($pages > 1): ?><div class="mx-two"><?php if ($d['page'] > 1): ?><a class="b3-gh" href="<?= h(sl_url(['page' => $d['page'] - 1])) ?>">Newer</a><?php else: ?><span></span><?php endif; ?><?php if ($d['page'] < $pages): ?><a class="b3-gh" href="<?= h(sl_url(['page' => $d['page'] + 1])) ?>">Older</a><?php endif; ?></div><?php endif; ?>
<?php else: ?>
<section class="b3-em"><span class="b3-oi"><i class="ic i-bell" aria-hidden="true"></i></span><b><?= $d['filter'] === 'all' ? 'No notifications yet' : 'You are all caught up' ?></b><small>We tell you here when a parcel moves.</small><a class="b3-gh" href="<?= h(sl_route('PublicTracking')) ?>"><i class="ic ic-sm i-search" aria-hidden="true"></i>Track a parcel</a></section>
<?php endif; ?>
<a class="b3-cd b3-rw mx-row" href="<?= h(sl_route('CustomerNotifications')) ?>"><span class="mx-li"><i class="ic i-sliders" aria-hidden="true"></i></span><span class="mx-rm"><b>Delivery preferences</b><small>Choose what reaches you by email, SMS or WhatsApp</small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
</main>
