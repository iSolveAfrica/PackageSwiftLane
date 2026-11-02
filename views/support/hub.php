<?php
// *************************************************************************
// * Help & Support — the customer's page.                                 *
// *                                                                       *
// * The chat with King (or a person) on the left; on the right the        *
// * customer's requests, earlier chats and the help articles. A request    *
// * opens in a dialog with its public thread, a reply box and the rating.  *
// * Data comes from ajax/support/customer_ajax.php (dataJs/support_hub.js).*
// *************************************************************************

$userData = $user->cdp_getUserData();
$e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$ready = cdp_csEnsureTables();
$king = ['on' => $ready && cdp_csKingOn(), 'name' => cdp_csSetting('king_name', 'King'), 'greeting' => cdp_csSetting('king_greeting')];
$actions = '<button type="button" class="btn btn-primary btn-sm" id="cs_new_req"><iconify-icon icon="solar:document-add-linear"></iconify-icon> New request</button>';
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title>Help &amp; Support | <?php echo $core->site_name ?></title>
    <?php include 'views/inc/head_scripts.php'; ?>
</head>

<body>
    <?php include 'views/inc/preloader.php'; ?>
    <div id="main-wrapper">
        <?php include 'views/inc/topbar.php'; ?>
        <?php include 'views/inc/left_sidebar.php'; ?>

        <div class="page-wrapper">
            <?php cdp_pageHeader('Help & Support', ['icon' => 'solar:chat-round-dots-linear', 'actions' => $actions]); ?>

            <div class="container-fluid">
                <div id="resultados_ajax"></div>
                <?php if (!$ready) : ?>
                    <div class="swl-cs-banner is-danger">Support is not available right now. Please try again later.</div>
                <?php endif; ?>

                <div class="swl-cs-hub">
                    <div id="cs_chat"></div>

                    <aside class="card">
                        <div class="card-body">
                            <div class="swl-cs-tabs" role="tablist">
                                <button type="button" class="is-on" data-tab="req" role="tab" aria-selected="true">My requests <span id="cs_req_n" class="swl-cs-muted"></span></button>
                                <button type="button" data-tab="chats" role="tab" aria-selected="false">Chats</button>
                                <button type="button" data-tab="help" role="tab" aria-selected="false">Help</button>
                            </div>
                            <div data-pane="req" id="cs_reqs"><div class="swl-cs-empty">Loading…</div></div>
                            <div data-pane="chats" hidden>
                                <button type="button" class="btn btn-secondary btn-sm btn-block mb-2" id="cs_chat_new"><iconify-icon icon="solar:pen-new-square-linear"></iconify-icon> New chat</button>
                                <div id="cs_chats"></div>
                            </div>
                            <div data-pane="help" hidden>
                                <label class="swl-cs-sr" for="cs_help_q">Search help articles</label>
                                <input type="search" class="form-control mb-2" id="cs_help_q" placeholder="Search help, e.g. storage, invoice, warehouse">
                                <div id="cs_help"></div>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>

            <!-- New request -->
            <div class="modal fade" id="cs_req_modal" tabindex="-1" role="dialog" aria-labelledby="cs_req_title" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <form class="modal-content" id="cs_req_form" autocomplete="off">
                        <div class="modal-header">
                            <h4 class="modal-title" id="cs_req_title">New request</h4>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label for="cs_req_cat">What is it about?</label>
                                    <select class="custom-select" id="cs_req_cat" name="category"></select>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="cs_req_ent">Related shipment or package (optional)</label>
                                    <select class="custom-select" id="cs_req_ent" name="entity"><option value="">None</option></select>
                                </div>
                                <div class="col-12 form-group">
                                    <label for="cs_req_subject">Subject</label>
                                    <input type="text" class="form-control" id="cs_req_subject" name="subject" maxlength="190" required>
                                </div>
                                <div class="col-12 form-group">
                                    <label for="cs_req_desc">What happened, and what do you need?</label>
                                    <textarea class="form-control" id="cs_req_desc" name="description" rows="5" maxlength="4000" required></textarea>
                                </div>
                                <div class="col-12 form-group">
                                    <label for="cs_req_file">Evidence (optional): a receipt, photo of the package or a tracking screenshot helps us check faster. Photos or PDF, up to 8 MB each.</label>
                                    <input type="file" class="form-control" id="cs_req_file" accept="image/*,application/pdf">
                                    <div class="swl-cs-pending-files mt-2" id="cs_req_files"></div>
                                </div>
                            </div>
                            <p class="swl-cs-small swl-cs-muted mb-0">Never include passwords, one-time codes or card numbers.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="cs_req_send">Send request</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- One request -->
            <div class="modal fade" id="cs_tk_modal" tabindex="-1" role="dialog" aria-labelledby="cs_tk_title" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title" id="cs_tk_title">Request</h4>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <dl class="swl-cs-kv mb-3" id="cs_tk_kv"></dl>
                            <div id="cs_tk_rate"></div>
                            <div class="swl-cs-chat-scroll" style="max-height: 46vh; border-radius: var(--radius-16);"><div id="cs_tk_thread" role="log" aria-live="polite"></div></div>
                            <form id="cs_tk_reply" class="mt-3" autocomplete="off">
                                <label for="cs_tk_text" class="swl-cs-sr">Your reply</label>
                                <textarea class="form-control" id="cs_tk_text" rows="3" maxlength="4000" placeholder="Write a reply…"></textarea>
                                <div class="d-flex mt-2" style="gap: 8px; align-items: center;">
                                    <input type="file" id="cs_tk_file" accept="image/*,application/pdf" class="form-control" style="max-width: 280px;">
                                    <span class="swl-cs-pending-files mb-0" id="cs_tk_files"></span>
                                    <button type="submit" class="btn btn-primary ml-auto">Send reply</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <script>
        window.CDP_CS = {
            url: 'ajax/support/customer_ajax.php',
            king: <?php echo json_encode($king, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
            hours: <?php echo json_encode((string) cdp_csSetting('business_hours', ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
            entity: <?php
                $ent = null;
                if (!empty($_GET['about']) && strpos((string) $_GET['about'], ':') !== false) {
                    list($et, $eid) = explode(':', (string) $_GET['about'], 2);
                    $en = $ready ? cdp_csEntity($et, (int) $eid) : null;
                    if ($en && (int) $en['owner'] === cdp_csUid()) {
                        $ent = ['type' => $en['type'], 'id' => $en['id'], 'ref' => $en['ref']];
                    }
                }
                echo json_encode($ent, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            ?>,
            openTicket: <?php echo (int) ($_GET['t'] ?? 0); ?>
        };
    </script>
    <script src="<?= cdp_asset('dataJs/support_common.js') ?>"></script>
    <script src="<?= cdp_asset('dataJs/support_chat.js') ?>"></script>
    <script src="<?= cdp_asset('dataJs/support_hub.js') ?>"></script>
</body>

</html>
