<?php
/**
 * M-CommsCenter for comms_center.php. The design's segmented list, filled with what this Communication Center holds:
 * campaigns, the outbox and the automatic messages (read from helpers/comms.php). Editing automations, segments and
 * settings stays on the full page (every pane of the web page is a JavaScript editor).
 */
require_once CDP_ROOT . '/views/sl/data/phone_msg.php';
require_once CDP_ROOT . '/helpers/sl_mx.php';
$C = !empty($d['ready']) ? sl_pmCenter() : ['campaigns' => [], 'outbox' => [], 'autos' => [], 'enabled' => false];
$full = sl_url(['view' => 'web']);
$stPill = ['sent' => 'delivered', 'done' => 'delivered', 'failed' => 'overdue', 'queued' => 'pending', 'scheduled' => 'pending', 'draft' => 'plain', 'cancelled' => 'plain', 'skipped' => 'plain', 'sending' => 'onway'];
$chIcon = ['whatsapp' => 'message', 'sms' => 'phone', 'email' => 'mail'];
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-msg.css')) ?>">
<?= sl_phoneTop('Communication center', $C['enabled'] ? 'Automatic messages on' : 'Automatic messages off', ['back' => 'menu.php'], sl_phoneIcon('more', 'Open the full page', $full)) ?>
<main class="mx-body">
<?php if (empty($d['ready'])): ?><div class="mx-ban warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>The communication tables could not be created.</span></div><?php endif; ?>
<div class="mx-seg" role="tablist" data-mx-seg><button type="button" role="tab" class="on" aria-pressed="true" data-mx-panel="cm">Campaigns<?= $C['campaigns'] ? ' ' . count($C['campaigns']) : '' ?></button><button type="button" role="tab" aria-pressed="false" data-mx-panel="ob">Outbox</button><button type="button" role="tab" aria-pressed="false" data-mx-panel="au">Automations</button></div>
<div data-mx-panel-of="cm">
<?php if ($C['campaigns']): ?><section class="mx-card mx-list"><?php foreach ($C['campaigns'] as $c): ?>
<a class="mx-row" href="<?= h($full . '#campaigns') ?>"><span class="mx-ava"><?= h(sl_initials((string) $c->name)) ?></span><span class="mx-rm"><b><?= h($c->name) ?></b><small><?= h(ucwords(str_replace(',', ' · ', (string) $c->channels))) ?><?= $c->segment_name ? ' · ' . h($c->segment_name) : '' ?> · <?= (int) $c->sent_count ?> sent<?= (int) $c->failed_count ? ' · ' . (int) $c->failed_count . ' failed' : '' ?></small></span><span class="mx-rr"><?= sl_pill(ucfirst((string) $c->status), $stPill[$c->status] ?? 'plain') ?><small><?= h(sl_ago($c->updated_at ?: $c->created_at)) ?></small></span></a>
<?php endforeach; ?></section>
<?php else: ?><?= sl_mxEmpty('send', 'No campaigns yet', 'Campaigns to a segment, with consent, are written on the full page.') ?><?php endif; ?>
</div>
<div data-mx-panel-of="ob" hidden>
<?php if ($C['outbox']): ?><section class="mx-card mx-list"><?php foreach ($C['outbox'] as $o): ?>
<?= sl_mxRow($chIcon[$o->channel] ?? 'send', (string) ($o->subject ?: str_replace('_', ' ', (string) ($o->event_key ?: $o->kind))), $o->recipient_to . ($o->status === 'failed' && $o->last_error ? ' · ' . mb_substr((string) $o->last_error, 0, 60) : ''), sl_pill(ucfirst((string) $o->status), $stPill[$o->status] ?? 'plain') . '<small>' . h(sl_ago($o->created_at)) . '</small>') ?>
<?php endforeach; ?></section>
<?php else: ?><?= sl_mxEmpty('archive', 'The outbox is empty', 'Automatic messages and campaigns queue here before they go out.') ?><?php endif; ?>
</div>
<div data-mx-panel-of="au" hidden>
<section class="mx-card mx-list"><?php foreach ($C['autos'] as $a): ?>
<div class="mx-row"><span class="mx-li"><i class="ic i-bolt" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($a['label']) ?></b><small><?= $a['on'] ? h(implode(' · ', array_map(fn($x) => ['whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'email' => 'Email'][$x] ?? $x, $a['on']))) : 'No channel' ?></small></span><span class="mx-rr"><?= $a['on'] && $C['enabled'] ? sl_pill('On', 'delivered') : sl_pill('Off', 'plain') ?></span></div>
<?php endforeach; ?></section>
<p class="mx-note">Automations, segments and settings are changed on the full page.</p>
</div>
<div class="mx-btns">
<?php if ($user->cdp_hasPermission('push_notifications')): ?><a class="mx-btn" href="<?= h(sl_route('ComposeMessage')) ?>">New message</a><?php endif; ?>
<a class="mx-btn sec" href="<?= h(sl_route('CommsInbox')) ?>">WhatsApp inbox</a>
</div>
</main>
