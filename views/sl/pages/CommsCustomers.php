<?php
/**
 * CommsCustomers - Customer history: everything said to and heard from one
 * customer (every Outbox row, survey answers, preference changes) and their
 * message preferences and marketing consent.
 * Data: views/sl/data/comms_outbox.php (sl_commsCustomersData).
 * Scripts: dataJs/sl/comms_outbox.js (message drawer), dataJs/sl/comms_customers.js.
 * Endpoint: ajax/comms/outbox_ajax.php.
 */
if (!isset($d)) { $d = []; }
$commsTab = 'commscustomers';
include __DIR__ . '/partials/comms_tabs.php';
$c = $d['customer'];
$p = $d['prefs'];
$chIcon = ['whatsapp' => 'i-wa', 'sms' => 'i-message', 'email' => 'i-mail', 'survey' => 'i-star', 'preferences' => 'i-sliders', '' => 'i-send'];
$boot = ['ajax' => $d['ajax'], 'open' => preg_match('/^(log|queue|cs|dlv|in)-\d+$/', (string) ($_GET['open'] ?? '')) ? (string) $_GET['open'] : '',
    'ai' => ['ok' => !empty($d['ai']['ok']), 'code' => (string) ($d['ai']['code'] ?? ''), 'message' => (string) ($d['ai']['message'] ?? ''),
        'setup_url' => (string) ($d['ai']['setup_url'] ?? ''), 'name' => (string) ($d['ai']['assistant'] ?? 'King')],
    'canAct' => $d['canAct'], 'outboxUrl' => $d['outboxUrl'], 'self' => sl_route('CommsCustomers'), 'uid' => $c ? $c['id'] : 0];
?>
<div class="sl-head"><div><h1 class="sl-title">Customer history</h1><p class="sl-lede">Every message one customer got from us or sent us, and what they agreed to receive.</p></div>
<div class="row g8 wrap"><a class="sl-btn ghost" href="<?= h($d['outboxUrl']) ?>"><i class="ic ic-sm i-inbox" aria-hidden="true"></i>Outbox</a></div></div>

<?php if (!$d['ready']): ?>
<div class="sl-banner bad"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>The communication tables could not be created. The database user needs the CREATE privilege once.</span></div>
<?php else: ?>

<div class="hs-find sl-card">
<label class="sl-lab" for="hs-q">Find a customer</label>
<div class="hs-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input class="sl-in" id="hs-q" autocomplete="off" placeholder="Name, locker, phone or e-mail" role="combobox" aria-expanded="false" aria-controls="hs-sug" aria-autocomplete="list"></div>
<div class="hs-sug" id="hs-sug" role="listbox" aria-label="Customers" hidden></div>
</div>

<?php if (!empty($d['missing'])): ?>
<div class="sl-banner warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>That customer was not found. Search for another one above.</span></div>
<?php endif; ?>

<?php if (!$c): ?>
<section class="sl-card" aria-labelledby="hs-recent-h">
<h2 class="sl-ct" id="hs-recent-h">Recently messaged</h2>
<?php if ($d['recent']): ?>
<ul class="hs-recent">
<?php foreach ($d['recent'] as $r): ?>
<li><a href="<?= h(sl_route('CommsCustomers', ['user' => $r['id']])) ?>"><span class="sl-av sm"><?= h(sl_initials($r['name'] ?: '?')) ?></span><span class="col"><span class="fw5"><?= h($r['name'] ?: 'Customer #' . $r['id']) ?></span><span class="sub"><?= h(implode(' · ', array_filter([$r['locker'], $r['phone'] ?: $r['email']]))) ?></span></span><span class="hs-rn"><span class="mono sm"><?= (int) $r['n'] ?></span><span class="sub"><?= h($r['last']) ?></span></span></a></li>
<?php endforeach; ?>
</ul>
<?php else: ?>
<div class="sl-empty"><span class="sl-tile"><i class="ic i-user" aria-hidden="true"></i></span>No customer has been messaged in the last four months. Search for a customer above.</div>
<?php endif; ?>
</section>
<?php else: ?>

<div class="hs-grid">
<aside class="col g16 hs-side">
<section class="sl-card hs-who" data-hs-uid="<?= (int) $c['id'] ?>" aria-labelledby="hs-name">
<div class="row g12"><span class="sl-av lg"><?= h($c['ini']) ?></span><div class="col"><h2 class="hs-name" id="hs-name"><?= h($c['name']) ?></h2><span class="mut sm"><?= h(implode(' · ', array_filter([$c['locker']]))) ?></span></div></div>
<dl class="hs-contact"><?php if ($c['phone'] !== ''): ?><div><dt>Phone</dt><dd class="mono"><?= h($c['phone']) ?></dd></div><?php endif; ?><?php if ($c['email'] !== ''): ?><div><dt>E-mail</dt><dd><?= h($c['email']) ?></dd></div><?php endif; ?></dl>
<div class="row g8 wrap"><a class="sl-btn sm" href="<?= h($c['send']) ?>"><i class="ic ic-sm i-send" aria-hidden="true"></i>Send a message</a><a class="sl-btn ghost sm" href="<?= h($c['outbox']) ?>"><i class="ic ic-sm i-inbox" aria-hidden="true"></i>In the Outbox</a><a class="sl-btn ghost sm" href="<?= h($c['profile']) ?>"><i class="ic ic-sm i-user" aria-hidden="true"></i>Profile</a></div>
<div class="hs-counts"><span title="Accepted by the provider"><b><?= (int) $d['counts']['sent'] ?></b> sent</span><span><b><?= (int) $d['counts']['in'] ?></b> received</span><span class="<?= $d['counts']['problems'] ? 'bad' : '' ?>" title="Failed, not sent or cancelled (the Problems filter)"><b><?= (int) $d['counts']['problems'] ?></b> problems</span><span><b><?= (int) $d['counts']['waiting'] ?></b> waiting</span></div>
</section>

<form class="sl-card hs-prefs" id="hs-prefs" aria-labelledby="hs-prefs-h">
<h2 class="sl-ct" id="hs-prefs-h">What they receive</h2>
<input type="hidden" name="user_id" value="<?= (int) $c['id'] ?>">
<?php
$sw = function ($k, $label, $hint = '') use ($p, $d) {
    $on = !empty($p[$k]);
    return '<div class="hs-sw"><span class="col"><span>' . h($label) . '</span>' . ($hint !== '' ? '<span class="sub">' . h($hint) . '</span>' : '') . '</span>'
        . '<span class="sl-toggle' . ($on ? ' on' : '') . '" role="switch" aria-checked="' . ($on ? 'true' : 'false') . '" aria-label="' . h($label) . '"' . ($d['canAct'] ? '' : ' data-static') . '><input type="hidden" name="' . h($k) . '" value="' . ($on ? '1' : '0') . '"></span></div>';
};
?>
<fieldset class="hs-fs"><legend class="sl-lab">Shipment updates</legend><?= $sw('whatsapp', 'WhatsApp') ?><?= $sw('sms', 'SMS') ?><?= $sw('email', 'E-mail') ?></fieldset>
<fieldset class="hs-fs"><legend class="sl-lab">Offers and campaigns (consent)</legend><?= $sw('mkt_whatsapp', 'WhatsApp') ?><?= $sw('mkt_sms', 'SMS') ?><?= $sw('mkt_email', 'E-mail') ?></fieldset>
<fieldset class="hs-fs"><legend class="sl-lab">Everything</legend><?= $sw('opted_out_all', 'Opted out of all messages', 'Switching this on cancels their waiting status updates, Support desk notices and delivery notices.') ?></fieldset>
<p class="mut sm"><?= !empty($p['consent_at']) ? 'Marketing consent recorded ' . h(sl_date($p['consent_at'], 'M j, Y H:i')) . ' (' . h(str_replace(['staff:', '_'], ['staff, ', ' '], (string) $p['consent_source'])) . ').' : 'No marketing consent recorded.' ?><?= empty($p['stored']) ? ' These are the defaults: the customer never changed them.' : '' ?></p>
<?php if ($d['canAct']): ?>
<label class="sl-field"><span class="sl-lab">How did the customer tell you?</span><span class="sl-sel native"><select name="source"><option value="phone">On the phone</option><option value="in_person">In person</option><option value="written">In writing</option><option value="chat">In a chat</option></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
<button class="sl-btn" type="submit">Save preferences</button>
<?php endif; ?>
</form>
</aside>

<section class="sl-card flush hs-tl" aria-labelledby="hs-tl-h">
<div class="hs-tlh"><h2 class="sl-ct" id="hs-tl-h">History</h2>
<div class="sl-seg boxed sm" id="hs-filter" role="group" aria-label="Show"><button type="button" class="on" data-hs-f="">All</button><button type="button" data-hs-f="out">Sent</button><button type="button" data-hs-f="in">Received</button><button type="button" data-hs-f="failed">Problems</button><button type="button" data-hs-f="note">Preferences</button></div></div>
<?php if (!empty($d['error'])): ?><div class="sl-banner bad"><span><?= h($d['error']) ?></span></div><?php endif; ?>
<ol class="hs-items">
<?php foreach ($d['items'] as $it): $prob = in_array($it['status'], ['failed', 'skipped', 'cancelled'], true); ?>
<li class="hs-it <?= h($it['dir']) ?>" data-hs-dir="<?= h($it['dir']) ?>" data-hs-prob="<?= $prob ? '1' : '0' ?>"<?php if ($it['ref'] !== ''): ?> data-ob-ref="<?= h($it['ref']) ?>" tabindex="0" role="button" aria-label="<?= h('Details: ' . $it['what'] . ', ' . $it['when']) ?>"<?php endif; ?>>
<span class="hs-ic <?= h($it['channel'] ?: 'all') ?>" aria-hidden="true"><i class="ic ic-sm <?= h($chIcon[$it['channel']] ?? 'i-send') ?>"></i></span>
<div class="hs-ib">
<div class="hs-top"><span class="fw5"><?= h($it['what']) ?></span><span class="hs-dir"><?= $it['dir'] === 'in' ? 'Received' : ($it['dir'] === 'note' ? 'Note' : 'Sent') ?> · <?= h($it['channel_label'] ?? $it['channel']) ?></span><?php if ($it['status_label'] !== ''): ?><span class="sl-pill <?= h($it['cls']) ?>"><?= h($it['status_label']) ?></span><?php endif; ?><time class="mono sm mut" datetime="<?= h($it['at']) ?>" title="<?= h($it['ago'] ?? '') ?>"><?= h($it['when']) ?></time></div>
<?php if ($it['subject'] !== ''): ?><div class="hs-subj"><?= h($it['subject']) ?></div><?php endif; ?>
<?php if ($it['body'] !== ''): ?><p class="hs-body"><?= h($it['body']) ?></p><?php endif; ?>
<?php if ($it['detail'] !== '' || $it['by'] !== '' || ($it['opens'] ?? null) !== null): ?><div class="hs-meta"><?php if ($it['detail'] !== ''): ?><span class="<?= $it['status'] === 'failed' ? 'bad' : '' ?>"><?= h($it['detail']) ?></span><?php endif; ?><?php if ($it['by'] !== ''): ?><span>by <?= h($it['by']) ?></span><?php endif; ?><?php if (($it['opens'] ?? null) !== null): ?><span><?= (int) $it['opens'] ?> opens · <?= (int) $it['clicks'] ?> clicks</span><?php endif; ?></div><?php endif; ?>
</div>
</li>
<?php endforeach; ?>
</ol>
<div class="sl-empty" data-hs-empty<?= $d['items'] ? ' hidden' : '' ?>><span class="sl-tile"><i class="ic i-inbox" aria-hidden="true"></i></span><?= $d['items'] ? 'Nothing of this kind.' : 'Nothing sent to or received from this customer yet.' ?></div>
</section>
</div>
<?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/partials/comms_outbox_drawer.php'; ?>
<script>window.SL_HISTORY = <?= json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
