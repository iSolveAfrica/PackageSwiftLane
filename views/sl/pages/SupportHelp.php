<?php
/**
 * SupportHelp - the customer's Help & Support page (support.php), replacing views/support/hub.php.
 * Left: the chat with King (AI) or a person. Right: the customer's requests, earlier chats and help articles.
 * Everything talks to the unchanged ajax/support/customer_ajax.php (dataJs/sl/support_help.js):
 * bootstrap, poll, start, send, human, solved, upload, ticket, ticket_create, ticket_reply, rate, help, kb_vote.
 * The floating chat button (views/support/widget.php) stays hidden on this page, as before.
 */
$cfg = $d['cfg'];
?>
<div class="sl-head">
  <div><h1 class="sl-title">Help and support</h1><p class="sl-lede">Chat with <?= h($cfg['king']['on'] ? $cfg['king']['name'] : 'our team') ?>, follow your requests or search the help articles<?= $cfg['hours'] !== '' ? ' · Support hours: ' . h($cfg['hours']) : '' ?></p></div>
  <div class="row g8 wrap"><button class="sl-btn" type="button" data-sl-open="hp-new"><i class="ic ic-sm i-newdoc" aria-hidden="true"></i>New request</button></div>
</div>
<?php if (!$d['ready']): ?><div class="sl-banner bad"><i class="ic ic-sm i-alert" aria-hidden="true"></i>Support is not available right now. Please try again later.</div><?php endif; ?>
<div class="split">
  <section class="a sl-card col g12" id="hp-chat" aria-label="Chat" style="min-height:560px">
    <div class="row g10 between wrap">
      <div class="row g10"><span class="sl-av dark" id="hp-av" aria-hidden="true"><?= h(mb_substr($cfg['king']['name'], 0, 1)) ?></span><div class="col"><b class="fw5" id="hp-name"><?= h($cfg['king']['name']) ?></b><span class="mut sm" id="hp-state"></span></div></div>
      <div class="row g6"><button class="sl-btn ghost sm" type="button" id="hp-human"><i class="ic ic-sm i-user" aria-hidden="true"></i>Talk to a person</button><button class="sl-btn ghost sm" type="button" id="hp-newchat"><i class="ic ic-sm i-edit" aria-hidden="true"></i>New chat</button></div>
    </div>
    <div id="hp-scroll" style="flex:1 1 auto;min-height:320px;max-height:56vh;overflow:auto;border-top:1px solid var(--line);border-bottom:1px solid var(--line);padding:12px 0">
      <div class="col g10" id="hp-thread" role="log" aria-live="polite"></div>
      <div class="mut sm" id="hp-typing" hidden style="padding:6px 2px"></div>
    </div>
    <div class="row g6 wrap" id="hp-quick">
      <?php foreach ([['i-route', 'Track my shipment', 'Where is my shipment right now?'], ['i-box', 'Warehouse package', 'My package was delivered to your US warehouse but I cannot see it on my account.'], ['i-receipt', 'Invoice or payment', 'I have a question about an invoice or a payment.'], ['i-truck', 'Delivery issue', 'I have a problem with a delivery or pickup.'], ['i-shield', 'Lost or damaged', 'My package is lost or arrived damaged.']] as $q): ?>
      <button type="button" class="sl-btn ghost sm" data-hp-q="<?= h($q[2]) ?>"><i class="ic ic-sm <?= h($q[0]) ?>" aria-hidden="true"></i><?= h($q[1]) ?></button>
      <?php endforeach; ?>
    </div>
    <div class="row g6 wrap" id="hp-files"></div>
    <form class="row g8" id="hp-form" autocomplete="off">
      <label class="sl-ib" title="Attach a photo or PDF" style="cursor:pointer"><i class="ic ic-sm i-paperclip" aria-hidden="true"></i><span class="sr-only" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)">Attach a photo or PDF</span><input type="file" id="hp-file" accept="image/*,application/pdf,text/plain" hidden></label>
      <textarea class="sl-in grow" id="hp-text" rows="1" maxlength="4000" placeholder="Write a message…" aria-label="Message" style="resize:none;min-height:40px"></textarea>
      <button type="submit" class="sl-btn" id="hp-send"><i class="ic ic-sm i-send" aria-hidden="true"></i>Send</button>
    </form>
    <div class="row between wrap g8"><span class="mut sm" id="hp-disclose">Never share passwords, one-time codes or card numbers in chat.</span><button type="button" class="sl-btn ghost sm" id="hp-solved" hidden><i class="ic ic-sm i-check" aria-hidden="true"></i>This solved it</button></div>
  </section>
  <aside class="b sl-card col g12" data-sl-tabs>
    <nav class="sl-tabs" aria-label="Support"><a class="on" href="#" data-sl-tab="req">My requests <span class="mut" id="hp-req-n"></span></a><a href="#" data-sl-tab="chats">Chats</a><a href="#" data-sl-tab="help" id="hp-help-tab">Help</a></nav>
    <div data-sl-tabpanel="req" class="col g8" id="hp-reqs"><div class="sl-empty mut sm">Loading…</div></div>
    <div data-sl-tabpanel="chats" class="col g8" hidden><div id="hp-chats" class="col g8"></div></div>
    <div data-sl-tabpanel="help" class="col g8" hidden>
      <div class="sl-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input type="search" id="hp-help-q" placeholder="Search help, e.g. storage, invoice, warehouse" aria-label="Search help articles"></div>
      <div id="hp-help" class="col g8"></div>
    </div>
  </aside>
</div>

<div class="sl-dialog" id="hp-new" hidden><form class="sl-modal col g12" id="hp-new-form" role="dialog" aria-label="New request" autocomplete="off" style="max-width:640px">
  <div class="row between"><h2 class="sl-ct">New request</h2><button type="button" class="sl-ib" data-sl-close aria-label="Close"><i class="ic i-x" aria-hidden="true"></i></button></div>
  <div class="grid cols2" style="gap:12px">
    <label class="sl-field"><span class="sl-lab">What is it about?</span><span class="sl-sel native"><select name="category" id="hp-cat"></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
    <label class="sl-field"><span class="sl-lab">Related shipment or package (optional)</span><span class="sl-sel native"><select name="entity" id="hp-ent"><option value="">None</option></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
  </div>
  <label class="sl-field"><span class="sl-lab">Subject</span><input class="sl-in" name="subject" maxlength="190" required></label>
  <label class="sl-field"><span class="sl-lab">What happened, and what do you need?</span><textarea class="sl-in" name="description" rows="5" maxlength="4000" required></textarea></label>
  <label class="sl-field"><span class="sl-lab">Evidence (optional)</span><input class="sl-in" type="file" id="hp-new-file" accept="image/*,application/pdf"><span class="sl-note">A receipt, a photo of the package or a tracking screenshot helps us check faster. Photos or PDF, up to 8 MB each.</span></label>
  <div class="row g6 wrap" id="hp-new-files"></div>
  <span class="mut sm">Never include passwords, one-time codes or card numbers.</span>
  <div class="row g8"><button type="submit" class="sl-btn" id="hp-new-send"><i class="ic ic-sm i-send" aria-hidden="true"></i>Send request</button><button type="button" class="sl-btn ghost" data-sl-close>Cancel</button></div>
</form></div>

<div class="sl-dialog" id="hp-tk" hidden><div class="sl-modal col g12" role="dialog" aria-labelledby="hp-tk-title" style="max-width:720px">
  <div class="row between"><h2 class="sl-ct" id="hp-tk-title">Request</h2><button type="button" class="sl-ib" data-sl-close aria-label="Close"><i class="ic i-x" aria-hidden="true"></i></button></div>
  <div class="sl-kv" id="hp-tk-kv"></div>
  <div id="hp-tk-rate"></div>
  <div style="max-height:40vh;overflow:auto;border-top:1px solid var(--line);padding-top:10px" id="hp-tk-scroll"><div class="col g10" id="hp-tk-thread" role="log" aria-live="polite"></div></div>
  <form class="col g8" id="hp-tk-reply" autocomplete="off">
    <textarea class="sl-in" id="hp-tk-text" rows="3" maxlength="4000" placeholder="Write a reply…" aria-label="Your reply"></textarea>
    <div class="row g8 wrap"><input class="sl-in" type="file" id="hp-tk-file" accept="image/*,application/pdf" style="max-width:260px" aria-label="Attach a file"><span class="row g6 wrap" id="hp-tk-files"></span><span class="grow"></span><button type="submit" class="sl-btn"><i class="ic ic-sm i-reply" aria-hidden="true"></i>Send reply</button></div>
  </form>
</div></div>
<script>window.SL_HELP = <?= json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
