<?php
/**
 * Email Builder (EmailBuilder.dc.html): newsletter designer and sender.
 * Template views/sl/pages/EmailBuilder.php, script dataJs/sl/email_builder.js (plain JS).
 * Designs live in cdb_newsletter_designs through ajax/tools/newsletter_ajax.php
 * (designs, load, save, delete, count, test, send, upload, history), the same
 * design JSON as before, so existing designs still open. ?email=a@b.c presets addresses.
 */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('manage_newsletter');
require_once 'helpers/querys.php';
require_once 'helpers/newsletter.php';
$nlSettings = cdp_getSettingsCourier();
sl_render('EmailBuilder', [
    'id'      => 'ebuild',
    'title'   => 'Email builder',
    'board'   => 'EmailBuilder',
    'styles'  => ['assets/swiftlane/pages/EmailBuilderApp.css'],
    'scripts' => ['dataJs/newsletter_templates.js', 'dataJs/sl/email_builder.js'],
    'wide'    => true,
], [
    'cfg' => [
        'ajax'      => 'ajax/tools/newsletter_ajax.php',
        'siteUrl'   => rtrim((string) ($nlSettings->site_url ?? ''), '/'),
        'siteName'  => (string) $core->site_name,
        'logo'      => $core->logo ? 'assets/' . $core->logo : '',
        'fromEmail' => (string) ($nlSettings->email_address ?? $core->site_email),
        'fromName'  => (string) (($nlSettings->smtp_names ?? '') !== '' ? $nlSettings->smtp_names : $core->site_name),
        'preset'    => isset($_GET['email']) ? cdp_nlParseEmails((string) $_GET['email'])['valid'] : [],
        'tags'      => cdp_nlTags(),
        'audiences' => cdp_nlAudiences(),
        'ready'     => cdp_nlTablesReady(),
    ],
]);
