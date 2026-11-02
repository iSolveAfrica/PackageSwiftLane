<?php
// *************************************************************************
// * Customer Service inbox (staff). Views on the left with counts; search, *
// * filters, sorting and a ticket table on the right; select rows for bulk *
// * changes. A row opens the ticket view. Data: ajax/support/desk_ajax.php *
// * (dataJs/support_inbox.js).                                            *
// *************************************************************************

$userData = $user->cdp_getUserData();
$ready = cdp_csEnsureTables();
$actions = '<a class="btn btn-secondary btn-sm" href="support_reports.php"><iconify-icon icon="solar:chart-2-linear"></iconify-icon> Reports</a> '
    . '<a class="btn btn-secondary btn-sm" href="support_settings.php"><iconify-icon icon="solar:settings-linear"></iconify-icon> Settings</a> '
    . '<button type="button" class="btn btn-primary btn-sm" id="cs_new"><iconify-icon icon="solar:add-circle-linear"></iconify-icon> New ticket</button>';
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title>Customer Service | <?php echo $core->site_name ?></title>
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

                <div class="swl-cs-desk">
                    <nav class="swl-cs-views" id="cs_views" aria-label="Inbox views"></nav>

                    <section class="card mb-0">
                        <div class="card-body">
                            <div class="swl-cs-toolbar">
                                <label for="cs_q" class="swl-cs-sr">Search tickets</label>
                                <input type="search" id="cs_q" class="form-control" placeholder="Search reference, subject, customer, locker, tracking number or message text">
                                <label for="cs_sort" class="swl-cs-sr">Sort</label>
                                <select id="cs_sort" class="custom-select">
                                    <option value="activity">Latest activity</option>
                                    <option value="created">Newest</option>
                                    <option value="oldest">Oldest</option>
                                    <option value="priority">Priority</option>
                                    <option value="sla">SLA due first</option>
                                </select>
                                <button type="button" class="btn btn-secondary btn-sm" id="cs_filter_btn" aria-expanded="false" aria-controls="cs_filters"><iconify-icon icon="solar:filter-linear"></iconify-icon> Filters <span id="cs_filter_n"></span></button>
                                <button type="button" class="btn btn-secondary btn-sm" id="cs_save_view"><iconify-icon icon="solar:bookmark-linear"></iconify-icon> Save view</button>
                                <button type="button" class="swl-cs-icon" id="cs_reload" aria-label="Refresh"><iconify-icon icon="solar:refresh-linear"></iconify-icon></button>
                            </div>

                            <div class="swl-cs-filters" id="cs_filters">
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
                                <button type="button" class="btn btn-secondary btn-sm" id="cs_clear">Clear filters</button>
                            </div>

                            <div class="swl-cs-bulk" id="cs_bulk" role="region" aria-label="Bulk actions">
                                <b id="cs_bulk_n">0 selected</b>
                                <select class="custom-select" data-b="status" aria-label="Set status"><option value="">Status…</option></select>
                                <select class="custom-select" data-b="priority" aria-label="Set priority"><option value="">Priority…</option></select>
                                <select class="custom-select" data-b="assignee_id" aria-label="Assign to"><option value="">Assign…</option><option value="0">Nobody</option></select>
                                <select class="custom-select" data-b="team_id" aria-label="Move to team"><option value="">Team…</option></select>
                                <select class="custom-select" data-b="disposition" aria-label="Move to"><option value="">Move to…</option><option value="none">Inbox</option><option value="archived">Archive</option><option value="spam">Spam</option><option value="trash">Trash</option></select>
                                <input type="text" class="form-control" data-b="tag" placeholder="Add tag" aria-label="Add tag" style="width: 120px; height: 36px;">
                                <button type="button" class="btn btn-primary btn-sm" id="cs_bulk_apply">Apply</button>
                                <button type="button" class="btn btn-secondary btn-sm" id="cs_bulk_clear">Cancel</button>
                            </div>

                            <div class="table-responsive">
                                <table class="table swl-cs-table mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 32px;"><input type="checkbox" id="cs_all" aria-label="Select all on this page"></th>
                                            <th>Ticket</th>
                                            <th>Customer</th>
                                            <th>Status</th>
                                            <th>Priority</th>
                                            <th>Assignee</th>
                                            <th>SLA</th>
                                            <th>Updated</th>
                                        </tr>
                                    </thead>
                                    <tbody id="cs_rows"><tr><td colspan="8"><div class="swl-cs-empty">Loading…</div></td></tr></tbody>
                                </table>
                            </div>
                            <div class="swl-cs-pager" id="cs_pager"></div>
                        </div>
                    </section>
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
                            <button type="submit" class="btn btn-primary">Create ticket</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <?php include 'views/support/_vocab.php'; ?>
    <script src="<?= cdp_asset('dataJs/support_common.js') ?>"></script>
    <script src="<?= cdp_asset('dataJs/support_inbox.js') ?>"></script>
</body>

</html>
