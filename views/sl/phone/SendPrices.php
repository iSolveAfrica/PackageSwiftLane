<?php
/**
 * M-SendPrices: send the price sheet on a phone (send_prices.php). Same $d, endpoint and field names as the
 * web page: ajax/sl/send_prices_ajax.php with users[], channels[], subject, message (max 50 customers a send).
 */
require_once CDP_ROOT . '/views/sl/data/phone_people.php';
$slPhone['nav'] = 'staff';
$first = $d['rows'][0]['name'] ?? 'Customer';
$fin = function_exists('cdp_financeEnabled') && cdp_financeEnabled();
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-people.css')) ?>">
<?= sl_phoneTop('Send prices', '', ['back' => sl_route('BillingHub')], sl_phoneIcon('message', 'Message logs', sl_route('MessageLogs'))) ?>
<form data-sl-form action="ajax/sl/send_prices_ajax.php" method="post" id="sp-form" class="pp-form">
<main class="mx-body">
<div class="mx-ban info"><i class="ic ic-sm i-sliders" aria-hidden="true"></i><span><?= $fin ? 'Finance is on. Switch it off in Settings › Modules to remove prices, bills and payments from every screen.' : 'Finance is off: prices are not part of the parcel workflow, but you can still send the price sheet.' ?></span></div>
<?php if ($d['recent']): ?><div class="mx-ban ok"><i class="ic ic-sm i-check" aria-hidden="true"></i><span><?= (int) $d['recent'] ?> price messages sent in the last 7 days.</span></div><?php endif; ?>
<section class="mx-card mx-form">
  <label class="mx-f"><small>To</small><select class="mx-in" aria-label="Customers to send to" onchange="location.href=this.value"><?php foreach ($d['quick'] as $s): ?><option value="<?= h(sl_url(['segment' => $s['f']])) ?>"<?= $s['cls'] === 'on' ? ' selected' : '' ?>><?= h($s['label']) ?> (<?= (int) $s['n'] ?>)</option><?php endforeach; ?></select></label>
  <div class="mx-f"><small id="sp-cl">Channel</small><div class="mx-fseg" role="group" aria-labelledby="sp-cl"><?php foreach ($d['chans'] as $c): ?><button type="button" class="<?= $c['on'] ? 'on' : '' ?>" aria-pressed="<?= $c['on'] ? 'true' : 'false' ?>" data-sp-ch="<?= h($c['k']) ?>"><?= h($c['label']) ?></button><input type="checkbox" name="channels[]" value="<?= h($c['k']) ?>" hidden<?= $c['on'] ? ' checked' : '' ?>><?php endforeach; ?></div></div>
  <label class="mx-f"><small>Subject</small><input class="mx-in" name="subject" value="<?= h($d['subject']) ?>" maxlength="120"></label>
  <label class="mx-f"><small>Message ([NAME] and [PRICES] are filled in)</small><textarea class="mx-in area" name="message" id="sp-msg" rows="5" maxlength="1500"><?= h($d['template']) ?></textarea></label>
</section>
<section class="mx-card" style="padding:14px;background:var(--active)"><div class="mx-msg"><p id="sp-pv" style="white-space:pre-wrap"></p><small>Preview · <span id="sp-pv-name"><?= h($first) ?></span></small></div></section>
<?php if ($d['rows']): ?>
<details class="mx-card pp-det">
  <summary><span><span id="sp-n">0</span> of <?= count($d['rows']) ?> customers ticked</span><i class="ic ic-sm i-down" aria-hidden="true"></i></summary>
  <div>
<?php foreach ($d['rows'] as $i => $r): ?>
    <label class="pp-pick"><input type="checkbox" class="sp-pick" name="users[]" value="<?= (int) $r['id'] ?>" data-name="<?= h($r['name']) ?>"<?= $i < 50 ? ' checked' : '' ?>><span class="mx-rm"><b><?= h($r['name']) ?></b><small><?= h(trim($r['locker'] . ' · ' . ($r['phone'] ?: $r['email']), ' ·')) ?></small></span></label>
<?php endforeach; ?>
  </div>
</details>
<p class="mx-note">Up to 50 customers per send. Every message is written to Message Logs.</p>
<?php else: ?>
<?= sl_mxEmpty('users', 'No customers in this group', 'Pick another group above.') ?>
<?php endif; ?>
</main>
<?php if ($d['rows']): ?><div class="mx-foot pp-foot"><div class="mx-btns"><button type="submit" class="mx-btn" id="sp-send">Send prices</button></div></div><?php endif; ?>
</form>
<script>
(function(){var f=document.getElementById('sp-form'),sheet=<?= json_encode($d['sheet']) ?>,first=<?= json_encode($first) ?>;if(!f)return;
function pv(){var n=f.querySelectorAll('.sp-pick:checked'),nm=n.length?n[0].dataset.name:first,c=document.getElementById('sp-n'),b=document.getElementById('sp-send');if(c)c.textContent=n.length;if(b){b.textContent='Send to '+n.length+' customer'+(n.length===1?'':'s');b.disabled=!n.length||n.length>50;}
document.getElementById('sp-pv-name').textContent=nm;document.getElementById('sp-pv').textContent=document.getElementById('sp-msg').value.replace(/\[NAME\]/g,nm).replace(/\[PRICES\]/g,sheet);}
f.addEventListener('click',function(e){var b=e.target.closest('[data-sp-ch]');if(!b)return;var on=!b.classList.contains('on');b.classList.toggle('on',on);b.setAttribute('aria-pressed',String(on));b.nextElementSibling.checked=on;});
f.addEventListener('change',pv);f.addEventListener('input',pv);pv();})();
</script>
