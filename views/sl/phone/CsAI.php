<?php
/**
 * M-CsAI: the AI for customers on a phone. Same $d as the web page (sl_csAiData). Administrators switch it with
 * ajax/support/admin_ajax.php action=settings (king_mode / copilot_enabled), the same values the web toggles send.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
require_once CDP_ROOT . '/views/sl/data/phone_desk.php';
$slPhone['scripts'][] = 'dataJs/sl/cs_common.js';
$slPhone['scripts'][] = 'dataJs/sl/phone/cs_desk.js';
$s = $d['settings'];
$admin = !empty($d['isAdmin']);
$ready = !empty($d['aiReady']);
$agentOn = $s['king_mode'] === 'customer';
$copOn = $s['king_mode'] !== 'off' && $s['copilot_enabled'] === '1';
$m = $d['ready'] ? sl_pdAiMonth() : ['n' => 0, 'handoff' => 0];
$handPct = $m['n'] ? (int) round($m['handoff'] / $m['n'] * 100) : 0;
$sw = function (string $key, bool $on, string $label, bool $enabled) use ($s) {
    return '<button type="button" class="mx-sw' . ($on ? ' on' : '') . '" role="switch" aria-checked="' . ($on ? 'true' : 'false') . '" aria-label="' . h($label) . '" data-pd-ai="' . h($key) . '" data-mode="' . h($s['king_mode'] === 'off' ? 'copilot' : $s['king_mode']) . '"' . ($enabled ? '' : ' disabled aria-disabled="true"') . '></button>';
};
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-desk.css')) ?>">
<?= sl_phoneTop('AI for customers', '', ['back' => sl_route('CsInbox')], sl_phoneIcon('ai', 'AI centre', sl_route('AiCenter'))) ?>
<main class="mx-body">
<section class="mx-hero"><div class="mx-hk"><?= h($d['king']) ?> answered<span class="mx-tag"><?= $agentOn && $ready ? 'On' : 'Off' ?></span></div><b class="mx-hv"><?= sl_num($m['n']) ?> chat<?= $m['n'] === 1 ? '' : 's' ?></b><small>this month<?= $m['n'] ? ' · ' . $handPct . '% passed to staff' : '' ?></small><?php if ($m['n']): ?><div class="mx-prog"><i style="width:<?= 100 - $handPct ?>%"></i></div><?php endif; ?></section>
<?php if (!$ready): ?><div class="mx-ban warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span><?= h($d['king']) ?> stays off until a super admin sets the AI provider, key and model in the AI centre. People answer customers meanwhile.</span></div><?php endif; ?>
<section class="mx-card mx-tgs">
<div class="mx-tg"><span class="mx-rm"><b>Answer customers in the web chat</b><small>From their own records and published help · hands over to staff when unsure</small></span><?= $sw('agent', $agentOn, 'Answer customers in the web chat', $admin && ($ready || $agentOn)) ?></div>
<div class="mx-tg"><span class="mx-rm"><b>Suggest replies to staff</b><small>Drafts, summaries and text clean-up · nothing is sent by the AI</small></span><?= $sw('copilot', $copOn, 'Suggest replies to staff', $admin) ?></div>
</section>
<section class="mx-card mx-list">
<a class="mx-row" href="<?= h(sl_route('CsPlaybook')) ?>"><span class="mx-li"><i class="ic i-book" aria-hidden="true"></i></span><span class="mx-rm"><b>Knowledge it uses</b><small><?= (int) ($d['kbPublished'] ?? 0) ?> published article<?= (int) ($d['kbPublished'] ?? 0) === 1 ? '' : 's' ?></small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
<a class="mx-row" href="support.php"><span class="mx-li"><i class="ic i-message" aria-hidden="true"></i></span><span class="mx-rm"><b>Try it</b><small>Chat as a customer</small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
</section>
<?php if ($d['runs']): ?>
<?= sl_mxSec('Recent runs') ?>
<section class="mx-card mx-list"><?php foreach (array_slice($d['runs'], 0, 6) as $r): ?><?= sl_mxRow('ai', $r['kind'] . ($r['ref'] ? ' · ' . $r['ref'] : ''), $r['at'] . ($r['model'] ? ' · ' . $r['model'] : ''), '<span class="sl-pill ' . ($r['ok'] ? 'delivered' : 'overdue') . '">' . h($r['outcome']) . '</span>', $r['ticket_id'] ? sl_route('CsTicket', ['id' => $r['ticket_id']]) : '') ?><?php endforeach; ?></section>
<?php endif; ?>
</main>
