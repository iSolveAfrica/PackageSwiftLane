<?php
/**
 * Handover - deliver / picked-up form for a shipment, package or consolidation
 * (look of docs/handover/artifact-source/HandOut.dc.html and WarehouseDelivery.dc.html).
 * Used by courier_deliver_shipment.php, customer_package_deliver.php,
 * consolidate_deliver_shipment.php and consolidate_package_deliver_shipment.php.
 * Data: views/sl/data/ops_forms.php (sl_handoverData). Posts the legacy field names to
 * $d['action'] (legacy endpoint, or the page itself for consolidations, whose handler was
 * moved verbatim to views/sl/handlers/). Client: dataJs/sl/ops_forms.js.
 */
if (!isset($d)) { $d = []; }
$s = $d['sum'];
?>
<div class="sl-head"><div><a href="<?= h($d['back']) ?>" class="row g6 mut sm"><i class="ic ic-sm i-left" aria-hidden="true"></i><?= h($s['label']) ?> <?= h($s['track']) ?></a><h1 class="sl-title" style="margin-top:4px">Hand over <?= h($s['track']) ?></h1><p class="sl-lede">Record who received the <?= h(strtolower($s['label'])) ?>, with signature and photo. The customer is notified.</p></div></div>
<?php if ($d['handed']): ?><div class="sl-banner ok"><i class="ic i-check" aria-hidden="true"></i><span>This <?= h(strtolower($s['label'])) ?> is already marked <?= h($s['status']) ?>. Saving again records a new hand-over.</span></div><?php endif; ?>
<div class="split">
  <form class="a sl-card col g16" data-ops-form action="<?= h($d['action']) ?>" method="post" enctype="multipart/form-data" data-ok="<?= h($d['back']) ?>"<?php if ($d['gate']): ?> data-pickup-module="<?= h($d['module']) ?>" data-pickup-ids="<?= (int) $d['id'] ?>" data-pickup-owner="<?= h($d['owner']) ?>"<?php endif; ?>>
    <?php if ($d['idField']): ?><input type="hidden" name="<?= h($d['idField']) ?>" value="<?= (int) $d['id'] ?>"><?php endif; ?>
    <div><div class="up" style="margin-bottom:8px">How was it handed over?</div>
      <div class="sl-seg boxed" data-ho-kind><button type="button" class="on" data-v="picked_up">Picked up · customer collected it</button><button type="button" data-v="delivered">Delivered · we took it to them</button></div>
      <input type="hidden" name="handover_type" value="picked_up"></div>
    <div class="grid cols2">
      <label class="sl-field"><span class="sl-lab">Date</span><input class="sl-in" type="date" name="<?= h($d['dateField']) ?>" value="<?= h($d['today']) ?>" required></label>
      <label class="sl-field"><span class="sl-lab">Handled by (rider / staff)</span><span class="sl-sel native"><select name="driver_id" required><option value="">Choose</option><?php foreach ($d['drivers'] as $dr): ?><option value="<?= (int) $dr['id'] ?>" <?= $dr['id'] === $d['driver'] ? 'selected' : '' ?>><?= h($dr['label']) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
      <label class="sl-field" style="grid-column:1/-1"><span class="sl-lab">Received by</span><input class="sl-in" name="person_receives" placeholder="Full name of the person who received it" required></label>
    </div>
    <div class="grid cols2">
      <div class="sl-field"><span class="sl-lab row between"><span>Signature</span><button type="button" class="sl-btn ghost sm" data-sig-clear>Clear</button></span><canvas data-sig="sig-dataUrl" style="width:100%;height:150px;border:1.5px dashed var(--line-2);border-radius:var(--r-md);background:var(--field);touch-action:none" aria-label="Signature pad"></canvas><input type="hidden" name="sig-dataUrl" value=""><span class="sl-hint">Sign with a finger or the mouse</span></div>
      <label class="sl-drop" style="cursor:pointer"><i class="ic ic-lg i-image" aria-hidden="true"></i><span class="fw5" style="color:var(--ink)">Photo at hand-over</span><span class="sm" id="ho-photo" data-empty="JPG or PNG">JPG or PNG</span><input type="file" name="miarchivo" accept="image/*" capture="environment" hidden data-ops-file="#ho-photo"></label>
    </div>
    <div class="row g12 wrap sm"><?php foreach ($d['notify'] as $n): ?><label class="row g6"><input type="checkbox" name="<?= h($n[0]) ?>" value="1" <?= $n[2] ? 'checked' : '' ?>><?= h($n[1]) ?></label><?php endforeach; ?></div>
    <div class="row g8"><button type="submit" class="sl-btn"><i class="ic ic-sm i-check" aria-hidden="true"></i>Confirm hand-over</button><a class="sl-btn ghost" href="<?= h($d['back']) ?>">Cancel</a></div>
  </form>
  <aside class="b col g16">
    <section class="sl-card col g10"><div class="row between"><b class="mono fw5"><?= h($s['track']) ?></b><span class="sl-pill <?= h($s['pc']) ?>"><?= h($s['status']) ?></span></div><div class="sl-kv"><?php foreach ($s['kv'] as $kv): ?><div><span><?= h($kv[0]) ?></span><span><?= h($kv[1]) ?></span></div><?php endforeach; ?></div></section>
    <section class="sl-card col g8"><h2 class="sl-ct"><?= in_array($d['kind'], ['consol', 'consol_pkg'], true) ? 'Packages inside' : 'Items' ?></h2><?php foreach ($d['items'] as $it): ?><div class="row between sm" style="padding:6px 0;border-top:1px solid var(--line)"><span><?= (int) $it['q'] ?> × <?= h($it['t']) ?></span><span class="mono mut"><?= h(sl_weight($it['w'], 2)) ?><?= $it['dims'] ? ' · ' . h($it['dims']) : '' ?></span></div><?php endforeach; ?><?php if (!$d['items']): ?><div class="sl-empty">No items recorded.</div><?php endif; ?></section>
  </aside>
</div>
<script>
document.addEventListener('click', function (e) { var b = e.target.closest('[data-ho-kind] button'); if (!b) return; var g = b.parentNode; g.querySelectorAll('button').forEach(function (x) { x.classList.toggle('on', x === b); }); g.closest('form').querySelector('[name="handover_type"]').value = b.getAttribute('data-v'); });
</script>
