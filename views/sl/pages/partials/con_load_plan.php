<?php
/**
 * Consolidation body (desktop ConsolidationDetail and the phone page): the flight, the picture and the real totals
 * (con_cargo_view.php), every package on board grouped by customer, an "Add packages" panel that works at any time
 * while the consolidation is open, and the optional cargo positions (bay / row / tier, no weight limit).
 * Data: sl_consolidationDetailData() (views/sl/data/consolidations.php). Writes: ajax/sl/consolidation_ajax.php
 * (add_items, remove_item) and ajax/sl/consolidation_load_plan.php (positions). Script: dataJs/sl/consolidation_load_plan.js.
 */
$sea = !empty($d['sea']);
$can = $can ?? ($d['can'] ?? []);
$rows = $d['rows'];
$unit = (string) ($core->weight_p ?? 'lbs');
$fmtW = fn($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.') . ' ' . $unit;
$stored = sl_tableExists('cdb_consolidation_load_plans') ? sl_row('SELECT revision,positions_json FROM cdb_consolidation_load_plans WHERE mode=:mode AND consolidation_id=:id', [':mode' => $d['type'], ':id' => $d['cid']]) : null;
$positions = json_decode($stored->positions_json ?? '[]', true) ?: [];
$positionMap = [];
foreach ($positions as $pos) $positionMap[(int) $pos['id']] = $pos;
$canEdit = !empty($d['canEdit']);
$editable = $canEdit && ($d['lq'] ?? '') === '' && sl_tableExists('cdb_consolidation_load_plans');

$actual = 0.0; $vol = 0.0; $heavy = null; $groups = [];
foreach ($rows as $r) {
    $actual += (float) ($r['weightKg'] ?? 0); $vol += (float) ($r['vn'] ?? 0);
    if (!$heavy || (float) ($r['weightKg'] ?? 0) > (float) ($heavy['weightKg'] ?? 0)) $heavy = $r;
    $k = ($r['name'] ?? '') . '|' . ($r['lock'] ?? '');
    $groups[$k] ??= ['name' => $r['name'] ?? '—', 'lock' => $r['lock'] ?? '', 'ini' => $r['ini'] ?? '', 'w' => 0.0, 'rows' => []];
    $groups[$k]['w'] += (float) ($r['weightKg'] ?? 0);
    $groups[$k]['rows'][] = $r;
}
$charge = max($actual, $vol);
uasort($groups, fn($a, $b) => strcasecmp($a['name'], $b['name']));
$self = $self ?? '';
$avail = (array) ($d['avail'] ?? []);
$addOpen = !empty($d['adding']);
?>
<section data-load-plan data-weight-unit="<?= h($unit) ?>" data-revision="<?= (int) ($stored->revision ?? 0) ?>" data-id="<?= (int) $d['cid'] ?>" data-type="<?= h($d['type']) ?>" data-self="<?= h($self) ?>" class="clp" id="load-plan" aria-label="<?= $sea ? 'Sea' : 'Air' ?> consolidation">
<?php include __DIR__ . '/con_cargo_view.php'; ?>

<section class="cpk" id="packages" aria-labelledby="cpk-h">
  <header class="cpk-head">
    <div><h2 id="cpk-h">Packages on board</h2><span class="mut sm"><?= count($rows) ?> package<?= count($rows) === 1 ? '' : 's' ?> · <?= count($groups) ?> customer<?= count($groups) === 1 ? '' : 's' ?> · <?= h($fmtW($actual)) ?></span></div>
    <div class="cpk-tools">
      <label class="sl-search cpk-q"><i class="ic ic-sm i-search" aria-hidden="true"></i><input type="search" data-cpk-q placeholder="Customer, locker, tracking or contents" aria-label="Find a package on board"></label>
      <?php if (!empty($can['packing'])): ?><a class="sl-btn ghost sm" href="<?= h($can['packing']) ?>" target="_blank"><i class="ic ic-sm i-print" aria-hidden="true"></i>Packing list</a><?php endif; ?>
      <?php if ($canEdit): ?><button type="button" class="sl-btn sm" data-cpk-add aria-expanded="<?= $addOpen ? 'true' : 'false' ?>" aria-controls="cpk-add"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add packages</button><?php endif; ?>
    </div>
  </header>

  <?php if ($canEdit): ?>
  <form class="cpk-add" id="cpk-add" data-sl-form action="ajax/sl/consolidation_ajax.php" method="post"<?= $addOpen ? '' : ' hidden' ?>>
    <input type="hidden" name="op" value="add_items"><input type="hidden" name="id" value="<?= (int) $d['cid'] ?>"><input type="hidden" name="type" value="<?= h($d['type']) ?>"><input type="hidden" name="ids" data-cpk-ids value="">
    <div class="cpk-add-h">
      <div><b>Add packages to <?= h($d['code']) ?></b><span class="mut sm">At the warehouse, <?= $sea ? 'sea' : 'air' ?> mode, not in a consolidation yet<?= count($avail) ? ' · ' . count($avail) . ' waiting' : '' ?>. No weight limit.</span></div>
      <label class="sl-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input type="search" data-cpk-aq value="<?= h($d['q'] ?? '') ?>" placeholder="Filter, or press Enter to search everything" aria-label="Find packages to add"></label>
    </div>
    <?php if ($avail): ?>
    <div class="cpk-av-list" role="list">
      <label class="cpk-av cpk-all"><input type="checkbox" data-cpk-all><span>Select all shown</span></label>
      <?php foreach ($avail as $a): ?>
      <label class="cpk-av" role="listitem" data-text="<?= h(strtolower($a['name'] . ' ' . $a['lock'] . ' ' . $a['id'] . ' ' . $a['post'] . ' ' . $a['what'])) ?>">
        <input type="checkbox" value="<?= (int) $a['oid'] ?>" data-w="<?= h((string) $a['wn']) ?>">
        <span class="cpk-av-who"><b><?= h($a['name']) ?></b><small><?= h($a['lock'] ?: 'No locker') ?> · <?= h($a['what']) ?></small></span>
        <span class="mono cpk-av-id"><?= h($a['id']) ?></span>
        <span class="sl-pill <?= h($a['stC']) ?>"><?= h($a['st']) ?></span>
        <b class="mono cpk-av-w"><?= h($a['w']) ?></b>
      </label>
      <?php endforeach; ?>
    </div>
    <?php else: ?><p class="mut sm cpk-none">Nothing is waiting to be consolidated<?= ($d['q'] ?? '') !== '' ? ' for “' . h($d['q']) . '”' : '' ?>.</p><?php endif; ?>
    <div class="cpk-add-f"><span class="sm" data-cpk-sel>Nothing selected</span><div class="row g8"><button type="button" class="sl-btn ghost" data-cpk-close>Close</button><button type="submit" class="sl-btn" disabled><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add selected</button></div></div>
  </form>
  <?php endif; ?>

  <?php if (!$rows): ?>
  <div class="cpk-empty"><span class="sl-tile"><i class="ic <?= $sea ? 'i-ship' : 'i-plane' ?>" aria-hidden="true"></i></span><b>No packages on board yet</b><span class="mut sm"><?= $canEdit ? 'Use “Add packages” to put waiting parcels in this consolidation.' : 'Packages appear here as they are added.' ?></span></div>
  <?php else: ?>
  <div class="cpk-list">
    <div class="cpk-cols" aria-hidden="true"><span>Package</span><span>Contents</span><span class="r">Pcs</span><span class="r">Weight</span><span>Status</span><span>Position</span><span></span></div>
    <?php foreach ($groups as $g): ?>
    <div class="cpk-group" data-cpk-group>
      <div class="cpk-gh"><span class="sl-av sm"><?= h($g['ini']) ?></span><b><?= h($g['name']) ?></b><?php if ($g['lock'] !== ''): ?><span class="mono mut sm"><?= h($g['lock']) ?></span><?php endif; ?><span class="cpk-gm"><?= count($g['rows']) ?> package<?= count($g['rows']) === 1 ? '' : 's' ?> · <?= h($fmtW($g['w'])) ?></span></div>
      <?php foreach ($g['rows'] as $r): $p = $positionMap[(int) $r['oid']] ?? null; $last = $r['hist'][0] ?? null; ?>
      <div class="cpk-row <?= h($r['cls'] ?? '') ?>" data-text="<?= h(strtolower(($r['name'] ?? '') . ' ' . ($r['lock'] ?? '') . ' ' . $r['id'] . ' ' . ($r['post'] ?? '') . ' ' . ($r['what'] ?? ''))) ?>">
        <span class="cpk-id"><a class="mono" href="<?= h($r['href']) ?>"><?= h($r['id']) ?></a><?php if (($r['post'] ?? '—') !== '—'): ?><small class="mono" title="Carrier tracking"><?= h($r['post']) ?></small><?php endif; ?></span>
        <span class="cpk-what"><?= h($r['what'] ?? '—') ?></span>
        <span class="r mono"><?= (int) ($r['pcs'] ?? 0) ?: '—' ?></span>
        <span class="r cpk-w"><b class="mono"><?= h($r['w']) ?></b><?php if ((float) ($r['vn'] ?? 0) > 0): ?><small class="mono"><?= h($r['v']) ?> vol</small><?php endif; ?></span>
        <span class="cpk-st"><span class="sl-pill <?= h($r['stC'] ?? '') ?>"><?= h($r['stL'] ?? '') ?></span><?php if ($last): ?><small title="<?= h($last['t'] . ($last['c'] !== '' ? ' · ' . $last['c'] : '')) ?>"><?= h($last['at']) ?></small><?php endif; ?></span>
        <span class="cpk-pos" data-load-label="<?= (int) $r['oid'] ?>"><?= $p ? h($p['bay'] . '-' . $p['row'] . '-' . $p['tier']) : 'Unplaced' ?></span>
        <span class="cpk-act"><?php if (!empty($r['canDel'])): ?><button type="button" class="sl-ib sm" aria-label="Take <?= h($r['id']) ?> out of <?= h($d['code']) ?>" title="Take out of this consolidation" data-sl-action="ajax/sl/consolidation_ajax.php" data-p-op="remove_item" data-p-id="<?= (int) $d['cid'] ?>" data-p-type="<?= h($d['type']) ?>" data-p-order="<?= (int) $r['oid'] ?>" data-sl-confirm="Take <?= h($r['id']) ?> (<?= h($r['name'] ?? '') ?>) out of <?= h($d['code']) ?>? It goes back to the warehouse list." data-sl-reload><i class="ic ic-sm i-x" aria-hidden="true"></i></button><?php endif; ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
    <div class="cpk-empty sm" data-cpk-none hidden>No package on board matches.</div>
  </div>
  <?php endif; ?>
</section>

<?php if ($rows): ?>
<details class="clp-editor"<?= $positions ? ' open' : '' ?>><summary><span><b>Cargo positions</b><small>Optional: record where each package sits (bay, row, tier). No weight limit per bay.</small></span></summary>
<?php if ($editable): ?><form data-load-form><div class="sl-tw"><table class="sl-table"><thead><tr><th>Package</th><th>Customer</th><th>Weight</th><th>Bay</th><th>Row</th><th>Tier</th><th></th></tr></thead><tbody><?php foreach ($rows as $r): $pos = $positionMap[(int) $r['oid']] ?? []; ?><tr data-load-row="<?= (int) $r['oid'] ?>" data-weight="<?= (float) $r['weightKg'] ?>"><td class="mono"><?= h($r['id']) ?></td><td><?= h($r['name'] ?? '') ?></td><td class="mono"><?= h($r['w']) ?></td><?php foreach (['bay' => 100, 'row' => 20, 'tier' => 10] as $axis => $limit): ?><td><input class="sl-in" type="number" min="1" max="<?= $limit ?>" name="<?= $axis ?>" aria-label="<?= h(ucfirst($axis) . ' for ' . $r['id']) ?>" value="<?= isset($pos[$axis]) ? (int) $pos[$axis] : '' ?>"></td><?php endforeach; ?><td><button class="sl-btn ghost sm" data-load-clear type="button">Clear</button></td></tr><?php endforeach; ?></tbody></table></div>
<div class="row between wrap g8"><div class="clp-balance" data-load-balance role="list"></div><div class="row g8"><span data-load-feedback role="status" class="mut sm"></span><button class="sl-btn" type="submit">Save positions</button></div></div></form>
<?php else: ?><p class="mut sm"><?= ($d['lq'] ?? '') !== '' ? 'Clear the package search to edit the complete load plan.' : 'Cargo positions are available when load planning is enabled for this consolidation.' ?></p><?php endif; ?></details>
<?php endif; ?>

<?php if (!empty($can['print']) || !empty($can['packingX']) || !empty($d['files'])): ?>
<section class="cpk-docs" aria-labelledby="cpk-docs-h"><h3 id="cpk-docs-h">Documents</h3><div>
  <?php if (!empty($can['print'])): ?><a target="_blank" href="<?= h($can['print']) ?>"><i class="ic ic-sm i-file" aria-hidden="true"></i><span><?= $sea ? 'Bill of lading / manifest' : 'Master air waybill / manifest' ?></span><small>PDF</small></a><?php endif; ?>
  <?php if (!empty($can['packingX'])): ?><a href="<?= h($can['packingX']) ?>"><i class="ic ic-sm i-file" aria-hidden="true"></i><span>Packing list · <?= count($rows) ?> packages</span><small>Excel</small></a><?php endif; ?>
  <?php foreach ($d['files'] ?? [] as $f): ?><a target="_blank" href="<?= h($f['url']) ?>"><i class="ic ic-sm i-paperclip" aria-hidden="true"></i><span><?= h($f['name']) ?></span><small>File</small></a><?php endforeach; ?>
</div></section>
<?php endif; ?>
</section>
