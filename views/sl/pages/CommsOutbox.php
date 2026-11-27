<?php
/**
 * CommsOutbox - every message that went out, is waiting, was blocked or came
 * in, from every origin, in one list. Filters (channel, status, origin,
 * trigger / source, campaign, customer, dates, search), tiles that count by
 * outcome, a detail drawer and CSV export.
 * Data: views/sl/data/comms_outbox.php. Script: dataJs/sl/comms_outbox.js.
 * Endpoint: ajax/comms/outbox_ajax.php.
 */
if (!isset($d)) { $d = []; }
$commsTab = 'commsoutbox';
include __DIR__ . '/partials/comms_tabs.php';
$f = $d['f'];
$chIcon = ['whatsapp' => 'i-wa', 'sms' => 'i-message', 'email' => 'i-mail', '' => 'i-send'];
$st = $d['stats'];
$tiles = [
    ['k' => '', 'label' => 'Everything', 'n' => $st['total'], 'cls' => ''],
    ['k' => 'sent', 'label' => 'Sent', 'n' => $st['sent'], 'cls' => 'ok'],
    ['k' => 'failed', 'label' => 'Failed', 'n' => $st['failed'], 'cls' => 'bad'],
    ['k' => 'skipped', 'label' => 'Not sent', 'n' => $st['skipped'], 'cls' => 'warn'],
    ['k' => 'waiting', 'label' => 'Waiting', 'n' => $st['waiting'], 'cls' => 'info'],
    ['k' => 'received', 'label' => 'Received', 'n' => $st['received'], 'cls' => 'in'],
];
$boot = ['ajax' => $d['ajax'], 'open' => $d['open'], 'ai' => ['ok' => !empty($d['ai']['ok']), 'code' => (string) ($d['ai']['code'] ?? ''),
    'message' => (string) ($d['ai']['message'] ?? ''), 'setup_url' => (string) ($d['ai']['setup_url'] ?? ''), 'name' => (string) ($d['ai']['assistant'] ?? 'King')],
    'canAct' => $d['canAct'], 'outboxUrl' => sl_route('CommsOutbox')];
?>
<div class="sl-head"><div><h1 class="sl-title">Outbox</h1><p class="sl-lede">Everything we sent, are about to send, did not send, and what customers sent us. Click a row for the full story.</p></div>
<div class="row g8 wrap"><a class="sl-btn ghost" href="<?= h($d['historyUrl']) ?>"><i class="ic ic-sm i-user" aria-hidden="true"></i>Customer history</a><?php if ($d['canCosts']): ?><a class="sl-btn ghost" href="<?= h($d['costsUrl']) ?>"><i class="ic ic-sm i-dollar" aria-hidden="true"></i>Costs</a><?php endif; ?><?php if ($d['ready']): ?><a class="sl-btn ghost" href="<?= h($d['exportUrl'] ?? '#') ?>" data-ob-export download><i class="ic ic-sm i-download" aria-hidden="true"></i>Export CSV</a><?php endif; ?><?php if ($d['canSend']): ?><a class="sl-btn" href="<?= h($d['sendUrl']) ?>"><i class="ic ic-sm i-send" aria-hidden="true"></i>Send a message</a><?php endif; ?></div></div>

<?php if (!$d['ready']): ?>
<div class="sl-banner bad"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>The message tables are missing. Run the database upgrade to start recording messages.</span></div>
<?php else: ?>
<?php if (!empty($d['error'])): ?><div class="sl-banner bad"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span><?= h($d['error']) ?></span></div><?php endif; ?>

<nav class="ob-tiles" aria-label="Filter by outcome">
<?php foreach ($tiles as $t): $on = $f['status'] === $t['k']; ?>
<a class="sl-card ob-tile <?= h($t['cls']) ?><?= $on ? ' on' : '' ?>" href="<?= h(sl_url(['status' => $t['k'], 'page' => null, 'open' => null])) ?>"<?= $on ? ' aria-current="true"' : '' ?>><span class="ob-tl"><?= h($t['label']) ?></span><b class="ob-tn"><?= sl_num($t['n']) ?></b></a>
<?php endforeach; ?>
<div class="sl-card ob-tile cost"><span class="ob-tl" title="Cost of what was sent with these filters">Cost</span><b class="ob-tn"><?= $st['cost'] > 0 ? h(trim(($st['currency'] ?? '') . ' ' . number_format((float) $st['cost'], 2))) : '<span class="mut">—</span>' ?></b></div>
</nav>

<section class="sl-card flush ob-list" aria-labelledby="ob-list-h">
<h2 class="sr-only" id="ob-list-h">Messages</h2>
<form method="get" class="ob-filters" id="ob-filters" role="search" aria-label="Filter messages">
<?php if ($f['status'] !== ''): ?><input type="hidden" name="status" value="<?= h($f['status']) ?>"><?php endif; ?>
<?php if ($f['customer']): ?><input type="hidden" name="customer" value="<?= (int) $f['customer'] ?>"><?php endif; ?>
<?php if ($f['sent_by'] > 0): ?><input type="hidden" name="sent_by" value="<?= (int) $f['sent_by'] ?>"><?php endif; ?>
<?php if ($f['template'] > 0): ?><input type="hidden" name="template_id" value="<?= (int) $f['template'] ?>"><?php endif; ?>
<div class="sl-search ob-q"><i class="ic ic-sm i-search" aria-hidden="true"></i><input name="q" value="<?= h($f['q']) ?>" aria-label="Search messages" placeholder="Name, number, e-mail, tracking, text or error"></div>
<label class="ob-f"><span class="sr-only">Channel</span><span class="sl-sel native"><select name="channel" data-ob-auto aria-label="Channel"><option value="">All channels</option><?php foreach ($d['channels'] as $k => $l): ?><option value="<?= h($k) ?>"<?= $f['channel'] === $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
<label class="ob-f"><span class="sr-only">Origin</span><span class="sl-sel native"><select name="origin" data-ob-auto aria-label="Origin"><option value="">Every origin</option><?php foreach ($d['origins'] as $k => $l): ?><option value="<?= h($k) ?>"<?= $f['origin'] === $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
<label class="ob-f"><span class="sr-only">Trigger or source</span><span class="sl-sel native"><select name="source" data-ob-auto aria-label="Trigger or source"><option value="">Every trigger</option><?php foreach ($d['sources'] as $k => $l): ?><option value="<?= h($k) ?>"<?= $f['source'] === (string) $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
<?php if ($d['campaigns'] || $f['campaign'] !== ''): ?>
<label class="ob-f"><span class="sr-only">Campaign</span><span class="sl-sel native"><select name="campaign" data-ob-auto aria-label="Campaign or broadcast"><option value="">Any campaign</option><?php foreach ($d['campaigns'] as $k => $l): ?><option value="<?= h($k) ?>"<?= $f['campaign'] === (string) $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
<?php endif; ?>
<details class="ob-dates" data-sl-pop><summary class="sl-btn ghost sm"><i class="ic ic-sm i-calendar" aria-hidden="true"></i><?= $f['from'] !== '' || $f['to'] !== '' ? h(($f['from'] !== '' ? sl_date($f['from'], 'M j') : '…') . ' – ' . ($f['to'] !== '' ? sl_date($f['to'], 'M j') : 'today')) : 'Any date' ?></summary>
<div class="ob-pop"><label class="sl-field"><span class="sl-lab">From</span><input class="sl-in" type="date" name="from" value="<?= h($f['from']) ?>" max="<?= date('Y-m-d') ?>"></label><label class="sl-field"><span class="sl-lab">To</span><input class="sl-in" type="date" name="to" value="<?= h($f['to']) ?>" max="<?= date('Y-m-d') ?>"></label>
<div class="row g6 wrap"><button type="button" class="sl-btn ghost sm" data-ob-range="0">Today</button><button type="button" class="sl-btn ghost sm" data-ob-range="7">7 days</button><button type="button" class="sl-btn ghost sm" data-ob-range="30">30 days</button><button type="button" class="sl-btn ghost sm" data-ob-range="">Any</button></div>
<button class="sl-btn sm" type="submit">Apply</button></div></details>
<button class="sl-btn ghost sm ob-go" type="submit"><i class="ic ic-sm i-search" aria-hidden="true"></i>Search</button>
</form>
<?php
$chips = [];
if ($f['customer']) { $chips[] = ['Customer: ' . ($d['customerName'] ?: '#' . $f['customer']), ['customer' => null, 'recipient_id' => null]]; }
if ($f['sent_by'] > 0) { $chips[] = ['Sent by ' . cdp_opsUserName($f['sent_by']), ['sent_by' => null]]; }
if ($f['template'] > 0) { $chips[] = ['Template #' . $f['template'], ['template_id' => null]]; }
if ($f['status'] !== '') { $chips[] = ['Status: ' . ($d['statuses'][$f['status']] ?? $f['status']), ['status' => null, 'filter' => null]]; }
?>
<?php if ($chips || $d['filtered']): ?>
<div class="ob-chips row g8 wrap"><?php foreach ($chips as [$l, $p]): ?><a class="ob-chip" href="<?= h(sl_url($p + ['page' => null, 'open' => null])) ?>" aria-label="Remove filter <?= h($l) ?>"><?= h($l) ?><i class="ic ic-sm i-x" aria-hidden="true"></i></a><?php endforeach; ?>
<?php if ($d['filtered']): ?><a class="sm ob-clear" href="<?= h(sl_route('CommsOutbox')) ?>">Clear all filters</a><?php endif; ?></div>
<?php endif; ?>

<div class="sl-tw ob-tw"><table class="sl-table ob-table"><thead><tr><th scope="col">When</th><th scope="col">Customer</th><th scope="col">Channel</th><th scope="col">Message</th><th scope="col">Status</th><th scope="col" class="ob-num">Cost</th><th scope="col"><span class="sr-only">Details</span></th></tr></thead><tbody>
<?php foreach ($d['rows'] as $r): $in = $r['k'] === 'in'; ?>
<tr class="ob-row<?= $in ? ' in' : '' ?>" data-ob-ref="<?= h($r['ref']) ?>" tabindex="0" aria-label="<?= h(($in ? 'From ' : 'To ') . ($r['name'] ?: $r['to']) . ', ' . $r['status_label'] . ', ' . $r['when']) ?>">
<td class="ob-when"><span class="mono sm"><?= h($r['when']) ?></span><span class="sub"><?= h($r['ago']) ?></span></td>
<td class="ob-who"><div class="sl-cell"><span class="sl-av sm"><?= h(sl_initials($r['name'] ?: ($r['to'] ?: '?'))) ?></span><span class="ob-wt"><span class="fw5"><?= h($r['name'] ?: ($r['to'] ?: '—')) ?></span><?php if ($r['name'] !== '' && $r['to'] !== ''): ?><span class="sub"><?= h($r['to']) ?></span><?php endif; ?></span></div></td>
<td class="ob-ch"><span class="ob-chn <?= h($r['channel'] ?: 'all') ?>"><i class="ic ic-sm <?= h($chIcon[$r['channel']] ?? 'i-send') ?>" aria-hidden="true"></i><?= h($r['channel_label']) ?></span><?php if ($in): ?><span class="sub">incoming</span><?php endif; ?></td>
<td class="ob-msg"><span class="ob-what"><span class="ob-org <?= h($r['origin']) ?>"><?= h($r['origin_label']) ?></span><?= h($r['what']) ?></span>
<span class="ob-ex"><?php if ($r['subject'] !== ''): ?><b class="fw5"><?= h($r['subject']) ?></b> · <?php endif; ?><?= h($r['excerpt'] !== '' ? $r['excerpt'] : '(no text)') ?></span>
<?php if ($r['record'] !== '' || $r['campaign'] !== ''): ?><span class="ob-meta"><?php if ($r['record'] !== ''): ?><span class="mono"><?= h($r['record']) ?></span><?php endif; ?><?php if ($r['campaign'] !== '' && $r['campaign'] !== $r['record']): ?><span><?= h($r['campaign']) ?></span><?php endif; ?></span><?php endif; ?></td>
<td class="ob-st"><span class="sl-status" title="<?= h($r['detail']) ?>"><span class="sl-pill <?= h($r['cls']) ?>"><?= h($r['status_label']) ?></span><?php if ($r['short'] !== ''): ?><small class="<?= in_array($r['status'], ['failed'], true) ? 'bad' : '' ?>"><?= h($r['short']) ?></small><?php endif; ?></span>
<?php if ($r['attempts'] > 1 || ($r['k'] === 'queue' && $r['attempts'] > 0 && $r['status'] !== 'sent')): ?><span class="sub"><?= (int) $r['attempts'] ?> attempt<?= $r['attempts'] === 1 ? '' : 's' ?></span><?php endif; ?>
<?php if ($r['status'] === 'scheduled' && $r['send_after'] !== ''): ?><span class="sub">Goes out <?= h(sl_date($r['send_after'], 'M j, H:i')) ?></span><?php endif; ?>
<?php if ($r['opens'] !== null): ?><span class="sub"><?= (int) $r['opens'] ?> open<?= $r['opens'] === 1 ? '' : 's' ?> · <?= (int) $r['clicks'] ?> click<?= $r['clicks'] === 1 ? '' : 's' ?></span><?php endif; ?></td>
<td class="ob-num mono sm"><?= $r['cost'] !== null ? h(number_format((float) $r['cost'], 3)) : '<span class="mut">—</span>' ?></td>
<td class="ob-go-c"><button type="button" class="sl-btn ghost sm ob-open" data-ob-open="<?= h($r['ref']) ?>" aria-label="Details of this message"><i class="ic ic-sm i-right" aria-hidden="true"></i><span class="ob-open-l">Details</span></button></td>
</tr>
<?php endforeach; ?>
<?php if (!$d['rows']): ?><tr><td colspan="7"><div class="sl-empty"><span class="sl-tile"><i class="ic i-inbox" aria-hidden="true"></i></span><?= $d['filtered'] ? 'No messages match these filters.' : 'Nothing has been sent yet. Messages appear here the moment they are queued, sent, blocked or received.' ?><?php if ($d['filtered']): ?> <a href="<?= h(sl_route('CommsOutbox')) ?>">Clear the filters</a><?php endif; ?></div></td></tr><?php endif; ?>
</tbody></table></div>
<?= sl_pager($d['page'], $d['per'], $d['total'], 'messages') ?>
</section>
<?php if (!$d['linked']): ?><p class="mut sm">Older Communication Center messages show their attempts separately: the message log has no link to the queue yet (migrations/2026_11_27_message_log_outbox.sql).</p><?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/partials/comms_outbox_drawer.php'; ?>
<script>window.SL_OUTBOX = <?= json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
