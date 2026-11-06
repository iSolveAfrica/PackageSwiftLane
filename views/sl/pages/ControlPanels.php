<?php
/**
 * Control panels - from docs/handover/artifact-source/ControlPanels.dc.html.
 * The Mobile boards (ControlPanelsMobile, 2, 3) are this page at phone width: the
 * shell supplies the mobile header and the cards stack (ControlPanels.css @640px).
 * Data: views/sl/data/control_panels.php (sl_controlPanelsData).
 */
if (!isset($d)) { $d = []; }
?>
<div class="cpx-hero"><div><h1><i class="ic i-grid" aria-hidden="true"></i>Control panels</h1><p class="sl-lede" style="font-size:14.5px;margin-top:6px">Every module in your workspace, grouped by the work it supports</p></div><label class="cpx-find"><i class="ic ic-sm i-search" aria-hidden="true"></i><input aria-label="Find a panel or action" placeholder="Find a panel or action" id="cpx-q"><kbd>⌘K</kbd></label></div>
<div class="row wrap g8 cpx-pins"><span class="up" style="margin-right:4px">Pinned</span><?php foreach ($d['pins'] as $p): ?><a class="chipbtn" href="<?= h($p['href']) ?>"><i class="ic ic-sm <?= h($p['icon']) ?>" aria-hidden="true"></i><?= h($p['label']) ?></a><?php endforeach; ?><span class="grow"></span><a class="chipbtn" href="<?= sl_route('SettingsHub') ?>"><i class="ic ic-sm i-sliders" aria-hidden="true"></i>Customise</a></div>
<div class="split">
<section class="a sl-card col g12"><div class="row between"><h2 class="sl-ct">Needs attention</h2><span class="mut sm">Updated <?= h($d['updated']) ?></span></div><div class="cpx-attn">
<?php foreach ($d['attn'] as $a): ?><a href="<?= h($a['href']) ?>"><span class="up"><?= h($a['l']) ?></span><b><?= sl_num($a['n']) ?></b><span class="d"<?= $a['up'] ? ' style="color:var(--amber-ink)"' : '' ?>><?= h($a['d']) ?></span><div class="cpx-mb" role="img" aria-label="<?= h($a['aria']) ?>"><?php foreach ($a['bars'] as $b): ?><i style="<?= h($b) ?>"></i><?php endforeach; ?></div><div class="cpx-hl"><span>7d ago</span><span>Today</span></div></a><?php endforeach; ?>
</div><?php if (!$d['attn']): ?><div class="sl-empty">Nothing needs your attention.</div><?php endif; ?></section>
<section class="b sl-card col g8"><h2 class="sl-ct">Workspace health</h2>
<?php foreach ($d['health'] as $hh): ?><div class="row g10 sm"><span class="cpx-dot <?= h($hh['c']) ?>" aria-hidden="true"></span><span class="grow"><?= h($hh['t']) ?></span><span<?= $hh['c'] ? ' style="color:var(--amber-ink)"' : ' class="mut"' ?>><?= h($hh['s']) ?></span></div><?php endforeach; ?></section>
</div>
<div class="cpx-cols" id="cpx-cols"><?php foreach ($d['sections'] as $s): ?><section class="cpx-card" id="<?= h($s['id']) ?>"><div class="cpx-ch"><span class="tl"><i class="ic <?= h($s['icon']) ?>" aria-hidden="true"></i></span><div><b><?= h($s['t']) ?></b><span><?= h($s['s']) ?></span></div></div><?php foreach ($s['rows'] as $r): ?><a class="cpx-row" href="<?= h($r['href']) ?>"><i class="ic ic-sm <?= h($r['icon']) ?>" aria-hidden="true"></i><span class="nm"><span><?= h($r['label']) ?></span><small><?= h($r['sub']) ?></small></span><?php if ($r['n'] !== ''): ?><span class="sl-badge mono"><?= h($r['n']) ?></span><?php endif; ?><i class="ic ic-sm i-right ch" aria-hidden="true"></i></a><?php endforeach; ?></section><?php endforeach; ?></div>
<div class="sl-empty" id="cpx-none" hidden>No panel matches.</div>
<script>
(function(){var q=document.getElementById('cpx-q');if(!q)return;document.addEventListener('keydown',function(e){if((e.metaKey||e.ctrlKey)&&e.key==='k'){e.preventDefault();q.focus();}});
q.addEventListener('input',function(){var t=q.value.trim().toLowerCase(),any=false;document.querySelectorAll('#cpx-cols .cpx-card').forEach(function(c){var shown=0;c.querySelectorAll('.cpx-row').forEach(function(r){var m=!t||r.textContent.toLowerCase().indexOf(t)>-1;r.hidden=!m;if(m)shown++;});c.hidden=!shown;if(shown)any=true;});document.getElementById('cpx-none').hidden=any;});
if(location.hash){var el=document.getElementById(location.hash.slice(1));if(el)el.scrollIntoView();}})();
</script>
