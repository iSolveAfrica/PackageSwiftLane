<?php
/** M-Notifs: the signed-in person's notifications (staff and riders). $d = views/sl/data/notifications.php. */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['tab'] = 'Alerts';
$back = sl_userIs('driver') ? sl_route('RiderHome') : sl_route('Main');
$pages = max(1, (int) ceil(($d['count'] ?? 0) / max(1, $d['per'] ?? 25)));
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-ops.css')) ?>">
<?= sl_phoneTop('Notifications', $d['unread'] ? sl_num($d['unread']) . ' unread' : 'All read', ['back' => $back]) ?>
<main class="mx-body">
<nav class="mx-seg" aria-label="Show">
<?php foreach ($d['filters'] as $f): $on = $d['filter'] === $f['f']; ?><a class="mx-segl<?= $on ? ' on' : '' ?>" href="<?= h(sl_route('Notifications', $f['f'] === 'all' ? [] : ['filter' => $f['f']])) ?>"<?= $on ? ' aria-current="page"' : '' ?>><?= h($f['label']) ?><?= ($f['f'] === 'unread' && $d['unread'] ? ' ' . sl_num($d['unread']) : '') ?></a><?php endforeach; ?>
</nav>
<?php if ($d['items']): ?>
<section class="mx-card mx-list">
<?php foreach ($d['items'] as $n): ?>
<div class="mx-row<?= $n['read'] ? '' : ' unread' ?>"><span class="mx-li"><i class="ic <?= h($n['ic']) ?>" aria-hidden="true"></i></span><?php if ($n['href']): ?><a class="mx-rm" href="<?= h($n['href']) ?>"><?php else: ?><span class="mx-rm"><?php endif; ?><b><?= h($n['t']) ?></b><small><?= h($n['d']) ?></small><?= $n['href'] ? '</a>' : '</span>' ?><span class="mx-rr"><small><?= h(str_replace(' ago', '', $n['w'])) ?></small><?php if (!$n['read']): ?><button type="button" class="mx-dotbtn" aria-label="Mark as read" title="Mark as read" data-sl-action="ajax/sl/notifications_ajax.php" data-p-op="read" data-p-id="<?= (int) $n['id'] ?>" data-sl-reload><i aria-hidden="true"></i></button><?php endif; ?></span></div>
<?php endforeach; ?>
</section>
<?php if ($pages > 1): ?>
<div class="mx-btns" style="flex-direction:row">
<?php if ($d['page'] > 1): ?><a class="mx-btn sec" style="flex:1" href="<?= h(sl_url(['page' => $d['page'] - 1])) ?>">Newer</a><?php endif; ?>
<?php if ($d['page'] < $pages): ?><a class="mx-btn sec" style="flex:1" href="<?= h(sl_url(['page' => $d['page'] + 1])) ?>">Older</a><?php endif; ?>
</div>
<?php endif; ?>
<?php else: ?>
<?= sl_mxEmpty('bell', $d['filter'] === 'unread' ? 'You are all caught up' : 'No notifications yet', 'Updates about shipments, payments and your account show up here.') ?>
<?php endif; ?>
<div class="mx-btns">
<?php if ($d['unread']): ?><button type="button" class="mx-btn" data-sl-action="ajax/sl/notifications_ajax.php" data-p-op="read_all" data-sl-reload>Mark all as read</button><?php endif; ?>
</div>
<details class="mx-grp"><summary><span>Notification settings</span><small><?= count($d['events']) + count($d['channels']) ?></small><i class="ic ic-sm i-down" aria-hidden="true"></i></summary>
<section class="mx-card">
<?php foreach ($d['events'] as $e): ?><?= sl_mxToggle($e['label'], 'In the app', (bool) $e['on'], 'ajax/sl/notifications_ajax.php', $e['key'], ['op' => 'pref', 'channel' => 'inapp']) ?><?php endforeach; ?>
</section>
<section class="mx-card">
<?php foreach ($d['channels'] as $c): ?><?= sl_mxToggle($c['label'], 'Send me notifications by ' . strtolower($c['label']), (bool) $c['on'], 'ajax/sl/notifications_ajax.php', 'all', ['op' => 'pref', 'channel' => $c['key']]) ?><?php endforeach; ?>
</section>
</details>
</main>
