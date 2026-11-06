<?php
/**
 * DevIssues - generated from docs/handover/artifact-source/DevIssues.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  kpis                         as $k
 *   loop  tabs                         as $t
 *   loop  nTypes                       as $x
 *   loop  nPri                         as $x
 *   loop  nAreas                       as $x
 *   loop  nWho                         as $x
 *   loop  views                        as $v
 *   loop  pris                         as $p
 *   loop  cols                         as $c
 *   loop  c.items                      as $i
 *   loop  list                         as $i
 *   loop  moves                        as $m
 *   loop  d.cms                        as $c
 *   loop  ctrls                        as $c
 *   loop  bTypes                       as $b
 *   loop  fixes                        as $f
 *   loop  pages                        as $p
 *   loop  p.sp                         as $h
 *   loop  kinds                        as $k
 *   loop  vers                         as $v
 *   loop  v.items                      as $e
 *   if    flash
 *   if    tIssues
 *   if    newOpen
 *   if    vBoard
 *   if    vList
 *   if    tSite
 *   if    tLog
 *   value addCm, bCls, bNote, bTypes, banner, cm, cols, create, ctrls, false, fixes, flash, kinds, kpis, list, lq, moves, nAreas, nPri, nTitle, nTypes, nWho, newOpen, notesLabel, onLq, onQ, openNew, pages, pris, q, setBanner, setCm, setNTitle, tIssues, tLog, tSite, tabs, toggleNotes, true, vBoard, vList, vers, views
 * sl-wrap extras in the design: style="max-width:1500px"
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="sl-head"><div><h1 class="sl-title row g8" style="font-size:22px"><i class="ic i-bug" aria-hidden="true"></i>Issues and site</h1><p class="sl-lede">Every bug, error and request in one place: ticket it, assign it, fix it and ship it in the change log.</p></div><div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('SystemLogs') ?>"><i class="ic ic-sm i-activity" aria-hidden="true"></i>System logs</a><button class="sl-btn" data-sl-on="click:openNew"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New ticket</button></div></div>
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(min(170px,100%),1fr));gap:12px"><?php foreach ((array) sl_get($d, 'kpis', []) as $__i => $k): ?><div class="iq-k"><small><?= sl_v($k, 'l') ?></small><b><?= sl_v($k, 'v') ?></b><small><?= sl_v($k, 's') ?></small></div><?php endforeach; ?></div>
<div class="iq-tabs" role="tablist"><?php foreach ((array) sl_get($d, 'tabs', []) as $__i => $t): ?><button class="<?= sl_v($t, 'cls') ?>" data-sl-on="click:pick"><i class="ic ic-sm <?= sl_v($t, 'icon') ?>" aria-hidden="true"></i><?= sl_v($t, 'label') ?><span class="sl-badge"><?= sl_v($t, 'n') ?></span></button><?php endforeach; ?></div>
<?php if (sl_get($d, 'flash')): ?><div class="iq-ok" role="status"><i class="ic ic-sm i-check" aria-hidden="true"></i><?= sl_v($d, 'flash') ?></div><?php endif; ?>

<?php if (sl_get($d, 'tIssues')): ?>
<?php if (sl_get($d, 'newOpen')): ?><section class="iq-form"><div class="sl-ch" style="margin:0"><h2 class="sl-ct">New ticket</h2><button class="sl-ib" aria-label="Close" data-sl-on="click:openNew"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div>
<div class="grid cols2" style="gap:12px"><label class="sl-field"><span class="sl-lab">What's wrong?</span><input class="sl-in" value="<?= sl_v($d, 'nTitle') ?>" data-sl-on="input:setNTitle" placeholder="Short title, e.g. Label prints blank on Zebra"></label><div class="sl-field"><span class="sl-lab">Type</span><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'nTypes', []) as $__i => $x): ?><button class="<?= sl_v($x, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($x, 'label') ?></button><?php endforeach; ?></div></div></div>
<div class="grid cols3" style="gap:12px"><div class="sl-field"><span class="sl-lab">Priority</span><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'nPri', []) as $__i => $x): ?><button class="<?= sl_v($x, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($x, 'label') ?></button><?php endforeach; ?></div></div><div class="sl-field"><span class="sl-lab">Area</span><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'nAreas', []) as $__i => $x): ?><button class="<?= sl_v($x, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($x, 'label') ?></button><?php endforeach; ?></div></div><div class="sl-field"><span class="sl-lab">Assign to</span><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'nWho', []) as $__i => $x): ?><button class="<?= sl_v($x, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($x, 'label') ?></button><?php endforeach; ?></div></div></div>
<label class="sl-field"><span class="sl-lab">Steps to see it</span><textarea class="iq-ta">1. Open Air Shipments
2. Click ⋯ on SL-100482 → Print label
3. The label preview is blank</textarea></label>
<div class="row between wrap g8"><span class="row g8 sm"><span class="sl-btn ghost sm"><i class="ic ic-sm i-upload" aria-hidden="true"></i>Attach screenshot</span><span class="mut">Browser, page and user are added automatically</span></span><button class="sl-btn" data-sl-on="click:create"><i class="ic ic-sm i-check" aria-hidden="true"></i>Create ticket</button></div></section><?php endif; ?>
<div class="row between wrap g8"><div class="row g8 wrap"><div class="sl-search" style="min-width:240px"><i class="ic ic-sm i-search" aria-hidden="true"></i><input aria-label="Search tickets" placeholder="Search tickets, pages, errors" value="<?= sl_v($d, 'q') ?>" data-sl-on="input:onQ"></div><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'views', []) as $__i => $v): ?><button class="<?= sl_v($v, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($v, 'label') ?></button><?php endforeach; ?></div><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'pris', []) as $__i => $p): ?><button class="<?= sl_v($p, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($p, 'label') ?></button><?php endforeach; ?></div></div><span class="mut sm">Drag cards between columns, or use the buttons on a ticket</span></div>
<div class="iq-split"><div class="col g12">
<?php if (sl_get($d, 'vBoard')): ?><div class="iq-board"><?php foreach ((array) sl_get($d, 'cols', []) as $__i => $c): ?><div class="iq-col <?= sl_v($c, 'over') ?>" data-sl-on="dragover:dragOver" data-sl-on="drop:drop"><div class="iq-ch"><span><?= sl_v($c, 'label') ?></span><span class="sl-badge"><?= sl_v($c, 'n') ?></span></div><?php foreach ((array) sl_get($c, 'items', []) as $__i => $i): ?><button class="iq-c <?= sl_v($i, 'cls') ?>" draggable="true" data-sl-on="dragstart:drag" data-sl-on="click:pick"><span class="iq-meta"><span class="mono"><?= sl_v($i, 'id') ?></span><span class="iq-sev <?= sl_v($i, 'pc') ?>"><?= sl_v($i, 'pri') ?></span><span><?= sl_v($i, 'type') ?></span></span><b><?= sl_v($i, 't') ?></b><span class="iq-meta"><i class="ic ic-sm i-pin" aria-hidden="true"></i><?= sl_v($i, 'area') ?> · <?= sl_v($i, 'age') ?><span class="me-pic <?= sl_v($i, 'pic') ?> iq-av"></span></span></button><?php endforeach; ?></div><?php endforeach; ?></div><?php endif; ?>
<?php if (sl_get($d, 'vList')): ?><section class="sl-card flush"><div class="sl-tw"><table class="sl-table"><thead><tr><th>Ticket</th><th>Title</th><th>Priority</th><th>Area</th><th>Status</th><th>Assignee</th><th>Opened</th></tr></thead><tbody><?php foreach ((array) sl_get($d, 'list', []) as $__i => $i): ?><tr style="cursor:pointer" data-sl-on="click:pick"><td class="mono sm"><?= sl_v($i, 'id') ?></td><td class="fw5"><?= sl_v($i, 't') ?></td><td><span class="iq-sev <?= sl_v($i, 'pc') ?>"><?= sl_v($i, 'pri') ?></span></td><td class="sm"><?= sl_v($i, 'area') ?></td><td><span class="sl-badge"><?= sl_v($i, 'st') ?></span></td><td><span class="row g6"><span class="me-pic <?= sl_v($i, 'pic') ?>" style="width:22px;height:22px"></span><?= sl_v($i, 'who') ?></span></td><td class="sm mut"><?= sl_v($i, 'age') ?></td></tr><?php endforeach; ?></tbody></table></div></section><?php endif; ?>
</div>
<aside class="iq-d"><section class="sl-card col g12"><div class="row g8"><span class="mono mut sm"><?= sl_v($d, 'd.id') ?></span><span class="iq-sev <?= sl_v($d, 'd.pc') ?>"><?= sl_v($d, 'd.pri') ?></span><span class="sl-badge"><?= sl_v($d, 'd.type') ?></span><span class="grow"></span><span class="sl-badge"><?= sl_v($d, 'd.st') ?></span></div><b class="fw5" style="font-size:17px"><?= sl_v($d, 'd.t') ?></b>
<div class="sl-kv"><div><span>Area</span><span><?= sl_v($d, 'd.area') ?></span></div><div><span>Reported by</span><span><?= sl_v($d, 'd.by') ?></span></div><div><span>Assigned to</span><span class="row g6"><span class="me-pic <?= sl_v($d, 'd.pic') ?>" style="width:20px;height:20px"></span><?= sl_v($d, 'd.who') ?></span></div><div><span>Opened</span><span><?= sl_v($d, 'd.age') ?></span></div><div><span>Affected</span><span><?= sl_v($d, 'd.hits') ?></span></div><div><span>Fix in</span><span class="mono"><?= sl_v($d, 'd.rel') ?></span></div></div>
<span class="up">From the logs</span><pre class="iq-pre"><?= sl_v($d, 'd.log') ?></pre>
<div class="row g6 wrap"><?php foreach ((array) sl_get($d, 'moves', []) as $__i => $m): ?><button class="sl-btn sm <?= sl_v($m, 'cls') ?>" data-sl-on="click:go"><?= sl_v($m, 'label') ?></button><?php endforeach; ?></div>
<span class="up">Activity</span><?php foreach ((array) sl_get($d, 'd.cms', []) as $__i => $c): ?><div class="iq-cm"><span class="me-pic <?= sl_v($c, 'pic') ?>" style="width:26px;height:26px;flex:none"></span><span class="col"><span><b class="fw5"><?= sl_v($c, 'who') ?></b> <span class="mut" style="font-size:12px"><?= sl_v($c, 'at') ?></span></span><span><?= sl_v($c, 't') ?></span></span></div><?php endforeach; ?>
<div class="row g8"><input class="sl-in grow" placeholder="Add a comment or @mention" value="<?= sl_v($d, 'cm') ?>" data-sl-on="input:setCm" aria-label="Comment"><button class="sl-btn" data-sl-on="click:addCm">Post</button></div></section></aside></div>
<?php endif; ?>

<?php if (sl_get($d, 'tSite')): ?>
<div class="grid cols3" style="gap:16px;align-items:start"><section class="sl-card col g12"><h2 class="sl-ct">Site controls</h2><?php foreach ((array) sl_get($d, 'ctrls', []) as $__i => $c): ?><div class="row g10" style="padding:6px 0;border-top:1px solid var(--line)"><span class="col grow"><span class="sm fw5"><?= sl_v($c, 't') ?></span><span class="mut" style="font-size:12px"><?= sl_v($c, 'd') ?></span></span><button class="sl-toggle <?= sl_v($c, 'cls') ?>" role="switch" aria-checked="<?= sl_v($c, 'aria') ?>" aria-label="<?= sl_v($c, 't') ?>" data-sl-on="click:flip"></button></div><?php endforeach; ?></section>
<section class="sl-card col g12"><h2 class="sl-ct">Banner on every page</h2><label class="sl-field"><span class="sl-lab">Message</span><input class="sl-in" value="<?= sl_v($d, 'banner') ?>" data-sl-on="input:setBanner"></label><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'bTypes', []) as $__i => $b): ?><button class="<?= sl_v($b, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($b, 'label') ?></button><?php endforeach; ?></div><div class="sl-banner <?= sl_v($d, 'bCls') ?>"><i class="ic i-info" aria-hidden="true"></i><span><?= sl_v($d, 'banner') ?></span></div><span class="mut sm"><?= sl_v($d, 'bNote') ?></span></section>
<section class="sl-card col g10"><h2 class="sl-ct">Quick fixes</h2><?php foreach ((array) sl_get($d, 'fixes', []) as $__i => $f): ?><button class="sl-btn ghost" style="justify-content:flex-start" data-sl-on="click:go"><i class="ic ic-sm <?= sl_v($f, 'icon') ?>" aria-hidden="true"></i><span class="grow" style="text-align:left"><?= sl_v($f, 't') ?></span><span class="mut sm"><?= sl_v($f, 's') ?></span></button><?php endforeach; ?></section></div>
<section class="col g10"><div class="row between"><h2 class="sl-ct">Pages and services · health</h2><span class="mut sm">Checked every minute from Accra and Newark</span></div><div class="iq-site"><?php foreach ((array) sl_get($d, 'pages', []) as $__i => $p): ?><div class="iq-pg"><span class="row g8"><span class="iq-dot <?= sl_v($p, 'dot') ?>"></span><b class="fw5 sm grow"><?= sl_v($p, 'n') ?></b><span class="mono mut" style="font-size:11.5px"><?= sl_v($p, 'ms') ?></span></span><span class="mono mut" style="font-size:11.5px"><?= sl_v($p, 'url') ?></span><div class="iq-sp" aria-hidden="true"><?php foreach ((array) sl_get($p, 'sp', []) as $__i => $h): ?><i class="<?= sl_v($h, 'c') ?>" style="<?= sl_v($h, 's') ?>"></i><?php endforeach; ?></div><span class="row between" style="font-size:12px"><span><?= sl_v($p, 'up') ?> uptime</span><span class="mut"><?= sl_v($p, 'err') ?></span></span></div><?php endforeach; ?></div></section>
<?php endif; ?>

<?php if (sl_get($d, 'tLog')): ?>
<div class="row between wrap g8"><div class="row g8 wrap"><div class="sl-search" style="min-width:240px"><i class="ic ic-sm i-search" aria-hidden="true"></i><input aria-label="Search change log" placeholder="Search changes" value="<?= sl_v($d, 'lq') ?>" data-sl-on="input:onLq"></div><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'kinds', []) as $__i => $k): ?><button class="<?= sl_v($k, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($k, 'label') ?></button><?php endforeach; ?></div></div><div class="row g8"><button class="sl-btn ghost" data-sl-on="click:toggleNotes"><i class="ic ic-sm i-send" aria-hidden="true"></i><?= sl_v($d, 'notesLabel') ?></button><a class="sl-btn ghost" href="<?= sl_route('DevDeployments') ?>"><i class="ic ic-sm i-rocket" aria-hidden="true"></i>Deployments</a></div></div>
<section class="sl-card col"><?php foreach ((array) sl_get($d, 'vers', []) as $__i => $v): ?><div class="iq-ver"><div class="iq-vh"><b><?= sl_v($v, 'v') ?></b><span class="mut sm"><?= sl_v($v, 'd') ?></span><span class="sl-badge"><?= sl_v($v, 'tag') ?></span><span class="mut" style="font-size:12px"><?= sl_v($v, 'by') ?></span></div><div class="col"><b class="fw5" style="margin-bottom:4px"><?= sl_v($v, 'title') ?></b><?php foreach ((array) sl_get($v, 'items', []) as $__i => $e): ?><div class="iq-ent"><span class="iq-tag <?= sl_v($e, 'k') ?>"><?= sl_v($e, 'kl') ?></span><span><?= sl_v($e, 't') ?></span><span class="mono mut" style="font-size:11.5px"><?= sl_v($e, 'ref') ?></span></div><?php endforeach; ?></div></div><?php endforeach; ?></section>
<?php endif; ?>
