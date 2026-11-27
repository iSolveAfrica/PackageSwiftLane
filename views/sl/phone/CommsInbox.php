<?php
/**
 * M-WaInbox (list) and M-WaChat (one conversation, ?c=<thread id>) for comms_inbox.php.
 * Lists use the same filters and search as ajax/comms/comms_ajax.php?action=threads (?f=open|mine|bot|all|closed, ?q=).
 * Opening a conversation marks it read through action=thread; replies go to action=reply (fields action, id, body);
 * assign / close / reopen / King on-off go to action=thread_update (id, op), all the endpoints the web inbox uses.
 */
require_once CDP_ROOT . '/views/sl/data/phone_msg.php';
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['scripts'][] = 'dataJs/sl/phone/msg.js';
$slPhone['tab'] = 'Inbox';
$king = (string) ($d['king'] ?? 'King');
$ajax = 'ajax/comms/comms_ajax.php';
$me = (int) ($userData->id ?? 0);
$cid = (int) ($_GET['c'] ?? 0);
$th = $cid > 0 && !empty($d['ready']) ? sl_pmThread($cid) : null;
$nameOf = fn($t) => trim((string) ($t->contact_name ?: $t->customer_name)) ?: '+' . $t->phone;
$ini = function ($t): string {
    $n = trim((string) ($t->contact_name ?: $t->customer_name));
    return $n === '' ? '#' : sl_initials($n);
};
$canCustomer = $user->cdp_hasPermission(['view_client_list', 'edit_client']);
$me2 = sl_phoneMe();
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-msg.css')) ?>">
<?php if ($th): $t = $th['t']; $closed = $t->status === 'closed'; $bot = (int) $t->bot_active === 1; $slPhone['nav'] = 'none'; $windowShut = $th['provider'] === 'cloud' && !$th['window']; ?>
<?= sl_phoneTop($nameOf($t), '+' . $t->phone . ((int) $t->user_id > 0 && $t->customer_since ? ' · customer since ' . date('Y', strtotime((string) $t->customer_since)) : ((int) $t->user_id > 0 ? '' : ' · not linked to a customer')), ['back' => sl_url(['c' => null])],
    ((int) $t->user_id > 0 && $canCustomer ? sl_phoneIcon('user', 'Customer page', sl_route('CustomerProfile', ['id' => (int) $t->user_id])) : '')) ?>
<main class="mx-body" id="pm-chat-body" data-pm-thread="<?= (int) $t->id ?>" data-pm-ajax="<?= h($ajax) ?>">
<div class="mx-chips">
<?php if ($t->locker): ?><span>Locker <?= h($t->locker) ?></span><?php endif; ?>
<?php if ((int) $t->user_id > 0): ?><span><?= (int) $th['parcels'] ?> parcel<?= $th['parcels'] === 1 ? '' : 's' ?></span><?php endif; ?>
<span><?= $closed ? 'Closed' : ($bot ? 'With ' . h($king) : 'Waiting for a person') ?></span>
<?php if ((int) $t->assignee_id > 0): ?><span><?= (int) $t->assignee_id === $me ? 'Assigned to you' : 'Assigned to ' . h(cdp_opsUserName($t->assignee_id)) ?></span><?php endif; ?>
</div>
<div class="mx-chips" role="group" aria-label="Conversation actions">
<?php if ($closed): ?><button type="button" data-pm-op="reopen">Reopen</button>
<?php else: ?>
<?php if ((int) $t->assignee_id !== $me): ?><button type="button" data-pm-op="assign_me">Assign to me</button><?php else: ?><button type="button" data-pm-op="unassign">Unassign</button><?php endif; ?>
<button type="button" data-pm-op="<?= $bot ? 'bot_off' : 'bot_on' ?>"><?= $bot ? 'Stop ' . h($king) : 'Let ' . h($king) . ' answer' ?></button>
<button type="button" data-pm-op="close" data-pm-confirm="Close this conversation?">Close</button>
<?php endif; ?>
</div>
<div class="mx-chan">WhatsApp · <?= $windowShut ? '24-hour window closed: send an approved template from the customer history' : 'replies go to +' . h($t->phone) ?></div>
<section class="mx-chat" aria-live="polite">
<?php foreach ($th['msgs'] as $m):
    $cls = $m->direction === 'in' ? '' : ($m->author_type === 'agent' ? 'me' : 'ai');
    $who = $m->direction === 'in' ? '' : ($m->author_type === 'agent' ? cdp_opsUserName($m->author_id) : ($m->author_type === 'bot' ? $king : 'System'));
    $fail = in_array($m->status, ['failed', 'error'], true);
?>
<div class="mx-msg <?= $cls ?><?= $fail ? ' fail' : '' ?>"><p><?= h((string) $m->body) ?></p><small><?= h(date('H:i', strtotime((string) $m->created_at))) ?><?= $who !== '' ? ' · ' . h($who) : '' ?><?= $fail ? ' · <b>Not delivered</b>' : '' ?></small></div>
<?php endforeach; ?>
<?php if (!$th['msgs']): ?><p class="mx-note">No messages yet.</p><?php endif; ?>
</section>
</main>
<div class="mx-foot">
<?php if ($closed): ?>
<div class="mx-comp"><span>This conversation is closed. Reopen it to reply.</span></div>
<?php else: ?>
<p class="pm-err" id="pm-reply-err" hidden></p>
<form class="mx-comp" id="pm-reply" data-pm-reply="<?= h($ajax) ?>">
<input type="hidden" name="id" value="<?= (int) $t->id ?>">
<textarea name="body" rows="1" aria-label="Reply on WhatsApp" placeholder="Reply on WhatsApp" maxlength="4000" required<?= $windowShut ? ' disabled' : '' ?>></textarea>
<button class="mx-send" type="submit" aria-label="Send"<?= $windowShut ? ' disabled' : '' ?>><i class="ic i-send" aria-hidden="true"></i></button>
</form>
<?php endif; ?>
</div>
<?php else:
    $L = !empty($d['ready']) ? sl_pmThreads((string) ($_GET['f'] ?? 'open'), (string) ($_GET['q'] ?? '')) : ['filter' => 'open', 'q' => '', 'rows' => [], 'counts' => ['all' => 0, 'open' => 0, 'mine' => 0, 'bot' => 0, 'closed' => 0]];
    $c = $d['conn'] ?? null;
    $tabs = ['open' => 'Waiting', 'mine' => 'Mine', 'bot' => 'With ' . $king, 'all' => 'All', 'closed' => 'Closed'];
    $empty = ['all' => 'No conversations yet.', 'open' => 'Nobody is waiting for a person.', 'mine' => 'No open conversations are assigned to you.', 'bot' => 'No conversations are with ' . $king . ' right now.', 'closed' => 'No closed conversations.'];
?>
<?= sl_phoneTop('WhatsApp inbox', '', ['avatar' => $me2['ini'], 'href' => sl_route('Workspace'), 'online' => true], sl_phoneIcon('search', 'Search', sl_route('LockerSearch')) . sl_phoneIcon('bell', 'Notifications', sl_route('Notifications'))) ?>
<main class="mx-body" data-pm-inbox>
<?php if (empty($d['ready'])): ?><div class="mx-ban warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>The communication tables could not be created.</span></div>
<?php elseif ($c && !$c['connected']): ?><div class="mx-ban warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>WhatsApp is <?= $c['inbound'] || $c['outbound'] ? 'partly connected' : 'not connected' ?>. <?= !empty($d['isAdmin']) ? 'Connect it on a computer in Communication Center › Settings.' : 'An administrator connects it.' ?></span></div><?php endif; ?>
<div class="mx-srch"><form method="get" role="search"><input type="hidden" name="f" value="<?= h($L['filter']) ?>"><label><i class="ic i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($L['q']) ?>" aria-label="Search chats" placeholder="Name, number, locker or text" autocomplete="off"></label></form></div>
<nav class="mx-seg" aria-label="Show conversations"><?php foreach ($tabs as $k => $l): ?><a class="<?= $k === $L['filter'] ? 'on' : '' ?>" href="<?= h(sl_url(['f' => $k === 'open' ? null : $k])) ?>"<?= $k === $L['filter'] ? ' aria-current="page"' : '' ?>><?= h($l) ?><?= $L['counts'][$k] ? ' ' . (int) $L['counts'][$k] : '' ?></a><?php endforeach; ?></nav>
<?php if ($L['rows']): ?>
<section class="mx-card mx-list">
<?php foreach ($L['rows'] as $r):
    $pill = (int) $r->unread > 0 ? sl_pill((string) (int) $r->unread, 'overdue') : ((int) $r->user_id === 0 && $r->status !== 'closed' ? sl_pill('New', 'sorting') : '');
?>
<a class="mx-row" href="<?= h(sl_url(['c' => (int) $r->id])) ?>"><span class="mx-ava"><?= h($ini($r)) ?></span><span class="mx-rm"><b><?= h($nameOf($r)) ?></b><small><?= h((string) $r->last_message) ?></small></span><span class="mx-rr"><?= $pill ?><small><?= h(sl_ago($r->updated_at)) ?></small></span></a>
<?php endforeach; ?>
</section>
<?php if ($L['counts'][$L['filter']] > count($L['rows'])): ?><p class="mx-note">Showing the latest <?= count($L['rows']) ?> of <?= (int) $L['counts'][$L['filter']] ?>. Search to narrow the list.</p><?php endif; ?>
<?php else: ?>
<?= sl_mxEmpty('message', $L['q'] !== '' ? 'No conversations match “' . $L['q'] . '”' : $empty[$L['filter']], '', $L['filter'] !== 'all' ? 'Show all' : '', sl_url(['f' => 'all', 'q' => null])) ?>
<?php endif; ?>
</main>
<?php endif; ?>
