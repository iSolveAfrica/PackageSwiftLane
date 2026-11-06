# Feature traceability matrix

One row per screen of the approved design. Status: **done** (real data, actions work, verified), **partial** (page renders with real data but listed actions are missing), **planned** (template converted, not yet bound). Legacy equivalents are the old page files this screen replaces.

| Screen | Design title | Legacy equivalent / decision | Status | Data source (tables) | Endpoints / writes | Permissions | Verification |
|---|---|---|---|---|---|---|---|
| DesignSystem | Design System | | planned | | | | |
| Main | Overview (Admin) | views/dashboard/index.php (replaced; richer briefing, queues, forecast) | done | cdb_add_order, cdb_customers_packages, cdb_pre_alert, cdb_consolidate*, cdb_courier_track, cdb_users, cdb_activity_log, cdb_met_payment, cdb_sl_settings | none (read only) | main_dashboard_index via index.php routing; money hidden without view_money_dashboard | screenshot 1440/390, seeded DB |
| ClientDashboard | Customer Portal Home | | planned | | | | |
| UserHome | My Account (Customer) | | planned | | | | |
| CustomerNotifications | My Notification Settings (Customer) | | planned | | | | |
| Login | Log In | | planned | | | | |
| SignUp | Sign Up | | planned | | | | |
| ForgotPassword | Forgot Password | | planned | | | | |
| LoginOtp | Login OTP | | planned | | | | |
| PasswordReset | Password Reset (every 30 days) | | planned | | | | |
| PublicTracking | Public Tracking | | planned | | | | |
| AirShipments | Air Shipments | | planned | | | | |
| NewShipment | New Shipment | | planned | | | | |
| ShipmentDetail | Shipment Detail | | planned | | | | |
| SeaPackages | Sea Packages | | planned | | | | |
| NewPackage | New Package | | planned | | | | |
| PackageDetail | Package Detail | | planned | | | | |
| PreAlerts | Pre-Alerts | | planned | | | | |
| Pickups | Pickups | | planned | | | | |
| WarehousePickups | Warehouse Pickups (handed out) | | planned | | | | |
| HandOut | Hand Out Packages (counter) | | planned | | | | |
| Consolidations | Consolidations | | planned | | | | |
| ConsolidationDetail | Consolidation Detail · Sea (SLC-4471) | | planned | | | | |
| ConsolidationAir | Consolidation Detail · Air (AIR-1187) | | planned | | | | |
| NewConsolidation | New Consolidation (create) | | planned | | | | |
| Warehouse | Warehouse | | planned | | | | |
| LockerSearch | Locker Search | | planned | | | | |
| Customers | Customers | customers_list.php + ajax/customers/customers_list_ajax.php (replaced by server-rendered list with same filters) | done | cdb_users, cdb_add_order, cdb_customers_packages, cdb_customer_blocks, cdb_profile_phone_verified, cdb_cs_tickets | ajax/sl/customer_status_ajax.php (approve/block/unblock), CSV export | view_client_list; edit_client/approve_client/delete_client for actions | screenshot 1440, approve + block exercised |
| CustomerProfile | Customer Profile | | planned | | | | |
| Recipients | Recipients | | planned | | | | |
| Drivers | Drivers | | planned | | | | |
| RiderDispatch | Dispatch board (riders) | | planned | | | | |
| DeliveryTracking | Delivery Tracking (live) | | planned | | | | |
| DriverApp | Rider App · Mobile | | planned | | | | |
| ShipmentTracking | Shipment Tracking (advanced, 25 features) | | planned | | | | |
| RiderDetail | Rider detail | | planned | | | | |
| StaffRoles | Staff & Roles | | planned | | | | |
| StaffActivity | Staff activity log | | planned | | | | |
| Payments | Payments | global_payments_gateways.php (replaced); payments_gateways_{courier,list,consolidate,package_consolidate}_list.php redirect here with ?type= | done | cdb_payments_gateway, cdb_charges_order, cdb_fs_payments (USD via each row's stored rate), cdb_users | export ?export=csv; links to RecordPayment / SendPrices | view_global_payments or any legacy list permission; cdp_financeGuardPage | php -l; loaded logged in (admin) 1440 + 390 (no horizontal scroll), php.log clean; screenshots /tmp/claude-0/shots/ |
| SendPrices | Send package prices | new send_prices.php | done (design's per-package pricing table replaced by customer/segment picker + tariff sheet, as specified) | cdb_users segments, cdb_shipping_fees, cdb_message_cost_rates | ajax/sl/send_prices_ajax.php (cdp_pushNotifyUser WhatsApp/e-mail, cdp_sendSms; source send_prices in cdb_message_log; 50 per request) | send_prices or push_notifications | sent once (logged as failed/skipped: no SMTP/SMS in dev) |
| AccountsReceivable | Accounts Receivable | accounts_receivable.php (replaced) | done | unpaid air/sea/consolidation invoices (status_invoice 23, due_date) bucketed current/1-30/31-60/60+ | ajax/sl/ar_remind_ajax.php (one invoice or all overdue, cdp_pushNotifyUser → cdb_message_log); ?export=csv | view_receivable_accounts; finance guard | sent one reminder (logged; SMTP not configured in dev) |
| FinancialOverview | Financial Overview | financial_overview.php (replaced) | done | revenue cdb_add_order + cdb_customers_packages + cdb_consolidate; cost = price_purchase + cdb_operating_costs (on billing, prorated); payments union for gateways | none (read only) | view_financial_overview + cdp_canViewMoney(finance) | screenshot 1440/390 |
| Reports | Reports | reports.php (replaced hub; every report_* list/excel/print page unchanged) | partial | KPIs, monthly volume, status mix, catalogue of 24 legacy reports with counts for the date range | Download all (Excel links in a dialog, sequential download) | view_general_reports + each report's own permission; finance reports hidden when finance off | design chart galleries for warehouse/people/support/messaging/platform not reproduced (linked to their boards) |
| ActivityLogs | Activity Logs | | planned | | | | |
| MessageLogs | Message Logs | | planned | | | | |
| Tariffs | Shipping Tariffs | shipping_tariffs_list.php (replaced) | done | cdb_shipping_fees by mode (air first), cdb_shipping_fees_custom | delete via ajax/sl/tariff_save_ajax.php; ?export=csv rate card; client-side price check | view_shipping_tariffs; edit/delete/create permissions per action | screenshot 1440/390 |
| Notifications | Notifications | | planned | | | | |
| ControlPanels | Control Panels | new control_panels.php replaces dashboard_admin_{shipments,packages_customers,pickup,consolidated,package_consolidated,account}.php and dashboard_warehouse.php (all redirect to control_panels.php#section) | done | live counts per module (helpers/dashboard_data.php loaded, nav agency scope), 7-day attention sparks, health from cdb_met_payment / SMS settings / cdb_message_log | none (read only); client-side panel search, ⌘K | any legacy dashboard permission; rows filtered by sl_navAllowed | screenshot 1440 + 390 |
| ControlPanelsMobile | Control Panels · Mobile (live page at phone width) | control_panels.php at 390px | done | as ControlPanels | — | as ControlPanels | 390 screenshot, no horizontal scroll |
| ControlPanelsMobile2 | Control Panels · Mobile 2 | control_panels.php at 390px (sections stack) | done | as ControlPanels | — | as ControlPanels | 390 screenshot |
| ControlPanelsMobile3 | Control Panels · Mobile 3 | control_panels.php at 390px (sections stack) | done | as ControlPanels | — | as ControlPanels | 390 screenshot |
| DevOverview | Developer Overview | | planned | | | | |
| DevServices | Services | | planned | | | | |
| DevLogs | Logs | | planned | | | | |
| DevMetrics | Metrics and Traces | | planned | | | | |
| DevInfrastructure | Infrastructure | | planned | | | | |
| DevDeployments | Deployments | | planned | | | | |
| DevAlerts | Alerts | | planned | | | | |
| DevIssues | Issues, Site Management and Change Log | | planned | | | | |
| DevIntegrations | Integrations | | planned | | | | |
| DevBilling | Usage & Billing | | planned | | | | |
| CsInbox | Support Inbox | support_inbox.php (legacy views/support/inbox.php kept, no longer routed) | done | cdb_cs_tickets, cdb_cs_conversations, cdb_cs_ticket_tags, cdb_cs_views, cdb_cs_teams | ajax/support/desk_ajax.php (create, bulk); server-side q/filter/sort/page | cdp_csIsAgent (rank 2+); spam/trash bulk admin only | php -l; page loaded logged in (admin), screenshot 1440 under /tmp/claude-0/shots; ticket created |
| CsTicket | Support Ticket | support_ticket.php?id= (shell page) | done | cdb_cs_tickets, messages, events, links, tasks, followers, proposals, presence | desk_ajax.php: update, send (reply/note), accept/requeue/release, tag, link_ref, unlink, task, task_done, merge, follow, proposal(_create), run_rule, upload, ai_draft/ai_refine/ai_summary (preview only), poll | rank 2+; approvals admin only | php -l; page loaded logged in (admin), screenshot 1440 under /tmp/claude-0/shots; reply, note, resolve, close exercised |
| CsCustomer | Customer 360 (support) | new support_customer.php?id= | done | cdb_users, cdb_cs_tickets, cdb_cs_messages (internal notes), cdb_add_order, cdb_activity_log, cdb_loyalty_accounts | desk_ajax.php create (new ticket dialog) | rank 2+; money via cdp_canViewMoney | php -l; page loaded logged in (admin), screenshot 1440 under /tmp/claude-0/shots |
| CsRules | Ticket Rules | new support_rules.php (replaces support_settings.php rules tab; support_settings.php redirects) | done | cdb_cs_rules, cdb_cs_rule_runs | ajax/support/admin_ajax.php rule_save/rule_status/rule_delete/rule_template/rule_simulate | view rank 2+, change rank 3+ | php -l; page loaded logged in (admin), screenshot 1440 under /tmp/claude-0/shots; rule created, template added, dry run |
| CsPlaybook | Playbook | new support_playbook.php | done | cdb_cs_kb, cdb_cs_canned, cdb_cs_rules | admin_ajax.php kb_save/canned_save/canned_delete | rank 2+; publishing articles rank 3+ | php -l; page loaded logged in (admin), screenshot 1440 under /tmp/claude-0/shots; saved reply and article created |
| Automations | Automations (builder) | | planned | | | | |
| CsReports | Support Reports | support_reports.php (cdp_csReport) | done | cdb_cs_tickets, conversations, outbox, rule_runs | ajax/support/reports_ajax.php?action=export (CSV) | rank 2+ | php -l; page loaded logged in (admin), screenshot 1440 under /tmp/claude-0/shots |
| StaffAI | AI Assistant (staff, from codebase) | | planned | | | | |
| CsAI | AI Assistant | new support_ai.php (AI for customers / King) | done | cdb_cs_settings king_*, copilot_enabled; cdb_cs_ai_runs | admin_ajax.php settings | rank 2+ view, rank 3+ change; "provider not configured" banner | php -l; page loaded logged in (admin), screenshot 1440 under /tmp/claude-0/shots; no provider key: customer mode refused |
| AiChatMobile | AI Assistant · Mobile pop-up | ai_assistant.php at <=640px: bottom sheet (assets/swiftlane/pages/AiChatMobile.sheet.css) | done | as StaffAI | ajax/ai_assistant/chat_ajax.php | as StaffAI | screenshot 390, sheet open, not-configured state |
| CsChannels | Channels and Teams | new support_channels.php | done | cdb_cs_teams, cdb_cs_team_members, cdb_cs_tickets, cdb_cs_outbox, cdb_cs_settings (sla_*, notify_channels) | admin_ajax.php team_save/team_delete/member_save/member_remove/availability/settings/outbox_retry | rank 2+; changes rank 3+ | php -l; page loaded logged in (admin), screenshot 1440 under /tmp/claude-0/shots |
| Piece-7d1a | Box | | planned | | | | |
| NotificationCenter | Notification Center | | planned | | | | |
| Broadcast | Push notifications (send with previews) | | planned | | | | |
| MessageTemplates | SMS and WhatsApp templates | | planned | | | | |
| EmailBuilder | Email builder (drag and drop) | | planned | | | | |
| WarehouseDelivery | Warehouse Delivery (pickup codes) | | planned | | | | |
| PickupAging | Pickup Aging | | planned | | | | |
| StaffProductivity | Staff Productivity | | planned | | | | |
| MessageCosts | Message Costs | | planned | | | | |
| Transactions | Transactions | transactions.php (replaced) | done | payments union (gateway, charges, fs ledger incl. refunds) | ?export=csv | view_transactions; finance guard | screenshot 1440/390 |
| SystemLogs | System Logs | | planned | | | | |
| MyBills | My Bills (Customer) | | planned | | | | |
| QuoteCalculator | Rate Calculator (Public) | | planned | | | | |
| PackageIntake | Package Intake (AI lookup) | | planned | | | | |
| Label4x6 | Label 4×6 in | | planned | | | | |
| Label1x2 | Label 1×2 in | | planned | | | | |
| LabelStudio | Label Studio (label options) | | planned | | | | |
| ReceiptPaid | Package receipt · Paid | | planned | | | | |
| ReceiptNotPaid | Package receipt · Not paid | | planned | | | | |
| ModuleController | Module Controller | | planned | | | | |
| ApiMonitor | API Monitoring (admin) | | planned | | | | |
| ApiAccess | API Access (users) | | planned | | | | |
| StaffPermissions | Staff Permissions | | planned | | | | |
| FinancialOverviewRestricted | Financial Overview · no money permission | same page when cdp_canViewMoney($user,'finance') denies (or ?as_restricted=1 to preview) | done | as FinancialOverview; every amount scrubbed server-side before render | reminders via ar_remind_ajax | view_financial_overview without view_money_finance/view_monetary_values | screenshot 1440 |
| CostSettings | Operating Costs (super admin) | new cost_settings.php | done | cdb_operating_costs, cdb_operating_cost_log, cdb_message_log | ajax/sl/cost_settings_ajax.php (save / add / remove, change log, activity log) | read: view_tools; write: super admin or manage_operating_costs | added two lines, screenshot 1440/390 |
| LoginSettings | Login Page (settings) | | planned | | | | |
| ShippingAddresses | Shipping Addresses (settings) | | planned | | | | |
| AiStudio | AI Profile Studio | | planned | | | | |
| SettingsHub | Settings (hub + general) | | planned | | | | |
| Documents | Documents & exports | | planned | | | | |
| ComposeMessage | Compose message | | planned | | | | |
| ImportData | Import from Excel | | planned | | | | |
| AddCustomer | Add customer / recipient | | planned | | | | |
| InviteStaff | Invite staff | | planned | | | | |
| RecordPayment | Record payment | add_payment_gateways_{courier,package,consolidate,consolidate_package}.php (replaced; one design form, ?type=) | done | invoices with status_invoice 23 per customer, cdb_payment_methods | ajax/sl/record_payment_ajax.php: cdp_insertPaymentGateway (packages/consolidations) or cdb_charges_order (air) + cdp_confirmPayment* → 19 when paid in full, fs_cleared_for_delivery, optional release of held parcels and receipt via cdp_pushNotifyUser; activity log | fs_record_payment / manage_account_payments / verify_*payment; finance guard | wrote one air payment (SL-101463 → invoice 19, charge row), screenshot 1440/390 |
| SchedulePickup | Schedule pickup | | planned | | | | |
| PayCheckout | Pay bills (customer) | | planned | | | | |
| RateEditor | Rate editor | shipping_tariffs_add.php / shipping_tariffs_edit.php?id= (replaced) | done | cdb_shipping_fees (+ min_charge, valid_from/until), countries/states/cities | ajax/sl/tariff_save_ajax.php (cdp_verifyRangeTariffsExist, cdp_insertTariffs/cdp_updateTariffs/cdp_deleteTariffs, same rules as ajax/tools/ship_tariffs/*) | create_shipping_tariff / edit_shipping_tariff / delete_shipping_tariff | created one band, overlap refused (422) |
| NewIntegration | Add integration | | planned | | | | |
| ApiConnections | API connections (payments, couriers, AI…) | | planned | | | | |
| ApiTraffic | Live API traffic (push and pull) | | planned | | | | |
| RiderSettings | Rider fees and split (settings) | | planned | | | | |
| FreightHub | Freight Hub | | planned | | | | |
| FreightSchedule | Departures and Arrivals | | planned | | | | |
| Waybills | Waybills, Manifests and Container Loading | | planned | | | | |
| CargoExceptions | Cargo Exceptions, Bulk Updates, Partial, Hand-overs | | planned | | | | |
| InsuranceClaims | Insurance and Claims | | planned | | | | |
| FreightPartners | Freight Partners | | planned | | | | |
| ExecDashboard | Management Dashboard (air first) | new exec_dashboard.php | done | cdb_add_order / cdb_customers_packages (mode air/sea/all), cdb_offices, cdb_operating_costs | none (read only); links to Excel/PDF exports | main_dashboard_index or view_exec_dashboard; money hidden without view_money_dashboard | screenshot 1440 |
| BillingHub | Billing and finance (switch off finance) | financial_sheet.php (hub; legacy sheet at ?legacy=1); financial_sheet_consolidation.php unchanged | done | cdb_add_order, payments union, cdb_payment_methods, cdb_shipping_fees, settings | ajax/sl/finance_switch_ajax.php writes cdb_sl_settings.finance_enabled (read by cdp_financeEnabled when CDP_FINANCE_ENABLED is not defined) | financial_sheet; switch: super admin or manage_finance_switch | toggled once; screenshot 1440 |
| LastMile | Ghana Last-Mile Delivery | | planned | | | | |
| AiCenter | AI Centre (air first) | new ai_center.php; ai_assistant_settings.php 301 → ai_center.php | partial | cdb_ai_settings (keys masked), cdb_ai_knowledge, cdb_ai_page_ideas, cdb_ai_questions, cdb_ai_messages, cdb_cs_ai_runs | ajax/ai_assistant/settings_ajax.php save/test/models/knowledge_add/knowledge_delete/idea_status/analyse | super admin (keys) / rank 3+ (insights) | php -l; page loaded logged in (admin), screenshot 1440 under /tmp/claude-0/shots; design demo panels (label reader, forecasts, WhatsApp bot) not built: no backend |
| CommsHub | Messages and Automation | | planned | | | | |
| SupportHub | Support and CRM | new support_hub.php | done | cdb_cs_tickets, cdb_cs_rules, cdb_cs_kb, cdb_cs_proposals | admin_ajax.php rule_status (escalation toggles) | cdp_csIsAgent | php -l; page loaded logged in (admin), screenshot 1440 under /tmp/claude-0/shots |
| LoyaltyRewards | Loyalty, Referrals and Rewards | new loyalty_rewards.php; helpers/loyalty.php; customer card views/sl/pages/_loyalty_card.php | partial | cdb_loyalty_accounts, cdb_loyalty_events, cdb_referrals, cdb_rewards, cdb_sl_settings loyalty_*/referral_* | ajax/sl/loyalty_ajax.php settings/toggle/award/reward_save/reward_toggle/backfill | manage_loyalty | php -l; page loaded logged in (admin), screenshot 1440 under /tmp/claude-0/shots; award, idempotent backfill, coupon. Not wired into the status-change path yet; coupons are not applied at checkout |
| SecurityCenter | Security and Reliability | | planned | | | | |
