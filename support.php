<?php
// *************************************************************************
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *************************************************************************
// * Help & Support: customers chat with King or a person, follow their requests, read help articles.
// * Swift Lane design: views/sl/pages/SupportHelp.php + dataJs/sl/support_help.js
// * (ajax/support/customer_ajax.php). See helpers/support.php and docs/CUSTOMER_SERVICE.md.
// *************************************************************************
require_once 'loader.php';
require_once 'helpers/querys.php';
require_once 'helpers/support_king.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
if (cdp_csIsAgent($user)) {
    // Staff work cases from the desk; a link to a ticket goes straight to it.
    header('Location: ' . (!empty($_GET['t']) ? 'support_ticket.php?id=' . (int) $_GET['t'] : 'support_inbox.php'));
    exit;
}
if (!cdp_csIsCustomer($user)) {
    header('Location: error403.php');
    exit;
}
$ready = cdp_csEnsureTables();
$entity = null;
if ($ready && !empty($_GET['about']) && strpos((string) $_GET['about'], ':') !== false) {
    [$et, $eid] = explode(':', (string) $_GET['about'], 2);
    $en = cdp_csEntity($et, (int) $eid);
    if ($en && (int) $en['owner'] === cdp_csUid()) {
        $entity = ['type' => $en['type'], 'id' => $en['id'], 'ref' => $en['ref']];
    }
}
$cfg = [
    'url' => 'ajax/support/customer_ajax.php',
    'king' => ['on' => $ready && cdp_csKingOn(), 'name' => (string) cdp_csSetting('king_name', 'King'), 'greeting' => (string) cdp_csSetting('king_greeting')],
    'hours' => (string) cdp_csSetting('business_hours', ''),
    'entity' => $entity,
    'openTicket' => (int) ($_GET['t'] ?? 0),
];
sl_render('SupportHelp', ['id' => 'support', 'title' => 'Help and support', 'board' => '', 'scripts' => ['dataJs/sl/support_help.js']], ['ready' => $ready, 'cfg' => $cfg]);
