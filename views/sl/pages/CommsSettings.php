<?php
/**
 * CommsSettings - Notifications & Messages > Settings. One page for every
 * channel setting. Each section is its own form posting to
 * ajax/comms/settings_ajax.php (data-sl-form); dataJs/sl/comms_settings.js
 * adds the provider / mailer switches, rotate key, connection checks, the
 * status-owner switch and the test sends.
 * Data: views/sl/data/comms_settings.php.
 */
if (!isset($d)) { $d = []; }
$commsTab = 'commssettings';
include __DIR__ . '/partials/comms_tabs.php';
$acc = $d['acc'];
$sec = $acc['secrets'];
$wa = $d['wa'];
$sms = $d['sms'];
$em = $d['email'];
$ru = $d['rules'];
$co = $d['consent'];
$kg = $d['king'];
$cost = $d['costs'];
$ajax = $d['ajax'];
$ro = $sec ? '' : ' disabled';
/** A switch posting 1/0 under $name. $instant: saves on its own (set_channel), for administrators who may not change keys. */
$tg = function (string $name, bool $on, string $label, bool $locked = false, bool $instant = false) use (&$ajax): string {
    return '<span class="sl-toggle' . ($on ? ' on' : '') . '" role="switch" aria-checked="' . ($on ? 'true' : 'false') . '" aria-label="' . h($label) . '"'
        . ($locked ? ' data-static' : '') . ($instant ? ' data-sl-save="' . h($ajax) . '" data-key="' . h($name) . '" data-p-action="set_channel"' : '')
        . ' data-cs-switch="' . h($name) . '">' . ($instant ? '' : '<input type="hidden" name="' . h($name) . '" value="' . ($on ? '1' : '0') . '">') . '</span>';
};
$secretPh = fn(bool $set) => $set ? '•••••••• saved - leave blank to keep' : 'Not set';
/** "Remove" next to a saved secret: clears it on the next save (clear_secrets). */
$clearBtn = fn(string $name, bool $set) => $sec && $set ? '<button type="button" class="sl-btn ghost sm cs-clr" data-cs-clear="' . h($name) . '" aria-pressed="false">Remove saved</button>' : '';
$sections = [
    'cs-whatsapp' => ['WhatsApp', 'i-wa'], 'cs-sms' => ['SMS', 'i-message'], 'cs-email' => ['E-mail', 'i-mail'], 'cs-rules' => ['Sending rules', 'i-bolt'],
    'cs-consent' => ['Consent and opt-out', 'i-shield'], 'cs-king' => [$kg['name'] . ' (WhatsApp bot)', 'i-ai'], 'cs-costs' => ['Costs', 'i-dollar'], 'cs-test' => ['Test send', 'i-send'],
];
$cur = $cost['currency'];
$money = fn($v) => trim($cur . ' ' . number_format((float) $v, (float) $v > 0 && (float) $v < 0.1 ? 4 : 2));
?>
<div class="sl-head"><div><h1 class="sl-title">Message settings</h1><p class="sl-lede">Providers, sending rules, consent, <?= h($kg['name']) ?>, costs and test sends for WhatsApp, SMS and e-mail - all in one place.</p></div>
<div class="row g8 wrap"><a class="sl-btn ghost" href="<?= h($d['links']['automations']) ?>"><i class="ic ic-sm i-bolt" aria-hidden="true"></i>Automations</a><a class="sl-btn ghost" href="<?= h($d['links']['outbox']) ?>"><i class="ic ic-sm i-archive" aria-hidden="true"></i>Outbox</a></div></div>

<?php if (!$d['ready']): ?><div class="sl-banner bad"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>The message tables could not be created. The database user needs the CREATE privilege once.</span></div><?php endif; ?>
<?php if ($d['demo']): ?><div class="sl-banner warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>Demo mode: provider settings cannot be changed.</span></div><?php endif; ?>
<?php if (!$sec): ?><div class="sl-banner info"><i class="ic ic-sm i-lock" aria-hidden="true"></i><span>Provider keys are read-only for you. A super admin (or someone with the API settings permission) changes them. You can switch WhatsApp and SMS on or off, and change sending rules and <?= h($kg['name']) ?>.</span></div><?php endif; ?>

<section class="cs-strip" aria-label="Channel status" data-cs-strip>
<?php foreach ($d['status'] as $k => $st): ?>
<div class="sl-card cs-st <?= $st['ok'] ? 'ok' : ($st['warn'] ? 'warn' : 'off') ?>" data-cs-st="<?= h($k) ?>"><span class="cs-sti"><i class="ic ic-sm <?= h($st['icon']) ?>" aria-hidden="true"></i></span><span class="col cs-stt"><b class="fw5"><?= h($st['label']) ?></b><span class="mut cs-sts"><?= h($st['provider']) ?></span></span><span class="sl-pill <?= $st['ok'] ? 'ok' : ($st['warn'] ? 'warn' : 'plain') ?>" data-cs-state><?= h($st['state']) ?></span></div>
<?php endforeach; ?>
</section>

<div class="cs-wrap">
<nav class="cs-index" aria-label="Settings sections">
<?php foreach ($sections as $id => [$l, $ic]): ?><a href="#<?= h($id) ?>"><i class="ic ic-sm <?= h($ic) ?>" aria-hidden="true"></i><span><?= h($l) ?></span></a><?php endforeach; ?>
</nav>
<div class="cs-main col g16">

<!-- WhatsApp -->
<form class="sl-card col g16 cs-sec" id="cs-whatsapp" data-sl-form action="<?= h($ajax) ?>" method="post" autocomplete="off">
<input type="hidden" name="action" value="save_whatsapp"><input type="hidden" name="clear_secrets" value="">
<div class="sl-ch" style="margin:0"><div><h2 class="sl-ct">WhatsApp</h2><span class="mut sm">Every WhatsApp message goes through the provider chosen here.</span></div><?php if ($sec): ?><button class="sl-btn sm" type="submit">Save WhatsApp</button><?php else: ?><span class="sl-badge">Read-only</span><?php endif; ?></div>
<label class="row g10 cs-sw"><?= $tg('active_whatsapp', $wa['active'], 'WhatsApp switched on', false, !$sec) ?><span class="col"><span>WhatsApp switched on</span><span class="mut sm">Off stops every WhatsApp message (automatic, manual and tests) with either provider. The inbox still receives.<?= $sec ? '' : ' This switch saves at once.' ?></span></span></label>
<div class="sl-field"><span class="sl-lab" id="cs-wa-prov-l">Provider</span><div class="sl-seg boxed" role="radiogroup" aria-labelledby="cs-wa-prov-l" data-cs-seg="wa_provider">
<?php foreach (['ultramsg' => 'UltraMsg (your phone, by QR)', 'cloud' => 'Meta WhatsApp Cloud API'] as $k => $l): ?><button type="button" role="radio" aria-checked="<?= $wa['provider'] === $k ? 'true' : 'false' ?>" class="<?= $wa['provider'] === $k ? 'on' : '' ?>" data-v="<?= h($k) ?>"<?= $ro ?>><?= h($l) ?></button><?php endforeach; ?>
</div><input type="hidden" name="wa_provider" value="<?= h($wa['provider']) ?>"></div>

<div class="col g12" data-cs-when="wa_provider=ultramsg"<?= $wa['provider'] === 'ultramsg' ? '' : ' hidden' ?>>
<div class="grid cols2 cs-g"><label class="sl-field"><span class="sl-lab">Instance address</span><input class="sl-in mono" name="api_ws_url" value="<?= h($wa['url']) ?>" placeholder="https://api.ultramsg.com/instance12345" inputmode="url"<?= $ro ?>><span class="sl-hint">From your UltraMsg dashboard, ends with your instance id.</span></label>
<label class="sl-field"><span class="sl-lab">Token</span><input class="sl-in mono" name="api_ws_token" type="password" autocomplete="new-password" placeholder="<?= h($secretPh($wa['token_set'])) ?>"<?= $ro ?>><?= $clearBtn('api_ws_token', $wa['token_set']) ?></label></div>
<div class="row g8 wrap cs-acts"><?php if ($wa['connect_url'] !== ''): ?><a class="sl-btn ghost sm" href="<?= h($wa['connect_url']) ?>"><i class="ic ic-sm i-phone" aria-hidden="true"></i>Link your phone (scan the QR)</a><?php endif; ?><?php if ($sec): ?><button class="sl-btn ghost sm" type="button" data-cs-do="check_whatsapp"><i class="ic ic-sm i-activity" aria-hidden="true"></i>Check connection</button><?php endif; ?><span class="mut sm" data-cs-out="check_whatsapp" role="status" aria-live="polite"></span></div>
</div>

<div class="col g12" data-cs-when="wa_provider=cloud"<?= $wa['provider'] === 'cloud' ? '' : ' hidden' ?>>
<div class="grid cols2 cs-g"><label class="sl-field"><span class="sl-lab">Phone number ID</span><input class="sl-in mono" name="wa_cloud_phone_id" value="<?= h($wa['cloud_phone_id']) ?>" inputmode="numeric"<?= $ro ?>></label>
<label class="sl-field"><span class="sl-lab">Access token</span><input class="sl-in mono" name="wa_cloud_token" type="password" autocomplete="new-password" placeholder="<?= h($secretPh($wa['cloud_token_set'])) ?>"<?= $ro ?>><?= $clearBtn('wa_cloud_token', $wa['cloud_token_set']) ?></label>
<label class="sl-field"><span class="sl-lab">App secret (checks Meta's signature)</span><input class="sl-in mono" name="wa_cloud_app_secret" type="password" autocomplete="new-password" placeholder="<?= h($secretPh($wa['cloud_secret_set'])) ?>"<?= $ro ?>><?= $clearBtn('wa_cloud_app_secret', $wa['cloud_secret_set']) ?></label>
<label class="sl-field"><span class="sl-lab">Verify token (you choose it)</span><input class="sl-in mono" name="wa_cloud_verify" value="<?= $sec ? h($wa['cloud_verify']) : ($wa['cloud_verify'] !== '' ? '••••••' : '') ?>"<?= $ro ?>></label>
<label class="sl-field"><span class="sl-lab">Graph API version</span><input class="sl-in mono" name="wa_cloud_version" value="<?= h($wa['cloud_version']) ?>" placeholder="v20.0"<?= $ro ?>></label></div>
<span class="mut sm">Outside the 24-hour window Meta only delivers approved templates: give each automatic message its template name in Automations.</span>
</div>

<div class="sl-field"><span class="sl-lab">Webhook address (paste it in the provider's dashboard)</span>
<div class="row g8 cs-hook"><input class="sl-in mono grow" readonly value="<?= h($wa['webhook']) ?>" aria-label="Webhook address" data-cs-hook><?php if ($sec): ?><button class="sl-btn ghost sm" type="button" data-cs-copy aria-label="Copy the webhook address"><i class="ic ic-sm i-copy" aria-hidden="true"></i>Copy</button><button class="sl-btn ghost sm" type="button" data-cs-do="rotate_key" data-cs-confirm="Make a new secret key? The old webhook address stops working until you paste the new one in the provider's dashboard."><i class="ic ic-sm i-refresh" aria-hidden="true"></i>Rotate key</button><?php endif; ?></div>
<?php if ($wa['site_url_missing']): ?><span class="sl-hint cs-warn">The company website address is empty, so this address uses the one you opened the page with. Set it in <a href="<?= h($d['links']['company']) ?>">Company settings</a>.</span><?php endif; ?></div>
</form>

<!-- SMS -->
<form class="sl-card col g16 cs-sec" id="cs-sms" data-sl-form action="<?= h($ajax) ?>" method="post" autocomplete="off">
<input type="hidden" name="action" value="save_sms"><input type="hidden" name="clear_secrets" value="">
<div class="sl-ch" style="margin:0"><div><h2 class="sl-ct">SMS</h2><span class="mut sm">Hubtel and mNotify (Ghana). The default provider sends; the other one can take over when it fails.</span></div><?php if ($sec): ?><button class="sl-btn sm" type="submit">Save SMS</button><?php else: ?><span class="sl-badge">Read-only</span><?php endif; ?></div>
<label class="row g10 cs-sw"><?= $tg('active_sms', $sms['active'], 'SMS switched on', false, !$sec) ?><span class="col"><span>SMS switched on</span><span class="mut sm">Needs at least one provider switched on.<?= $sec ? '' : ' This switch saves at once.' ?></span></span></label>
<div class="cs-prov">
<fieldset class="cs-pc"><legend class="row g8"><b class="fw5">Hubtel</b><span class="sl-pill <?= $sms['hub_ready'] ? 'ok' : 'plain' ?>"><?= $sms['hub_ready'] ? 'Keys saved' : 'No keys' ?></span></legend>
<label class="row g10 sm cs-sw"><?= $tg('hubtel_enabled', $sms['hub_on'], 'Hubtel on', !$sec) ?><span>Hubtel on</span></label>
<label class="sl-field"><span class="sl-lab">Client ID</span><input class="sl-in mono" name="hubtel_client_id" value="<?= h($sms['hub_id']) ?>"<?= $ro ?>></label>
<label class="sl-field"><span class="sl-lab">Client secret</span><input class="sl-in mono" name="hubtel_client_secret" type="password" autocomplete="new-password" placeholder="<?= h($secretPh($sms['hub_secret_set'])) ?>"<?= $ro ?>><?= $clearBtn('hubtel_client_secret', $sms['hub_secret_set']) ?></label>
<label class="sl-field"><span class="sl-lab">Sender ID (max 11)</span><input class="sl-in" name="hubtel_sender_id" maxlength="11" value="<?= h($sms['hub_sender']) ?>"<?= $ro ?>></label></fieldset>
<fieldset class="cs-pc"><legend class="row g8"><b class="fw5">mNotify</b><span class="sl-pill <?= $sms['mn_ready'] ? 'ok' : 'plain' ?>"><?= $sms['mn_ready'] ? 'Keys saved' : 'No keys' ?></span></legend>
<label class="row g10 sm cs-sw"><?= $tg('mnotify_enabled', $sms['mn_on'], 'mNotify on', !$sec) ?><span>mNotify on</span></label>
<label class="sl-field"><span class="sl-lab">API key</span><input class="sl-in mono" name="mnotify_api_key" type="password" autocomplete="new-password" placeholder="<?= h($secretPh($sms['mn_key_set'])) ?>"<?= $ro ?>><?= $clearBtn('mnotify_api_key', $sms['mn_key_set']) ?></label>
<label class="sl-field"><span class="sl-lab">Sender ID (max 11)</span><input class="sl-in" name="mnotify_sender_id" maxlength="11" value="<?= h($sms['mn_sender']) ?>"<?= $ro ?>></label>
<?php if ($sec): ?><div class="row g8 wrap"><button class="sl-btn ghost sm" type="button" data-cs-do="mnotify_balance"><i class="ic ic-sm i-dollar" aria-hidden="true"></i>Check balance</button><span class="mut sm" data-cs-out="mnotify_balance" role="status" aria-live="polite"></span></div><?php endif; ?></fieldset>
</div>
<div class="grid cols2 cs-g"><label class="sl-field"><span class="sl-lab">Default provider</span><span class="sl-sel native"><select name="sms_default_provider"<?= $ro ?>><?php foreach ($sms['providers'] as $k => $l): ?><option value="<?= h($k) ?>"<?= $sms['default'] === $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
<label class="row g10 cs-sw"><?= $tg('sms_fallback', $sms['fallback'], 'Use the other provider when one fails', !$sec) ?><span>Use the other provider when one fails</span></label></div>
</form>

<!-- E-mail -->
<form class="sl-card col g16 cs-sec" id="cs-email" data-sl-form action="<?= h($ajax) ?>" method="post" autocomplete="off">
<input type="hidden" name="action" value="save_email"><input type="hidden" name="clear_secrets" value="">
<div class="sl-ch" style="margin:0"><div><h2 class="sl-ct">E-mail</h2><span class="mut sm">The mail server and the one address every e-mail comes from.</span></div><?php if ($sec): ?><button class="sl-btn sm" type="submit">Save e-mail</button><?php else: ?><span class="sl-badge">Read-only</span><?php endif; ?></div>
<div class="sl-field"><span class="sl-lab" id="cs-mailer-l">Send with</span><div class="sl-seg boxed" role="radiogroup" aria-labelledby="cs-mailer-l" data-cs-seg="mailer">
<?php foreach (['SMTP' => 'SMTP server (recommended)', 'PHP' => 'PHP mail() on this server'] as $k => $l): ?><button type="button" role="radio" aria-checked="<?= $em['mailer'] === $k ? 'true' : 'false' ?>" class="<?= $em['mailer'] === $k ? 'on' : '' ?>" data-v="<?= h($k) ?>"<?= $ro ?>><?= h($l) ?></button><?php endforeach; ?>
</div><input type="hidden" name="mailer" value="<?= h($em['mailer']) ?>"></div>
<div class="grid cols2 cs-g"><label class="sl-field"><span class="sl-lab">From name</span><input class="sl-in" name="smtp_names" value="<?= h($em['from_name']) ?>" maxlength="120"<?= $ro ?>></label>
<label class="sl-field"><span class="sl-lab">From address</span><input class="sl-in" name="email_address" type="email" value="<?= h($em['from']) ?>" placeholder="hello@yourcompany.com"<?= $ro ?>></label></div>
<label class="row g10 sm cs-sw"><?= $tg('sync_site_email', $em['site_email'] === '' || strcasecmp($em['site_email'], $em['from']) === 0, 'Use this address everywhere', !$sec) ?><span class="col"><span>Use the same address as the company support e-mail</span><span class="mut sm">Some older screens send from the support e-mail<?= $em['site_email'] !== '' && strcasecmp($em['site_email'], $em['from']) !== 0 ? ' (now ' . h($em['site_email']) . ')' : '' ?>. One address avoids spam filters.</span></span></label>
<div class="col g12" data-cs-when="mailer=SMTP"<?= $em['mailer'] === 'SMTP' ? '' : ' hidden' ?>>
<div class="grid cols2 cs-g"><label class="sl-field"><span class="sl-lab">SMTP host</span><input class="sl-in mono" name="smtp_host" value="<?= h($em['host']) ?>" placeholder="smtp.yourmail.com"<?= $ro ?>></label>
<label class="sl-field"><span class="sl-lab">User name</span><input class="sl-in mono" name="smtp_user" value="<?= h($em['user']) ?>" autocomplete="off"<?= $ro ?>></label>
<label class="sl-field"><span class="sl-lab">Password</span><input class="sl-in mono" name="smtp_password" type="password" autocomplete="new-password" placeholder="<?= h($secretPh($em['pass_set'])) ?>"<?= $ro ?>><?= $clearBtn('smtp_password', $em['pass_set']) ?></label>
<div class="grid cols2 cs-g"><label class="sl-field"><span class="sl-lab">Port</span><input class="sl-in mono" name="smtp_port" type="number" min="1" max="65535" value="<?= (int) $em['port'] ?>"<?= $ro ?>></label>
<label class="sl-field"><span class="sl-lab">Security</span><span class="sl-sel native"><select name="smtp_secure"<?= $ro ?>><?php foreach (['tls' => 'TLS (587)', 'ssl' => 'SSL (465)', 'none' => 'None (25)'] as $k => $l): ?><option value="<?= $k ?>"<?= $em['secure'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label></div></div>
<?php if ($sec): ?><div class="row g8 wrap"><button class="sl-btn ghost sm" type="button" data-cs-do="check_smtp"><i class="ic ic-sm i-activity" aria-hidden="true"></i>Check the server</button><span class="mut sm" data-cs-out="check_smtp" role="status" aria-live="polite"></span></div><?php endif; ?>
</div>
</form>

<!-- Sending rules -->
<form class="sl-card col g16 cs-sec" id="cs-rules" data-sl-form action="<?= h($ajax) ?>" method="post">
<input type="hidden" name="action" value="save_rules">
<div class="sl-ch" style="margin:0"><div><h2 class="sl-ct">Sending rules</h2><span class="mut sm">When automatic messages go out. Which message goes by which channel is set in <a href="<?= h($d['links']['automations']) ?>">Automations</a> and <a href="<?= h($d['links']['statuses']) ?>">Status messages</a>.</span></div><button class="sl-btn sm" type="submit">Save rules</button></div>
<label class="row g10 cs-sw"><?= $tg('enabled', $ru['enabled'], 'Automatic messages switched on') ?><span class="col"><span>Automatic messages switched on</span><span class="mut sm">The master switch for status messages, reminders and surveys sent by the Automation Center. Messages already waiting are still delivered.</span></span></label>

<div class="cs-owner <?= $ru['owner'] === 'comms' ? 'on' : '' ?>" data-cs-owner="<?= h($ru['owner']) ?>">
<div class="col g4 grow"><b class="fw5">Who sends status messages</b>
<span class="sm" data-cs-owner-text><?= $ru['owner'] === 'comms' ? 'The Automation Center sends every status message, with the texts and channels of Status messages. The old screens no longer send them (no double messages).' : 'The old screens send status messages as they always did. Status messages edited here are used once you hand them over.' ?></span>
<span class="mut sm">Worker: <?= $ru['last_run'] !== '' ? 'last ran ' . h(sl_ago($ru['last_run'])) : 'has never run' ?><?= $ru['worker_fresh'] ? '' : ' - it must run every 5 minutes (cron/comms_worker.php) before you hand over' ?>.</span></div>
<div class="sl-seg boxed" role="radiogroup" aria-label="Who sends status messages"><button type="button" role="radio" aria-checked="<?= $ru['owner'] === 'legacy' ? 'true' : 'false' ?>" class="<?= $ru['owner'] === 'legacy' ? 'on' : '' ?>" data-cs-owner-set="legacy">Old screens</button><button type="button" role="radio" aria-checked="<?= $ru['owner'] === 'comms' ? 'true' : 'false' ?>" class="<?= $ru['owner'] === 'comms' ? 'on' : '' ?>" data-cs-owner-set="comms">Automation Center</button></div>
</div>

<div class="grid cols3 cs-g"><label class="sl-field"><span class="sl-lab">Quiet hours from</span><input class="sl-in" type="time" name="quiet_start" value="<?= h($ru['quiet_start']) ?>"></label>
<label class="sl-field"><span class="sl-lab">Quiet hours until</span><input class="sl-in" type="time" name="quiet_end" value="<?= h($ru['quiet_end']) ?>"></label>
<label class="sl-field"><span class="sl-lab">Messages per worker run</span><input class="sl-in" type="number" min="1" max="10000" name="batch_size" value="<?= (int) $ru['batch_size'] ?>"></label></div>
<span class="mut sm cs-sub">Nothing automatic goes out during quiet hours; it waits until they end. One-time codes and replies to customers are never held.</span>
<fieldset class="cs-fs"><legend class="fw5">Reminder: parcel waiting for pickup</legend><div class="grid cols3 cs-g">
<label class="sl-field"><span class="sl-lab">First after (days)</span><input class="sl-in" type="number" min="0" max="365" name="claim_after_days" value="<?= (int) $ru['claim_after_days'] ?>"></label>
<label class="sl-field"><span class="sl-lab">Then every (days)</span><input class="sl-in" type="number" min="1" max="365" name="claim_every_days" value="<?= (int) $ru['claim_every_days'] ?>"></label>
<label class="sl-field"><span class="sl-lab">At most</span><input class="sl-in" type="number" min="0" max="50" name="claim_max" value="<?= (int) $ru['claim_max'] ?>"></label></div></fieldset>
<fieldset class="cs-fs"><legend class="fw5">Reminder: unpaid balance</legend><div class="grid cols3 cs-g">
<label class="sl-field"><span class="sl-lab">First after (days)</span><input class="sl-in" type="number" min="0" max="365" name="pay_after_days" value="<?= (int) $ru['pay_after_days'] ?>"></label>
<label class="sl-field"><span class="sl-lab">Then every (days)</span><input class="sl-in" type="number" min="1" max="365" name="pay_every_days" value="<?= (int) $ru['pay_every_days'] ?>"></label>
<label class="sl-field"><span class="sl-lab">At most</span><input class="sl-in" type="number" min="0" max="50" name="pay_max" value="<?= (int) $ru['pay_max'] ?>"></label>
<label class="sl-field"><span class="sl-lab">Only from (amount)</span><input class="sl-in" type="number" min="0" step="0.01" name="pay_min_amount" value="<?= h($ru['pay_min_amount']) ?>"></label>
<label class="sl-field"><span class="sl-lab">Currency shown in messages</span><input class="sl-in" name="currency" maxlength="8" value="<?= h($ru['currency']) ?>"></label></div></fieldset>
<label class="row g10 cs-sw"><?= $tg('campaign_needs_approval', $ru['approval'], 'Campaigns need a second person to approve') ?><span class="col"><span>Campaigns need a second person to approve</span><span class="mut sm">A super admin may approve their own.</span></span></label>
<span class="mut sm">Survey timing and the in-app survey pop-up are set on <a href="<?= h($d['links']['surveys']) ?>">Surveys</a>.</span>
</form>

<!-- Consent -->
<section class="sl-card col g16 cs-sec" id="cs-consent" aria-labelledby="cs-consent-h">
<div class="sl-ch" style="margin:0"><div><h2 class="sl-ct" id="cs-consent-h">Consent and opt-out</h2><span class="mut sm">Always on: a customer's choice beats every setting on this page.</span></div><a class="sl-btn ghost sm" href="<?= h($d['links']['customers']) ?>"><i class="ic ic-sm i-user" aria-hidden="true"></i>Change for a customer</a></div>
<div class="cs-kpis">
<div class="cs-k"><span class="mut sm">Opted out of everything</span><b><?= sl_num($co['optout']) ?></b></div>
<div class="cs-k"><span class="mut sm">No WhatsApp / SMS / e-mail</span><b><?= sl_num($co['wa_off']) ?> / <?= sl_num($co['sms_off']) ?> / <?= sl_num($co['email_off']) ?></b></div>
<div class="cs-k"><span class="mut sm">Said yes to offers (WhatsApp / SMS / e-mail)</span><b><?= sl_num($co['mkt']['whatsapp']) ?> / <?= sl_num($co['mkt']['sms']) ?> / <?= sl_num($co['mkt']['email']) ?></b></div>
<div class="cs-k"><span class="mut sm">Newsletter unsubscribes</span><b><?= sl_num($co['unsub']) ?></b></div>
</div>
<div class="cs-kw"><div class="col g6"><b class="fw5 sm">Keywords customers can send by WhatsApp</b>
<ul class="cs-kwl"><li><span class="cs-kwt">STOP</span><span class="cs-kwt">UNSUBSCRIBE</span><span class="cs-kwt">OPT OUT</span><span class="mut sm">stops WhatsApp messages and every offer (WhatsApp, SMS and e-mail); status SMS and e-mails still go</span></li>
<li><span class="cs-kwt">START</span><span class="cs-kwt">SUBSCRIBE</span><span class="mut sm">turns messages back on</span></li>
<li><span class="cs-kwt">AGENT</span><span class="cs-kwt">HUMAN</span><span class="mut sm">hands the chat from <?= h($kg['name']) ?> to a person in WhatsApp</span></li></ul></div>
<ul class="cs-rl mut sm">
<li>Transactional messages (statuses, codes, reminders) go unless the customer switched that channel off. A customer who wants nothing at all switches everything off in their preferences.</li>
<li>Campaigns only reach customers who said yes to offers on that channel. Contacts saved from WhatsApp groups are reachable by campaigns; a STOP from them is honoured the same way.</li>
<li>Every e-mail carries an unsubscribe link and every SMS campaign says "Reply STOP to opt out".</li>
<li>Customers change their choices on <a href="<?= h($d['links']['prefs']) ?>">Notification preferences</a> (the only preferences the senders read).</li></ul></div>
</section>

<!-- King -->
<form class="sl-card col g16 cs-sec" id="cs-king" data-sl-form action="<?= h($ajax) ?>" method="post">
<input type="hidden" name="action" value="save_king">
<div class="sl-ch" style="margin:0"><div><h2 class="sl-ct"><?= h($kg['name']) ?> on WhatsApp</h2><span class="mut sm"><?= h($kg['name']) ?> answers customers in WhatsApp first and hands over to a person when asked or unsure.</span></div><button class="sl-btn sm" type="submit">Save</button></div>
<?php if (empty($kg['ai']['ok'])): ?><div class="sl-banner warn"><i class="ic ic-sm i-ai" aria-hidden="true"></i><span><?= h($kg['ai']['message'] ?: $kg['name'] . ' is not set up yet.') ?> <a href="<?= h($kg['ai']['setup_url'] ?: $d['links']['ai']) ?>">Open the AI centre</a>. Writing with <?= h($kg['name']) ?> on the message pages needs it too.</span></div><?php endif; ?>
<label class="row g10 cs-sw"><?= $tg('bot_enabled', $kg['on'], $kg['name'] . ' answers WhatsApp') ?><span class="col"><span><?= h($kg['name']) ?> answers WhatsApp</span><span class="mut sm"><?= sl_num($kg['today']) ?> answers today.</span></span></label>
<div class="grid cols3 cs-g"><label class="sl-field"><span class="sl-lab">Answers per day, at most</span><input class="sl-in" type="number" min="0" max="10000" name="bot_daily_cap" value="<?= (int) $kg['cap'] ?>"><span class="sl-hint">After that, chats wait for a person.</span></label></div>
<label class="sl-field"><span class="sl-lab">Persona and instructions</span><textarea class="sl-in" name="bot_instructions" rows="5" maxlength="4000" placeholder="Friendly and short. Answer in the customer's language. Never promise a delivery date. Offer a person for complaints."><?= h($kg['instructions']) ?></textarea><span class="sl-hint">Added to what <?= h($kg['name']) ?> already knows about the customer and their parcels. The name is set in <a href="<?= h($kg['persona_url']) ?>">AI for customers</a>.</span></label>
</form>

<!-- Costs -->
<section class="sl-card col g16 cs-sec" id="cs-costs" aria-labelledby="cs-costs-h">
<div class="sl-ch" style="margin:0"><div><h2 class="sl-ct" id="cs-costs-h">Costs</h2><span class="mut sm">Price per message, from Message costs. Used for the Outbox, campaign estimates and the Overview.</span></div><?php if ($cost['canView']): ?><a class="sl-btn ghost sm" href="<?= h($cost['url']) ?>"><i class="ic ic-sm i-dollar" aria-hidden="true"></i><?= $cost['canEdit'] ? 'Change prices' : 'See costs' ?></a><?php endif; ?></div>
<div class="cs-kpis">
<?php foreach (['whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'email' => 'E-mail'] as $ch => $l): $r = (float) ($cost['rates'][$ch] ?? 0); ?>
<div class="cs-k"><span class="mut sm"><?= h($l) ?> per message<?= $ch === 'sms' && $cost['sms_parts'] ? ' part' : '' ?></span><b><?= $r > 0 ? h($money($r)) : '<span class="mut">not set</span>' ?></b><span class="mut sm">This month <?= h($money($cost['spent'][$ch] ?? 0)) ?></span></div>
<?php endforeach; ?>
<div class="cs-k"><span class="mut sm">Spent this month</span><b><?= h($money($cost['total'])) ?></b></div>
</div>
</section>

<!-- Test -->
<form class="sl-card col g16 cs-sec" id="cs-test" data-cs-test action="<?= h($ajax) ?>" method="post">
<div class="sl-ch" style="margin:0"><div><h2 class="sl-ct">Test send</h2><span class="mut sm">Sends one short message with the saved settings. It shows in the Outbox as a test.</span></div></div>
<div class="sl-seg boxed" role="radiogroup" aria-label="Channel to test" data-cs-seg="test_channel">
<?php foreach (['whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'email' => 'E-mail'] as $k => $l): ?><button type="button" role="radio" aria-checked="<?= $k === 'whatsapp' ? 'true' : 'false' ?>" class="<?= $k === 'whatsapp' ? 'on' : '' ?>" data-v="<?= $k ?>"><?= $l ?></button><?php endforeach; ?>
</div><input type="hidden" name="test_channel" value="whatsapp">
<div class="grid cols3 cs-g">
<label class="sl-field" data-cs-when="test_channel=whatsapp,sms"><span class="sl-lab">Phone number</span><input class="sl-in mono" name="test_phone" inputmode="tel" value="<?= h($d['me']['phone']) ?>" placeholder="+233 24 551 0921"></label>
<label class="sl-field" data-cs-when="test_channel=email" hidden><span class="sl-lab">E-mail address</span><input class="sl-in" name="test_email" type="email" value="<?= h($d['me']['email']) ?>"></label>
<label class="sl-field" data-cs-when="test_channel=sms" hidden><span class="sl-lab">Through</span><span class="sl-sel native"><select name="test_provider"><option value="">Default provider</option><?php foreach ($sms['providers'] as $k => $l): ?><option value="<?= h($k) ?>"><?= h($l) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
</div>
<div class="row g8 wrap"><button class="sl-btn" type="submit"><i class="ic ic-sm i-send" aria-hidden="true"></i>Send test</button><span class="sm" data-cs-out="test" role="status" aria-live="polite"></span></div>
</form>

</div></div>
<script>window.SL_COMMS_SETTINGS = <?= json_encode(['ajax' => $ajax, 'secrets' => $sec, 'king' => $kg['name']], JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
