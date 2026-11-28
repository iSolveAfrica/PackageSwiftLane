<?php
/**
 * WhatsAppWeb - the company's WhatsApp number inside the app (whatsapp_web.php).
 * Three panes like WhatsApp Web: chats (search and filters), the conversation
 * (bubbles, media, ticks, composer with attachments, quick replies and King)
 * and the details beside it (customer, shipments, balance, assign, close, King,
 * tags, ticket, group members and exports). A connection screen with the QR
 * code shows until the phone is linked. Drawn by dataJs/sl/whatsapp_web.js from
 * ajax/comms/wa_web_ajax.php. Styles: pages/WhatsAppWeb.css.
 */
if (!isset($d)) { $d = []; }
$king = (string) ($d['king'] ?? 'King');
$isAdmin = !empty($d['isAdmin']);
$ai = (array) ($d['ai'] ?? []);
$u = (array) ($d['urls'] ?? []);
$cfg = [
    'ajax' => $u['ajax'] ?? 'ajax/comms/wa_web_ajax.php', 'admin' => $isAdmin, 'king' => $king, 'me' => (int) ($d['me'] ?? 0),
    'ai' => ['ok' => !empty($ai['ok']), 'code' => (string) ($ai['code'] ?? ''), 'message' => (string) ($ai['message'] ?? ''), 'setup' => (string) ($ai['setup_url'] ?? '')],
    'bot' => !empty($d['botEnabled']), 'configured' => !empty($d['configured']), 'active' => !empty($d['active']),
    'urls' => ['inbox' => $u['inbox'] ?? '', 'send' => $u['send'] ?? '', 'settings' => $u['settings'] ?? '', 'messaging' => $u['messaging'] ?? ''],
];
?>
<div class="ops ww-page">
<?php $commsTab = 'commswaweb'; include __DIR__ . '/partials/comms_tabs.php'; ?>
<nav class="ww-crumb" aria-label="Breadcrumb"><span class="ww-crumb-home"><a href="<?= h($u['home'] ?? '') ?>">Communications</a><i class="ic ic-sm i-right" aria-hidden="true"></i></span><a href="<?= h($u['inbox'] ?? '') ?>">WhatsApp Inbox</a><i class="ic ic-sm i-right" aria-hidden="true"></i><span aria-current="page">WhatsApp screen</span></nav>
<div class="sl-head ww-head"><div><h1 class="sl-title" style="font-size:22px">WhatsApp screen</h1>
<p class="sl-lede">Your company WhatsApp number inside Swift Lane: every chat and group with its history, replies with pictures and documents, ticks, and the customer beside each chat.</p></div>
<div class="row g8 wrap"><a class="sl-btn ghost sm" href="<?= h($u['inbox'] ?? '') ?>"><i class="ic ic-sm i-inbox" aria-hidden="true"></i>WhatsApp Inbox</a></div>
</div>

<?php if (empty($d['ready'])): ?>
<div class="sl-banner bad">The WhatsApp tables could not be created. The database user needs the CREATE privilege once.</div>
<?php else: ?>
<div class="ww" id="ww" data-cfg="<?= h(json_encode($cfg, JSON_UNESCAPED_SLASHES)) ?>">
  <p class="ww-note" id="ww_note" role="status" hidden></p>

  <section class="ww-conn sl-card" id="ww_conn" aria-live="polite" hidden>
    <div class="ww-load"><span class="ww-spin" aria-hidden="true"></span><span>Checking the WhatsApp connection…</span></div>
  </section>

  <div class="ww-app" id="ww_app" hidden>
    <aside class="ww-side" aria-label="Chats">
      <header class="ww-sh">
        <span class="ww-av me" id="ww_me_av" aria-hidden="true"><i class="ic i-phone"></i></span>
        <div class="ww-mi"><b id="ww_me_name">WhatsApp</b><span id="ww_me_sub" class="mut sm"></span></div>
        <div class="ww-sa">
          <button type="button" class="ww-ib" data-ww="refresh" aria-label="Refresh chats" title="Refresh chats"><i class="ic ic-sm i-refresh" aria-hidden="true"></i></button>
          <?php if ($isAdmin): ?><button type="button" class="ww-ib" data-ww="contacts" aria-label="Contacts and exports" title="Contacts and exports"><i class="ic ic-sm i-users" aria-hidden="true"></i></button><?php endif; ?>
          <button type="button" class="ww-ib" data-ww="connection" aria-label="Connection" title="Connection"><span class="ww-dot" id="ww_dot" aria-hidden="true"></span><i class="ic ic-sm i-cog" aria-hidden="true"></i></button>
        </div>
      </header>
      <div class="ww-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input id="ww_q" type="search" aria-label="Search chats" placeholder="Search name, number, locker, tag or text" autocomplete="off"></div>
      <div class="ww-filters" role="group" aria-label="Show chats">
        <?php foreach (['all' => 'All', 'unread' => 'Unread', 'groups' => 'Groups', 'customers' => 'Customers', 'mine' => 'Assigned to me'] as $k => $l): ?>
        <button type="button" aria-pressed="<?= $k === 'all' ? 'true' : 'false' ?>" data-ww-filter="<?= h($k) ?>"><?= h($l) ?> <em data-ww-count="<?= h($k) ?>"></em></button>
        <?php endforeach; ?>
      </div>
      <div class="ww-list" id="ww_list" aria-live="polite"><p class="ww-none">Loading chats…</p></div>
    </aside>
    <section class="ww-main" id="ww_main" aria-label="Conversation">
      <div class="ww-pick"><span class="ww-pick-ic" aria-hidden="true"><i class="ic i-message"></i></span><b>WhatsApp, inside Swift Lane</b><span class="mut">Pick a chat on the left to read it and reply. New messages appear here as they arrive.</span></div>
    </section>
    <aside class="ww-panel" id="ww_panel" aria-label="Chat details" hidden></aside>
  </div>
</div>

<div class="sl-dialog" id="ww_dlg_conn" hidden><div class="sl-modal ww-modal" aria-labelledby="ww_dlg_conn_t">
  <div class="sl-ch"><h2 class="sl-ct" id="ww_dlg_conn_t">WhatsApp connection</h2><button type="button" class="sl-ib" data-sl-close aria-label="Close"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div>
  <div id="ww_conn_body" class="col g12"></div>
</div></div>

<div class="sl-dialog" id="ww_dlg_ticket" hidden><div class="sl-modal ww-modal" aria-labelledby="ww_dlg_ticket_t">
  <div class="sl-ch"><h2 class="sl-ct" id="ww_dlg_ticket_t">Open a support ticket</h2><button type="button" class="sl-ib" data-sl-close aria-label="Close"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div>
  <form id="ww_ticket_form" class="col g12" novalidate>
    <p class="mut sm" id="ww_ticket_who"></p>
    <label class="sl-field"><span class="sl-lab">Subject</span><input class="sl-in" name="subject" maxlength="190" required></label>
    <label class="sl-field"><span class="sl-lab">Priority</span><select class="sl-in" name="priority"><option value="low">Low</option><option value="normal" selected>Normal</option><option value="high">High</option><option value="urgent">Urgent</option></select></label>
    <label class="sl-field"><span class="sl-lab">Note for the team <span class="mut">(optional)</span></span><textarea class="sl-in ww-ta" name="note" rows="3" maxlength="2000"></textarea></label>
    <p class="mut sm">The last 15 messages of the chat are copied into the ticket. It is assigned to you.</p>
    <div class="row g8 wrap ww-dlg-actions"><button type="button" class="sl-btn ghost" data-sl-close>Cancel</button><button type="submit" class="sl-btn">Open ticket</button></div>
  </form>
</div></div>

<div class="sl-dialog" id="ww_dlg_qr" hidden><div class="sl-modal ww-modal" aria-labelledby="ww_dlg_qr_t">
  <div class="sl-ch"><h2 class="sl-ct" id="ww_dlg_qr_t">Quick replies</h2><button type="button" class="sl-ib" data-sl-close aria-label="Close"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div>
  <p class="mut sm">Short answers your team sends often. <code>{first_name}</code>, <code>{name}</code>, <code>{locker}</code>, <code>{balance}</code> and <code>{tracking}</code> are filled from the customer of the chat; <code>{hours}</code> from the business hours saved in the Support desk settings.</p>
  <div id="ww_qr_rows" class="col g8"></div>
  <div class="row g8 wrap ww-dlg-actions"><button type="button" class="sl-btn ghost" data-ww="qr-add"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add a reply</button><button type="button" class="sl-btn ghost" data-ww="qr-reset"><i class="ic ic-sm i-refresh" aria-hidden="true"></i>Restore defaults</button><span class="grow"></span><button type="button" class="sl-btn ghost" data-sl-close>Cancel</button><button type="button" class="sl-btn" data-ww="qr-save">Save</button></div>
</div></div>

<?php if ($isAdmin): ?>
<div class="sl-dialog" id="ww_dlg_contacts" hidden><div class="sl-modal ww-modal" aria-labelledby="ww_dlg_contacts_t">
  <div class="sl-ch"><h2 class="sl-ct" id="ww_dlg_contacts_t">Contacts and exports</h2><button type="button" class="sl-ib" data-sl-close aria-label="Close"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div>
  <div id="ww_contacts_body" class="col g12"><p class="mut">Loading…</p></div>
</div></div>
<?php endif; ?>
<?php endif; ?>
</div>
