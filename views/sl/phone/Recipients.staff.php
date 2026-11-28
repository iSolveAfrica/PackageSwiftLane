<?php
/**
 * M-Recipients (staff): every recipient in scope (recipients_admin_list.php). Same $d as the web page
 * (views/sl/data/recipients.php): search q, page. Add opens AddCustomer?type=recipient like the web button.
 */
require_once CDP_ROOT . '/views/sl/data/phone_people.php';
$slPhone['nav'] = 'staff';
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-people.css')) ?>">
<?= sl_phoneTop('Recipients', sl_num($d['count']) . ' in total', ['back' => 'menu.php'], sl_phoneIcon('download', 'Export recipients (CSV)', sl_url(['export' => 'csv', 'page' => null]))) ?>
<main class="mx-body">
<form class="mx-srch" method="get" role="search">
  <?php if ($d['country']): ?><input type="hidden" name="country" value="<?= (int) $d['country'] ?>"><?php endif; ?>
  <?php if ($d['owner']): ?><input type="hidden" name="owner" value="<?= (int) $d['owner'] ?>"><?php endif; ?>
  <label><i class="ic i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($d['q']) ?>" aria-label="Search recipients" placeholder="Search recipients" autocomplete="off" enterkeyhint="search"></label>
</form>
<?php if ($d['owner'] || $d['country']): ?><nav class="mx-seg" aria-label="Filters"><a class="on" href="<?= h(sl_url(['owner' => null, 'country' => null, 'page' => null])) ?>">Clear filter ×</a></nav><?php endif; ?>
<?php if ($d['rows']): ?>
<section class="mx-card mx-list">
<?php foreach ($d['rows'] as $r):
    $place = $r['addr'] !== '—' ? $r['addr'] : $r['region'];
    $sub = trim(($place !== '—' ? $place : $r['phone']) . ' · for ' . $r['by'] . ($r['n'] ? ' · ' . $r['n'] . ' parcel' . ($r['n'] === 1 ? '' : 's') : ''), ' ·');
    $href = $d['canEdit'] ? 'recipients_edit.php?recipient=' . (int) $r['id'] : ($r['ownerIsCustomer'] ? sl_route('CustomerProfile', ['id' => $r['ownerId']]) : '');
?>
<?php if ($href !== ''): ?>
  <a class="mx-row" href="<?= h($href) ?>"><span class="mx-ava"><?= h($r['ini']) ?></span><span class="mx-rm"><b><?= h($r['name']) ?></b><small><?= h($sub) ?></small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
<?php else: ?>
  <div class="mx-row"><span class="mx-ava"><?= h($r['ini']) ?></span><span class="mx-rm"><b><?= h($r['name']) ?></b><small><?= h($sub) ?></small></span></div>
<?php endif; ?>
<?php endforeach; ?>
</section>
<?= sl_ppPager((int) $d['page'], (int) $d['per'], (int) $d['count']) ?>
<?php else: ?>
<?= sl_mxEmpty('pin', 'No recipients match', $d['q'] !== '' ? 'Nothing found for “' . $d['q'] . '”.' : 'No recipient has been added yet.') ?>
<?php endif; ?>
</main>
<?php if ($user->cdp_hasPermission('add_client')): ?><a class="mx-fab" href="<?= h(sl_route('AddCustomer', ['type' => 'recipient'])) ?>" aria-label="Add recipient"><i class="ic i-plus" aria-hidden="true"></i></a><?php endif; ?>
