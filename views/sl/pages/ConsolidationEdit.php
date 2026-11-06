<?php
/**
 * ConsolidationEdit - edit an air or sea consolidation (look of
 * docs/handover/artifact-source/NewConsolidation.dc.html / ConsolidationDetail.dc.html).
 * Used by consolidate_edit.php and consolidate_package_edit.php. Data: views/sl/data/ops_forms.php
 * (sl_consolEditData). Posts the old form's fields to the page itself; the old view's
 * handler runs unchanged (views/sl/handlers/consolidate*_edit.php). Removing a row takes
 * the package out (the handler rewrites the detail rows from what is posted); packages are
 * added on the consolidation page. Client: dataJs/sl/ops_forms.js.
 */
if (!isset($d)) { $d = []; }
$s = $d['sum']; $v = $d['vals'];
$opt = function (array $rows, $cur, string $lab) { $o = ''; foreach ($rows as $r) { $id = (int) (is_array($r) ? $r['id'] : $r->id); $l = is_array($r) ? $r[$lab] : $r->$lab; $o .= '<option value="' . $id . '"' . ($id === (int) $cur ? ' selected' : '') . '>' . h(str_replace('_', ' ', (string) $l)) . '</option>'; } return $o; };
?>
<form data-ops-form action="<?= h($d['action']) ?>" method="post" enctype="multipart/form-data" data-ok="<?= h($d['back']) ?>" class="col g16" id="ce-form">
<input type="hidden" name="total_item" value="<?= count($d['items']) ?>" data-ce-total>
<input type="hidden" name="sender_address_id" value="<?= (int) $v['sender_address_id'] ?>"><input type="hidden" name="recipient_address_id" value="<?= (int) $v['recipient_address_id'] ?>">
<div class="sl-head"><div><a href="<?= h($d['back']) ?>" class="row g6 mut sm"><i class="ic ic-sm i-left" aria-hidden="true"></i><?= h($s['label']) ?> <?= h($s['track']) ?></a><h1 class="sl-title" style="margin-top:4px">Edit <?= h($s['track']) ?></h1></div><div class="row g8 wrap"><a class="sl-btn ghost" href="<?= h($d['detail']) ?>&add=1"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add packages</a><a class="sl-btn ghost" href="<?= h($d['back']) ?>">Cancel</a><button type="submit" class="sl-btn">Save changes</button></div></div>
<?php if ($d['dg']): ?><div class="sl-banner bad"><i class="ic i-alert" aria-hidden="true"></i><span>This consolidation holds dangerous goods only.</span></div><?php endif; ?>
<div class="split">
  <div class="a col g16">
    <section class="sl-card col g16"><h2 class="sl-ct">Consolidation</h2>
      <div class="grid cols3">
        <label class="sl-field"><span class="sl-lab">Status</span><span class="sl-sel native"><select name="status_courier"><?= $opt($d['statuses'], $v['status_courier'], 'mod_style') ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
        <label class="sl-field"><span class="sl-lab">Payment method</span><span class="sl-sel native"><select name="order_pay_mode"><?= $opt($d['payModes'], $v['order_pay_mode'], 'label') ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
        <label class="sl-field"><span class="sl-lab">Driver</span><span class="sl-sel native"><select name="driver_id"><option value="0">Unassigned</option><?= $opt($d['drivers'], $v['driver_id'], 'label') ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
        <label class="sl-field"><span class="sl-lab">Seals</span><input class="sl-in mono" name="seals" value="<?= h($v['seals']) ?>"></label>
        <label class="sl-field"><span class="sl-lab">Estimated arrival</span><input class="sl-in" type="date" name="estimated_eta" value="<?= h(substr($v['estimated_eta'], 0, 10)) ?>"></label>
        <?php if ($d['hasAwb']): ?><label class="sl-field"><span class="sl-lab">Air waybill (AWB)</span><input class="sl-in mono" name="awb_no" value="<?= h($v['awb_no']) ?>"></label><?php endif; ?>
      </div>
    </section>
    <section class="sl-card flush">
      <div class="row between" style="padding:16px 20px"><h2 class="sl-ct">Packages inside</h2><span class="sl-badge" data-ce-count><?= count($d['items']) ?></span></div>
      <div class="sl-tw"><table class="sl-table"><thead><tr><th>Package</th><th>Owner</th><th>Status</th><th class="num" style="width:90px">Weight</th><th class="num" style="width:76px">L</th><th class="num" style="width:76px">W</th><th class="num" style="width:76px">H</th><th class="num" style="width:90px">Vol. wt</th><th style="width:44px"></th></tr></thead>
      <tbody data-ce-rows>
      <?php foreach ($d['items'] as $it): ?><tr data-ce-row><td class="mono"><input type="hidden" name="order_id[]" value="<?= (int) $it['order_id'] ?>"><input type="hidden" name="prefix[]" value="<?= h($it['prefix']) ?>"><input type="hidden" name="order_no_item[]" value="<?= h($it['no']) ?>"><?= h($it['prefix'] . $it['no']) ?></td><td class="sm"><?= h($it['owner']) ?></td><td><span class="sl-pill <?= h($it['pc']) ?>"><?= h($it['st']) ?></span></td><td class="num"><input class="sl-in mono" name="weight[]" value="<?= h($it['weight']) ?>" inputmode="decimal" aria-label="Weight"></td><td class="num"><input class="sl-in mono" name="length[]" value="<?= h($it['length']) ?>" inputmode="decimal" aria-label="Length" data-ce-dim></td><td class="num"><input class="sl-in mono" name="width[]" value="<?= h($it['width']) ?>" inputmode="decimal" aria-label="Width" data-ce-dim></td><td class="num"><input class="sl-in mono" name="height[]" value="<?= h($it['height']) ?>" inputmode="decimal" aria-label="Height" data-ce-dim></td><td class="num"><input class="sl-in mono" name="weight_vol[]" value="<?= h($it['weight_vol']) ?>" inputmode="decimal" aria-label="Volumetric weight"></td><td><button type="button" class="sl-ib sm" aria-label="Take out" data-ce-rm><i class="ic i-x" aria-hidden="true"></i></button></td></tr><?php endforeach; ?>
      </tbody></table></div>
      <?php if (!$d['items']): ?><div class="sl-empty">No packages in this consolidation.</div><?php endif; ?>
    </section>
    <section class="sl-card col g12"><h2 class="sl-ct">Documents</h2>
      <?php foreach ($d['files'] as $fl): ?><div class="row between g8 sm" style="padding:6px 0;border-top:1px solid var(--line)"><a class="row g8" href="<?= h($fl->url) ?>" target="_blank" rel="noopener"><i class="ic ic-sm i-paperclip" aria-hidden="true"></i><?= h($fl->name) ?></a><button type="button" class="sl-ib sm" aria-label="Delete <?= h($fl->name) ?>" data-sl-action="<?= h($d['fileDel']) ?>" data-p-id="<?= (int) $fl->id ?>" data-sl-confirm="Delete <?= h($fl->name) ?>?" data-sl-reload><i class="ic ic-sm i-trash" aria-hidden="true"></i></button></div><?php endforeach; ?>
      <label class="sl-drop" style="cursor:pointer"><i class="ic ic-lg i-upload" aria-hidden="true"></i><span class="fw5" style="color:var(--ink)">Add documents</span><span class="sm" id="ce-f" data-empty="Manifests, invoices, photos">Manifests, invoices, photos</span><input type="file" name="filesMultiple[]" multiple hidden data-ops-file="#ce-f"></label>
    </section>
  </div>
  <aside class="b col g16"><section class="sl-card col g10"><div class="row between"><b class="mono fw5"><?= h($s['track']) ?></b><span class="sl-pill <?= h($s['pc']) ?>"><?= h($s['status']) ?></span></div><div class="sl-kv"><?php foreach ($s['kv'] as $kv): ?><div><span><?= h($kv[0]) ?></span><span><?= h($kv[1]) ?></span></div><?php endforeach; ?></div><button type="submit" class="sl-btn block">Save changes</button></section></aside>
</div>
</form>
<script>
(function () {
  var f = document.getElementById('ce-form'), meter = <?= json_encode($d['meter']) ?>;
  function count() { var n = f.querySelectorAll('[data-ce-row]').length; f.querySelector('[data-ce-total]').value = n; f.querySelector('[data-ce-count]').textContent = n; }
  f.addEventListener('click', function (e) { var b = e.target.closest('[data-ce-rm]'); if (!b) return; if (!confirm('Take this package out of the consolidation when you save?')) return; b.closest('tr').remove(); count(); });
  f.addEventListener('input', function (e) { if (!e.target.matches('[data-ce-dim]') || !meter) return; var tr = e.target.closest('tr'), g = function (n) { return parseFloat(tr.querySelector('[name="' + n + '[]"]').value) || 0; }; tr.querySelector('[name="weight_vol[]"]').value = (g('length') * g('width') * g('height') / meter).toFixed(2); });
})();
</script>
