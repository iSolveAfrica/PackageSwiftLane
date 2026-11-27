<?php
/**
 * Rider app · Shifts (design: M-RShifts). Data: sl_riderShiftsData() in views/sl/data/rider_money.php.
 * A rider books and cancels their own availability blocks for the next 14 days (cdb_rider_shifts).
 * Guaranteed pay, released blocks with spots and the swap board have no backend and are left out.
 */
if (!isset($d)) { $d = []; }
$ajax = 'ajax/sl/rider_app_ajax.php';
echo sl_rmOpen('Shifts', 'Book the hours you plan to ride', ['back' => sl_route('RiderAccount')], '', true);
if (!$d['isRider']) { echo sl_rmNotRider(), sl_rmClose(); return; }
?>
<?php if (!$d['ready']): ?>
<div class="mx-ban warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>Shifts are not set up on this system yet. Ask the office to run the database upgrade.</span></div>
<?php echo sl_rmClose(); return; endif; ?>
<div class="mxp-grid">
<div class="mxp-col">
<section class="mx-k c2">
  <?= sl_mxKpi('calendar', 'Booked this week', $d['weekHours'], $d['weekN'] . ($d['weekN'] === 1 ? ' block' : ' blocks')) ?>
  <?= sl_mxKpi('clock', 'Next 14 days', $d['allHours'], $d['allN'] . ($d['allN'] === 1 ? ' block' : ' blocks')) ?>
</section>
<nav class="rc-days rm-days" aria-label="Pick a day"><?php foreach ($d['days'] as $x): ?><a class="rc-day <?= h($x['cls']) ?>" href="<?= h($x['href']) ?>" aria-label="<?= h($x['aria']) ?>"<?= $x['k'] === $d['sel'] ? ' aria-current="date"' : '' ?>><small><?= h($x['l']) ?></small><b><?= h($x['n']) ?></b><i class="<?= $x['bk'] ? 'bk' : '' ?>"></i></a><?php endforeach; ?></nav>
<?= sl_mxSec('Book a block · ' . $d['selLabel']) ?>
<section class="mx-card mx-form"><form data-sl-form data-sl-reload action="<?= h($ajax) ?>" method="post">
  <input type="hidden" name="op" value="shift_book"><input type="hidden" name="day" value="<?= h($d['sel']) ?>">
  <div class="mx-two">
    <label class="mx-f"><small>Start</small><input class="mx-in" type="time" name="start_time" required step="900"<?= $d['selIsToday'] ? ' min="' . h($d['minStart']) . '"' : '' ?>></label>
    <label class="mx-f"><small>End</small><input class="mx-in" type="time" name="end_time" required step="900"></label>
  </div>
  <label class="mx-f"><small>Area</small><select class="mx-in" name="hub"><option value="">Any area</option><?php foreach ($d['hubs'] as $hub): ?><option value="<?= h($hub) ?>"><?= h($hub) ?></option><?php endforeach; ?></select></label>
  <button type="submit" class="mx-btn rs-amb"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Book <?= h(sl_date($d['sel'], 'D j M')) ?></button>
  <p class="mx-note">Between 30 minutes and 12 hours, not overlapping another block. The office sees your booked blocks when it plans the day.</p>
</form></section>
</div>
<div class="mxp-col">
<?= sl_mxSec('Your booked blocks') ?>
<?php if (!$d['rows']): ?>
<?= sl_mxEmpty('calendar', 'No blocks booked', 'Pick a day and a time to tell the office when you plan to ride.') ?>
<?php endif; ?>
<?php foreach ($d['rows'] as $r): ?>
<section class="mx-card rc-bk">
  <div class="rc-bkh"><span class="rc-bkd"><small><?= h($r['dow']) ?></small><b><?= h($r['dom']) ?></b></span><span class="mx-rm"><b><?= h($r['hub']) ?></b><small><?= h($r['time']) ?></small></span><span class="sl-pill <?= $r['running'] ? 'onway' : 'paid' ?>"><?= $r['running'] ? 'On now' : 'Booked' ?></span></div>
  <div class="rc-bkm"><div><small>Length</small><b><?= h($r['len']) ?></b></div><div><small>Starts</small><b><?= h(substr($r['time'], 0, 5)) ?></b></div><div><small>Ends</small><b><?= h(substr($r['time'], -5)) ?></b></div></div>
  <span class="rc-starts"><i class="ic ic-sm i-<?= $r['soon'] ? 'clock' : 'calendar' ?>" aria-hidden="true"></i> <?php if ($r['soon']): ?>Starts in <b data-mx-countdown="<?= h($r['startIso']) ?>" data-done="a moment"></b><?php else: ?><?= h($r['when']) ?><?php endif; ?></span>
  <div class="rc-bka rm-bka"><?php if ($r['nav'] !== ''): ?><a href="<?= h($r['nav']) ?>" target="_blank" rel="noopener"><i class="ic ic-sm i-route" aria-hidden="true"></i>Directions</a><?php endif; ?><button type="button" class="bad" data-sl-action="<?= h($ajax) ?>" data-p-op="shift_cancel" data-p-id="<?= (int) $r['id'] ?>" data-sl-confirm="Cancel the block <?= h($r['dow'] . ' ' . $r['dom'] . ', ' . $r['time']) ?>?" data-sl-reload><i class="ic ic-sm i-x" aria-hidden="true"></i>Cancel</button></div>
</section>
<?php endforeach; ?>
<?= sl_mxSec('How blocks work') ?>
<section class="mx-card mx-kv">
  <div><span>What a block is</span><b>Hours you plan to be riding</b></div>
  <div><span>Who sees it</span><b>You and the office</b></div>
  <div><span>Cancel</span><b>Any time before it ends</b></div>
  <div><span>Pay</span><b>Per finished trip, as always</b></div>
</section>
<p class="mx-note">Booking a block does not guarantee jobs or a minimum pay. Guaranteed pay, released blocks and swaps are not available yet.</p>
<div class="mx-btns"><a class="mx-btn sec" href="<?= h(sl_route('RiderHome')) ?>">Back to today’s jobs</a></div>
</div>
</div>
<?= sl_rmClose() ?>
