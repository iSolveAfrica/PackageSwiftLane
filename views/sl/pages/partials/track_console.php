<?php
/**
 * Live tracking console: tracking list + dotted world map + overview panel (Overview / Timeline /
 * Documents / Cost / Priority). Always dark, on light and dark themes.
 *
 * Expects:
 *   $tc        sl_trackConsoleData() result
 *   $tcTitle   list heading, e.g. 'Tracking list'
 *   $tcNoun    'shipments' | 'consolidations'
 *   $tcExpand  optional href for the expand button (the full page)
 *   $tcFilters optional, false hides the in-console filter chips (the full page filters on the server)
 * Styles: assets/swiftlane/track-console.css · behaviour: assets/swiftlane/track-console.js
 * (both included here so any page can drop the partial in).
 */
if (empty($tc) || !is_array($tc)) return;
$tcTitle = $tcTitle ?? 'Tracking list';
$tcNoun = $tcNoun ?? 'shipments';
$tcExpand = $tcExpand ?? '';
$tcFilters = $tcFilters ?? true;
$tcNounOne = ['shipments' => 'shipment', 'consolidations' => 'consolidation', 'records' => 'record'][$tcNoun] ?? 'record';
$tcUid = 'tc' . substr(md5($tc['source'] . mt_rand()), 0, 6);
$items = $tc['items'];
$first = $items[0] ?? null;
$vw = SL_TC_W;
$vh = SL_TC_H;
/** Chip on the map: monospace text keeps its width predictable. */
$chip = function (float $x, float $y, string $k, string $v, string $anchor = 'left') {
    $w = 16 + mb_strlen($k) * 6.6 + 8 + mb_strlen($v) * 7.4 + 14;
    $x0 = $anchor === 'right' ? $x - $w - 14 : (in_array($anchor, ['center', 'below'], true) ? $x - $w / 2 : $x + 14);
    $y0 = $anchor === 'center' ? $y - 46 : ($anchor === 'below' ? $y + 20 : $y + 10);
    $x0 = max(6, min(SL_TC_W - $w - 6, $x0));
    return '<g class="tc-chip"><rect x="' . round($x0, 1) . '" y="' . round($y0, 1) . '" width="' . round($w, 1) . '" height="28" rx="9"/>'
        . '<text x="' . round($x0 + 14, 1) . '" y="' . round($y0 + 18.5, 1) . '"><tspan class="k">' . h($k) . '</tspan><tspan class="v" dx="8">' . h($v) . '</tspan></text></g>';
};
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/track-console.css')) ?>">
<script defer src="<?= h(cdp_asset('assets/swiftlane/track-console.js')) ?>"></script>
<div class="tc-wrap"><section class="tc" id="<?= h($tcUid) ?>" data-tc aria-label="Live tracking">
<svg class="tc-sprite" width="0" height="0" aria-hidden="true" focusable="false"><symbol id="<?= $tcUid ?>-plane" viewBox="0 0 600 190"><defs>
<linearGradient id="<?= $tcUid ?>-fus" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#FFE58A"/><stop offset=".28" stop-color="#FFCC33"/><stop offset=".7" stop-color="#F2B400"/><stop offset="1" stop-color="#B98600"/></linearGradient>
<linearGradient id="<?= $tcUid ?>-fin" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FFD84D"/><stop offset="1" stop-color="#E8A900"/></linearGradient>
<linearGradient id="<?= $tcUid ?>-red" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#E5483A"/><stop offset="1" stop-color="#A51F1A"/></linearGradient>
<linearGradient id="<?= $tcUid ?>-wing" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#F4F4F2"/><stop offset="1" stop-color="#A9ABAD"/></linearGradient>
<linearGradient id="<?= $tcUid ?>-eng" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#FFFFFF"/><stop offset=".45" stop-color="#E4E5E6"/><stop offset="1" stop-color="#8E9194"/></linearGradient>
<radialGradient id="<?= $tcUid ?>-intake" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#3A3D40"/><stop offset=".7" stop-color="#1B1C1E"/><stop offset="1" stop-color="#5B5F63"/></radialGradient>
<radialGradient id="<?= $tcUid ?>-shadow" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#000" stop-opacity=".45"/><stop offset="1" stop-color="#000" stop-opacity="0"/></radialGradient>
</defs>
<ellipse cx="300" cy="176" rx="250" ry="9" fill="url(#<?= $tcUid ?>-shadow)"/>
<path d="M326 79 L380 60 L390 60 L358 81 Z" fill="#C9CBCD"/>
<path d="M70 86 L104 72 L112 72 L100 88 Z" fill="#D9A300"/>
<path d="M58 88 L22 16 Q21 10 28 10 L50 10 Q56 10 60 16 L124 86 Z" fill="url(#<?= $tcUid ?>-fin)"/>
<path d="M36 40 L66 40 L84 60 L48 60 Z" fill="url(#<?= $tcUid ?>-red)"/>
<path d="M58 88 L22 16 Q21 10 28 10 L34 10 L70 86 Z" fill="#fff" opacity=".18"/>
<path d="M30 84 C 60 78 110 76 160 76 L 500 76 C 540 76 566 86 580 98 C 586 104 584 110 576 114 C 560 122 530 124 500 124 L 170 124 C 120 124 80 116 52 104 C 38 98 30 92 30 84 Z" fill="url(#<?= $tcUid ?>-fus)"/>
<path d="M96 104 C 140 108 170 108 200 108 L 520 108 C 540 108 556 106 568 104 L 566 110 C 552 114 536 116 520 116 L 200 116 C 160 116 126 112 100 108 Z" fill="url(#<?= $tcUid ?>-red)"/>
<path d="M70 82 C 110 79 140 78 170 78 L 500 78 C 520 78 536 80 548 84 L 500 82 L 170 82 C 130 82 100 83 70 85 Z" fill="#fff" opacity=".55"/>
<rect x="150" y="82" width="58" height="22" rx="3" fill="none" stroke="#A97A00" stroke-width="1.2" opacity=".7"/>
<path d="M500 83 v18 M488 83 v18 M120 86 v16" stroke="#A97A00" stroke-width="1.1" opacity=".6"/>
<path d="M548 88 L566 92 C 569 93 570 96 567 96 L 546 96 Z" fill="#1E2328"/>
<path d="M552 89 L562 91.5 L551 91.5 Z" fill="#7FA2C2" opacity=".7"/>
<path d="M572 104 C 580 106 584 108 580 112 C 576 115 570 116 562 117 Z" fill="#B98600" opacity=".55"/>
<path d="M30 84 C 30 90 34 95 40 98 L 26 88 Z" fill="#9C9EA0"/>
<path d="M300 112 L 372 112 L 238 170 Q 226 175 214 172 L 208 170 Z" fill="url(#<?= $tcUid ?>-wing)"/>
<path d="M300 112 L 372 112 L 366 117 L 296 117 Z" fill="#8E9194" opacity=".6"/>
<path d="M214 172 L 206 168 L 212 160 Z" fill="#D9DADB"/>
<path d="M300 118 L 326 118 L 318 132 L 296 132 Z" fill="#B7B9BB"/>
<rect x="252" y="128" width="88" height="30" rx="15" fill="url(#<?= $tcUid ?>-eng)"/>
<path d="M253 133 L 238 138 Q 234 143 238 148 L 253 153 Z" fill="#7C7F82"/><path d="M238 138 Q 234 143 238 148 L 232 145 Q 230 143 232 141 Z" fill="#4A4D50"/>
<ellipse cx="338" cy="143" rx="7" ry="15" fill="url(#<?= $tcUid ?>-intake)"/>
<ellipse cx="338" cy="143" rx="7" ry="15" fill="none" stroke="#F4F4F4" stroke-width="2.2"/>
<path d="M262 132 L 330 132" stroke="#fff" stroke-width="2" opacity=".8" stroke-linecap="round"/>
<g fill="#7A5A00" opacity=".55"><circle cx="520" cy="90" r="1.8"/><circle cx="530" cy="90" r="1.8"/></g></symbol><symbol id="<?= $tcUid ?>-ship" viewBox="0 0 360 120"><path d="M8 78h330l-26 30H40z" fill="#26323F"/><path d="M8 78h330l-3 6H11z" fill="#C8372D"/><rect x="250" y="40" width="44" height="38" rx="3" fill="#E9ECEF"/><rect x="258" y="47" width="28" height="6" rx="2" fill="#26323F"/><rect x="270" y="22" width="10" height="18" fill="#26323F"/><g><rect x="40" y="56" width="40" height="22" fill="#FFC72C"/><rect x="82" y="56" width="40" height="22" fill="#3E7CB1"/><rect x="124" y="56" width="40" height="22" fill="#FFC72C"/><rect x="166" y="56" width="40" height="22" fill="#E07A5F"/><rect x="208" y="56" width="38" height="22" fill="#3E7CB1"/><rect x="60" y="34" width="40" height="22" fill="#E07A5F"/><rect x="102" y="34" width="40" height="22" fill="#FFC72C"/><rect x="144" y="34" width="40" height="22" fill="#3E7CB1"/><rect x="186" y="34" width="40" height="22" fill="#FFC72C"/></g><path d="M0 112c40 6 80-6 120 0s80 6 120 0 80-6 120 0" stroke="#5A7FA6" stroke-width="2" fill="none" opacity=".6"/></symbol></svg>
  <aside class="tc-list">
    <div class="tc-lh">
      <h2><?= h($tcTitle) ?></h2>
      <span class="tc-count"><i class="ic ic-sm <?= $tcNoun === 'consolidations' ? 'i-layers' : 'i-box' ?>" aria-hidden="true"></i><?= isset($tc['shown']) && $tc['shown'] < $tc['total'] ? sl_num($tc['shown']) . ' of ' : '' ?><?= sl_num($tc['total']) ?> <?= h($tcNoun) ?></span>
      <span class="grow"></span>
      <?php if ($tcExpand): ?><a class="tc-ib" href="<?= h($tcExpand) ?>" aria-label="Open the full page"><i class="ic ic-sm i-expand" aria-hidden="true"></i></a><?php endif; ?>
    </div>
    <label class="tc-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input type="search" data-tc-q placeholder="<?= $tcFilters ? 'Search' : 'Narrow' ?> <?= $tcNoun === 'consolidations' ? 'container, carrier' : 'tracking, customer, container' ?>…" aria-label="Search the tracking list"></label>
    <?php if ($tcFilters): ?>
    <div class="tc-filters" role="group" aria-label="Filter">
      <button type="button" class="on" data-tc-f="all">All <?= $tcNoun === 'consolidations' ? 'loads' : 'shipments' ?></button>
      <?php if ($tc['counts']['air']): ?><button type="button" data-tc-f="air"><i class="ic ic-sm i-plane" aria-hidden="true"></i>Air</button><?php endif; ?>
      <?php if ($tc['counts']['sea']): ?><button type="button" data-tc-f="sea"><i class="ic ic-sm i-ship" aria-hidden="true"></i>Sea</button><?php endif; ?>
      <?php if (!empty($tc['counts']['con']) && $tcNoun !== 'consolidations'): ?><button type="button" data-tc-f="con"><i class="ic ic-sm i-layers" aria-hidden="true"></i>Consolidations</button><?php endif; ?>
      <?php if ($tc['counts']['late']): ?><button type="button" data-tc-f="late"><i class="ic ic-sm i-alert" aria-hidden="true"></i>Delayed <b><?= (int) $tc['counts']['late'] ?></b></button><?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="tc-cards" role="listbox" aria-label="<?= h($tcTitle) ?>">
      <?php foreach ($items as $i => $it): ?>
      <button type="button" role="option" class="tc-card<?= $i === 0 ? ' on' : '' ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" data-tc-key="<?= h($it['key']) ?>" data-mode="<?= h($it['mode']) ?>" data-kind="<?= h($it['kind'] ?? '') ?>" data-late="<?= $it['late'] ? '1' : '' ?>" data-text="<?= h(strtolower($it['code'] . ' ' . $it['customer'] . ' ' . $it['carrier'] . ' ' . $it['st'] . ' ' . ($it['search'] ?? ''))) ?>">
        <span class="tc-c1"><b class="mono"><?= h($it['code']) ?></b><?php if (!empty($it['kind'])): ?><span class="tc-kind <?= h($it['kind']) ?>" title="<?= h($it['modeL'] . ' ' . strtolower($it['kindL'])) ?>"><?= h($it['kind'] === 'consolidation' ? 'Consol' : $it['kindL']) ?></span><?php endif; ?><span class="tc-st <?= $it['late'] ? 'late' : h($it['cls']) ?>"><?= h($it['late'] ? 'Delayed' : $it['st']) ?></span></span>
        <span class="tc-c2"><span><?= h($it['from']['code']) ?></span><i class="tc-dots"></i><i class="ic ic-sm <?= $it['mode'] === 'sea' ? 'i-ship' : 'i-plane' ?>" aria-hidden="true"></i><i class="tc-line"><em><?= h($it['transit']) ?></em></i><span><?= h($it['to']['code']) ?></span></span>
        <span class="tc-c3"><span><?= h($it['from']['city']) ?>, <?= h($it['from']['country']) ?></span><span><?= h($it['to']['city']) ?>, <?= h($it['to']['country']) ?></span></span>
        <span class="tc-c4"><span><i class="tc-sw <?= h($it['mode']) ?>"></i><?= h($it['carrier']) ?> · <?= h($it['customer']) ?></span><span><?= $it['arrived'] ? 'Arrived' : 'ETA' ?> <b><?= h($it['etaShort']) ?></b></span></span>
      </button>
      <?php endforeach; ?>
      <div class="tc-empty" data-tc-empty<?= $items ? ' hidden' : '' ?>><?= !empty($tc['q']) ? 'Nothing matches “' . h($tc['q']) . '”.' : 'No open ' . h($tcNoun) . ' to track.' ?></div>
    </div>
  </aside>

    <div class="tc-map">
      <img class="tc-world" src="<?= h(cdp_asset('assets/swiftlane/maps/world-dots.svg')) ?>" alt="" aria-hidden="true" loading="lazy">
      <svg class="tc-over" viewBox="0 0 <?= $vw ?> <?= $vh ?>" preserveAspectRatio="xMidYMid slice" role="img" aria-label="Route map of the selected <?= h($tcNounOne) ?>">
        <defs>
          <radialGradient id="<?= $tcUid ?>-glow"><stop offset="0" stop-color="#fff" stop-opacity=".55"/><stop offset=".35" stop-color="#fff" stop-opacity=".12"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></radialGradient>
          <radialGradient id="<?= $tcUid ?>-sun"><stop offset="0" stop-color="#FFD43B" stop-opacity=".55"/><stop offset="1" stop-color="#FFD43B" stop-opacity="0"/></radialGradient>
          <linearGradient id="<?= $tcUid ?>-fade" x1="0" x2="0" y1="0" y2="1"><stop offset=".55" stop-color="#151515" stop-opacity="0"/><stop offset="1" stop-color="#151515" stop-opacity=".9"/></linearGradient>
        </defs>
        <?php foreach ($tc['glows'] as $gp): ?><g class="tc-hub" transform="translate(<?= $gp[0] ?> <?= $gp[1] ?>)"><circle r="16" fill="url(#<?= $tcUid ?>-glow)"/><circle r="4.2" class="dot"/></g><?php endforeach; ?>
        <?php foreach ($items as $i => $it): $g = $it['geo']; $v = $g['veh']; $n = count($g['pts']); ?>
        <g class="tc-geo<?= $i === 0 ? ' on' : '' ?>" data-tc-geo="<?= h($it['key']) ?>">
          <?php foreach ($g['pts'] as $pi => $pt): ?><circle cx="<?= $pt[0] ?>" cy="<?= $pt[1] ?>" r="60" fill="url(#<?= $tcUid ?>-glow)" opacity=".55"/><?php endforeach; ?>
          <path class="tc-route" d="<?= h($g['path']) ?>"/>
          <path class="tc-done" d="<?= h($g['done']) ?>"/>
          <?php foreach ($g['pts'] as $pi => $pt): ?><g class="tc-pin" transform="translate(<?= $pt[0] ?> <?= $pt[1] ?>)"><circle r="13" class="halo"/><circle r="6.5" class="dot"/></g><?php endforeach; ?>
          <?= $chip($g['pts'][0][0], $g['pts'][0][1], 'From', $it['from']['city'] . ', ' . $it['from']['country'], 'below') ?>
          <?php if ($it['via']): ?><?= $chip($g['pts'][1][0], $g['pts'][1][1], 'Transit', $it['via']['city'], 'center') ?><?php endif; ?>
          <?= $chip($g['pts'][$n - 1][0], $g['pts'][$n - 1][1], 'Dest', $it['to']['city'] . ', ' . $it['to']['country'], 'left') ?>
          <g class="tc-veh" transform="translate(<?= $v[0] ?> <?= $v[1] ?>)">
            <circle r="46" fill="url(#<?= $tcUid ?>-sun)"/>
            <g transform="rotate(<?= $v[2] ?>)">
              <?php if ($it['mode'] === 'sea'): ?>
              <path class="tc-craft" d="M-16 -7h22l12 7-12 7h-22l-4-7z"/><rect class="tc-craft-d" x="-11" y="-4" width="5" height="8" rx="1"/><rect class="tc-craft-d" x="-4" y="-4" width="5" height="8" rx="1"/>
              <?php else: ?>
              <path class="tc-craft" d="M20 0c0-1.6-1.4-2.6-3.4-2.6H6.4L-4.8-17h-4.4l5.2 14.4h-9.6l-3.6-5.2h-3.4l2.4 7.8-2.4 7.8h3.4l3.6-5.2h9.6L-9.2 17h4.4L6.4 2.6h10.2C18.6 2.6 20 1.6 20 0z"/>
              <?php endif; ?>
            </g>
          </g>
          <?php if ($it['pct'] > 8 && !$it['arrived']): ?><text class="tc-now" x="<?= $v[0] ?>" y="<?= $v[1] - 30 ?>" text-anchor="middle"><?= h($it['where']) ?></text><?php endif; ?>
        </g>
        <?php endforeach; ?>
        <rect width="<?= $vw ?>" height="<?= $vh ?>" fill="url(#<?= $tcUid ?>-fade)" pointer-events="none"/>
      </svg>
      <div class="tc-legend"><span><i class="l1"></i>Travelled</span><span><i class="l2"></i>Remaining</span><span class="live"><i></i>Live</span></div>
    </div>

    <div class="tc-panel">
      <div class="tc-tabs" role="tablist">
        <?php foreach (['overview' => 'Overview', 'details' => 'Details', 'contents' => 'Contents', 'timeline' => 'Timeline', 'docs' => 'Documents', 'cost' => 'Cost', 'priority' => 'Priority'] as $tk => $tl): ?>
        <button type="button" role="tab" class="<?= $tk === 'overview' ? 'on' : '' ?>" aria-selected="<?= $tk === 'overview' ? 'true' : 'false' ?>" data-tc-tab="<?= $tk ?>"><?= h($tl) ?></button>
        <?php endforeach; ?>
        <span class="grow"></span>
        <?php if ($first): ?><a class="tc-ib" data-tc-open href="<?= h($first['href']) ?>" aria-label="Open the selected record"><i class="ic ic-sm i-expand" aria-hidden="true"></i></a><?php endif; ?>
      </div>
      <?php foreach ($items as $i => $it): ?>
      <div class="tc-body<?= $i === 0 ? ' on' : '' ?>" data-tc-body="<?= h($it['key']) ?>" data-href="<?= h($it['href']) ?>">
        <div class="tc-idrow"><b class="tc-code mono"><?= h($it['code']) ?></b><span class="tc-badge <?= $it['late'] ? 'late' : h($it['cls']) ?>"><?= h(strtoupper($it['st'])) ?></span><?php if ($it['late']): ?><span class="tc-badge late">DELAYED</span><?php endif; ?></div>

        <div class="tc-sec on" data-tc-sec="overview">
          <div class="tc-ov">
            <div class="tc-veh-card">
              <svg class="tc-art" viewBox="<?= $it['mode'] === 'sea' ? '0 0 360 120' : '0 0 600 190' ?>" aria-hidden="true"><use href="#<?= $tcUid ?>-<?= $it['mode'] === 'sea' ? 'ship' : 'plane' ?>"/></svg>
              <b><?= h($it['carrier']) ?> / <?= h($it['vehicle']) ?></b>
              <span><?= $it['flight'] !== '' ? 'Flight ' . h($it['flight']) . ' · ' : '' ?><?= $it['awb'] !== '' ? 'AWB ' . h($it['awb']) : h($it['modeL']) . ' · ' . h($it['weight']) ?><?= $it['customer'] !== $it['weight'] ? ' · ' . h($it['customer']) : '' ?></span>
            </div>
            <div class="tc-route-card">
              <div class="tc-rh"><b>Route</b><span>ON THE WAY: <b><?= h($it['onway']) ?></b></span></div>
              <div class="tc-bar" role="img" aria-label="<?= (int) $it['pct'] ?>% of the route covered">
                <i class="done" style="flex-basis:<?= max(2, min(96, (int) $it['pct'])) ?>%"></i><i class="now"></i>
                <span class="rest"><i></i><i></i><i></i><i class="s"></i><i class="s"></i></span>
              </div>
              <div class="tc-ends">
                <div><span class="tc-code2"><i class="pt"></i><?= h($it['from']['code']) ?></span><span><?= h($it['from']['city']) ?>, <?= h($it['from']['country']) ?></span><span class="mut"><i class="tc-sw <?= h($it['mode']) ?>"></i><?= h($it['carrier']) ?> · <?= h($it['created']) ?></span></div>
                <div class="r"><span class="tc-code2"><?= h($it['to']['code']) ?><i class="pt"></i></span><span><?= h($it['to']['city']) ?>, <?= h($it['to']['country']) ?></span><span><span class="mut">ETA</span> <?= h($it['etaShort']) ?></span></div>
              </div>
            </div>
            <div class="tc-side">
              <div class="tc-k"><div class="tc-kh"><i class="ic i-clock" aria-hidden="true"></i><span class="<?= h($it['estCls']) ?>"><?= h($it['estimate']) ?></span></div><div class="tc-kv"><span>Estimate</span><b><?= h($it['etaShort']) ?></b></div></div>
              <?php if ($it['money']): ?>
              <div class="tc-k"><div class="tc-kh"><i class="ic i-card" aria-hidden="true"></i><span class="<?= h($it['payCls']) ?>"><?= h($it['pay']) ?></span></div><div class="tc-kv"><span>Total cost</span><b><?= h($it['total']) ?></b></div></div>
              <?php else: ?>
              <div class="tc-k"><div class="tc-kh"><i class="ic i-box" aria-hidden="true"></i><span class="mut"><?= h($it['modeL']) ?></span></div><div class="tc-kv"><span>Weight</span><b><?= h($it['weight']) ?></b></div></div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="tc-sec" data-tc-sec="details">
          <?php if (!empty($it['details'])): ?><dl class="tc-dl tc-det"><?php foreach ($it['details'] as [$dk, $dv]): ?><div><dt><?= h($dk) ?></dt><dd><?= h((string) $dv) ?></dd></div><?php endforeach; ?></dl>
          <?php else: ?><p class="tc-muted">No further details recorded.</p><?php endif; ?>
        </div>

        <div class="tc-sec" data-tc-sec="contents">
          <?php if (!empty($it['contents'])): ?>
          <div class="tc-items" style="--c:<?= isset($it['contents'][0]['who']) ? 4 : 3 ?>"><div class="tc-ir h"><span>Parcel</span><?php if (isset($it['contents'][0]['who'])): ?><span>Customer</span><?php endif; ?><span>Weight</span><span>Size</span><span>Status</span></div>
            <?php foreach ($it['contents'] as $c): ?><div class="tc-ir"><b class="mono"><?= h($c['code']) ?></b><?php if (isset($c['who'])): ?><span><?= h($c['who']) ?></span><?php endif; ?><span><?= h($c['w']) ?></span><span><?= h($c['dim']) ?></span><span class="tc-st <?= h($c['cls']) ?>"><?= h($c['st']) ?></span></div><?php endforeach; ?>
          </div>
          <?php if (count($it['contents']) < (int) $it['customer']): ?><p class="tc-muted">Showing the first <?= count($it['contents']) ?> parcels. Open the record for the full manifest.</p><?php endif; ?>
          <?php elseif (!empty($it['con'])): ?><p class="tc-muted">Travels inside consolidation <b class="mono"><?= h($it['con']['code']) ?></b> · <?= h(sl_statusLabel($it['con']['status'])) ?>.</p>
          <?php elseif (($it['kind'] ?? '') === 'consolidation'): ?><p class="tc-muted">No parcels loaded yet.</p>
          <?php else: ?><p class="tc-muted">A single <?= h(strtolower($it['kindL'] ?? 'parcel')) ?>, not part of a consolidation yet.</p><?php endif; ?>
        </div>

        <div class="tc-sec" data-tc-sec="timeline">
          <ol class="tc-tl">
            <?php foreach ($it['hist'] as $hi => $h): ?><li class="<?= $hi === 0 ? 'now' : '' ?>"><b><?= h($h['t']) ?></b><span><?= h(trim($h['c'] . ($h['loc'] !== '' ? ' · ' . $h['loc'] : ''), ' ·')) ?></span><time><?= h($h['at']) ?></time></li><?php endforeach; ?>
            <li class="start"><b>Registered</b><span><?= h($it['from']['city']) ?></span><time><?= h($it['created']) ?></time></li>
          </ol>
        </div>

        <div class="tc-sec" data-tc-sec="docs">
          <div class="tc-docs">
            <?php foreach ($it['docs'] as [$dl, $dh, $di]): ?><a href="<?= h($dh) ?>" target="_blank" rel="noopener"><i class="ic ic-sm <?= h($di) ?>" aria-hidden="true"></i><?= h($dl) ?><i class="ic ic-sm i-right" aria-hidden="true"></i></a><?php endforeach; ?>
            <a href="<?= h($it['href']) ?>"><i class="ic ic-sm i-eye" aria-hidden="true"></i>Open record<i class="ic ic-sm i-right" aria-hidden="true"></i></a>
          </div>
        </div>

        <div class="tc-sec" data-tc-sec="cost">
          <?php if ($it['money']): ?>
          <dl class="tc-dl"><div><dt>Subtotal</dt><dd><?= h($it['sub']) ?></dd></div><div><dt>Taxes &amp; fees</dt><dd><?= h($it['tax']) ?></dd></div><div class="tot"><dt>Total</dt><dd><?= h($it['total']) ?></dd></div><div><dt>Payment</dt><dd><span class="<?= h($it['payCls']) ?>"><?= h($it['pay']) ?></span></dd></div></dl>
          <?php else: ?><p class="tc-muted">Amounts are hidden for your role.</p><?php endif; ?>
        </div>

        <div class="tc-sec" data-tc-sec="priority">
          <dl class="tc-dl"><div><dt>Priority</dt><dd><span class="<?= h($it['prio']['cls']) ?>"><?= h($it['prio']['label']) ?></span></dd></div><div><dt>Open for</dt><dd><?= (int) $it['prio']['days'] ?> day<?= (int) $it['prio']['days'] === 1 ? '' : 's' ?></dd></div><div><dt>ETA</dt><dd><?= $it['prio']['etaDelta'] === null ? 'Not set' : ($it['prio']['etaDelta'] < 0 ? abs($it['prio']['etaDelta']) . ' d overdue' : ($it['prio']['etaDelta'] === 0 ? 'Today' : 'In ' . $it['prio']['etaDelta'] . ' d')) ?></dd></div><div><dt>Route covered</dt><dd><?= (int) $it['pct'] ?>%</dd></div><div><dt>Now</dt><dd><?= h($it['where']) ?></dd></div></dl>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (!$items): ?><div class="tc-body on"><p class="tc-muted">Nothing is moving right now. New <?= h($tcNoun) ?> appear here as soon as they are registered.</p></div><?php endif; ?>
    </div>
</section></div>
