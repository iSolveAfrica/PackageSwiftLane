<?php
/**
 * M-Settings: every settings area as grouped rows. Same $d as the web hub (views/sl/data/settings_hub.php).
 * The design's three groups come first (each row only when the person may open that page), then
 * every other setting of the web index. The exchange rate is edited in place through the web hub's own
 * endpoint (ajax/tools/config_general_ajax.php, same field names; the other fields go back unchanged).
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$s = $d['s'];
$slPhone['nav'] = 'staff';
$slPhone['tab'] = 'Menu';
$slPhone['scripts'][] = 'dataJs/sl/phone/settings.js';
$can = fn($p) => $user->cdp_hasPermission($p);
$super = function_exists('sl_userIs') && sl_userIs('superadmin');
@include_once CDP_ROOT . '/helpers/devops.php';
@include_once CDP_ROOT . '/helpers/dev_access.php';
$modulesOk = function_exists('cdp_devSectionAllowed') ? cdp_devSectionAllowed($user, 'modules') : $super;
$groups = [
    'Business' => [
        ['i-warehouse', 'Company', 'Name, logo, addresses, sign-up rules', 'settings_form.php?f=system', $can('view_tools')],
        ['i-sliders', 'Modules', 'Air, sea, finance, AI, loyalty on/off', sl_route('ModuleController'), $modulesOk],
        ['i-tag', 'Rates & tariffs', 'Freight price, taxes and route prices', sl_route('Tariffs'), true],
        ['i-pin', 'Shipping addresses', 'Warehouses customers ship to', sl_route('ShippingAddresses'), $can(['manage_shipping_addresses', 'view_tools'])],
        ['i-bell', 'Notifications', 'When and how customers hear', sl_route('NotificationCenter'), $can(['manage_notification_rules', 'view_tools'])],
        ['i-message', 'Message templates', 'WhatsApp, SMS, email', sl_route('MessageTemplates'), $can(['list_sms_templates', 'view_tools'])],
    ],
    'Running the app' => [
        ['i-key', 'Staff & permissions', '', sl_route('StaffPermissions'), $can('view_role_assignment')],
        ['i-image', 'Login page', 'Pictures, texts, sign-in methods', sl_route('LoginSettings'), $can('view_tools')],
        ['i-print', 'Labels', 'Sizes and printers', sl_route('LabelStudio'), $can(['print_label', 'view_tools'])],
        ['i-bolt', 'Automations', '', sl_route('Automations'), $can('manage_automations')],
        ['i-dollar', 'Operating costs', '', sl_route('CostSettings'), $super || $can(['manage_operating_costs', 'view_tools'])],
        ['i-grid', 'App screens', 'Every phone screen and its page', sl_route('AppScreens'), $can('view_tools')],
    ],
    'Connections' => [
        ['i-link', 'API connections', '', sl_route('ApiConnections'), $can('manage_api_settings')],
        ['i-activity', 'Live API traffic', '', sl_route('ApiTraffic'), $can(['view_api_logs', 'manage_api_settings'])],
        ['i-mail', 'Email, SMS and WhatsApp setup', 'SMTP, Hubtel, WhatsApp', sl_route('ApiConnections', ['p' => 'smtp']), $can('manage_api_settings')],
        ['i-globe', 'SEO and app icon', 'Meta description, share card', 'settings_form.php?f=seo', $can('view_tools')],
        ['i-database', 'Backups', 'Download a database backup', 'backup.php', $can('view_tools')],
    ],
];
$row = fn($icon, $t, $sub, $href) => '<a class="mx-row" href="' . h($href) . '" data-mx-menu-item><span class="mx-li"><i class="ic ' . h($icon) . '" aria-hidden="true"></i></span><span class="mx-rm"><b>' . h($t) . '</b>' . ($sub !== '' ? '<small>' . h($sub) . '</small>' : '') . '</span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>';
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-settings.css')) ?>">
<?= sl_phoneTop('Settings', '', ['back' => 'menu.php']) ?>
<main class="mx-body">
<?php require_once CDP_ROOT . '/helpers/sl_setup_checks.php'; foreach (sl_setupChecks($core) as [$ck, $ct, $cx, $ch, $cl]): ?><a class="mx-ban warn" href="<?= h($ch) ?>"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span><b><?= h($ct) ?>.</b> <?= h($cx) ?> <u><?= h($cl) ?></u></span></a><?php endforeach; ?>
<div class="mx-srch"><label><i class="ic i-search" aria-hidden="true"></i><input type="search" aria-label="Search settings" placeholder="Search <?= (int) $d['allN'] ?> settings" data-mx-menu-q></label></div>
<?php foreach ($groups as $label => $items): $items = array_filter($items, fn($i) => $i[4]); if (!$items) continue; ?>
<div data-mx-menu-group>
<div class="mx-sec"><h2><?= h($label) ?></h2></div>
<section class="mx-card mx-list"><?php foreach ($items as $i) echo $row($i[0], $i[1], $i[2], $i[3]); ?></section>
</div>
<?php endforeach; ?>
<?php if (!empty($d['canEdit'])): ?>
<div class="mx-sec"><h2>Money</h2></div>
<form data-sl-form data-sl-reload action="ajax/tools/config_general_ajax.php" method="post">
<div class="mx-card mx-form">
<?php foreach (['language', 'timezone', 'cformat', 'currency', 'for_currency', 'for_symbol', 'for_decimal'] as $k): ?><input type="hidden" name="<?= h($k) ?>" value="<?= h($s->$k ?? '') ?>"><?php endforeach; ?>
<label class="mx-f"><small>USD → <?= h($s->currency ?? 'local') ?> rate</small><input class="mx-in" name="exchange_rate" inputmode="decimal" autocomplete="off" value="<?= h($s->exchange_rate ?? '') ?>"></label>
<?php if (!empty($d['rateLog'][0])): ?><p class="mx-note">Last change <?= h($d['rateLog'][0]['what']) ?> by <?= h($d['rateLog'][0]['who']) ?> <?= h($d['rateLog'][0]['when']) ?>.</p><?php endif; ?>
<button class="mx-btn" type="submit">Save rate</button>
</div>
</form>
<?php endif; ?>
<div class="mx-sec"><h2>Everything else</h2></div>
<?php foreach ($d['sets'] as $g): ?>
<details class="mx-grp" data-mx-menu-group><summary><span><?= h($g['label']) ?></span><small><?= count($g['items']) ?></small><i class="ic ic-sm i-down" aria-hidden="true"></i></summary><section class="mx-card mx-list">
<?php foreach ($g['items'] as $i) echo $row($g['icon'], $i['t'], $i['d'], $i['href']); ?>
</section></details>
<?php endforeach; ?>
</main>
