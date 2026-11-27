<?php
/** M-PreAlerts (staff): pre-alerts on a phone. $d = views/sl/data/prealerts.php sl_preAlertsData() (GET q, filter, page, new). */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['tab'] = 'Shipments';
$pages = max(1, (int) ceil($d['count'] / max(1, $d['per'])));
$st = $d['stats'] ?? [];
$canIntake = $user->cdp_hasPermission(['package_intake', 'add_package']);
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-ops.css')) ?>">
<?= sl_phoneTop('Pre-alerts', sl_num($d['openN']) . ' waiting to arrive', ['back' => sl_route('Main')], sl_phoneIcon('search', 'Search', sl_route('LockerSearch'))) ?>
<main class="mx-body">
<form method="get" action="<?= h(sl_route('PreAlerts')) ?>" role="search" class="mx-formcontents">
<input type="hidden" name="filter" value="<?= h($d['filter']) ?>">
<div class="mx-srch"><label><i class="ic i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($d['q']) ?>" aria-label="Tracking, store, contents or customer" placeholder="Tracking, store, contents or customer" autocomplete="off" enterkeyhint="search"></label><?php if ($canIntake): ?><a class="mx-ib" href="<?= h(sl_route('PackageIntake')) ?>" aria-label="Scan"><i class="ic i-scan" aria-hidden="true"></i></a><?php endif; ?></div>
</form>
<nav class="mx-seg" aria-label="Show">
<?php foreach ($d['filters'] as $f): $on = $d['filter'] === $f['f']; ?><a class="mx-segl<?= $on ? ' on' : '' ?>" href="<?= h(sl_route('PreAlerts', array_filter(['filter' => $f['f'], 'q' => $d['q']]))) ?>"<?= $on ? ' aria-current="page"' : '' ?>><?= h($f['label']) ?><?= $f['f'] === 'open' ? ' ' . sl_num($d['openN']) : '' ?></a><?php endforeach; ?>
</nav>
<?php if ($d['rows']): ?>
<section class="mx-card mx-list">
<?php foreach ($d['rows'] as $r):
    $e = $r['edit'];
    $href = $e['refHref'] !== '' ? $e['refHref'] : ($r['open'] && $canIntake ? sl_route('PackageIntake', ['tracking' => $r['trk']]) : sl_route('LockerSearch', ['q' => $r['trk']]));
    $pill = $r['open'] && !$r['lateBy'] && $e['date'] !== '' ? 'Expected ' . date('M j', strtotime($e['date'])) : ($r['lateBy'] ? $r['lateBy'] . ' d late' : $r['st']);
    $icon = $r['open'] ? ($r['lateBy'] ? 'alert' : 'inbox') : ($e['state'] === 1 ? 'check' : 'x');
?>
<a class="mx-row" href="<?= h($href) ?>"><span class="mx-li"><i class="ic i-<?= h($icon) ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h(trim($r['sup'] . ' · ' . $r['what'], ' ·')) ?></b><small><?= h($r['name'] . ($r['locker'] !== '' ? ' · ' . $r['locker'] : '')) ?></small><small class="mono"><?= h($r['trk'] . ($r['invoice'] && $d['canInvoice'] ? ' · invoice attached' : '')) ?></small></span><span class="mx-rr"><span class="sl-pill <?= h($r['cls']) ?>"><?= h($pill) ?></span></span></a>
<?php endforeach; ?>
</section>
<?php if ($pages > 1): ?>
<div class="mx-btns" style="flex-direction:row">
<?php if ($d['page'] > 1): ?><a class="mx-btn sec" style="flex:1" href="<?= h(sl_url(['page' => $d['page'] - 1])) ?>">Previous</a><?php endif; ?>
<?php if ($d['page'] < $pages): ?><a class="mx-btn sec" style="flex:1" href="<?= h(sl_url(['page' => $d['page'] + 1])) ?>">Next</a><?php endif; ?>
</div>
<?php endif; ?>
<?php else: ?>
<?= sl_mxEmpty('inbox', 'No pre-alerts here', $d['q'] !== '' ? 'Nothing matches “' . $d['q'] . '”.' : 'Try another filter.') ?>
<?php endif; ?>
<div class="mx-ban ok"><i class="ic ic-sm i-bolt" aria-hidden="true"></i><span>When a parcel is registered with the same carrier tracking number, its pre-alert is linked and the customer is told automatically.</span></div>
<?php if ($d['canAdd']): ?>
<details class="mx-grp" id="new"<?= $d['openForm'] ? ' open' : '' ?>><summary><span>New pre-alert</span><i class="ic ic-sm i-down" aria-hidden="true"></i></summary>
<form class="mx-card mx-form" data-sl-form data-sl-reload action="ajax/sl/prealert_ajax.php" method="post" enctype="multipart/form-data">
<input type="hidden" name="op" value="create">
<label class="mx-fld"><span>Customer</span><select name="customer_id" required><option value="">Select customer</option><?php foreach ($d['customers'] as $c): ?><option value="<?= (int) $c->id ?>"><?= h(trim($c->fname . ' ' . $c->lname)) ?><?= $c->locker ? ' · ' . h($c->locker) : '' ?></option><?php endforeach; ?></select></label>
<label class="mx-fld"><span>Carrier tracking number</span><input name="tracking" class="mono" required autocomplete="off" autocapitalize="characters"></label>
<label class="mx-fld"><span>Carrier</span><select name="courier" required><?php foreach ($d['couriers'] as $c): ?><option value="<?= (int) $c->id ?>"><?= h($c->name_com) ?></option><?php endforeach; ?></select></label>
<label class="mx-fld"><span>What is in it</span><input name="description" required></label>
<label class="mx-fld"><span>Purchase price</span><input name="price" type="number" step="0.01" min="0.01" inputmode="decimal" class="mono" required></label>
<label class="mx-fld"><span>Expected at the warehouse</span><input name="date" type="date" value="<?= h(date('Y-m-d', strtotime('+5 days'))) ?>" required></label>
<label class="mx-fld"><span>Invoice (optional)</span><input type="file" name="file_invoice" accept=".pdf,image/*"></label>
<button type="submit" class="mx-btn or">Submit pre-alert</button>
</form></details>
<?php endif; ?>
</main>
<?php if ($d['canAdd']): ?><a class="mx-fab" href="#new" aria-label="Add pre-alert" onclick="var g=document.getElementById('new');if(g){g.open=true;}"><i class="ic i-plus" aria-hidden="true"></i></a><?php endif; ?>
