<?php
/**
 * CommsSend - one Send page for every message a person writes: one person,
 * several people or a campaign; WhatsApp (phone preview), SMS (phone preview
 * and counter), E-mail (the drag-and-drop builder) and the in-app bell; now or
 * later; who will be skipped and why; approval; Write with King.
 * Data: views/sl/data/comms_send.php. Script: dataJs/sl/comms_send.js.
 * Endpoint: ajax/comms/send_ajax.php.
 */
if (!isset($d)) { $d = []; }
$commsTab = 'commssend';
include __DIR__ . '/partials/comms_tabs.php';
$chs = ['whatsapp' => ['WhatsApp', 'i-wa'], 'sms' => ['SMS', 'i-message'], 'email' => ['E-mail', 'i-mail'], 'inapp' => ['In-app', 'i-bell']];
$boot = $d;
unset($boot['canvas']);
?>
<div class="sl-head"><div><h1 class="sl-title">Send a message</h1><p class="sl-lede">One person, several people or a whole campaign, by WhatsApp, SMS, e-mail or in the app.</p></div>
<div class="row g8 wrap snd-hrow"><?php if ($d['canCampaign']): ?><a class="sl-btn ghost" href="#campaigns" data-snd-jump="campaigns"><i class="ic ic-sm i-send" aria-hidden="true"></i>Campaigns</a><a class="sl-btn ghost" href="<?= h($d['reportUrl']) ?>?all=1"><i class="ic ic-sm i-chart" aria-hidden="true"></i>All bulk sends</a><?php endif; ?><a class="sl-btn ghost" href="<?= h($d['outboxUrl']) ?>"><i class="ic ic-sm i-file" aria-hidden="true"></i>Outbox</a></div></div>
<?php if (!$d['ready']): ?>
<div class="sl-banner bad"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>The communication tables could not be created. The database user needs the CREATE privilege once.</span></div>
<?php else: ?>
<div class="snd-health row g8 wrap" aria-label="Channel status"><?php foreach ($chs as $k => [$l, $ic]): $hh = $d['health'][$k] ?? null; if (!$hh) continue; ?><span class="snd-hp <?= $hh['ok'] ? 'ok' : 'bad' ?>"><i class="ic ic-sm <?= $ic ?>" aria-hidden="true"></i><?= h($l) ?> · <?= h($hh['state']) ?></span><?php endforeach; ?><?php if ($d['isAdmin']): ?><a class="sm" href="<?= h($d['settingsUrl']) ?>">Channel settings</a><?php endif; ?></div>

<form class="snd-grid" id="snd-form" novalidate>
<div class="snd-main col g16">

<section class="sl-card snd-step" id="snd-who" aria-labelledby="snd-who-h">
<div class="snd-sh"><span class="snd-n" aria-hidden="true">1</span><div class="col"><h2 class="sl-ct" id="snd-who-h">Who gets it</h2><span class="mut sm" id="snd-who-sub">Pick one customer, a few people, or build a campaign audience.</span></div></div>
<div class="sl-seg boxed snd-mode" role="tablist" aria-label="Send to">
<button type="button" role="tab" data-mode="one"><i class="ic ic-sm i-user" aria-hidden="true"></i>One person</button>
<button type="button" role="tab" data-mode="several"><i class="ic ic-sm i-users" aria-hidden="true"></i>Several people</button>
<?php if ($d['canCampaign']): ?><button type="button" role="tab" data-mode="campaign"><i class="ic ic-sm i-send" aria-hidden="true"></i>Campaign</button><?php endif; ?>
</div>

<div class="snd-pane col g12" data-pane="one" hidden>
<div class="sl-field snd-find"><label class="sl-lab" for="snd-q1">Customer</label><div class="snd-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input class="sl-in" id="snd-q1" autocomplete="off" placeholder="Name, locker, phone or e-mail" aria-controls="snd-sug1" aria-autocomplete="list"></div><div class="snd-sug" id="snd-sug1" role="listbox" hidden></div></div>
<div class="snd-one" id="snd-one" hidden></div>
</div>

<div class="snd-pane col g12" data-pane="several" hidden>
<div class="sl-field snd-find"><label class="sl-lab" for="snd-q2">Add customers</label><div class="snd-chips" id="snd-chips"><i class="ic ic-sm i-search mut" aria-hidden="true"></i><input id="snd-q2" autocomplete="off" placeholder="Name, locker, phone or e-mail" aria-controls="snd-sug2" aria-autocomplete="list"></div><div class="snd-sug" id="snd-sug2" role="listbox" hidden></div></div>
<label class="sl-field"><span class="sl-lab">Or paste phone numbers and e-mail addresses</span><textarea class="sl-in snd-ta" id="snd-list" rows="3" placeholder="ama@mail.com, 024 551 0921&#10;One per line, or separated by commas"><?= h($d['list']) ?></textarea><span class="sl-hint">Numbers and addresses that belong to a customer are matched to that customer. Up to <?= (int) $d['directMax'] ?> people; for more, send a campaign.</span></label>
</div>

<?php if ($d['canCampaign']): ?>
<div class="snd-pane col g12" data-pane="campaign" hidden>
<div class="grid cols2 snd-g2"><label class="sl-field"><span class="sl-lab">Campaign name <span class="mut">(only your team sees it)</span></span><input class="sl-in" id="snd-name" maxlength="160" placeholder="e.g. December sea freight discount"></label>
<label class="sl-field"><span class="sl-lab">Start from</span><span class="sl-sel native"><select id="snd-base"><?php foreach ($d['presets'] as $p): ?><option value="<?= h($p['k']) ?>"><?= h($p['label']) ?></option><?php endforeach; ?><?php if ($d['consolidations']): ?><option value="consolidation">Owners of one consolidation</option><?php endif; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label></div>
<label class="sl-field" id="snd-cons-f" hidden><span class="sl-lab">Consolidation</span><span class="sl-sel native"><select id="snd-cons"><?php foreach ($d['consolidations'] as $c): ?><option value="<?= h($c['k']) ?>"><?= h($c['label']) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
<div class="col g8"><div class="row between wrap g8"><span class="sl-lab">Only customers who match</span><span class="sl-seg boxed sm" id="snd-match" role="group" aria-label="Match"><button type="button" class="on" data-v="all">All filters</button><button type="button" data-v="any">Any filter</button></span></div>
<div class="col g8" id="snd-rules"></div>
<div class="row g8 wrap"><button type="button" class="sl-btn ghost sm" id="snd-addrule"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add a filter</button><span class="mut sm">City, last shipment, balance, packages waiting, opted in per channel…</span></div></div>
<div class="snd-contacts col g8">
<?php if ($d['contacts']['ready']): ?>
<label class="row g8 snd-check"><input type="checkbox" id="snd-ccust" checked><span><b class="fw5">Customers</b> <span class="mut sm">accounts on the platform, as filtered above</span></span></label>
<label class="row g8 snd-check"><input type="checkbox" id="snd-con"><span><b class="fw5">WhatsApp contacts</b> <span class="mut sm">saved from WhatsApp chats and groups. You chose that they may receive campaigns; anyone who replied STOP is left out.</span></span></label>
<label class="sl-field" id="snd-csrc-f" hidden><span class="sl-lab">Contacts saved from</span><span class="sl-sel native"><select id="snd-csrc"><option value="">Every chat and group</option><option value="whatsapp_group:*">Every group</option><?php foreach ($d['contacts']['sources'] as $s): ?><option value="<?= h($s['k']) ?>"><?= h(ucfirst($s['label'])) ?> (<?= (int) $s['n'] ?>)</option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
<?php else: ?>
<p class="mut sm row g6"><i class="ic ic-sm i-wa" aria-hidden="true"></i><span>Contacts you save from WhatsApp chats and groups (<a href="<?= h($d['waUrl']) ?>">WhatsApp</a>) appear here as an audience too.</span></p>
<?php endif; ?>
</div>
</div>
<?php endif; ?>
</section>

<section class="sl-card snd-step" id="snd-msg" aria-labelledby="snd-msg-h">
<div class="snd-sh"><span class="snd-n" aria-hidden="true">2</span><div class="col"><h2 class="sl-ct" id="snd-msg-h">Message</h2><span class="mut sm">Switch on the channels to use. Each channel keeps its own text.</span></div></div>
<div class="snd-chs" role="group" aria-label="Channels"><?php foreach ($chs as $k => [$l, $ic]): $hh = $d['health'][$k] ?? ['ok' => true, 'state' => '']; ?>
<button type="button" class="snd-ch" data-ch="<?= $k ?>" aria-pressed="false" title="<?= h($l . ' · ' . $hh['state']) ?>"><span class="snd-tick" aria-hidden="true"></span><i class="ic ic-sm <?= $ic ?>" aria-hidden="true"></i><span><?= h($l) ?></span><?php if (!$hh['ok']): ?><em class="snd-warn" title="<?= h($hh['state']) ?>">!</em><?php endif; ?></button><?php endforeach; ?></div>
<label class="sl-field"><span class="sl-lab">What is it about? <span class="mut">(helps <?= h($d['ai']['name']) ?> write; customers do not see it)</span></span><input class="sl-in" id="snd-goal" maxlength="600" placeholder="e.g. 15% off sea freight booked before 20 December"></label>
<div id="snd-editor" class="snd-editor"></div>
<div class="snd-inapp sl-card col g10" id="snd-inapp" hidden>
<div class="row between wrap g8"><b class="fw5 row g6"><i class="ic ic-sm i-bell" aria-hidden="true"></i>In-app notification</b><span class="mut sm">Shown in the customer's bell when they open the app or website. Up to 350 characters.</span></div>
<div class="snd-inapp-g"><textarea class="sl-in snd-ta" id="snd-inapp-t" rows="3" maxlength="350" placeholder="Hi {first_name}, …"></textarea><div class="snd-bell" aria-label="Preview"><div class="snd-bell-h"><i class="ic ic-sm i-bell" aria-hidden="true"></i><b>Notifications</b></div><div class="snd-bell-i"><span class="snd-bell-d" aria-hidden="true"></span><span class="col"><span id="snd-inapp-pv" class="sm"></span><small class="mut">System · just now</small></span></div></div></div>
<div class="row g6 wrap" id="snd-inapp-tags"></div>
</div>
</section>

<section class="sl-card snd-step" id="snd-when" aria-labelledby="snd-when-h">
<div class="snd-sh"><span class="snd-n" aria-hidden="true">3</span><div class="col"><h2 class="sl-ct" id="snd-when-h">When and send</h2><span class="mut sm" id="snd-when-sub">Check who is skipped, then send now or later.</span></div></div>
<div class="row g12 wrap snd-whenrow"><span class="sl-seg boxed" id="snd-whenseg" role="group" aria-label="When"><button type="button" class="on" data-v="now">Now</button><button type="button" data-v="later">Later</button></span><input type="datetime-local" class="sl-in snd-at" id="snd-at" hidden aria-label="Send at"></div>
<div class="snd-notes col g8" id="snd-notes"></div>
<div class="snd-result" id="snd-result" role="status" aria-live="polite" hidden></div>
<div class="row g8 wrap snd-actions"><button type="button" class="sl-btn ghost" id="snd-test"><i class="ic ic-sm i-send" aria-hidden="true"></i><span>Send a test to me</span></button><?php if ($d['canCampaign']): ?><button type="button" class="sl-btn ghost" id="snd-draft" hidden><i class="ic ic-sm i-file" aria-hidden="true"></i>Save draft</button><button type="button" class="sl-btn ghost" id="snd-new" hidden><i class="ic ic-sm i-plus" aria-hidden="true"></i>New campaign</button><?php endif; ?><span class="grow"></span><button type="submit" class="sl-btn" id="snd-go"><i class="ic ic-sm i-send" aria-hidden="true"></i><span>Send</span></button></div>
</section>
</div>

<aside class="snd-side" aria-labelledby="snd-reach-h">
<section class="sl-card col g12 snd-reach">
<div class="row between g8"><h2 class="sl-ct" id="snd-reach-h">Who will get it</h2><span class="snd-spin" id="snd-spin" hidden aria-hidden="true"></span></div>
<div class="snd-big"><b id="snd-total">0</b><span class="mut sm" id="snd-total-l">people chosen</span></div>
<div class="col g6" id="snd-reach-ch"></div>
<div class="col g4" id="snd-sample"></div>
<button type="button" class="sl-btn ghost sm" id="snd-all" hidden><i class="ic ic-sm i-users" aria-hidden="true"></i>See everyone</button>
<div class="snd-cost" id="snd-cost"></div>
<p class="mut sm" id="snd-kind"></p>
</section>
</aside>
</form>

<?php if ($d['canCampaign']): ?>
<section class="sl-card col g12 snd-camps" id="campaigns" aria-labelledby="snd-camps-h">
<div class="row between wrap g8"><div class="col"><h2 class="sl-ct" id="snd-camps-h">Campaigns</h2><span class="mut sm">Drafts, waiting for approval, scheduled, sending and sent. Open a report for deliveries, opens and clicks.</span></div>
<span class="sl-seg boxed sm" id="snd-cf" role="group" aria-label="Filter campaigns"><button type="button" class="on" data-v="">All</button><button type="button" data-v="draft">Drafts</button><button type="button" data-v="pending">Waiting</button><button type="button" data-v="scheduled">Scheduled</button><button type="button" data-v="sending">Sending</button><button type="button" data-v="sent">Sent</button></span></div>
<div class="sl-tw"><table class="sl-table snd-ctab"><thead><tr><th>Campaign</th><th>Status</th><th>Channels</th><th class="num">Queued</th><th class="num">Sent</th><th class="num">Failed</th><th class="num">Opened</th><th class="num">Clicked</th><th></th></tr></thead><tbody id="snd-clist"></tbody></table></div>
<div class="sl-empty" id="snd-cempty" hidden>No campaigns yet. Choose Campaign above to write the first one.</div>
</section>
<?php endif; ?>

<div class="sl-dialog" id="snd-dlg" hidden><div class="sl-modal snd-modal col g12" aria-labelledby="snd-dlg-h">
<div class="row between g8"><h2 class="sl-ct" id="snd-dlg-h">Everyone in this audience</h2><button type="button" class="sl-ib" data-sl-close aria-label="Close"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div>
<div class="snd-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input class="sl-in" id="snd-dq" placeholder="Find someone in the list" aria-label="Find someone in the list"></div>
<div class="col g4 snd-dlist" id="snd-dlist"></div>
<div class="row between g8"><span class="mut sm" id="snd-dcount"></span><button type="button" class="sl-btn ghost sm" id="snd-dmore" hidden>Show more</button></div>
</div></div>

<script type="application/json" id="snd-boot"><?= json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<script>window.SL_EMAIL_CANVAS_CFG = <?= json_encode($d['canvas'], JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>; window.SL_KING_NAME = <?= json_encode($d['ai']['name'], JSON_HEX_TAG) ?>;</script>
<?php $ec = ['template' => true, 'tabs' => $d['canvas']['ajax'] ? ['blocks', 'tpl', 'saved', 'vars'] : ['blocks', 'tpl', 'vars']]; include CDP_ROOT . '/views/sl/inc/email_canvas.php'; ?>
<?php endif; ?>
