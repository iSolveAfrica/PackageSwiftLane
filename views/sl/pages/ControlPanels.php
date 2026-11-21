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
    <div class="cpa-grid">
      <?php foreach ($d['attn'] as $index => $q): $tone = ['violet', 'amber', 'blue', 'mint'][$index % 4]; $delta = $q['n'] - $q['carried']; $newPeak = max(1, max($q['new'])); ?>
        <a class="cpa t-<?= $tone ?><?= $q['n'] ? '' : ' zero' ?>" href="<?= h($q['href']) ?>">
          <div class="cpa-h"><span class="cpa-ic"><i class="ic ic-sm <?= h($q['icon']) ?>" aria-hidden="true"></i></span><b><?= h($q['l']) ?></b><i class="ic ic-sm i-right cpa-go" aria-hidden="true"></i></div>
          <div class="cpa-fig"><b><?= sl_num($q['n']) ?></b><span><?= $q['n'] ? 'open' : 'all clear' ?></span>
            <?php if ($q['n']): ?><em>+<?= sl_num($q['new7']) ?> this week</em><?php endif; ?></div>
          <span class="cpa-why"><?= h($q['why']) ?></span>
          <?php if ($q['n']): ?>
          <div class="cpa-age" role="img" aria-label="Age of open items: <?= h(implode(', ', array_map(fn($a) => $a[0] . ' ' . $a[1], $q['ages']))) ?>">
            <?php foreach ($q['ages'] as $ai => [$al, $av]): if (!$av) continue; ?><i class="a<?= $ai ?>" style="flex:<?= (int) $av ?>" title="<?= h($al) ?>: <?= sl_num($av) ?>"></i><?php endforeach; ?>
          </div>
          <dl class="cpa-ages"><?php foreach ($q['ages'] as $ai => [$al, $av]): ?><div><dt><i class="a<?= $ai ?>"></i><?= h($al) ?></dt><dd><?= sl_num($av) ?></dd></div><?php endforeach; ?></dl>
          <?php endif; ?>
          <div class="cpa-flow">
            <?php $newLabels = array_map(fn($i) => date('M j', strtotime("-$i days")), range(6, 0)); ?><div class="cpa-bars" role="img" aria-label="<?= h($q['l']) ?>: new per day, last 7 days" <?= sl_chartAttrs([['name' => $q['l'], 'values' => $q['new']]], $newLabels, $q['l'] . ' · new per day') ?>><?php foreach ($q['new'] as $i => $v): ?><i style="height:<?= max(4, (int) round($v / $newPeak * 100)) ?>%" title="<?= h(date('D j M', strtotime('-' . (6 - $i) . ' days'))) ?>: <?= (int) $v ?> new"></i><?php endforeach; ?></div>
            <div class="cpa-meta"><span>New / day · 7 d</span><span><?= $q['oldest'] !== null ? 'Oldest ' . (int) $q['oldest'] . ' d' : '—' ?><?= $q['n'] ? ' · ' . (int) $q['stale'] . '% over 30 d' : '' ?></span></div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?><div class="sl-empty">Nothing needs your attention.</div><?php endif; ?>
</section>
<section class="col g12" aria-labelledby="health-title"><h2 class="sl-ct" id="health-title">Workspace health</h2>
<div class="cph-grid"><?php foreach ($d['health'] as $hh): ?><a class="cph <?= h($hh['c']) ?>" href="<?= h($hh['href'] ?? '#') ?>"><span class="cph-ic"><i class="ic ic-sm <?= h($hh['i'] ?? 'i-check') ?>" aria-hidden="true"></i></span><span class="col grow" style="min-width:0"><span class="row between g8"><b><?= h($hh['t']) ?></b><span class="cph-s"><i></i><?= h($hh['s']) ?></span></span><small><?= h($hh['x'] ?? '') ?></small><?php if (isset($hh['pct'])): ?><span class="cph-bar"><i style="width:<?= max(0, min(100, (int) $hh['pct'])) ?>%"></i></span><?php endif; ?></span></a><?php endforeach; ?></div>
</section>
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
