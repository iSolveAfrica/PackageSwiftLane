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
                    </section>

                    <!-- Alerts -->
                    <section id="dv_p_alerts" data-pane="alerts" role="tabpanel" aria-label="Alerts" class="swl-dev" hidden>
                        <div class="swl-dev-grid is-4" id="dv_al_kpis"></div>
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
