<?php
/**
 * M-CNotifPrefs: notification settings (customer_notifications.php, sl_notifPrefsData()). Every control saves at once
 * through ajax/sl/notification_prefs_ajax.php, exactly like the web page: switches via mx.js (.mx-sw[data-sl-save]),
 * the event matrix and quiet hours via dataJs/sl/notifications.js, presets/language/summary via data-sl-action.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['nav'] = 'none';
$slPhone['scripts'][] = 'dataJs/sl/notifications.js';
$ep = 'ajax/sl/notification_prefs_ajax.php';
$paused = !empty($d['paused']);
$chOn = [];
foreach ((array) ($d['chans'] ?? []) as $c) { $chOn[$c['key']] = ($c['cls'] ?? '') === 'on'; }
$sw = fn(bool $on, string $label, string $attrs) => '<button type="button" class="mx-sw' . ($on ? ' on' : '') . '" role="switch" aria-checked="' . ($on ? 'true' : 'false') . '" aria-label="' . h($label) . '" data-sl-save="' . h($ep) . '"' . $attrs . '></button>';
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-cust.css')) ?>">
<?= sl_phoneTop('Notification settings', '', ['back' => sl_route('MyAccount')]) ?>
<main class="mx-body">
<?php if (empty($d['ready'])): ?><div class="mx-ban warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>Saving these settings needs a database upgrade. The defaults below are used until then.</span></div><?php endif; ?>
<section class="b3-cd b3-pause"><span class="mx-li"><i class="ic i-bell" aria-hidden="true"></i></span><span class="mx-rm"><b>Pause all messages</b><small><?= $paused ? 'Paused · security codes still arrive' : 'Security codes are always sent' ?></small></span><?= $sw($paused, 'Pause all messages', ' data-p-op="pref" data-key="notif_paused" data-sl-reload') ?></section>

<div class="mx-sec"><h2>Where we reach you</h2><a href="<?= h(sl_route('MyNotifications')) ?>">My messages</a></div>
<section class="b3-cd">
<?php foreach ((array) ($d['chans'] ?? []) as $c): $on = ($c['cls'] ?? '') === 'on'; ?>
<div class="b3-chn"><span class="mx-li"><i class="ic <?= h($c['icon']) ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($c['label']) ?></b><small><?= h($c['addr']) ?><?= ($c['addr'] ?? '') === 'Not on file' ? ' · add it in your profile' : '' ?></small></span><?= $sw($on, $c['label'] . ' messages', ' data-p-op="channel" data-p-channel="' . h($c['key']) . '"') ?></div>
<?php endforeach; ?>
<div class="b3-chn"><span class="mx-li"><i class="ic i-send" aria-hidden="true"></i></span><span class="mx-rm"><b>Test message</b><small>Arrives in your bell, free</small></span><button type="button" class="b3-gh sm" data-sl-action="<?= h($ep) ?>" data-p-op="test" data-sl-reload>Send me a test</button></div>
</section>

<div class="mx-sec"><h2>What we send</h2></div>
<div class="b3-mini wide" role="group" aria-label="Preset"><button type="button" data-sl-action="<?= h($ep) ?>" data-p-op="preset" data-p-preset="all" data-sl-reload>Everything</button><button type="button" data-sl-action="<?= h($ep) ?>" data-p-op="preset" data-p-preset="key" data-sl-reload>Only important</button><button type="button" class="on" aria-pressed="true" disabled>Custom</button></div>
<section class="b3-cd b3-mx"><div class="b3-mxr h"><span>Event</span><span>WhatsApp</span><span>SMS</span><span>E-mail</span></div>
<?php foreach ((array) ($d['evs'] ?? []) as $e): ?>
<div class="b3-mxr"><span><?= h($e['n']) ?></span><?php foreach (['whatsapp' => ['wa', 'WhatsApp'], 'sms' => ['sms', 'SMS'], 'email' => ['em', 'e-mail']] as $ch => [$k, $lbl]): $dis = empty($chOn[$ch]) || $paused; $on = !$dis && ($e[$k] ?? '') === 'on'; ?><button type="button" class="sl-chk<?= $on ? ' on' : '' ?>" role="checkbox" aria-checked="<?= $on ? 'true' : 'false' ?>" aria-label="<?= h($e['n'] . ' by ' . $lbl) ?>" data-sl-chk-save="<?= h($ep) ?>" data-channel="<?= h($ch) ?>" data-event="<?= h($e['key']) ?>"<?= $dis ? ' disabled title="' . ($paused ? 'Messages are paused' : h($lbl) . ' is off') . '"' : '' ?>><i class="ic ic-sm i-check" aria-hidden="true"></i></button><?php endforeach; ?></div>
<?php endforeach; ?>
</section>
<p class="b3-cap">Security codes (log-in and pickup codes) are always sent, whatever you pick here. <?= h($d['costNote'] ?? '') ?></p>

<div class="mx-sec"><h2>Quiet hours</h2></div>
<section class="mx-card mx-form">
<div class="mx-tg"><span class="mx-rm"><b>No WhatsApp or SMS at night</b><small>Messages wait until morning</small></span><?= $sw(!empty($d['quiet']), 'Quiet hours', ' data-p-op="pref" data-key="notif_quiet"') ?></div>
<div class="mx-two"><label class="mx-f"><small>From</small><input class="mx-in mx-inp mono" id="cn-quiet-from" type="time" value="<?= h($d['quietFrom'] ?? '21:00') ?>"></label><label class="mx-f"><small>Until</small><input class="mx-in mx-inp mono" id="cn-quiet-to" type="time" value="<?= h($d['quietTo'] ?? '07:00') ?>"></label></div>
<div class="mx-f"><small id="cn-lang-l">Language</small><div class="mx-fseg" role="group" aria-labelledby="cn-lang-l"><?php foreach ((array) ($d['langs'] ?? []) as $l): ?><button type="button" class="<?= h($l['cls']) ?>" aria-pressed="<?= $l['cls'] === 'on' ? 'true' : 'false' ?>" data-sl-action="<?= h($ep) ?>" data-p-op="pref" data-p-key="notif_lang" data-p-value="<?= h($l['v']) ?>" data-sl-reload><?= h($l['label']) ?></button><?php endforeach; ?></div></div>
<div class="mx-f"><small id="cn-dig-l">E-mail summary</small><div class="mx-fseg" role="group" aria-labelledby="cn-dig-l"><?php foreach ((array) ($d['digs'] ?? []) as $g): ?><button type="button" class="<?= h($g['cls']) ?>" aria-pressed="<?= $g['cls'] === 'on' ? 'true' : 'false' ?>" data-sl-action="<?= h($ep) ?>" data-p-op="pref" data-p-key="notif_digest" data-p-value="<?= h($g['v']) ?>" data-sl-reload><?= h($g['label']) ?></button><?php endforeach; ?></div></div>
</section>

<div class="mx-sec"><h2>Offers and news</h2></div>
<section class="mx-card mx-tgs">
<div class="mx-tg"><span class="mx-rm"><b>Promotions</b><small>Special prices when we run them</small></span><?= $sw(!empty($d['promo']), 'Promotions', ' data-p-op="pref" data-key="notif_promo"') ?></div>
<div class="mx-tg"><span class="mx-rm"><b>Newsletter</b><small>Once a month by e-mail</small></span><?= $sw(!empty($d['news']), 'Newsletter', ' data-p-op="newsletter"') ?></div>
</section>

<div class="mx-sec"><h2>Recent messages</h2><a href="<?= h(sl_route('MyNotifications')) ?>">See all</a></div>
<?php if (!empty($d['recent'])): ?>
<section class="mx-card mx-list">
<?php foreach ($d['recent'] as $r): ?>
<div class="mx-row"><span class="mx-li"><i class="ic <?= h($r['icon']) ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($r['t']) ?></b></span><span class="mx-rr"><small><?= h($r['w']) ?></small></span></div>
<?php endforeach; ?>
</section>
<?php else: ?>
<section class="mx-card"><?= sl_mxEmpty('bell', 'No messages yet', 'Parcel updates you get show up here.') ?></section>
<?php endif; ?>
</main>
<div class="mx-foot"><div class="b3-ft"><a class="mx-btn" href="<?= h(sl_route('MyAccount')) ?>">Done · changes save straight away</a></div></div>
