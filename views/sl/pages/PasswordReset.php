<?php
/**
 * PasswordReset - from docs/handover/artifact-source/PasswordReset.dc.html (scripts/sl_convert.py), bound to forgot-reset.php.
 * Shell page: no (plain page)
 * Data contract ($d): mode (forgot|rotate), done, error, expired, age{days,period,left,changed,next}|null, period, nextDue, signedOut, continue, back, help, pics, site
 */
if (!isset($d)) { $d = []; }
$pics = sl_get($d, 'pics', []);
$age = sl_get($d, 'age');
$done = (bool) sl_get($d, 'done');
$rules = [['len', 'At least 8 characters'], ['num', 'A number'], ['sym', 'A symbol such as ! or #'], ['case', 'Upper and lower case letters']];
?>
<div class="sl-auth fp-full">
  <div class="form">
    <div>
      <div class="sl-brand"><span class="sl-logo"><i></i></span><?= sl_v($d, 'site') ?></div>
      <div class="col g8" style="margin-top: 12px">
        <div class="sl-steps" aria-label="Step <?= $done ? 3 : 2 ?> of 3"><i class="on"></i><i class="on"></i><i class="<?= $done ? 'on' : '' ?>"></i></div>
        <div class="row between mut sm"><span>Verify it’s you</span><span class="<?= $done ? '' : 'fw5' ?>" <?= $done ? '' : 'style="color: var(--ink)"' ?>>New password</span><span class="<?= $done ? 'fw5' : '' ?>" <?= $done ? 'style="color: var(--ink)"' : '' ?>>Done</span></div>
      </div>
      <?php if (!$done): ?>
      <div class="col g6"><h1 style="font-size: 28px; font-weight: 500; letter-spacing: -.015em;margin:0">Choose a new password</h1><p class="mut" style="margin:0"><?php if ($age): ?>Passwords reset every <?= (int) $age['period'] ?> days. <?= $age['expired'] ? 'Yours has expired, so change it before you continue.' : 'Yours expires in ' . max(0, (int) $age['left']) . ' days.' ?><?php else: ?>Use a password you do not use anywhere else.<?php endif; ?></p></div>
      <?php if (sl_get($d, 'error')): ?><div class="sl-banner bad" role="alert"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span><?= sl_v($d, 'error') ?></span></div><?php endif; ?>
      <?php if ($age): $pct = sl_pct(min($age['days'], $age['period']), $age['period']); ?>
      <div class="sl-card col g8" style="padding:12px 14px"><div class="row between"><span class="fw5 sm"><i class="ic ic-sm i-lock" aria-hidden="true"></i> Password age</span><span class="sl-pill <?= $age['expired'] ? 'overdue' : ($age['left'] <= 7 ? 'pending' : 'delivered') ?>"><?= $age['expired'] ? 'Expired' : 'Expires in ' . max(0, (int) $age['left']) . ' days' ?></span></div><div class="sl-ticks <?= $age['expired'] || $age['left'] <= 7 ? 'r' : '' ?>"><i style="width: <?= $pct ?>%"></i></div><div class="row between mut sm"><span>Last changed <?= h(sl_date($age['changed'])) ?></span><span><?= min((int) $age['days'], (int) $age['period']) ?> of <?= (int) $age['period'] ?> days</span></div></div>
      <?php endif; ?>
      <form method="post" action="forgot-reset.php" class="col g16" id="reset-form" autocomplete="off">
        <input type="hidden" name="<?= h(cdp_csrf_param()) ?>" value="<?= h(cdp_csrf_token()) ?>">
        <label class="sl-field"><span class="sl-lab">New password</span><div class="sl-inwrap"><i class="ic ic-sm i-lock" aria-hidden="true"></i><input id="password" name="password" class="sl-in" type="password" autocomplete="new-password" minlength="8" required placeholder="At least 8 characters" data-sl-pw="#pw-meter" data-sl-pw-msg="#pw-msg" data-sl-pw-confirm="#password_confirm" data-sl-pw-match="#pw-match" data-sl-pw-submit="#pw-submit"><span role="button" tabindex="0" class="sl-ib" style="width:30px;height:30px;cursor:pointer" aria-label="Show password" data-sl-peek="#password"><i class="ic ic-sm i-eye" aria-hidden="true"></i></span></div></label>
        <div class="pr-meter" id="pw-meter" aria-hidden="true"><i></i><i></i><i></i><i></i></div><span class="sm mut" id="pw-msg"></span>
        <label class="sl-field"><span class="sl-lab">Confirm password</span><div class="sl-inwrap"><i class="ic ic-sm i-lock" aria-hidden="true"></i><input id="password_confirm" name="password_confirm" class="sl-in" type="password" autocomplete="new-password" minlength="8" required placeholder="Repeat the password"></div><span class="sm" id="pw-match"></span></label>
        <div class="col g6 sm"><?php foreach ($rules as $r): ?><span class="row g8" data-sl-rule="<?= h($r[0]) ?>"><i class="ic ic-sm i-x" aria-hidden="true"></i><?= h($r[1]) ?></span><?php endforeach; ?></div>
        <?php if (sl_get($d, 'mode') === 'rotate'): ?><label class="row g10 sm" style="cursor:pointer"><span class="sl-toggle on" role="switch" aria-checked="true" aria-label="Sign out other devices"><input type="hidden" name="sign_out_others" value="1"></span><span>Sign out of all other devices</span></label><?php else: ?><input type="hidden" name="sign_out_others" value="1"><p class="mut sm" style="margin:0">All devices that were trusted for this account will be signed out.</p><?php endif; ?>
        <button class="sl-btn block" type="submit" id="pw-submit" style="height:46px;opacity:.6">Update password</button>
      </form>
      <?php if (sl_get($d, 'period') > 0): ?><p class="mut sm" style="margin:0">You’re reminded 7 days before and on the day it expires. Expired passwords must be reset before you can use <?= sl_v($d, 'site') ?>.</p><?php endif; ?>
      <?php else: ?>
      <div class="col g12" style="align-items:center;text-align:center;padding:20px 0"><span class="sl-tile" style="width:64px;height:64px;border-radius:20px;background:var(--green-bg);color:var(--green-ink)"><i class="ic i-check" aria-hidden="true"></i></span><h1 style="font-size:26px;font-weight:500;margin:0">Password updated</h1><p class="mut" style="margin:0"><?= sl_get($d, 'nextDue') ? 'Next reset due ' . sl_v($d, 'nextDue') . '. ' : '' ?><?= sl_get($d, 'signedOut') ? 'Other devices were signed out.' : '' ?></p><a class="sl-btn block" href="<?= sl_v($d, 'continue') ?>" style="height:46px">Continue to <?= sl_v($d, 'site') ?></a></div>
      <?php endif; ?>
      <div class="row between sm"><a href="<?= sl_v($d, 'back') ?>" class="row g6 fw5"><i class="ic ic-sm i-left" aria-hidden="true"></i>Back</a><a href="<?= sl_v($d, 'help') ?>" class="mut">Need help?</a></div>
    </div>
  </div>
  <div class="art fs" aria-label="Pictures and tips" style="<?= h($pics['style'] ?? '') ?>" data-sl-slides="<?= (int) ($pics['n'] ?? 0) ?>" data-sl-every="<?= (int) ($pics['every'] ?? 6) ?>"><div aria-hidden="true" style="position:absolute;inset:0"><?= $pics['bgs'] ?? '' ?></div><div class="fs-dim" aria-hidden="true"></div>
<div class="fs-top"><span class="up" style="color:rgba(255,255,255,.75)">Password reset</span><a href="<?= sl_route('PublicTracking') ?>" style="color:#fff">Track a package →</a></div>
<div class="fs-cap"><div class="fs-caps"><div><span class="fs-ic"><i class="ic i-mail" aria-hidden="true"></i></span><span class="up" style="color:rgba(255,255,255,.7)">Step 1</span><h2>Enter the code we sent you</h2><span style="opacity:.8;max-width:460px">One-time codes are single use and expire in 5 minutes.</span></div><div><span class="fs-ic"><i class="ic i-key" aria-hidden="true"></i></span><span class="up" style="color:rgba(255,255,255,.7)">Step 2</span><h2>Choose a new password</h2><span style="opacity:.8;max-width:460px">At least 8 characters with a number and a symbol.</span></div><div><span class="fs-ic"><i class="ic i-shield" aria-hidden="true"></i></span><span class="up" style="color:rgba(255,255,255,.7)">Stay safe</span><h2><?= sl_v($d, 'site') ?> never asks for your code</h2><span style="opacity:.8;max-width:460px">Do not share it with anyone, even our staff.</span></div></div><div class="fs-dots"><i></i><i></i><i></i></div></div></div>
</div>
