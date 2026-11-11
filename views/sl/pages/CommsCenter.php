<?php
/**
 * CommsCenter - Communication Center (comms_center.php). Every pane is filled
 * by dataJs/comms.js from ajax/comms/comms_ajax.php; styles assets/swiftlane/ops.css.
 */
if (!isset($d)) { $d = []; }
$tabs = ['overview' => 'Overview', 'automations' => 'Automations', 'templates' => 'Templates', 'send' => 'Send message', 'campaigns' => 'Campaigns',
    'segments' => 'Segments', 'outbox' => 'Outbox', 'customers' => 'Customer history', 'surveys' => 'Surveys', 'deliveries' => 'Delivery bookings'];
if (!empty($d['isAdmin'])) { $tabs['settings'] = 'Settings'; }
?>
<div class="ops">
<div class="sl-head"><div><h1 class="sl-title" style="font-size:20px">Communication Center</h1><p class="sl-lede">WhatsApp, SMS and e-mail: automatic alerts, campaigns with consent, preferences and history</p></div>
<div class="row g8 wrap"><a class="sl-btn ghost" href="<?= h(sl_route('CommsInbox')) ?>"><i class="ic ic-sm i-message" aria-hidden="true"></i>WhatsApp Inbox</a><a class="sl-btn ghost" href="<?= h(sl_route('CommsHub')) ?>">Communications hub</a></div></div>
<?php if (empty($d['ready'])): ?><div class="swl-ops-banner is-danger">The communication tables could not be created. The database user needs the CREATE privilege once.</div><?php endif; ?>
<div class="swl-ops-tabs" role="tablist" aria-label="Communication Center sections">
<?php $first = true; foreach ($tabs as $k => $label): ?><button type="button" role="tab" id="cm_tab_<?= h($k) ?>" aria-controls="cm_pane_<?= h($k) ?>" aria-selected="<?= $first ? 'true' : 'false' ?>" data-tab="<?= h($k) ?>"><?= h($label) ?></button><?php $first = false; endforeach; ?>
</div>
<?php $first = true; foreach ($tabs as $k => $label): ?>
<section class="swl-ops-pane sl-card" role="tabpanel" id="cm_pane_<?= h($k) ?>" aria-labelledby="cm_tab_<?= h($k) ?>"<?= $first ? '' : ' hidden' ?>><div data-pane-body="<?= h($k) ?>"><p class="swl-ops-muted">Loading…</p></div></section>
<?php $first = false; endforeach; ?>
</div>
<script>window.CDP_COMMS = { isAdmin: <?= !empty($d['isAdmin']) ? 'true' : 'false' ?> };</script>
