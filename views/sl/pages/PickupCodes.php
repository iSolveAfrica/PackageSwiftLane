<a class="sl-btn ghost" href="<?= h(sl_route('DeliveryRequest')) ?>"><i class="ic i-truck" aria-hidden="true"></i>Request delivery / My deliveries</a>
<div class="sl-head"><div><h1 class="sl-title">Your pickup codes</h1><p class="sl-lede">Show your code to the team when you collect your parcels.</p></div></div>
<div class="sl-banner info"><i class="ic i-shield" aria-hidden="true"></i><span>Keep these codes private. Share only with the person handing over your parcel. Bring your ID.</span></div>
<div class="grid cols2 sl-code-wallet">
<?php foreach ($d['codes'] as $code): ?>
<section class="sl-card col g16"><div class="row between wrap g8"><h2 class="sl-ct"><?= $code->module === 'sea' ? 'Sea packages' : 'Air shipments' ?></h2><span class="sl-pill delivered">Ready to show</span></div>
<p class="mono sl-pickup-number" aria-label="Pickup code <?= h(implode(' ', str_split($code->code))) ?>"><?= h($code->code) ?></p>
<p class="sm mut">For <span class="mono"><?= h($code->parcels) ?></span></p>
<div class="row between wrap g8"><span class="sm mut">Valid until <?= h(sl_date($code->expires_at, 'M j, Y')) ?></span><button type="button" class="sl-btn ghost" data-sl-copy="<?= h($code->code) ?>"><i class="ic i-copy" aria-hidden="true"></i>Copy code</button></div></section>
<?php endforeach; ?>
</div>
<?php if (!$d['codes']): ?><section class="sl-card sl-empty"><span class="sl-tile"><i class="ic i-key" aria-hidden="true"></i></span><h2 class="sl-ct">No active pickup codes</h2><p>Your code appears here once your parcel is ready. Collected or expired codes are removed automatically.</p><a class="sl-btn" href="<?= h(sl_route('PublicTracking')) ?>">Track a parcel</a></section><?php endif; ?>
<a class="sl-btn ghost" href="support.php?q=I%20need%20help%20with%20my%20pickup%20code">Need help with a code?</a>
