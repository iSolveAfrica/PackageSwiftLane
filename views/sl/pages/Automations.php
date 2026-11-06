<?php
/**
 * Automations - from docs/handover/artifact-source/Automations.dc.html.
 * Data: views/sl/data/automations.php (sl_automationsData). Builder state is kept in JS and posted as JSON.
 */
if (!isset($d)) { $d = []; }
$ed = $d['edit'];
$init = $ed ? ['id' => (int) $ed['id'], 'name' => $ed['name'], 'trigger' => $ed['trigger_key'], 'conditions' => $ed['conditions'], 'actions' => $ed['actions'], 'enabled' => (int) $ed['enabled']]
    : ['id' => 0, 'name' => 'New automation', 'trigger' => 'shipment.status', 'conditions' => [], 'actions' => [], 'enabled' => 1];
$ajax = 'ajax/sl/automations_ajax.php';
?>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:20px">Automations</h1><p class="sl-lede">Pick what happens, add a condition if you need one, choose what Swift Lane does. No code.</p></div><div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('CsPlaybook') ?>"><i class="ic ic-sm i-book" aria-hidden="true"></i>Playbook</a><?php if ($ed): ?><button class="sl-btn ghost" data-sl-action="<?= $ajax ?>" data-p-op="test" data-p-id="<?= (int) $ed['id'] ?>"><i class="ic ic-sm i-play" aria-hidden="true"></i>Test run</button><?php endif; ?><button class="sl-btn" type="button" data-au-save><i class="ic ic-sm i-check" aria-hidden="true"></i><?= $ed ? 'Save changes' : 'Create automation' ?></button></div></div>
<div class="cs-tabs"><a href="<?= sl_route('CsInbox') ?>">Capture</a><a href="<?= sl_route('CsTicket') ?>">Solve</a><a href="<?= sl_route('CsRules') ?>">Route</a><a class="on" href="<?= sl_route('Automations') ?>">Automate</a><a href="<?= sl_route('CsReports') ?>">Measure</a><a href="<?= sl_route('CsAI') ?>">AI Assistant</a><a href="<?= sl_route('CsChannels') ?>">Channels</a></div>
<?php if (!$d['ready']): ?><div class="sl-banner warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i>Automations need the database upgrade <b>2026_11_06_messaging_settings</b> (tables cdb_automations and cdb_automation_runs).</div><?php endif; ?>
<section class="sl-card flush au-man">
<form method="get" class="row between wrap g12" style="padding:16px 18px 12px"><div class="col"><h2 class="sl-ct">Created automations</h2><span class="mut sm"><?= h($d['totalRuns']) ?> runs in total · edit, pause, duplicate or delete any of them</span></div><div class="row g8 wrap"><div class="sl-search" style="min-width:200px"><i class="ic ic-sm i-search" aria-hidden="true"></i><input name="q" aria-label="Search automations" placeholder="Search automations" value="<?= h($d['q']) ?>"></div><div class="sl-seg boxed"><?php foreach ($d['lfs'] as $x): ?><a class="<?= $x['f'] === $d['lf'] ? 'on' : '' ?>" href="<?= h(sl_url(['lf' => $x['f']])) ?>"><?= h($x['label']) ?></a><?php endforeach; ?></div><a class="sl-btn" href="<?= sl_route('Automations') ?>"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New automation</a></div></form>
<div class="sl-tw"><table class="sl-table"><thead><tr><th>Automation</th><th>When</th><th>Then</th><th>Created</th><th>Last run</th><th class="num">Runs</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($d['rows'] as $r): ?><tr><td><div class="sl-cell"><span class="sl-tile" style="width:32px;height:32px"><i class="ic ic-sm <?= h($r['icon']) ?>" aria-hidden="true"></i></span><span class="col"><a class="au-nm" href="<?= h(sl_route('Automations', ['edit' => $r['id']])) ?>"><?= h($r['name']) ?></a><span class="mut" style="font-size:12px"><?= h($r['cond']) ?></span></span></div></td><td class="sm"><?= h($r['when']) ?></td><td class="sm" style="max-width:220px"><?= h($r['then']) ?></td><td class="sm"><span class="col"><span><?= h($r['by']) ?></span><span class="mut" style="font-size:12px"><?= h($r['created']) ?></span></span></td><td class="sm mut"><?= h($r['last']) ?></td><td class="num mono sm"><?= h($r['runs']) ?></td><td><span class="row g8"><span class="sl-toggle <?= $r['on'] ? 'on' : '' ?>" role="switch" aria-checked="<?= $r['on'] ? 'true' : 'false' ?>" aria-label="Turn on or off" data-sl-save="<?= $ajax ?>" data-p-op="toggle" data-p-id="<?= (int) $r['id'] ?>"></span><span class="sm"><?= $r['on'] ? 'On' : 'Paused' ?></span></span></td><td><span class="row g4 au-acts"><a class="sl-btn ghost sm" href="<?= h(sl_route('Automations', ['edit' => $r['id']])) ?>"><i class="ic ic-sm i-edit" aria-hidden="true"></i>Edit</a><button class="sl-ib" aria-label="Duplicate" title="Duplicate" data-sl-action="<?= $ajax ?>" data-p-op="duplicate" data-p-id="<?= (int) $r['id'] ?>" data-sl-reload><i class="ic ic-sm i-copy" aria-hidden="true"></i></button><button class="sl-ib" aria-label="Delete" title="Delete" data-sl-action="<?= $ajax ?>" data-p-op="delete" data-p-id="<?= (int) $r['id'] ?>" data-sl-confirm="Delete this automation?"><i class="ic ic-sm i-trash" aria-hidden="true"></i></button></span></td></tr><?php endforeach; ?>
<?php if (!$d['rows']): ?><tr><td colspan="8"><div class="sl-empty"><span class="sl-tile"><i class="ic i-bolt" aria-hidden="true"></i></span>No automations yet. Build one below or start from a template.</div></td></tr><?php endif; ?>
</tbody></table></div></section>
<?php if ($ed): ?><div class="sl-banner info"><i class="ic i-edit" aria-hidden="true"></i><span>Editing <b class="fw5"><?= h($ed['name']) ?></b> below. Save changes to update it, or start a <a class="au-nm" style="text-decoration:underline" href="<?= sl_route('Automations') ?>">new automation</a>.</span></div><?php endif; ?>
<div class="au">
<div class="au-main">
<section class="sl-card col g12" style="padding:28px 30px">
<div class="row between wrap g8"><label class="row g8" style="flex:1 1 300px"><span class="up">Name</span><input class="sl-in" id="au-name" name="name" aria-label="Automation name" value="" style="flex:1"></label><span class="ov-pill <?= $init['enabled'] ? 'ok' : '' ?>"><?= $ed ? ($init['enabled'] ? 'On' : 'Paused') : 'Draft' ?></span></div>
<p class="au-sent" id="au-sent"></p>
</section>
<section class="sl-card au-step"><div class="au-sh"><span class="au-n">1</span><h2 class="sl-ct">When this happens</h2></div>
<div class="au-grid"><?php foreach ($d['triggers'] as $k => $t): ?><button type="button" class="au-opt" data-au-trigger="<?= h($k) ?>" title="<?= h($t['hook']) ?>"><i class="ic ic-sm <?= h($t['icon']) ?>" aria-hidden="true"></i><?= h($t['label']) ?></button><?php endforeach; ?></div>
<span class="mut sm">Shipment status changes are live today. The other events start firing once their screen calls the automation hook (see helpers/automations.php).</span></section>
<section class="sl-card au-step"><div class="au-sh"><span class="au-n">2</span><h2 class="sl-ct">Only if</h2><span class="mut sm">Optional</span></div>
<div class="row g8 wrap" id="au-conds"></div>
<div class="row g8 wrap"><span class="mut sm">Add:</span><?php foreach ($d['conds'] as $k => $c): ?><button type="button" class="au-add" data-au-cond="<?= h($k) ?>">+ <?= h($c['label']) ?></button><?php endforeach; ?></div>
<div class="row g8 wrap" id="au-cond-ed" style="display:none"><label class="sl-sel native" id="au-st-wrap" style="width:220px"><select id="au-st" aria-label="Status"><?php foreach ($d['statuses'] as $sid => $sn): ?><option value="<?= (int) $sid ?>"><?= h($sn) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></label><input class="sl-in" id="au-cv" style="width:160px" aria-label="Value"><button type="button" class="sl-btn sm" id="au-cond-ok">Add condition</button></div></section>
<section class="sl-card au-step"><div class="au-sh"><span class="au-n">3</span><h2 class="sl-ct">Then do this</h2><span class="mut sm" id="au-actn"></span></div>
<div class="au-grid"><?php foreach ($d['actions'] as $k => $a): ?><button type="button" class="au-opt" data-au-act="<?= h($k) ?>"><i class="ic ic-sm <?= h($a['icon']) ?>" aria-hidden="true"></i><span class="grow"><?= h($a['label']) ?></span><span class="sl-chk" aria-hidden="true"></span></button><?php endforeach; ?></div>
<div class="col g10" id="au-act-ed"></div>
<div class="row wrap g6"><span class="mut sm">Variables:</span><?php foreach ($d['vars'] as $v): ?><span class="sl-badge mono"><?= h($v) ?></span><?php endforeach; ?></div></section>
</div>
<aside class="au-side">
<section class="sl-card col"><div class="sl-ch"><h2 class="sl-ct">Recent runs</h2><span class="mut sm"><?= (int) $d['onCount'] ?> on</span></div>
<?php foreach ($d['runs'] as $l): ?><div class="au-row"><span class="sl-tile"><i class="ic ic-sm <?= $l['outcome'] === 'failed' ? 'i-alert' : 'i-check' ?>" aria-hidden="true"></i></span><div class="grow col" style="min-width:0"><span class="fw5 sm"><?= h($l['name']) ?> <span class="mut">· <?= h($l['outcome']) ?></span></span><span class="mut" style="font-size:12px"><?= h($l['label']) ?> · <?= h($l['at']) ?></span><span class="mut" style="font-size:11.5px"><?= h(mb_substr((string) $l['detail'], 0, 90)) ?></span></div></div><?php endforeach; ?>
<?php if (!$d['runs']): ?><span class="mut sm">No runs yet.</span><?php endif; ?></section>
<section class="sl-card col g8"><h2 class="sl-ct">Start from a template</h2>
<?php foreach ($d['templates'] as $i => $t): ?><button type="button" class="au-opt" data-au-tpl="<?= (int) $i ?>"><i class="ic ic-sm <?= h($t['icon']) ?>" aria-hidden="true"></i><span class="grow"><?= h($t['label']) ?></span><i class="ic ic-sm i-right mut" aria-hidden="true"></i></button><?php endforeach; ?></section>
</aside>
</div>
<script>
(function(){
  var S = <?= json_encode($init, JSON_HEX_TAG) ?>, T = <?= json_encode($d['triggers'], JSON_HEX_TAG) ?>, A = <?= json_encode($d['actions'], JSON_HEX_TAG) ?>, C = <?= json_encode($d['conds'], JSON_HEX_TAG) ?>, ST = <?= json_encode($d['statuses'], JSON_HEX_TAG) ?>, TPL = <?= json_encode($d['templates'], JSON_HEX_TAG) ?>;
  var pending = null, $ = function(s){ return document.querySelector(s); };
  function esc(s){ return String(s == null ? '' : s).replace(/[&<>"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
  function cl(c){ var v = c.value; if (c.key === 'status_is') v = String(v).split(',').map(function(i){ return ST[i] || i; }).join(' or '); return C[c.key].label + (C[c.key].input !== 'none' && v !== '' ? ' ' + v : ''); }
  function draw(){
    $('#au-name').value = S.name;
    document.querySelectorAll('[data-au-trigger]').forEach(function(b){ var on = b.getAttribute('data-au-trigger') === S.trigger; b.classList.toggle('on', on); b.setAttribute('aria-pressed', on); });
    document.querySelectorAll('[data-au-act]').forEach(function(b){ var on = S.actions.some(function(a){ return a.type === b.getAttribute('data-au-act'); }); b.classList.toggle('on', on); b.querySelector('.sl-chk').classList.toggle('on', on); });
    $('#au-conds').innerHTML = S.conditions.map(function(c, i){ return '<span class="au-chip">' + esc(cl(c)) + '<button type="button" data-au-rm="' + i + '" aria-label="Remove condition"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></span>'; }).join('');
    $('#au-actn').textContent = S.actions.length + ' chosen · runs in order';
    $('#au-act-ed').innerHTML = S.actions.map(function(a, i){ var f = A[a.type].fields; return '<div class="sl-card col g8" style="padding:12px"><b class="fw5 sm">' + (i + 1) + ' · ' + esc(A[a.type].label) + '</b>' + (f.indexOf('subject') >= 0 ? '<input class="sl-in" data-au-f="subject" data-i="' + i + '" placeholder="Subject" value="' + esc(a.subject) + '">' : '') + (f.indexOf('message') >= 0 ? '<textarea class="sl-in" rows="3" data-au-f="message" data-i="' + i + '" placeholder="Message">' + esc(a.message) + '</textarea>' : '') + '</div>'; }).join('');
    var sent = 'When <span class="au-tok t"><i class="ic ic-sm ' + esc(T[S.trigger].icon) + '" aria-hidden="true"></i>' + esc(T[S.trigger].label) + '</span>';
    if (S.conditions.length) sent += ' and ' + S.conditions.map(function(c){ return '<span class="au-tok c">' + esc(cl(c)) + '</span>'; }).join(' ');
    sent += ', then ' + (S.actions.length ? S.actions.map(function(a){ return '<span class="au-tok a"><i class="ic ic-sm ' + esc(A[a.type].icon) + '" aria-hidden="true"></i>' + esc(A[a.type].label) + '</span>'; }).join(', ') : '<span class="au-tok empty">choose an action</span>') + '.';
    $('#au-sent').innerHTML = sent;
  }
  document.addEventListener('input', function(e){ var f = e.target.getAttribute('data-au-f'); if (f) S.actions[+e.target.getAttribute('data-i')][f] = e.target.value; if (e.target.id === 'au-name') { S.name = e.target.value; } });
  document.addEventListener('click', function(e){
    var b;
    if ((b = e.target.closest('[data-au-trigger]'))) { S.trigger = b.getAttribute('data-au-trigger'); draw(); }
    else if ((b = e.target.closest('[data-au-act]'))) { var k = b.getAttribute('data-au-act'), ix = S.actions.findIndex(function(a){ return a.type === k; }); if (ix >= 0) S.actions.splice(ix, 1); else S.actions.push({ type: k, subject: '', message: '' }); draw(); }
    else if ((b = e.target.closest('[data-au-rm]'))) { S.conditions.splice(+b.getAttribute('data-au-rm'), 1); draw(); }
    else if ((b = e.target.closest('[data-au-cond]'))) { pending = b.getAttribute('data-au-cond'); var inp = C[pending].input; if (inp === 'none') { S.conditions.push({ key: pending, value: '' }); draw(); return; } $('#au-cond-ed').style.display = 'flex'; $('#au-st-wrap').style.display = inp === 'status' ? '' : 'none'; $('#au-cv').style.display = inp === 'status' ? 'none' : ''; $('#au-cv').placeholder = C[pending].hint || (inp === 'select' ? Object.keys(C[pending].options).join(' / ') : ''); }
    else if (e.target.closest('#au-cond-ok')) { var v = C[pending].input === 'status' ? $('#au-st').value : $('#au-cv').value.trim(); if (v !== '') S.conditions.push({ key: pending, value: v }); $('#au-cond-ed').style.display = 'none'; $('#au-cv').value = ''; draw(); }
    else if ((b = e.target.closest('[data-au-tpl]'))) { var t = TPL[+b.getAttribute('data-au-tpl')]; S = { id: 0, name: t.name, trigger: t.trigger, conditions: JSON.parse(JSON.stringify(t.conditions)), actions: t.actions.map(function(a){ return { type: a.type, subject: a.subject || '', message: a.message || '' }; }), enabled: 1 }; draw(); $('#au-name').focus(); }
    else if ((b = e.target.closest('[data-au-save]'))) { b.classList.add('loading'); SL.post('<?= $ajax ?>', { op: 'save', id: S.id, name: S.name, trigger_key: S.trigger, conditions: JSON.stringify(S.conditions), actions: JSON.stringify(S.actions) }).then(function(j){ SL.toast(j.message || '', !j.success); if (j.success && j.redirect) setTimeout(function(){ location.href = j.redirect; }, 300); }).finally(function(){ b.classList.remove('loading'); }); }
  });
  draw();
})();
</script>
