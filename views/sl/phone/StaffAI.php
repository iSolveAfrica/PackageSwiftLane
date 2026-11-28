<?php
/** M-AskKing: the staff AI assistant on a phone. $d = views/sl/data/ai.php; chat posts to ajax/ai_assistant/chat_ajax.php (dataJs/sl/phone/ask_king.js). */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['tab'] = 'Home';
$slPhone['scripts'] = array_merge($slPhone['scripts'], ['assets/vendor/libs/marked/marked.min.js', 'assets/vendor/libs/dompurify/purify.min.js', 'dataJs/sl/ai_response.js', 'dataJs/sl/phone/ask_king.js']);
$live = !empty($d['configured']) && !empty($d['canUse']);
$king = cdp_kingName();
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-ops.css')) ?>">
<?= sl_phoneTop('Ask ' . $king, $d['conv']['title'] ?? ($live ? ($d['model'] ?? '') : 'Not set up'), ['back' => sl_route('Main')], ($d['conv'] ? '<button type="button" class="mx-ib" id="ak-del" aria-label="Delete conversation"><i class="ic i-trash" aria-hidden="true"></i></button>' : '') . sl_phoneIcon('plus', 'New chat', sl_route('StaffAI'))) ?>
<main class="mx-body" id="ak" data-live="<?= $live ? 1 : 0 ?>" data-conv="<?= (int) ($d['conv']['id'] ?? 0) ?>" data-auto="<?= !empty($d['autoSend']) ? 1 : 0 ?>" data-king="<?= h($king) ?>">
<div class="mx-chan"><?= h($king) ?> answers from what your role can see (<?= h(strtolower($d['scope'] ?? 'staff')) ?>). Read only.</div>
<?php if (!$live): ?>
<div class="mx-ban warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span><?= h($king) ?> is not set up yet. <?= !empty($d['canManage']) ? 'Choose the provider, API key and model in the AI centre.' : 'A super admin sets it up in the AI centre.' ?> Until then no answers are given.</span></div>
<?php endif; ?>
<section class="mx-chat" id="ak-th" aria-live="polite">
<?php foreach ($d['msgs'] as $m): ?>
<div class="mx-msg <?= $m['me'] ? 'me' : 'ai' ?>"><?php foreach ($m['tools'] as $t): ?><small><i class="ic ic-sm i-search" aria-hidden="true"></i> <?= h($t) ?></small><?php endforeach; ?><?php if ($m['me']): ?><p><?= h($m['text']) ?></p><?php else: ?><div class="mx-ans" data-ai-answer><?= h($m['text']) ?></div><?php endif; ?><small><?= h($m['at']) ?></small></div>
<?php endforeach; ?>
</section>
<?php if (!$d['msgs']): ?>
<div class="mx-chips" id="ak-chips"><?php foreach ($d['starters'] as $st): ?><button type="button" data-ak-ask="<?= h($st['label']) ?>"<?= $live ? '' : ' disabled' ?>><?= h($st['label']) ?></button><?php endforeach; ?></div>
<?php endif; ?>
<?php if ($d['convs']): ?>
<details class="mx-grp"><summary><span>Recent chats</span><small><?= count($d['convs']) ?></small><i class="ic ic-sm i-down" aria-hidden="true"></i></summary><section class="mx-card mx-list">
<?php foreach (array_slice($d['convs'], 0, 15) as $c): ?><a class="mx-row" href="<?= h(sl_route('StaffAI', ['c' => $c['id']])) ?>"<?= $c['on'] ? ' aria-current="page"' : '' ?>><span class="mx-li"><i class="ic i-message" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($c['title']) ?></b><small><?= h($c['when']) ?></small></span></a><?php endforeach; ?>
</section></details>
<?php endif; ?>
</main>
<div class="mx-foot"><form id="ak-form"><div class="mx-comp"><label class="mx-compin"><span class="sl-sr">Your question</span><textarea id="ak-q" rows="1" maxlength="8000" placeholder="<?= h($live ? 'Ask about shipments, customers, money…' : $king . ' is not set up yet') ?>"<?= $live ? '' : ' disabled' ?>><?= h($d['draft'] ?? '') ?></textarea></label><button class="mx-send" type="submit" aria-label="Send"<?= $live ? '' : ' disabled' ?>><i class="ic i-send" aria-hidden="true"></i></button></div></form></div>
