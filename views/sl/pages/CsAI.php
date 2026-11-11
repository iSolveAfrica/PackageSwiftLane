<?php
/**
 * CsAI - from docs/handover/artifact-source/CsAI.dc.html: the AI for customers (King) and the agent copilot.
 * Data: views/sl/data/support.php (sl_csAiData: cdb_cs_settings king_* / copilot_enabled, cdb_cs_ai_runs).
 * Writes: ajax/support/admin_ajax.php action=settings (administrators). Never shows invented answers:
 * the demo boxes show real recent runs or a "not configured" state.
 */
if (!isset($d)) { $d = []; }
$s = $d['settings'];
$admin = $d['isAdmin'];
$ready = $d['aiReady'];
$agentOn = $s['king_mode'] === 'customer';
$copOn = $s['king_mode'] !== 'off' && $s['copilot_enabled'] === '1';
$tg = function (string $key, bool $on, string $label) use ($admin) {
    return '<span class="sl-toggle ' . ($on ? 'on' : '') . '" role="switch" aria-checked="' . ($on ? 'true' : 'false') . '" aria-label="' . h($label) . '" ' . ($admin ? 'data-ai-key="' . h($key) . '"' : 'data-static') . '></span>';
};
?>
<div class="sl-head"><div><h1 class="sl-title">AI Assistant</h1><p class="sl-lede">Let AI handle the repeatable parts of support · <?= h($d['king']) ?> uses <?= h($d['providerLabel']) ?><?= $d['model'] ? ' · ' . h($d['model']) : '' ?></p></div><div class="row g8 wrap"><?php if ($admin): ?><button class="sl-btn" type="button" data-ai-all <?= $ready ? '' : 'aria-disabled="true"' ?>><i class="ic ic-sm i-spark" aria-hidden="true"></i>Turn on all</button><?php endif; ?></div></div>
<div class="cs-tabs"><?php foreach ($d['tabs'] as $t): ?><a class="<?= $t['on'] ? 'on' : '' ?>" href="<?= h($t['href']) ?>"><?= h($t['label']) ?></a><?php endforeach; ?></div>
<?php if (!$ready): ?><div class="sl-banner warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span><b class="fw5">AI provider not configured.</b> <?= h($d['king']) ?> and the copilot stay off until a super admin sets the provider, API key and model in the <a href="<?= sl_route('AiCenter') ?>">AI centre</a>. Customers are answered by people meanwhile.</span></div><?php endif; ?>
<div class="grid cols3">
<div class="aicard"><div class="row between"><span class="sl-tile"><i class="ic i-cog" aria-hidden="true"></i></span><?= $tg('agent', $agentOn, 'AI agent for customers') ?></div><h3>AI agent · <?= h($d['king']) ?></h3><p>Answers customers in the web chat from their own records and published help, then hands over to a person when needed, when asked, or when unsure.</p><div class="demo"><?php if ($d['runs'] && ($k = array_values(array_filter($d['runs'], fn($r) => $r['kind'] === 'king')))): ?><b>Last answer</b> <?= h($k[0]['at']) ?> · <?= h($k[0]['outcome']) ?><?= $k[0]['ref'] ? ' · ' . h($k[0]['ref']) : '' ?><?php else: ?>No customer chats answered yet. Mode: <b><?= h(['off' => 'Off', 'copilot' => 'Helps agents only', 'customer' => 'Answers customers'][$s['king_mode']] ?? $s['king_mode']) ?></b>.<?php endif; ?></div><div class="row g8"><a class="sl-btn ghost sm" href="support.php">Try the agent</a><?php if ($admin): ?><button class="sl-btn ghost sm" type="button" data-sl-open="ai-limits">Set limits</button><?php endif; ?></div></div>
<div class="aicard"><div class="row between"><span class="sl-tile"><i class="ic i-reply" aria-hidden="true"></i></span><?= $tg('copilot', $copOn, 'Reply suggestions') ?></div><h3>Reply suggestions</h3><p>Reads the conversation (never the private notes) and drafts a reply your team edits and sends. Nothing is sent by the AI on its own.</p><div class="demo"><?php foreach (array_slice(cdp_csCanned($d['uid']), 0, 2) as $c): ?><span class="aipill"><?= h($c['title']) ?></span> <?php endforeach; ?><?= $copOn && $ready ? '' : '<span class="mut">Off</span>' ?></div><div class="row g8"><a class="sl-btn ghost sm" href="<?= sl_route('CsInbox', ['view' => 'mine']) ?>">Review suggestions</a></div></div>
<div class="aicard"><div class="row between"><span class="sl-tile"><i class="ic i-file" aria-hidden="true"></i></span><?= $tg('copilot', $copOn, 'Ticket summary') ?></div><h3>Ticket summary</h3><p>Long threads become a few lines for the next teammate, saved on the ticket.</p><div class="demo"><?php $sum = sl_scalar("SELECT summary FROM cdb_cs_tickets WHERE summary IS NOT NULL AND summary <> '' ORDER BY updated_at DESC", [], ''); ?><?= $sum ? h(mb_substr($sum, 0, 140)) : 'No summaries written yet.' ?></div><div class="row g8"><a class="sl-btn ghost sm" href="<?= sl_route('CsInbox') ?>">Open a ticket</a></div></div>
<div class="aicard"><div class="row between"><span class="sl-tile"><i class="ic i-wand" aria-hidden="true"></i></span><?= $tg('copilot', $copOn, 'Text enhancements') ?></div><h3>Text enhancements</h3><p>Rephrase, shorten, soften, fix spelling or translate before you hit send. Preview only.</p><div class="demo"><span class="aipop" style="width:100%"><?php foreach (array_slice(cdp_csRefineModes(), 0, 2) as $l): ?><a><i class="ic ic-sm i-wand" aria-hidden="true"></i><?= h($l) ?></a><?php endforeach; ?></span></div><div class="row g8"><a class="sl-btn ghost sm" href="<?= sl_route('CsInbox') ?>">Open in composer</a></div></div>
<div class="aicard"><div class="row between"><span class="sl-tile"><i class="ic i-lang" aria-hidden="true"></i></span><?= $tg('copilot', $copOn, 'Language detection') ?></div><h3>Language detection</h3><p>Spots the customer's language; a language set by hand is never overwritten.</p><div class="demo">Answers in: <?= h(implode(', ', array_map(fn($x) => cdp_csLanguages()[trim($x)] ?? $x, explode(',', $s['languages'])))) ?></div><div class="row g8"><?php if ($admin): ?><button class="sl-btn ghost sm" type="button" data-sl-open="ai-limits">Choose languages</button><?php endif; ?></div></div>
<div class="aicard"><div class="row between"><span class="sl-tile"><i class="ic i-hash" aria-hidden="true"></i></span><?= $tg('copilot', $copOn, 'Tag suggestions') ?></div><h3>Tag suggestions</h3><p>Proposes tags and a category so routing rules fire correctly.</p><div class="demo"><?php foreach (sl_rows('SELECT tag FROM cdb_cs_ticket_tags GROUP BY tag ORDER BY COUNT(*) DESC LIMIT 3') as $tgx): ?><span class="tg">#<?= h($tgx->tag) ?></span> <?php endforeach; ?></div><div class="row g8"><a class="sl-btn ghost sm" href="<?= sl_route('CsRules') ?>">Manage rules</a></div></div>
</div>
<section class="sl-card flush"><div class="row between" style="padding:14px 16px"><h2 class="sl-ct">Recent AI runs</h2><span class="mut sm"><?php foreach ($d['runStats'] as $r): ?><?= h($r['outcome']) ?> <?= (int) $r['n'] ?> · <?php endforeach; ?>last 7 days</span></div><div class="sl-tw"><table class="sl-table"><thead><tr><th>When</th><th>Kind</th><th>Ticket</th><th>Outcome</th><th>Model</th><th class="num">Tokens</th><th class="num">Time</th></tr></thead><tbody><?php foreach ($d['runs'] as $r): ?><tr><td class="mut"><?= h($r['at']) ?></td><td><?= h($r['kind']) ?></td><td><?php if ($r['ticket_id']): ?><a class="mono" href="<?= h(sl_route('CsTicket', ['id' => $r['ticket_id']])) ?>"><?= h($r['ref']) ?></a><?php else: ?>—<?php endif; ?></td><td><span class="sl-pill <?= $r['ok'] ? 'delivered' : 'overdue' ?>"><?= h($r['outcome']) ?></span><?php if ($r['detail']): ?><div class="mut" style="font-size:13.5px"><?= h(mb_substr($r['detail'], 0, 80)) ?></div><?php endif; ?></td><td class="mono sm"><?= h($r['model'] ?: '—') ?></td><td class="num"><?= sl_num($r['tokens']) ?></td><td class="num"><?= $r['ms'] ? sl_num($r['ms']) . ' ms' : '—' ?></td></tr><?php endforeach; ?><?php if (!$d['runs']): ?><tr><td colspan="7"><div class="sl-empty"><span class="sl-tile"><i class="ic i-ai" aria-hidden="true"></i></span>No AI runs yet<?= $ready ? '' : ': the provider is not configured' ?>.</div></td></tr><?php endif; ?></tbody></table></div></section>
<?php if ($admin): ?>
<div class="sl-dialog" id="ai-limits" hidden><form class="sl-modal" data-cs-form="settings" data-cs-url="ajax/support/admin_ajax.php" data-cs-reload role="dialog" aria-label="AI limits" style="width:min(680px,100%)"><b class="fw5"><?= h($d['king']) ?> settings</b>
<div class="grid cols2" style="gap:10px"><label class="sl-field"><span class="sl-lab">Name</span><input class="sl-in" name="king_name" value="<?= h($s['king_name']) ?>" maxlength="40"></label><label class="sl-field"><span class="sl-lab">Mode</span><label class="sl-sel native"><select name="king_mode"><option value="off" <?= $s['king_mode'] === 'off' ? 'selected' : '' ?>>Off</option><option value="copilot" <?= $s['king_mode'] === 'copilot' ? 'selected' : '' ?>>Helps agents only</option><option value="customer" <?= $s['king_mode'] === 'customer' ? 'selected' : '' ?> <?= $ready ? '' : 'disabled' ?>>Answers customers<?= $ready ? '' : ' (needs a provider key)' ?></option></select><i class="ic ic-sm i-down" aria-hidden="true"></i></label></label></div>
<label class="sl-field"><span class="sl-lab">Greeting</span><textarea class="sl-in" name="king_greeting" rows="2"><?= h($s['king_greeting']) ?></textarea></label>
<label class="sl-field"><span class="sl-lab">House rules</span><textarea class="sl-in" name="king_instructions" rows="2"><?= h($s['king_instructions']) ?></textarea></label>
<div class="grid cols3" style="gap:10px"><label class="sl-field"><span class="sl-lab">Turns before handover</span><input class="sl-in" type="number" min="1" name="king_max_turns" value="<?= h($s['king_max_turns']) ?>"></label><label class="sl-field"><span class="sl-lab">Lookups per answer</span><input class="sl-in" type="number" min="1" name="king_max_rounds" value="<?= h($s['king_max_rounds']) ?>"></label><label class="sl-field"><span class="sl-lab">Answers per day (0 = no cap)</span><input class="sl-in" type="number" min="0" name="king_daily_budget" value="<?= h($s['king_daily_budget']) ?>"></label></div>
<label class="sl-field"><span class="sl-lab">Languages (codes, comma separated)</span><input class="sl-in" name="languages" value="<?= h($s['languages']) ?>"><span class="sl-hint"><?= h(implode(', ', array_map(fn($k, $v) => "$k = $v", array_keys(cdp_csLanguages()), cdp_csLanguages()))) ?></span></label>
<label class="sl-field"><span class="sl-lab">Shown while waiting for a person</span><textarea class="sl-in" name="offline_note" rows="2"><?= h($s['offline_note']) ?></textarea></label>
<div class="row g8"><button type="submit" class="sl-btn">Save</button><button type="button" class="sl-btn ghost" data-sl-close>Cancel</button></div></form></div>
<script>
(function () {
  function save(data, sw) {
    CS.post(CS.admin, 'settings', data).then(function (j) {
      SL.toast(j.success ? 'Saved' : j.message, !j.success);
      if (j.success) setTimeout(function () { location.reload(); }, 300); else if (sw) sw.classList.toggle('on');
    });
  }
  document.addEventListener('click', function (e) {
    var sw = e.target.closest('[data-ai-key]');
    if (sw) {
      var on = sw.classList.contains('on'), key = sw.getAttribute('data-ai-key');
      if (key === 'agent') save({ king_mode: on ? 'customer' : 'copilot' }, sw);
      else save(on ? { copilot_enabled: 1, king_mode: '<?= $s['king_mode'] === 'off' ? 'copilot' : h($s['king_mode']) ?>' } : { copilot_enabled: 0 }, sw);
    }
    if (e.target.closest('[data-ai-all]')) save({ king_mode: 'customer', copilot_enabled: 1 });
  });
})();
</script>
<?php endif; ?>
