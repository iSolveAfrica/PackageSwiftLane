<?php
/** M-Menu: every page from the web app, grouped as in the sidebar, with live counts. */
$me = sl_phoneMe();
$slPhone['tab'] = 'Menu';
if (sl_userIs('driver')) $slPhone['nav'] = 'rider';
$open = true;
?>
<?= sl_phoneTop('Everything', '', ['avatar' => $me['ini'], 'href' => sl_route('Workspace'), 'online' => true], sl_phoneIcon('search', 'Search', sl_route('LockerSearch')) . sl_phoneIcon('bell', 'Notifications', sl_route('Notifications'), $d['unread'])) ?>
<main class="mx-body">
<div class="mx-srch"><label><i class="ic i-search" aria-hidden="true"></i><input type="search" aria-label="Search any page or setting" placeholder="Search any page or setting" data-mx-menu-q></label></div>
<div class="mx-ban info"><i class="ic ic-sm i-grid" aria-hidden="true"></i><span>Every page from the web app is here, in the same groups as the sidebar.</span></div>
<?php foreach ($d['groups'] as $g): ?>
<details class="mx-grp"<?= $open ? ' open' : '' ?> data-mx-menu-group><summary><span><?= h(ucwords(strtolower($g['label']))) ?></span><small><?= count($g['items']) ?></small><i class="ic ic-sm i-down" aria-hidden="true"></i></summary><section class="mx-card mx-list">
<?php foreach ($g['items'] as $it): ?>
<a class="mx-row" href="<?= h($it['href']) ?>" data-mx-menu-item><span class="mx-li"><i class="ic <?= h($it['icon']) ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($it['label']) ?></b></span><?php if ($it['c1'] !== ''): ?><span class="mx-cnt"><?= h($it['c1']) ?><?= $it['c2'] !== '' ? ' / ' . h($it['c2']) : '' ?></span><?php endif; ?><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
<?php endforeach; ?>
</section></details>
<?php $open = false; endforeach; ?>
<section class="mx-card mx-list">
<a class="mx-row" href="<?= h(sl_route('AccountSecurity')) ?>"><span class="mx-li"><i class="ic i-shield" aria-hidden="true"></i></span><span class="mx-rm"><b>My account security</b></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
<a class="mx-row" href="index.php?view=web"><span class="mx-li"><i class="ic i-monitor" aria-hidden="true"></i></span><span class="mx-rm"><b>View the full website</b><small>The computer layout, on this phone</small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
<a class="mx-row bad" href="logout.php"><span class="mx-li"><i class="ic i-logout" aria-hidden="true"></i></span><span class="mx-rm"><b>Sign out</b></span></a>
</section>
</main>
