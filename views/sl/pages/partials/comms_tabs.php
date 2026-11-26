<?php
/**
 * Communications sub-navigation, shared by CommsHub (Overview), CommsCenter
 * and CommsInbox. Shows only the pages this user may open, with the same
 * permission rules as the sidebar (helpers/sl_nav.php: sl_navAllowed), and
 * marks the current one. Set $commsTab to the nav id of the current page.
 */
require_once CDP_ROOT . '/helpers/sl_nav.php';
$commsTab = isset($commsTab) ? (string) $commsTab : '';
$commsTabItems = [
    ['id' => 'commshub', 'label' => 'Overview', 'board' => 'CommsHub', 'perm' => ['push_notifications', 'manage_newsletter', 'view_message_logs']],
    ['id' => 'commsinbox', 'label' => 'WhatsApp Inbox', 'board' => 'CommsInbox', 'perm' => 'ops:staff', 'waiting' => true],
    ['id' => 'commscenter', 'label' => 'Communication Center', 'board' => 'CommsCenter', 'perm' => 'ops:staff'],
    ['id' => 'campaigns', 'label' => 'Campaigns', 'board' => 'Campaigns', 'perm' => ['view_message_logs', 'manage_newsletter', 'push_notifications']],
    ['id' => 'messages', 'label' => 'Message logs', 'board' => 'MessageLogs', 'perm' => 'view_message_logs'],
];
$commsTabOut = [];
foreach ($commsTabItems as $it) {
    if (!sl_navAllowed($it['perm'], $it)) {
        continue;
    }
    if (function_exists('sl_navItemEnabled') && !sl_navItemEnabled($it['id'])) {
        continue;
    }
    $n = 0;
    if (!empty($it['waiting'])) {
        // One definition and one COUNT per request (cdp_commWaitingCount); sidebar fallback otherwise.
        $n = function_exists('cdp_commWaitingCount') ? cdp_commWaitingCount()
            : (sl_tableExists('cdb_comm_threads') ? sl_navCount("SELECT COUNT(*) FROM cdb_comm_threads WHERE status = 'open'") : 0);
    }
    $commsTabOut[] = $it + ['n' => $n];
}
$commsTabCss = '.cm-tabs{display:flex;gap:2px;flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none;border-bottom:1px solid var(--line);margin:0 0 4px} .cm-tabs::-webkit-scrollbar{display:none} .cm-tabs a{display:inline-flex;align-items:center;gap:8px;height:40px;padding:0 12px;white-space:nowrap;color:var(--muted);font:500 15.5px var(--font);border-bottom:2px solid transparent;margin-bottom:-1px;text-decoration:none} .cm-tabs a:hover{color:var(--ink)} .cm-tabs a.on{color:var(--ink);border-bottom-color:var(--accent,var(--ink))} .cm-tabs em{font-style:normal;min-width:20px;height:20px;padding:0 6px;border-radius:999px;background:var(--amber-bg);color:var(--amber-ink);font:600 13px var(--font);display:inline-grid;place-items:center}';
if (count($commsTabOut) > 1): ?>
<?php if ($commsTab === 'commscenter'): // CommsHub.css and CommsInbox.css carry these rules; Communication Center has no board stylesheet ?>
<style><?= $commsTabCss ?></style>
<?php endif; ?>
<nav class="cm-tabs" aria-label="Communications">
<?php foreach ($commsTabOut as $it): $on = $it['id'] === $commsTab; ?>
<a class="<?= $on ? 'on' : '' ?>" href="<?= h(sl_route($it['board'])) ?>"<?= $on ? ' aria-current="page"' : '' ?>><?= h($it['label']) ?><?php if ($it['n'] > 0): ?><em data-cm-waiting title="Waiting for a person"><?= (int) $it['n'] ?></em><?php endif; ?></a>
<?php endforeach; ?>
</nav>
<?php endif;
