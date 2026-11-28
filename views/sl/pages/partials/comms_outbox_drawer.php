<?php
/**
 * Message detail drawer shared by the Outbox (CommsOutbox) and Customer
 * history (CommsCustomers). Filled by dataJs/sl/comms_outbox.js from
 * ajax/comms/outbox_ajax.php?action=detail. Uses the .sl-dialog mechanics of
 * swiftlane.js (Esc, overlay click, focus trap, focus return).
 */
?>
<div class="sl-dialog ob-dlg" id="ob-dlg" hidden>
<div class="sl-modal ob-drawer" aria-labelledby="ob-d-title">
<header class="ob-dh">
<div class="ob-dh-t"><span class="ob-d-ch" id="ob-d-ch" aria-hidden="true"><i class="ic i-send"></i></span><div class="col"><h2 class="ob-d-title" id="ob-d-title">Message</h2><span class="mut sm" id="ob-d-sub"></span></div></div>
<div class="row g6"><a class="sl-btn ghost sm" id="ob-d-link" href="#" hidden title="Link to this message"><i class="ic ic-sm i-link" aria-hidden="true"></i><span class="sr-only">Link to this message</span></a><button type="button" class="sl-btn ghost sm" data-sl-close aria-label="Close"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div>
</header>
<div class="ob-db" id="ob-d-body" aria-live="polite"><div class="ob-load"><span class="ob-spin" aria-hidden="true"></span>Loading…</div></div>
<footer class="ob-df" id="ob-d-actions" hidden></footer>
</div>
</div>
