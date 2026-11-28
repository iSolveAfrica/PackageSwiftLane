<?php
/** Status pictures on the phone: one card per status, air and sea picture. */
$slPhone['tab'] = 'Menu';
echo sl_phoneTop('Status pictures', count($d['rows']) . ' statuses', ['back' => sl_route('SettingsHub')]);
?>
<main class="mx-body">
<div class="mx-ban info"><i class="ic ic-sm i-image" aria-hidden="true"></i><span>The picture a load plan shows for each status. It changes when the status changes.</span></div>
<form data-sl-form data-sl-reload action="ajax/sl/cargo_art_ajax.php" method="post" class="mx-form" style="display:flex;flex-direction:column;gap:12px"><input type="hidden" name="op" value="save">
<?php foreach ($d['rows'] as $r): ?>
<section class="mx-card mx-list"><div class="mx-row"><span class="mx-rm"><b><?= h($r['name']) ?></b><small><?= h($r['detail']) ?></small></span></div>
<?php foreach (['air' => 'plane', 'sea' => 'ship'] as $m => $ic): ?><label class="mx-row"><img src="<?= h(cdp_asset('assets/swiftlane/cargo/' . $d['art'][$r[$m]['key']][0])) ?>" alt="" data-sp-img style="width:72px;height:40px;object-fit:contain;flex:none"><span class="mx-rm"><small><?= $m === 'air' ? 'Air' : 'Sea' ?><?= $r[$m]['custom'] ? ' · your choice' : '' ?></small><select class="mx-in" name="<?= $m ?>[<?= (int) $r['id'] ?>]" data-sp-pick aria-label="<?= h(ucfirst($m) . ' picture for ' . $r['name']) ?>"><?php foreach ($d['art'] as $k => [$f, $alt, $short]): ?><option value="<?= h($k) ?>"<?= $r[$m]['key'] === $k ? ' selected' : '' ?> data-src="<?= h(cdp_asset('assets/swiftlane/cargo/' . $f)) ?>"><?= h($short) ?></option><?php endforeach; ?></select></span></label><?php endforeach; ?>
</section>
<?php endforeach; ?>
<div class="mx-btns"><button type="submit" class="mx-btn">Save pictures</button><button type="button" class="mx-btn sec" data-sl-action="ajax/sl/cargo_art_ajax.php" data-p-op="reset" data-sl-confirm="Put every status back to its default picture?" data-sl-reload>Reset to defaults</button></div>
</form>
</main>
<script>
document.addEventListener('change', function (e) { var s = e.target.closest('[data-sp-pick]'); if (!s) return; var r = s.closest('.mx-row'); var i = r && r.querySelector('[data-sp-img]'); if (i) i.src = s.selectedOptions[0].getAttribute('data-src'); });
</script>
