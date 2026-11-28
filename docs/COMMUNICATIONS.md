# Notifications & Messages

WhatsApp, SMS and e-mail for customers in one area: automatic messages for every shipment status and event, reminders, sending to one person, several people or a campaign, one outbox for everything, two-way WhatsApp with King as first responder, surveys, consent and the customer's own preferences.

In the sidebar it is **one** Command Centers item, **Notifications & Messages**. Clicking it opens a sub-menu (keyboard: Enter/Space on the item; on phones it opens inside the drawer) with the pages below; each page also shows the same list as tabs at the top (`views/sl/pages/partials/comms_tabs.php`). Both come from one list, `sl_navCommsChildren()` in `helpers/sl_nav.php`, so the menu and the tabs never disagree, and a person only sees the pages they may open.

## Pages

| Page | Address | Who | What |
|---|---|---|---|
| Overview | `comms_hub.php` | Staff (rank 2+) or a messaging permission | Sent / failed / waiting from the Outbox feed for 1, 7, 30 or 90 days, per channel with cost, where messages came from, recent failures, alerts (channel not set up, worker late, failures, campaigns to approve, WhatsApp chats waiting, King without AI) and a way into every page |
| Automations | `comms_automations.php` | View rank 2+, change rank 3+ | Everything that sends a message, grouped, with a WhatsApp / SMS / e-mail switch per trigger and Edit (the shared editor). "Other triggers" (`?tab=other&ch=…`) edits the older per-screen templates |
| Status messages | `comms_statuses.php` | as Automations | Every shipment status × channel, predefined text per status (`helpers/notify_drafts.php`), Edit opens WhatsApp phone, SMS phone and the drag-and-drop e-mail canvas; Write with King |
| Send | `comms_send.php` | Rank 2+; campaigns with push or newsletter permission | One person, several people or a campaign; WhatsApp and SMS phone previews with tag chips, the full e-mail builder, cost estimate, schedule, approval. Campaigns list at `#campaigns`; report at `campaigns.php?batch=campaign-N` |
| Outbox | `comms_outbox.php` | Rank 2+ or `view_message_logs` | Every message: automatic, campaign, individual, inbox replies and the old per-screen sends, with every detail, retry and cancel |
| WhatsApp | `comms_inbox.php` | Rank 2+ | Two-way WhatsApp; badge = chats waiting for a person |
| Customer history | `comms_customers.php` | Rank 2+ | Everything said to and heard from one customer, and their preferences |
| Surveys | `comms_surveys.php` | Rank 2+, forms rank 3+ | Questions, when they are sent (statuses, delay, channels), the in-app pop-up, answers |
| Settings | `comms_settings.php` | Administrators (rank 3+); provider keys super admin or `manage_api_settings` | See below |

Customer pages: `comms_preferences.php` (Notification preferences, the only preferences the senders read), `survey.php?t=…` (public signed link).

### Old addresses (kept, they redirect)

| Old | Goes to |
|---|---|
| `comms_center.php#overview`, `#deliveries`, anything else | Overview |
| `#automations` / `#templates` | Automations / Status messages |
| `#send`, `#campaigns`, `#segments` | Send (`#campaigns` lands on the campaigns list) |
| `#outbox`, `#customers`, `#surveys`, `#settings`, `#inbox` | the page of that name (`?tab=<name>` works without JavaScript) |
| `notification_center.php`, `templates_*.php` | Automations (other triggers) |
| `notify_studio.php?key=status:N` | Status messages |
| `compose_message.php`, `push_notifications.php` (not `?type=invoice`), `newsletter.php`, `campaigns.php` without `?batch` | Send |
| `message_logs.php?…` | Outbox with the same filters |
| `delivery_schedule.php?t=…` | "This link has closed" page (customer delivery bookings were removed) |

The sidebar no longer has Notification Control, Notification Studio, Campaigns, Communication Center, Email Builder, SMS & WhatsApp, Compose Message or Push Notifications; Insights › Message Logs opens the Outbox. `sl_route()` board names for the old pages still resolve (to the redirecting files).

## Settings (`comms_settings.php`)

One page, one form per section, saved by `ajax/comms/settings_ajax.php` (POST + CSRF; every save writes one activity-log line with field names, never values):

- **Channel strip:** WhatsApp, SMS, e-mail, automatic messages (worker) and King, each On / Switched off / Not set up.
- **WhatsApp:** on/off, provider (UltraMsg on your phone by QR, or Meta Cloud API), instance address and token / Cloud phone id, token, app secret, verify token, Graph version; "Link your phone (scan the QR)" opens the in-app WhatsApp screen; "Check connection" asks the provider for the instance state; the webhook address with Copy and Rotate key.
- **SMS:** on/off, Hubtel and mNotify keys and sender IDs, each provider on/off, default provider, fallback, mNotify balance.
- **E-mail:** SMTP or PHP mail(), from name and address (optionally the same as the company support e-mail), host, user, password, port and security, "Check the server".
- **Sending rules:** master switch; **who sends status messages** (old screens or the Automation Center; handing over is refused unless automatic messages are on and the worker ran in the last 15 minutes, through `cdp_commSetStatusOwner`); quiet hours; messages per worker run; pickup and payment reminder timing; campaign approval.
- **Consent and opt-out:** counts, the WhatsApp keywords (STOP / UNSUBSCRIBE / OPT OUT, START / SUBSCRIBE, AGENT / HUMAN) and the rules that always apply.
- **King (WhatsApp bot):** on/off, answers per day, persona and instructions; a note and link when the AI centre has no provider.
- **Costs:** price per message per channel and this month's spend, read from Message costs (`message_costs.php`, where prices are set).
- **Test send:** one WhatsApp, SMS (choose the provider) or e-mail with the saved settings; it shows in the Outbox.

Secrets are never put in the page: a saved secret shows as "saved - leave blank to keep", and "Remove saved" next to it clears it on the next save (`clear_secrets`). People without the secrets permission see provider keys read-only, but can still switch WhatsApp and SMS on or off (the `set_channel` action saves at once: an emergency stop needs no key). The WhatsApp switch stops both providers (Meta Cloud included, in `cdp_commSendWhatsApp`).

Rules the endpoint enforces:

- Only fields present in the POST change; a missing field keeps its saved value. Only the chosen WhatsApp provider's fields are checked and saved.
- Provider addresses must be public: the UltraMsg instance address must be `https://` with no port or user, and neither it nor the SMTP host may point at loopback, private (RFC 1918), link-local (cloud metadata) or reserved addresses (`sl_commsHostCheck` in `views/sl/data/comms_settings.php`; the connection check pins the checked address). For a local mock provider set `define('CDP_ALLOW_LOCAL_PROVIDERS', true);` in `config/config.php` (never in production).
- When the instance address or SMTP host changes, the token or password must be typed again: a saved secret only goes to the server it was typed for.
- "Check the server" tests what is on screen (the saved password only for the saved host).
- Rotate key: with UltraMsg set up, the new webhook address is sent to the instance at once (`cdp_umWebhookSetup`); otherwise paste it in the provider dashboard.
- Test sends: at most 10 per person per hour (429 after that); a test refused before it reaches a provider is still written to the Outbox as skipped (source `comms_test`).
- STOP / UNSUBSCRIBE / OPT OUT by WhatsApp switches off WhatsApp and every offer (WhatsApp, SMS, e-mail); status SMS and e-mails keep going unless the customer switches them off in their preferences.

The old SMTP form (`settings_form.php?f=smtp`) redirects here. The Overview (`comms_hub.php`) keeps its Outbox figures for 60 seconds in `cdb_ops_settings` (`comms/overview_cache_<days>`; `?fresh=1` skips it). On phones the Overview has its own screen (`views/sl/phone/CommsHub.php`, same data); `comms_center.php` has no phone screen, so its hash router runs on every device.

## Setup

1. **Deploy.** Run `migrations/2026_11_27_comms_v2.sql`, `2026_11_27_message_log_outbox.sql` and `2026_11_27_comms_surveys.sql` (the tables are also created on first use; the database user needs CREATE once). The one-time data migration (`cdp_commMigrateV2`) runs by itself.
2. **Worker**, every 5 minutes:

   ```
   */5 * * * * php /path/to/app/cron/comms_worker.php >/dev/null 2>&1
   ```

3. **Settings:** set up the providers, then send a test on each channel.
4. **Automations and Status messages:** switch on the channels per trigger and status; edit the predefined texts if needed.
5. **Hand over status messages** in Settings › Sending rules when the worker is running. Until then the old screens keep sending them, exactly as before.

## How it works

- **Engine:** `helpers/comms.php` (outbox, delivery, status scan), `helpers/comms_triggers.php` (catalogue, templates, placeholders), `helpers/comms_gate.php` (status owner, the gate that stops old per-screen sends a trigger switch turned off or the Automation Center now owns), `helpers/comms_ai.php` (King drafts with validation), `helpers/comms_campaigns.php`, `helpers/comms_surveys.php`, `helpers/comms_outbox_view.php`.
- **One switch per trigger and channel** (`cdb_ops_settings` ns `comms`, key `rules`), checked when the message is sent.
- **One outbox** (`cdb_comm_outbox`) with dedupe, retries (5 min, 30 min, 2 h) and quiet hours; every attempt is logged in `cdb_message_log` with its origin.
- **Consent:** transactional messages go unless the customer switched that channel off; campaigns reach only people who said yes to offers on that channel (contacts saved from WhatsApp groups included, and their STOP is honoured the same way); e-mails carry an unsubscribe link.
- **Removed:** Segments (campaign audiences are built on the Send page), customer delivery bookings (`delivery_schedule.php` is a stub; the `cdb_comm_delivery_slots` table stays), `dataJs/comms.js`.

## Tests

```
php scripts/check_command_centers.php
OPS_TEST_DB=swl_test OPS_TEST_USER=swl OPS_TEST_PASS=swl php tests/ops/ops_test.php
```

Without `OPS_TEST_DB` only the pure functions run. With it, the test needs a disposable database loaded with `database/fresh_install_schema.sql` and `migrations/*.sql`. No message is sent.

## Known limits

- Inbound SMS and e-mail are not received; replies come in on WhatsApp only.
- The in-app bell for status changes stays with the old screens (it is not a channel of the Automation Center).
- `customer_notifications.php` preferences are not read by any sender; only `comms_preferences.php` counts.
- Times use the server time zone, like the rest of the app.
