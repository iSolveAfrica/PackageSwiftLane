<?php
// *************************************************************************
// * Customer Service settings. King and general, teams and routing,       *
// * automation rules (with a dry-run simulator), service levels, saved    *
// * replies, the knowledge base, and channels and delivery health.        *
// * Administrators change settings; agents keep their own saved replies   *
// * and may draft help articles. Data: ajax/support/admin_ajax.php        *
// * (dataJs/support_settings.js).                                         *
// *************************************************************************

$userData = $user->cdp_getUserData();
$ready = cdp_csEnsureTables();
$isAdmin = cdp_csIsAdmin($user);
$actions = '<a class="btn btn-secondary btn-sm" href="support_inbox.php"><iconify-icon icon="solar:inbox-linear"></iconify-icon> Inbox</a>';
$tabs = ['king' => 'King & general', 'teams' => 'Teams & routing', 'rules' => 'Automation', 'sla' => 'Service levels', 'canned' => 'Saved replies', 'kb' => 'Knowledge base', 'channels' => 'Channels & delivery'];
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title>Customer Service settings | <?php echo $core->site_name ?></title>
    <?php include 'views/inc/head_scripts.php'; ?>
</head>

<body>
    <?php include 'views/inc/preloader.php'; ?>
    <div id="main-wrapper">
        <?php include 'views/inc/topbar.php'; ?>
        <?php include 'views/inc/left_sidebar.php'; ?>

        <div class="page-wrapper">
            <?php cdp_pageHeader('Customer Service settings', ['icon' => 'solar:settings-linear', 'actions' => $actions]); ?>

            <div class="container-fluid">
                <div id="resultados_ajax"></div>
                <?php if (!$ready) : ?>
                    <div class="swl-cs-banner is-danger">The helpdesk tables could not be created. The database user needs the CREATE privilege once.</div>
                <?php endif; ?>
                <?php if (!$isAdmin) : ?>
                    <div class="swl-cs-banner is-info">You can manage your own saved replies and draft help articles. Other settings are read-only: an administrator changes them.</div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <div class="swl-cs-tabs" role="tablist" id="cs_set_tabs">
                            <?php $first = true; foreach ($tabs as $k => $label) : ?>
                                <button type="button" class="<?php echo $first ? 'is-on' : ''; ?>" data-tab="<?php echo $k; ?>" role="tab" aria-selected="<?php echo $first ? 'true' : 'false'; ?>"><?php echo htmlspecialchars($label); ?></button>
                            <?php $first = false; endforeach; ?>
                        </div>
                        <?php $first = true; foreach ($tabs as $k => $label) : ?>
                            <div data-pane="<?php echo $k; ?>" id="cs_s_<?php echo $k; ?>"<?php echo $first ? '' : ' hidden'; ?>><div class="swl-cs-empty">Loading…</div></div>
                        <?php $first = false; endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Rule editor -->
            <div class="modal fade" id="cs_rule_modal" tabindex="-1" role="dialog" aria-labelledby="cs_rule_title" aria-hidden="true">
                <div class="modal-dialog modal-xl swl-cs-modal-wide" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title" id="cs_rule_title">Automation rule</h4>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body" id="cs_rule_body"></div>
                        <div class="modal-footer">
                            <span class="swl-cs-small swl-cs-muted mr-auto">Saving keeps the previous version. Drafts never run.</span>
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-secondary" data-rule-save="draft">Save as draft</button>
                            <button type="button" class="btn btn-primary" data-rule-save="published">Save and turn on</button>
                        </div>
                    </div>
                </div>
            </div>

            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <?php include 'views/support/_vocab.php'; ?>
    <script src="<?= cdp_asset('dataJs/support_common.js') ?>"></script>
    <script src="<?= cdp_asset('dataJs/support_settings.js') ?>"></script>
</body>

</html>
