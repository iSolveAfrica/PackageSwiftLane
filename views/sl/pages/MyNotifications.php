<?php
/**
 * Notifications (design: M-CNotifs). Data: sl_caNotificationsData(). Marking read uses the existing
 * endpoint ajax/sl/notifications_ajax.php (op read / read_all).
 */
if (!isset($d)) { $d = []; }
$markAll = $d['unread'] ? '<button type="button" class="mx-ib" data-sl-action="ajax/sl/notifications_ajax.php" data-p-op="read_all" data-sl-reload aria-label="Mark all as read"><i class="ic i-check" aria-hidden="true"></i></button>' : '';
$markAll .= sl_mxIcon('sliders', 'Notification settings', sl_route('CustomerNotifications'));
?>
<div class="mxp wide ca">
  <?= sl_mxHead('Notifications', $d['unread'] ? sl_num($d['unread']) . ' unread' : 'All read', sl_route('MyAccount'), $markAll) ?>
  <div class="mx-seg" role="group" aria-label="Show">
    <a class="ca-segb<?= $d['filter'] === 'all' ? ' on' : '' ?>" href="<?= h(sl_url(['filter' => null, 'page' => null])) ?>"<?= $d['filter'] === 'all' ? ' aria-current="true"' : '' ?>>All</a>
    <a class="ca-segb<?= $d['filter'] === 'unread' ? ' on' : '' ?>" href="<?= h(sl_url(['filter' => 'unread', 'page' => null])) ?>"<?= $d['filter'] === 'unread' ? ' aria-current="true"' : '' ?>>Unread<?= $d['unread'] ? ' · ' . sl_num($d['unread']) : '' ?></a>
    <a class="ca-segb<?= $d['filter'] === 'shipments' ? ' on' : '' ?>" href="<?= h(sl_url(['filter' => 'shipments', 'page' => null])) ?>"<?= $d['filter'] === 'shipments' ? ' aria-current="true"' : '' ?>>Shipments</a>
  </div>
  <?php if (!$d['items']): ?>
    <?= $d['filter'] !== 'all'
        ? sl_mxEmpty('check', 'Nothing here', 'No notifications match this filter.', 'Show all notifications', sl_url(['filter' => null, 'page' => null]))
        : sl_mxEmpty('bell', 'No notifications yet', 'Updates about your parcels, deliveries and account show up here.', 'See my parcels', sl_route('MyParcels')) ?>
  <?php else: ?>
  <section class="mx-card mx-list ca-feed">
    <?php foreach ($d['items'] as $n): $tag = $n['href'] !== '' ? 'a' : 'div'; ?>
    <div class="ca-n<?= $n['read'] ? '' : ' new' ?>">
      <<?= $tag ?> class="mx-row"<?= $n['href'] !== '' ? ' href="' . h($n['href']) . '"' : '' ?><?= !$n['read'] && $n['href'] !== '' ? ' data-ca-read="' . (int) $n['id'] . '"' : '' ?>>
        <span class="mx-li"><i class="ic i-<?= h($n['icon']) ?>" aria-hidden="true"></i></span>
        <span class="mx-rm"><b><?= h($n['title']) ?></b><small><?= h($n['sub']) ?> · <?= h($n['when']) ?><?= $n['read'] ? '' : ' · unread' ?></small></span>
        <?php if ($n['href'] !== ''): ?><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i><?php endif; ?>
      </<?= $tag ?>>
      <?php if (!$n['read']): ?><button type="button" class="ca-nread" data-sl-action="ajax/sl/notifications_ajax.php" data-p-op="read" data-p-id="<?= (int) $n['id'] ?>" data-sl-reload aria-label="Mark as read"><i aria-hidden="true"></i></button><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </section>
  <?= sl_pager($d['page'], $d['per'], $d['count'], 'notifications') ?>
  <?php endif; ?>
  <p class="mx-note">Choose what reaches you by email, SMS or WhatsApp in <a class="mx-lnk" href="<?= h(sl_route('CustomerNotifications')) ?>">notification settings</a>.</p>
</div>
