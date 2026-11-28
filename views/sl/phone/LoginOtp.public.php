<?php
/**
 * M-Otp (auth-otp.php?flow=login|signup, view LoginOtp), signed out: the 6-digit code after the password
 * or after sign-up. Posts otp_code / resend to auth-otp.php like the web page.
 */
$slPhone['nav'] = 'none';
$slPhone['cls'] = 'mx-bare';
$slPhone['scripts'][] = 'dataJs/sl/auth.js';
$flow = (string) ($d['flow'] ?? 'login');
$signup = $flow === 'signup';
$age = $d['age'] ?? null;
$cdEnd = (int) ($d['countdownEnd'] ?? 0);
$expires = $cdEnd > 0 ? $cdEnd - 60 + 300 : 0;
$csrf = '<input type="hidden" name="' . h(cdp_csrf_param()) . '" value="' . h(cdp_csrf_token()) . '">';
?>
<main class="mx-body">
<div class="mx-auth-hero"><div style="display:flex;align-items:center;justify-content:space-between"><a class="mx-ib" href="<?= h($d['back'] ?? sl_route('Login')) ?>" aria-label="<?= h($d['backLabel'] ?? 'Back') ?>"><i class="ic i-left" aria-hidden="true"></i></a><?= sl_brandLogo('white', 40) ?></div><small><?= $signup ? 'Confirm your email · step 2 of 3' : 'Sign-in check · step 2 of 2' ?></small></div>
<h1 class="mx-big"><?= $signup ? 'Confirm your email' : 'Enter the 6-digit code' ?></h1>
<p class="mx-lede">Code sent to <b><?= h($d['email'] ?? '') ?></b><?php if (!empty($d['hasPhone']) && !$signup && ($d['phone'] ?? '') !== ''): ?> · also on WhatsApp <?= h($d['phone']) ?><?php endif; ?>.</p>
<?php if (($d['message'] ?? '') !== ''): ?><div class="mx-ban ok" role="status"><i class="ic ic-sm i-check" aria-hidden="true"></i><span><?= h($d['message']) ?></span></div><?php endif; ?>
<?php if (($d['error'] ?? '') !== ''): ?><div class="mx-ban bad" role="alert"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span><?= h($d['error']) ?></span></div><?php endif; ?>
<form method="post" action="auth-otp.php?flow=<?= h($flow) ?>" id="otp-form" style="display:contents">
<?= $csrf ?>
<section class="mx-card mx-codew">
<small><label for="otp_code">Code from e-mail<?= !empty($d['hasPhone']) && !$signup ? ' or WhatsApp' : '' ?></label></small>
<div class="mx-code" role="group" aria-label="6-digit code" data-sl-otp="#otp_code"><span></span><span></span><span></span><span></span><span></span><span></span></div>
<input id="otp_code" name="otp_code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required aria-label="6-digit code" style="position:absolute;opacity:0;height:1px;width:1px;overflow:hidden">
</section>
<?php if ($expires > 0): ?><div class="mx-ban info"><i class="ic ic-sm i-clock" aria-hidden="true"></i><span>Code expires in <b data-mx-countdown="<?= h(date('c', $expires)) ?>" data-done="0 s (request a new one)"><?= max(0, $expires - time()) ?> s</b></span></div><?php endif; ?>
<?php if (!$signup): ?>
<section class="mx-card mx-tgs"><div class="mx-tg"><span class="mx-rm"><b>Trust this device for 30 days</b><small><?= !empty($d['remember']) ? 'Skip this code next time on this phone' : 'Turn it on at sign-in to skip this code next time' ?></small></span><button type="button" class="mx-sw<?= !empty($d['remember']) ? ' on' : '' ?>" role="switch" aria-checked="<?= !empty($d['remember']) ? 'true' : 'false' ?>" aria-label="Trust this device for 30 days" disabled aria-disabled="true"></button></div></section>
<?php endif; ?>
<div class="mx-btns"><button class="mx-btn b4-b" type="submit"><?= $signup ? 'Verify and finish' : 'Verify and continue' ?></button></div>
</form>
<form method="post" action="auth-otp.php?flow=<?= h($flow) ?>" class="b4-resend">
<?= $csrf ?>
<i class="ic ic-sm i-clock" aria-hidden="true"></i><span>Didn’t get it?</span><button type="submit" name="resend" value="1" id="resendBtn" class="mx-lnk"<?= !empty($d['canResend']) ? '' : ' disabled' ?>>Resend code</button><b class="mono" data-sl-countdown="<?= $cdEnd ?>" data-sl-countdown-btn="#resendBtn" hidden></b>
</form>
<section class="b4-rules">
<div><i class="ic ic-sm i-shield" aria-hidden="true"></i><span>At most 5 codes every 30 minutes. Codes are single use.</span></div>
<div><i class="ic ic-sm i-lock" aria-hidden="true"></i><span>After 5 wrong codes the code is locked and you must request a new one.</span></div>
</section>
<?php if ($age && (!empty($age['expired']) || (int) $age['left'] <= 14)): ?>
<div class="b4-exp"><span><b><?= !empty($age['expired']) ? 'Your password has expired' : 'Your password expires in ' . max(0, (int) $age['left']) . ' days' ?></b>It is <?= (int) $age['days'] ?> days old. Change it after you sign in.</span><a href="<?= h(sl_route('PasswordReset')) ?>">Change</a></div>
<?php endif; ?>
<p class="mx-note">Didn’t get it? Check the email on your account, or ask the hub to update it. <a class="mx-lnk" style="min-height:0" href="<?= h($d['back'] ?? sl_route('Login')) ?>"><?= h($d['backLabel'] ?? 'Back') ?></a></p>
</main>
