<?php
// *************************************************************************
// * Customer Service reports (staff). Volume, response and resolution     *
// * times (median and 90th percentile), SLA attainment, ratings, King's    *
// * results, breakdowns and agents, compared with the previous period.    *
// * Every number has a definition at the bottom. CSV export of tickets.   *
// * Data: ajax/support/reports_ajax.php (dataJs/support_reports.js).      *
// *************************************************************************

require_once __DIR__ . '/../../helpers/theme.php';
$userData = $user->cdp_getUserData();
$ready = cdp_csEnsureTables();
$actions = '<a class="btn btn-secondary btn-sm" href="support_inbox.php"><iconify-icon icon="solar:inbox-linear"></iconify-icon> Inbox</a> '
    . '<button type="button" class="btn btn-primary btn-sm" id="cs_export"><iconify-icon icon="solar:download-linear"></iconify-icon> Export CSV</button>' . ' ' . cdp_themeToggle();
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title>Customer Service reports | <?php echo $core->site_name ?></title>
    <?php cdp_themeBoot('light'); ?>
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

                <div class="swl-desk-wrap">
                <div class="swl-rp">
                    <form class="swl-rp-toolbar" id="cs_rep_form" autocomplete="off">
                        <div class="swl-rp-seg" role="group" aria-label="Period">
                            <button type="button" data-range="7">Last 7 days</button>
                            <button type="button" data-range="30" class="is-on" aria-pressed="true">Last 30 days</button>
                            <button type="button" data-range="90">Last 90 days</button>
                            <button type="button" data-range="month">This month</button>
                            <button type="button" data-range="custom">Custom</button>
                        </div>
                        <label for="cs_rep_range" class="swl-cs-sr">Period</label>
                        <select id="cs_rep_range" class="swl-cs-sr" tabindex="-1" aria-hidden="true">
                            <option value="7">Last 7 days</option>
                            <option value="30" selected>Last 30 days</option>
                            <option value="90">Last 90 days</option>
                            <option value="month">This month</option>
                            <option value="custom">Custom…</option>
                        </select>
                        <input type="date" class="swl-rp-in" id="cs_rep_from" aria-label="From" hidden>
                        <input type="date" class="swl-rp-in" id="cs_rep_to" aria-label="To" hidden>
                        <span class="grow"></span>
                        <span class="swl-rp-sel"><iconify-icon icon="solar:global-linear" aria-hidden="true"></iconify-icon><select data-rf="channel" aria-label="Channel"><option value="">All channels</option></select></span>
                        <span class="swl-rp-sel"><iconify-icon icon="solar:tag-linear" aria-hidden="true"></iconify-icon><select data-rf="category" aria-label="Topic"><option value="">All topics</option></select></span>
                        <span class="swl-rp-sel"><iconify-icon icon="solar:flag-linear" aria-hidden="true"></iconify-icon><select data-rf="priority" aria-label="Priority"><option value="">All priorities</option></select></span>
                        <span class="swl-rp-sel"><iconify-icon icon="solar:users-group-rounded-linear" aria-hidden="true"></iconify-icon><select data-rf="team_id" aria-label="Team"><option value="">All teams</option></select></span>
                        <span class="swl-rp-sel"><iconify-icon icon="solar:user-linear" aria-hidden="true"></iconify-icon><select data-rf="assignee_id" aria-label="Agent"><option value="">All agents</option></select></span>
                    </form>
                    <p class="swl-rp-period" id="cs_rep_period"></p>

                    <div class="swl-rp-kpis" id="cs_kpis"></div>

                    <div class="swl-rp-row is-21">
                        <section class="swl-rp-card">
                            <div class="swl-rp-h"><iconify-icon icon="solar:graph-up-linear" class="is-blue" aria-hidden="true"></iconify-icon><h2>Volume and outcomes per day</h2><span class="r" id="cs_daily_note"></span></div>
                            <div id="cs_chart_daily" class="swl-rp-chart" role="img" aria-label="Tickets created, resolved, solved by King and handed to a person, per day"></div>
                        </section>
                        <section class="swl-rp-card">
                            <div class="swl-rp-h"><iconify-icon icon="solar:magic-stick-3-linear" class="is-violet" aria-hidden="true"></iconify-icon><h2>Where King's chats ended</h2></div>
                            <div id="cs_chart_ends" class="swl-rp-chart is-sm" role="img" aria-label="Where King's chats ended"></div>
                            <div class="swl-rp-kingnote" id="cs_king"></div>
                        </section>
                    </div>

                    <div class="swl-rp-row is-21">
                        <section class="swl-rp-card">
                            <div class="swl-rp-h"><iconify-icon icon="solar:users-group-two-rounded-linear" class="is-teal" aria-hidden="true"></iconify-icon><h2>Agents</h2><span class="r">Bar: resolved out of assigned</span></div>
                            <div class="table-responsive">
                                <table class="table swl-rp-table mb-0">
                                    <thead><tr><th>Agent</th><th>Resolved / assigned</th><th>First reply</th><th>SLA met</th><th>Rating</th></tr></thead>
                                    <tbody id="cs_agents"></tbody>
                                </table>
                            </div>
                        </section>
                        <section class="swl-rp-card">
                            <div class="swl-rp-h"><iconify-icon icon="solar:transfer-horizontal-linear" class="is-amber" aria-hidden="true"></iconify-icon><h2>Why chats went to a person</h2></div>
                            <div id="cs_reasons_bars" class="swl-rp-bars is-amber"></div>
                        </section>
                    </div>

                    <div class="swl-rp-row is-3">
                        <section class="swl-rp-card"><div class="swl-rp-h"><iconify-icon icon="solar:global-linear" class="is-blue" aria-hidden="true"></iconify-icon><h2>By channel</h2></div><div id="cs_by_channel" class="swl-rp-chan"></div></section>
                        <section class="swl-rp-card"><div class="swl-rp-h"><iconify-icon icon="solar:hourglass-linear" class="is-red" aria-hidden="true"></iconify-icon><h2>Open backlog by age</h2></div><div id="cs_backlog" class="swl-rp-cols"></div></section>
                        <section class="swl-rp-card"><div class="swl-rp-h"><iconify-icon icon="solar:star-bold" class="is-star" aria-hidden="true"></iconify-icon><h2>Customer ratings</h2><span class="r" id="cs_rating_n"></span></div><div id="cs_ratings" class="swl-rp-stars"></div></section>
                    </div>

                    <h2 class="swl-rp-more">More detail</h2>
                    <div class="swl-rp-row is-21">
                        <section class="swl-rp-card"><div class="swl-rp-h"><iconify-icon icon="solar:users-group-rounded-linear" class="is-teal" aria-hidden="true"></iconify-icon><h2>Agent workload</h2></div><div id="cs_chart_agents" class="swl-rp-chart" role="img" aria-label="Tickets assigned and resolved per agent"></div></section>
                        <section class="swl-rp-card"><div class="swl-rp-h"><iconify-icon icon="solar:shield-check-linear" class="is-blue" aria-hidden="true"></iconify-icon><h2>Service health</h2></div><div id="cs_chart_health" class="swl-rp-chart is-sm" role="img" aria-label="Service health percentages"></div></section>
                    </div>
                    <div class="swl-rp-row is-3">
                        <section class="swl-rp-card"><div class="swl-rp-h"><iconify-icon icon="solar:tag-linear" class="is-blue" aria-hidden="true"></iconify-icon><h2>By topic</h2></div><div id="cs_by_category"></div></section>
                        <section class="swl-rp-card"><div class="swl-rp-h"><iconify-icon icon="solar:users-group-two-rounded-linear" class="is-teal" aria-hidden="true"></iconify-icon><h2>By team</h2></div><div id="cs_by_team"></div></section>
                        <section class="swl-rp-card"><div class="swl-rp-h"><iconify-icon icon="solar:bolt-linear" class="is-amber" aria-hidden="true"></iconify-icon><h2>Delivery and automation</h2></div><div id="cs_ops"></div></section>
                    </div>
                    <section class="swl-rp-card">
                        <div class="swl-rp-h"><iconify-icon icon="solar:info-circle-linear" class="is-blue" aria-hidden="true"></iconify-icon><h2>How these numbers are worked out</h2></div>
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
                    </section>
                </div>
                </div>
            </div>

            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <?php include 'views/support/_vocab.php'; ?>
    <script src="<?= cdp_asset('assets/css_main_swiftlane/js/apexcharts.min.js') ?>"></script>
    <script src="<?= cdp_asset('dataJs/swl_theme.js') ?>"></script>
    <script src="<?= cdp_asset('dataJs/support_common.js') ?>"></script>
    <script src="<?= cdp_asset('dataJs/support_reports.js') ?>"></script>
</body>

</html>
