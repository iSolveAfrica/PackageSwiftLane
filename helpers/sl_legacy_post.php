<?php
/**
 * Runs a POST handler that used to live at the top of a legacy view
 * (views/sl/handlers/*.php) and turns its outcome into the JSON the Swift Lane
 * forms expect (dataJs/sl/ops_forms.js):
 *   - a Location header (the handler's success redirect)  -> success + redirect
 *   - a non-empty $errors array                          -> 422 + errors
 *   - $success_script / $error_script + $message          -> success / 422
 * The handler code itself is unchanged, so validation, numbering, notifications,
 * status rules and audit stay exactly as they were.
 *
 * Usage (controller, global scope):
 *   sl_legacyPostBegin();
 *   include 'views/sl/handlers/x.php';
 *   sl_legacyPostEnd($errors ?? [], ['message' => $message ?? null, 'ok' => isset($success_script), 'bad' => isset($error_script)]);
 */

function sl_legacyLocation(): ?string
{
    foreach (headers_list() as $h) {
        if (stripos($h, 'Location:') === 0) {
            return trim(substr($h, 9));
        }
    }
    return null;
}

function sl_legacyPostBegin(): void
{
    $GLOBALS['__sl_lp_done'] = false;
    ob_start();
    // A handler may exit() after a redirect (cdp_redirect_to): answer from here.
    register_shutdown_function(function () {
        if (!empty($GLOBALS['__sl_lp_done'])) {
            return;
        }
        $loc = sl_legacyLocation();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header_remove('Location');
        $denied = $loc === null || stripos($loc, 'error403') !== false || stripos($loc, 'login.php') !== false || preg_match('/_list\.php$/', $loc);
        http_response_code($denied ? 403 : 200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($denied ? ['success' => false, 'message' => 'Not allowed or not found.'] : ['success' => true, 'message' => 'Saved', 'redirect' => $loc]);
    });
}

function sl_legacyPostEnd(array $errors, array $opt = []): void
{
    $GLOBALS['__sl_lp_done'] = true;
    $loc = sl_legacyLocation();
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header_remove('Location');
    $msg = isset($opt['message']) && $opt['message'] !== '' ? (string) $opt['message'] : null;
    if ($errors) {
        $errors = array_map(fn($e) => is_array($e) ? implode(' ', $e) : (string) $e, $errors);
        sl_json(['success' => false, 'errors' => $errors, 'message' => implode(' ', array_values($errors))], 422);
    }
    if (!empty($opt['bad'])) {
        sl_json(['success' => false, 'message' => $msg ?? 'The request was not accepted'], 422);
    }
    if ($loc === null && empty($opt['ok']) && !empty($opt['requireOk'])) {
        sl_json(['success' => false, 'message' => 'Nothing was saved. Check the form and try again.'], 422);
    }
    sl_json(['success' => true, 'message' => $msg ?? 'Saved', 'redirect' => $loc ?: ($opt['redirect'] ?? null)]);
}
