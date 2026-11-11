<?php
/**
 * MasterView - Gerald Master View (master_view.php). Widgets are drawn by
 * dataJs/master_view.js in the order this user chose; figures, exports and
 * scheduled reports all come from cdp_mgmtData() in helpers/mgmt.php.
 */
if (!isset($d)) { $d = []; }
?>
<div class="ops">
<div class="sl-head"><div><h1 class="sl-title" style="font-size:20px">Gerald Master View</h1><p class="sl-lede">The whole business on one page, compared with the period before</p></div>
<div class="row g8 wrap">
  <button type="button" class="sl-btn ghost" id="mv_customise"><i class="ic ic-sm i-sliders" aria-hidden="true"></i>Customise</button>
  <button type="button" class="sl-btn ghost mv-export" data-format="pdf"><i class="ic ic-sm i-download" aria-hidden="true"></i>PDF</button>
  <button type="button" class="sl-btn ghost mv-export" data-format="xlsx">Excel</button>
  <button type="button" class="sl-btn ghost mv-export" data-format="csv">CSV</button>
</div></div>
<?php if (empty($d['ready'])): ?><div class="swl-ops-banner is-danger">The Master View tables could not be created. The database user needs the CREATE privilege once.</div><?php endif; ?>
<section class="sl-card">
  <form class="swl-ops-toolbar mb-0" id="mv_filters" autocomplete="off">
    <label for="mv_range" class="sr-only">Period</label>
    <select id="mv_range" class="custom-select"><option value="7">Last 7 days</option><option value="30" selected>Last 30 days</option><option value="90">Last 90 days</option><option value="month">This month</option><option value="lastmonth">Last month</option><option value="year">This year</option><option value="custom">Custom…</option></select>
    <label for="mv_from" class="sr-only">From</label><input type="date" id="mv_from" class="form-control" hidden>
    <label for="mv_to" class="sr-only">To</label><input type="date" id="mv_to" class="form-control" hidden>
    <label for="mv_branch" class="sr-only">Branch</label><select id="mv_branch" class="custom-select"><option value="0"><?= !empty($d['isAdmin']) ? 'All branches' : 'My branches' ?></option></select>
    <span class="swl-ops-muted swl-ops-small" id="mv_period"></span>
  </form>
</section>
<section class="sl-card" id="mv_custom_panel" hidden>
  <h2 class="card-title">Customise your dashboard</h2>
  <p class="swl-ops-muted">Show, hide and reorder the panels. The layout is saved for you only.</p>
  <ul class="swl-ops-list" id="mv_custom_list"></ul>
  <div class="row g8 mt-2"><button type="button" class="btn" id="mv_custom_save">Save layout</button><button type="button" class="btn btn-secondary" id="mv_custom_reset">Reset</button></div>
</section>
<div class="swl-ops-widgets" id="mv_widgets"><p class="swl-ops-muted w-full">Loading…</p></div>
<?php if (!empty($d['isAdmin'])): ?>
<h2 class="sl-ct">Scheduled reports and settings</h2>
<div class="swl-ops-widgets"><section class="sl-card w-half" id="mv_schedules"></section><section class="sl-card w-half" id="mv_settings"></section></div>
<?php endif; ?>
</div>
<script>window.CDP_MV = { isAdmin: <?= !empty($d['isAdmin']) ? 'true' : 'false' ?>, currency: <?= json_encode((string) ($d['currency'] ?? '')) ?> };</script>
