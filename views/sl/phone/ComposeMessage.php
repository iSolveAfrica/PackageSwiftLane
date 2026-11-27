<?php
/**
 * M-Compose for compose_message.php. Posts to ajax/sl/compose_ajax.php with the web page's fields
 * (op estimate | test | send, channel, ids, segment, list, subject, message; chunked with after_id / batch_id).
 */
$slPhone['scripts'][] = 'dataJs/sl/phone/msg.js';
$ajax = 'ajax/sl/compose_ajax.php';
$ch = $d['channel'] ?? 'whatsapp';
$site = $core->site_name ?? 'Swift Lane';
$chL = ['whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'email' => 'Email'];
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-msg.css')) ?>">
<?= sl_phoneTop('Compose message', '', ['back' => sl_route('CommsHub')], sl_phoneIcon('more', 'Open the full page', sl_url(['view' => 'web']))) ?>
<main class="mx-body">
<form id="pm-compose" data-pm-compose="<?= h($ajax) ?>">
<input type="hidden" name="channel" id="pm-cm-ch" value="<?= h($ch) ?>">
<input type="hidden" name="ids" value="<?= h(implode(',', array_column($d['pre'] ?? [], 'id'))) ?>">
<label class="mx-f"><small>Send by</small><div class="mx-fseg" role="group" aria-label="Send by"><?php foreach ($chL as $k => $l): ?><button type="button" data-pm-ch="<?= $k ?>" aria-pressed="<?= $k === $ch ? 'true' : 'false' ?>" class="<?= $k === $ch ? 'on' : '' ?>"><?= $l ?><?= empty($d['health'][$k]['ok']) ? ' ·' : '' ?></button><?php endforeach; ?></div></label>
<p class="mx-note" data-pm-chnote></p>
<section class="mx-card mx-form">
<?php if (!empty($d['pre'])): ?><div class="mx-f"><small>To</small><div class="mx-chips"><?php foreach ($d['pre'] as $p): ?><span><?= h($p['name']) ?></span><?php endforeach; ?></div></div><?php endif; ?>
<label class="mx-f"><small><?= !empty($d['pre']) ? 'And a group' : 'To' ?></small><span class="pm-sel"><select class="mx-in" name="segment"><option value="">— <?= !empty($d['pre']) ? 'nobody else' : 'pick a group' ?> —</option><?php foreach ($d['segments'] as $s): ?><option value="<?= h($s['k']) ?>"<?= $s['k'] === $d['segment'] ? ' selected' : '' ?>><?= h($s['label']) ?> (<?= (int) $s['n'] ?>)</option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
<label class="mx-f"><small>Or paste e-mails / phones</small><input class="mx-in" name="list" inputmode="email" autocomplete="off" placeholder="ama@mail.com, 024 551 0921"></label>
<label class="mx-f"><small>Template</small><span class="pm-sel"><select class="mx-in" data-pm-tpl aria-label="Template"><option value="">Write my own</option></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
<label class="mx-f" data-pm-subj><small>Subject</small><input class="mx-in" name="subject" placeholder="Your parcel update"></label>
<label class="mx-f"><small>Message</small><textarea class="mx-in" name="message" rows="5" data-pm-body>Hi [FIRST_NAME], </textarea></label>
</section>
<div class="mx-chips"><?php foreach (['[FIRST_NAME]', '[USERNAME]', '[SITE_NAME]', '[URL]'] as $v): ?><button type="button" data-pm-var="<?= h($v) ?>"><?= h($v) ?></button><?php endforeach; ?></div>
<section class="mx-card pm-wa"><div class="mx-msg"><p data-pm-preview></p><small>Preview for <?= h($d['sample']['name']) ?></small></div></section>
<section class="mx-card mx-kv"><div><span data-pm-costl>Cost</span><b data-pm-cost>Pick recipients</b></div><div><span>Send</span><b>Now</b></div></section>
<p class="mx-note" data-pm-prog hidden></p>
<div class="mx-btns"><button class="mx-btn" type="submit" data-pm-send>Send</button><button class="mx-btn sec" type="button" data-pm-cm-test>Send me a test</button></div>
</form>
<script type="application/json" id="pm-compose-data"><?= json_encode(['tpl' => $d['templates'] ?? [], 'health' => array_map(fn($x) => ['ok' => (bool) $x['ok'], 'state' => (string) $x['state']], $d['health'] ?? []), 'sample' => $d['sample'] ?? [], 'site' => $site, 'logs' => sl_route('MessageLogs')], JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
</main>
