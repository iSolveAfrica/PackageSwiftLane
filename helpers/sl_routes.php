<?php
/**
 * Swift Lane design → application route map.
 *
 * The approved design (docs/handover/artifact-source/*.dc.html) links screens by
 * artboard name ("Customers.dc.html"). Every converted page links through
 * sl_route('Customers') so one table decides which PHP page serves each screen.
 * Legacy URLs are kept where a page already existed, so deep links and
 * bookmarks keep working.
 */

function sl_routes(): array
{
    static $routes = null;
    if ($routes !== null) {
        return $routes;
    }
    $routes = [
        // Foundation and dashboards
        'Main'                 => 'index.php',
        'ControlPanels'        => 'control_panels.php',
        'ControlPanelsMobile'  => 'control_panels.php',
        'ControlPanelsMobile2' => 'control_panels.php',
        'ControlPanelsMobile3' => 'control_panels.php',
        'ExecDashboard'        => 'exec_dashboard.php',
        'ClientDashboard'      => 'index.php',
        'UserHome'             => 'customers_profile_edit.php',
        'DesignSystem'         => 'design_system.php',
        'Piece-7d1a'           => 'index.php',

        // Sign in and public
        'Login'          => 'login.php',
        'LoginOtp'       => 'auth-otp.php',
        'ForgotPassword' => 'forgot-password.php',
        'PasswordReset'  => 'forgot-reset.php',
        'SignUp'         => 'sign-up.php',
        'PublicTracking' => 'track.php',
        'QuoteCalculator'=> 'cotizar.php',

        // Air freight
        'AirShipments'     => 'courier_list.php',
        'NewShipment'      => 'courier_add.php',
        'ShipmentDetail'   => 'courier_view.php',
        'ShipmentTracking' => 'courier_shipment_tracking.php',
        'DeliveryTracking' => 'delivery_tracking.php',
        'ImportData'       => 'import_excel_add_courier.php',

        // Sea freight and pre-alerts
        'SeaPackages'   => 'customer_packages_list.php',
        'NewPackage'    => 'customer_packages_add.php',
        'PackageDetail' => 'customer_packages_view.php',
        'PackageIntake' => 'package_intake.php',
        'PreAlerts'     => 'prealert_list.php',

        // Pickups, consolidation and warehouse
        'Pickups'             => 'pickup_list.php',
        'SchedulePickup'      => 'pickup_add_full.php',
        'PickupAging'         => 'pickup_aging.php',
        'Consolidations'      => 'consolidate_list.php',
        'ConsolidationDetail' => 'consolidate_view.php',
        'ConsolidationAir'    => 'consolidate_view.php',
        'NewConsolidation'    => 'consolidate_add.php',
        'Warehouse'           => 'warehouse.php',
        'WarehouseDelivery'   => 'warehouse_delivery.php',
        'WarehousePickups'    => 'warehouse_pickups.php',
        'HandOut'             => 'pickup_client.php',
        'LockerSearch'        => 'locker_search.php',
        'LabelStudio'         => 'label_studio.php',
        'Label4x6'            => 'print_label_ship.php',
        'Label1x2'            => 'print_label_ship.php',
        'ReceiptPaid'         => 'print_invoice_package.php',
        'ReceiptNotPaid'      => 'print_invoice_package.php',

        // People
        'Customers'        => 'customers_list.php',
        'CustomerProfile'  => 'customer_view.php',
        'AddCustomer'      => 'customers_add.php',
        'Recipients'       => 'recipients_admin_list.php',
        'RecipientsMine'   => 'recipients_list.php',
        'Drivers'          => 'drivers_list.php',
        'DriverApp'        => 'driver_app.php',
        'RiderDispatch'    => 'rider_dispatch.php',
        'RiderDetail'      => 'rider_detail.php',
        'StaffRoles'       => 'users_list.php',
        'InviteStaff'      => 'users_add.php',
        'StaffPermissions' => 'manage_permissions.php',
        'StaffActivity'    => 'staff_activity.php',
        'StaffProductivity'=> 'staff_productivity.php',
        'AiStudio'         => 'ai_studio.php',

        // Finance
        'Payments'                    => 'global_payments_gateways.php',
        'RecordPayment'               => 'add_payment_gateways_courier.php',
        'AccountsReceivable'          => 'accounts_receivable.php',
        'FinancialOverview'           => 'financial_overview.php',
        'FinancialOverviewRestricted' => 'financial_overview.php',
        'Transactions'                => 'transactions.php',
        'BillingHub'                  => 'financial_sheet.php',
        'FinancialSheet'              => 'financial_sheet_consolidation.php',
        'MyBills'                     => 'my_bills.php',
        'PayCheckout'                 => 'pay_checkout.php',
        'CostSettings'                => 'cost_settings.php',
        'RiderSettings'               => 'rider_settings.php',
        'SendPrices'                  => 'send_prices.php',

        // Insights
        'Reports'      => 'reports.php',
        'ActivityLogs' => 'activity_logs.php',
        'MessageLogs'  => 'message_logs.php',
        'MessageCosts' => 'message_costs.php',
        'SystemLogs'   => 'system_logs.php',
        'Documents'    => 'documents.php',

        // Messaging
        'Notifications'         => 'notifications_list.php',
        'NotificationCenter'    => 'notification_center.php',
        'CustomerNotifications' => 'customer_notifications.php',
        'ComposeMessage'        => 'compose_message.php',
        'Broadcast'             => 'push_notifications.php',
        'EmailBuilder'          => 'newsletter.php',
        'MessageTemplates'      => 'templates_sms.php',
        'CommsHub'              => 'comms_hub.php',
        'Automations'           => 'automations.php',

        // Customer service
        'CsInbox'    => 'support_inbox.php',
        'CsTicket'   => 'support_ticket.php',
        'CsCustomer' => 'support_customer.php',
        'CsRules'    => 'support_rules.php',
        'CsPlaybook' => 'support_playbook.php',
        'CsReports'  => 'support_reports.php',
        'CsChannels' => 'support_channels.php',
        'CsAI'       => 'support_ai.php',
        'StaffAI'    => 'ai_assistant.php',
        'AiChatMobile' => 'ai_assistant.php',
        'AiCenter'   => 'ai_center.php',
        'SupportHub' => 'support_hub.php',

        // Freight
        'FreightHub'      => 'freight_hub.php',
        'FreightSchedule' => 'freight_schedule.php',
        'Waybills'        => 'waybills.php',
        'CargoExceptions' => 'cargo_exceptions.php',
        'InsuranceClaims' => 'insurance_claims.php',
        'FreightPartners' => 'freight_partners.php',
        'LastMile'        => 'last_mile.php',
        'LoyaltyRewards'  => 'loyalty_rewards.php',
        'SecurityCenter'  => 'security_center.php',

        // Settings and admin
        'SettingsHub'       => 'tools.php',
        'Catalog'           => 'catalog.php',
        'Tariffs'           => 'shipping_tariffs_list.php',
        'RateEditor'        => 'shipping_tariffs_add.php',
        'ShippingAddresses' => 'shipping_addresses.php',
        'LoginSettings'     => 'login_settings.php',
        'ModuleController'  => 'module_controller.php',
        'ApiConnections'    => 'api_connections.php',
        'NewIntegration'    => 'api_connections.php?new=1',
        'ApiAccess'         => 'api_access.php',
        'ApiMonitor'        => 'api_monitor.php',
        'ApiTraffic'        => 'api_traffic.php',

        // Developer
        'DevOverview'       => 'dev_overview.php',
        'DevServices'       => 'dev_services.php',
        'DevLogs'           => 'dev_logs.php',
        'DevMetrics'        => 'dev_metrics.php',
        'DevInfrastructure' => 'dev_infrastructure.php',
        'DevDeployments'    => 'dev_deployments.php',
        'DevAlerts'         => 'dev_alerts.php',
        'DevIssues'         => 'dev_issues.php',
        'DevIntegrations'   => 'dev_integrations.php',
    ];
    return $routes;
}

/** URL of a design screen. Unknown names fall back to the overview. */
function sl_route(string $board, array $query = []): string
{
    $board = preg_replace('/\.dc\.html$/', '', $board);
    $url = sl_routes()[$board] ?? 'index.php';
    if ($query) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);
    }
    return $url;
}
