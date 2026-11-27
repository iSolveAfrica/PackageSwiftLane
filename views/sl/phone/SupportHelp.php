<?php
/**
 * M-CSupport: customer help and chat (support.php). Same element ids as views/sl/pages/SupportHelp.php, so the
 * unchanged dataJs/sl/support_help.js drives it against ajax/support/customer_ajax.php (chat with King or a person,
 * requests, earlier chats, help articles, new request, one request). The .mx-body is the chat scroller (#hp-scroll).
 */
$cfg = (array) ($d['cfg'] ?? []);
$king = (array) ($cfg['king'] ?? ['on' => false, 'name' => 'King']);
$kName = (string) ($king['name'] ?? 'King');
$slPhone['nav'] = 'none';
$slPhone['scripts'][] = 'dataJs/sl/support_help.js';
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-cust.css')) ?>">
<header class="mx-top"><a class="mx-ib" href="<?= h(sl_route('Main')) ?>" data-mx-back aria-label="Back"><i class="ic i-left" aria-hidden="true"></i></a><div class="mx-ttl"><h1>Help and support</h1><small id="hp-state"><?= !empty($king['on']) ? h($kName) . ' · a person takes over any time' : 'Our team replies here' ?></small></div><button type="button" class="mx-ib" data-sl-open="hp-new" aria-label="New request"><i class="ic i-newdoc" aria-hidden="true"></i></button></header>
<main class="mx-body" id="hp-scroll" data-sl-tabs>
<nav class="b3-ls" aria-label="Sections" role="tablist"><a class="on" href="#chat" data-sl-tab="chat" role="tab" aria-selected="true">Chat</a><a href="#requests" data-sl-tab="req" role="tab" aria-selected="false">My requests <span id="hp-req-n"></span></a><a href="#help" data-sl-tab="help" id="hp-help-tab" role="tab" aria-selected="false">Help</a></nav>
<?php if (empty($d['ready'])): ?><div class="mx-ban bad"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>Support is not available right now. Please try again later.</span></div><?php endif; ?>

<div data-sl-tabpanel="chat" class="pc-panel">
<div class="b3-row2"><button type="button" class="b3-gh" id="hp-newchat"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New chat</button><button type="button" class="b3-gh" id="hp-human"><i class="ic ic-sm i-user" aria-hidden="true"></i>Talk to a person</button></div>
<div class="b3-pl" id="hp-quick">
<?php foreach ([['i-route', 'Track my shipment', 'Where is my shipment right now?'], ['i-warehouse', 'Warehouse package', 'My package was delivered to your US warehouse but I cannot see it on my account.'], ['i-receipt', 'Invoice or payment', 'I have a question about an invoice or a payment.'], ['i-truck', 'Delivery issue', 'I have a problem with a delivery or pickup.'], ['i-alert', 'Lost or damaged', 'My package is lost or arrived damaged.']] as $q): ?>
<button type="button" data-hp-q="<?= h($q[2]) ?>"><i class="ic ic-sm <?= h($q[0]) ?>" aria-hidden="true"></i><?= h($q[1]) ?></button>
<?php endforeach; ?>
</div>
<div class="mx-chan"><span id="hp-av" hidden><?= h(mb_substr($kName, 0, 1)) ?></span><span id="hp-name"><?= h($kName) ?></span></div>
<section class="mx-chat pc-chat" id="hp-thread" role="log" aria-live="polite"></section>
<div class="mx-msg ai" id="hp-typing" hidden></div>
<div class="b3-solv"><button type="button" class="b3-cta" id="hp-solved" hidden><i class="ic ic-sm i-check" aria-hidden="true"></i>This solved it</button><a class="b3-lk" href="#new" data-sl-open="hp-new">Open a ticket<i class="ic ic-sm i-right" aria-hidden="true"></i></a></div>
<p class="b3-cap"><span id="hp-disclose">Never share passwords, one-time codes or card numbers in chat.</span><?= !empty($cfg['hours']) ? ' Support hours: ' . h($cfg['hours']) . '.' : '' ?> Photos and PDFs up to 8 MB.</p>
</div>

<div data-sl-tabpanel="req" class="pc-panel" hidden>
<div id="hp-reqs" class="pc-list"><div class="mxp-empty"><b>Loading…</b></div></div>
<div class="mx-sec"><h2>Earlier chats</h2></div>
<section class="mx-card pc-pad"><div id="hp-chats" class="pc-list"></div></section>
<button type="button" class="b3-gh" data-sl-open="hp-new"><i class="ic ic-sm i-newdoc" aria-hidden="true"></i>New request</button>
</div>

<div data-sl-tabpanel="help" class="pc-panel" hidden>
<div class="mx-srch"><label><i class="ic i-search" aria-hidden="true"></i><input type="search" id="hp-help-q" placeholder="Search help, e.g. storage, invoice" aria-label="Search help articles"></label></div>
<div id="hp-help" class="pc-list"></div>
</div>
</main>
<div class="mx-foot"><div id="hp-files" class="pc-files"></div><form id="hp-form" autocomplete="off"><div class="mx-comp"><label class="mx-ib" aria-label="Attach a photo or PDF" style="cursor:pointer"><i class="ic i-paperclip" aria-hidden="true"></i><input type="file" id="hp-file" accept="image/*,application/pdf,text/plain" hidden></label><textarea id="hp-text" rows="1" maxlength="4000" placeholder="Ask a question" aria-label="Message" class="pc-comp"></textarea><button type="submit" class="mx-send" id="hp-send" aria-label="Send"><i class="ic i-send" aria-hidden="true"></i></button></div></form></div>

<div class="sl-dialog" id="hp-new" hidden><form class="sl-modal col g12" id="hp-new-form" role="dialog" aria-label="New request" autocomplete="off">
  <div class="row between"><h2 class="sl-ct">New request</h2><button type="button" class="mx-ib" data-sl-close aria-label="Close"><i class="ic i-x" aria-hidden="true"></i></button></div>
  <label class="mx-f"><small>What is it about?</small><select class="mx-in mx-inp" name="category" id="hp-cat"></select></label>
  <label class="mx-f"><small>Related shipment or package (optional)</small><select class="mx-in mx-inp" name="entity" id="hp-ent"><option value="">None</option></select></label>
  <label class="mx-f"><small>Subject</small><input class="mx-in mx-inp" name="subject" maxlength="190" required></label>
  <label class="mx-f"><small>What happened, and what do you need?</small><textarea class="mx-in mx-inp pc-ta" name="description" rows="4" maxlength="4000" required></textarea></label>
  <label class="mx-f"><small>Evidence (optional)</small><input class="mx-in mx-inp" type="file" id="hp-new-file" accept="image/*,application/pdf"><small class="mx-fh">A receipt, a photo or a tracking screenshot helps. Photos or PDF, up to 8 MB.</small></label>
  <div class="pc-files" id="hp-new-files"></div>
  <small class="mx-fh">Never include passwords, one-time codes or card numbers.</small>
  <div class="b3-row2"><button type="button" class="b3-gh" data-sl-close>Cancel</button><button type="submit" class="b3-cta" id="hp-new-send"><i class="ic ic-sm i-send" aria-hidden="true"></i>Send request</button></div>
</form></div>

<div class="sl-dialog" id="hp-tk" hidden><div class="sl-modal col g12" role="dialog" aria-labelledby="hp-tk-title">
  <div class="row between"><h2 class="sl-ct" id="hp-tk-title">Request</h2><button type="button" class="mx-ib" data-sl-close aria-label="Close"><i class="ic i-x" aria-hidden="true"></i></button></div>
  <div class="sl-kv" id="hp-tk-kv"></div>
  <div id="hp-tk-rate"></div>
  <div style="max-height:40vh;overflow:auto;border-top:1px solid var(--line);padding-top:10px" id="hp-tk-scroll"><div class="mx-chat pc-chat" id="hp-tk-thread" role="log" aria-live="polite"></div></div>
  <form class="col g8" id="hp-tk-reply" autocomplete="off">
    <textarea class="mx-in mx-inp pc-ta" id="hp-tk-text" rows="3" maxlength="4000" placeholder="Write a reply…" aria-label="Your reply"></textarea>
    <input class="mx-in mx-inp" type="file" id="hp-tk-file" accept="image/*,application/pdf" aria-label="Attach a file"><span class="pc-files" id="hp-tk-files"></span>
    <button type="submit" class="b3-cta"><i class="ic ic-sm i-reply" aria-hidden="true"></i>Send reply</button>
  </form>
</div></div>
<script>window.SL_HELP = <?= json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
