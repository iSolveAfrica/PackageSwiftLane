# Customer Service and King

The Customer Service module is a helpdesk inside Swift Lane. Customers chat with **King**, the AI agent, or with a person. Staff work every case from one inbox. It sits beside the AI Assistant and uses the same AI provider settings.

## Pages

| Page | Who | What |
|---|---|---|
| `support.php` (Help & Support) | Customers | Chat with King or a person, the customer's requests, earlier chats, help articles, a new-request form with evidence upload, request detail with reply and rating. Staff who open it are sent to the inbox, or to the ticket when the link carries `?t=ID`. |
| Chat button (every page) | Customers | Bottom-right launcher (`views/support/widget.php`). It loads nothing until it is opened. It is not shown on `support.php`. An unread badge counts new replies and requests waiting on the customer. |
| `support_inbox.php` | Staff (rank 2+) | Views with counts (queues, statuses, deadlines, archive, spam, trash) and saved views (personal, or shared by an admin). Search covers reference, subject, customer, locker, tracking number and message text. Filters, sorting, bulk changes, pagination (`pg`), and a new ticket for phone or walk-in cases and staff requests. |
| `support_ticket.php?id=` | Staff | Timeline with internal notes. Reply or note composer with saved replies (`/shortcut`), attachments, "status after sending", AI draft and AI rewrite (preview only). Accept, take over, return to queue or hand back to King. Details panel: status, priority, language, topic, team, assignee, SLA clocks, tags, linked records, tasks, approvals, manual automations, followers. Also Customer 360, AI (summary, classification, help-article draft) and History tabs. Merge, archive, spam and trash. |
| `support_settings.php` | Admins change settings; agents keep their own saved replies and draft articles | King and general settings, teams and routing, automation rules with a dry-run tester and run history, service levels, saved replies, knowledge base, channel health and the delivery queue. |
| `support_reports.php` | Staff | Volume, first reply and resolution (median and p90), SLA met, solved first time, ratings, wait for a person, King results, breakdowns, backlog age, agents, delivery and automation, all compared with the previous period. CSV export. Every metric has a written definition on the page. |

Links: "Customer Service" in the staff sidebar, "Help & Support" in the customer sidebar, and "Customer Service" in Tools for admins. Bell notifications use `shipping_type = 9` and open the ticket.

## Setup

1. **Deploy the code.** Tables (`cdb_cs_*`) are created on first use, like the AI Assistant's. The database user needs the CREATE privilege once. A default team, "Customer Support", is seeded.
2. **AI provider.** Set the provider, key and model on Tools › AI Assistant. King uses the same settings.
3. **Switch King on.** Go to Settings › King & general. The modes are:
   - **Off**: no AI anywhere.
   - **Helps agents only**: drafts, summaries and classification for staff.
   - **Answers customers**: King answers first and hands over to a person.

   King starts **off**.
4. **Teams.** Add staff to a team, and set their capacity and availability. Choose round robin, least loaded or manual assignment.
5. **Service levels.** The shipped targets are provisional. Agree them with the business and tick "approved".
6. **Knowledge base.** King answers policy questions only from published articles, so publish the policies it may quote (storage, fees, claims and so on).
7. **Worker (cron)**, every 5 minutes:

   ```
   */5 * * * * php /path/to/app/cron/support_worker.php >/dev/null 2>&1
   ```

   The worker checks SLA deadlines, runs "no customer reply" rules, auto-closes (only when the days setting is above 0), and sends e-mail, WhatsApp and SMS copies and rating requests through the outbox (retries with backoff, dead after 5 attempts). Settings › Channels & delivery shows when it last ran and lists failed messages with a Retry button. In-app chat and bell notifications work without the worker.
8. **Attachments** are stored in `support_files/`. It is git-ignored and protected by `.htaccess`. Files are served only through `ajax/support/file.php`, which checks access. Images and PDFs from customers, up to 8 MB.

## How it works

- **Ownership of a chat** is separate from the ticket status: `ai_active → handoff_queued → human_active → resolved`. Moves are conditional updates, and King commits its answer inside a transaction that re-checks ownership. So once a person takes the chat, or the customer asks for one, a late King answer is discarded and logged, never sent.
- **King's scope.**
  - The customer's identity always comes from the session. King's tools see only that customer's shipments, packages, pickups, invoices and tickets, plus published help.
  - Sources and tool traces stay on the staff side.
  - Refunds, waivers, claims, ownership and address changes become **approval requests**, which only administrators decide. A decision is bound to a hash of the request and expires after 7 days. The change itself is made on the page that owns it.
  - If the provider fails, King hits its turn limit or the daily budget runs out, the chat goes to a person instead of an invented answer.
- **Internal notes** never reach the customer, notifications or the customer-facing AI. Reply drafts are built without notes. Summaries for staff may use them.
- **Collisions.** A reply is refused if a colleague replied after the agent opened the ticket (they can still choose "send anyway"). Field changes carry the ticket version. Viewers and typing are shown live.
- **Automation.** A rule is a trigger, ALL or ANY conditions, then actions. Rules have versions, a dry-run tester against a real ticket and a run log. Each rule has a per-ticket cap, events are deduplicated, and rules that trigger rules stop at 3 levels.
- **Ratings** are scheduled after resolving and skipped if the ticket reopens first.

## Tests

```
CS_TEST_DB=swl_test CS_TEST_USER=swl CS_TEST_PASS=swl php tests/support/support_test.php
```

The test needs a disposable MySQL or MariaDB database with the app schema. It starts `tests/support/fake_claude.php` on port 8765, so no real AI is called. It covers customer scoping (A02), the handover race (A07 and A08), provider failure (A09 and A29), notes staying internal (A10), approvals (A11 and A12), the survey being suppressed on reopen (A20), rule loops and the dry run (A21), stale drafts (A23), report reconciliation (A24) and CSV formula injection. `node scripts/check_page_shell.js` checks the page pattern.

## Traceability to the brief

| Brief | Where |
|---|---|
| Unified inbox, views, filters, bulk (screenshots: inbox) | `support_inbox.php`, `cdp_csInboxViews/InboxList` |
| Conversation detail, notes, AI reply suggestions, summaries (screenshots: ticket view) | `support_ticket.php`, `cdp_csCopilotDraft/Summary/Refine` |
| Customer 360 sidebar | Ticket › Customer, `cdp_csCustomer360` |
| Tags, language detection | Ticket › AI › Suggest, `cdp_csCopilotClassify` (a language set by hand is never overwritten) |
| Routing rules, automation (screenshots: rules) | Settings › Teams / Automation, `cdp_csRules*`, `cdp_csAutoAssign` |
| Reports (screenshots: reports) | `support_reports.php`, `cdp_csReport` |
| AI customer agent, grounded answers, execution boundary (§8) | `helpers/support_king.php` |
| Human handover and ownership (§10) | `cdp_csHandover`, `cdp_csAccept`, `cdp_csOwnerMove` |
| SLAs and teams (§14) | `cdp_csSlaTargets/SlaSweep/SlaClock`, Settings › Service levels |
| Notifications (§12) | `cdp_csNotifyCustomer`, outbox, `cron/support_worker.php` |
| Knowledge (§16) | Settings › Knowledge base, Help & Support › Help |

The look follows Swift Lane's own design system (`swiftlane-ds.css` §25, tokens only). It is not a copy of the reference screenshots.

## Known limits

- **No inbound e-mail, WhatsApp or SMS into tickets yet.** That needs a provider webhook with a signing secret. Customers reach support through the web chat and the request form. Outbound copies use the app's existing senders.
- **Approvals are recorded, not executed.** An approved refund or address change is made on its own page by staff.
- **Times use the server time zone.** SLA clocks run in calendar time; business-hours calendars and holidays are not modelled.
- **Delegated business-account access (A26) is not modelled.** Each customer sees only their own account.
- **Logged-out visitors cannot chat.** The chat and the request form are for signed-in customers.

## Look and theme (November 2026)

The inbox, ticket, reports, settings and Help & Support pages have a light and a dark theme. The toggle is in the page header, and the choice is kept per browser (`helpers/theme.php`, `dataJs/swl_theme.js`, swiftlane-ds.css §26). Charts follow the theme.

**Inbox**
- **Needs attention** (waiting for a person, SLA at risk, overdue) is always at the top. Its counts light up amber or red while something is waiting.
- The other groups (Tickets, Status, Elsewhere) collapse, and the browser remembers which are collapsed.
- Each row shows:
  - the customer's avatar and e-mail
  - the subject, with a mark when the customer replied
  - a channel icon and a priority arrow
  - who handles it: King, a person, "waiting for a person" or unassigned
  - a status pill with an icon
  - an SLA bar
- The summary tiles and the 14-day chart sit above the list.
- Keyboard: J and K move between tickets, Enter opens one, and / jumps to search.

**Ticket**
- The Details tab starts with King's summary.
- Below it are **Actions on this account**. Refund, fee waiver, address change and damage claim open the approval request with the right kind already chosen. Draft with King, internal note, create task and follow are also there.
