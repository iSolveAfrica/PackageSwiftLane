<?php
/**
 * AccountSecurity (account_security.php): the signed-in user's own sign-in security.
 * Reuses sl_ownSignInData() (remembered devices + recent sign-ins), the system-wide sign-in code
 * switch (helpers/otp_settings.php) and the passkey store (helpers/webauthn.php). Nothing here takes a
 * user id from the request.
 */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/views/sl/data/account_edit.php';

function sl_accountSecurityData(int $uid): array
{
    if (is_file(CDP_ROOT . '/helpers/sl_device.php')) {
        @include_once CDP_ROOT . '/helpers/sl_device.php';
    }
    $dev = function_exists('sl_device') ? (array) sl_device() : [];
    $dev += ['biometric' => 'Passkey', 'label' => 'This device', 'form' => 'desktop', 'os' => 'other'];
    $me = sl_row('SELECT id, username FROM cdb_users WHERE id = :id', [':id' => $uid]);
    $own = sl_ownSignInData($uid, (string) ($me->username ?? ''));

    $otp = null;
    if (is_file(CDP_ROOT . '/helpers/otp_settings.php')) {
        require_once CDP_ROOT . '/helpers/otp_settings.php';
        $otp = function_exists('cdp_otpEnabled') ? (bool) cdp_otpEnabled() : null;
    }

    // Passkeys are stored per account, not per browser: the one made on this device carries the label
    // helpers/webauthn.php gives this user agent, which is the only link back to "this phone".
    $pk = null;
    try {
        require_once CDP_ROOT . '/helpers/webauthn.php';
        if (cdp_passkeysEnabled()) {
            $label = cdp_waDeviceLabel();
            $keys = sl_tableExists('cdb_passkeys') ? sl_rows('SELECT id, label FROM cdb_passkeys WHERE user_id = :u ORDER BY id DESC', [':u' => $uid]) : [];
            $here = 0;
            foreach ($keys as $k) {
                if ((string) $k->label === $label) {
                    $here = (int) $k->id;
                    break;
                }
            }
            $pk = ['label' => $label, 'here' => $here, 'count' => count($keys)];
        }
    } catch (Throwable $e) {
        $pk = null;
    }

    $place = $dev['form'] === 'phone' ? 'phone' : ($dev['form'] === 'tablet' ? 'tablet' : 'computer');
    return [
        'dev' => $dev,
        'place' => $place,
        'otp' => $otp,
        'pk' => $pk,
        'devices' => $own['devices'],
        'signins' => $own['signins'],
        'failed' => $own['failed'],
        'isClient' => sl_userIs('client'),
    ];
}
