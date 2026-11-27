<?php
/**
 * M-LoginPage: what the sign-in screen looks like. Same $d as the web page (login_settings.php).
 * Look and headline save through ajax/sl/login_settings_ajax.php op=save with the web's field names
 * (the other texts, captions and timing go back unchanged); pictures-per-page switches use op=page;
 * fingerprint sign-in uses op=auth. Sign-up and sign-in codes are the company settings of the web
 * Settings hub (ajax/tools/config_system_ajax.php, every field sent back unchanged).
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
require_once CDP_ROOT . '/helpers/querys.php';
$ep = 'ajax/sl/login_settings_ajax.php';
$slPhone['nav'] = 'staff';
$slPhone['tab'] = 'Menu';
$slPhone['scripts'][] = 'dataJs/sl/phone/settings.js';
$look = (array) ($d['look'] ?? []);
$tx = (array) ($look['texts'] ?? []);
$defaults = ['login' => ['Welcome back', 'Log in'], 'register' => ['Create your account', 'Create my account'], 'forgot' => ['Forgot your password?', 'Send me a code']];
$txt = fn($pg, $i) => (string) (($tx[$pg][$i] ?? null) ?: $defaults[$pg][$i]);
$pages = (array) ($look['pages'] ?? []);
$cur = $d['cur'] ?? null;
$bg = $cur && ($cur['url'] ?? '') !== '' ? 'background-image:url(' . $cur['url'] . ')' : '';
$blur = (int) ($look['blur'] ?? 50);
$blurOpts = [0 => 'Photo · sharp', 25 => 'Photo · light blur', 50 => 'Photo · blurred', 75 => 'Photo · strong blur', 100 => 'Photo · fully blurred'];
if (!isset($blurOpts[$blur])) $blurOpts[$blur] = 'Photo · ' . $blur . '% blur';
ksort($blurOpts);
$s = function_exists('cdp_getSettingsCourier') ? cdp_getSettingsCourier() : null;
$auth = (array) ($d['auth'] ?? []);
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-settings.css')) ?>">
<?= sl_phoneTop('Login page', (int) $d['onCount'] . ' of ' . count($d['slides']) . ' pictures showing', ['back' => sl_route('SettingsHub')], sl_phoneIcon('eye', 'Open the login page', sl_route('Login'))) ?>
<main class="mx-body">
<section class="mx-cv ph-bg" style="min-height:200px;<?= h($bg) ?>" aria-label="Preview of the sign-in screen"><b style="font-size:18px"><?= h($txt('login', 0)) ?></b><small style="opacity:.7">Preview of the sign-in screen</small></section>
<form data-sl-form data-sl-reload action="<?= h($ep) ?>" method="post">
<input type="hidden" name="op" value="save">
<input type="hidden" name="dim" value="<?= (int) ($look['dim'] ?? 30) ?>"><input type="hidden" name="interval" value="<?= (int) ($look['interval'] ?? 6) ?>"><input type="hidden" name="captions" value="<?= !empty($look['captions']) ? '1' : '0' ?>">
<input type="hidden" name="text_b[login]" value="<?= h($txt('login', 1)) ?>">
<?php foreach (['register', 'forgot'] as $pg): ?><input type="hidden" name="text_h[<?= $pg ?>]" value="<?= h($txt($pg, 0)) ?>"><input type="hidden" name="text_b[<?= $pg ?>]" value="<?= h($txt($pg, 1)) ?>"><?php endforeach; ?>
<section class="mx-card mx-form">
<label class="mx-f"><small>Background</small><select class="mx-in" name="blur"><?php foreach ($blurOpts as $v => $l): ?><option value="<?= (int) $v ?>"<?= $v === $blur ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></label>
<label class="mx-f"><small>Headline</small><input class="mx-in" name="text_h[login]" maxlength="60" value="<?= h($txt('login', 0)) ?>" autocomplete="off"></label>
<a class="mx-f" href="settings_form.php?f=logo" style="color:inherit"><small>Logo</small><span class="mx-in sel"><span><?= h($d['logo'] !== '' ? basename($d['logo']) : 'Not set') ?></span><i class="ic ic-sm i-right" aria-hidden="true"></i></span></a>
<a class="mx-f" href="settings_form.php?f=logo" style="color:inherit"><small>Favicon and app icon</small><span class="mx-in sel"><span><?= h($d['favicon'] !== '' ? basename($d['favicon']) : 'Not set') ?></span><i class="ic ic-sm i-right" aria-hidden="true"></i></span></a>
</section>
<div class="mx-btns"><button class="mx-btn" type="submit">Save</button></div>
</form>
<div class="mx-sec"><h2>Pictures on</h2></div>
<section class="mx-card mx-tgs">
<?php foreach ([['login', 'Log in'], ['forgot', 'Forgot password'], ['otp', 'Code check (OTP)']] as [$pg, $lbl]): ?>
<?= sl_mxToggle($lbl, '', in_array($pg, $pages, true), $ep, '', ['op' => 'page', 'page' => $pg]) ?>
<?php endforeach; ?>
</section>
<div class="mx-sec"><h2>Sign-in</h2></div>
<section class="mx-card mx-tgs">
<?php if ($s): ?>
<form data-sl-form data-sl-reload action="ajax/tools/config_system_ajax.php" method="post">
<?php foreach (['site_name', 'site_url', 'c_nit', 'c_phone', 'cell_phone', 'c_address', 'locker_address', 'c_country', 'c_city', 'c_postal', 'site_email', 'reg_verify', 'notify_admin', 'auto_verify', 'code_number_locker', 'digit_random_locker', 'prefix_locker'] as $k): ?><input type="hidden" name="<?= $k ?>" value="<?= h($s->$k ?? '') ?>"><?php endforeach; ?>
<input type="hidden" name="reg_allowed" id="ls-reg" value="<?= (int) ($s->reg_allowed ?? 0) === 1 ? 1 : 0 ?>"><input type="hidden" name="active_otp" id="ls-otp" value="<?= (int) ($s->active_otp ?? 0) === 1 ? 1 : 0 ?>">
<div class="mx-tg"><span class="mx-rm"><b>Allow sign-up</b><small>Customers can create an account</small></span><button type="button" class="mx-sw<?= (int) ($s->reg_allowed ?? 0) === 1 ? ' on' : '' ?>" role="switch" aria-checked="<?= (int) ($s->reg_allowed ?? 0) === 1 ? 'true' : 'false' ?>" aria-label="Allow sign-up" data-input="#ls-reg" data-ph-submit></button></div>
<div class="mx-tg"><span class="mx-rm"><b>Sign-in codes</b><small>A code by e-mail or WhatsApp after the password</small></span><button type="button" class="mx-sw<?= (int) ($s->active_otp ?? 0) === 1 ? ' on' : '' ?>" role="switch" aria-checked="<?= (int) ($s->active_otp ?? 0) === 1 ? 'true' : 'false' ?>" aria-label="Sign-in codes" data-input="#ls-otp" data-ph-submit></button></div>
</form>
<?php endif; ?>
<form data-sl-form data-sl-reload action="<?= h($ep) ?>" method="post"><input type="hidden" name="op" value="auth"><input type="hidden" name="passkeys" id="ls-pk" value="<?= !empty($auth['passkeys']) ? 1 : 0 ?>">
<div class="mx-tg"><span class="mx-rm"><b>Fingerprint and face sign-in</b><small><?= !empty($auth['https']) ? 'Passkeys on this phone' : 'Needs the site on HTTPS' ?></small></span><button type="button" class="mx-sw<?= !empty($auth['passkeys']) ? ' on' : '' ?>" role="switch" aria-checked="<?= !empty($auth['passkeys']) ? 'true' : 'false' ?>" aria-label="Fingerprint and face sign-in" data-input="#ls-pk" data-ph-submit></button></div>
</form>
</section>
</main>
