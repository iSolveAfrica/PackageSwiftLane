<?php
/**
 * Consolidation page hero: package list on the left, route map on the right; a package opens a pop-up
 * with everything about it. Data: sl_consolidationDetailData() ($d['map'], $d['rows'][*]['hist']).
 * Styles: assets/swiftlane/pages/ConsolidationMap.css (loaded below).
 * No charges or payment state on cards or pop-ups: consolidation billing is in Finance.
 */
$mp = $d['map'];
$g = $mp['geo'];
$v = $g['veh'];
$pts = $g['pts'];
$np = count($pts);
$lane = $mp['lane'];
$W = SL_TC_W; $H = SL_TC_H;
$chip = function (float $x, float $y, string $k, string $val, string $anchor) use ($W) {
    $w = 16 + mb_strlen($k) * 6.6 + 8 + mb_strlen($val) * 7.4 + 14;
    $x0 = $anchor === 'below' ? $x - $w / 2 : $x + 14;
    $y0 = $anchor === 'below' ? $y + 20 : $y + 10;
    $x0 = max(6, min($W - $w - 6, $x0));
    return '<g class="cdm-chip"><rect x="' . round($x0, 1) . '" y="' . round($y0, 1) . '" width="' . round($w, 1) . '" height="28" rx="9"/><text x="' . round($x0 + 14, 1) . '" y="' . round($y0 + 18.5, 1) . '"><tspan class="k">' . h($k) . '</tspan><tspan class="v" dx="8">' . h($val) . '</tspan></text></g>';
};
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pages/ConsolidationMap.css')) ?>">
<section class="cdm" aria-label="Route and packages of <?= h($d['code']) ?>">
  <aside class="cdm-list">
    <div class="cdm-lh"><h2>Packages</h2><span class="cdm-count"><?= (int) $d['pkN'] ?> · <?= h($d['wt']) ?></span></div>
    <label class="cdm-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input type="search" data-cdm-q placeholder="Customer, locker, tracking or contents" aria-label="Find a package"></label>
    <div class="cdm-cards" role="list">
      <?php foreach ($d['rows'] as $r): ?>
      <button type="button" role="listitem" class="cdm-pk <?= h($r['cls']) ?>" data-cdm-open="<?= (int) $r['n'] ?>" data-text="<?= h(strtolower($r['name'] . ' ' . $r['lock'] . ' ' . $r['id'] . ' ' . $r['post'] . ' ' . $r['what'])) ?>">
        <span class="sl-av sm"><?= h($r['ini']) ?></span>
        <span class="cdm-pk-b">
          <span class="row between g8"><b class="fw5"><?= h($r['name']) ?></b><span class="mono sm"><?= h($r['w']) ?></span></span>
          <span class="mono mut sm cdm-ids"><?= h($r['id']) ?><?= $r['lock'] !== '' ? ' · ' . h($r['lock']) : '' ?></span>
          <span class="row between g8"><span class="cdm-what"><?= h($r['what']) ?></span><span class="sl-pill <?= h($r['stC']) ?>" title="<?= h($r['stL']) ?>"><span class="cdm-stt"><?= h($r['stL']) ?></span></span></span>
        </span>
      </button>
      <?php endforeach; ?>
      <div class="cdm-empty" data-cdm-empty<?= $d['rows'] ? ' hidden' : '' ?>><?= $d['rows'] ? 'No package matches.' : 'No packages in this consolidation yet.' ?></div>
    </div>
  </aside>

  <div class="cdm-map">
    <img class="cdm-world" src="<?= h(cdp_asset('assets/swiftlane/maps/world-dots.svg')) ?>" alt="" aria-hidden="true">
    <svg class="cdm-over" viewBox="0 0 <?= $W ?> <?= $H ?>" preserveAspectRatio="xMidYMid slice" role="img" aria-label="Route from <?= h($lane['from']['city']) ?> to <?= h($lane['to']['city']) ?>, <?= (int) $mp['pct'] ?>% covered">
      <defs><radialGradient id="cdm-glow"><stop offset="0" stop-color="#1f6fb0" stop-opacity=".28"/><stop offset="1" stop-color="#1f6fb0" stop-opacity="0"/></radialGradient><radialGradient id="cdm-sun"><stop offset="0" stop-color="#ffc72c" stop-opacity=".55"/><stop offset="1" stop-color="#ffc72c" stop-opacity="0"/></radialGradient></defs>
      <?php foreach ($mp['glows'] as $gp): ?><circle class="cdm-hub" cx="<?= $gp[0] ?>" cy="<?= $gp[1] ?>" r="3.6"/><?php endforeach; ?>
      <?php foreach ($pts as $pt): ?><circle cx="<?= $pt[0] ?>" cy="<?= $pt[1] ?>" r="70" fill="url(#cdm-glow)"/><?php endforeach; ?>
      <path class="cdm-route" d="<?= h($g['path']) ?>"/>
      <path class="cdm-done" d="<?= h($g['done']) ?>"/>
      <?php foreach ($pts as $pt): ?><g class="cdm-pin" transform="translate(<?= $pt[0] ?> <?= $pt[1] ?>)"><circle r="13" class="halo"/><circle r="6.5" class="dot"/></g><?php endforeach; ?>
      <?= $chip($pts[0][0], $pts[0][1], 'From', $lane['from']['city'] . ', ' . $lane['from']['country'], 'below') ?>
      <?= $chip($pts[$np - 1][0], $pts[$np - 1][1], 'Dest', $lane['to']['city'] . ', ' . $lane['to']['country'], 'left') ?>
      <g transform="translate(<?= $v[0] ?> <?= $v[1] ?>)"><circle r="46" fill="url(#cdm-sun)" class="cdm-pulse"/><g transform="rotate(<?= $v[2] ?>) scale(1.25)">
        <?php if ($sea): ?><path class="cdm-craft" d="M-16 -7h22l12 7-12 7h-22l-4-7z"/><rect x="-11" y="-4" width="5" height="8" rx="1" fill="#8a6a00"/><rect x="-4" y="-4" width="5" height="8" rx="1" fill="#8a6a00"/>
        <?php else: ?><path class="cdm-craft" d="M20 0c0-1.6-1.4-2.6-3.4-2.6H6.4L-4.8-17h-4.4l5.2 14.4h-9.6l-3.6-5.2h-3.4l2.4 7.8-2.4 7.8h3.4l3.6-5.2h9.6L-9.2 17h4.4L6.4 2.6h10.2C18.6 2.6 20 1.6 20 0z"/><?php endif; ?>
      </g></g>
    </svg>
    <div class="cdm-legend"><span><i class="l1"></i>Travelled</span><span><i class="l2"></i>Remaining</span></div>
    <div class="cdm-card">
      <div class="row between g8"><span class="cdm-k"><?= $sea ? 'Container' : 'Air load' ?> <?= h($d['code']) ?></span><span class="sl-pill <?= $mp['late'] ? 'overdue' : h($d['stCls']) ?>"><?= h($mp['late'] ? 'Past ETA' : $d['st']) ?></span></div>
      <b class="cdm-where"><?= h($mp['where']) ?></b>
      <div class="cdm-bar" role="img" aria-label="<?= (int) $mp['pct'] ?>% of the route covered"><i style="width:<?= max(3, min(100, (int) $mp['pct'])) ?>%"></i></div>
      <div class="cdm-ends"><span><b><?= h($lane['from']['code']) ?></b> <?= h($lane['from']['city']) ?></span><span><?= (int) $mp['pct'] ?>%</span><span><b><?= h($lane['to']['code']) ?></b> <?= h($lane['to']['city']) ?></span></div>
      <dl class="cdm-facts"><div><dt>ETA</dt><dd><?= h($mp['eta']) ?></dd></div><div><dt>On the way</dt><dd><?= h($mp['onway']) ?></dd></div><div><dt><?= $sea ? 'Fill' : 'Load' ?></dt><dd><?= h($d['capS']) ?></dd></div></dl>
    </div>
  </div>
</section>

<?php foreach ($d['rows'] as $r): ?>
<template data-cdm-tpl="<?= (int) $r['n'] ?>">
  <div class="cdm-ph">
    <span class="sl-av"><?= h($r['ini']) ?></span>
    <div class="col grow" style="min-width:0"><b class="fw5"><?= h($r['name']) ?></b><span class="mut sm"><?= h(trim(($r['lock'] !== '' ? 'Locker ' . $r['lock'] : '') . ($r['phone'] !== '' ? ' · ' . $r['phone'] : ''), ' ·')) ?></span></div>
    <span class="sl-pill <?= h($r['stC']) ?>"><?= h($r['stL']) ?></span>
    <button type="button" class="sl-ib sm" data-cdm-close aria-label="Close"><i class="ic ic-sm i-x" aria-hidden="true"></i></button>
  </div>
  <div class="cdm-pcode"><span class="mut sm">Swift tracking</span><b class="mono"><?= h($r['id']) ?></b><span class="mut sm">in <?= h($d['code']) ?> · package <?= (int) $r['n'] ?> of <?= (int) $d['pkN'] ?></span></div>
  <dl class="cdm-pdl">
    <div><dt>Postal / store tracking</dt><dd class="mono"><?= h($r['post']) ?></dd></div>
    <div class="wide"><dt>What is inside</dt><dd><?= h($r['what']) ?></dd></div>
    <div><dt>Pieces</dt><dd><?= (int) $r['pcs'] ?></dd></div>
    <div><dt>Weight</dt><dd><?= h($r['w']) ?></dd></div>
    <div><dt><?= $sea ? 'Volume' : 'Volumetric weight' ?></dt><dd><?= h($r['v']) ?></dd></div>
  </dl>
  <h4 class="cdm-h4">Latest tracking</h4>
  <?php if ($r['hist']): ?><ol class="cdm-tl"><?php foreach ($r['hist'] as $i => $ev): ?><li class="<?= $i === 0 ? 'now' : '' ?>"><b><?= h($ev['t']) ?></b><span><?= h($ev['c']) ?></span><time><?= h($ev['at']) ?></time></li><?php endforeach; ?></ol>
  <?php else: ?><p class="mut sm">No tracking events recorded for this package yet.</p><?php endif; ?>
  <div class="cdm-pacts">
    <a class="sl-btn" href="<?= h($r['href']) ?>"><i class="ic ic-sm i-eye" aria-hidden="true"></i>Open package</a>
    <?php if ($r['canDel']): ?><button type="button" class="sl-btn ghost" style="color:var(--red-ink)" data-sl-action="ajax/sl/consolidation_ajax.php" data-p-op="remove_item" data-p-id="<?= (int) $d['cid'] ?>" data-p-type="<?= h($d['type']) ?>" data-p-order="<?= (int) $r['oid'] ?>" data-sl-confirm="Take <?= h($r['id']) ?> out of <?= h($d['code']) ?>?" data-sl-reload><i class="ic ic-sm i-x" aria-hidden="true"></i>Remove from <?= h($d['code']) ?></button><?php endif; ?>
  </div>
</template>
<?php endforeach; ?>
<dialog class="cdm-pop" id="cdm-pop" aria-label="Package details"><div class="cdm-pop-in" data-cdm-body></div></dialog>
<script>
(function () {
  var dlg = document.getElementById('cdm-pop'), body = dlg && dlg.querySelector('[data-cdm-body]');
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-cdm-open]');
    if (b && dlg && typeof dlg.showModal === 'function') {
      var t = document.querySelector('template[data-cdm-tpl="' + b.getAttribute('data-cdm-open') + '"]');
      if (!t) return;
      body.textContent = ''; body.appendChild(t.content.cloneNode(true));
      document.querySelectorAll('.cdm-pk.on').forEach(function (x) { x.classList.remove('on'); }); b.classList.add('on');
      dlg.showModal(); return;
    }
    if (e.target.closest('[data-cdm-close]') || e.target === dlg) dlg.close();
  });
  var q = document.querySelector('[data-cdm-q]');
  if (q) q.addEventListener('input', function () {
    var v = q.value.trim().toLowerCase(), shown = 0;
    document.querySelectorAll('.cdm-pk').forEach(function (c) { var ok = !v || (c.getAttribute('data-text') || '').indexOf(v) !== -1; c.hidden = !ok; if (ok) shown++; });
    var em = document.querySelector('[data-cdm-empty]'); if (em) { em.hidden = shown > 0; em.textContent = 'No package matches.'; }
  });
})();
</script>
