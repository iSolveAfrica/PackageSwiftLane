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
| Payments | Payments | | planned | | | | |
| SendPrices | Send package prices | | planned | | | | |
| AccountsReceivable | Accounts Receivable | | planned | | | | |
| FinancialOverview | Financial Overview | | planned | | | | |
| Reports | Reports | | planned | | | | |
| ActivityLogs | Activity Logs | | planned | | | | |
| MessageLogs | Message Logs | | planned | | | | |
| Tariffs | Shipping Tariffs | | planned | | | | |
| Notifications | Notifications | | planned | | | | |
| ControlPanels | Control Panels | | planned | | | | |
| ControlPanelsMobile | Control Panels · Mobile (live page at phone width) | | planned | | | | |
| ControlPanelsMobile2 | Control Panels · Mobile 2 | | planned | | | | |
| ControlPanelsMobile3 | Control Panels · Mobile 3 | | planned | | | | |
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
| CsInbox | Support Inbox | | planned | | | | |
| CsTicket | Support Ticket | | planned | | | | |
| CsCustomer | Customer 360 (support) | | planned | | | | |
| CsRules | Ticket Rules | | planned | | | | |
| CsPlaybook | Playbook | | planned | | | | |
| Automations | Automations (builder) | | planned | | | | |
| CsReports | Support Reports | | planned | | | | |
| StaffAI | AI Assistant (staff, from codebase) | | planned | | | | |
| CsAI | AI Assistant | | planned | | | | |
| AiChatMobile | AI Assistant · Mobile pop-up | | planned | | | | |
| CsChannels | Channels and Teams | | planned | | | | |
| Piece-7d1a | Box | | planned | | | | |
| NotificationCenter | Notification Center | | planned | | | | |
| Broadcast | Push notifications (send with previews) | | planned | | | | |
| MessageTemplates | SMS and WhatsApp templates | | planned | | | | |
| EmailBuilder | Email builder (drag and drop) | | planned | | | | |
| WarehouseDelivery | Warehouse Delivery (pickup codes) | | planned | | | | |
| PickupAging | Pickup Aging | | planned | | | | |
| StaffProductivity | Staff Productivity | | planned | | | | |
| MessageCosts | Message Costs | | planned | | | | |
| Transactions | Transactions | | planned | | | | |
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
| FinancialOverviewRestricted | Financial Overview · no money permission | | planned | | | | |
| CostSettings | Operating Costs (super admin) | | planned | | | | |
| LoginSettings | Login Page (settings) | | planned | | | | |
| ShippingAddresses | Shipping Addresses (settings) | | planned | | | | |
| AiStudio | AI Profile Studio | | planned | | | | |
| SettingsHub | Settings (hub + general) | | planned | | | | |
| Documents | Documents & exports | | planned | | | | |
| ComposeMessage | Compose message | | planned | | | | |
| ImportData | Import from Excel | | planned | | | | |
| AddCustomer | Add customer / recipient | | planned | | | | |
| InviteStaff | Invite staff | | planned | | | | |
| RecordPayment | Record payment | | planned | | | | |
| SchedulePickup | Schedule pickup | | planned | | | | |
| PayCheckout | Pay bills (customer) | | planned | | | | |
| RateEditor | Rate editor | | planned | | | | |
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
| ExecDashboard | Management Dashboard (air first) | | planned | | | | |
| BillingHub | Billing and Finance (switch off finance) | | planned | | | | |
| LastMile | Ghana Last-Mile Delivery | | planned | | | | |
| AiCenter | AI Centre (air first) | | planned | | | | |
| CommsHub | Messages and Automation | | planned | | | | |
| SupportHub | Support and CRM | | planned | | | | |
| LoyaltyRewards | Loyalty, Referrals and Rewards | | planned | | | | |
| SecurityCenter | Security and Reliability | | planned | | | | |
