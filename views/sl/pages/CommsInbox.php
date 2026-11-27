<?php
/**
 * CommsInbox - WhatsApp Inbox, part of Communications (comms_inbox.php).
 * Customers' WhatsApp messages arrive through webhooks/whatsapp.php
 * (cdp_commHandleInbound); King answers first when switched on and hands over
 * to a person; staff answer here. List, conversation and actions are drawn by
 * dataJs/comms_inbox.js from ajax/comms/comms_ajax.php. Styles: pages/CommsInbox.css.
 */
if (!isset($d)) { $d = []; }
require_once CDP_ROOT . '/helpers/sl_nav.php';
$king = (string) ($d['king'] ?? 'King');
$c = $d['conn'] ?? null;
$isAdmin = !empty($d['isAdmin']);
$commsHome = sl_navAllowed(['push_notifications', 'manage_newsletter', 'view_message_logs'], []) ? sl_route('CommsHub') : sl_route('CommsCenter');
$settingsUrl = sl_route('CommsCenter') . '#settings';
$waSendUrl = 'config_whatsapp.php'; // Settings › General › Messaging: the UltraMsg instance used for sending
$noKey = $c && $c['provider'] !== 'cloud' && !$c['webhook_has_key'];
$waiting = function_exists('cdp_commWaitingCount') && !empty($d['ready']) ? cdp_commWaitingCount() : 0;
?>
<div class="ops ib-page">
<?php $commsTab = 'commsinbox'; include __DIR__ . '/partials/comms_tabs.php'; ?>
<nav class="ib-crumb" aria-label="Breadcrumb"><a href="<?= h($commsHome) ?>">Communications</a><i class="ic ic-sm i-right" aria-hidden="true"></i><span aria-current="page">WhatsApp Inbox</span></nav>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:22px">WhatsApp Inbox</h1>
<p class="sl-lede">Messages customers send to your WhatsApp number arrive here. <?= h($king) ?> answers first while switched on and hands the chat to a person; your team replies from this page.</p></div>
<?php if (empty($c) || $c['provider'] !== 'cloud'): ?><div class="row g8 wrap"><a class="sl-btn ghost sm" href="<?= h(sl_route('WhatsAppWeb')) ?>" title="Every chat and group of the company number, with QR linking, attachments and ticks"><i class="ic ic-sm i-phone" aria-hidden="true"></i>Open WhatsApp screen</a></div><?php endif; ?>
</div>

<?php if (empty($d['ready'])): ?>
<div class="sl-banner bad">The communication tables could not be created. The database user needs the CREATE privilege once.</div>
<?php elseif ($c): ?>
<section class="ib-strip" aria-label="WhatsApp connection">
  <span class="ib-st"><span class="ib-dot <?= $c['connected'] ? 'ok' : ($c['inbound'] || $c['outbound'] ? 'w' : 'e') ?>" aria-hidden="true"></span><b><?= $c['connected'] ? 'WhatsApp connected' : ($c['inbound'] || $c['outbound'] ? 'WhatsApp partly connected' : 'WhatsApp not connected') ?></b></span>
  <span class="ib-items">
    <span class="ib-st mut sm"><?= h($c['provider_label']) ?></span>
    <span class="ib-st"><span class="mut">Receiving</span> <?= $c['inbound'] ? sl_pill('Set up', 'delivered') : sl_pill('Not set up', 'pending') ?></span>
    <span class="ib-st"><span class="mut">Sending</span> <?= $c['outbound'] ? sl_pill('On', 'delivered') : sl_pill('Off', 'pending') ?></span>
    <span class="ib-st"><span class="mut"><?= h($king) ?></span> <?= $c['bot_on'] ? ($c['bot_ai_ok'] === false ? sl_pill('On, AI not set up', 'onway') : sl_pill('Answers first', 'delivered')) : sl_pill('Off: people answer', 'plain') ?></span>
  </span>
  <span class="ib-sa"><?php if (!$c['connected']): ?><button type="button" class="sl-btn ghost sm" data-sl-open="ib-setup"><?= $isAdmin ? 'How to connect' : 'What does this mean?' ?></button><?php endif; ?><?php if ($isAdmin): ?><a class="sl-btn ghost sm" href="<?= h($settingsUrl) ?>" aria-label="Communication Center settings" title="Communication Center settings"><i class="ic ic-sm i-cog" aria-hidden="true"></i></a><?php endif; ?></span>
</section>
<?php if (!$c['connected'] && $c['threads'] === 0): ?>
<section class="sl-card ib-empty-setup">
  <span class="ib-empty-ic" aria-hidden="true"><i class="ic i-message"></i></span>
  <div class="col g8 grow">
    <h2 class="sl-ct">No WhatsApp conversations yet</h2>
    <p class="mut">Conversations appear here once your WhatsApp number is connected: the provider forwards every customer message to this system, <?= h($king) ?> answers first when switched on, and anything it cannot handle waits here for a person.</p>
    <div class="row g8 wrap"><button type="button" class="sl-btn" data-sl-open="ib-setup"><?= $isAdmin ? 'Connect WhatsApp' : 'How it gets connected' ?></button><?php if ($isAdmin): ?><a class="sl-btn ghost" href="<?= h($settingsUrl) ?>">Open settings</a><?php endif; ?></div>
  </div>
</section>
<?php endif; ?>
<?php endif; ?>

<div class="ib-wrap<?= $c && !$c['connected'] && $c['threads'] === 0 ? ' is-empty' : '' ?>" id="ib_wrap" data-king="<?= h($king) ?>" data-waiting="<?= (int) $waiting ?>" data-provider="<?= h($c['provider'] ?? '') ?>">
  <section class="ib-list sl-card flush" aria-label="Conversations">
    <div class="ib-lt">
      <div class="ib-filters" role="group" aria-label="Show conversations">
        <?php foreach (['all' => 'All', 'open' => 'Waiting for a person', 'mine' => 'Assigned to me', 'bot' => 'With ' . $king, 'closed' => 'Closed'] as $k => $l): ?>
        <button type="button" aria-pressed="false" aria-controls="ib_threads" data-ib-filter="<?= h($k) ?>"><?= h($l) ?> <em data-ib-count="<?= h($k) ?>">·</em></button>
        <?php endforeach; ?>
      </div>
      <div class="sl-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input id="ib_q" type="search" aria-label="Search conversations" placeholder="Name, number, locker or text" autocomplete="off"></div>
    </div>
    <div class="ib-rows" id="ib_threads" aria-live="polite"><p class="ib-none">Loading conversations…</p></div>
  </section>
  <section class="ib-conv sl-card flush" id="ib_detail" aria-label="Conversation">
    <div class="ib-pick"><span class="ib-empty-ic" aria-hidden="true"><i class="ic i-message"></i></span><b>Choose a conversation</b><span class="mut sm">Pick one on the left to read it and reply.</span></div>
  </section>
</div>
</div>

<?php if ($c): ?>
<div class="sl-dialog" id="ib-setup" hidden><div class="sl-modal ib-modal" role="dialog" aria-modal="true" aria-labelledby="ib-setup-t">
  <div class="sl-ch"><h2 class="sl-ct" id="ib-setup-t">Connect WhatsApp to the inbox</h2><button type="button" class="sl-ib" data-sl-close aria-label="Close"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div>
  <div class="col g12">
    <p class="mut">Two things make the inbox work: the WhatsApp provider must <b>forward incoming messages</b> to this system (the webhook), and it must be able to <b>send</b> your replies.</p>
    <ul class="ib-check">
      <li class="<?= $c['inbound'] ? 'ok' : '' ?>"><span><b>Receiving messages:</b> <?= $c['inbound'] ? 'set up.' : 'not set up yet.' ?></span>
        <?php if ($c['provider'] === 'cloud'): ?><span class="mut">Meta Cloud API needs a verify token and the app secret, saved in <?= $isAdmin ? '<a href="' . h($settingsUrl) . '">Communication Center › Settings</a>' : 'Communication Center › Settings' ?>.</span><?php else: ?><span class="mut">UltraMsg needs the webhook address below, which carries a secret key.</span><?php endif; ?></li>
      <li class="<?= $c['outbound'] ? 'ok' : '' ?>"><span><b>Sending replies:</b> <?= $c['outbound'] ? 'on.' : 'off.' ?></span>
        <span class="mut"><?php if ($c['provider'] === 'cloud'): ?>Needs the phone number ID and access token in <?= $isAdmin ? '<a href="' . h($settingsUrl) . '">Communication Center › Settings</a>' : 'Communication Center › Settings' ?>.<?php else: ?>Needs WhatsApp switched on with the UltraMsg instance in <?= $isAdmin ? '<a href="' . h($waSendUrl) . '">Settings › General › Messaging</a>' : 'Settings › General › Messaging' ?>.<?php endif; ?></span></li>
      <li class="<?= $c['bot_on'] ? 'ok' : 'neutral' ?>"><span><b><?= h($king) ?>:</b> <?= $c['bot_on'] ? 'answers first.' : 'off, so every message waits for a person. That is a valid choice.' ?></span><span class="mut">Switch it in <?= $isAdmin ? '<a href="' . h($settingsUrl) . '">Communication Center › Settings</a>' : 'Communication Center › Settings' ?>.</span></li>
    </ul>
    <div class="col g6">
      <span class="sl-lab">Webhook address to give the provider</span>
      <?php if ($isAdmin): ?>
      <div class="row g8"><input class="sl-in mono" id="ib_hook" readonly value="<?= h($c['webhook_url']) ?>" aria-label="Webhook address"><button type="button" class="sl-btn ghost" data-ib-copy="ib_hook"<?= $noKey ? ' disabled title="Available once the secret key exists"' : '' ?>>Copy</button></div>
      <?php if ($noKey): ?><span class="ib-warn sm">Not complete yet: the address ends with <code>?key=</code> and a secret key, which is created the first time you save <a href="<?= h($settingsUrl) ?>">Communication Center › Settings</a>. Save the settings, then come back to copy the full address.</span><?php endif; ?>
      <span class="mut sm"><?= $c['provider'] === 'cloud' ? 'In Meta: WhatsApp › Configuration › Webhook: paste this address and the verify token, then subscribe to "messages".' : 'In UltraMsg: Instance › Settings › Webhook on received: paste this address. Keep it private: the key proves the messages come from your provider.' ?></span>
      <?php else: ?>
      <code class="ib-code"><?= h($c['webhook_url']) ?></code>
      <span class="mut sm">The full address ends with a secret key that only administrators can see. Ask an administrator to connect WhatsApp in Communication Center › Settings.</span>
      <?php endif; ?>
    </div>
  </div>
  <div class="row g8 wrap" style="justify-content:flex-end;margin-top:16px"><button type="button" class="sl-btn ghost" data-sl-close>Close</button><?php if ($isAdmin): ?><a class="sl-btn" href="<?= h($settingsUrl) ?>">Open settings</a><?php endif; ?></div>
</div></div>
<?php endif; ?>
