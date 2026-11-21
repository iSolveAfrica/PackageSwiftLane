<?php
// SMS helper library, included by the shipment / pickup / package endpoints and helpers/message_log.php.
// It sits under ajax/ but is not an endpoint: requested directly it would run with no login or permission check, so refuse it.
// Including it from another script is unaffected.
// Fail closed: when SCRIPT_FILENAME cannot be resolved (path-mapped FPM, chroot) the realpath test alone
// would let a direct request through, so also refuse unless loader.php (which defines CDP_ROOT) ran first.
if ((!defined('CDP_ROOT') && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === false)
    || realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit;
}
// ini_set('display_errors', 1);
require_once("../../helpers/querys.php");
require_once(__DIR__ . "/../../helpers/hubtel_sms.php");

// Configuración de la plantilla
function generateSMSBody($user_data, $fullshipment, $add_status, $app_url, $template_id) {
    $subjectVal = cdp_getsmsTemplates($template_id);
    if (!$subjectVal || trim((string) $subjectVal->body) === '') {
        return '';
    }

    // Every stored template starts with [SITE_NAME]; it was never filled in,
    // so texts went out reading "[SITE_NAME]: ...".
    static $siteName = null;
    if ($siteName === null) {
        $settings = cdp_getSettingsCourier();
        $siteName = trim((string) ($settings->site_name ?? ''));
    }

    $body = str_replace(
        array('[SITE_NAME]', '[NAME]', '[TRACK]', '[STATUS]', '[LINK]'),
        array(
            $siteName,
            trim(($user_data->fname ?? '') . ' ' . ($user_data->lname ?? '')),
            $fullshipment,
            $add_status,
            $app_url
        ),
        $subjectVal->body
    );

    $newbody = cdp_cleanOut($body);

    return $newbody;
}

// The SMS the "notify by SMS" toggles ask for. Hubtel sends it (Tools > SMS);
// the shared body lives in helpers/hubtel_sms.php so both SMS services and the
// WhatsApp copy clean the text the same way (plain text, no emoji).
function sendNotificationSMS($user, $sms_body, $notify)
{
    return cdp_smsSendNotification($user, $sms_body, $notify);
}
