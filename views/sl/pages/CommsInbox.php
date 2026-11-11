<?php
/** CommsInbox - two-way WhatsApp support (comms_inbox.php, dataJs/comms_inbox.js). */
if (!isset($d)) { $d = []; }
?>
<div class="ops">
<div class="sl-head"><div><h1 class="sl-title" style="font-size:20px">WhatsApp Inbox</h1><p class="sl-lede">Conversations with customers. The AI assistant answers first when it is on and hands over to a person.</p></div>
<div class="row g8 wrap"><a class="sl-btn ghost" href="<?= h(sl_route('CommsCenter')) ?>">Communication Center</a></div></div>
<?php if (empty($d['ready'])): ?><div class="swl-ops-banner is-danger">The communication tables could not be created.</div><?php endif; ?>
<div class="swl-ops-inbox">
  <section class="sl-card">
    <div class="swl-ops-toolbar">
      <label for="ib_status" class="sr-only">Show</label>
      <select id="ib_status" class="custom-select"><option value="">All conversations</option><option value="open" selected>Waiting for a person</option><option value="mine">Assigned to me</option><option value="bot">Handled by the assistant</option><option value="closed">Closed</option></select>
      <label for="ib_q" class="sr-only">Search</label><input id="ib_q" type="search" class="form-control" placeholder="Name, number or text">
    </div>
    <div id="ib_counts" class="swl-ops-muted swl-ops-small mb-2"></div>
    <div class="swl-ops-threads" id="ib_threads" aria-live="polite"></div>
  </section>
  <section class="sl-card" id="ib_detail"><p class="swl-ops-muted">Choose a conversation.</p></section>
</div>
</div>
