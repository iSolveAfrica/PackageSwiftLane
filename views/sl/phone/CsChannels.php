<?php
/**
 * M-CsSettings: support channels and service settings on a phone. Same $d as the web page (sl_csChannelsData).
 * Administrators save through ajax/support/admin_ajax.php action=settings (notify_channels, business_hours,
 * sla_first_normal, auto_close_days), exactly like the web form; everyone else sees the values read-only.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['scripts'][] = 'dataJs/sl/cs_common.js';
$slPhone['scripts'][] = 'dataJs/sl/phone/cs_desk.js';
$admin = !empty($d['isAdmin']);
$s = $d['settings'] ?? [];
$nc = array_filter(explode(',', (string) ($s['notify_channels'] ?? 'email')));
$health = $d['health'] ?? [];
$hint = ['email' => 'E-mail · outgoing updates', 'whatsapp' => 'WhatsApp · outgoing updates', 'sms' => 'SMS · outgoing updates'];
$detail = [];
foreach ($health as $hr) $detail[$hr['label']] = $hr['detail'];
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-desk.css')) ?>">
<?= sl_phoneTop('Support settings', '', ['back' => sl_route('CsInbox')], sl_phoneIcon('filter', 'Ticket rules', sl_route('CsRules'))) ?>
<main class="mx-body">
<?php if (!$d['ready']): ?><?= sl_mxEmpty('cog', 'Helpdesk not installed', 'Ask an administrator to run the support migration.') ?><?php else: ?>
<?= sl_mxSec('Channels') ?>
<section class="mx-card mx-tgs">
<?php foreach (['whatsapp' => 'WhatsApp', 'email' => 'Email', 'sms' => 'SMS'] as $k => $l): $on = in_array($k, $nc, true); ?>
<div class="mx-tg"><span class="mx-rm"><b><?= h($l) ?></b><small>Send copies of replies · <?= h($detail[$hint[$k]] ?? '') ?></small></span><button type="button" class="mx-sw<?= $on ? ' on' : '' ?>" role="switch" aria-checked="<?= $on ? 'true' : 'false' ?>" aria-label="Send copies of replies by <?= h($l) ?>" data-pd-notify="<?= h($k) ?>"<?= $admin ? '' : ' disabled aria-disabled="true"' ?>></button></div>
<?php endforeach; ?>
</section>
<section class="mx-card mx-list">
<?php foreach ($health as $hr): if (preg_match('/outgoing/', $hr['label'])) continue; ?>
<div class="mx-row"><span class="mx-li"><i class="ic i-<?= h($hr['icon']) ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($hr['label']) ?></b><small><?= h($hr['detail']) ?></small></span><span class="mx-rr"><span class="sl-pill <?= h($hr['pill']) ?>"><?= h(ucfirst(strtolower($hr['state']))) ?></span></span></div>
<?php endforeach; ?>
</section>
<?php if ($admin): ?>
<form class="mx-card mx-form" data-cs-form="settings" data-cs-url="ajax/support/admin_ajax.php" data-cs-reload>
  <label class="pd-f"><small>Opening hours</small><input class="pd-in" name="business_hours" value="<?= h($s['business_hours'] ?? '') ?>" placeholder="Mon–Fri 08:00–18:00" autocomplete="off"></label>
  <label class="pd-f"><small>Reply target (minutes, normal priority)</small><input class="pd-in" type="number" inputmode="numeric" min="1" name="sla_first_normal" value="<?= h($s['sla_first_normal'] ?? '') ?>"></label>
  <label class="pd-f"><small>Close resolved tickets after (days, 0 = never)</small><input class="pd-in" type="number" inputmode="numeric" min="0" name="auto_close_days" value="<?= h($s['auto_close_days'] ?? '0') ?>"></label>
  <button type="submit" class="mx-btn">Save</button>
</form>
<?php else: ?>
<section class="mx-card mx-kv"><div><span>Opening hours</span><b><?= h(($s['business_hours'] ?? '') ?: '—') ?></b></div><div><span>Reply target</span><b><?= h(($s['sla_first_normal'] ?? '') ?: '—') ?> min</b></div><div><span>Auto-close</span><b><?= (int) ($s['auto_close_days'] ?? 0) ? (int) $s['auto_close_days'] . ' days' : 'Never' ?></b></div></section>
<?php endif; ?>
<?php if ($d['teamsOut']): ?>
<?= sl_mxSec('Teams') ?>
<section class="mx-card mx-list"><?php foreach ($d['teamsOut'] as $tm): ?><a class="mx-row" href="<?= h($tm['href']) ?>"><span class="mx-li"><i class="ic i-users" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($tm['name']) ?></b><small><?= h($tm['desc']) ?></small></span><span class="mx-cnt"><?= h($tm['n']) ?></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a><?php endforeach; ?></section>
<?php endif; ?>
<?php endif; ?>
</main>
