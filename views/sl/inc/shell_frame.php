<?php
/** Shell frame: header, rail, sidebar and the opening of <main>. Variables come from shell_start.php (or the legacy bridge). */
?>
<a class="sl-skip" href="#sl-main">Skip to main content</a>
<div class="<?= h($slTheme) ?>" id="sl-app" data-page="<?= h($slPage['id'] ?? '') ?>">
  <input type="checkbox" id="navtg" class="nav-tg" aria-hidden="true" tabindex="-1">
  <header class="sl-top">
    <button class="sl-ib nav-open" type="button" data-sl-nav aria-controls="sl-navigation" aria-expanded="true" aria-label="Close the menu"><i class="ic i-sidebar" aria-hidden="true"></i></button>
    <a class="sl-switch" href="<?= h($slHome) ?>"><small><?= h(sl_initials($core->site_name ?? 'SL')) ?></small> <?= h($core->site_name ?? 'Swift Lane') ?> · <?= h($slHub) ?> <i class="ic ic-sm i-down" aria-hidden="true"></i></a>
    <?php if ($slRate > 0): ?>
    <a class="fx-chip" href="<?= h($slIsClient ? sl_route('MyBills') : sl_route('SettingsHub') . '#language') ?>" title="Exchange rate · set in Settings › General"><i class="ic ic-sm i-dollar" aria-hidden="true"></i><span class="mono">USD 1 = GHS <?= h(number_format($slRate, 2)) ?></span><?php if ($slRateDelta): ?><span class="fx-d"><?= h($slRateDelta) ?></span><?php endif; ?></a>
    <?php endif; ?>
    <?php if ($slIsClient || sl_userIs('driver')): ?><a class="sl-phone-identity" href="<?= h($slIsClient ? sl_route('Main') : sl_route('DriverApp')) ?>"><?= h($core->site_name ?? 'Swift Lane') ?><small><?= $slIsClient ? 'Your shipping companion' : 'Rider workspace' ?></small></a><?php endif; ?>
    <span class="grow"></span>
    <?php if ($slHasSupport || $slCanAskAI): ?><details class="aitop"><summary aria-label="Help and support"><span class="o"><i class="ic i-ai" aria-hidden="true"></i></span>Help</summary><div class="pop" role="menu">
<div class="me"><span class="sl-av sm dark"><?= h($slInitials) ?></span><div class="grow"><div class="fw5 sm"><?= h($slName) ?></div><div class="mut" style="font-size:14px"><?= h($slSub) ?></div></div><a class="sl-btn sm ghost" href="<?= h($slProfileUrl) ?>">Edit</a></div>
<a class="ask" href="<?= h($slAssistantUrl) ?>"><i class="ic ic-sm i-spark" aria-hidden="true"></i><?= $slCanAskAI ? 'Ask AI for help on this page' : 'Open help and support' ?><b><i class="ic ic-sm i-send" aria-hidden="true"></i></b></a>
<div class="hd">Your profile with AI</div>
<a class="it" role="menuitem" href="<?= h($slProfileUrl) ?>#retouch"><span class="t"><i class="ic ic-sm i-wand" aria-hidden="true"></i></span><span>Retouch my photo<small>Light, skin and background</small></span></a>
<a class="it" role="menuitem" href="<?= h($slProfileUrl) ?>#reset"><span class="t"><i class="ic ic-sm i-refresh" aria-hidden="true"></i></span><span>Reset images<small>Go back to the original photo</small></span></a>
<a class="it" role="menuitem" href="<?= h($slProfileUrl) ?>#name"><span class="t"><i class="ic ic-sm i-edit" aria-hidden="true"></i></span><span>Change my name<small>AI fixes spelling and capitals</small></span></a>
<a class="it" role="menuitem" href="<?= h($slProfileUrl) ?>#icon"><span class="t"><i class="ic ic-sm i-user" aria-hidden="true"></i></span><span>Make my icon<small>Avatar from your photo or initials</small></span></a>
<div class="hd">Help</div>
<a class="it" role="menuitem" href="<?= h($slAssistantUrl) ?>"><span class="t"><i class="ic ic-sm i-help" aria-hidden="true"></i></span><span><?= $slCanAskAI ? 'Explain this page' : 'Help and support' ?></span></a>
<?php if ($slHasSupport): ?><a class="it" role="menuitem" href="<?= h($slHelpUrl) ?>"><span class="t"><i class="ic ic-sm i-message" aria-hidden="true"></i></span><span>Talk to a person</span></a><?php endif; ?>
</div></details><?php endif; ?>
    <a class="sl-ib sl-bell" aria-label="Notifications<?= $slUnread ? ', ' . $slUnread . ' unread' : '' ?>" href="<?= h(sl_route('Notifications')) ?>"><i class="ic i-bell" aria-hidden="true"></i><?php if ($slUnread): ?><b class="sl-bell-n"><?= $slUnread > 99 ? '99+' : $slUnread ?></b><?php endif; ?></a>
    <button class="sl-ib" type="button" aria-label="Switch theme" data-sl-theme><i class="ic i-sun" aria-hidden="true"></i></button>
    <?php if (!$slIsClient && $user->cdp_hasPermission(['view_documents', 'view_general_reports'])): ?><a class="sl-ib" aria-label="Documents" href="<?= h(sl_route('Documents')) ?>"><i class="ic i-file" aria-hidden="true"></i></a><?php endif; ?>
    <?php if ($slPrimary): ?><a class="sl-btn" href="<?= h($slPrimary['href']) ?>"><?= h($slPrimary['label']) ?></a><?php endif; ?>
    <details class="uptop"><summary aria-label="Your profile and settings, <?= h($slName) ?>"><?= $slPic(34) ?><span class="up-nm"><?= h($slFirst) ?></span><i class="ic ic-sm i-down" aria-hidden="true"></i></summary><div class="pop" role="menu">
<a class="up-me" href="<?= h($slProfileUrl) ?>"><?= $slPic(48) ?><span class="col" style="min-width:0"><b><?= h($slName) ?></b><small><?= h($slSub) ?></small><small class="mono"><?= h($userData->email ?? '') ?></small></span></a>
<div class="up-st"><span class="row g6 sm"><span class="ld" style="width:8px;height:8px;border-radius:50%;background:var(--green);display:inline-block"></span>Available</span><a class="sm fw5" href="<?= h($slProfileUrl) ?>">Edit profile</a></div>
<?php if ($slIsClient): ?>
<div class="up-h">Short links</div><div class="up-g"><a class="up-l" role="menuitem" href="<?= h(sl_route('UserHome')) ?>"><i class="ic ic-sm i-grid" aria-hidden="true"></i>My account</a><a class="up-l" role="menuitem" href="<?= h(sl_route('PublicTracking')) ?>"><i class="ic ic-sm i-route" aria-hidden="true"></i>Track a parcel</a><?php if (cdp_financeEnabled()): ?><a class="up-l" role="menuitem" href="<?= h(sl_route('MyBills')) ?>"><i class="ic ic-sm i-receipt" aria-hidden="true"></i>My bills</a><?php endif; ?><a class="up-l" role="menuitem" href="pickup_add.php"><i class="ic ic-sm i-truck" aria-hidden="true"></i>Request pickup</a><a class="up-l" role="menuitem" href="<?= h(sl_route('UserHome')) ?>#addresses"><i class="ic ic-sm i-pin" aria-hidden="true"></i>My shipping addresses</a><a class="up-l" role="menuitem" href="<?= h(sl_route('Notifications')) ?>"><i class="ic ic-sm i-bell" aria-hidden="true"></i>Notifications</a></div>
<div class="up-h">Settings</div><a class="up-l" role="menuitem" href="<?= h($slProfileUrl) ?>"><i class="ic ic-sm i-ai" aria-hidden="true"></i>Profile and photo</a><a class="up-l" role="menuitem" href="<?= h(sl_route('PasswordReset')) ?>"><i class="ic ic-sm i-lock" aria-hidden="true"></i>Password and sign-in</a><a class="up-l" role="menuitem" href="<?= h(sl_route('CustomerNotifications')) ?>"><i class="ic ic-sm i-sliders" aria-hidden="true"></i>Notification settings</a>
<?php else: ?>
<div class="up-h">Short links</div><div class="up-g"><a class="up-l" role="menuitem" href="<?= h($slHome) ?>"><i class="ic ic-sm i-grid" aria-hidden="true"></i>Overview</a><?php if ($user->cdp_hasPermission(['dispatch_riders', 'assign_drivers', 'assign_driver_pickup'])): ?><a class="up-l" role="menuitem" href="<?= h(sl_route('RiderDispatch')) ?>"><i class="ic ic-sm i-truck" aria-hidden="true"></i>Dispatch board</a><?php endif; ?><?php if ($user->cdp_hasPermission(['send_prices', 'push_notifications'])): ?><a class="up-l" role="menuitem" href="<?= h(sl_route('SendPrices')) ?>"><i class="ic ic-sm i-send" aria-hidden="true"></i>Send prices</a><?php endif; ?><?php if ($user->cdp_hasPermission('view_general_reports')): ?><a class="up-l" role="menuitem" href="<?= h(sl_route('Reports')) ?>"><i class="ic ic-sm i-chart" aria-hidden="true"></i>Reports</a><?php endif; ?><?php if ($user->cdp_hasPermission(['view_documents', 'view_general_reports'])): ?><a class="up-l" role="menuitem" href="<?= h(sl_route('Documents')) ?>"><i class="ic ic-sm i-file" aria-hidden="true"></i>Documents</a><?php endif; ?><?php if ($user->cdp_hasPermission(['view_staff_activity', 'view_staff_productivity'])): ?><a class="up-l" role="menuitem" href="<?= h(sl_route('StaffActivity', ['id' => (int) $_SESSION['userid']])) ?>"><i class="ic ic-sm i-clock" aria-hidden="true"></i>My activity</a><?php endif; ?></div>
<div class="up-h">Settings</div><a class="up-l" role="menuitem" href="<?= h($slProfileUrl) ?>"><i class="ic ic-sm i-ai" aria-hidden="true"></i>Profile and photo</a><?php if ($user->cdp_hasPermission('view_tools')): ?><a class="up-l" role="menuitem" href="<?= h(sl_route('SettingsHub')) ?>"><i class="ic ic-sm i-cog" aria-hidden="true"></i>Settings</a><?php endif; ?><?php if ($user->cdp_hasPermission('manage_api_settings')): ?><a class="up-l" role="menuitem" href="<?= h(sl_route('ApiConnections')) ?>"><i class="ic ic-sm i-link" aria-hidden="true"></i>API connections</a><?php endif; ?><?php if ($user->cdp_hasPermission('view_role_assignment')): ?><a class="up-l" role="menuitem" href="<?= h(sl_route('StaffPermissions')) ?>"><i class="ic ic-sm i-shield" aria-hidden="true"></i>Permissions</a><?php endif; ?>
<?php endif; ?>
<div class="up-row"><span class="sm mut">Theme</span><span class="sl-seg boxed" style="height:30px" data-sl-theme-seg><button type="button" class="on" data-sl-set-theme="light" style="height:26px;padding:0 10px;display:inline-flex;align-items:center">Light</button><button type="button" data-sl-set-theme="dark" style="height:26px;padding:0 10px;display:inline-flex;align-items:center">Dark</button></span></div>
<?php if ($slHasSupport): ?><a class="up-l" role="menuitem" href="<?= h($slHelpUrl) ?>"><i class="ic ic-sm i-help" aria-hidden="true"></i>Help and support</a><?php endif; ?>
<a class="up-l out" role="menuitem" href="logout.php"><i class="ic ic-sm i-logout" aria-hidden="true"></i>Sign out</a>
</div></details>
  </header>
  <div class="sl-body">
    <label class="nav-bd" for="navtg" aria-hidden="true"></label>
    <div class="sl-rail" aria-label="Quick actions">
      <?php if ($slIsClient): ?>
      <?php if ($user->cdp_hasPermission('courier_add_client')): ?><a class="<?= ($slPage['id'] ?? '') === 'new' ? 'is-on' : '' ?>" href="courier_add_client.php" aria-label="New shipment" title="New shipment"><i class="ic i-newdoc" aria-hidden="true"></i></a><?php endif; ?>
      <a href="<?= h(sl_route('Notifications')) ?>" aria-label="Messages" title="Messages"><i class="ic i-message" aria-hidden="true"></i></a>
      <a href="<?= h(sl_route('PublicTracking')) ?>" aria-label="Tracking" title="Tracking"><i class="ic i-route" aria-hidden="true"></i></a>
      <?php else: ?>
      <?php if ($user->cdp_hasPermission('add_shipment')): ?><a class="<?= ($slPage['id'] ?? '') === 'new' ? 'is-on' : '' ?>" href="<?= h(sl_route('NewShipment')) ?>" aria-label="New shipment" title="New shipment"><i class="ic i-newdoc" aria-hidden="true"></i></a><?php endif; ?>
      <?php if (sl_navAllowed('ai:use', [])): ?><a href="<?= h(sl_route('StaffAI')) ?>" aria-label="AI assistant" title="AI assistant"><i class="ic i-ai" aria-hidden="true"></i></a><?php endif; ?>
      <a href="<?= h(sl_route('PublicTracking')) ?>" aria-label="Tracking" title="Tracking"><i class="ic i-track" aria-hidden="true"></i></a>
      <?php if ($user->cdp_hasPermission('view_shipment_list')): ?><a href="<?= h(sl_route('LockerSearch')) ?>" aria-label="Search tracking number" title="Search tracking number"><i class="ic i-tsearch" aria-hidden="true"></i></a><?php endif; ?>
      <?php if ($user->cdp_hasPermission(['view_consolidate_list', 'view_consolidate_package_list'])): ?><a href="<?= h(sl_route('Consolidations')) ?>" aria-label="Consolidation" title="Consolidation"><i class="ic i-layers" aria-hidden="true"></i></a><?php endif; ?>
      <?php if ($user->cdp_hasPermission(['warehouse_view', 'view_warehouse_delivery'])): ?><a href="<?= h(sl_route('Warehouse')) ?>" aria-label="Warehouse" title="Warehouse"><i class="ic i-warehouse" aria-hidden="true"></i></a><?php endif; ?>
      <?php if (function_exists('sl_displayCurrency') && !sl_userIs('client')): $slGhs = sl_displayCurrency() === 'GHS'; ?><button type="button" class="sl-ccy-rail<?= $slGhs ? ' is-on on' : '' ?>" role="switch" aria-checked="<?= $slGhs ? 'true' : 'false' ?>" aria-label="Show amounts in <?= $slGhs ? 'USD' : 'GHS' ?>" title="Showing <?= $slGhs ? 'GHS' : 'USD' ?> · click to show <?= $slGhs ? 'USD' : 'GHS' ?>" data-sl-action="ajax/sl/display_currency_ajax.php" data-sl-reload><span class="ccy-pill" aria-hidden="true"><b class="<?= $slGhs ? '' : 'act' ?>">USD</b><b class="<?= $slGhs ? 'act' : '' ?>">GHS</b></span></button><?php endif; ?>
      <?php endif; ?>
      <div class="sl-shortcuts" data-shortcuts-user="<?= (int) $_SESSION['userid'] ?>" aria-label="My shortcuts"><span class="sl-shortcuts-caption">Pinned</span><div class="sl-shortcuts-list"></div><span class="sl-shortcuts-hint">Drop here</span></div>
      <a class="me-pic" href="<?= h($slProfileUrl) ?>" aria-label="Your profile, <?= h($slName) ?>" style="width:34px;height:34px;margin-top:auto;font-size:14px;<?= $slPicStyle ?>"><?= $slAvatar ? '' : h($slInitials) ?></a>
    </div>
    <aside class="sl-side" id="sl-navigation" aria-label="<?= $slIsClient ? 'Customer navigation' : 'Main navigation' ?>">
      <div class="nav-x"><span class="fw5">Menu</span><button class="sl-ib" type="button" data-sl-nav aria-controls="sl-navigation" aria-expanded="true" aria-label="Close the menu"><i class="ic i-x" aria-hidden="true"></i></button></div>
      <?php include __DIR__ . '/nav_groups.php'; ?>
      <div class="sl-mobile-shortcuts" aria-label="My shortcuts"><div class="sl-gl">My shortcuts</div><nav class="sl-shortcuts-list sl-nav"></nav></div>
      <div class="sl-gl">Other</div>
      <nav class="sl-nav">
        <?php if (!$slIsClient && $user->cdp_hasPermission('view_tools')): ?><a href="<?= h(sl_route('SettingsHub') . '#language') ?>"><i class="ic i-globe" aria-hidden="true"></i><span>Language</span></a><?php endif; ?>
        <?php if ($slHasSupport): ?><a href="<?= h($slHelpUrl) ?>"><i class="ic i-help" aria-hidden="true"></i><span>Get help</span></a><?php endif; ?>
        <a href="logout.php" class="danger"><i class="ic i-logout" aria-hidden="true"></i><span>Sign out</span></a>
      </nav>
      <a class="me-card" href="<?= h($slProfileUrl) ?>" aria-label="Signed in as <?= h($slName) ?><?= $slSub ? ', ' . h($slSub) : '' ?>. Open your profile">
        <?= $slPic(44) ?>
        <span class="grow col" style="min-width:0"><b><?= h($slName) ?></b><small><?= h($slSub) ?></small></span>
        <i class="ic ic-sm i-more mut" aria-hidden="true"></i>
      </a>
      <?php if ($slIsClient): ?>
      <a class="me-sub" href="<?= h(sl_route('Main')) ?>"><i class="ic ic-sm i-phone" aria-hidden="true"></i>My shipping dashboard</a>
      <?php elseif (sl_navAllowed('cs:agent', [])): ?>
      <a class="me-sub" href="<?= h(sl_route('CsInbox')) ?>"><i class="ic ic-sm i-ticket" aria-hidden="true"></i>Customer service<span class="sl-tag"><?= (int) $slOpenTickets ?> open</span></a>
      <?php endif; ?>
    </aside>

    <main class="sl-main<?= !empty($slPage['mainClass']) ? ' ' . h($slPage['mainClass']) : '' ?>" id="sl-main" tabindex="-1">
      <div class="sl-wrap<?= !empty($slPage['wide']) ? ' wide' : '' ?>">
<?php if (!empty($_SESSION['imp_original_username'])): ?>
        <div class="sl-banner warn"><i class="ic ic-sm i-eye" aria-hidden="true"></i>You are viewing the app as <b><?= h($slName) ?></b>. <a href="view_as.php?stop=1" class="fw5">Exit view mode</a></div>
<?php endif; ?>
<?php if (!empty($_SESSION['sl_flash'])): $slFlash = $_SESSION['sl_flash']; unset($_SESSION['sl_flash']); ?>
        <div class="sl-banner <?= h($slFlash['type'] ?? 'ok') ?>" role="status"><?= h($slFlash['text'] ?? '') ?></div>
<?php endif; ?>
