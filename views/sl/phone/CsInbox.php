<?php
/**
 * M-Desk: the support desk on a phone. Same $d as the web inbox (views/sl/data/support.php sl_csInboxData);
 * segments are the same GET parameters the web page reads (view, priority, page). New ticket: the web
 * page's own dialog (views/sl/pages/_cs_new_ticket.php) posting to ajax/support/desk_ajax.php action=create.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
require_once CDP_ROOT . '/views/sl/data/phone_desk.php';
$slPhone['tab'] = 'Inbox';
$slPhone['scripts'][] = 'dataJs/sl/cs_common.js';
$n = [];
foreach ($d['views'] as $v) $n[$v['key']] = $v['n'];
$prio = (string) ($d['filters']['priority'] ?? '');
$seg = $prio === 'urgent' ? 'urgent' : ($d['view'] === 'mine' ? 'mine' : ($d['view'] === 'closed' ? 'closed' : 'open'));
$urgent = $d['ready'] ? sl_pdUrgentCount() : 0;
$segs = [
    ['mine', 'Mine', $n['mine'] ?? '', sl_route('CsInbox', ['view' => 'mine'])],
    ['open', 'Open', $n['all'] ?? '', sl_route('CsInbox')],
    ['urgent', 'Urgent', $urgent ? (string) $urgent : '', sl_route('CsInbox', ['priority' => 'urgent'])],
    ['closed', 'Closed', '', sl_route('CsInbox', ['view' => 'closed'])],
];
$pill = ['u' => 'overdue', 'h' => 'pending'];
$pages = (int) ceil($d['count'] / max(1, $d['per']));
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-desk.css')) ?>">
<?= sl_phoneTop('Support desk', '', ['back' => 'menu.php'], sl_phoneIcon('chart', 'Support reports', sl_route('CsReports'))) ?>
<main class="mx-body">
<?php if (!$d['ready']): ?><div class="mx-ban warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>The helpdesk tables are missing. Ask an administrator to run the support migration.</span></div><?php endif; ?>
<nav class="mx-seg" aria-label="Ticket lists"><?php foreach ($segs as $s): ?><a class="<?= $seg === $s[0] ? 'on' : '' ?>" href="<?= h($s[3]) ?>"<?= $seg === $s[0] ? ' aria-current="page"' : '' ?>><?= h($s[1]) ?><?= $s[2] !== '' ? ' ' . h($s[2]) : '' ?></a><?php endforeach; ?></nav>
<?php if ($d['rows']): ?>
<section class="mx-card mx-list">
<?php foreach ($d['rows'] as $r): $cls = $r['prioCls'] === 'u' ? 'overdue' : $r['statusCls']; $lbl = $r['prioCls'] === 'u' && !in_array($r['statusCls'], ['delivered', 'plain'], true) ? $r['prio'] : $r['status']; ?>
<a class="mx-row" href="<?= h(sl_route('CsTicket', ['id' => $r['id']])) ?>"><span class="mx-ava"><?= h($r['ini']) ?></span><span class="mx-rm"><b><?= h($r['ref'] . ' ' . $r['subject']) ?></b><small><?= h($r['customer'] . ' · ' . $r['chanLabel']) ?></small></span><span class="mx-rr"><span class="sl-pill <?= h($cls) ?>"><?= h($lbl) ?></span><small><?= h($r['ago']) ?></small></span></a>
<?php endforeach; ?>
</section>
<?php if ($pages > 1): ?><div class="pd-pager"><?php if ($d['page'] > 1): ?><a href="<?= h(sl_url(['page' => $d['page'] - 1])) ?>">Newer</a><?php else: ?><span></span><?php endif; ?><?php if ($d['page'] < $pages): ?><a href="<?= h(sl_url(['page' => $d['page'] + 1])) ?>">Older</a><?php endif; ?></div><?php endif; ?>
<?php elseif ($d['ready']): ?>
<?= sl_mxEmpty('inbox', 'No tickets here', $seg === 'mine' ? 'Nothing is assigned to you right now.' : 'This list is empty.') ?>
<?php endif; ?>
</main>
<?php if ($d['ready']): ?>
<button type="button" class="mx-fab" data-sl-open="cs-new" aria-label="New ticket"><i class="ic i-plus" aria-hidden="true"></i></button>
<?php include CDP_ROOT . '/views/sl/pages/_cs_new_ticket.php'; ?>
<?php endif; ?>
