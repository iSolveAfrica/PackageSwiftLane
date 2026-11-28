<?php
/**
 * M-Reports: every report the person may open, grouped as on the web hub, with a search box.
 * Same $d as the web page (views/sl/data/reports.php sl_reportsData). Each row opens the report runner
 * (report_view.php?r=…) when the report has one, otherwise its own page.
 */
require_once CDP_ROOT . '/helpers/sl_reports.php';
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['nav'] = 'staff';
$slPhone['tab'] = 'Menu';
$slPhone['scripts'][] = 'dataJs/sl/phone/settings.js';
$runner = function (string $href): string {
    $k = basename(strtok($href, '?#'), '.php');
    return sl_report($k) ? 'report_view.php?r=' . rawurlencode($k) : $href;
};
$iconFor = fn(string $title, string $gIcon) => stripos($title, 'top ') === 0 ? 'i-star' : ($gIcon === 'i-dollar' ? 'i-dollar' : 'i-chart');
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-settings.css')) ?>">
<?= sl_phoneTop('Reports', sl_date($d['from']) . ' – ' . sl_date($d['to']), ['back' => 'menu.php']) ?>
<main class="mx-body">
<div class="mx-srch"><label><i class="ic i-search" aria-hidden="true"></i><input type="search" aria-label="Find a report" placeholder="Find a report" data-mx-menu-q></label></div>
<?php foreach ($d['groups'] as $g): ?>
<div data-mx-menu-group>
<div class="mx-sec"><h2><?= h($g['title']) ?></h2></div>
<section class="mx-card mx-list">
<?php foreach ($g['items'] as $it): ?>
<a class="mx-row" href="<?= h($runner($it['href'])) ?>" data-mx-menu-item><span class="mx-li"><i class="ic <?= h($iconFor($it['title'], $g['icon'])) ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($it['title']) ?></b><small><?= h($it['sub']) ?></small></span><?php if ($it['n'] !== ''): ?><span class="mx-cnt" aria-label="<?= h($it['n']) ?> in the last 30 days"><?= h($it['n']) ?></span><?php endif; ?><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
<?php endforeach; ?>
</section>
</div>
<?php endforeach; ?>
<?php if (!$d['groups']): ?><section class="mx-card"><?= sl_mxEmpty('chart', 'No reports for your role', 'Ask an administrator for report permissions.') ?></section><?php endif; ?>
</main>
