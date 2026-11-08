<?php
/**
 * Send prices - from docs/handover/artifact-source/SendPrices.dc.html.
 * Data: views/sl/data/send_prices.php. Write: ajax/sl/send_prices_ajax.php (cdp_pushNotifyUser / cdp_sendSms, logged to cdb_message_log).
 */
if (!isset($d)) { $d = []; }
$first = $d['rows'][0]['name'] ?? 'Customer';
?>
<div class="row g8 mut sm"><a href="<?= sl_route('Payments') ?>">Payments</a><i class="ic ic-sm i-right" aria-hidden="true"></i><span style="color:var(--ink)">Send prices</span></div>
<div class="sl-head"><div><h1 class="sl-title">Send prices</h1><p class="sl-lede">Pick who should get the current price sheet, choose SMS, WhatsApp or e-mail, and send in one go</p></div><div class="row g8"></div></div>
<?php if ($d['recent']): ?><div class="sp-ok" role="status"><i class="ic ic-sm i-check" aria-hidden="true"></i><?= (int) $d['recent'] ?> price messages sent in the last 7 days. <a href="<?= sl_route('MessageLogs') ?>">See Message Logs</a></div><?php endif; ?>
<form data-sl-form action="ajax/sl/send_prices_ajax.php" id="sp-form" class="split">
<section class="a sl-card flush"><div class="row between wrap g8" style="padding:14px 16px"><div><h2 class="sl-ct">Customers · <?= h($d['segLabel']) ?> · <?= count($d['rows']) ?></h2><div class="mut sm">Tick who to send to. Up to 300 are listed; narrow with the search.</div></div><button type="button" class="sl-btn ghost sm" id="sp-all">Select all</button></div>
<div class="row wrap g8 sm" style="padding:0 16px 12px"><span class="mut">Segments:</span><?php foreach ($d['quick'] as $s): ?><a class="chipbtn <?= h($s['cls']) ?>" href="<?= h(sl_url(['segment' => $s['f']])) ?>"><?= h($s['label']) ?> · <?= (int) $s['n'] ?></a><?php endforeach; ?></div>
<div style="padding:0 16px 12px"><div class="sl-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input form="sp-search" name="q" value="<?= h($d['q']) ?>" aria-label="Search customers" placeholder="Search name, locker, phone or e-mail"></div></div>
<div class="sl-tw"><table class="sl-table"><thead><tr><th style="width:36px"></th><th>Customer</th><th>Phone</th><th>E-mail</th><th class="num">Parcels</th></tr></thead><tbody>
<?php foreach ($d['rows'] as $r): ?><tr><td><input type="checkbox" class="sp-pick" name="users[]" value="<?= (int) $r['id'] ?>" data-name="<?= h($r['name']) ?>" aria-label="Select <?= h($r['name']) ?>"></td><td><a class="id" href="<?= sl_route('CustomerProfile', ['id' => $r['id']]) ?>"><?= h($r['name']) ?></a><span class="sub"><?= h($r['locker']) ?></span></td><td class="sm"><?= h($r['phone'] ?: '—') ?></td><td class="sm"><?= h($r['email'] ?: '—') ?></td><td class="num"><?= (int) $r['n'] ?></td></tr><?php endforeach; ?>
<?php if (!$d['rows']): ?><tr><td colspan="5"><div class="sl-empty">No customers in this segment.</div></td></tr><?php endif; ?></tbody></table></div></section>
<aside class="b col g12"><section class="sl-card col g10"><h2 class="sl-ct">Send by</h2><?php foreach ($d['chans'] as $c): ?><label class="sp-ch <?= $c['on'] ? 'on' : '' ?>"><span class="sl-tile"><i class="ic <?= h($c['icon']) ?>" aria-hidden="true"></i></span><span class="col grow"><b class="fw5"><?= h($c['label']) ?></b><span class="mut sm"><?= h($c['cost']) ?></span></span><input type="checkbox" name="channels[]" value="<?= h($c['k']) ?>" <?= $c['on'] ? 'checked' : '' ?>></label><?php endforeach; ?></section>
<section class="sl-card col g8"><label class="sl-field"><span class="sl-lab">Subject</span><input class="sl-in" name="subject" value="<?= h($d['subject']) ?>" maxlength="120"></label><label class="sl-field"><span class="sl-lab">Message ([NAME] and [PRICES] are filled in)</span><textarea class="sl-in" name="message" id="sp-msg" rows="6" maxlength="1500"><?= h($d['template']) ?></textarea></label></section>
<section class="col g6"><span class="up">Preview · <span id="sp-pv-name"><?= h($first) ?></span></span><div class="sp-ph"><div class="sc"><div class="bb" id="sp-pv" style="white-space:pre-wrap"></div></div></div></section>
<section class="sl-card col g8"><div class="sl-kv"><div><span>Customers</span><span id="sp-n">0</span></div><div><span>Bands in the sheet</span><span><?= substr_count($d['sheet'], "\n") + 1 ?></span></div></div><button class="sl-btn block" style="height:46px" type="submit"><i class="ic ic-sm i-send" aria-hidden="true"></i>Send prices</button><a class="mut sm" href="<?= sl_route('MessageLogs') ?>" style="text-align:center">Every message is written to Message Logs →</a></section></aside>
</form>
<form id="sp-search" method="get"><input type="hidden" name="segment" value="<?= h($d['seg']) ?>"></form>
<script>
(function(){var f=document.getElementById('sp-form'),sheet=<?= json_encode($d['sheet']) ?>,first=<?= json_encode($first) ?>;
function pv(){var n=f.querySelectorAll('.sp-pick:checked'),nm=n.length?n[0].dataset.name:first;document.getElementById('sp-n').textContent=n.length;document.getElementById('sp-pv-name').textContent=nm;document.getElementById('sp-pv').textContent=document.getElementById('sp-msg').value.replace(/\[NAME\]/g,nm).replace(/\[PRICES\]/g,sheet);
f.querySelectorAll('.sp-ch').forEach(function(l){l.classList.toggle('on',l.querySelector('input').checked);});}
f.addEventListener('change',pv);f.addEventListener('input',pv);document.getElementById('sp-all').addEventListener('click',function(){var all=f.querySelectorAll('.sp-pick'),on=[].some.call(all,function(c){return !c.checked;});all.forEach(function(c){c.checked=on;});this.textContent=on?'Clear all':'Select all';pv();});pv();})();
</script>
