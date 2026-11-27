<?php
/**
 * Riders grouped by status (ON THE WAY / LOADING / WAITING / AVAILABLE / OFF) with load bars for kg / L / parcels.
 * Plan tab: each rider is a drop target for stops, has a lock toggle and lists its proposed stops (draggable).
 * Input: $d['riders'], $d['groups'], $d['plan'], $rpTab; $rpNoDrag = true for the read-only fleet list.
 */
$rpDrag = empty($rpNoDrag) && ($rpTab ?? 'plan') === 'plan';
$rpPlanId = (int) (($d['plan']['id'] ?? 0));
?>
<aside class="rp-riders col g10" aria-label="Riders" data-rp-riders>
  <div class="row between g8"><h2 class="sl-ct">Riders · <?= count((array) ($d['riders'] ?? [])) ?></h2><?php if ($rpDrag): ?><span class="mut sm">Drop stops on a rider</span><?php endif; ?></div>
  <div class="rp-chips" role="group" aria-label="Filter riders by status">
    <button type="button" class="rp-chip on" data-rp-group="all" aria-pressed="true">All <b><?= count((array) ($d['riders'] ?? [])) ?></b></button>
    <?php foreach ((array) ($d['groups'] ?? []) as $g): ?><button type="button" class="rp-chip st-<?= h($g['k']) ?>" data-rp-group="<?= h($g['k']) ?>" aria-pressed="false"<?= $g['n'] ? '' : ' data-empty' ?>><?= h(mb_strtoupper($g['label'])) ?> <b><?= (int) $g['n'] ?></b></button><?php endforeach; ?>
  </div>
  <?php foreach ((array) ($d['groups'] ?? []) as $g): if (!$g['ids']) continue; ?>
  <div class="rp-grp col g8" data-rp-grp="<?= h($g['k']) ?>">
    <span class="up rp-grp-h"><?= h($g['label']) ?> · <?= (int) $g['n'] ?></span>
    <?php foreach ($g['ids'] as $rid): $r = $d['riders'][$rid] ?? null; if (!$r) continue; $v = $r['vehicle']; ?>
    <article class="rp-r<?= $r['sel'] ? ' sel' : '' ?><?= $r['locked'] ? ' locked' : '' ?><?= !empty($r['over']) ? ' over' : '' ?>" data-rp-rider="<?= (int) $r['id'] ?>" data-group="<?= h($g['k']) ?>"<?= $rpDrag ? ' data-rp-drop="' . (int) $r['id'] . '"' : '' ?> style="--rc:<?= h($r['color']) ?>">
      <div class="row g10 rp-r-h">
        <span class="rp-av" aria-hidden="true"><?= h($r['initials']) ?></span>
        <a class="col grow rp-r-name" href="<?= h($r['href']) ?>"<?= $r['sel'] ? ' aria-current="true"' : '' ?>><b class="fw5"><?= h($r['name']) ?></b><span class="mut sm rp-r-sub"><?= h($v['typeLabel']) ?><?php if ($v['plate']): ?> · <span class="mono rp-plate"><?= h($v['plate']) ?></span><?php endif; ?></span><?php if ($r['zone'] && empty($rpDrag)): ?><span class="mut sm"><?= h($r['zone']) ?></span><?php endif; ?></a>
        <span class="sl-pill <?= h($r['stCls']) ?>"><?= h($r['stLabel']) ?></span>
        <?php if ($rpDrag): ?><button type="button" class="rp-lock<?= $r['locked'] ? ' on' : '' ?>" data-rp-lock="<?= (int) $r['id'] ?>" aria-pressed="<?= $r['locked'] ? 'true' : 'false' ?>" aria-label="<?= $r['locked'] ? 'Unlock' : 'Lock' ?> <?= h($r['name']) ?>" title="<?= $r['locked'] ? 'Locked: auto-allocate keeps this set' : 'Lock this rider\'s set' ?>"><i class="ic ic-sm i-lock" aria-hidden="true"></i></button><?php endif; ?>
      </div>
      <div class="rp-bars" aria-label="Load"><?php foreach ($r['bars'] as $k => $b): ?><div class="rp-bar-row" title="<?= h($b['label'] . ': ' . $b['text'] . ' (' . $b['pct'] . '%)') ?>"><span class="rp-bl"><?= h($b['label']) ?></span><span class="rp-lb <?= h($b['cls']) ?>" role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) min(100, $b['pct']) ?>" aria-label="<?= h($b['label']) ?>"><i style="width:<?= (int) $b['w'] ?>%"></i></span><span class="rp-bv mono"><?= h($b['text']) ?></span></div><?php endforeach; ?></div>
      <div class="row g8 wrap mut sm rp-r-meta">
        <?php if ($r['planUnits']): ?><span><b class="mono"><?= count($r['planUnits']) ?></b> proposed stops<?= $r['route'] ? ' · ' . h($r['route']['km']) . ' · ' . h($r['route']['time']) : '' ?></span><?php endif; ?>
        <?php if ($r['progress']['total']): ?><span><b class="mono"><?= (int) $r['progress']['done'] ?>/<?= (int) $r['progress']['total'] ?></b> stops done</span><?php endif; ?>
        <?php if ($r['nextEta']): ?><span>next ETA <b class="mono"><?= h($r['nextEta']) ?></b></span><?php endif; ?>
        <?php if ($r['lastPing']): ?><span>seen <?= h($r['lastPing']) ?></span><?php endif; ?>
        <?php if ($r['eligible']): ?><span class="rp-elig"><?php foreach ($r['eligible'] as $e): ?><i class="rp-hb h-<?= h($e) ?>" title="Takes <?= h(RPV_HANDLING[$e] ?? $e) ?>"><?= h(RPV_HANDLING[$e] ?? $e) ?></i><?php endforeach; ?></span><?php endif; ?>
      </div>
      <?php if ($rpDrag && $r['planUnits']): ?><div class="rp-r-units" aria-label="Proposed stops for <?= h($r['name']) ?>"><?php foreach ($r['planUnits'] as $u): ?><span role="button" tabindex="0" class="rp-uchip<?= $u['late'] ? ' late' : '' ?>" draggable="true" data-rp-move="<?= (int) $u['id'] ?>" aria-label="<?= h('Stop ' . $u['seq'] . ': ' . ($u['contact'] ?: $u['address']) . '. Move to another rider') ?>" data-rp-unit="<?= (int) $u['id'] ?>" data-rp-from="<?= (int) $r['id'] ?>" data-rp-label="<?= h('#' . $u['id'] . ' ' . ($u['contact'] ?: $u['address'])) ?>" title="<?= h(($u['contact'] ?: '') . ' · ' . $u['area'] . ' · ' . $u['kg'] . ' kg · ' . $u['n'] . ' parcels' . ($u['eta'] ? ' · ETA ' . $u['eta'] : '')) ?>"><span class="mono"><?= (int) $u['seq'] ?></span><?= h(mb_strimwidth($u['contact'] ?: $u['area'] ?: ('#' . $u['id']), 0, 16, '…')) ?></span><?php endforeach; ?></div><?php elseif ($rpDrag && $rpPlanId && !$r['locked'] && in_array($r['status'], ['available', 'loading', 'waiting', 'onway'], true)): ?><div class="rp-r-drop mut sm">No stops proposed · drop one here</div><?php endif; ?>
    </article>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>
  <?php if (empty($d['riders'])): ?><div class="sl-card mut sm">No active riders. Riders are staff with the rider role (userlevel 3); add one from Staff &amp; Roles.</div><?php endif; ?>
</aside>
