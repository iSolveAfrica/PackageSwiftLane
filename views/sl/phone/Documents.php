<?php
/**
 * M-Docs: every invoice, receipt, label, manifest and export printed or downloaded. Same $d as the web page
 * (views/sl/data/documents.php sl_documentsData); search and type tabs use the web page's q / filter / page parameters.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['nav'] = 'staff';
$fc = $d['facets'];
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-wh.css')) ?>">
<?= sl_phoneTop('Documents', sl_num($d['count']) . ' document' . ($d['count'] === 1 ? '' : 's'), ['back' => 'menu.php'], !empty($d['canLabels']) ? sl_phoneIcon('print', 'Print labels', sl_route('LabelStudio')) : '') ?>
<main class="mx-body">
<form method="get" class="mx-srch" role="search"><?php if ($d['filter'] !== 'all'): ?><input type="hidden" name="filter" value="<?= h($d['filter']) ?>"><?php endif; ?><label><i class="ic i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($d['q']) ?>" aria-label="Invoice, waybill, receipt" placeholder="Invoice, waybill, receipt" enterkeyhint="search"></label></form>
<nav class="mx-seg" aria-label="Document type"><?php foreach ($d['filters'] as $f): $on = $f['f'] === $d['filter']; ?><a class="<?= $on ? 'on' : '' ?>" href="<?= h(sl_url(['filter' => $f['f'] === 'all' ? null : $f['f'], 'page' => null])) ?>"<?= $on ? ' aria-current="page"' : '' ?>><?= h($f['label']) ?><?= !empty($fc[$f['f']]) ? ' ' . sl_num($fc[$f['f']]) : '' ?></a><?php endforeach; ?></nav>
<?php if ($d['rows']): ?>
<section class="mx-card mx-list">
<?php foreach ($d['rows'] as $r):
    $sub = $r['who'] !== '' ? $r['who'] : ($r['recs'] ? implode(', ', $r['recs']) . ($r['more'] ? ' +' . $r['more'] : '') : ucfirst($r['cat']));
    $sub .= ' · ' . $r['by'];
    $tag = $r['href'] !== '' ? 'a' : 'div';
?>
<<?= $tag ?> class="mx-row"<?= $r['href'] !== '' ? ' href="' . h($r['href']) . '" target="_blank" rel="noopener"' : '' ?>><span class="mx-li"><i class="ic <?= h($r['icon']) ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($r['title']) ?></b><small><?= h($sub) ?></small></span><span class="mx-rr"><small><?= h($r['ago']) ?></small></span></<?= $tag ?>>
<?php endforeach; ?>
</section>
<?php if ($d['count'] > $d['per']): $pages = (int) ceil($d['count'] / $d['per']); ?><nav class="wh-pg" aria-label="Pages"><?php if ($d['page'] > 1): ?><a href="<?= h(sl_url(['page' => $d['page'] - 1])) ?>">Previous</a><?php else: ?><span></span><?php endif; ?><span>Page <?= (int) $d['page'] ?> of <?= $pages ?></span><?php if ($d['page'] < $pages): ?><a href="<?= h(sl_url(['page' => $d['page'] + 1])) ?>">Next</a><?php else: ?><span></span><?php endif; ?></nav><?php endif; ?>
<?php else: ?>
<?= sl_mxEmpty('file', $d['q'] !== '' || $d['filter'] !== 'all' ? 'No documents match' : 'No documents yet', $d['q'] !== '' ? '' : 'Labels, receipts and exports appear here as soon as someone prints or downloads one.') ?>
<?php endif; ?>
</main>
