<?php
// *************************************************************************
// * Customer Service reports (staff). Volume, response and resolution     *
// * times (median and 90th percentile), SLA attainment, ratings, King's    *
// * results, breakdowns and agents, compared with the previous period.    *
// * Every number has a definition at the bottom. CSV export of tickets.   *
// * Data: ajax/support/reports_ajax.php (dataJs/support_reports.js).      *
// *************************************************************************

$userData = $user->cdp_getUserData();
$ready = cdp_csEnsureTables();
$actions = '<a class="btn btn-secondary btn-sm" href="support_inbox.php"><iconify-icon icon="solar:inbox-linear"></iconify-icon> Inbox</a> '
    . '<button type="button" class="btn btn-primary btn-sm" id="cs_export"><iconify-icon icon="solar:download-linear"></iconify-icon> Export CSV</button>';
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title>Customer Service reports | <?php echo $core->site_name ?></title>
    <?php include 'views/inc/head_scripts.php'; ?>
</head>

<body>
    <?php include 'views/inc/preloader.php'; ?>
    <div id="main-wrapper">
        <?php include 'views/inc/topbar.php'; ?>
        <?php include 'views/inc/left_sidebar.php'; ?>

        <div class="page-wrapper">
            <?php cdp_pageHeader('Customer Service reports', ['icon' => 'solar:chart-2-linear', 'actions' => $actions]); ?>

            <div class="container-fluid">
                <div id="resultados_ajax"></div>
                <?php if (!$ready) : ?>
                    <div class="swl-cs-banner is-danger">The helpdesk tables could not be created.</div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form class="swl-cs-toolbar mb-0" id="cs_rep_form" autocomplete="off">
                            <label for="cs_rep_range" class="swl-cs-sr">Period</label>
                            <select id="cs_rep_range" class="custom-select">
                                <option value="7">Last 7 days</option>
                                <option value="30" selected>Last 30 days</option>
                                <option value="90">Last 90 days</option>
                                <option value="month">This month</option>
                                <option value="custom">Custom…</option>
                            </select>
                            <input type="date" class="form-control" id="cs_rep_from" aria-label="From" hidden>
                            <input type="date" class="form-control" id="cs_rep_to" aria-label="To" hidden>
                            <select class="custom-select" data-rf="channel" aria-label="Channel"><option value="">All channels</option></select>
                            <select class="custom-select" data-rf="category" aria-label="Topic"><option value="">All topics</option></select>
                            <select class="custom-select" data-rf="priority" aria-label="Priority"><option value="">All priorities</option></select>
                            <select class="custom-select" data-rf="team_id" aria-label="Team"><option value="">All teams</option></select>
                            <select class="custom-select" data-rf="assignee_id" aria-label="Agent"><option value="">All agents</option></select>
                        </form>
                    </div>
                </div>

                <p class="swl-cs-small swl-cs-muted" id="cs_rep_period"></p>
                <div class="swl-cs-kpis" id="cs_kpis"></div>

                <div class="row">
                    <div class="col-12 col-xl-8">
                        <div class="card"><div class="card-body">
                            <div class="swl-cs-card-head"><h5 class="card-title"><iconify-icon icon="solar:chart-2-linear" aria-hidden="true"></iconify-icon> Volume and outcomes per day</h5><span class="swl-cs-small swl-cs-muted" id="cs_daily_note"></span></div>
                            <div id="cs_chart_daily" class="swl-cs-chart" role="img" aria-label="Tickets created, resolved, solved by King and handed to a person, per day"></div>
                        </div></div>
                    </div>
                    <div class="col-12 col-xl-4">
                        <div class="card"><div class="card-body">
                            <div class="swl-cs-card-head"><h5 class="card-title"><iconify-icon icon="solar:magic-stick-3-linear" aria-hidden="true"></iconify-icon> Where King's chats ended</h5></div>
                            <div id="cs_chart_ends" class="swl-cs-chart swl-cs-chart-sm" role="img" aria-label="Where King's chats ended"></div>
                            <div id="cs_king"></div>
                        </div></div>
                    </div>
                    <div class="col-12 col-xl-5">
                        <div class="card"><div class="card-body">
                            <div class="swl-cs-card-head"><h5 class="card-title"><iconify-icon icon="solar:shield-check-linear" aria-hidden="true"></iconify-icon> Service health</h5></div>
                            <div id="cs_chart_health" class="swl-cs-chart swl-cs-chart-sm" role="img" aria-label="Service health percentages"></div>
                        </div></div>
                    </div>
                    <div class="col-12 col-xl-7">
                        <div class="card"><div class="card-body">
                            <div class="swl-cs-card-head"><h5 class="card-title"><iconify-icon icon="solar:hand-shake-linear" aria-hidden="true"></iconify-icon> Why chats went to a person</h5></div>
                            <div id="cs_chart_reasons" class="swl-cs-chart swl-cs-chart-sm" role="img" aria-label="Reasons chats were handed to a person"></div>
                        </div></div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-4"><div class="card"><div class="card-body"><h5 class="card-title"><iconify-icon icon="solar:global-linear" aria-hidden="true"></iconify-icon> By channel</h5><div id="cs_by_channel"></div></div></div></div>
                    <div class="col-12 col-md-6 col-xl-4"><div class="card"><div class="card-body"><h5 class="card-title"><iconify-icon icon="solar:tag-linear" aria-hidden="true"></iconify-icon> By topic</h5><div id="cs_by_category"></div></div></div></div>
                    <div class="col-12 col-md-6 col-xl-4"><div class="card"><div class="card-body"><h5 class="card-title"><iconify-icon icon="solar:users-group-rounded-linear" aria-hidden="true"></iconify-icon> By team</h5><div id="cs_by_team"></div></div></div></div>
                    <div class="col-12 col-md-6 col-xl-4"><div class="card"><div class="card-body"><h5 class="card-title"><iconify-icon icon="solar:hourglass-linear" aria-hidden="true"></iconify-icon> Open backlog by age</h5><div id="cs_backlog"></div></div></div></div>
                    <div class="col-12 col-md-6 col-xl-4"><div class="card"><div class="card-body"><h5 class="card-title"><iconify-icon icon="solar:bolt-linear" aria-hidden="true"></iconify-icon> Delivery and automation</h5><div id="cs_ops"></div></div></div></div>
                    <div class="col-12 col-xl-6">
                        <div class="card"><div class="card-body">
                            <div class="swl-cs-card-head"><h5 class="card-title"><iconify-icon icon="solar:users-group-rounded-linear" aria-hidden="true"></iconify-icon> Agent workload</h5></div>
                            <div id="cs_chart_agents" class="swl-cs-chart" role="img" aria-label="Tickets assigned and resolved per agent"></div>
                        </div></div>
                    </div>
                    <div class="col-12 col-xl-6">
                        <div class="card"><div class="card-body">
                            <div class="swl-cs-card-head"><h5 class="card-title"><iconify-icon icon="solar:users-group-two-rounded-linear" aria-hidden="true"></iconify-icon> Agents</h5></div>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead><tr><th>Agent</th><th>Assigned</th><th>Resolved</th><th>Median first reply</th><th>Rating</th></tr></thead>
                                    <tbody id="cs_agents"></tbody>
                                </table>
                            </div>
                        </div></div>
                    </div>
                    <div class="col-12">
                        <div class="card"><div class="card-body">
                            <h5 class="card-title">How these numbers are worked out</h5>
                            <dl class="swl-cs-defs mb-0">
                                <dt>Tickets</dt><dd>Tickets created in the period that match the filters, including staff requests. Spam, trash and merged tickets are left out.</dd>
                                <dt>First reply</dt><dd>From the ticket being created to the first public reply by a person (King's answers do not count). Median: half were faster. 90th percentile: 9 in 10 were faster.</dd>
                                <dt>Resolution time</dt><dd>From creation to the latest resolution, for tickets resolved in the period, in elapsed calendar time.</dd>
                                <dt>SLA met</dt><dd>Of tickets with a deadline that has passed or been met, the share that met it. A missed deadline stays missed even if the ticket is fixed later. Paused time (waiting on the customer) is excluded when pausing is on.</dd>
                                <dt>Solved first time</dt><dd>Resolved tickets that were never reopened.</dd>
                                <dt>Rating</dt><dd>Share of ratings that were 4 or 5 stars, out of the ratings received (with how many were asked).</dd>
                                <dt>King solved</dt><dd>Chats where King answered and the customer never needed a person. "Confirmed" means the customer pressed "This solved it"; the rest simply ended. Handed over: King passed the chat to a person.</dd>
                                <dt>Times</dt><dd id="cs_basis">Server time zone.</dd>
                            </dl>
                        </div></div>
                    </div>
                </div>
            </div>

            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <?php include 'views/support/_vocab.php'; ?>
    <script src="<?= cdp_asset('assets/css_main_swiftlane/js/apexcharts.min.js') ?>"></script>
    <script src="<?= cdp_asset('dataJs/support_common.js') ?>"></script>
    <script src="<?= cdp_asset('dataJs/support_reports.js') ?>"></script>
</body>

</html>
