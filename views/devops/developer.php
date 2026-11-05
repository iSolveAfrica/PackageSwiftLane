<?php
// *************************************************************************
// * Developer dashboard (assigned super admins). Tabs: Overview,          *
// * Services, Logs, Metrics, Deployments, Infrastructure, Alerts, Access. *
// * Every value is live: ajax/devops/devops_ajax.php measures it on each  *
// * refresh (dataJs/devops.js), helpers/devops.php holds the checks.      *
// *************************************************************************
require_once __DIR__ . '/../../helpers/theme.php';
$userData = $user->cdp_getUserData();
$actions = '<span class="swl-dev-head-state" id="dv_head_state" role="status" aria-live="polite"><span class="swl-dev-state is-unknown">Checking…</span></span> '
    . '<button type="button" class="btn btn-secondary btn-sm swl-dev-live is-on" id="dv_live" aria-pressed="true">Live</button> '
    . '<button type="button" class="btn btn-secondary btn-sm" id="dv_refresh" aria-label="Refresh now"><iconify-icon icon="solar:refresh-linear" aria-hidden="true"></iconify-icon></button> '
    . cdp_themeToggle();
$tabs = [
    'overview' => ['Overview', 'solar:widget-5-linear'],
    'services' => ['Services', 'solar:server-square-linear'],
    'logs' => ['Logs', 'solar:document-text-linear'],
    'metrics' => ['Metrics', 'solar:chart-square-linear'],
    'deployments' => ['Deployments', 'solar:rocket-2-linear'],
    'infrastructure' => ['Infrastructure', 'solar:structure-linear'],
    'alerts' => ['Alerts', 'solar:bell-bing-linear'],
    'users' => ['User activity', 'solar:users-group-two-rounded-linear'],
    'data' => ['System data', 'solar:database-linear'],
    'ai' => ['AI diagnostics', 'solar:magic-stick-3-linear'],
    'access' => ['Access', 'solar:shield-user-linear'],
];
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title>Developer | <?php echo $core->site_name ?></title>
    <?php cdp_themeBoot('dark'); ?>
    <?php include 'views/inc/head_scripts.php'; ?>
</head>

<body>
    <?php include 'views/inc/preloader.php'; ?>
    <div id="main-wrapper">
        <?php include 'views/inc/topbar.php'; ?>
        <?php include 'views/inc/left_sidebar.php'; ?>

        <div class="page-wrapper">
            <?php cdp_pageHeader('Developer', ['icon' => 'solar:code-square-linear', 'sub' => 'Live health of this installation', 'actions' => $actions]); ?>

            <div class="container-fluid">
                <div id="resultados_ajax"></div>
                <div class="swl-dev">
                    <nav class="swl-dev-tabs" role="tablist" aria-label="Developer sections" id="dv_tabs">
                        <?php $first = true; foreach ($tabs as $k => $t) : ?>
                            <button type="button" role="tab" data-tab="<?php echo $k; ?>" class="<?php echo $first ? 'is-on' : ''; ?>" aria-selected="<?php echo $first ? 'true' : 'false'; ?>" aria-controls="dv_p_<?php echo $k; ?>">
                                <iconify-icon icon="<?php echo $t[1]; ?>" aria-hidden="true"></iconify-icon><?php echo $t[0]; ?><?php if ($k === 'alerts') : ?><span class="n" id="dv_alert_n" hidden></span><?php endif; ?>
                            </button>
                        <?php $first = false; endforeach; ?>
                    </nav>

                    <!-- Overview -->
                    <section id="dv_p_overview" data-pane="overview" role="tabpanel" aria-label="Overview" class="swl-dev">
                        <div class="swl-dev-grid is-4" id="dv_kpis"></div>
                        <div class="swl-dev-grid is-main">
                            <div class="swl-dev">
                                <div class="swl-dev-card">
                                    <div class="swl-dev-card-h"><iconify-icon icon="solar:structure-linear" aria-hidden="true"></iconify-icon><h2>System architecture</h2>
                                        <div class="r"><button type="button" class="swl-dev-link" data-go="infrastructure">Infrastructure view</button></div></div>
                                    <div class="swl-dev-arch" id="dv_arch_small"></div>
                                </div>
                                <div class="swl-dev-grid is-2">
                                    <div class="swl-dev-card">
                                        <div class="swl-dev-card-h"><iconify-icon icon="solar:stopwatch-linear" aria-hidden="true"></iconify-icon><h2>Response time</h2>
                                            <div class="r"><label class="swl-cs-sr" for="dv_rt_range">Period</label><select class="custom-select" id="dv_rt_range" data-range><option value="1h">Last hour</option><option value="24h" selected>Last 24 hours</option><option value="7d">Last 7 days</option><option value="30d">Last 30 days</option></select></div></div>
                                        <div class="swl-dev-chart" id="dv_c_rt" role="img" aria-label="Web, API and database response time"></div>
                                    </div>
                                    <div class="swl-dev-card">
                                        <div class="swl-dev-card-h"><iconify-icon icon="solar:danger-triangle-linear" aria-hidden="true"></iconify-icon><h2>Error rate</h2>
                                            <div class="r"><span class="swl-dev-note">Failed actions, %</span></div></div>
                                        <div class="swl-dev-chart" id="dv_c_err" role="img" aria-label="Error rate"></div>
                                    </div>
                                </div>
                                <div class="swl-dev-grid is-2">
                                    <div class="swl-dev-card">
                                        <div class="swl-dev-card-h"><iconify-icon icon="solar:rocket-2-linear" aria-hidden="true"></iconify-icon><h2>Recent deployments</h2>
                                            <div class="r"><button type="button" class="swl-dev-link" data-go="deployments">View all</button></div></div>
                                        <div class="table-responsive"><table class="table swl-dev-table"><thead><tr><th>Commit</th><th>Change</th><th>Type</th><th>When</th></tr></thead><tbody id="dv_ov_deploys"></tbody></table></div>
                                    </div>
                                    <div class="swl-dev-card">
                                        <div class="swl-dev-card-h"><iconify-icon icon="solar:bell-bing-linear" aria-hidden="true"></iconify-icon><h2>Active alerts</h2>
                                            <div class="r"><button type="button" class="swl-dev-link" data-go="alerts">View all alerts</button></div></div>
                                        <div id="dv_ov_alerts"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="swl-dev">
                                <div class="swl-dev-card">
                                    <div class="swl-dev-card-h"><iconify-icon icon="solar:pulse-2-linear" aria-hidden="true"></iconify-icon><h2>System status</h2>
                                        <div class="r"><button type="button" class="swl-dev-link" data-go="services">View all</button></div></div>
                                    <ul class="swl-dev-status-list" id="dv_status"></ul>
                                    <p class="swl-dev-note mb-0 mt-2" id="dv_updated"></p>
                                </div>
                                <div class="swl-dev-card">
                                    <div class="swl-dev-card-h"><iconify-icon icon="solar:document-text-linear" aria-hidden="true"></iconify-icon><h2>Live logs</h2>
                                        <div class="r"><button type="button" class="swl-dev-link" data-go="logs">View all</button></div></div>
                                    <div class="swl-dev-term" id="dv_ov_logs" aria-live="off"></div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Services -->
                    <section id="dv_p_services" data-pane="services" role="tabpanel" aria-label="Services" class="swl-dev" hidden>
                        <div class="swl-dev-grid is-4" id="dv_svc_kpis"></div>
                        <div class="swl-dev-card">
                            <div class="swl-dev-toolbar">
                                <label class="swl-cs-sr" for="dv_svc_q">Search services</label>
                                <input type="search" class="form-control grow" id="dv_svc_q" placeholder="Search services…">
                                <label class="swl-cs-sr" for="dv_svc_layer">Layer</label>
                                <select class="custom-select" id="dv_svc_layer"><option value="">All layers</option><option value="app">Application</option><option value="data">Data</option><option value="jobs">Jobs</option><option value="external">External</option></select>
                                <label class="swl-cs-sr" for="dv_svc_status">Status</label>
                                <select class="custom-select" id="dv_svc_status"><option value="">All statuses</option><option value="operational">Operational</option><option value="degraded">Degraded</option><option value="down">Down</option><option value="not_configured">Not set up</option></select>
                            </div>
                            <div class="table-responsive"><table class="table swl-dev-table"><thead><tr><th>Service</th><th>Status</th><th>Response time</th><th>Error rate</th><th>Throughput</th><th>Instances</th><th>Detail</th></tr></thead><tbody id="dv_svc_rows"></tbody></table></div>
                            <p class="swl-dev-note mb-0 mt-2" id="dv_svc_foot"></p>
                        </div>
                    </section>

                    <!-- Logs -->
                    <section id="dv_p_logs" data-pane="logs" role="tabpanel" aria-label="Logs" class="swl-dev" hidden>
                        <div class="swl-dev-card">
                            <div class="swl-dev-toolbar">
                                <label class="swl-cs-sr" for="dv_log_src">Source</label>
                                <select class="custom-select" id="dv_log_src"><option value="">All sources</option><option value="activity">Application activity</option><option value="messages">Messages (e-mail, SMS, WhatsApp)</option><option value="ai">King AI</option><option value="php">PHP errors</option><option value="alerts">Alerts</option></select>
                                <label class="swl-cs-sr" for="dv_log_lvl">Level</label>
                                <select class="custom-select" id="dv_log_lvl"><option value="">All levels</option><option value="INFO">Info</option><option value="WARN">Warning</option><option value="ERROR">Error</option></select>
                                <label class="swl-cs-sr" for="dv_log_win">Time window</label>
                                <select class="custom-select" id="dv_log_win"><option value="15">Last 15 minutes</option><option value="60" selected>Last hour</option><option value="360">Last 6 hours</option><option value="1440">Last 24 hours</option><option value="10080">Last 7 days</option></select>
                                <label class="swl-cs-sr" for="dv_log_q">Search logs</label>
                                <input type="search" class="form-control grow" id="dv_log_q" placeholder="Search logs…">
                                <button type="button" class="btn btn-secondary btn-sm swl-dev-live is-on" id="dv_tail" aria-pressed="true">Live tail</button>
                                <span class="swl-dev-note" id="dv_log_n"></span>
                            </div>
                            <div class="swl-dev-chart" id="dv_c_loghist" style="min-height: 150px" role="img" aria-label="Log lines over time by level"></div>
                            <div class="swl-dev-logs">
                                <div class="swl-dev-logbox">
                                    <div class="table-responsive"><table class="table swl-dev-table"><thead><tr><th>Timestamp</th><th>Service</th><th>Level</th><th>Message</th></tr></thead><tbody id="dv_log_rows"></tbody></table></div>
                                </div>
                                <div class="swl-dev-card" id="dv_log_detail"><div class="swl-dev-empty"><iconify-icon icon="solar:document-text-linear" aria-hidden="true"></iconify-icon>Pick a line to see its details.</div></div>
                            </div>
                        </div>
                    </section>

                    <!-- Metrics -->
                    <section id="dv_p_metrics" data-pane="metrics" role="tabpanel" aria-label="Metrics" class="swl-dev" hidden>
                        <div class="swl-dev-metrics">
                            <div class="swl-dev-card"><nav class="swl-dev-metric-nav" id="dv_metric_nav" aria-label="Metric"></nav></div>
                            <div class="swl-dev-card">
                                <div class="swl-dev-card-h"><iconify-icon icon="solar:chart-square-linear" aria-hidden="true"></iconify-icon><h2 id="dv_metric_title">CPU usage</h2>
                                    <div class="r"><label class="swl-cs-sr" for="dv_m_range">Period</label><select class="custom-select" id="dv_m_range" data-range><option value="1h">Last hour</option><option value="24h" selected>Last 24 hours</option><option value="7d">Last 7 days</option><option value="30d">Last 30 days</option></select></div></div>
                                <div class="swl-dev-chart" id="dv_c_metric" style="min-height: 340px" role="img" aria-label="Selected metric over time"></div>
                            </div>
                        </div>
                        <div class="swl-dev-grid is-4" id="dv_metric_tiles"></div>
                    </section>

                    <!-- Deployments -->
                    <section id="dv_p_deployments" data-pane="deployments" role="tabpanel" aria-label="Deployments" class="swl-dev" hidden>
                        <div class="swl-dev-grid is-4" id="dv_dep_kpis"></div>
                        <div class="swl-dev-grid is-2">
                            <div class="swl-dev-card"><div class="swl-dev-card-h"><iconify-icon icon="solar:chart-2-linear" aria-hidden="true"></iconify-icon><h2>Deployments per day</h2><div class="r"><span class="swl-dev-note">Last 30 days</span></div></div><div class="swl-dev-chart" id="dv_c_deploys" role="img" aria-label="Deployments per day"></div></div>
                            <div class="swl-dev-card"><div class="swl-dev-card-h"><iconify-icon icon="solar:pie-chart-2-linear" aria-hidden="true"></iconify-icon><h2>Change types</h2></div><div class="swl-dev-chart" id="dv_c_depkinds" role="img" aria-label="Change types"></div></div>
                        </div>
                        <div class="swl-dev-card">
                            <div class="swl-dev-card-h"><iconify-icon icon="solar:routing-2-linear" aria-hidden="true"></iconify-icon><h2>Deployment pipeline</h2><div class="r"><span class="swl-dev-note" id="dv_dep_head"></span></div></div>
                            <div class="swl-dev-pipe" id="dv_pipe"></div>
                        </div>
                        <div class="swl-dev-card">
                            <div class="swl-dev-card-h"><iconify-icon icon="solar:history-linear" aria-hidden="true"></iconify-icon><h2>Deployment history</h2><div class="r"><span class="swl-dev-note">From the git history of this server's checkout</span></div></div>
                            <div class="table-responsive"><table class="table swl-dev-table"><thead><tr><th>Commit</th><th>Type</th><th>Change</th><th>Author</th><th>Previous</th><th>When</th></tr></thead><tbody id="dv_dep_rows"></tbody></table></div>
                        </div>
                    </section>

                    <!-- Infrastructure -->
                    <section id="dv_p_infrastructure" data-pane="infrastructure" role="tabpanel" aria-label="Infrastructure" class="swl-dev" hidden>
                        <div class="swl-dev-grid is-4" id="dv_inf_kpis"></div>
                        <div class="swl-dev-card">
                            <div class="swl-dev-card-h"><iconify-icon icon="solar:structure-linear" aria-hidden="true"></iconify-icon><h2>Infrastructure map</h2><div class="r"><span class="swl-dev-note">Live status of every part, left to right as a request travels</span></div></div>
                            <div class="swl-dev-arch" id="dv_arch"></div>
                            <div class="swl-dev-legend"><span><i class="g"></i>Healthy</span><span><i class="a"></i>Degraded</span><span><i class="r"></i>Down</span><span><i class="n"></i>Not set up / idle</span></div>
                        </div>
                        <div class="swl-dev-grid is-3">
                            <div class="swl-dev-card"><div class="swl-dev-card-h"><iconify-icon icon="solar:pie-chart-2-linear" aria-hidden="true"></iconify-icon><h2>Memory</h2></div><div id="dv_c_mem" class="swl-dev-chart" role="img" aria-label="Memory used and available"></div></div>
                            <div class="swl-dev-card"><div class="swl-dev-card-h"><iconify-icon icon="solar:server-linear" aria-hidden="true"></iconify-icon><h2>Server</h2></div><dl class="swl-dev-kv" id="dv_srv_kv"></dl></div>
                            <div class="swl-dev-card"><div class="swl-dev-card-h"><iconify-icon icon="solar:database-linear" aria-hidden="true"></iconify-icon><h2>Database and PHP</h2></div><dl class="swl-dev-kv" id="dv_db_kv"></dl></div>
                        </div>
                        <div class="swl-dev-grid is-3">
                            <div class="swl-dev-card"><div class="swl-dev-card-h"><iconify-icon icon="solar:heart-pulse-linear" aria-hidden="true"></iconify-icon><h2>Health status</h2></div><div class="swl-dev-chart" id="dv_c_health" role="img" aria-label="Services by health"></div></div>
                            <div class="swl-dev-card"><div class="swl-dev-card-h"><iconify-icon icon="solar:cpu-bolt-linear" aria-hidden="true"></iconify-icon><h2>Top processes</h2><div class="r"><span class="swl-dev-note">By memory</span></div></div>
                                <div class="table-responsive"><table class="table swl-dev-table"><thead><tr><th>Process</th><th>PID</th><th>Memory</th><th>CPU avg</th></tr></thead><tbody id="dv_proc_rows"></tbody></table></div></div>
                            <div class="swl-dev-card"><div class="swl-dev-card-h"><iconify-icon icon="solar:history-linear" aria-hidden="true"></iconify-icon><h2>Recent events</h2></div><ul class="swl-dev-status-list" id="dv_inf_events"></ul></div>
                        </div>
                    </section>

                    <!-- Alerts -->
                    <section id="dv_p_alerts" data-pane="alerts" role="tabpanel" aria-label="Alerts" class="swl-dev" hidden>
                        <div class="swl-dev-grid is-4" id="dv_al_kpis"></div>
                        <div class="swl-dev-grid is-2">
                            <div class="swl-dev-card"><div class="swl-dev-card-h"><iconify-icon icon="solar:chart-2-linear" aria-hidden="true"></iconify-icon><h2>Alerts per day</h2><div class="r"><span class="swl-dev-note">By severity, last 14 days</span></div></div><div class="swl-dev-chart" id="dv_c_alday" role="img" aria-label="Alerts per day"></div></div>
                            <div class="swl-dev-card"><div class="swl-dev-card-h"><iconify-icon icon="solar:server-square-linear" aria-hidden="true"></iconify-icon><h2>Alerts by service</h2></div><div class="swl-dev-chart" id="dv_c_alsvc" role="img" aria-label="Alerts by service"></div></div>
                        </div>
                        <div class="swl-dev-card">
                            <div class="swl-dev-toolbar">
                                <label class="swl-cs-sr" for="dv_al_status">Status</label>
                                <select class="custom-select" id="dv_al_status"><option value="all">All alerts</option><option value="open" selected>Open</option><option value="resolved">Resolved</option></select>
                                <label class="swl-cs-sr" for="dv_al_q">Search alerts</label>
                                <input type="search" class="form-control grow" id="dv_al_q" placeholder="Search alerts…">
                            </div>
                            <div class="table-responsive"><table class="table swl-dev-table"><thead><tr><th>Alert</th><th>Service</th><th>Status</th><th>Severity</th><th>Triggered</th><th>Duration</th><th>Context</th><th><span class="swl-cs-sr">Actions</span></th></tr></thead><tbody id="dv_al_rows"></tbody></table></div>
                            <p class="swl-dev-note mb-0 mt-2">Alerts open and close by themselves on every check (every refresh, and every minute when the collector runs from cron). Acknowledge one to show you are on it.</p>
                        </div>
                    </section>

                    <!-- User activity -->
                    <section id="dv_p_users" data-pane="users" role="tabpanel" aria-label="User activity" class="swl-dev" hidden>
                        <div class="swl-dev-grid is-4" id="dv_u_kpis"></div>
                        <div class="swl-dev-grid is-main">
                            <div class="swl-dev-card"><div class="swl-dev-card-h"><iconify-icon icon="solar:chart-2-linear" aria-hidden="true"></iconify-icon><h2>Activity, last 24 hours</h2><div class="r"><span class="swl-dev-note">Actions per hour by module</span></div></div><div class="swl-dev-chart" id="dv_c_uhour" style="min-height: 300px" role="img" aria-label="Actions per hour by module"></div></div>
                            <div class="swl-dev-card"><div class="swl-dev-card-h"><iconify-icon icon="solar:pie-chart-2-linear" aria-hidden="true"></iconify-icon><h2>Actions by role</h2><div class="r"><span class="swl-dev-note">7 days</span></div></div><div class="swl-dev-chart" id="dv_c_urole" role="img" aria-label="Actions by role"></div></div>
                        </div>
                        <div class="swl-dev-grid is-2">
                            <div class="swl-dev-card"><div class="swl-dev-card-h"><iconify-icon icon="solar:calendar-linear" aria-hidden="true"></iconify-icon><h2>When people work</h2><div class="r"><span class="swl-dev-note">Actions by weekday and hour, 30 days</span></div></div><div class="swl-dev-chart" id="dv_c_uheat" style="min-height: 280px" role="img" aria-label="Activity heatmap"></div></div>
                            <div class="swl-dev-card"><div class="swl-dev-card-h"><iconify-icon icon="solar:graph-up-linear" aria-hidden="true"></iconify-icon><h2>Daily activity</h2><div class="r"><span class="swl-dev-note">Actions, people and problems, 30 days</span></div></div><div class="swl-dev-chart" id="dv_c_uday" style="min-height: 280px" role="img" aria-label="Daily activity"></div></div>
                        </div>
                        <div class="swl-dev-grid is-3">
                            <div class="swl-dev-card"><div class="swl-dev-card-h"><iconify-icon icon="solar:ranking-linear" aria-hidden="true"></iconify-icon><h2>Most active people</h2><div class="r"><span class="swl-dev-note">7 days</span></div></div><div class="swl-dev-chart" id="dv_c_utop" role="img" aria-label="Most active people"></div></div>
                            <div class="swl-dev-card"><div class="swl-dev-card-h"><iconify-icon icon="solar:check-circle-linear" aria-hidden="true"></iconify-icon><h2>Outcomes</h2><div class="r"><span class="swl-dev-note">7 days</span></div></div><div class="swl-dev-chart" id="dv_c_uout" role="img" aria-label="Action outcomes"></div></div>
                            <div class="swl-dev-card"><div class="swl-dev-card-h"><iconify-icon icon="solar:danger-triangle-linear" aria-hidden="true"></iconify-icon><h2>Actions that fail most</h2><div class="r"><span class="swl-dev-note">7 days</span></div></div><ul class="swl-dev-status-list" id="dv_u_failing"></ul></div>
                        </div>
                        <div class="swl-dev-grid is-main">
                            <div class="swl-dev-card"><div class="swl-dev-card-h"><iconify-icon icon="solar:user-check-linear" aria-hidden="true"></iconify-icon><h2>Online now</h2><div class="r"><span class="swl-dev-note">Active in the last 15 minutes</span></div></div>
                                <div class="table-responsive"><table class="table swl-dev-table"><thead><tr><th>Person</th><th>Role</th><th>Last action</th><th>Where</th><th>Device</th><th>IP</th><th>Actions</th><th>Seen</th></tr></thead><tbody id="dv_u_online"></tbody></table></div></div>
                            <div class="swl-dev-card"><div class="swl-dev-card-h"><iconify-icon icon="solar:global-linear" aria-hidden="true"></iconify-icon><h2>Busiest addresses</h2><div class="r"><span class="swl-dev-note">24 hours</span></div></div><ul class="swl-dev-status-list" id="dv_u_ips"></ul></div>
                        </div>
                        <div class="swl-dev-card">
                            <div class="swl-dev-card-h"><iconify-icon icon="solar:list-check-linear" aria-hidden="true"></iconify-icon><h2>Everything people did</h2><div class="r"><span class="swl-dev-note" id="dv_u_feed_n"></span></div></div>
                            <div class="swl-dev-toolbar">
                                <label class="swl-cs-sr" for="dv_u_mod">Module</label><select class="custom-select" id="dv_u_mod"><option value="">All modules</option></select>
                                <label class="swl-cs-sr" for="dv_u_out">Outcome</label><select class="custom-select" id="dv_u_out"><option value="">All outcomes</option><option value="success">Succeeded</option><option value="problem">Failed or denied</option><option value="denied">Denied</option></select>
                                <label class="swl-cs-sr" for="dv_u_q">Search activity</label><input type="search" class="form-control grow" id="dv_u_q" placeholder="Search person, action, record or IP…">
                                <button type="button" class="btn btn-secondary btn-sm" id="dv_u_clear" hidden><iconify-icon icon="solar:close-circle-linear" aria-hidden="true"></iconify-icon> <span>Clear person</span></button>
                            </div>
                            <div id="dv_u_detail"></div>
                            <div class="swl-dev-logbox"><div class="table-responsive"><table class="table swl-dev-table"><thead><tr><th>When</th><th>Person</th><th>Module</th><th>Action</th><th>Outcome</th><th>Record</th><th>Request</th><th>IP</th></tr></thead><tbody id="dv_u_feed"></tbody></table></div></div>
                        </div>
                    </section>

                    <!-- System data -->
                    <section id="dv_p_data" data-pane="data" role="tabpanel" aria-label="System data" class="swl-dev" hidden>
                        <div class="swl-dev-grid is-4" id="dv_d_kpis"></div>
                        <div class="swl-dev-card"><div class="swl-dev-card-h"><iconify-icon icon="solar:chart-2-linear" aria-hidden="true"></iconify-icon><h2>New records per day</h2><div class="r"><span class="swl-dev-note">Last 30 days</span></div></div><div class="swl-dev-chart" id="dv_c_dareas" style="min-height: 320px" role="img" aria-label="New records per day by area"></div></div>
                        <div class="swl-dev-grid is-3">
                            <div class="swl-dev-card"><div class="swl-dev-card-h"><iconify-icon icon="solar:letter-linear" aria-hidden="true"></iconify-icon><h2>Messages by channel</h2><div class="r"><span class="swl-dev-note">30 days</span></div></div><div class="swl-dev-chart" id="dv_c_dmsg" role="img" aria-label="Messages by channel"></div></div>
                            <div class="swl-dev-card"><div class="swl-dev-card-h"><iconify-icon icon="solar:magic-stick-3-linear" aria-hidden="true"></iconify-icon><h2>AI usage</h2><div class="r"><span class="swl-dev-note">Calls and tokens, 30 days</span></div></div><div class="swl-dev-chart" id="dv_c_dai" role="img" aria-label="AI calls and tokens"></div></div>
                            <div class="swl-dev-card"><div class="swl-dev-card-h"><iconify-icon icon="solar:pie-chart-2-linear" aria-hidden="true"></iconify-icon><h2>Database by size</h2></div><div class="swl-dev-chart" id="dv_c_dsize" role="img" aria-label="Largest tables by size"></div></div>
                        </div>
                        <div class="swl-dev-card"><div class="swl-dev-card-h"><iconify-icon icon="solar:database-linear" aria-hidden="true"></iconify-icon><h2>Largest tables</h2></div>
                            <div class="table-responsive"><table class="table swl-dev-table"><thead><tr><th>Table</th><th>Rows (approx.)</th><th>Data</th><th>Indexes</th><th>Engine</th><th>Last write</th></tr></thead><tbody id="dv_d_tables"></tbody></table></div></div>
                    </section>

                    <!-- AI diagnostics -->
                    <section id="dv_p_ai" data-pane="ai" role="tabpanel" aria-label="AI diagnostics" class="swl-dev" hidden>
                        <div class="swl-dev-grid is-main">
                            <div class="swl-dev-card">
                                <div class="swl-dev-card-h"><iconify-icon icon="solar:magic-stick-3-linear" aria-hidden="true"></iconify-icon><h2>Ask for a diagnosis</h2><div class="r" id="dv_ai_state"></div></div>
                                <form id="dv_ai_form" class="swl-dev-aiform" autocomplete="off">
                                    <div class="form-group"><label for="dv_ai_q">What should it look into?</label>
                                        <textarea class="form-control" id="dv_ai_q" rows="3" maxlength="2000" placeholder="e.g. Why are SMS failing since this morning? Or leave empty for a general health check."></textarea></div>
                                    <fieldset class="form-group"><legend class="swl-dev-legend-t">Evidence to collect</legend>
                                        <div class="swl-dev-checks">
                                            <label><input type="checkbox" name="scope" value="health" checked> <iconify-icon icon="solar:pulse-2-linear" aria-hidden="true"></iconify-icon> Health and services</label>
                                            <label><input type="checkbox" name="scope" value="alerts" checked> <iconify-icon icon="solar:bell-bing-linear" aria-hidden="true"></iconify-icon> Alerts</label>
                                            <label><input type="checkbox" name="scope" value="errors" checked> <iconify-icon icon="solar:danger-triangle-linear" aria-hidden="true"></iconify-icon> Errors and failures</label>
                                            <label><input type="checkbox" name="scope" value="deploys" checked> <iconify-icon icon="solar:rocket-2-linear" aria-hidden="true"></iconify-icon> Deployments</label>
                                            <label><input type="checkbox" name="scope" value="activity"> <iconify-icon icon="solar:users-group-two-rounded-linear" aria-hidden="true"></iconify-icon> User activity</label>
                                            <label><input type="checkbox" name="scope" value="code" checked> <iconify-icon icon="solar:code-file-linear" aria-hidden="true"></iconify-icon> Read the code errors point to</label>
                                        </div></fieldset>
                                    <div class="swl-dev-grid is-2">
                                        <div class="form-group mb-0"><label for="dv_ai_win">Look back</label><select class="custom-select" id="dv_ai_win"><option value="60">1 hour</option><option value="360">6 hours</option><option value="1440" selected>24 hours</option><option value="4320">3 days</option><option value="10080">7 days</option></select></div>
                                        <div class="form-group mb-0"><label for="dv_ai_paths">Also read these files <span class="swl-dev-note">(optional, one per line, file.php:120 for a line)</span></label>
                                            <textarea class="form-control" id="dv_ai_paths" rows="2" placeholder="helpers/support.php:1200"></textarea></div>
                                    </div>
                                    <div class="swl-dev-toolbar mt-3 mb-0">
                                        <button type="submit" class="btn btn-primary" id="dv_ai_go"><iconify-icon icon="solar:magic-stick-3-linear" aria-hidden="true"></iconify-icon> Generate report</button>
                                        <span class="swl-dev-note">Secrets, e-mail addresses and tokens are masked before anything is sent. Configuration files are never read.</span>
                                    </div>
                                </form>
                            </div>
                            <div class="swl-dev-card">
                                <div class="swl-dev-card-h"><iconify-icon icon="solar:folder-with-files-linear" aria-hidden="true"></iconify-icon><h2>Saved reports</h2></div>
                                <ul class="swl-dev-status-list" id="dv_ai_list"></ul>
                            </div>
                        </div>
                        <div class="swl-dev-card" id="dv_ai_out_card" hidden>
                            <div class="swl-dev-card-h"><iconify-icon icon="solar:document-text-linear" aria-hidden="true"></iconify-icon><h2 id="dv_ai_title">Report</h2>
                                <div class="r"><a class="btn btn-secondary btn-sm" id="dv_ai_dl" href="#"><iconify-icon icon="solar:download-linear" aria-hidden="true"></iconify-icon> Report (.md)</a>
                                    <a class="btn btn-secondary btn-sm" id="dv_ai_dle" href="#"><iconify-icon icon="solar:code-file-linear" aria-hidden="true"></iconify-icon> Evidence (.json)</a></div></div>
                            <div class="swl-dev-md" id="dv_ai_out" aria-live="polite"></div>
                        </div>
                    </section>

                    <!-- Access -->
                    <section id="dv_p_access" data-pane="access" role="tabpanel" aria-label="Access" class="swl-dev" hidden>
                        <div class="swl-dev-card">
                            <div class="swl-dev-card-h"><iconify-icon icon="solar:shield-user-linear" aria-hidden="true"></iconify-icon><h2>Who can open this page</h2></div>
                            <p class="swl-dev-note">Only super admins can be assigned, and each one has to be assigned, including you. At least one must keep access.</p>
                            <div class="table-responsive"><table class="table swl-dev-table"><thead><tr><th>Super admin</th><th>E-mail</th><th>Assigned</th><th>Since</th><th><span class="swl-cs-sr">Change</span></th></tr></thead><tbody id="dv_acc_rows"></tbody></table></div>
                        </div>
                    </section>
                </div>
            </div>

            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <script src="<?= cdp_asset('assets/css_main_swiftlane/js/apexcharts.min.js') ?>"></script>
    <script src="<?= cdp_asset('dataJs/swl_theme.js') ?>"></script>
    <script src="<?= cdp_asset('dataJs/devops.js') ?>"></script>
</body>

</html>
