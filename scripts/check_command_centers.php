<?php
/** Check navigation structure and local destinations without loading the app or database. */
define('CDP_ROOT', dirname(__DIR__));
require CDP_ROOT . '/helpers/sl_nav.php';

$groups = sl_navStaff();
$names = array_column($groups, 'label');
$expectedGroups = ['Operations', 'Command Centers', 'Developer', 'Support'];
if (array_slice($names, 0, 4) !== $expectedGroups) throw new RuntimeException('The primary sidebar groups are out of order.');
$expected = [
    'Command Centers' => ['Management Center', 'AI Automation Center', 'Notification Control', 'Payment Gateways', 'Loyalty & Referrals', 'Customer Care', 'Communications', 'Security Center', 'My Account Security'],
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
        if (isset($ids[$item['id']])) throw new RuntimeException('Duplicate navigation id: ' . $item['id']);
        $ids[$item['id']] = true;
        $url = $item['href'] ?? sl_route($item['board']);
        if (preg_match('~^(?:https?:)?//~i', $url) || !is_file(CDP_ROOT . '/' . strtok($url, '?#'))) {
            throw new RuntimeException('Missing or external destination: ' . $url);
        }
    }
}
echo "PASS: center, developer and support navigation uses unique ids and local pages.\n";
