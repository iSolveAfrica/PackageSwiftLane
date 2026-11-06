<?php
/** Lookup list page (Swift Lane design system): list + add/edit dialog. Data: views/sl/data/catalog.php */
$cat = $d['cat'];
$fields = $cat['fields'];
$listCols = array_slice(array_keys($fields), 0, 5);
?>
        <div class="sl-head">
          <div><h1 class="sl-title"><?= h($cat['title']) ?></h1><p class="sl-lede"><?= sl_num($d['count']) ?> <?= h($cat['noun']) ?><?= $d['count'] === 1 ? '' : 's' ?> · <?= h($cat['group']) ?> settings</p></div>
          <div class="row g8 wrap"><a class="sl-btn ghost" href="<?= h(sl_route('SettingsHub')) ?>"><i class="ic ic-sm i-left" aria-hidden="true"></i>Settings</a><?php if ($d['canCreate']): ?><button type="button" class="sl-btn" data-sl-open="cat-dialog" data-cat-new><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add <?= h($cat['noun']) ?></button><?php endif; ?></div>
        </div>
        <?php if (count($d['siblings']) > 1): ?>
        <nav class="sl-tabs" aria-label="<?= h($cat['group']) ?> lists"><?php foreach ($d['siblings'] as $k => $c): ?><a class="<?= $k === $d['key'] ? 'on' : '' ?>" href="catalog.php?c=<?= h($k) ?>"><?= h($c['title']) ?></a><?php endforeach; ?></nav>
        <?php endif; ?>
        <section class="sl-card flush">
          <form method="get" class="row wrap g12" style="padding:14px 16px">
            <input type="hidden" name="c" value="<?= h($d['key']) ?>">
            <div class="sl-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input name="q" value="<?= h($d['q']) ?>" aria-label="Search <?= h($cat['title']) ?>" placeholder="Search <?= h(strtolower($cat['title'])) ?>"></div>
            <?php if ($d['filterCol']): ?>
            <label class="sl-sel native" style="min-width:200px"><select name="f" aria-label="Filter by <?= h($fields[$d['filterCol']][0]) ?>" onchange="this.form.submit()"><option value="0">All <?= h(strtolower($fields[$d['filterCol']][0])) ?>s</option><?php foreach ($d['filterOptions'] as $v => $l): ?><option value="<?= (int) $v ?>" <?= (int) $v === $d['filterVal'] ? 'selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></label>
            <?php endif; ?>
            <button class="sl-btn ghost sm" type="submit">Search</button>
          </form>
          <div class="sl-tw"><table class="sl-table">
            <thead><tr><?php foreach ($listCols as $col): ?><th data-sort><?= h($fields[$col][0]) ?></th><?php endforeach; ?><th style="width:110px"></th></tr></thead>
            <tbody>
            <?php foreach ($d['rows'] as $r): $locked = in_array((int) $r->id, $cat['locked'] ?? [], true); ?>
              <tr data-sl-row>
                <?php foreach ($listCols as $i => $col): $f = $fields[$col]; $v = $r->$col ?? ''; ?>
                <td class="<?= $i === 0 ? 'fw5' : '' ?> wrap">
                  <?php if ($f[1] === 'color'): ?><span class="row g8"><i style="width:14px;height:14px;border-radius:4px;display:inline-block;background:<?= h(preg_match('/^#[0-9a-f]{3,8}$/i', (string) $v) ? $v : '#999') ?>"></i><span class="mono sm"><?= h($v) ?></span></span>
                  <?php elseif ($f[1] === 'select'): ?><?= $col === 'is_active' ? sl_pill($f[3][$v] ?? $v, (int) $v ? 'delivered' : 'plain') : h($f[3][$v] ?? $v) ?>
                  <?php elseif ($f[1] === 'lookup'): ?><?= h($d['lookups'][$col][$v] ?? ('#' . $v)) ?>
                  <?php else: ?><?= h(mb_strimwidth((string) $v, 0, 90, '…')) ?><?php endif; ?>
                </td>
                <?php endforeach; ?>
                <td><span class="row g4" style="justify-content:flex-end">
                  <?php if ($d['canEdit']): ?><button type="button" class="sl-btn ghost sm" data-sl-open="cat-dialog" data-cat-row="<?= h(json_encode($r, JSON_UNESCAPED_UNICODE)) ?>"><i class="ic ic-sm i-edit" aria-hidden="true"></i>Edit</button>
                  <?php if (!$locked): ?><button type="button" class="sl-ib sm" aria-label="Delete <?= h($r->{$listCols[0]} ?? '') ?>" data-sl-action="ajax/sl/catalog_ajax.php" data-p-op="delete" data-p-c="<?= h($d['key']) ?>" data-p-id="<?= (int) $r->id ?>" data-sl-confirm="Delete this <?= h($cat['noun']) ?>?" data-sl-reload><i class="ic ic-sm i-trash" aria-hidden="true"></i></button><?php endif; ?><?php endif; ?>
                </span></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$d['rows']): ?><tr><td colspan="<?= count($listCols) + 1 ?>"><div class="sl-empty"><span class="sl-tile"><i class="ic i-folder" aria-hidden="true"></i></span>No <?= h(strtolower($cat['title'])) ?> found.</div></td></tr><?php endif; ?>
            </tbody>
          </table></div>
          <?= sl_pager($d['page'], $d['per'], $d['count'], strtolower($cat['title'])) ?>
        </section>

<?php if ($d['canCreate'] || $d['canEdit']): ?>
<div class="sl-dialog" id="cat-dialog" hidden>
  <form class="sl-modal" data-sl-form data-sl-reload action="ajax/sl/catalog_ajax.php" method="post" aria-labelledby="cat-dlg-title">
    <div class="row between"><h2 class="sl-ct" id="cat-dlg-title">Add <?= h($cat['noun']) ?></h2><button type="button" class="sl-ib" data-sl-close aria-label="Close"><i class="ic i-x" aria-hidden="true"></i></button></div>
    <input type="hidden" name="op" value="save"><input type="hidden" name="c" value="<?= h($d['key']) ?>"><input type="hidden" name="id" value="0">
    <div class="grid cols2" style="gap:12px">
    <?php foreach ($fields as $col => $f): $req = !empty($f[2]); ?>
      <div class="sl-field" <?= $f[1] === 'textarea' ? 'style="grid-column:1/-1"' : '' ?>>
        <label class="sl-lab" for="cf-<?= h($col) ?>"><?= h($f[0]) ?><?= $req ? ' *' : '' ?></label>
        <?php if ($f[1] === 'textarea'): ?><textarea class="sl-in" id="cf-<?= h($col) ?>" name="<?= h($col) ?>" <?= $req ? 'required' : '' ?>></textarea>
        <?php elseif ($f[1] === 'select' || $f[1] === 'lookup'): $opts = $f[1] === 'select' ? $f[3] : sl_catalogLookup($f[3], '', 800); ?>
        <label class="sl-sel native"><select id="cf-<?= h($col) ?>" name="<?= h($col) ?>" <?= $req ? 'required' : '' ?>><?php if ($f[1] === 'lookup'): ?><option value="">Choose…</option><?php endif; ?><?php foreach ($opts as $v => $l): ?><option value="<?= h($v) ?>"><?= h($l) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></label>
        <?php else: ?><input class="sl-in" id="cf-<?= h($col) ?>" name="<?= h($col) ?>" type="<?= $f[1] === 'number' ? 'number' : ($f[1] === 'color' ? 'color' : 'text') ?>" <?= $req ? 'required' : '' ?> <?= $f[1] === 'color' ? 'style="padding:4px;height:42px"' : '' ?>>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    </div>
    <div class="row g8"><button type="submit" class="sl-btn">Save</button><button type="button" class="sl-btn ghost" data-sl-close>Cancel</button></div>
  </form>
</div>
<script>
(function () {
  var dlg = document.getElementById('cat-dialog'); if (!dlg) return;
  var form = dlg.querySelector('form'), title = document.getElementById('cat-dlg-title'), noun = <?= json_encode($cat['noun']) ?>;
  function fill(row) {
    form.reset(); form.elements.id.value = row ? row.id : 0;
    title.textContent = (row ? 'Edit ' : 'Add ') + noun;
    if (row) Object.keys(row).forEach(function (k) { var el = form.elements[k]; if (el && k !== 'id') el.value = row[k] === null ? '' : row[k]; });
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-cat-row],[data-cat-new]'); if (!b) return;
    fill(b.hasAttribute('data-cat-row') ? JSON.parse(b.getAttribute('data-cat-row')) : null);
  }, true);
  <?php if ($d['openNew'] && $d['canCreate']): ?>fill(null); dlg.hidden = false;<?php endif; ?>
  <?php if ($d['openEdit'] && $d['canEdit']): $er = sl_row('SELECT * FROM `' . $cat['table'] . '` WHERE id = :id', [':id' => $d['openEdit']]); if ($er): ?>fill(<?= json_encode($er, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>); dlg.hidden = false;<?php endif; endif; ?>
})();
</script>
<?php endif; ?>
