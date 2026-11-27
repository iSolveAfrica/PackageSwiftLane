<?php
/** M-Search: Smart search on a phone. $d = views/sl/data/smart_search.php (same GET parameters q, in, cust). */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['tab'] = 'Home';
$c = $d['cust'] ?? null;
$canScan = $user->cdp_hasPermission(['package_intake', 'add_package']);
$all = $d['counts'] ?? [];
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-ops.css')) ?>">
<?= sl_phoneTop('Smart search', $d['q'] !== '' ? sl_num($d['total']) . ' results' : '', ['back' => sl_route('Main')], !empty($d['canAi']) ? sl_phoneIcon('ai', 'Ask ' . cdp_kingName(), sl_route('StaffAI', $d['q'] !== '' ? ['q' => $d['q']] : [])) : '') ?>
<main class="mx-body">
<form method="get" action="<?= h(sl_route('LockerSearch')) ?>" role="search" class="mx-formcontents">
<div class="mx-srch"><label><i class="ic i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($d['q']) ?>" aria-label="Name, phone, locker ID, tracking, AWB" placeholder="Name, phone, locker ID, tracking, AWB" autocomplete="off" enterkeyhint="search"></label><?php if ($canScan): ?><a class="mx-ib" href="<?= h(sl_route('PackageIntake')) ?>" aria-label="Scan"><i class="ic i-scan" aria-hidden="true"></i></a><?php endif; ?></div>
<?php if ($d['q'] !== '' && $all): ?>
<div class="mx-seg" aria-label="Search in">
<button type="submit" name="in" value="all" class="<?= $d['in'] === 'all' ? 'on' : '' ?>" aria-pressed="<?= $d['in'] === 'all' ? 'true' : 'false' ?>">All</button>
<?php foreach ($all as $g): ?><button type="submit" name="in" value="<?= h($g['key']) ?>" class="<?= $d['in'] === $g['key'] ? 'on' : '' ?>" aria-pressed="<?= $d['in'] === $g['key'] ? 'true' : 'false' ?>"><?= h($g['label']) ?> <?= sl_num($g['n']) ?></button><?php endforeach; ?>
</div>
<?php endif; ?>
</form>
<?php if ($d['q'] === ''): ?>
<div class="mx-sec"><h2>Try searching</h2></div>
<section class="mx-card mx-list">
<?php foreach ($d['tips'] ?? [] as [$tq, $tl]): ?>
<a class="mx-row" href="<?= h(sl_route('LockerSearch', ['q' => $tq])) ?>"><span class="mx-li"><i class="ic i-search" aria-hidden="true"></i></span><span class="mx-rm"><b>“<?= h($tq) ?>”</b><small><?= h(ucfirst($tl)) ?></small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
<?php endforeach; ?>
</section>
<?php else: ?>
<?php if (!empty($d['best'])): $b = $d['best']; ?>
<div class="mx-sec"><h2>Exact match · <?= h($b['group']) ?></h2></div>
<section class="mx-card mx-list"><a class="mx-row" href="<?= h($b['href']) ?>"><span class="mx-li"><i class="ic <?= h($b['gicon']) ?>" aria-hidden="true"></i></span><span class="mx-rm"><b class="<?= !empty($b['mono']) ? 'mono' : '' ?>"><?= h($b['t']) ?></b><small><?= h($b['s'] ?? '') ?></small></span><?php if (!empty($b['st'])): ?><span class="mx-rr"><span class="sl-pill <?= h($b['cls'] ?? '') ?>"><?= h($b['st']) ?></span></span><?php endif; ?></a></section>
<?php endif; ?>
<?php if ($c): ?>
<div class="mx-sec"><h2>Customer</h2><?php if (!empty($c['canProfile'])): ?><a href="<?= h(sl_route('CustomerProfile', ['id' => $c['id']])) ?>">Profile</a><?php endif; ?></div>
<section class="mx-card mx-list">
<div class="mx-row"><span class="mx-ava"><?= h($c['ini']) ?></span><span class="mx-rm"><b><?= h($c['name']) ?></b><small><?= h(implode(' · ', array_filter(['Locker ' . $c['lock'], $c['phone'] ?? '']))) ?></small></span><span class="mx-rr"><span class="sl-pill <?= $c['ok'] ? 'delivered' : 'pending' ?>"><?= $c['ok'] ? 'Verified' : 'Not active' ?></span></span></div>
<?php foreach (array_slice($c['parcels'], 0, 6) as $p): ?>
<a class="mx-row" href="<?= h($p['href']) ?>"><span class="mx-li"><i class="ic i-<?= $p['mode'] === 'sea' ? 'ship' : 'plane' ?>" aria-hidden="true"></i></span><span class="mx-rm"><b class="mono"><?= h($p['id']) ?></b><small><?= h(($p['mode'] === 'sea' ? 'Sea' : 'Air') . ' · ' . $p['age'] . ' · ' . $p['w'] . ($p['bay'] !== '' && $p['bay'] !== '—' ? ' · Bay ' . $p['bay'] : '')) ?></small></span><span class="mx-rr"><span class="sl-pill <?= h($p['cls']) ?>"><?= h($p['st']) ?></span></span></a>
<?php endforeach; ?>
</section>
<div class="mx-btns"><a class="mx-btn sec" href="<?= h(sl_route('LiveTracking', ['q' => $c['lock'] !== '—' ? $c['lock'] : $c['name']])) ?>"><i class="ic ic-sm i-route" aria-hidden="true"></i>Track parcels</a><a class="mx-btn" href="<?= h(sl_route('HandOut', ['customer' => $c['id']])) ?>"><i class="ic ic-sm i-key" aria-hidden="true"></i>Hand out</a></div>
<?php endif; ?>
<?php foreach ($d['groups'] as $g): ?>
<div class="mx-sec"><h2><?= h($g['label']) ?></h2><?php if ($d['in'] === 'all' && $g['total'] > count($g['items'])): ?><a href="<?= h(sl_url(['in' => $g['key'], 'cust' => null])) ?>">All <?= sl_num($g['total']) ?></a><?php endif; ?></div>
<section class="mx-card mx-list">
<?php foreach ($g['items'] as $it): ?>
<a class="mx-row" href="<?= h($it['href']) ?>"><?php if (!empty($it['ini'])): ?><span class="mx-ava"><?= h($it['ini']) ?></span><?php else: ?><span class="mx-li"><i class="ic <?= h($it['icon'] ?? $g['icon']) ?>" aria-hidden="true"></i></span><?php endif; ?><span class="mx-rm"><b class="<?= !empty($it['mono']) ? 'mono' : '' ?>"><?= h($it['t']) ?></b><?php if (($it['s'] ?? '') !== ''): ?><small><?= h($it['s']) ?></small><?php endif; ?></span><?php if (!empty($it['st']) || !empty($it['badge']) || !empty($it['m'])): ?><span class="mx-rr"><?php if (!empty($it['st'])): ?><span class="sl-pill <?= h($it['cls'] ?? '') ?>"><?= h($it['st']) ?></span><?php elseif (!empty($it['badge'])): ?><span class="sl-pill pending"><?= h($it['badge']) ?></span><?php else: ?><small><?= h($it['m']) ?></small><?php endif; ?></span><?php endif; ?></a>
<?php endforeach; ?>
</section>
<?php endforeach; ?>
<?php if (!$d['groups']): ?>
<?= sl_mxEmpty('search', 'Nothing found for “' . $d['q'] . '”', 'Check the spelling, or search a tracking number, locker, phone or name.') ?>
<?php if (!empty($d['canAi'])): ?><a class="mx-btn sec" href="<?= h(sl_route('StaffAI', ['q' => $d['q']])) ?>"><i class="ic ic-sm i-ai" aria-hidden="true"></i>Ask <?= h(cdp_kingName()) ?></a><?php endif; ?>
<?php endif; ?>
<?php endif; ?>
</main>
