<?php
/**
 * M-Push for push_notifications.php. Same endpoint and fields as the web page: ajax/tools/push_notifications_ajax.php
 * (action=estimate, then chunked sends with notification_type, user_id, consolidation_id, channels, subject, message,
 * after_id, batch_id). Customer search: ajax/sl/compose_ajax.php op=search. Invoice push and picking single owners of a
 * consolidation stay on a computer.
 */
$slPhone['scripts'][] = 'dataJs/sl/phone/msg.js';
$site = $core->site_name ?? 'Swift Lane';
$rt = $d['rates'] ?? ['available' => false, 'currency' => ''];
$type = in_array($d['type'] ?? '', ['broadcast', 'single_user', 'consolidation'], true) ? $d['type'] : 'broadcast';
$types = array_values(array_filter($d['types'] ?? [], fn($t) => $t['k'] !== 'invoice'));
$chL = ['whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'email' => 'Email'];
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-msg.css')) ?>">
<?= sl_phoneTop('Push notifications', '', ['back' => sl_route('CommsHub')], sl_phoneIcon('more', 'Open the full page', sl_url(['view' => 'web']))) ?>
<main class="mx-body">
<form id="pm-push" data-pm-push="ajax/tools/push_notifications_ajax.php">
<input type="hidden" name="notification_type" value="<?= h($type) ?>" data-pm-type>
<input type="hidden" name="user_id" value="<?= (int) ($d['one']['id'] ?? 0) ?>" data-pm-uid>
<section class="mx-card mx-form">
<label class="mx-f"><small>Title</small><input class="mx-in" name="subject" placeholder="Holiday opening hours" data-pm-subj></label>
<label class="mx-f"><small>Message</small><textarea class="mx-in" name="message" rows="4" placeholder="Write the message every customer gets" data-pm-body></textarea></label>
<label class="mx-f"><small>Audience</small><span class="pm-sel"><select class="mx-in" data-pm-aud aria-label="Audience"><?php foreach ($types as $t): ?><option value="<?= h($t['k']) ?>"<?= $t['k'] === $type ? ' selected' : '' ?>><?= h($t['label']) ?><?= $t['k'] === 'broadcast' ? ' (' . sl_num($d['counts']['all'] ?? 0) . ')' : '' ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
<label class="mx-f" data-pm-show="single_user"<?= $type === 'single_user' ? '' : ' hidden' ?>><small>Customer</small><input class="mx-in" type="search" data-pm-q autocomplete="off" placeholder="Type a name, phone or locker" value="<?= h($d['one']['name'] ?? '') ?>"></label>
<div class="pm-sug" data-pm-sug></div>
<label class="mx-f" data-pm-show="consolidation"<?= $type === 'consolidation' ? '' : ' hidden' ?>><small>Consolidation · all package owners</small><span class="pm-sel"><select class="mx-in" name="consolidation_id"><option value="">Pick a consolidation</option><?php foreach ($d['consolidations'] ?? [] as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $c['id'] === ($d['cid'] ?? 0) ? ' selected' : '' ?>><?= h($c['label']) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
<div class="mx-f"><small>Send by</small><div class="mx-fseg" role="group" aria-label="Send by"><?php foreach ($chL as $k => $l): $on = $k !== 'sms'; ?><button type="button" data-pm-pch="<?= $k ?>" aria-pressed="<?= $on ? 'true' : 'false' ?>"><?= $l ?></button><?php endforeach; ?></div></div>
</section>
<section class="mx-card" style="padding:12px;display:flex;gap:10px;align-items:center"><span class="mx-ava" aria-hidden="true"><?= h(sl_initials($site)) ?></span><div class="mx-rm"><b data-pm-pv-s>Title</b><small data-pm-preview>Your message appears here.</small></div><small class="mx-note">now</small></section>
<section class="mx-card mx-kv"><div><span>Reach</span><b data-pm-reach>Counting…</b></div><div><span>Cost</span><b data-pm-cost>—</b></div></section>
<p class="mx-note" data-pm-prog hidden></p>
<div class="mx-btns"><button class="mx-btn" type="submit" data-pm-send>Send push</button><a class="mx-btn sec" href="<?= h(sl_route('MessageLogs')) ?>">Sent history</a></div>
<p class="mx-note">Invoice pushes are sent from a computer.</p>
</form>
<script type="application/json" id="pm-push-data"><?= json_encode(['rates' => $rt], JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
</main>
