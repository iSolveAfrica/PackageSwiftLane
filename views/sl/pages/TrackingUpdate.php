<?php
/**
 * TrackingUpdate - add a tracking event to a package or consolidation
 * (look of docs/handover/artifact-source/ShipmentTracking.dc.html: milestones + event form).
 * Used by customer_package_tracking.php, consolidate_shipment_tracking.php and
 * consolidate_package_shipment_tracking.php. Data: views/sl/data/ops_forms.php (sl_trackUpdateData).
 * Posts the legacy fields to $d['action'] (legacy endpoint, or the page itself for
 * consolidations: handler moved verbatim to views/sl/handlers/). Client: dataJs/sl/ops_forms.js.
 */
if (!isset($d)) { $d = []; }
$s = $d['sum'];
?>
<div class="sl-head"><div><a href="<?= h($d['back']) ?>" class="row g6 mut sm"><i class="ic ic-sm i-left" aria-hidden="true"></i><?= h($s['label']) ?> <?= h($s['track']) ?></a><h1 class="sl-title row g8" style="margin-top:4px"><i class="ic i-route" aria-hidden="true"></i>Update tracking</h1><p class="sl-lede">Add an event: the status changes and the customer sees it on their tracking page.</p></div></div>
<div class="split">
  <div class="a col g16">
    <form class="sl-card col g16" data-ops-form action="<?= h($d['action']) ?>" method="post" data-ok="<?= h($d['back']) ?>" data-pickup-module="<?= h($d['module']) ?>" data-pickup-ids="<?= (int) $d['id'] ?>" data-pickup-owner="<?= h($d['owner']) ?>">
      <?php if ($d['idField']): ?><input type="hidden" name="<?= h($d['idField']) ?>" value="<?= (int) $d['id'] ?>"><?php endif; ?>
      <h2 class="sl-ct">New event for <?= h($s['track']) ?></h2>
      <div class="grid cols2">
        <label class="sl-field"><span class="sl-lab">Status</span><span class="sl-sel native"><select name="status_courier" required><?php foreach ($d['statuses'] as $st): ?><option value="<?= (int) $st->id ?>" <?= (int) $st->id === $d['statusId'] ? 'selected' : '' ?>><?= h(str_replace('_', ' ', $st->mod_style)) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
        <label class="sl-field"><span class="sl-lab">Date</span><input class="sl-in" type="date" name="t_date" value="<?= h($d['today']) ?>" required></label>
        <label class="sl-field"><span class="sl-lab">Office</span><span class="sl-sel native"><select name="office" required><?php foreach ($d['offices'] as $o): ?><option value="<?= (int) $o->id ?>" <?= (int) $o->id === $d['office'] ? 'selected' : '' ?>><?= h($o->name_off) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
        <label class="sl-field"><span class="sl-lab">Country</span><span class="sl-sel native"><select name="country" required><?php foreach ($d['countries'] as $c): ?><option value="<?= h($c->name) ?>"><?= h($c->name) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
        <label class="sl-field" style="grid-column:1/-1"><span class="sl-lab">Location / address</span><input class="sl-in" name="address" placeholder="Where the parcel is now" required></label>
        <?php if ($d['flight']): ?><label class="sl-field"><span class="sl-lab">Flight number</span><input class="sl-in mono" name="flight_no" placeholder="KQ 101"></label><label class="sl-field"><span class="sl-lab">AWB number</span><input class="sl-in mono" name="awb_no"></label><?php endif; ?>
        <label class="sl-field" style="grid-column:1/-1"><span class="sl-lab">Comment</span><textarea class="sl-in" name="comments" maxlength="250" style="height:72px;padding:10px 12px"></textarea></label>
      </div>
      <?php if ($d['notify']): ?><div class="row g12 wrap sm"><?php foreach ($d['notify'] as $n): ?><label class="row g6"><input type="checkbox" name="<?= h($n[0]) ?>" value="1" <?= $n[2] ? 'checked' : '' ?>><?= h($n[1]) ?></label><?php endforeach; ?></div><?php endif; ?>
      <div class="row g8"><button type="submit" class="sl-btn">Save event</button><a class="sl-btn ghost" href="<?= h($d['back']) ?>">Cancel</a></div>
    </form>
    <section class="sl-card col g12"><div class="sl-ch" style="margin:0"><h2 class="sl-ct">History</h2><span class="sl-badge"><?= count($d['events']) ?> events</span></div>
      <?php foreach (array_reverse($d['events']) as $e): ?><div class="row g10 sm" style="padding:8px 0;border-top:1px solid var(--line)"><span class="sl-pill <?= h($e['pc']) ?>"><?= h($e['t']) ?></span><span class="col grow"><span><?= h($e['where'] ?: '—') ?></span><?php if ($e['note']): ?><span class="mut" style="font-size:12px"><?= h($e['note']) ?></span><?php endif; ?></span><span class="mono mut" style="font-size:12px"><?= h($e['at']) ?></span></div><?php endforeach; ?>
      <?php if (!$d['events']): ?><div class="sl-empty">No events recorded yet.</div><?php endif; ?>
    </section>
  </div>
  <aside class="b col g16"><section class="sl-card col g10"><div class="row between"><b class="mono fw5"><?= h($s['track']) ?></b><span class="sl-pill <?= h($s['pc']) ?>"><?= h($s['status']) ?></span></div><div class="sl-kv"><?php foreach ($s['kv'] as $kv): ?><div><span><?= h($kv[0]) ?></span><span><?= h($kv[1]) ?></span></div><?php endforeach; ?></div></section></aside>
</div>
