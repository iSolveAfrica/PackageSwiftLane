<?php
/**
 * Automations - generated from docs/handover/artifact-source/Automations.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  lfs                          as $x
 *   loop  rows                         as $r
 *   loop  conds                        as $c
 *   loop  acts                         as $a
 *   loop  triggers                     as $t
 *   loop  conds                        as $c
 *   loop  condOpts                     as $c
 *   loop  actions                      as $a
 *   loop  list                         as $l
 *   loop  templates                    as $t
 *   if    editing
 *   if    hasConds
 *   if    noActs
 *   if    tested
 *   value actCount, actions, acts, condOpts, conds, editName, editing, false, hasConds, lfs, list, name, newAuto, noActs, onCount, onName, onQ, q, rows, save, saveLabel, stateCls, stateLabel, tCls, tIcon, tLabel, templates, test, testMsg, tested, totalRuns, triggers, true
 * sl-wrap extras in the design: style="max-width:1180px"
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:20px">Automations</h1><p class="sl-lede">Pick what happens, add a condition if you need one, choose what Swift Lane does. No code.</p></div><div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('CsPlaybook') ?>"><i class="ic ic-sm i-book" aria-hidden="true"></i>Playbook</a><button class="sl-btn ghost" data-sl-on="click:test"><i class="ic ic-sm i-play" aria-hidden="true"></i>Test run</button><button class="sl-btn" data-sl-on="click:save"><i class="ic ic-sm i-check" aria-hidden="true"></i><?= sl_v($d, 'saveLabel') ?></button></div></div>
<div class="cs-tabs"><a href="<?= sl_route('CsInbox') ?>">Capture</a><a href="<?= sl_route('CsTicket') ?>">Solve</a><a href="<?= sl_route('CsRules') ?>">Route</a><a class="on" href="<?= sl_route('Automations') ?>">Automate</a><a href="<?= sl_route('CsReports') ?>">Measure</a><a href="<?= sl_route('CsAI') ?>">AI Assistant</a><a href="<?= sl_route('CsChannels') ?>">Channels</a></div>
<section class="sl-card flush au-man">
<div class="row between wrap g12" style="padding:16px 18px 12px"><div class="col"><h2 class="sl-ct">Created automations</h2><span class="mut sm"><?= sl_v($d, 'totalRuns') ?> runs this month · edit, pause, duplicate or delete any of them</span></div><div class="row g8 wrap"><div class="sl-search" style="min-width:200px"><i class="ic ic-sm i-search" aria-hidden="true"></i><input aria-label="Search automations" placeholder="Search automations" value="<?= sl_v($d, 'q') ?>" data-sl-on="input:onQ"></div><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'lfs', []) as $__i => $x): ?><button class="<?= sl_v($x, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($x, 'label') ?></button><?php endforeach; ?></div><button class="sl-btn" data-sl-on="click:newAuto"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New automation</button></div></div>
<div class="sl-tw"><table class="sl-table"><thead><tr><th>Automation</th><th>When</th><th>Then</th><th>Created</th><th>Last run</th><th class="num">Runs</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ((array) sl_get($d, 'rows', []) as $__i => $r): ?><tr class="<?= sl_v($r, 'rowCls') ?>"><td><div class="sl-cell"><span class="sl-tile" style="width:32px;height:32px"><i class="ic ic-sm <?= sl_v($r, 'icon') ?>" aria-hidden="true"></i></span><span class="col"><button class="au-nm" data-sl-on="click:edit"><?= sl_v($r, 'name') ?></button><span class="mut" style="font-size:12px"><?= sl_v($r, 'cond') ?></span></span></div></td><td class="sm"><?= sl_v($r, 'when') ?></td><td class="sm" style="max-width:220px"><?= sl_v($r, 'then') ?></td><td class="sm"><span class="col"><span><?= sl_v($r, 'by') ?></span><span class="mut" style="font-size:12px"><?= sl_v($r, 'created') ?></span></span></td><td class="sm mut"><?= sl_v($r, 'last') ?></td><td class="num mono sm"><?= sl_v($r, 'runs') ?></td><td><span class="row g8"><button class="au-sw <?= sl_v($r, 'cls') ?>" role="switch" aria-checked="<?= sl_v($r, 'p') ?>" aria-label="Turn on or off" data-sl-on="click:toggle"></button><span class="sm"><?= sl_v($r, 'st') ?></span></span></td><td><span class="row g4 au-acts"><button class="sl-btn ghost sm" data-sl-on="click:edit"><i class="ic ic-sm i-edit" aria-hidden="true"></i>Edit</button><button class="sl-ib" aria-label="Duplicate" title="Duplicate" data-sl-on="click:dup"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button><a class="sl-ib" aria-label="Run history" title="Run history" href="<?= sl_route('ActivityLogs') ?>"><i class="ic ic-sm i-clock" aria-hidden="true"></i></a><button class="sl-ib" aria-label="Delete" title="Delete" data-sl-on="click:del"><i class="ic ic-sm i-trash" aria-hidden="true"></i></button></span></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php if (sl_get($d, 'editing')): ?><div class="sl-banner info"><i class="ic i-edit" aria-hidden="true"></i><span>Editing <b class="fw5"><?= sl_v($d, 'editName') ?></b> below. Save changes to update it, or start a <button class="au-nm" style="text-decoration:underline" data-sl-on="click:newAuto">new automation</button>.</span></div><?php endif; ?>
<div class="au">
<div class="au-main">
<section class="sl-card col g12" style="padding:28px 30px">
<div class="row between wrap g8"><label class="row g8" style="flex:1 1 300px"><span class="up">Name</span><input class="sl-in" aria-label="Automation name" value="<?= sl_v($d, 'name') ?>" data-sl-on="change:onName" style="flex:1"></label><span class="ov-pill <?= sl_v($d, 'stateCls') ?>"><?= sl_v($d, 'stateLabel') ?></span></div>
<p class="au-sent">When <span class="au-tok <?= sl_v($d, 'tCls') ?>"><i class="ic ic-sm <?= sl_v($d, 'tIcon') ?>" aria-hidden="true"></i><?= sl_v($d, 'tLabel') ?></span><?php if (sl_get($d, 'hasConds')): ?> and <?php foreach ((array) sl_get($d, 'conds', []) as $__i => $c): ?><span class="au-tok c"><?= sl_v($c, 'label') ?></span> <?php endforeach; ?><?php endif; ?>, then <?php foreach ((array) sl_get($d, 'acts', []) as $__i => $a): ?><span class="au-tok a"><i class="ic ic-sm <?= sl_v($a, 'icon') ?>" aria-hidden="true"></i><?= sl_v($a, 'label') ?></span><?= sl_v($a, 'sep') ?><?php endforeach; ?><?php if (sl_get($d, 'noActs')): ?><span class="au-tok empty">choose an action</span><?php endif; ?>.</p>
<?php if (sl_get($d, 'tested')): ?><div class="au-ok" role="status"><i class="ic ic-sm i-check" aria-hidden="true"></i><?= sl_v($d, 'testMsg') ?></div><?php endif; ?>
</section>
<section class="sl-card au-step"><div class="au-sh"><span class="au-n">1</span><h2 class="sl-ct">When this happens</h2></div>
<div class="au-grid"><?php foreach ((array) sl_get($d, 'triggers', []) as $__i => $t): ?><button class="au-opt <?= sl_v($t, 'cls') ?>" data-sl-on="click:pick" aria-pressed="<?= sl_v($t, 'p') ?>"><i class="ic ic-sm <?= sl_v($t, 'icon') ?>" aria-hidden="true"></i><?= sl_v($t, 'label') ?></button><?php endforeach; ?></div></section>
<section class="sl-card au-step"><div class="au-sh"><span class="au-n">2</span><h2 class="sl-ct">Only if</h2><span class="mut sm">Optional</span></div>
<div class="row g8 wrap"><?php foreach ((array) sl_get($d, 'conds', []) as $__i => $c): ?><span class="au-chip"><?= sl_v($c, 'label') ?><button data-sl-on="click:remove" aria-label="Remove condition"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></span><?php endforeach; ?></div>
<div class="row g8 wrap"><span class="mut sm">Add:</span><?php foreach ((array) sl_get($d, 'condOpts', []) as $__i => $c): ?><button class="au-add" data-sl-on="click:add">+ <?= sl_v($c, 'label') ?></button><?php endforeach; ?></div></section>
<section class="sl-card au-step"><div class="au-sh"><span class="au-n">3</span><h2 class="sl-ct">Then do this</h2><span class="mut sm"><?= sl_v($d, 'actCount') ?> chosen · runs in order</span></div>
<div class="au-grid"><?php foreach ((array) sl_get($d, 'actions', []) as $__i => $a): ?><button class="au-opt <?= sl_v($a, 'cls') ?>" data-sl-on="click:toggle" aria-pressed="<?= sl_v($a, 'p') ?>"><i class="ic ic-sm <?= sl_v($a, 'icon') ?>" aria-hidden="true"></i><span class="grow"><?= sl_v($a, 'label') ?></span><span class="sl-chk <?= sl_v($a, 'chk') ?>" aria-hidden="true"></span></button><?php endforeach; ?></div></section>
</div>
<aside class="au-side">
<section class="sl-card col"><div class="sl-ch"><h2 class="sl-ct">Your automations</h2><span class="mut sm"><?= sl_v($d, 'onCount') ?> on</span></div>
<?php foreach ((array) sl_get($d, 'list', []) as $__i => $l): ?><div class="au-row"><span class="sl-tile"><i class="ic ic-sm <?= sl_v($l, 'icon') ?>" aria-hidden="true"></i></span><div class="grow col" style="min-width:0"><span class="fw5 sm"><?= sl_v($l, 'name') ?></span><span class="mut" style="font-size:12px"><?= sl_v($l, 'runs') ?></span></div><button class="au-sw <?= sl_v($l, 'cls') ?>" role="switch" aria-checked="<?= sl_v($l, 'p') ?>" aria-label="<?= sl_v($l, 'name') ?>" data-sl-on="click:toggle"></button></div><?php endforeach; ?></section>
<section class="sl-card col g8"><h2 class="sl-ct">Start from a template</h2>
<?php foreach ((array) sl_get($d, 'templates', []) as $__i => $t): ?><button class="au-opt" data-sl-on="click:use"><i class="ic ic-sm <?= sl_v($t, 'icon') ?>" aria-hidden="true"></i><span class="grow"><?= sl_v($t, 'label') ?></span><i class="ic ic-sm i-right mut" aria-hidden="true"></i></button><?php endforeach; ?></section>
</aside>
</div>
