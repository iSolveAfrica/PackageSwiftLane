<?php
/**
 * M-AiCenter: what the AI assistant did, its page ideas and what it knows. Same $d as the web page
 * (views/sl/data/ai.php sl_aiCenterData). Writes go through the web page's own script dataJs/sl/ai_center.js to
 * ajax/ai_assistant/settings_ajax.php (knowledge_add, knowledge_delete, idea_status, analyse). Provider and key
 * changes stay on the website (the masked key is never shown in full).
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['nav'] = 'staff';
$slPhone['tab'] = 'Menu';
$slPhone['scripts'][] = 'dataJs/sl/ai_center.js';
$st = $d['stats'];
$total = (int) $st['total'];
$unans = count($st['unanswered']);
$okPct = $total ? (int) round(($total - $unans) / $total * 100) : 0;
$ideas = array_values(array_filter($d['ideas'], fn($i) => in_array($i['status'] ?? 'new', ['new', 'planned'], true)));
$king = cdp_kingName();
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-centres.css')) ?>">
<?= sl_phoneTop('AI automation center', $d['providerLabel'] . ' · ' . ($d['configured'] ? 'on' : 'off'), ['back' => 'menu.php'], $d['canManage'] ? sl_phoneIcon('cog', 'Provider settings', 'ai_center.php?view=web#settings') : '') ?>
<main class="mx-body">
<?php if (!$d['configured']): ?>
<div class="mx-ban warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span><b>Provider not configured.</b> <?= $d['hasKey'] ? 'A key is saved but the assistant is switched off.' : 'No API key is saved.' ?> Nothing here calls a model until a super admin sets it up on the website.</span></div>
<?php endif; ?>
<section class="mx-hero">
  <div class="mx-hk">Last 30 days <?= h($king) ?> answered<?php if ($total): ?><span class="mx-tag"><?= $okPct ?>% answered</span><?php endif; ?></div>
  <b class="mx-hv"><?= h(sl_num($total)) ?> question<?= $total === 1 ? '' : 's' ?></b>
  <small><?= h(sl_num($st['users'] ?? 0)) ?> people asked · <?= h(sl_num($d['usage']['answers'])) ?> answers · <?= h(sl_num($d['usage']['tokens'] + $d['usage']['csTokens'])) ?> tokens</small>
  <div class="mx-prog"><i style="width:<?= $okPct ?>%"></i></div>
</section>
<?= sl_mxSec('Page ideas from questions') ?>
<?php foreach (array_slice($ideas, 0, 4) as $i): ?>
<section class="mx-card" style="padding:14px;display:flex;flex-direction:column;gap:8px">
  <div style="display:flex;gap:8px;align-items:center"><span class="mx-li"><i class="ic i-ai" aria-hidden="true"></i></span><b style="flex:1"><?= h($i['title']) ?></b><span class="sl-pill sorting"><?= (int) $i['votes'] ?> vote<?= (int) $i['votes'] === 1 ? '' : 's' ?></span></div>
  <?php if (!empty($i['purpose'])): ?><small class="mx-note"><?= h(mb_substr((string) $i['purpose'], 0, 160)) ?></small><?php endif; ?>
  <div class="mx-two"><?php if (($i['status'] ?? 'new') === 'new'): ?><button type="button" class="mx-btn pc-btn" data-aic="idea_status" data-p-id="<?= (int) $i['id'] ?>" data-p-status="planned">Plan it</button><?php else: ?><button type="button" class="mx-btn pc-btn" data-aic="idea_status" data-p-id="<?= (int) $i['id'] ?>" data-p-status="built">Mark built</button><?php endif; ?><button type="button" class="mx-btn sec pc-btn" data-aic="idea_status" data-p-id="<?= (int) $i['id'] ?>" data-p-status="dismissed">Dismiss</button></div>
</section>
<?php endforeach; ?>
<?php if (!$ideas): ?>
<?= sl_mxEmpty('ai', 'No open page ideas', $d['configured'] ? 'Analyse the questions people asked to get ideas.' : 'Ideas appear once the assistant is set up.') ?>
<?php endif; ?>
<?php if ($d['configured']): ?><div class="mx-btns"><button type="button" class="mx-btn sec pc-btn" data-aic="analyse">Analyse questions</button></div><?php endif; ?>
<?= sl_mxSec('What the assistant knows') ?>
<form class="mx-card mx-form" data-aic-form="knowledge_add"><label class="mx-f"><small>Teach a fact (added to every conversation)</small><input class="mx-in" name="note" placeholder="e.g. Air cut-off is Friday 18:00" maxlength="1000" autocomplete="off" required></label><button class="mx-btn pc-btn" type="submit">Add fact</button></form>
<?php if ($d['knowledge']): ?>
<section class="mx-card mx-list pc-wrap">
<?php foreach (array_slice($d['knowledge'], 0, 10) as $k): ?>
<div class="mx-row"><span class="mx-li"><i class="ic i-book" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($k['note']) ?></b><small><?= h($k['author'] ?: $k['source']) ?></small></span><span class="mx-rr"><button type="button" class="mx-ib pc-btn" data-aic="knowledge_delete" data-p-id="<?= (int) $k['id'] ?>" aria-label="Remove this fact"><i class="ic ic-sm i-trash" aria-hidden="true"></i></button></span></div>
<?php endforeach; ?>
</section>
<?php endif; ?>
<?php if ($st['by_area']): ?>
<?= sl_mxSec('Questions by area') ?>
<section class="mx-card mx-mts">
<?php $bm = max(1, max(array_column($st['by_area'], 'count'))); foreach (array_slice($st['by_area'], 0, 5) as $b): ?>
  <div class="mx-mt"><div><span><?= h($b['label']) ?></span><b><?= (int) $b['count'] ?></b></div><span class="mx-trk"><i style="width:<?= (int) sl_pct($b['count'], $bm) ?>%"></i></span></div>
<?php endforeach; ?>
</section>
<?php endif; ?>
<div class="mx-btns"><a class="mx-btn" href="<?= h(sl_route('StaffAI')) ?>">Ask <?= h($king) ?> anything</a></div>
</main>
