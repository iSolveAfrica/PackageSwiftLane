<?php
/**
 * Rider app · Safety (design: M-RSafety). Data: sl_riderSafetyData() in views/sl/data/rider_money.php.
 * Real actions only: call the emergency number the office configured (none is assumed for a country), call
 * dispatch, call or text the rider's own emergency contact, send the phone's current position through the
 * share sheet (dataJs/sl/rider_app.js), and today's checklist. Live trip sharing, incident reports and
 * insurance cover have no backend and are left out.
 */
if (!isset($d)) { $d = []; }
$ajax = 'ajax/sl/rider_app_ajax.php';
echo sl_rmOpen('Safety', 'Help is one tap away', ['back' => sl_route('RiderAccount')], $d['isRider'] ? sl_mxIcon('message', 'Help and contact', sl_route('RiderChat')) : '', true);
if (!$d['isRider']) { echo sl_rmNotRider(), sl_rmClose(); return; }
$c = $d['contact'];
$first = $d['dispatch'][0] ?? null;
$sosHref = $d['emergency'] !== '' ? 'tel:' . $d['emergency'] : ($first['tel'] ?? ($c['set'] ? $c['tel'] : ''));
$sosWho = $d['emergency'] !== '' ? 'the emergency number ' . $d['emergency'] : ($first ? 'dispatch on ' . $first['phone'] : ($c['set'] ? $c['name'] : ''));
$done = count($d['checked']);
$total = count($d['checklist']);
?>
<div class="mxp-grid">
<div class="mxp-col">
<section class="rd-sos" aria-label="Emergency">
  <?php if ($sosHref !== ''): ?><a class="rd-sosb" href="<?= h($sosHref) ?>" aria-label="Emergency call to <?= h($sosWho) ?>"><span>SOS</span><small>Tap to call</small></a><?php else: ?><span class="rd-sosb rm-sos-off" aria-hidden="true"><span>SOS</span><small>No number</small></span><?php endif; ?>
  <div class="rd-sosx"><b>Emergency call</b>
  <?php if ($sosHref !== ''): ?><small>Calls <?= h($sosWho) ?> from your phone. It does not alert the office by itself, so call dispatch as well when you are safe.</small>
  <?php else: ?><small>No emergency or office number is set up. Use your phone’s dialler to call the emergency services, and add an emergency contact below.</small><?php endif; ?>
  <?php if ($d['plate'] !== ''): ?><span class="rd-gps rm-plate"><i class="ic ic-sm i-scooter" aria-hidden="true"></i> Your plate: <?= h($d['plate']) ?></span><?php endif; ?></div>
</section>
<section class="mx-act c3 rm-act">
  <?php if ($d['emergency'] !== ''): ?><a href="tel:<?= h($d['emergency']) ?>"><span><i class="ic i-alert" aria-hidden="true"></i></span><small>Emergency <?= h($d['emergency']) ?></small></a><?php endif; ?>
  <?php foreach (array_slice($d['dispatch'], 0, 2) as $x): ?><a href="<?= h($x['tel']) ?>"><span><i class="ic i-phone" aria-hidden="true"></i></span><small><?= h($x['label']) ?></small></a><?php endforeach; ?>
  <?php if ($c['set']): ?><a href="<?= h($c['tel']) ?>"><span><i class="ic i-user" aria-hidden="true"></i></span><small>Call <?= h(explode(' ', $c['name'])[0]) ?></small></a><?php endif; ?>
  <a href="<?= h(sl_route('RiderChat')) ?>"><span><i class="ic i-message" aria-hidden="true"></i></span><small>All contacts</small></a>
</section>
<?php if ($d['emergency'] === ''): ?><p class="mx-note">The office has not set a national emergency number for this app, so none is shown.</p><?php endif; ?>
<?= sl_mxSec('Share where you are') ?>
<section class="mx-card rd-share">
  <div class="rd-shh"><span class="mx-li"><i class="ic i-pin" aria-hidden="true"></i></span><span class="mx-rm"><b>Send my location now</b><small><?= $d['current'] ? 'You are on the way to ' . h($d['current']['name']) . ' · ' . h($d['current']['ref']) : ($d['openStops'] ? (int) $d['openStops'] . ' open ' . ($d['openStops'] === 1 ? 'job' : 'jobs') . ' today' : 'No open jobs right now') ?></small></span></div>
  <div class="mx-two">
    <button type="button" class="mx-btn sec" data-rm-locate data-rm-text="<?= h($d['name'] . ' is here' . ($d['plate'] !== '' ? ' (plate ' . $d['plate'] . ')' : '') . ':') ?>"><i class="ic ic-sm i-send" aria-hidden="true"></i> Share location</button>
    <?php if ($c['set']): ?><button type="button" class="mx-btn sec" data-rm-locate data-rm-sms="<?= h($c['sms']) ?>" data-rm-text="<?= h($d['name'] . ' is here' . ($d['plate'] !== '' ? ' (plate ' . $d['plate'] . ')' : '') . ':') ?>"><i class="ic ic-sm i-message" aria-hidden="true"></i> Text <?= h(explode(' ', $c['name'])[0]) ?></button><?php else: ?><a class="mx-btn sec" href="#contact">Add a contact</a><?php endif; ?>
  </div>
  <small class="mx-note" data-rm-locate-status role="status">Sends a map link of where your phone is at this moment. It is a single position, not live tracking, and nothing is stored.</small>
</section>
<section class="mx-card rd-chk" aria-label="Before you ride">
  <div class="rd-chkh"><span class="mx-li"><i class="ic i-scooter" aria-hidden="true"></i></span><span class="mx-rm"><b>Before you ride</b><small data-rm-chk-count><?= $done ?> of <?= $total ?> done today</small></span><span class="sl-pill <?= $done === $total ? 'delivered' : 'pending' ?>" data-rm-chk-pill><?= $done === $total ? 'Ready' : 'To do' ?></span></div>
  <ol><?php foreach ($d['checklist'] as $k => $label): $on = in_array($k, $d['checked'], true); ?><li class="<?= $on ? 'on' : '' ?>"><button type="button" class="rm-chk" role="checkbox" aria-checked="<?= $on ? 'true' : 'false' ?>" data-rm-chk="<?= h($k) ?>" data-rm-url="<?= h($ajax) ?>"<?= $d['ready'] ? '' : ' disabled' ?>><span><i class="ic ic-sm i-check" aria-hidden="true"></i></span><?= h($label) ?></button></li><?php endforeach; ?></ol>
  <small class="mx-note">Your own daily check. It resets every morning.</small>
</section>
</div>
<div class="mxp-col">
<div id="contact"></div>
<?= sl_mxSec('Emergency contact') ?>
<?php if ($c['set']): ?>
<section class="mx-card mx-list"><div class="mx-row"><span class="mx-ava"><?= h($c['ini']) ?></span><span class="mx-rm"><b><?= h($c['name']) ?></b><small><?= h(($c['relation'] !== '' ? $c['relation'] . ' · ' : '') . $c['phone']) ?></small></span><span class="mx-rr"><a class="rd-call" href="<?= h($c['tel']) ?>" aria-label="Call <?= h($c['name']) ?>"><i class="ic i-phone" aria-hidden="true"></i></a></span></div></section>
<?php endif; ?>
<?php if ($d['ready']): ?>
<details class="rm-details"<?= $c['set'] ? '' : ' open' ?>><summary class="mx-btn sec"><i class="ic ic-sm i-<?= $c['set'] ? 'edit' : 'plus' ?>" aria-hidden="true"></i><?= $c['set'] ? 'Change emergency contact' : 'Add an emergency contact' ?></summary>
<section class="mx-card mx-form"><form data-sl-form data-sl-reload action="<?= h($ajax) ?>" method="post">
  <input type="hidden" name="op" value="emergency_contact">
  <label class="mx-f"><small>Name</small><input class="mx-in" name="name" maxlength="80" autocomplete="off" value="<?= h($c['name']) ?>"></label>
  <label class="mx-f"><small>Phone number, with country code</small><input class="mx-in" name="phone" type="tel" inputmode="tel" maxlength="30" autocomplete="off" value="<?= h($c['phone']) ?>" placeholder="+…"></label>
  <label class="mx-f"><small>Who they are to you (optional)</small><input class="mx-in" name="relation" maxlength="40" value="<?= h($c['relation']) ?>" placeholder="Sister, partner, friend"></label>
  <button type="submit" class="mx-btn">Save contact</button>
  <p class="mx-note">Only you see this contact in the app. Leave name and phone empty and save to remove it.</p>
</form></section></details>
<?php else: ?><div class="mx-ban warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>Emergency contacts are not set up on this system yet.</span></div><?php endif; ?>
<?= sl_mxSec('Safety tips') ?>
<section class="mx-card mx-list">
  <?= sl_mxRow('sun', 'Ride with lights on, day and night', 'Be seen from far away') ?>
  <?= sl_mxRow('pin', 'Meet at the gate, not inside', 'You never have to enter a home or office') ?>
  <?= sl_mxRow('cloud', 'Heavy rain? Stop and wait', 'Tell dispatch you are waiting it out', '<i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i>', sl_route('RiderChat')) ?>
  <?= sl_mxRow('phone', 'Stop before you use the phone', 'Pull over to call, message or check the map') ?>
</section>
<p class="mx-note">Had an accident or a problem with a customer? Call dispatch. In-app incident reports, live trip sharing and insurance details are not available yet.</p>
</div>
</div>
<?= sl_rmClose() ?>
