<?php
/**
 * Notifications & Messages sub-navigation, shared by every page of the area
 * (Overview, Automations, Status messages, Send, Outbox, WhatsApp, WhatsApp Web, Customer
 * history, Surveys, Settings). The list is the sidebar's sub-menu
 * (helpers/sl_nav.php: sl_navCommsChildren), filtered with the same rules, so
 * the tabs and the menu can never disagree. Set $commsTab to the current
 * page's id (commshub is accepted for the Overview); when it is not set the
 * tab whose page is being served is marked.
 */
require_once CDP_ROOT . '/helpers/sl_nav.php';
$commsTab = isset($commsTab) ? (string) $commsTab : '';
if ($commsTab === 'commshub') {
    $commsTab = 'commsoverview';
}
$commsTabOut = sl_navResolveChildren(sl_navCommsChildren(), $commsTab);
$commsTabCss = '.cm-tabs{display:flex;gap:2px;flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none;border-bottom:1px solid var(--line);margin:0 0 4px} .cm-tabs::-webkit-scrollbar{display:none} .cm-tabs a{display:inline-flex;align-items:center;gap:8px;height:40px;padding:0 12px;white-space:nowrap;color:var(--muted);font:500 15.5px var(--font);border-bottom:2px solid transparent;margin-bottom:-1px;text-decoration:none} .cm-tabs a:hover{color:var(--ink)} .cm-tabs a.on{color:var(--ink);border-bottom-color:var(--accent,var(--ink))} .cm-tabs em{font-style:normal;min-width:20px;height:20px;padding:0 6px;border-radius:999px;background:var(--amber-bg);color:var(--amber-ink);font:600 13px var(--font);display:inline-grid;place-items:center}';
if (count($commsTabOut) > 1): ?>
<style><?= $commsTabCss ?></style>
<nav class="cm-tabs" aria-label="Notifications and messages">
<?php foreach ($commsTabOut as $it): $on = $it['cls'] === 'is-active'; ?>
<a class="<?= $on ? 'on' : '' ?>" href="<?= h($it['href']) ?>"<?= $on ? ' aria-current="page"' : '' ?>><?= h($it['label']) ?><?php if ($it['c1'] !== ''): ?><em data-cm-waiting title="Waiting for a person"><?= (int) $it['c1'] ?></em><?php endif; ?></a>
<?php endforeach; ?>
</nav>
<script>(function () { var a = document.querySelector('.cm-tabs a.on'), n = a && a.parentNode; if (n && n.scrollWidth > n.clientWidth) { n.scrollLeft = Math.max(0, a.offsetLeft - n.offsetLeft - 16); } })();</script>
<?php endif;
