<?php
/** Workspace - the signed-in person's role workspace (workspace.php, dataJs/workspace.js). */
if (!isset($d)) { $d = []; }
?>
<div class="ops">
<div class="sl-head"><div><h1 class="sl-title" style="font-size:20px" id="ws_title">My Workspace</h1><p class="sl-lede" id="ws_lede">Your shift, tasks and the work waiting for your role</p></div>
<div class="row g8 wrap" id="ws_clock"></div></div>
<?php if (empty($d['ready'])): ?><div class="swl-ops-banner is-danger">The staff tables could not be created.</div><?php endif; ?>
<div class="swl-ops-widgets" id="ws_panels"><p class="swl-ops-muted w-full">Loading…</p></div>
</div>
