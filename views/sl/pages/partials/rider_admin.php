<?php
/** Quests and the emergency number (web: $phone = false, phone: true). $ra = sl_riderAdminData(). */
$dt = fn($v) => $v ? date('Y-m-d\TH:i', strtotime((string) $v)) : '';
$in = $phone ? 'mx-in' : 'sl-in';
$f = fn(string $t, string $inner) => $phone ? '<label class="mx-f"><small>' . h($t) . '</small>' . $inner . '</label>' : '<label class="sl-field"><span class="sl-lab">' . h($t) . '</span>' . $inner . '</label>';
$q = $ra['questEdit'];
$qs = $phone ? '?section=quests&' : '?';
?>
<?php if ($phone): ?><div class="mx-sec" id="quests"><h2>Rider quests</h2><?php if ($ra['canEdit'] && $ra['questsReady']): ?><a href="<?= $qs ?>quest=new#quests">New quest</a><?php endif; ?></div>
<?php else: ?><section class="sl-card col g12" id="quests"><div class="sl-ch" style="margin:0"><div><h2 class="sl-ct">Rider quests</h2><span class="mut sm">Extra pay for reaching a number of trips in a period. Riders see them in their app.</span></div><?php if ($ra['canEdit'] && $ra['questsReady']): ?><a class="sl-btn ghost sm" href="<?= $qs ?>quest=new#quests">New quest</a><?php endif; ?></div><?php endif; ?>
<?php if (!$ra['questsReady']): ?><p class="<?= $phone ? 'mx-note' : 'mut sm' ?>">Quests need the rider app database update (migrations/2026_11_27_rider_app.sql).</p>
<?php else: ?>
<?php if ($q && $ra['canEdit']): ?>
<form class="<?= $phone ? 'mx-card mx-form' : 'col g12' ?>" data-sl-form action="ajax/sl/rider_app_ajax.php" method="post" data-sl-reload>
<input type="hidden" name="op" value="quest_save"><input type="hidden" name="id" value="<?= (int) $q->id ?>">
<?= $f('Name riders see', '<input class="' . $in . '" name="title" maxlength="120" required placeholder="Weekend run" value="' . h($q->title) . '">') ?>
<div class="<?= $phone ? 'mx-two' : 'grid cols2' ?>"><?= $f('Trips to finish', '<input class="' . $in . '" name="target_trips" type="number" inputmode="numeric" min="1" max="10000" required value="' . h((string) $q->target_trips) . '">') ?><?= $f('Reward', '<input class="' . $in . '" name="reward" type="number" inputmode="decimal" step="0.01" min="0" required value="' . h((string) $q->reward) . '">') ?></div>
<div class="<?= $phone ? 'mx-two' : 'grid cols2' ?>"><?= $f('Starts', '<input class="' . $in . '" name="starts_at" type="datetime-local" required value="' . h($dt($q->starts_at)) . '">') ?><?= $f('Ends', '<input class="' . $in . '" name="ends_at" type="datetime-local" required value="' . h($dt($q->ends_at)) . '">') ?></div>
<label class="row g8 sm"><input type="checkbox" name="active" value="1"<?= (int) $q->active ? ' checked' : '' ?>> Show it to riders</label>
<div class="<?= $phone ? 'mx-btns' : 'row g8' ?>"><button type="submit" class="<?= $phone ? 'mx-btn' : 'sl-btn' ?>"><?= $q->id ? 'Save quest' : 'Add quest' ?></button><a class="<?= $phone ? 'mx-btn sec' : 'sl-btn ghost' ?>" href="<?= $phone ? '?section=quests' : '?' ?>#quests">Cancel</a></div>
</form>
<?php endif; ?>
<?php if (!$ra['quests']): ?><p class="<?= $phone ? 'mx-note' : 'mut sm' ?>">No quests yet. Riders see "No quests are running" until you add one.</p><?php else: ?>
<?php if ($phone): ?><section class="mx-card mx-list"><?php endif; ?>
<?php foreach ($ra['quests'] as $x): $sub = $x['target'] . ' trips · ' . $x['reward'] . ' · ' . $x['window'] . ($x['reached'] ? ' · reached by ' . implode(', ', array_slice($x['reached'], 0, 4)) . (count($x['reached']) > 4 ? ' +' . (count($x['reached']) - 4) : '') : ''); ?>
<?php if ($phone): ?><?= sl_mxRow('flag', $x['title'], $sub, sl_pill($x['state'], $x['cls']), $ra['canEdit'] ? $qs . 'quest=' . $x['id'] . '#quests' : '') ?>
<?php else: ?><div class="row between g12 wrap" style="padding:10px 0;border-top:1px solid var(--line)"><span class="col" style="min-width:0"><b class="fw5"><?= h($x['title']) ?></b><small class="mut"><?= h($sub) ?></small></span><span class="row g8"><?= sl_pill($x['state'], $x['cls']) ?><?php if ($ra['canEdit']): ?><a class="sl-btn ghost sm" href="<?= $qs ?>quest=<?= (int) $x['id'] ?>#quests">Edit</a><?php endif; ?></span></div><?php endif; ?>
<?php endforeach; ?>
<?php if ($phone): ?></section><?php endif; ?>
<?php endif; ?>
<p class="<?= $phone ? 'mx-note' : 'mut sm' ?>">Rewards are not paid automatically: pay the riders listed under "reached by" with their next settlement or payout.</p>
<?php endif; ?>
<?php if (!$phone): ?></section><?php endif; ?>

<?php if ($phone): ?><div class="mx-sec"><h2>Emergency number</h2></div><?php else: ?><section class="sl-card col g12"><div><h2 class="sl-ct">Emergency number</h2><span class="mut sm">The SOS button in the rider app calls this number. Without it, SOS calls dispatch.</span></div><?php endif; ?>
<form class="<?= $phone ? 'mx-card mx-form' : 'row g8 wrap' ?>" data-sl-form action="ajax/sl/rider_app_ajax.php" method="post">
<input type="hidden" name="op" value="site_setting"><input type="hidden" name="key" value="emergency_number">
<?= $f('Number to call', '<input class="' . $in . '" name="value" type="tel" inputmode="tel" maxlength="16" placeholder="For example 112" value="' . h($ra['emergency']) . '"' . ($ra['canEdit'] ? '' : ' disabled') . '>') ?>
<?php if ($ra['canEdit']): ?><button type="submit" class="<?= $phone ? 'mx-btn' : 'sl-btn' ?>">Save number</button><?php endif; ?>
</form>
<?php if ($phone): ?><p class="mx-note">The SOS button in the rider app calls this number. Leave it empty to call dispatch.</p><?php else: ?></section><?php endif; ?>
