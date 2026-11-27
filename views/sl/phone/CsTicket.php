<?php
/**
 * M-Ticket: one support ticket on a phone. Same $d as the web page (views/sl/data/support.php sl_csTicketData).
 * Writes go to ajax/support/desk_ajax.php (update / send) through dataJs/sl/phone/cs_desk.js with the web
 * page's field names (status, priority, assignee_id, team_id, resolution_code, resolution_summary; mode, body, set_status).
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['nav'] = 'none';
$slPhone['scripts'][] = 'dataJs/sl/cs_common.js';
$slPhone['scripts'][] = 'dataJs/sl/phone/cs_desk.js';
$t = $d['ticket'] ?? null;
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-desk.css')) ?>">
<?php if (!$t): ?>
<?= sl_phoneTop('Ticket', '', ['back' => sl_route('CsInbox')]) ?>
<main class="mx-body"><?= sl_mxEmpty('ticket', 'Ticket not found', 'It may have been merged or deleted.', 'Back to the desk', sl_route('CsInbox')) ?></main>
<?php return; endif;
$sla = $d['sla'];
$clock = $sla['first'][0] !== '' && strpos($sla['first'][0], 'Met') !== 0 ? ['First reply', $sla['first']] : ['Resolve', $sla['resolve']];
$link = $d['links'][0]['ref'] ?? ($d['links'][0]['entity_ref'] ?? '');
$sub = implode(' · ', array_filter([$t['from'], $link, $t['resolved'] ? $t['statusLabel'] : $clock[0] . ' ' . lcfirst($clock[1][0])]));
$more = $d['customer'] ? sl_phoneIcon('user', 'Customer profile', sl_route('CsCustomer', ['id' => $d['customer']['id']])) : '';
?>
<?= sl_phoneTop($t['ref'], '', ['back' => sl_route('CsInbox')], $more) ?>
<main class="mx-body" id="pd-tk" data-id="<?= (int) $t['id'] ?>" data-version="<?= (int) $t['version'] ?>" data-last="<?= (int) $t['lastId'] ?>">
<section class="mx-hero"><div class="mx-hk"><?= h($t['subject']) ?><span class="mx-tag"><?= h($t['statusLabel']) ?></span></div><b class="mx-hv"><?= h($t['prioLabel']) ?></b><small><?= h($sub) ?></small><div class="mx-prog" role="img" aria-label="Resolution time used <?= (int) $sla['resolvePct'] ?>%"><i style="width:<?= (int) $sla['resolvePct'] ?>%"></i></div></section>
<section class="mx-chat" aria-label="Conversation">
<?php foreach ($d['thread'] as $m): ?>
<?php if ($m['kind'] === 'sys'): ?><div class="mx-msg sys"><p><?= h($m['text']) ?></p></div>
<?php else: ?><div class="mx-msg <?= h($m['cls']) ?>"><p><?= h($m['body']) ?></p><small><?= $m['note'] ? 'Private note · ' : '' ?><?= h($m['who'] . ' · ' . $m['when']) ?></small><?php foreach ($m['files'] as $f): ?><small><a href="<?= h($f['href']) ?>" target="_blank" rel="noopener"><?= h($f['name']) ?></a></small><?php endforeach; ?></div>
<?php endif; ?>
<?php endforeach; ?>
<?php if (!$d['thread']): ?><div class="mx-msg sys"><p>No messages yet.</p></div><?php endif; ?>
</section>
<?php if ($t['summary'] !== ''): ?><div class="mx-ban info"><i class="ic ic-sm i-ai" aria-hidden="true"></i><span><b>Summary by <?= h($d['king']) ?>:</b> <?= h($t['summary']) ?></span></div><?php endif; ?>
<section class="mx-card mx-kv"><div><span>Assigned</span><b><?= h($t['assignee']) ?></b></div><div><span>Priority</span><b><?= h($t['prioLabel']) ?></b></div><div><span>Channel</span><b><?= h($t['channelLabel']) ?></b></div><div><span>Tags</span><b><?= $d['tags'] ? h(implode(' · ', $d['tags'])) : '—' ?></b></div></section>
<div class="mx-btns"><a class="mx-btn" href="#pd-body" data-pd-reply>Reply</a><?php if (!$t['resolved']): ?><button type="button" class="mx-btn sec" data-sl-open="pd-resolve">Close ticket</button><?php else: ?><button type="button" class="mx-btn sec" data-pd-field="status" data-value="open">Reopen ticket</button><?php endif; ?></div>
<?= sl_mxSec('Reply') ?>
<section class="mx-card mx-form">
  <label class="pd-f"><small>To <?= h($t['from']) ?> via <?= h($t['channelLabel']) ?></small><textarea class="pd-in" id="pd-body" maxlength="8000" placeholder="Write your reply…"></textarea></label>
  <label class="pd-f"><small>After sending</small><select class="pd-in" id="pd-after"><option value="">Keep status</option><option value="pending_customer">Wait on customer</option><option value="pending_internal">Wait on our team</option><option value="resolved">Resolve</option></select></label>
  <div class="mx-btns"><button type="button" class="mx-btn" data-pd-send="reply">Send reply</button><button type="button" class="mx-btn sec" data-pd-send="note">Add private note</button></div>
</section>
<?= sl_mxSec('Ticket') ?>
<section class="mx-card mx-form">
  <label class="pd-f"><small>Status</small><select class="pd-in" data-pd-field="status"><?php foreach ($d['statuses'] as $k => $l): ?><option value="<?= h($k) ?>"<?= $k === $t['status'] ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></label>
  <label class="pd-f"><small>Priority</small><select class="pd-in" data-pd-field="priority"><?php foreach ($d['priorities'] as $k => $l): ?><option value="<?= h($k) ?>"<?= $k === $t['priority'] ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></label>
  <label class="pd-f"><small>Assigned to</small><select class="pd-in" data-pd-field="assignee_id"><option value="0">Unassigned</option><?php foreach ($d['agents'] as $a): ?><option value="<?= (int) $a['id'] ?>"<?= (int) $a['id'] === $t['assignee_id'] ? ' selected' : '' ?>><?= h($a['name']) ?></option><?php endforeach; ?></select></label>
  <label class="pd-f"><small>Team</small><select class="pd-in" data-pd-field="team_id"><option value="0">No team</option><?php foreach ($d['teams'] as $tm): ?><option value="<?= (int) $tm['id'] ?>"<?= (int) $tm['id'] === $t['team_id'] ? ' selected' : '' ?>><?= h($tm['name']) ?></option><?php endforeach; ?></select></label>
</section>
</main>
<div class="sl-dialog pd-sheet" id="pd-resolve" hidden><form id="pd-resolve-form" role="dialog" aria-label="Close ticket">
<h2>Close <?= h($t['ref']) ?></h2><p class="pd-note">The customer is asked for a rating later, unless the ticket reopens first.</p>
<label class="pd-f"><small>How was it resolved?</small><select class="pd-in" name="resolution_code"><?php foreach ($d['resolutionCodes'] as $k => $l): ?><option value="<?= h($k) ?>"><?= h($l) ?></option><?php endforeach; ?></select></label>
<label class="pd-f"><small>In a sentence</small><textarea class="pd-in" name="resolution_summary" rows="2" required><?= h($t['resolution']) ?></textarea></label>
<div class="mx-btns"><button type="submit" class="mx-btn">Close ticket</button><button type="button" class="mx-btn sec" data-sl-close>Cancel</button></div>
</form></div>
