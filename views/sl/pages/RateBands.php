<?php
/** Route prices (rate_bands.php): public weight bands per route, and the add / edit form. */
$phone = false;
?>
<div class="sl-wrap col g16">
<div class="sl-head"><div><h1 class="sl-title">Route prices</h1><p class="sl-lede">What the public rate calculator charges per <?= h($d['unit']) ?>, by route and weight. Where no route price matches, it quotes the standard freight price<?= $d['standard'] !== '' ? ' (' . h($d['standard']) . ')' : '' ?>.</p></div>
<div class="row g8 wrap"><a class="sl-btn ghost" href="<?= h(sl_route('Tariffs')) ?>">All rates</a><a class="sl-btn ghost" href="<?= h(sl_route('QuoteCalculator')) ?>">Open calculator</a><?php if ($d['canEdit']): ?><a class="sl-btn" href="rate_bands.php?edit=new">Add route price</a><?php endif; ?></div></div>
<?php if ($d['editMissing']): ?><div class="sl-banner warn">That route price no longer exists.</div><?php endif; ?>
<?php if ($d['edit'] && $d['canEdit']): $e = $d['edit']; ?>
<h2 class="sl-section"><?= $e->id ? 'Change route price' : 'New route price' ?></h2>
<?php include __DIR__ . '/_rate_band_form.php'; ?>
<?php endif; ?>
<?php if (!$d['routes']): ?>
<section class="sl-card sl-empty"><h2 class="sl-ct">No route prices yet</h2><p>The calculator quotes every route at the standard freight price until you add one.</p><?php if ($d['canEdit']): ?><a class="sl-btn" href="rate_bands.php?edit=new">Add route price</a><?php endif; ?></section>
<?php endif; ?>
<?php foreach ($d['routes'] as $r): ?>
<section class="sl-card flush"><div class="row between wrap g8" style="padding:14px 16px"><h2 class="sl-ct"><?= h($r['title']) ?></h2><span class="mut sm"><?= count($r['bands']) ?> band<?= count($r['bands']) === 1 ? '' : 's' ?></span></div>
<div class="sl-tw"><table class="sl-table"><thead><tr><th>Weight</th><th>Category</th><th class="num">Price</th><th>Minimum</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($r['bands'] as $b): ?><tr><td><?= h($b['range']) ?></td><td><?= h($b['cat']) ?></td><td class="num fw5"><?= h($b['price']) ?></td><td><?= h($b['min'] ?: '—') ?></td><td><span class="sl-pill <?= $b['live'] ? 'delivered' : 'plain' ?>"><?= $b['live'] ? 'In use' : 'Not in date' ?></span><?= $b['dates'] !== '' ? ' <span class="mut sm">' . h($b['dates']) . '</span>' : '' ?></td><td><?php if ($d['canEdit']): ?><a class="sl-btn ghost sm" href="rate_bands.php?edit=<?= (int) $b['id'] ?>">Edit</a><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php endforeach; ?>
</div>
