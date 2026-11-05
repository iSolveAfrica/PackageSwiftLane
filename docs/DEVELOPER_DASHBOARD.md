# Developer dashboard

`developer.php` is a live DevOps view of this installation: infrastructure, services, logs, deployments, architecture and alerts. Every value on it is measured on the server when the page asks for it. Nothing is sample data.

## Who can open it

- **Super admins only**, and each one must be **assigned**. This includes you.
- The first super admin who opens the page is assigned automatically, so nobody can be locked out. After that, an assigned developer assigns the others in the **Access** tab.
- At least one super admin always keeps access.
- The "Developer" side-menu link shows only to people who can open the page.
- Assignments live in `cdb_dev_access`.

## Tabs

| Tab | What it shows | Where the numbers come from |
|---|---|---|
| Overview | Uptime (30 days), web response time, error rate, throughput with 24 h sparklines. Also the architecture, system status, response-time and error-rate charts, recent deploys, active alerts and live logs | Everything below |
| Services | Every service with status, response time, error rate, throughput, instances and a detail line, plus healthy / degraded / down totals | See "Services" below |
| Logs | One stream: application activity, messages (e-mail, SMS, WhatsApp), King AI calls, PHP errors and alerts. Filter by source, level, time window and text. Live tail every 3 s. Clicking a line shows its details as JSON | `cdb_activity_log`, `cdb_message_log`, `cdb_cs_ai_runs`, the PHP `error_log` file, `cdb_dev_alerts` |
| Metrics | CPU, memory, disk, network I/O, disk I/O, response time, database latency, database queries/s, request rate and error rate over 1 h, 24 h, 7 d or 30 d | `cdb_dev_metrics` (one sample a minute) |
| Deployments | Success rate, deploys and rollbacks over 30 days, and the last deploy. Pipeline for the running version (code → tests → dependencies → deploy → health check) and the full history | The git reflog of this checkout (`.git/logs/HEAD`), `.phpunit.result.cache`, `composer.lock` vs `vendor/`, live health |
| Infrastructure | CPU, memory, disk and DB-connection gauges, the infrastructure map with live status per node, a memory chart, and server, database and PHP facts | `/proc`, `disk_*_space`, MySQL `SHOW GLOBAL STATUS` |
| Alerts | Open / critical / warning / resolved-in-24 h totals, and every alert with its service, severity, when it started, how long it lasted and its context. Acknowledge and Resolve buttons | `cdb_dev_alerts` |
| Access | Super admins and who is assigned | `cdb_dev_access` |

The page has a light and a dark theme (dark by default). The choice is remembered per browser.

## Services and how their status is worked out

| Service | Check | Degraded / down when |
|---|---|---|
| Web application | Timed GET of `login.php` on the site URL (Tools › General) | slower than 1.5 s / no answer or HTTP 5xx |
| REST API v1 | Timed GET of `api/v1/` | slower than 1.5 s / no answer or HTTP 5xx |
| Database | 3 timed `SELECT 1`, MySQL status counters | ping over 250 ms / no connection |
| File storage | Free disk; `uploads`, `support_files`, `driver_files` writable | over 85 % full or not writable / over 95 % |
| Support worker | `worker_last_run` written by `cron/support_worker.php` | last run over 15 min ago / over 1 h |
| Delivery queue | `cdb_cs_outbox` | any dead messages, or more than 200 waiting |
| Metrics collector | Heartbeat from `cron/devops_collector.php` | last sample over 10 min ago |
| AI provider (King) | Key set; errors and latency of the last 24 h of `cdb_cs_ai_runs` | over 20 % errors (at least 5 calls) |
| E-mail, SMS, WhatsApp | `cdb_message_log`, last 24 h | over 20 % failed (at least 5) / everything failed |
| Payment gateways | Active rows in `cdb_met_payment` (configuration only; their APIs are not called) | none active: shown as "not set up" |

HTTP probes are cached for 15 s, so a 5 s refresh does not load the site.

**Error rate** is the share of actions that did not succeed in the activity log over the last 5 minutes. **Throughput** is actions per minute, from the same log. **Uptime** is the share of samples in 30 days where the site answered the probe; if no probe ran, it uses whether the database answered.

## Alerts

Alert rules are checked on every sample:
- Disk 85 % (warning) and 95 % (critical).
- Memory 92 %.
- CPU 90 %, or a 5-minute load of twice the core count (critical).
- Database down or slower than 250 ms.
- Any service degraded (warning) or down (critical).
- More than 10 % of actions failing (at least 10 actions).

An alert opens once and updates while the condition lasts. It resolves by itself when the condition clears. **Acknowledge** marks it as being handled; **Resolve** closes it by hand.

## Setup

1. Deploy the code. The tables (`cdb_dev_*`) are created on first use. The database user needs the CREATE privilege once.
2. Set the site URL in Tools › General so the web and API probes have something to call. The server needs the PHP curl extension and must be able to reach its own URL.
3. Add the collector to cron, so history, uptime and alerts keep going while nobody has the page open:

   ```
   * * * * * php /path/to/app/cron/devops_collector.php >/dev/null 2>&1
   ```

   Without it, samples are only taken while the page is open, at most one a minute. The Services tab shows the collector as "not set up" until it runs.
4. Optional: point PHP's `error_log` at a file the web server can read, so PHP errors appear in Logs.

## Limits

- CPU, memory, network and disk I/O come from Linux `/proc`. On other systems, or where `/proc` is hidden, they show "–".
- Request rate counts application actions (the activity log), not raw HTTP hits. The web server's access log is not read.
- Deployments come from git. A server updated by uploading files shows no history; it does show the running version once git is used.
- Payment gateways are checked for configuration only.
