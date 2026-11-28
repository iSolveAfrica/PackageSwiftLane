<?php
/**
 * Smart Routing: one rider's route (timeline). Input: $srDet (srv_detail), $srDay, $srAv, $d.
 * Completed stops get a green tick and the line down to them is filled; late stops are flagged.
 */
$t = $srDet; $r = $t['rider']; $sm = $t['summary'];
$srIcons = ['delivered' => ['i-check', 'Delivered'], 'failed' => ['i-x', 'Not delivered'], 'returned' => ['i-reply', 'Returned'], 'arrived' => ['i-pin', 'Rider is here']];
?>
<section class="sl-card sr-panel sr-detail" aria-label="<?= h($srDay) ?> route details" data-sr-route="<?= (int) $t['id'] ?>" data-sr-status="<?= h($t['status']) ?>">
  <div class="sr-panel-top">
    <div class="row g10">
      <a class="sr-round" href="<?= h($t['backUrl']) ?>" data-sr-back aria-label="Back to the riders"><i class="ic ic-sm i-left" aria-hidden="true"></i></a>
      <h2 class="sr-h2 grow"><?= h($srDay) ?> route details</h2>
      <?php if ($t['editable']): ?><button type="button" class="sl-btn ghost sr-pillbtn sm" data-sl-open="sr-add"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add</button><?php endif; ?>
    </div>
    <div class="row g8">
      <label class="sl-search sr-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input type="search" data-sr-stop-search placeholder="Search stops" aria-label="Search stops" autocomplete="off"></label>
      <label class="sr-round sr-selwrap" title="Show"><i class="ic ic-sm i-down" aria-hidden="true"></i><select data-sr-stop-filter aria-label="Show which stops"><option value="all">All stops</option><option value="open">Still to do</option><option value="done">Finished</option><option value="late">Running late</option></select></label>
    </div>
  </div>

  <div class="sr-rhead">
    <?= $srAv($r, 'lg') ?>
    <span class="sr-rider-t"><b><?= h($r['name']) ?></b><span class="mut"><?= h($r['sub']) ?></span></span>
    <span class="sl-pill <?= h($t['stCls']) ?>" data-sr-route-status><?= h($t['stLabel']) ?></span>
    <?php if ($t['editable']): ?><button type="button" class="sl-btn ghost sr-pillbtn sm" data-sr-edit aria-pressed="false" aria-controls="sr-editbar"><i class="ic ic-sm i-edit" aria-hidden="true"></i><span>Edit</span></button><?php endif; ?>
  </div>

  <?php if ($t['editable']): ?>
  <div class="sr-editbar" id="sr-editbar" data-sr-editbar hidden>
    <span class="mut sm">Drag a stop by its handle or use the arrows to change the order; arrival times update straight away.</span>
    <div class="row g8 wrap">
      <button type="button" class="sl-btn ghost sr-pillbtn sm" data-sr-reopt><i class="ic ic-sm i-wand" aria-hidden="true"></i>Optimise order</button>
      <a class="sl-btn ghost sr-pillbtn sm" href="<?= h($r['viewUrl']) ?>"><i class="ic ic-sm i-user" aria-hidden="true"></i>View rider</a>
    </div>
    <?php if ($t['canChangeRider'] && $t['others']): ?>
    <form class="row g8 wrap" data-sr-change-rider>
      <label class="grow"><span class="sl-chart-sr">Give this route to another rider</span><select class="sl-in sr-in" name="rider_id" required><option value="">Give this route to…</option><?php foreach ($t['others'] as $o): ?><option value="<?= (int) $o['id'] ?>"><?= h($o['name'] . ' · ' . $o['vehicle']['typeLabel'] . ' · ' . $o['stLabel']) ?></option><?php endforeach; ?></select></label>
      <button type="submit" class="sl-btn ghost sr-pillbtn sm">Hand over</button>
    </form>
    <?php elseif (!$t['canChangeRider']): ?><span class="mut sm">The rider has started, so the route cannot be handed to someone else. Stops not yet picked up can still be removed.</span><?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="sr-sum" aria-label="Route summary">
    <span><small>Distance</small><b><?= h($sm['distance']) ?></b></span>
    <span><small>Drive time</small><b><?= h($sm['drive']) ?></b></span>
    <?php if ($sm['finish']): ?><span><small>Last stop</small><b><?= h($sm['finish']) ?></b></span><?php endif; ?>
    <?php if ($sm['cash']): ?><span><small>To collect</small><b><?= h($sm['cash']) ?></b></span><?php endif; ?>
    <span class="sr-sum-load"><small><?= h($sm['load']['label']) ?> <?= h($sm['load']['text']) ?></small><span class="sr-lb <?= h($sm['load']['cls']) ?>" role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) min(100, $sm['load']['pct']) ?>" aria-label="Vehicle load"><i style="width:<?= (int) $sm['load']['w'] ?>%"></i></span></span>
    <span class="sr-sum-prog"><small><span data-sr-done><?= (int) $t['doneN'] ?></span> of <?= (int) $t['total'] ?> stops done<?= $t['lastPing'] && $t['pos'] ? ' · rider seen ' . h($t['lastPing']) : '' ?></small><span class="sr-lb ok" role="progressbar" aria-valuemin="0" aria-valuemax="<?= (int) $t['total'] ?>" aria-valuenow="<?= (int) $t['doneN'] ?>" aria-label="Stops done"><i style="width:<?= $t['total'] ? (int) round($t['doneN'] * 100 / $t['total']) : 0 ?>%"></i></span></span>
  </div>
  <?php if ($t['lateN']): ?><div class="sl-banner sr-note" role="status"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span><?= (int) $t['lateN'] ?> stop<?= $t['lateN'] === 1 ? ' is' : 's are' ?> planned after the time the customer asked for. Move <?= $t['lateN'] === 1 ? 'it' : 'them' ?> earlier or tell the customer.</span></div><?php endif; ?>
  <?php foreach ($t['alerts'] as $a): if ($a === '') continue; ?><div class="sl-banner sr-note" role="status"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span><?= h($a) ?></span></div><?php endforeach; ?>
  <?php if ($sm['estimated']): ?><p class="mut sr-fine">Distances and times are estimates (straight line with a road factor) until a map provider is connected.</p><?php endif; ?>

  <ol class="sr-tl" data-sr-timeline>
    <li class="sr-stop is-start<?= $t['start']['left'] ? ' is-done' : '' ?>">
      <span class="sr-node"><i class="ic ic-sm i-warehouse" aria-hidden="true"></i></span>
      <span class="sr-stop-main"><b><?= h($t['start']['label']) ?></b><span class="mut">Start place</span></span>
      <span class="sr-time"><i class="ic ic-sm i-clock" aria-hidden="true"></i><?= h($t['start']['time']) ?></span>
    </li>
    <?php foreach ($t['stops'] as $s): $ico = $srIcons[$s['status']] ?? null; ?>
    <li class="sr-stop is-<?= h($s['status']) ?><?= $s['done'] ? ' is-done' : '' ?><?= $s['late'] ? ' is-late' : '' ?>" data-sr-stop="<?= (int) $s['id'] ?>" data-status="<?= h($s['status']) ?>" data-late="<?= $s['late'] ? 1 : 0 ?>" data-search="<?= h($s['search']) ?>"<?= $s['movable'] ? ' data-movable' : '' ?>>
      <span class="sr-node"><?= h($s['num']) ?></span>
      <button type="button" class="sr-stop-main" data-sr-stop-open="<?= (int) $s['id'] ?>" aria-label="Stop <?= (int) $s['seq'] ?>, <?= h($s['name']) ?>: open details">
        <b><?= h($s['name']) ?><?php if ($s['hasNote']): ?><i class="ic ic-sm i-note sr-hasnote" title="Has a staff note" aria-label="Has a staff note"></i><?php endif; ?></b>
        <span class="mut"><?= h($s['address']) ?></span>
        <?php if ($s['late']): ?><span class="sr-flag">Late<?= $s['window'] ? ': customer asked ' . h($s['window']) : '' ?></span><?php elseif ($s['status'] === 'failed' || $s['status'] === 'returned'): ?><span class="sr-flag"><?= h($s['fail'] ?: 'Not delivered') ?></span><?php elseif ($s['status'] === 'arrived'): ?><span class="sr-flag ok">Rider is at this stop</span><?php endif; ?>
      </button>
      <span class="sr-time<?= $ico ? ' has-ico' : '' ?>" title="<?= $s['done'] ? 'Finished at' : 'Expected at' ?> <?= h($s['time']) ?>"><i class="ic ic-sm i-clock" aria-hidden="true"></i><?= h($s['time'] ?: '--:--') ?><?php if ($ico): ?><em class="sr-tick st-<?= h($s['status']) ?>" title="<?= h($ico[1]) ?>"><i class="ic ic-sm <?= $ico[0] ?>" aria-hidden="true"></i><span class="sl-chart-sr"><?= h($ico[1]) ?></span></em><?php endif; ?></span>
      <?php if ($t['editable'] && $s['movable']): ?>
      <span class="sr-tools" data-sr-tools hidden>
        <span class="sr-grip" data-sr-grip title="Drag to move" aria-hidden="true"><i class="ic ic-sm i-more"></i></span>
        <button type="button" class="sr-tool" data-sr-move="up" aria-label="Move stop <?= (int) $s['seq'] ?> earlier"><i class="ic ic-sm i-up" aria-hidden="true"></i></button>
        <button type="button" class="sr-tool" data-sr-move="down" aria-label="Move stop <?= (int) $s['seq'] ?> later"><i class="ic ic-sm i-down" aria-hidden="true"></i></button>
        <?php if ($s['removable']): ?><button type="button" class="sr-tool danger" data-sr-remove="<?= (int) $s['id'] ?>" data-name="<?= h($s['name']) ?>" aria-label="Remove stop <?= (int) $s['seq'] ?>, <?= h($s['name']) ?>"><i class="ic ic-sm i-trash" aria-hidden="true"></i></button><?php endif; ?>
      </span>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
    <li class="sr-none" data-sr-stops-none hidden>No stop matches.</li>
    <?php if (!$t['stops']): ?><li class="sr-none">This route has no stops left. Use Add to give the rider a delivery.</li><?php endif; ?>
  </ol>

  <div class="sr-panel-foot">
    <span class="sr-sent mut sm" data-sr-sent>
      <?php if ($t['sent']): ?>
        <span>Sent <?= h($t['sent']['at']) ?> by <?= h($t['sent']['by']) ?><?= $t['sent']['changed'] ? ' · changed since, send again' : '' ?></span>
        <span class="sr-chan"><?php foreach ($t['sent']['channels'] as $c): ?><span class="sr-ch st-<?= h($c['status'] ?? '') ?>" title="<?= h($c['note'] ?? '') ?>"><?= h($c['label'] ?? '') ?>: <?= h(['queued' => 'queued', 'sent' => 'sent', 'failed' => 'failed', 'skipped' => 'not sent', 'cancelled' => 'cancelled', 'sending' => 'sending'][$c['status'] ?? ''] ?? ($c['status'] ?? '')) ?></span><?php endforeach; ?></span>
      <?php elseif ($t['saved']): ?><span>Draft saved <?= h($t['saved']['at']) ?> by <?= h($t['saved']['by']) ?>. Not sent to the rider yet.</span>
      <?php else: ?><span>Not sent to the rider yet.</span><?php endif; ?>
    </span>
    <?php if ($t['editable']): ?><button type="button" class="sl-btn sr-pillbtn sr-primary" data-sr-send><?= $t['sent'] ? 'Send again' : 'Send rider route' ?><i class="ic ic-sm i-arrow" aria-hidden="true"></i></button><?php endif; ?>
  </div>
</section>
