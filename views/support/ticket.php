<?php
// *************************************************************************
// * One ticket (staff). Conversation timeline with internal notes, the    *
// * reply / note composer with saved replies and AI help (drafts are only *
// * ever previewed: the agent sends), and a side panel with the details,  *
// * the customer, AI tools and the history. Data: desk_ajax.php           *
// * (dataJs/support_ticket.js).                                           *
// *************************************************************************

require_once __DIR__ . '/../../helpers/theme.php';
$userData = $user->cdp_getUserData();
$ready = cdp_csEnsureTables();
$ticketId = (int) ($_GET['id'] ?? 0);
$actions = '<a class="btn btn-secondary btn-sm" href="support_inbox.php"><iconify-icon icon="solar:arrow-left-linear"></iconify-icon> Inbox</a>' . ' ' . cdp_themeToggle();
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title>Ticket | <?php echo $core->site_name ?></title>
    <?php cdp_themeBoot('light'); ?>
    <?php include 'views/inc/head_scripts.php'; ?>
</head>

<body>
    <?php include 'views/inc/preloader.php'; ?>
    <div id="main-wrapper">
        <?php include 'views/inc/topbar.php'; ?>
        <?php include 'views/inc/left_sidebar.php'; ?>

        <div class="page-wrapper">
            <?php cdp_pageHeader('Customer Service', ['icon' => 'solar:inbox-linear', 'sub' => 'Ticket', 'actions' => $actions]); ?>

            <div class="container-fluid">
                <div id="resultados_ajax"></div>
                <?php if (!$ready) : ?>
                    <div class="swl-cs-banner is-danger">The helpdesk tables could not be created.</div>
                <?php endif; ?>
                <div class="swl-desk-wrap">
                <div class="swl-tk">
                    <nav class="swl-desk-rail" aria-label="Customer Service">
                        <a href="support_inbox.php" class="is-on" aria-label="Inbox" title="Inbox"><iconify-icon icon="solar:inbox-linear" aria-hidden="true"></iconify-icon></a>
                        <a href="support_reports.php" aria-label="Reports" title="Reports"><iconify-icon icon="solar:chart-2-linear" aria-hidden="true"></iconify-icon></a>
                        <a href="support_settings.php#rules" aria-label="Automation" title="Automation"><iconify-icon icon="solar:bolt-linear" aria-hidden="true"></iconify-icon></a>
                        <span class="grow"></span>
                        <a href="support_settings.php" aria-label="Settings" title="Settings"><iconify-icon icon="solar:settings-linear" aria-hidden="true"></iconify-icon></a>
                    </nav>

                    <section class="swl-tk-main" aria-label="Conversation">
                        <header class="swl-tk-head">
                            <div class="swl-tk-crumb">
                                <a href="support_inbox.php"><iconify-icon icon="solar:alt-arrow-left-linear" aria-hidden="true"></iconify-icon>Inbox</a>
                                <span aria-hidden="true">/</span><span id="cs_tk_ref">…</span>
                                <span class="swl-cs-viewers" id="cs_viewers" aria-live="polite"></span>
                            </div>
                            <div class="swl-tk-titlerow">
                                <h2 id="cs_t_subject">Loading…</h2>
                                <div class="swl-tk-chips" id="cs_t_sub"></div>
                                <div class="swl-tk-actions" id="cs_t_actions"></div>
                            </div>
                        </header>
                        <div id="cs_banners" class="swl-tk-banners"></div>

                        <div class="swl-cs-timeline swl-tk-timeline" id="cs_timeline" role="log" aria-live="polite" aria-label="Messages"></div>

                        <div class="swl-cs-composer" id="cs_composer">
                            <div class="swl-cs-comp-top">
                                <div class="swl-cs-mode" role="tablist" aria-label="Reply or internal note">
                                    <button type="button" class="is-on" data-mode="reply" role="tab" aria-selected="true"><iconify-icon icon="solar:reply-linear" aria-hidden="true"></iconify-icon> Reply to customer</button>
                                    <button type="button" data-mode="note" role="tab" aria-selected="false"><iconify-icon icon="solar:notes-linear" aria-hidden="true"></iconify-icon> Internal note</button>
                                </div>
                                <span class="note-warn" id="cs_note_warn" hidden>Only staff see notes. Use @name to notify a colleague.</span>
                                <span class="ml-auto"></span>
                                <label for="cs_canned" class="swl-cs-sr">Insert a saved reply</label>
                                <select id="cs_canned" class="custom-select custom-select-sm" style="width: auto;"><option value="">Saved replies…</option></select>
                                <button type="button" class="btn btn-secondary btn-sm cs-ai" id="cs_ai_draft"><iconify-icon icon="solar:magic-stick-3-linear"></iconify-icon> Draft with AI</button>
                                <label for="cs_refine" class="swl-cs-sr">Improve the text with AI</label>
                                <select id="cs_refine" class="custom-select custom-select-sm cs-ai" style="width: auto;"><option value="">Improve…</option></select>
                            </div>
                            <div id="cs_ai_preview"></div>
                            <label for="cs_body" class="swl-cs-sr">Message</label>
                            <textarea id="cs_body" class="form-control" placeholder="Write your reply…" maxlength="8000"></textarea>
                            <div class="swl-cs-comp-bot">
                                <label class="btn btn-secondary btn-sm mb-0" for="cs_file"><iconify-icon icon="solar:paperclip-linear"></iconify-icon> Attach</label>
                                <input type="file" id="cs_file" class="swl-cs-sr" accept="image/*,application/pdf,.doc,.docx,.xls,.xlsx,.csv,.txt">
                                <span class="swl-cs-pending-files mb-0" id="cs_files"></span>
                                <span class="grow"></span>
                                <label for="cs_after" class="swl-cs-sr">Status after sending</label>
                                <select id="cs_after" class="custom-select custom-select-sm" style="width: auto;">
                                    <option value="">Keep status</option>
                                    <option value="pending_customer">and wait on customer</option>
                                    <option value="pending_internal">and wait on our team</option>
                                    <option value="resolved">and resolve</option>
                                </select>
                                <button type="button" class="btn btn-primary btn-sm" id="cs_send"><iconify-icon icon="solar:plain-2-linear" aria-hidden="true"></iconify-icon> Send <span class="swl-cs-small">(Ctrl+Enter)</span></button>
                            </div>
                        </div>
                    </section>

                    <aside class="swl-tk-side" aria-label="Customer and actions">
                        <div class="swl-tk-card" id="cs_tk_customer"></div>
                        <div class="swl-tk-card is-king" id="cs_tk_summary" hidden></div>
                        <div class="swl-tk-card">
                            <h3>Actions on this account</h3>
                            <div class="swl-cs-actions" id="cs_tk_actions"></div>
                            <p class="swl-tk-note"><iconify-icon icon="solar:shield-check-linear" aria-hidden="true"></iconify-icon> Money and ownership changes need an admin's approval.</p>
                        </div>
                        <div class="swl-tk-card"><h3>Linked shipments</h3><div id="cs_tk_ships"></div></div>
                        <div class="swl-tk-card"><h3>Service level</h3><div id="cs_tk_sla"></div></div>
                        <div class="swl-tk-card swl-cs-side">
                        <div class="swl-cs-tabs" role="tablist">
                            <button type="button" class="is-on" data-tab="details" role="tab" aria-selected="true"><iconify-icon icon="solar:document-text-linear" aria-hidden="true"></iconify-icon> Details</button>
                            <button type="button" data-tab="customer" role="tab" aria-selected="false"><iconify-icon icon="solar:user-id-linear" aria-hidden="true"></iconify-icon> Customer</button>
                            <button type="button" data-tab="ai" role="tab" aria-selected="false"><iconify-icon icon="solar:magic-stick-3-linear" aria-hidden="true"></iconify-icon> King &amp; AI</button>
                            <button type="button" data-tab="history" role="tab" aria-selected="false"><iconify-icon icon="solar:history-linear" aria-hidden="true"></iconify-icon> History</button>
                        </div>
                        <div data-pane="details" id="cs_p_details"></div>
                        <div data-pane="customer" id="cs_p_customer" hidden></div>
                        <div data-pane="ai" id="cs_p_ai" hidden></div>
                        <div data-pane="history" id="cs_p_history" hidden></div>
                        </div>
                    </aside>
                </div>
                </div>
            </div>

            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <?php include 'views/support/_vocab.php'; ?>
    <script>window.CDP_CS_TICKET = <?php echo $ticketId; ?>;</script>
    <script src="<?= cdp_asset('dataJs/swl_theme.js') ?>"></script>
    <script src="<?= cdp_asset('dataJs/support_common.js') ?>"></script>
    <script src="<?= cdp_asset('dataJs/support_ticket.js') ?>"></script>
</body>

</html>
