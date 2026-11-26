<?php
/**
 * ID document (type / number / photos) for a customer profile.
 *
 * The document is OPTIONAL. Any subset of the fields may be sent; only the
 * fields that were sent are updated. Photos: `document_photo` (or the older
 * `document`) is the FRONT of the card (cdb_users.document_photo),
 * `document_photo_back` the BACK (cdb_users.document_photo_back, written only
 * once migrations/2026_11_26_id_document_back.sql added the column). `skip=1`
 * records that the customer chose not to provide one so the onboarding modal
 * stops asking.
 *
 * Used by: the "My Profile" page and the forced account-setup modal. Staff
 * with the edit_client_document permission may update a client's document by
 * passing `id`.
 */
ini_set('display_errors', 0);

require_once("../loader.php");
require_once("../helpers/querys.php");
require_once("../helpers/rbac.php");
require_once("../helpers/profile.php");
require_once("../helpers/ajax_guard.php");
require_login();

header('Content-Type: application/json; charset=UTF-8');

$user = new User();
$db   = new Conexion;

$targetId = (int) ($_POST['id'] ?? $user->uid);
if (!cdp_profileCanEdit($user, $targetId, 'edit_client_document')) {
    echo json_encode(['status' => 'error', 'message' => 'You can only update your own document.']);
    exit;
}

// The back of the card has its own column once migrations/2026_11_26_id_document_back.sql ran.
$db->cdp_query("SELECT COUNT(*) AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_users' AND COLUMN_NAME = 'document_photo_back'");
$hasBackCol = (int) ($db->cdp_registro()->n ?? 0) > 0;
$db->cdp_query("SELECT id, document_type, document_number, document_photo" . ($hasBackCol ? ', document_photo_back' : '') . ", fname, lname, userlevel, agency_id FROM cdb_users WHERE id = :id LIMIT 1");
$db->bind(':id', $targetId);
$row = $db->cdp_registro();
if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'Account not found.']);
    exit;
}
// Someone else's document: edit_client_document is for customer accounts, and the caller must
// outrank the target, so a customer or junior staff member holding the permission can never
// rewrite an administrator's (or a peer's) ID document. Agency-restricted staff also stay
// inside their own agency.
if ($targetId !== (int) $user->uid
    && (!cdp_canManageUser($user, (int) $row->userlevel)
        || (!$user->cdp_is_Admin() && !cdp_roleIsClient((int) $row->userlevel))
        || !cdp_ajaxRowInAgencyScope((object) ['agency' => $row->agency_id]))) {
    echo json_encode(['status' => 'error', 'message' => 'You can only update your own document.']);
    exit;
}

// "Skip for now": nothing to store, just stop the onboarding prompt.
if (!empty($_POST['skip'])) {
    cdp_profileMarkStep($targetId, 'update_document');
    echo json_encode(['status' => 'success', 'message' => 'You can add your ID document later from your profile.', 'skipped' => true]);
    exit;
}

$allowedTypes = ['PSP', 'ECW', 'DNI'];
// Older rows (and the select that shows them) carry the label instead of the code.
$typeLabels = ['PASSPORT' => 'PSP', 'GHANA CARD' => 'ECW', 'NATIONAL IDENTITY DOCUMENT' => 'DNI'];
$type   = isset($_POST['document_type']) ? strtoupper(trim(cdp_sanitize($_POST['document_type']))) : null;
if ($type !== null && isset($typeLabels[$type])) {
    $type = $typeLabels[$type];
}
$number = isset($_POST['document_number']) ? trim(cdp_sanitize($_POST['document_number'])) : null;
$hasFront = !empty($_FILES['document_photo']['name']) || !empty($_FILES['document']['name']);
$hasBack  = !empty($_FILES['document_photo_back']['name']);
if ($hasBack && !$hasBackCol) {
    echo json_encode(['status' => 'error', 'message' => 'The back of the ID cannot be stored yet: run migrations/2026_11_26_id_document_back.sql.']);
    exit;
}
$hasPhoto = $hasFront || $hasBack;

if ($type !== null && $type !== '' && !in_array($type, $allowedTypes, true)) {
    echo json_encode(['status' => 'error', 'message' => 'Unknown document type.']);
    exit;
}
if ($number !== null && $number !== '' && mb_strlen($number) < 3) {
    echo json_encode(['status' => 'error', 'message' => 'The document number looks too short.']);
    exit;
}
// A number without a type (or vice-versa) is not a usable document. Checked only when one of the
// two was sent, so a photo can always be added to an account whose type or number is incomplete.
$finalType   = ($type === null) ? (string) $row->document_type : $type;
$finalNumber = ($number === null) ? (string) $row->document_number : $number;
if (($type !== null || $number !== null) && ($finalType === '') !== ($finalNumber === '')) {
    echo json_encode(['status' => 'error', 'message' => 'Please provide both the document type and the document number.']);
    exit;
}

if ($type === null && $number === null && !$hasPhoto) {
    echo json_encode(['status' => 'error', 'message' => 'Nothing to update.']);
    exit;
}

// Images only (JPEG / PNG / GIF / WEBP by their contents), stored under a name we choose.
$photoPath = null;
$backPath  = null;
if ($hasFront) {
    $file = !empty($_FILES['document_photo']['name']) ? $_FILES['document_photo'] : $_FILES['document'];
    $stored = cdp_profileStoreImage($file, 'users', 'document_' . $targetId);
    if (empty($stored['ok'])) {
        echo json_encode(['status' => 'error', 'message' => $stored['error']]);
        exit;
    }
    $photoPath = $stored['path'];
}
if ($hasBack) {
    $stored = cdp_profileStoreImage($_FILES['document_photo_back'], 'users', 'document_back_' . $targetId);
    if (empty($stored['ok'])) {
        echo json_encode(['status' => 'error', 'message' => 'Back of the ID: ' . $stored['error']]);
        exit;
    }
    $backPath = $stored['path'];
}

$sql = 'UPDATE cdb_users SET document_type = :t, document_number = :n'
     . ($photoPath !== null ? ', document_photo = :p' : '')
     . ($backPath !== null ? ', document_photo_back = :pb' : '')
     . ' WHERE id = :id';
$db->cdp_query($sql);
$db->bind(':t', $finalType);
$db->bind(':n', $finalNumber);
if ($photoPath !== null) {
    $db->bind(':p', $photoPath);
}
if ($backPath !== null) {
    $db->bind(':pb', $backPath);
}
$db->bind(':id', $targetId);

if (!$db->cdp_execute()) {
    echo json_encode(['status' => 'error', 'message' => 'Could not save the document.']);
    exit;
}

cdp_profileMarkStep($targetId, 'update_document');
if ($photoPath !== null) {
    cdp_profileHistoryLog($targetId, (int) $user->uid, (string) $row->document_photo, 'Document updated');
}
if ($backPath !== null) {
    cdp_profileHistoryLog($targetId, (int) $user->uid, (string) ($row->document_photo_back ?? ''), 'Back of ID updated');
}

if (function_exists('cdp_activityLog')) {
    $changes = [];
    if ((string) $row->document_type !== $finalType)     { $changes['document_type']   = ['from' => (string) $row->document_type,   'to' => $finalType]; }
    if ((string) $row->document_number !== $finalNumber) { $changes['document_number'] = ['from' => (string) $row->document_number, 'to' => $finalNumber]; }
    if ($photoPath !== null)                             { $changes['document_photo']  = ['from' => (string) $row->document_photo,  'to' => $photoPath]; }
    if ($backPath !== null)                              { $changes['document_photo_back'] = ['from' => (string) ($row->document_photo_back ?? ''), 'to' => $backPath]; }
    cdp_activityLog([
        'module'       => 'profile',
        'verb'         => 'update',
        'action'       => 'profile.document',
        'label'        => 'Profile · ID Document Updated',
        'entity_type'  => 'user',
        'entity_id'    => $targetId,
        'entity_label' => trim($row->fname . ' ' . $row->lname),
        'summary'      => ($targetId === (int) $user->uid)
            ? 'Updated their own ID document'
            : 'Updated the ID document of customer #' . $targetId,
        'changes'      => $changes,
    ]);
}

echo json_encode([
    'status'            => 'success',
    'message'           => $backPath !== null && $photoPath === null ? 'Back of the ID saved.' : ($photoPath !== null && $backPath === null && $type === null && $number === null ? 'Front of the ID saved.' : 'Document saved.'),
    'document_url'      => $photoPath !== null ? cdp_avatarUrl($photoPath, 'uploads/blankID.jpg') : null,
    'document_back_url' => $backPath !== null ? cdp_avatarUrl($backPath, 'uploads/blankID.jpg') : null,
]);
