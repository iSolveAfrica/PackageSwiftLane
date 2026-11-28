<?php
/** M-Sea: Sea packages on a staff phone. $d = views/sl/data/packages.php sl_seaPackagesData() (GET q, filter, status, container, page). */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['tab'] = 'Shipments';
$pages = max(1, (int) ceil($d['count'] / max(1, $d['per'])));
$keep = array_filter(['q' => $d['q'], 'status' => $d['status'] ?: null, 'container' => $d['container'] ?: null]);
$kn = array_column($d['kpi'], 'n', 'f');
$cnt = ['all' => $d['total'], 'open' => $d['open'], 'ready' => $kn['ready'] ?? null, 'done' => $kn['done'] ?? null];
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-ops.css')) ?>">
<?= sl_phoneTop('Sea packages', $d['lede'], ['back' => sl_route('Main')], sl_phoneIcon('search', 'Search', sl_route('LockerSearch'))) ?>
<main class="mx-body">
<section class="mx-k c2">
<?php foreach ($d['kpi'] as $k): ?>
<a class="mx-kt" href="<?= h(sl_route('SeaPackages', ['filter' => $k['f']])) ?>"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm <?= h($k['icon']) ?>" aria-hidden="true"></i></span><small><?= h($k['label']) ?></small></div><b><?= h(sl_num($k['n'])) ?></b><em class=""><?= (int) $k['pct'] ?>% of all</em></a>
<?php endforeach; ?>
</section>
<form method="get" action="<?= h(sl_route('SeaPackages')) ?>" role="search" class="mx-formcontents">
<?php if ($d['filter'] !== 'all'): ?><input type="hidden" name="filter" value="<?= h($d['filter']) ?>"><?php endif; ?>
<div class="mx-srch"><label><i class="ic i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($d['q']) ?>" aria-label="Package, store tracking or customer" placeholder="Package, store tracking or customer" autocomplete="off" enterkeyhint="search"></label><?php if ($user->cdp_hasPermission(['package_intake', 'add_package'])): ?><a class="mx-ib" href="<?= h(sl_route('PackageIntake')) ?>" aria-label="Scan"><i class="ic i-scan" aria-hidden="true"></i></a><?php endif; ?></div>
</form>
<nav class="mx-seg" aria-label="Show">
<?php foreach ($d['filters'] as $f): $on = $d['filter'] === $f['f']; ?><a class="mx-segl<?= $on ? ' on' : '' ?>" href="<?= h(sl_route('SeaPackages', $keep + ($f['f'] === 'all' ? [] : ['filter' => $f['f']]))) ?>"<?= $on ? ' aria-current="page"' : '' ?>><?= h($f['label']) ?><?= isset($cnt[$f['f']]) ? ' ' . sl_num($cnt[$f['f']]) : '' ?></a><?php endforeach; ?>
</nav>
<?php if ($d['rows']): ?>
<section class="mx-card mx-list">
<?php foreach ($d['rows'] as $r): ?>
<a class="mx-row" href="<?= h(sl_route('PackageDetail', ['id' => $r['id']])) ?>"><span class="mx-rm"><b><?= h($r['trk'] . ' · ' . $r['name']) ?></b><small><?= h($r['box'] !== '—' ? 'Container ' . $r['box'] : implode(' · ', array_filter([$r['supplier'], $r['date']]))) ?></small><small class="mono"><?= h(implode(' · ', array_filter([$r['items'] . ' item' . ($r['items'] === 1 ? '' : 's'), $r['w'] !== '—' ? $r['w'] : '', $r['postal']]))) ?></small></span><span class="mx-rr"><span class="sl-pill <?= h($r['stCls']) ?>"><?= h(ucfirst(strtolower($r['st']))) ?></span></span></a>
<?php endforeach; ?>
</section>
<?php if ($pages > 1): ?>
<div class="mx-btns" style="flex-direction:row">
<?php if ($d['page'] > 1): ?><a class="mx-btn sec" style="flex:1" href="<?= h(sl_url(['page' => $d['page'] - 1])) ?>">Previous</a><?php endif; ?>
<?php if ($d['page'] < $pages): ?><a class="mx-btn sec" style="flex:1" href="<?= h(sl_url(['page' => $d['page'] + 1])) ?>">Next</a><?php endif; ?>
</div>
<p class="mx-note">Page <?= (int) $d['page'] ?> of <?= (int) $pages ?> · <?= sl_num($d['count']) ?> packages</p>
<?php endif; ?>
<?php else: ?>
<?= sl_mxEmpty('ship', 'No packages here', $d['q'] !== '' ? 'Nothing matches “' . $d['q'] . '”.' : 'Try another filter.', 'Show all', sl_route('SeaPackages')) ?>
<?php endif; ?>
</main>
<?php if ($d['canAdd']): ?><a class="mx-fab" href="<?= h(sl_route('NewPackage')) ?>" aria-label="New package"><i class="ic i-plus" aria-hidden="true"></i></a><?php endif; ?>
