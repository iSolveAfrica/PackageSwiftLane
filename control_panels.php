<?php
/**
 * Control panels (ControlPanels.dc.html; ControlPanelsMobile/2/3 are this page at phone width).
 * Replaces dashboard_admin_*.php and dashboard_warehouse.php, which redirect to control_panels.php#section.
 */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate(['view_dashboard_ship', 'view_dashboard_pack', 'view_dashboard_pick', 'view_dashboard_shipments', 'view_dashboard_packages', 'view_dashboard', 'warehouse_view', 'view_warehouse_delivery', 'main_dashboard_index']);
require_once 'views/sl/data/control_panels.php';
sl_render('ControlPanels', ['id' => 'panels', 'title' => 'Control panels', 'board' => 'ControlPanels'], sl_controlPanelsData($user, $core));
