<?php
// ============================================================================
// Pickup Aging — list panel (no pop-up).
//
// Included by the Warehouse Panel (views/dashboard/dashboard_warehouse.php), the
// Packages Control Panel (views/dashboard/dashboard_admin_packages_customers.php)
// and the Pickup Aging page (views/reports/pickup_aging/pickup_aging.php).
// The page that includes it must also load dataJs/pickup_aging.js after
// views/inc/footer.php (SweetAlert2 + jQuery).
//
// Optional before the include:
//   $paLimit  rows to show (0 = all). Panels on dashboards show the oldest 25
//             and link to the full Pickup Aging page.
//   $paTitle  heading text.
//
// Notifying customers always goes through the typed-count confirmation in
// dataJs/pickup_aging.js and the server check in
// ajax/pickup_aging/pickup_aging_ajax.php.
// ============================================================================

if (!isset($user) || !$user->cdp_hasPermission('view_pickup_aging')) return;

require_once __DIR__ . '/../../../helpers/pickup_aging.php';

$paLimit = isset($paLimit) ? (int) $paLimit : 0;
$paTitle = isset($paTitle) ? (string) $paTitle : 'Pickup Ageing';

// Refresh the state machine (same scan the cron runs), then read the chain.
cdp_processPickupAging();

$paDb = new Conexion;
$paDb->cdp_query("
    SELECT p.order_id, p.order_track, p.ready_at, p.notified_at, p.not_picked_at, p.auction_at,
           DATEDIFF(NOW(), p.ready_at) AS days_ready,
           a.status_courier, s.mod_style, s.color,
           u.fname, u.lname, u.locker
    FROM cdb_package_pickup_aging p
    JOIN cdb_add_order a ON a.order_id = p.order_id
    LEFT JOIN cdb_styles s ON s.id = a.status_courier
    LEFT JOIN cdb_users  u ON u.id = a.sender_id   -- the package's current owner, not the ledger copy
    ORDER BY p.ready_at ASC");
$paDb->cdp_execute();
$paRows = (array) $paDb->cdp_registros();
$paTotal = count($paRows);
if ($paLimit > 0) $paRows = array_slice($paRows, 0, $paLimit);

// Only packages genuinely due the 14-day reminder can be ticked.
$paScope = cdp_pickupAgingScope(null);
$paDue = array_flip($paScope['ids']);
$paReadyDays = (int) CDP_PA_READY_DAYS;
?>
<style>
    .pa-panel .pa-badge { color:var(--white, #fff); padding:2px 8px; border-radius:10px; font-size:11px; font-weight:700; }
    .pa-panel .pa-stage { font-size:11px; font-weight:700; padding:2px 8px; border-radius:10px; white-space:nowrap; }
    .pa-panel .pa-stage-pending { background:var(--amber-surface, #fff4d6); color:var(--amber-500, #b7791f); }
    .pa-panel .pa-stage-notified { background:var(--sky-surface, #e0f2fe); color:var(--sky-500, #0284c7); }
    .pa-panel .pa-stage-notpicked { background:var(--fill-100, #f3f4f6); color:var(--red-500, #dc2626); }
    .pa-panel .pa-stage-auction { background:var(--red-500, #dc2626); color:var(--white, #fff); }
    .pa-panel .pa-stage-waiting { background:var(--line-200, #e5e7eb); color:var(--ink-800, #1f2937); }
    .pa-panel .pa-actions { display:flex; flex-wrap:wrap; gap:8px; align-items:center; justify-content:space-between; }
    .pa-panel .pa-danger-zone { border:1px dashed var(--red-500, #dc2626); border-radius:8px; padding:6px 10px; display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .pa-panel .pa-scroll { max-height:520px; overflow:auto; }
</style>
<div class="col-12 pa-panel" id="pickup-ageing">
    <div class="card card-outline" style="border-top:3px solid var(--red-500, #dc2626);">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
                <h4 class="card-title mb-0"><i class="mdi mdi-clock-alert-outline"></i> <?php echo htmlspecialchars($paTitle); ?></h4>
                <?php if ($paLimit > 0 && $paTotal > $paLimit): ?>
                    <a href="pickup_aging.php" class="btn btn-sm btn-outline-dark">See all <?php echo (int) $paTotal; ?></a>
                <?php endif; ?>
            </div>
            <p class="text-muted mb-3">
                Packages waiting for collection. A package at <b>Ready for PickUp</b> for
                <b><?php echo $paReadyDays; ?>+ days</b> can have its customer reminded (status &rarr;
                Pending Collection); a week later it moves to <b>Not Picked Up</b>, then to <b>Auction</b>.
                <b><?php echo (int) $paScope['packages']; ?></b> package(s) from
                <b><?php echo (int) $paScope['customers']; ?></b> customer(s) are due a reminder now.
            </p>

            <div class="pa-actions mb-3">
                <button type="button" class="btn btn-dark btn-sm pa-notify-selected" onclick="cdpPaNotifySelected(this)" disabled>
                    <i class="mdi mdi-bell-ring"></i> Notify customers of ticked packages (<span class="pa-selected-count">0</span>)
                </button>
                <?php if ($paScope['packages'] > 0): ?>
                <div class="pa-danger-zone">
                    <span class="small text-muted">Sends to every customer due a reminder. You will be asked to type the customer count.</span>
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="cdpPaNotifyAll()">
                        Notify all <?php echo (int) $paScope['customers']; ?> customer(s)&hellip;
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <div class="table-responsive pa-scroll">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr style="background:var(--page-050, #f9fafb);">
                            <th style="width:30px;"><?php if ($paScope['packages'] > 0): ?><input type="checkbox" class="pa-check-all" aria-label="Tick every package due a reminder"><?php endif; ?></th>
                            <th>Shipment</th>
                            <th>Customer</th>
                            <th>Current Status</th>
                            <th>Ready Since</th>
                            <th>Stage</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$paRows): ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">No packages are waiting for collection.</td></tr>
                    <?php else: foreach ($paRows as $r):
                        $due = isset($paDue[(int) $r->order_id]);
                        if ($r->auction_at !== null)        { $stage = '<span class="pa-stage pa-stage-auction">Auction</span>'; }
                        elseif ($r->not_picked_at !== null) { $stage = '<span class="pa-stage pa-stage-notpicked">Not Picked Up</span>'; }
                        elseif ($r->notified_at !== null)   { $stage = '<span class="pa-stage pa-stage-notified">Pending Collection</span>'; }
                        elseif ($due)                       { $stage = '<span class="pa-stage pa-stage-pending">Awaiting Reminder</span>'; }
                        else                                { $stage = '<span class="pa-stage pa-stage-waiting">Ready (' . (int) $r->days_ready . 'd)</span>'; }
                        $who = trim(($r->fname ?? '') . ' ' . ($r->lname ?? ''));
                        if ($r->locker) $who .= ' (' . $r->locker . ')';
                    ?>
                        <tr>
                            <td><?php if ($due): ?><input type="checkbox" class="pa-check" value="<?php echo (int) $r->order_id; ?>" aria-label="Tick <?php echo htmlspecialchars($r->order_track); ?>"><?php endif; ?></td>
                            <td><b><?php echo htmlspecialchars($r->order_track); ?></b></td>
                            <td><?php echo htmlspecialchars($who ?: 'N/A'); ?></td>
                            <td><span class="pa-badge" style="background:<?php echo htmlspecialchars($r->color ?: 'var(--slate-400, #94a3b8)'); ?>;"><?php echo htmlspecialchars($r->mod_style ?: '—'); ?></span></td>
                            <td><?php echo htmlspecialchars((string) $r->ready_at); ?> <span class="text-muted">(<?php echo (int) $r->days_ready; ?>d)</span></td>
                            <td><?php echo $stage; ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
