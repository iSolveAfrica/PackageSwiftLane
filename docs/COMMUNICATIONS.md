# Communication Center

WhatsApp, SMS and e-mail automation for customers: automatic alerts from what happens to their shipments, reminders, delivery booking, surveys, consent-based campaigns, two-way WhatsApp with an AI first responder, and the customer's own notification preferences.

Code: `helpers/comms.php` (engine), `ajax/comms/comms_ajax.php` (staff endpoint), `webhooks/whatsapp.php` (inbound), `cron/comms_worker.php` (worker), pages `comms_center.php`, `comms_inbox.php`, `comms_preferences.php`, `survey.php`, `delivery_schedule.php`. Shared plumbing (settings, signed links, CSV/Excel/PDF): `helpers/ops_db.php`.

## Pages

| Page | Who | What |
|---|---|---|
| Command Centers › Communication Center (`comms_center.php`) | Staff (rank 2+). Settings, automations, templates and campaign approval: administrators | Overview, Automations, Templates, Send message, Campaigns, Segments, Outbox, Customer history, Surveys, Delivery bookings, Settings |
| Command Centers › WhatsApp Inbox (`comms_inbox.php`) | Staff | Two-way WhatsApp conversations; assign, close, hand back to the AI assistant |
| My shipping › Notification preferences (`comms_preferences.php`) | Customers, and anyone with a signed link | Channels, which updates, marketing consent, stop everything |
| `survey.php?t=…` / `delivery_schedule.php?t=…` | Public, signed links | Rate the service; choose a delivery day and time window |

## Setup

1. **Deploy.** Tables (`cdb_comm_*`, `cdb_ops_settings`) are created on first use; the database user needs CREATE once.
2. **Worker**, every 5 minutes (one line also runs the Master View's scheduled reports and the staff module's housekeeping):

   ```
   */5 * * * * php /path/to/app/cron/comms_worker.php >/dev/null 2>&1
   ```

3. **Settings** (administrators): switch automations on, set quiet hours, reminder timing and the currency label.
4. **Automations:** tick the messages and channels to send. Nothing is sent until something is ticked. Check **How statuses are read**: each status is matched to a message by its id and name; override any that are misread.
5. **WhatsApp provider.** *UltraMsg* uses the existing WhatsApp settings. *WhatsApp Business Cloud API (Meta)* needs the phone number ID, an access token, the app secret and a verify token. Paste the webhook address shown under Settings into Meta (or UltraMsg). Outside Meta's 24-hour window only approved templates are delivered: give each WhatsApp template its approved name and the placeholders in order.
6. **AI assistant (optional):** uses the provider and key from Tools › AI Assistant. Switch it on under Settings › AI WhatsApp assistant and add what it may quote (hours, addresses, prices).

## How it works

- **Detection, not hooks.** The worker reads new rows of the tracking history (`cdb_courier_track`) and new warehouse packages (`cdb_customers_packages`) past a watermark. The first run only sets the watermark, so history from before switching on is never announced. No shipment screen had to change.
- **One outbox.** Every message is a row in `cdb_comm_outbox` with a unique dedupe key (tracking row + customer + event + channel), so a rescan never sends twice. A row is claimed with a conditional update before sending; preferences are checked again at that moment (an opt-out after queueing wins). Failures retry after 5 min, 30 min and 2 h, then stop. A row interrupted mid-send is closed as failed for review, never resent blindly.
- **Specific before generic.** A status that maps to a specific message (departure, customs…) is sent as that message; the generic "status alert" only covers channels the specific one leaves out.
- **Sending** uses the app's primitives (UltraMsg, Hubtel/mNotify, PHPMailer) or the Cloud API, so `cdb_message_log` still records every attempt.
- **Quiet hours** delay everything except driver arrival and failed-delivery alerts.
- **Consent.** Transactional messages go unless the customer switched the channel or the event off. Campaigns reach only customers with marketing consent for that channel and carry an opt-out line. Every change of a preference is written to `cdb_comm_consent_log` with its source (customer, link, staff with how they were told, WhatsApp keyword).
- **Keywords on WhatsApp:** STOP (WhatsApp and marketing off), START, AGENT/HUMAN (hand over), 1-5 (answers the latest survey; 1-2 hands the chat to a person).
- **Campaign approval:** when switched on, a second administrator must approve a campaign (a super admin may approve their own).
- **AI assistant:** answers only from the customer's own shipments, packages and balance (the number must match exactly one customer) and the published help articles. Refunds, claims, damage, address changes or anything it cannot answer from those facts are handed to a person. A daily cap applies; on any provider error the chat goes to a person.

## Traceability

| Requirement | Where |
|---|---|
| 1 WhatsApp Business API integration | `cdp_commWaCloudSend`, `webhooks/whatsapp.php` (signature check), Settings › WhatsApp |
| 2 Automated SMS shipment alerts | Automations › SMS column; `cdp_commScanStatusHistory` |
| 3 Automated e-mail shipment notifications | Automations › E-mail column; `cdp_commSendEmail` |
| 4 Warehouse package received alerts | `warehouse_received`, `cdp_commScanWarehouse` |
| 5 Package claiming reminders | `claim_reminder`, `cdp_commScanClaimReminders` (after N days, every N days, at most N) |
| 6 Departure announcements | `departure` (status mapping) |
| 7 Cargo arrival notifications | `arrival` |
| 8 Customs clearance updates | `customs` |
| 9 Payment reminders | `payment_reminder`, `cdp_commScanPaymentReminders` (billing ledger) |
| 10 Delivery scheduling messages | `delivery_schedule`, `delivery_schedule.php`, Delivery bookings tab |
| 11 Driver arrival notifications | `driver_arrival` (On Route status) and the driver workspace "I am arriving" |
| 12 Failed delivery alerts | `failed_delivery` with a re-booking link |
| 13 Promotional broadcasting with consent | Campaigns tab, `cdp_commLaunchCampaign` |
| 14 Customer segmentation | Segments tab, `cdp_commSegmentSql` (whitelisted fields, bound values) |
| 15 Notification templates | Templates tab, `cdb_comm_templates` (per event, channel, language) |
| 16 Customer communication history | Customer history tab, `cdp_commHistory` (message log + inbound + surveys + preference changes) |
| 17 Two-way WhatsApp support | WhatsApp Inbox, `cdp_commHandleInbound`, `cdp_commReply` |
| 18 AI WhatsApp support bot | `cdp_commBotAnswer` |
| 19 Customer satisfaction surveys | `survey` event, `survey.php`, WhatsApp 1-5 replies, Surveys tab |
| 20 Notification preferences and opt-out | `comms_preferences.php`, STOP/START, staff-recorded consent |

## Tests

```
OPS_TEST_DB=swl_test OPS_TEST_USER=swl OPS_TEST_PASS=swl php tests/ops/ops_test.php
```

Without `OPS_TEST_DB` only the pure functions run. With it, the test needs a disposable database loaded with `database/fresh_install_schema.sql` and `migrations/*.sql`; it seeds data and runs the three modules end to end with PDO in exception mode. No message is sent.

## Known limits

- Inbound SMS and e-mail are not received; replies come in on WhatsApp only.
- The existing per-screen WhatsApp messages (Notification Control) still send as before. Switching the same event on here as well sends the customer two messages; use one or the other per event.
- Times use the server time zone, like the rest of the app.
