<?php
/** Check navigation structure and local destinations without loading the app or database. */
define('CDP_ROOT', dirname(__DIR__));
require CDP_ROOT . '/helpers/sl_nav.php';

$groups = sl_navStaff();
$names = array_column($groups, 'label');
$expectedGroups = ['Operations', 'Command Centers', 'Developer', 'Support'];
// Other groups (e.g. RIDERS) may sit between them; these four keep their order and Operations comes first.
if ($names[0] !== 'Operations' || array_values(array_intersect($names, $expectedGroups)) !== $expectedGroups) throw new RuntimeException('The primary sidebar groups are out of order.');
$expected = [
    'Command Centers' => ['Management Center', 'Gerald Master View', 'AI Automation Center', 'Payment Gateways', 'Loyalty & Referrals', 'Customer Care', 'Notifications & Messages', 'Security Center', 'My Account Security'],
    'Developer' => ['Developer Console'],
    'Support' => ['Support Desk', 'Service Panel', 'Service Reports', 'Playbooks', 'Knowledge Base', 'Support Settings'],
];
$ids = [];
foreach ($groups as $group) {
    $labels = array_column($group['items'], 'label');
    if (isset($expected[$group['label']]) && array_slice($labels, 0, count($expected[$group['label']])) !== $expected[$group['label']]) {
        throw new RuntimeException('Incorrect destinations in ' . $group['label']);
    }
    foreach ($group['items'] as $item) {
        // A sub-menu's children are checked like items (ids unique across the whole sidebar).
        foreach (array_merge([$item], $item['children'] ?? []) as $it) {
            if (isset($ids[$it['id']])) throw new RuntimeException('Duplicate navigation id: ' . $it['id']);
            $ids[$it['id']] = true;
            $url = $it['href'] ?? sl_route($it['board']);
            if (preg_match('~^(?:https?:)?//~i', $url) || !is_file(CDP_ROOT . '/' . strtok($url, '?#'))) {
                throw new RuntimeException('Missing or external destination: ' . $url);
            }
        }
    }
}
// Notifications & Messages: one Command Centers item whose sub-menu lists every messaging page, in this order.
$comms = array_column(sl_navCommsChildren(), 'label');
$expectedComms = ['Overview', 'Automations', 'Status messages', 'Send', 'Outbox', 'WhatsApp', 'Customer history', 'Surveys', 'Settings'];
if ($comms !== $expectedComms) throw new RuntimeException('The Notifications & Messages sub-menu is not ' . implode(', ', $expectedComms));
foreach ($groups as $group) {
    foreach ($group['items'] as $item) {
        if (in_array($item['id'], ['notifc', 'notifystudio', 'campaigns', 'commscenter', 'ebuild', 'msgtpl', 'compose', 'bcast'], true)) {
            throw new RuntimeException('Retired messaging item still in the sidebar: ' . $item['label']);
        }
    }
}
echo "PASS: center, developer and support navigation (with the Notifications & Messages sub-menu) uses unique ids and local pages.\n";
