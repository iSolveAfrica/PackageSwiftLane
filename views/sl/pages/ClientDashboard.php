<?php
/**
 * ClientDashboard - from docs/handover/artifact-source/ClientDashboard.dc.html (scripts/sl_convert.py), bound to index.php (client branch).
 * Shell page: yes
 * Data contract ($d): see sl_clientDashboardData() in views/sl/data/portal.php
 *   first, locker, ready, active, year, finance, dueMoney, dueN, dueDate, duePct, atWh, addresses[], rows[], latest, tl[], latestSteps, aiOn, canShip, canPrealert
 */
if (!isset($d)) { $d = []; }
$latest = sl_get($d, 'latest');
$steps = sl_get($d, 'latestSteps');
?>
        <?php /* King is the icon at the bottom right (views/sl/inc/shell_end.php), not a panel on the dashboard */ ?>
        <?php include __DIR__ . '/partials/mobile_dashboard.php'; ?>
        <div class="sl-dashboard-detail"><?php include __DIR__ . (!empty($d['cc']) ? '/partials/client_overview.php' : '/partials/client_hero.php'); ?></div>
        <?php if (!empty($d['tracking']['items'])): $tc = $d['tracking']; $tcTitle = 'On the move'; $tcNoun = 'parcels'; $tcExpand = sl_route('LiveTracking'); ?>
        <?php include __DIR__ . '/partials/track_console.php'; ?>
        <?php endif; ?>
        <div class="sl-dashboard-detail">
        <?php if (empty($d['cc'])) include __DIR__ . '/partials/portal_addresses.php'; /* the overview shows the addresses in its top grid */ ?>
        <?php include __DIR__ . '/partials/client_lower.php'; ?>

</div>
