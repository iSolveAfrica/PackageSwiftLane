# Gerald Master View

The management and administrative dashboard: the whole business on one page for a chosen period, compared with the period before, with exports, scheduled e-mail reports, anomaly alerts and AI-written insights.

Code: `helpers/mgmt.php` (every figure), `ajax/mgmt/mgmt_ajax.php`, `master_view.php`, `views/sl/pages/MasterView.php`, `dataJs/master_view.js`. Navigation: Command Centers › Gerald Master View.

## Who sees what

- **Administrators** see every branch.
- **Branch managers and finance officers** (job role set under Staff & Branches) see their own branches only; the branch filter cannot widen that.
- **Money** (charges, costs, margins, balances) appears only for people allowed to see money in reports (`view_monetary_values` / `view_money_reports`, super admins always). Others get the same page without money panels, and exports leave money out too.

## Panels

Executive overview, total shipments, warehouse activity, delivery performance, revenue, monthly growth (12 months), customer acquisition, active vs inactive customers, shipping volume trend, revenue by destination, warehouse receiving productivity, pending shipments (no tracking movement for N days), delayed shipments (open past the target for their mode), outstanding balances with aging, complaint metrics, branch performance, employee productivity, shipping cost analysis, profitability per shipment, fraud and anomaly alerts, AI insights.

**Customise** shows, hides and reorders panels per person (`cdb_mgmt_layouts`).

## Definitions

- **Shipments:** air shipments (`cdb_add_order`, not pickups, not cancelled) created in the period.
- **Handed over / on time:** first Delivered or Picked up date in the tracking history; on time when within the target days for the mode (Settings: air 10, sea 45, other 14 by default).
- **Delayed:** not handed over and older than its target. **Pending:** no tracking movement for the "stale" number of days.
- **Active customer:** shipped or had a package received in the last 90 days. **Inactive:** shipped before, not lately. **Never:** no shipment at all.
- **Charges:** shipment totals. **Billed / received / outstanding (USD):** the financial module's ledger, when in use. **Balances (GHS):** billing ledger, net of discounts and payments.
- **Cost:** recorded purchase price plus an optional overhead per shipment. **Margin:** charges − cost − overhead.
- **Branch figures:** a staff branch counts the shipments of the office and agency it is linked to (Staff & Branches › Branches).

## Anomaly alerts

Unusual daily volume (z-score against 30 days), shipments with no charge, charged below cost, a staff member's cancellation rate far above the average, out-of-hours registrations, carrier tracking numbers used twice, several customer accounts on one phone number, repeated failed sign-ins, many blocked actions, refunds this week. Thresholds are in Settings. "Mark as checked" records who looked and why; checked alerts move to the bottom.

## Exports and scheduled reports

PDF, Excel (.xlsx) and CSV of the visible panels. Scheduled reports (administrators) are e-mailed daily (yesterday), weekly (last 7 days) or monthly (last calendar month) with the file attached and the observations in the e-mail body. They are sent by `cron/comms_worker.php`, which runs them as the system (`CDP_OPS_SYSTEM_RUN`).

## AI insights

Rule-based observations are always shown. With an AI provider configured (Tools › AI Assistant), the figures are sent as JSON and the model writes highlights, risks and actions grounded in those numbers; the answer is cached for 6 hours for the same filters. "Analyse again" asks afresh.

## Traceability

| Requirement | Where |
|---|---|
| 1 Executive overview | `executive` panel |
| 2 Total shipments | `cdp_mgmtShipmentStats` |
| 3 Warehouse activity | `cdp_mgmtWarehouse` |
| 4 Delivery performance | `cdp_mgmtDelivery` |
| 5 Revenue performance | `revenue` panel, `cdp_mgmtLedger` |
| 6 Monthly growth | `cdp_mgmtMonthly` |
| 7 Customer acquisition | `cdp_mgmtCustomers` |
| 8 Active vs inactive customers | `cdp_mgmtCustomers` |
| 9 Shipping volume trends | `volume` panel |
| 10 Revenue by destination | `cdp_mgmtDestinations` |
| 11 Warehouse receiving productivity | `cdp_mgmtReceiving` |
| 12 Pending shipment identification | `cdp_mgmtPending` |
| 13 Delayed shipment tracking | `cdp_mgmtDelayed` |
| 14 Outstanding customer balances | `cdp_mgmtBalances` |
| 15 Customer complaint metrics | `cdp_mgmtComplaints` |
| 16 Branch-level performance | `cdp_mgmtBranches` |
| 17 Employee productivity | `cdp_staffPerformance` |
| 18 Shipping cost analysis | `cdp_mgmtCosts` |
| 19 Profitability per shipment | `cdp_mgmtProfit` |
| 20 Fraud and anomaly alerts | `cdp_mgmtAlerts` |
| 21 Automated management reports | `cdp_mgmtBuildReport` |
| 22 Export to PDF, CSV and Excel | `cdp_opsSendExport` |
| 23 Customisable dashboards | Customise, `cdb_mgmt_layouts` |
| 24 Scheduled reporting | `cdb_mgmt_schedules`, `cdp_mgmtRunSchedules` |
| 25 AI-assisted business insights | `cdp_mgmtInsights` |

The existing **Management Center** (`exec_dashboard.php`) is unchanged and stays alongside.
