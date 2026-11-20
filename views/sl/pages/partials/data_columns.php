<?php
/** Compact count chart. The caller supplies chronological values and matching labels. */
$columnPeak = max(1, ...array_values($columnValues));
?>
<div class="sl-data-columns" role="img" aria-label="<?= h($columnTitle) ?>"
     <?= sl_chartAttrs([['name' => $columnTitle, 'values' => $columnValues]], $columnLabels, $columnTitle) ?>>
  <?php foreach ($columnValues as $columnIndex => $columnValue): ?>
    <div class="sl-data-columns__track" title="<?= h($columnLabels[$columnIndex] . ': ' . sl_num($columnValue)) ?>">
      <i style="height:<?= sl_pct($columnValue, $columnPeak) ?>%"></i>
    </div>
  <?php endforeach; ?>
</div>
<div class="sl-data-columns__axis"><span><?= h(reset($columnLabels)) ?></span><span><?= h(end($columnLabels)) ?></span></div>
