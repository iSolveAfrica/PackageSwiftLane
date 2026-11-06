<?php
// *************************************************************************
// * Customer Service inbox (staff). Views on the left with counts; search, *
// * filters, sorting and a ticket table on the right; select rows for bulk *
// * changes. A row opens the ticket view. Data: ajax/support/desk_ajax.php *
// * (dataJs/support_inbox.js).                                            *
// *************************************************************************

require_once __DIR__ . '/../../helpers/theme.php';
$userData = $user->cdp_getUserData();
$ready = cdp_csEnsureTables();
$actions = '<a class="btn btn-secondary btn-sm" href="support_reports.php"><iconify-icon icon="solar:chart-2-linear"></iconify-icon> Reports</a> '
    . '<a class="btn btn-secondary btn-sm" href="support_settings.php"><iconify-icon icon="solar:settings-linear"></iconify-icon> Settings</a> '
    . cdp_themeToggle();
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title>Customer Service | <?php echo $core->site_name ?></title>
    <?php cdp_themeBoot('light'); ?>
    <?php include 'views/inc/head_scripts.php'; ?>
</head>

<body>
    <?php include 'views/inc/preloader.php'; ?>
    <div id="main-wrapper">
        <?php include 'views/inc/topbar.php'; ?>
        <?php include 'views/inc/left_sidebar.php'; ?>

        <div class="page-wrapper">
            <?php cdp_pageHeader('Customer Service', ['icon' => 'solar:inbox-linear', 'actions' => $actions]); ?>

            <div class="container-fluid">
                <div id="resultados_ajax"></div>
                <?php if (!$ready) : ?>
                    <div class="swl-cs-banner is-danger">The helpdesk tables could not be created. The database user needs the CREATE privilege once.</div>
                <?php endif; ?>

                <!-- The desk: module rail, inbox panel and work area (design: Customer Service canvas, Inbox board) -->
                <div class="swl-desk-wrap">
                <div class="swl-desk">
                    <nav class="swl-desk-rail" aria-label="Customer Service">
                        <a href="support_inbox.php" class="is-on" aria-current="page" aria-label="Inbox" title="Inbox"><iconify-icon icon="solar:inbox-linear" aria-hidden="true"></iconify-icon></a>
                        <a href="customers_list.php" aria-label="Customers" title="Customers"><iconify-icon icon="solar:users-group-rounded-linear" aria-hidden="true"></iconify-icon></a>
                        <a href="support_reports.php" aria-label="Reports" title="Reports"><iconify-icon icon="solar:chart-2-linear" aria-hidden="true"></iconify-icon></a>
                        <a href="support_settings.php#rules" aria-label="Automation" title="Automation"><iconify-icon icon="solar:bolt-linear" aria-hidden="true"></iconify-icon></a>
                        <a href="support_settings.php#king" class="is-king" aria-label="King AI" title="King AI"><iconify-icon icon="solar:magic-stick-3-linear" aria-hidden="true"></iconify-icon></a>
                        <span class="grow"></span>
                        <a href="support_settings.php" aria-label="Settings" title="Settings"><iconify-icon icon="solar:settings-linear" aria-hidden="true"></iconify-icon></a>
                        <a href="support_settings.php#kb" aria-label="Help articles" title="Help articles"><iconify-icon icon="solar:question-circle-linear" aria-hidden="true"></iconify-icon></a>
                    </nav>

                    <aside class="swl-desk-panel" aria-label="Inbox views">
                        <div class="swl-desk-panel-head">
                            <h2>Inbox</h2>
                            <button type="button" class="swl-desk-plus" id="cs_new" aria-label="New ticket" title="New ticket"><iconify-icon icon="solar:add-circle-linear" aria-hidden="true"></iconify-icon></button>
                        </div>
                        <nav class="swl-desk-views" id="cs_views" aria-label="Views"></nav>
                        <div class="swl-desk-king" id="cs_king_card">
                            <span class="ic"><iconify-icon icon="solar:magic-stick-3-linear" aria-hidden="true"></iconify-icon></span>
                            <div><b id="cs_king_title">King is answering</b><span id="cs_king_line">…</span></div>
                        </div>
                    </aside>

                    <main class="swl-desk-main">
                        <header class="swl-desk-top">
                            <span class="swl-desk-chip" id="cs_pulse_csat" title="Share of ratings that were 4 or 5 stars, last 30 days"><iconify-icon icon="solar:smile-circle-linear" class="is-green" aria-hidden="true"></iconify-icon><b>–</b> happy customers</span>
                            <span class="swl-desk-chip" id="cs_pulse_king" title="Chats King answered today that never needed a person"><iconify-icon icon="solar:magic-stick-3-linear" class="is-violet" aria-hidden="true"></iconify-icon><b>–</b> solved by King today</span>
                            <label class="swl-desk-search" for="cs_q">
                                <iconify-icon icon="solar:magnifer-linear" aria-hidden="true"></iconify-icon>
                                <span class="swl-cs-sr">Search tickets</span>
                                <input type="search" id="cs_q" placeholder="Search customer, reference, tracking no. or message…">
                                <kbd>/</kbd>
                            </label>
                            <div class="swl-desk-online" id="cs_online" aria-live="polite"></div>
                        </header>

                        <section class="swl-desk-card" aria-labelledby="cs_view_title">
                            <div class="swl-desk-card-head">
                                <iconify-icon icon="solar:inbox-linear" id="cs_view_icon" aria-hidden="true"></iconify-icon>
                                <h2 id="cs_view_title">All</h2>
                                <span class="swl-desk-count" id="cs_count"></span>
                                <div class="r">
                                    <button type="button" class="swl-desk-btn" id="cs_filter_btn" aria-expanded="false" aria-controls="cs_filters"><iconify-icon icon="solar:filter-linear" aria-hidden="true"></iconify-icon> Filter <span id="cs_filter_n"></span></button>
                                    <label for="cs_sort" class="swl-cs-sr">Sort</label>
                                    <span class="swl-desk-btn is-select"><iconify-icon icon="solar:sort-vertical-linear" aria-hidden="true"></iconify-icon>
                                        <select id="cs_sort">
                                            <option value="activity">Latest activity</option>
                                            <option value="sla">SLA due first</option>
                                            <option value="created">Newest</option>
                                            <option value="oldest">Oldest</option>
                                            <option value="priority">Priority</option>
                                        </select></span>
                                    <button type="button" class="swl-desk-btn" id="cs_save_view" aria-label="Save this view" title="Save this view"><iconify-icon icon="solar:bookmark-linear" aria-hidden="true"></iconify-icon></button>
                                    <button type="button" class="swl-desk-btn" id="cs_reload" aria-label="Refresh" title="Refresh"><iconify-icon icon="solar:refresh-linear" aria-hidden="true"></iconify-icon></button>
                                    <button type="button" class="swl-desk-btn is-primary" id="cs_new2"><iconify-icon icon="solar:add-circle-linear" aria-hidden="true"></iconify-icon> New ticket</button>
                                </div>
                            </div>

                            <div class="swl-cs-filters swl-desk-pad" id="cs_filters">
                                <select class="custom-select" data-f="status" aria-label="Status"><option value="">Any status</option></select>
                                <select class="custom-select" data-f="priority" aria-label="Priority"><option value="">Any priority</option></select>
                                <select class="custom-select" data-f="category" aria-label="Topic"><option value="">Any topic</option></select>
                                <select class="custom-select" data-f="channel" aria-label="Channel"><option value="">Any channel</option></select>
                                <select class="custom-select" data-f="team_id" aria-label="Team"><option value="">Any team</option></select>
                                <select class="custom-select" data-f="assignee_id" aria-label="Assignee"><option value="">Anyone</option><option value="0">Unassigned</option></select>
                                <select class="custom-select" data-f="language" aria-label="Language"><option value="">Any language</option></select>
                                <input type="text" class="form-control" data-f="tag" placeholder="Tag" aria-label="Tag">
                                <input type="date" class="form-control" data-f="from" aria-label="Created from">
                                <input type="date" class="form-control" data-f="to" aria-label="Created to">
                                <button type="button" class="btn btn-secondary btn-sm" id="cs_clear"><iconify-icon icon="solar:eraser-linear" aria-hidden="true"></iconify-icon> Clear filters</button>
                            </div>

                            <div class="swl-cs-bulk swl-desk-pad" id="cs_bulk" role="region" aria-label="Bulk actions">
                                <b id="cs_bulk_n">0 selected</b>
                                <select class="custom-select" data-b="status" aria-label="Set status"><option value="">Status…</option></select>
                                <select class="custom-select" data-b="priority" aria-label="Set priority"><option value="">Priority…</option></select>
                                <select class="custom-select" data-b="assignee_id" aria-label="Assign to"><option value="">Assign…</option><option value="0">Nobody</option></select>
                                <select class="custom-select" data-b="team_id" aria-label="Move to team"><option value="">Team…</option></select>
                                <select class="custom-select" data-b="disposition" aria-label="Move to"><option value="">Move to…</option><option value="none">Inbox</option><option value="archived">Archive</option><option value="spam">Spam</option><option value="trash">Trash</option></select>
                                <input type="text" class="form-control" data-b="tag" placeholder="Add tag" aria-label="Add tag" style="width: 120px; height: 36px;">
                                <button type="button" class="btn btn-primary btn-sm" id="cs_bulk_apply"><iconify-icon icon="solar:check-read-linear" aria-hidden="true"></iconify-icon> Apply</button>
                                <button type="button" class="btn btn-secondary btn-sm" id="cs_bulk_clear"><iconify-icon icon="solar:close-circle-linear" aria-hidden="true"></iconify-icon> Cancel</button>
                            </div>

                            <div class="table-responsive swl-desk-scroll">
                                <table class="table swl-cs-table swl-desk-table mb-0">
                                    <thead>
                                        <tr>
                                            <th class="c-sel"><input type="checkbox" id="cs_all" aria-label="Select all on this page"></th>
                                            <th>Customer</th>
                                            <th>Subject</th>
                                            <th>Channel</th>
                                            <th>Priority</th>
                                            <th>Handled by</th>
                                            <th>Status</th>
                                            <th>SLA</th>
                                            <th class="c-last">Last message</th>
                                        </tr>
                                    </thead>
                                    <tbody id="cs_rows"><tr><td colspan="9"><div class="swl-cs-empty">Loading…</div></td></tr></tbody>
                                </table>
                            </div>
                            <div class="swl-desk-foot" id="cs_pager"></div>
                        </section>
                    </main>
                </div>
                </div>
            </div>

            <!-- New ticket -->
            <div class="modal fade" id="cs_new_modal" tabindex="-1" role="dialog" aria-labelledby="cs_new_title" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <form class="modal-content" id="cs_new_form" autocomplete="off">
                        <div class="modal-header">
                            <h4 class="modal-title" id="cs_new_title">New ticket</h4>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-12 form-group">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="cs_n_internal" name="is_internal" value="1">
                                        <label class="custom-control-label" for="cs_n_internal">Staff request (no customer; for example an IT or operations issue)</label>
                                    </div>
                                </div>
                                <div class="col-md-6 form-group cs-cust">
                                    <label for="cs_n_cust_q">Customer</label>
                                    <input type="search" class="form-control" id="cs_n_cust_q" placeholder="Name, e-mail, phone or locker">
                                    <input type="hidden" name="customer_id" id="cs_n_cust">
                                    <div class="swl-cs-list mt-1" id="cs_n_cust_list"></div>
                                </div>
                                <div class="col-md-6 form-group cs-cust">
                                    <label for="cs_n_ent">Related record</label>
                                    <select class="custom-select" id="cs_n_ent" name="entity"><option value="">None</option></select>
                                </div>
                                <div class="col-12 form-group">
                                    <label for="cs_n_subject">Subject</label>
                                    <input type="text" class="form-control" id="cs_n_subject" name="subject" maxlength="190" required>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="cs_n_cat">Topic</label>
                                    <select class="custom-select" id="cs_n_cat" name="category"></select>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="cs_n_pri">Priority</label>
                                    <select class="custom-select" id="cs_n_pri" name="priority"></select>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="cs_n_ch">How did it come in?</label>
                                    <select class="custom-select" id="cs_n_ch" name="channel"></select>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="cs_n_team">Team</label>
                                    <select class="custom-select" id="cs_n_team" name="team_id"><option value="0">Default team</option></select>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="cs_n_asg">Assignee</label>
                                    <select class="custom-select" id="cs_n_asg" name="assignee_id"><option value="0">Automatic</option></select>
                                </div>
                                <div class="col-12 form-group">
                                    <label for="cs_n_desc">Details (saved as an internal note)</label>
                                    <textarea class="form-control" id="cs_n_desc" name="description" rows="4"></textarea>
                                </div>
                                <div class="col-12 form-group cs-cust mb-0">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="cs_n_notify" name="notify_customer" value="1">
                                        <label class="custom-control-label" for="cs_n_notify">Send the customer the acknowledgement with the ticket number</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary"><iconify-icon icon="solar:add-circle-linear" aria-hidden="true"></iconify-icon> Create ticket</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <?php include 'views/support/_vocab.php'; ?>
    <script src="<?= cdp_asset('assets/css_main_swiftlane/js/apexcharts.min.js') ?>"></script>
    <script src="<?= cdp_asset('dataJs/swl_theme.js') ?>"></script>
    <script src="<?= cdp_asset('dataJs/support_common.js') ?>"></script>
    <script src="<?= cdp_asset('dataJs/support_inbox.js') ?>"></script>
</body>

</html>
