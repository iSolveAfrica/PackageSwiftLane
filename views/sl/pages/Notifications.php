<?php
/**
 * Notifications - from docs/handover/artifact-source/Notifications.dc.html.
 * Data: views/sl/data/notifications.php (sl_notificationsData).
 */
if (!isset($d)) { $d = []; }
?>
        <div class="sl-head">
          <div><h1 class="sl-title">Notifications</h1><p class="sl-lede"><?= (int) $d['unread'] ?> unread · choose what reaches you and how</p></div>
          <button class="sl-btn ghost" data-sl-action="ajax/sl/notifications_ajax.php" data-p-op="read_all" data-sl-reload <?= $d['unread'] ? '' : 'disabled' ?>>Mark All Read</button>
        </div>
        <div class="split">
          <section class="a sl-card flush">
            <div class="row between wrap g12" style="padding: 14px 16px"><div class="sl-seg boxed"><?php foreach ($d['filters'] as $f): ?><a class="<?= $f['f'] === $d['filter'] ? 'on' : '' ?>" href="<?= h(sl_url(['filter' => $f['f'], 'page' => null])) ?>"><?= h($f['label']) ?></a><?php endforeach; ?></div><span class="mut sm"><?= sl_num($d['count']) ?> notifications</span></div>
            <?php foreach ($d['items'] as $n): ?><div class="row g12 top" style="padding: 14px 16px; border-top: 1px solid var(--line)"><span class="sl-tile"><i class="ic <?= h($n['ic']) ?>" aria-hidden="true"></i></span><div class="grow"><div class="fw5"><?= h($n['t']) ?></div><div class="mut sm"><?= h($n['d']) ?><?php if ($n['href']): ?> · <a href="<?= h($n['href']) ?>">Open</a><?php endif; ?></div></div><div class="col g6 end"><span class="mut sm"><?= h($n['w']) ?></span><?php if (!$n['read']): ?><button class="sl-dot g" type="button" style="border:0;cursor:pointer" aria-label="Mark as read" title="Mark as read" data-sl-action="ajax/sl/notifications_ajax.php" data-p-op="read" data-p-id="<?= (int) $n['id'] ?>" data-sl-reload></button><?php else: ?><span class="sl-dot"></span><?php endif; ?></div></div><?php endforeach; ?>
            <?php if (!$d['items']): ?><div class="sl-empty" style="padding:28px"><span class="sl-tile"><i class="ic i-bell" aria-hidden="true"></i></span>Nothing here yet. Shipment, package and account updates will show up on this page.</div><?php endif; ?>
            <?= sl_pager($d['page'], $d['per'], $d['count'], 'notifications') ?>
          </section>
          <aside class="b sl-card col g12"><h2 class="sl-ct">Delivery preferences</h2>
            <?php if (!$d['hasPrefs']): ?><span class="mut sm">Preferences are saved once the database upgrade is applied.</span><?php endif; ?>
            <?php foreach ($d['events'] as $e): ?><div class="row between"><span><?= h($e['label']) ?></span><span class="sl-toggle <?= $e['on'] ? 'on' : '' ?>" role="switch" aria-checked="<?= $e['on'] ? 'true' : 'false' ?>" aria-label="<?= h($e['label']) ?>" data-sl-save="ajax/sl/notifications_ajax.php" data-key="<?= h($e['key']) ?>" data-p-op="pref" data-p-channel="inapp"></span></div><?php endforeach; ?>
            <hr class="hr">
            <h2 class="sl-ct">Channels</h2>
            <div class="row wrap g6"><?php foreach ($d['channels'] as $c): ?><span class="row g6 sm"><span class="sl-toggle <?= $c['on'] ? 'on' : '' ?>" role="switch" aria-checked="<?= $c['on'] ? 'true' : 'false' ?>" aria-label="<?= h($c['label']) ?>" data-sl-save="ajax/sl/notifications_ajax.php" data-key="all" data-p-op="pref" data-p-channel="<?= h($c['key']) ?>"></span><?= h($c['label']) ?></span><?php endforeach; ?></div>
          </aside>
        </div>
