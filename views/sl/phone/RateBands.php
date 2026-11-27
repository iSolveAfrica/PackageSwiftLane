<?php
/** Route prices on the phone (same components as M-Rates). */
$phone = true;
$e = $d['edit'];
$slPhone['nav'] = 'staff';
$slPhone['tab'] = 'Menu';
if ($e && $d['canEdit']):
    echo sl_phoneTop($e->id ? 'Change route price' : 'New route price', '', ['back' => 'rate_bands.php']); ?>
<main class="mx-body"><?php include CDP_ROOT . '/views/sl/pages/_rate_band_form.php'; ?></main>
<?php return; endif;
echo sl_phoneTop('Route prices', $d['count'] . ' price band' . ($d['count'] === 1 ? '' : 's'), ['back' => sl_route('Tariffs')], $d['canEdit'] ? sl_phoneIcon('plus', 'Add route price', 'rate_bands.php?edit=new') : ''); ?>
<main class="mx-body">
<?php if ($d['editMissing']): ?><div class="mx-ban warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>That route price no longer exists.</span></div><?php endif; ?>
<div class="mx-ban info"><i class="ic ic-sm i-dollar" aria-hidden="true"></i><span>The rate calculator charges these prices per <?= h($d['unit']) ?>. Where none matches it uses the standard freight price<?= $d['standard'] !== '' ? ' (' . h($d['standard']) . ')' : '' ?>.</span></div>
<?php if (!$d['routes']): ?><?= sl_mxEmpty('route', 'No route prices yet', 'Every route is quoted at the standard freight price.', $d['canEdit'] ? 'Add route price' : '', 'rate_bands.php?edit=new') ?><?php endif; ?>
<?php foreach ($d['routes'] as $r): ?>
<?= sl_mxSec($r['title'], $d['canEdit'] ? 'Add band' : '', $d['canEdit'] ? 'rate_bands.php?edit=new' : '') ?>
<section class="mx-card mx-list">
<?php foreach ($r['bands'] as $b): ?><?= sl_mxRow('box', $b['range'] . ' · ' . $b['cat'], trim($b['min'] . ($b['dates'] !== '' ? ' · ' . $b['dates'] : ''), ' ·'), '<b>' . h($b['price']) . '</b><small>' . ($b['live'] ? 'In use' : 'Not in date') . '</small>', $d['canEdit'] ? 'rate_bands.php?edit=' . $b['id'] : '') ?><?php endforeach; ?>
</section>
<?php endforeach; ?>
</main>
