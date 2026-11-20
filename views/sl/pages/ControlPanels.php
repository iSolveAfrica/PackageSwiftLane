<?php
/**
 * Control panels - from docs/handover/artifact-source/ControlPanels.dc.html.
 * The Mobile boards (ControlPanelsMobile, 2, 3) are this page at phone width: the
 * shell supplies the mobile header and the cards stack (ControlPanels.css @640px).
 * Data: views/sl/data/control_panels.php (sl_controlPanelsData).
 */
if (!isset($d)) { $d = []; }
$featuredSections = array_values(array_filter($d['sections'], fn($section) => $section['featured']));
$otherSections = array_values(array_filter($d['sections'], fn($section) => !$section['featured']));
?>
<div class="cpx-hero"><div><h1><i class="ic i-grid" aria-hidden="true"></i>Control panels</h1><p class="sl-lede" style="font-size:14.5px;margin-top:6px">Every module in your workspace, grouped by the work it supports</p></div><label class="cpx-find"><i class="ic ic-sm i-search" aria-hidden="true"></i><input aria-label="Find a panel or action" placeholder="Find a panel or action" id="cpx-q"><kbd>⌘K</kbd></label></div>
<div class="row wrap g8 cpx-pins"><span class="up" style="margin-right:4px">Pinned</span><?php foreach ($d['pins'] as $p): ?><a class="chipbtn" href="<?= h($p['href']) ?>"><i class="ic ic-sm <?= h($p['icon']) ?>" aria-hidden="true"></i><?= h($p['label']) ?></a><?php endforeach; ?><span class="grow"></span><a class="chipbtn" href="<?= sl_route('SettingsHub') ?>"><i class="ic ic-sm i-sliders" aria-hidden="true"></i>Customise</a></div>
<section class="col g12" aria-labelledby="attention-title">
  <div class="row between wrap g8"><h2 class="sl-ct" id="attention-title">Needs attention</h2><span class="mut sm">Updated <?= h($d['updated']) ?></span></div>
  <?php if ($d['attn']): ?>
    <div class="sl-data-grid">
      <?php foreach ($d['attn'] as $index => $attention): ?>
        <article class="sl-data-card sl-data-card--<?= ['violet', 'amber', 'blue', 'mint'][$index % 4] ?>">
          <header><h3 class="sl-data-card__title"><a href="<?= h($attention['href']) ?>"><?= h($attention['l']) ?> ↗</a></h3><span class="sl-data-card__period">Open now</span></header>
          <p class="sl-data-card__value"><?= sl_num($attention['n']) ?></p>
          <p class="sl-data-card__detail">Open items created by each date</p>
          <?php
          $columnValues = $attention['s'];
          $columnLabels = array_map(fn($i) => date('M j', strtotime("-$i days")), range(6, 0));
          $columnTitle = $attention['l'] . ' by creation cutoff';
          include __DIR__ . '/partials/data_columns.php';
          ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?><div class="sl-empty">Nothing needs your attention.</div><?php endif; ?>
</section>
<section class="b sl-card col g8"><h2 class="sl-ct">Workspace health</h2>
<?php foreach ($d['health'] as $hh): ?><div class="row g10 sm"><span class="cpx-dot <?= h($hh['c']) ?>" aria-hidden="true"></span><span class="grow"><?= h($hh['t']) ?></span><span<?= $hh['c'] ? ' style="color:var(--amber-ink)"' : ' class="mut"' ?>><?= h($hh['s']) ?></span></div><?php endforeach; ?></section>
<div id="cpx-groups">
<?php if ($featuredSections): ?><section class="cpx-group" id="cpx-featured"><div class="cpx-group-head"><div><span class="up">Your workspace</span><h2>Centers &amp; teams</h2><p>Open a destination below, or search all panels above.</p></div><nav class="cpx-jump" aria-label="Jump to a center or team"><?php foreach ($featuredSections as $s): ?><a href="#<?= h($s['id']) ?>"><?= h($s['t']) ?><i class="ic ic-sm i-right" aria-hidden="true"></i></a><?php endforeach; ?></nav></div><div class="cpx-featured-grid"><?php foreach ($featuredSections as $s) include __DIR__ . '/partials/control_panel_section.php'; ?></div></section><?php endif; ?>
<?php if ($otherSections): ?><section class="cpx-group" id="cpx-other"><div class="cpx-group-head"><div><span class="up">Explore</span><h2>All workspace panels</h2><p>Shipping, people, finance and platform tools.</p></div></div><div class="cpx-cols"><?php foreach ($otherSections as $s) include __DIR__ . '/partials/control_panel_section.php'; ?></div></section><?php endif; ?>
</div>
<div class="sl-empty" id="cpx-none" hidden>No panel matches.</div>
<script>
(function(){var q=document.getElementById('cpx-q');if(!q)return;document.addEventListener('keydown',function(e){if((e.metaKey||e.ctrlKey)&&e.key==='k'){e.preventDefault();q.focus();}});
q.addEventListener('input',function(){var t=q.value.trim().toLowerCase(),any=false;document.querySelectorAll('#cpx-groups .cpx-card').forEach(function(c){var shown=0;c.querySelectorAll('.cpx-row').forEach(function(r){var m=!t||r.textContent.toLowerCase().indexOf(t)>-1;r.hidden=!m;if(m)shown++;});c.hidden=!shown;if(shown)any=true;});document.querySelectorAll('#cpx-groups .cpx-group').forEach(function(g){g.hidden=!g.querySelector('.cpx-card:not([hidden])');});document.getElementById('cpx-none').hidden=any;});
if(location.hash){var el=document.getElementById(location.hash.slice(1));if(el)el.scrollIntoView();}})();
</script>
