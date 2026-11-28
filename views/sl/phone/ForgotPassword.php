<?php
/**
 * M-Forgot (forgot-password.php, step 1) and its code step (auth-otp.php?flow=forgot, step 2), view ForgotPassword.
 * Step 1 posts email to ajax/forgot-password-ajax.php (data-sl-legacy-form, as the web page); step 2 posts
 * otp_code / resend to auth-otp.php?flow=forgot.
 */
$slPhone['nav'] = 'none';
$slPhone['cls'] = 'mx-bare';
$slPhone['scripts'][] = 'dataJs/sl/auth.js';
$step = (int) ($d['step'] ?? 1);
$steps = [1 => '1 Email', 2 => '2 Verify code', 3 => '3 New password'];
$cdEnd = (int) ($d['countdownEnd'] ?? 0);
$expires = $cdEnd > 0 ? $cdEnd - 60 + 300 : 0;
$csrf = '<input type="hidden" name="' . h(cdp_csrf_param()) . '" value="' . h(cdp_csrf_token()) . '">';
?>
<main class="mx-body">
<div class="mx-auth-hero"><div style="display:flex;align-items:center;justify-content:space-between"><a class="mx-ib" href="<?= h($step === 1 ? sl_route('Login') : sl_route('ForgotPassword')) ?>" aria-label="Back"><i class="ic i-left" aria-hidden="true"></i></a><?= sl_brandLogo('white', 40) ?></div><small>We’ll get you back in</small></div>
<ol class="b4-steps" aria-label="Steps"><?php foreach ($steps as $n => $label): ?><li class="<?= $n < $step ? 'done' : ($n === $step ? 'cur' : '') ?>"<?= $n === $step ? ' aria-current="step"' : '' ?>><i></i><span><?= h($label) ?></span></li><?php endforeach; ?></ol>
<?php if ($step === 1): ?>
<h1 class="mx-big"><?= h($d['heading'] ?? 'Reset your password') ?></h1>
<p class="mx-lede">Enter the email of your account. We send a 6-digit code to it (and to your WhatsApp when it is on file). It expires in 5 minutes.</p>
<?php if (($d['notice'] ?? '') !== ''): ?><div class="mx-ban info" role="status"><i class="ic ic-sm i-info" aria-hidden="true"></i><span><?= h($d['notice']) ?></span></div><?php endif; ?>
<form data-sl-legacy-form action="ajax/forgot-password-ajax.php" method="post" id="forgotPassword" style="display:contents">
<div class="mx-ban" data-sl-msg style="display:none" role="status"></div>
<section class="mx-card mx-form"><label class="mx-f"><small>Email address</small><input class="mx-in mx-inp" id="email" name="email" type="email" inputmode="email" autocomplete="email" autocapitalize="none" spellcheck="false" required placeholder="you@email.com"></label></section>
<div class="mx-btns"><button class="mx-btn b4-b" type="submit"><?= h($d['button'] ?? 'Send me a code') ?></button></div>
</form>
<div class="mx-ban info"><i class="ic ic-sm i-shield" aria-hidden="true"></i><span>For your safety we never say whether an account exists. At most 5 codes every 30 minutes.</span></div>
<div class="b4-row2"><a class="mx-lnk" href="<?= h(sl_route('SignUp')) ?>">No account yet? Sign up</a><a class="mx-lnk" href="<?= h(sl_route('Login')) ?>">Back to log in</a></div>
<?php else: ?>
<h1 class="mx-big">Check your email<?= !empty($d['hasPhone']) ? ' or WhatsApp' : '' ?></h1>
<p class="mx-lede">Code sent to <b><?= h($d['email'] ?? '') ?></b><?= !empty($d['hasPhone']) && ($d['phone'] ?? '') !== '' ? ' · also on WhatsApp ' . h($d['phone']) : '' ?>. It expires in 5 minutes.</p>
<?php if (($d['message'] ?? '') !== ''): ?><div class="mx-ban ok" role="status"><i class="ic ic-sm i-check" aria-hidden="true"></i><span><?= h($d['message']) ?></span></div><?php endif; ?>
<?php if (($d['error'] ?? '') !== ''): ?><div class="mx-ban bad" role="alert"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span><?= h($d['error']) ?></span></div><?php endif; ?>
<form method="post" action="auth-otp.php?flow=forgot" id="otp-form" style="display:contents">
<?= $csrf ?>
<section class="mx-card mx-codew">
<small><label for="otp_code">Code from e-mail<?= !empty($d['hasPhone']) ? ' or WhatsApp' : '' ?></label></small>
<div class="mx-code" role="group" aria-label="Verification code" data-sl-otp="#otp_code"><span></span><span></span><span></span><span></span><span></span><span></span></div>
<input id="otp_code" name="otp_code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required aria-label="6-digit code" style="position:absolute;opacity:0;height:1px;width:1px;overflow:hidden">
</section>
<?php if ($expires > 0): ?><div class="mx-ban info"><i class="ic ic-sm i-clock" aria-hidden="true"></i><span>Code expires in <b data-mx-countdown="<?= h(date('c', $expires)) ?>" data-done="0 s (request a new one)"><?= max(0, $expires - time()) ?> s</b></span></div><?php endif; ?>
<div class="mx-btns"><button class="mx-btn b4-b" type="submit">Verify and continue</button></div>
</form>
<form method="post" action="auth-otp.php?flow=forgot" class="b4-resend">
<?= $csrf ?>
<i class="ic ic-sm i-clock" aria-hidden="true"></i><span>Didn’t get it?</span><button type="submit" name="resend" value="1" id="resendBtn" class="mx-lnk"<?= !empty($d['canResend']) ? '' : ' disabled' ?>>Resend code</button><b class="mono" data-sl-countdown="<?= $cdEnd ?>" data-sl-countdown-btn="#resendBtn" hidden></b>
</form>
<div class="mx-ban info"><i class="ic ic-sm i-shield" aria-hidden="true"></i><span>Your email and phone number are partly hidden to keep your account private.</span></div>
<div class="b4-row2"><a class="mx-lnk" href="<?= h(sl_route('ForgotPassword')) ?>">Use another email</a><a class="mx-lnk" href="<?= h(sl_route('Login')) ?>">Back to log in</a></div>
<?php endif; ?>
</main>
