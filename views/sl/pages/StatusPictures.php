<?php
/** Status pictures: every status, with the picture the load plan shows for air and for sea. */
$img = fn($k) => cdp_asset('assets/swiftlane/cargo/' . $d['art'][$k][0]);
$sel = function (string $mode, array $r) use ($d) {
    $o = '<select class="sl-in" name="' . $mode . '[' . $r['id'] . ']" aria-label="' . h(ucfirst($mode) . ' picture for ' . $r['name']) . '" data-sp-pick>';
    foreach (sl_cargoArtOf($mode === 'sea') as $k => [$f, $alt, $short]) $o .= '<option value="' . h($k) . '"' . ($r[$mode]['key'] === $k ? ' selected' : '') . ' data-src="' . h(cdp_asset('assets/swiftlane/cargo/' . $f)) . '">' . h($short) . '</option>';
    return $o . '</select>';
};
?>
<style>
.sp-grid{display:grid;gap:10px}.sp-row{display:grid;grid-template-columns:minmax(160px,1fr) minmax(0,1.4fr) minmax(0,1.4fr);gap:14px;align-items:center;padding:12px 16px;border-top:1px solid var(--line)}
.sp-row:first-child{border-top:0}.sp-cell{display:grid;grid-template-columns:120px minmax(0,1fr);gap:10px;align-items:center}
.sp-cell img{width:120px;height:62px;object-fit:contain;border-radius:8px;background:var(--bg)}.sp-head{font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted)}
@media (max-width:900px){.sp-row{grid-template-columns:1fr}.sp-cell{grid-template-columns:96px minmax(0,1fr)}.sp-cell img{width:96px;height:50px}}
</style>
<div class="sl-wrap col g16">
<div class="sl-head"><div><h1 class="sl-title">Status pictures</h1><p class="sl-lede">The picture a load plan shows for each shipment status. When a consolidation's status changes, its picture changes with it.</p></div>
<div class="row g8 wrap"><button type="button" class="sl-btn ghost" data-sl-action="ajax/sl/cargo_art_ajax.php" data-p-op="reset" data-sl-confirm="Put every status back to its default picture?" data-sl-reload>Reset to defaults</button></div></div>
<form class="sl-card flush" data-sl-form data-sl-reload action="ajax/sl/cargo_art_ajax.php" method="post"><input type="hidden" name="op" value="save">
<div class="sp-row sp-head"><span>Status</span><span>Air consolidation</span><span>Sea consolidation</span></div>
<?php foreach ($d['rows'] as $r): ?>
<div class="sp-row"><span class="col"><b class="fw5"><?= h($r['name']) ?></b><small class="mut"><?= h($r['detail']) ?></small></span>
<?php foreach (['air', 'sea'] as $m): ?><label class="sp-cell"><img src="<?= h($img($r[$m]['key'])) ?>" alt="" data-sp-img><span class="col g4"><?= $sel($m, $r) ?><small class="mut"><?= $r[$m]['custom'] ? 'Your choice' : 'Default' ?></small></span></label><?php endforeach; ?>
</div>
<?php endforeach; ?>
<div class="row between wrap g8" style="padding:14px 16px;border-top:1px solid var(--line)"><span class="mut sm">New statuses get a picture from the words in their name (for example "Accepted by airline" shows the loading picture) until you choose one here.</span><button type="submit" class="sl-btn">Save pictures</button></div>
</form>
</div>
<script>
document.addEventListener('change', function (e) {
  var s = e.target.closest('[data-sp-pick]'); if (!s) return;
  var img = s.closest('.sp-cell, .mx-row') && s.closest('.sp-cell, .mx-row').querySelector('[data-sp-img]');
  if (img) img.src = s.selectedOptions[0].getAttribute('data-src');
});
</script>
