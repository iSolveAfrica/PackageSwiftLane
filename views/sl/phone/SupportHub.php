<?php
/** M-Care: customer care at a glance (support_hub.php). Data: sl_csHubData() plus the WhatsApp waiting count. */
require_once CDP_ROOT . '/views/sl/data/phone_msg.php';
require_once CDP_ROOT . '/helpers/sl_mx.php';
$k = $d['kpis'] ?? [];
$waWaiting = sl_pmWaWaiting();
$breached = (int) preg_replace('/\D/', '', (string) ($k[2]['v'] ?? '0'));
$sp = function (array $bars): string {
    if (!$bars) return '';
    $out = '<div class="mx-sp" aria-hidden="true">';
    $n = count($bars);
    foreach ($bars as $i => $b) $out .= '<i class="' . ($i === $n - 1 ? 'now' : '') . '" style="' . h($b['s']) . '"></i>';
    return $out . '</div>';
};
$tile = function (string $icon, string $label, string $value, string $note, string $cls, array $bars, string $href) use ($sp): string {
    return '<a class="mx-kt" href="' . h($href) . '"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-' . h($icon) . '" aria-hidden="true"></i></span><small>' . h($label) . '</small></div><b>' . h($value) . '</b>'
        . ($note !== '' ? '<em class="' . h($cls) . '">' . h($note) . '</em>' : '') . $sp($bars) . '</a>';
};
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-msg.css')) ?>">
<?= sl_phoneTop('Customer care', '', ['back' => 'menu.php'], sl_phoneIcon('more', 'Open the full page', sl_url(['view' => 'web']))) ?>
<main class="mx-body">
<?php if (empty($d['ready'])): ?><div class="mx-ban warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>The helpdesk tables are missing. Ask an administrator to run the support upgrade.</span></div><?php endif; ?>
<section class="mx-k c2">
<?= $tile('ticket', 'Open tickets', (string) ($k[0]['v'] ?? '0'), $breached > 0 ? $breached . ' past SLA' : '', 'warn', $k[0]['sp'] ?? [], sl_route('CsInbox')) ?>
<?= $tile('message', 'WhatsApp waiting', sl_num($waWaiting), '', '', [], sl_route('CommsInbox')) ?>
<?= $tile('clock', 'First reply', (string) ($k[1]['v'] ?? '—'), (string) ($k[1]['s'] ?? ''), 'up', $k[1]['sp'] ?? [], sl_route('CsReports')) ?>
<?= $tile('star', 'Happy · CSAT', (string) ($k[3]['v'] ?? '—'), (string) ($k[3]['s'] ?? ''), '', $k[3]['sp'] ?? [], sl_route('CsReports', ['section' => 'csat'])) ?>
</section>
<section class="mx-act c4">
<a href="<?= h(sl_route('CsInbox')) ?>"><span><i class="ic i-ticket" aria-hidden="true"></i></span><small>Tickets</small></a>
<a href="<?= h(sl_route('CommsInbox')) ?>"><span><i class="ic i-message" aria-hidden="true"></i></span><small>WhatsApp</small></a>
<a href="<?= h(sl_route('CsPlaybook')) ?>"><span><i class="ic i-book" aria-hidden="true"></i></span><small>Playbooks</small></a>
<a href="<?= h(sl_route('CsAI')) ?>"><span><i class="ic i-ai" aria-hidden="true"></i></span><small>AI replies</small></a>
</section>
<div class="mx-sec"><h2>Needs a reply</h2><?php if (!empty($d['sla'])): ?><a href="<?= h(sl_route('CsInbox')) ?>">All</a><?php endif; ?></div>
<?php if (!empty($d['sla'])): ?>
<section class="mx-card mx-list">
<?php foreach ($d['sla'] as $x): $pc = $x['d'] === 'e' ? ['overdue', 'Late'] : ($x['d'] === 'w' ? ['pending', 'Soon'] : ['plain', 'Open']); ?>
<a class="mx-row" href="<?= h(sl_route('CsTicket', ['id' => $x['id']])) ?>"><span class="mx-li"><i class="ic i-ticket" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($x['t']) ?></b><small><?= h($x['ref']) ?> · <?= h($x['who']) ?> · <?= h($x['cat']) ?></small></span><span class="mx-rr"><span class="sl-pill <?= h($pc[0]) ?>"><?= h($pc[1]) ?></span><small><?= h($x['left']) ?></small></span></a>
<?php endforeach; ?>
</section>
<?php else: ?>
<?= sl_mxEmpty('check', 'Nothing is waiting', 'No open tickets right now.') ?>
<?php endif; ?>
</main>
