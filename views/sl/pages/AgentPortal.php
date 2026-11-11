<?php
/** AgentPortal - partner agent portal (agent_portal.php, dataJs/agent_portal.js). */
if (!isset($d)) { $d = []; }
?>
<div class="ops">
<div class="sl-head"><div><h1 class="sl-title" style="font-size:20px">Partner Portal</h1><p class="sl-lede" id="ap_lede">Your shipments, commission and statements</p></div>
<div class="row g8 wrap"><a class="sl-btn" href="courier_add.php"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New shipment</a><a class="sl-btn ghost" href="<?= h(sl_route('Workspace')) ?>">Workspace</a></div></div>
<?php if (empty($d['ready'])): ?><div class="swl-ops-banner is-danger">The staff tables could not be created.</div><?php endif; ?>
<div class="swl-ops-widgets" id="ap_body" data-agent="<?= (int) ($d['agentId'] ?? 0) ?>"><p class="swl-ops-muted w-full">Loading…</p></div>
</div>
