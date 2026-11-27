<?php
/** M-DevAlerts: alerts by status, one alert's actions, e-mail notification switches. Data: sl_dinfAlertsData (same as the web DevAlerts). Writes: ajax/sl/dev_infra_ajax.php (existing ops). */
require_once CDP_ROOT . '/views/sl/data/dev_phone.php';
$slPhone['nav'] = 'dev';
$slPhone['tab'] = 'Alerts';
$slPhone['scripts'][] = 'dataJs/sl/phone/dev.js';
$me = sl_phoneMe();
$ajax = (string) ($d['ajax'] ?? 'ajax/sl/dev_infra_ajax.php');
$ns = $d['notify'] ?? ['on' => 0, 'builtin' => 0, 'min' => 'warning', 'extra' => []];
$sevPill = ['critical' => ['overdue', 'High'], 'warning' => ['pending', 'Medium'], 'info' => ['plain', 'Low']];
$sel = $d['sel'] ?? null;
$tabKeys = ['open', 'resolved', 'all'];
?>
<?= sl_phoneTop('Alerts', (int) ($d['openN'] ?? 0) . ' open', ['avatar' => $me['ini'], 'href' => sl_route('Workspace'), 'online' => true], sl_phoneIcon('search', 'Search', sl_route('LockerSearch')) . sl_phoneIcon('bell', 'Notifications', sl_route('Notifications'))) ?>
<main class="mx-body">
<div class="mx-seg" role="group" aria-label="Status"><form method="get" style="display:contents"><?php if (($d['sev'] ?? '') !== ''): ?><input type="hidden" name="sev" value="<?= h($d['sev']) ?>"><?php endif; ?>
<?php foreach ($d['tabs'] ?? [] as $i => $t): ?><button type="submit" name="tab" value="<?= h($tabKeys[$i] ?? 'open') ?>" class="<?= h($t['cls']) ?>" aria-pressed="<?= $t['cls'] ? 'true' : 'false' ?>"><?= h($t['label']) ?> <?= (int) $t['n'] ?></button><?php endforeach; ?>
</form></div>
<?php if (!empty($d['selOutside'])): ?><div class="mx-ban info"><i class="ic ic-sm i-info" aria-hidden="true"></i><span>That alert is not in this filter. <a href="<?= h($d['selOutside']) ?>">Show all alerts</a></span></div><?php endif; ?>
<?php if ($sel): $sp = $sevPill[$sel['sev']] ?? ['plain', ucfirst($sel['sev'])]; ?>
<section class="mx-card mx-kv" aria-label="<?= h($sel['t']) ?>">
<div><span><b style="color:var(--ink);font-weight:600"><?= h($sel['t']) ?></b></span><b><span class="sl-pill <?= h($sp[0]) ?>"><?= h($sp[1]) ?></span></b></div>
<div><span>Status</span><b><?= h($sel['stLabel']) ?></b></div>
<div><span>Service</span><b><?= h($sel['svc']) ?></b></div>
<?php if ($sel['value'] !== ''): ?><div><span>Value</span><b><?= h($sel['value'] . ($sel['limit'] ? ' · limit ' . $sel['limit'] : '')) ?></b></div><?php endif; ?>
<div><span>Detail</span><b><?= h($sel['detail'] ?: '—') ?></b></div>
<div><span>Triggered</span><b><?= h($sel['first'] . ' · ' . $sel['firstAgo']) ?></b></div>
<div><span>Duration</span><b><?= h($sel['dur']) ?></b></div>
<div><span>Source</span><b><?= h($sel['source']) ?></b></div>
<?php if ($sel['ack']): ?><div><span>Owner</span><b><?= h($sel['ack']) ?></b></div><?php endif; ?>
<?php if ($sel['mail']): ?><div><span>E-mail</span><b><?= h($sel['mail']) ?></b></div><?php endif; ?>
</section>
<div class="mx-btns">
<?php if ($sel['st'] === 'active'): ?><button type="button" class="mx-btn" data-sl-action="<?= h($ajax) ?>" data-p-op="alert_ack" data-p-id="<?= (int) $sel['id'] ?>">Acknowledge: I own it</button><?php endif; ?>
<?php if ($sel['st'] !== 'resolved'): ?><button type="button" class="mx-btn sec" data-sl-action="<?= h($ajax) ?>" data-p-op="alert_resolve" data-p-id="<?= (int) $sel['id'] ?>">Resolve</button><?php else: ?><button type="button" class="mx-btn sec" data-sl-action="<?= h($ajax) ?>" data-p-op="alert_reopen" data-p-id="<?= (int) $sel['id'] ?>">Reopen</button><?php endif; ?>
<a class="mx-btn sec" href="<?= h(sl_url(['alert' => null])) ?>">Close</a>
</div>
<?php endif; ?>
<section class="mx-card mx-list">
<?php foreach (array_slice($d['alerts'] ?? [], 0, 60) as $a): $sp = $sevPill[$a['sev']] ?? ['plain', ucfirst($a['sev'])]; ?>
<a class="mx-row" href="<?= h(sl_url(['alert' => $a['id']])) ?>"<?= $a['sel'] ? ' aria-current="true"' : '' ?>><span class="mx-li"><i class="ic i-<?= $a['st'] === 'resolved' ? 'check' : 'alert' ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($a['t']) ?></b><small><?= h($a['svc'] . ' · ' . ($a['st'] === 'resolved' ? 'resolved · ' . $a['dur'] : $a['firstAgo'] . ($a['st'] === 'acknowledged' ? ' · acknowledged' : ''))) ?></small></span><span class="mx-rr"><span class="sl-pill <?= h($a['st'] === 'resolved' ? 'delivered' : $sp[0]) ?>"><?= h($a['st'] === 'resolved' ? 'Resolved' : $sp[1]) ?></span></span></a>
<?php endforeach; ?>
<?php if (empty($d['alerts'])): ?><?= sl_mxEmpty('check', ($d['tab'] ?? 'open') === 'open' && ($d['sev'] ?? '') === '' && ($d['q'] ?? '') === '' ? 'No open alerts' : 'No alert matches this filter', ($d['tab'] ?? 'open') === 'open' ? 'Everything watched is within its limits.' : '') ?><?php endif; ?>
</section>
<form id="ph-al-notify" action="<?= h($ajax) ?>" method="post" hidden>
<input type="hidden" name="op" value="notify_save">
<input type="hidden" name="on" id="ph-al-on" value="<?= !empty($ns['on']) ? '1' : '0' ?>">
<input type="hidden" name="builtin" id="ph-al-builtin" value="<?= !empty($ns['builtin']) ? '1' : '0' ?>">
<input type="hidden" name="min" value="<?= h((string) $ns['min']) ?>">
<input type="hidden" name="extra" value="<?= h(implode(', ', (array) $ns['extra'])) ?>">
</form>
<section class="mx-card mx-tgs">
<div class="mx-tg"><span class="mx-rm"><b>Alert on email</b><small><?= count($d['recipients'] ?? []) ? h(implode(', ', array_slice(array_column($d['recipients'], 'name'), 0, 3))) . (count($d['recipients']) > 3 ? ' +' . (count($d['recipients']) - 3) : '') : 'Nobody: no developer has an e-mail address' ?></small></span><button type="button" class="mx-sw<?= !empty($ns['on']) ? ' on' : '' ?>" role="switch" aria-checked="<?= !empty($ns['on']) ? 'true' : 'false' ?>" aria-label="Alert on email" data-input="#ph-al-on" data-ph-form="ph-al-notify"></button></div>
<div class="mx-tg"><span class="mx-rm"><b>Include built-in checks</b><small>Web, API, database, e-mail, WhatsApp</small></span><button type="button" class="mx-sw<?= !empty($ns['builtin']) ? ' on' : '' ?>" role="switch" aria-checked="<?= !empty($ns['builtin']) ? 'true' : 'false' ?>" aria-label="Include built-in checks" data-input="#ph-al-builtin" data-ph-form="ph-al-notify"></button></div>
</section>
<div class="mx-btns"><button type="button" class="mx-btn sec" data-sl-action="<?= h($ajax) ?>" data-p-op="evaluate">Evaluate rules now</button></div>
</main>
