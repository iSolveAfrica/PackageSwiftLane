<?php
/**
 * M-ActLogs: who did what. Same $d as the web page (views/sl/data/activity_logs.php): the segmented
 * filter is the web ?filter=, search is ?q=, pages are ?page=. Export stays on the web page's ?export=csv.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['nav'] = 'staff';
$slPhone['tab'] = 'Menu';
$pages = max(1, (int) ceil(($d['count'] ?? 0) / max(1, $d['per'])));
$acts = !empty($d['canExport']) && !empty($d['ready']) ? sl_phoneIcon('download', 'Export the log (CSV)', sl_url(['export' => 'csv', 'page' => null])) : '';
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-settings.css')) ?>">
<?= sl_phoneTop('Activity logs', $d['ready'] ? sl_num($d['count']) . ' events' : '', ['back' => 'menu.php'], $acts) ?>
<main class="mx-body">
<?php if (!$d['ready']): ?>
<section class="mx-card"><?= sl_mxEmpty('clock', 'Activity log not installed', 'Run the database upgrade to start recording actions.') ?></section>
<?php else: ?>
<form method="get" role="search"><div class="mx-srch"><input type="hidden" name="filter" value="<?= h($d['filter']) ?>"><label><i class="ic i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($d['q']) ?>" aria-label="Search activity" placeholder="Search action, user or tracking number"></label></div></form>
<nav class="mx-seg" aria-label="Filter"><?php foreach ($d['filters'] as $x): $on = $x['f'] === $d['filter']; ?><a class="<?= $on ? 'on' : '' ?>" href="<?= h(sl_url(['filter' => $x['f'], 'page' => null])) ?>"<?= $on ? ' aria-current="true"' : '' ?>><?= h($x['label']) ?></a><?php endforeach; ?></nav>
<section class="mx-card mx-list">
<?php foreach ($d['rows'] as $r): $sys = $r['u'] === 'Unknown' || !$r['uid']; ?>
<div class="mx-row"><?php if ($sys): ?><span class="mx-li"><i class="ic i-bolt" aria-hidden="true"></i></span><?php else: ?><span class="mx-ava"><?= h($r['ini']) ?></span><?php endif; ?><span class="mx-rm"><b><?= h(($sys ? 'System' : explode(' ', $r['u'])[0]) . ' · ' . $r['a']) ?></b><small><?= h(trim(($r['summary'] ?: $r['module']) . ($r['tg'] !== '—' ? ' · ' . $r['tg'] : ''))) ?></small></span><span class="mx-rr"><?php if ($r['b'] === 'warn'): ?><span class="sl-pill overdue"><?= h($r['module']) ?></span><?php endif; ?><small><?= h($r['t']) ?></small></span></div>
<?php endforeach; ?>
<?php if (!$d['rows']): ?><?= sl_mxEmpty('clock', 'No events', 'Nothing matches this filter.') ?><?php endif; ?>
</section>
<?php if ($pages > 1): ?>
<div class="ph-pager"><a class="mx-btn sec" href="<?= h(sl_url(['page' => $d['page'] - 1])) ?>"<?= $d['page'] <= 1 ? ' aria-disabled="true" tabindex="-1"' : '' ?>>Newer</a><span>Page <?= (int) $d['page'] ?> of <?= (int) $pages ?></span><a class="mx-btn sec" href="<?= h(sl_url(['page' => $d['page'] + 1])) ?>"<?= $d['page'] >= $pages ? ' aria-disabled="true" tabindex="-1"' : '' ?>>Older</a></div>
<?php endif; ?>
<?php endif; ?>
</main>
