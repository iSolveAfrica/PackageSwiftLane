<?php
/**
 * Login page settings (LoginSettings board): pictures + captions behind the sign-in pages,
 * which pages show them, blur / darken / timing, page and page texts.
 * Stored in cdb_sl_settings (login_slides JSON, login_blur, login_dim, login_interval,
 * login_captions, login_pages, login_texts JSON). Pictures are saved
 * under assets/uploads/login/ after an image-type check (getimagesize, not the file name).
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/sl_auth.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_permission('view_tools');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
if (CDP_APP_MODE_DEMO === true) {
    sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
}
if (!sl_tableExists('cdb_sl_settings')) {
    sl_json(['success' => false, 'message' => 'Run the database upgrade first (cdb_sl_settings is missing).'], 409);
}
$by = (int) $_SESSION['userid'];
$op = (string) ($_POST['op'] ?? '');
$slides = sl_loginSlidesAll();
$saveSlides = function (array $s) use ($by) {
    return sl_settingSet('login_slides', json_encode(array_values($s), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $by);
};
$log = function (string $summary) {
    cdp_activityLog(['module' => 'settings', 'action' => 'login_page.update', 'action_label' => 'Login page settings', 'verb' => 'update', 'outcome' => 'ok', 'summary' => $summary, 'entity_type' => 'settings', 'entity_id' => 'login_page']);
};
$idx = (int) ($_POST['i'] ?? -1);

switch ($op) {
    case 'upload':
        if (count($slides) >= 8) {
            sl_json(['success' => false, 'message' => 'Up to 8 pictures. Delete one first.'], 422);
        }
        $files = $_FILES['pictures'] ?? null;
        if (!$files || empty($files['name'])) {
            sl_json(['success' => false, 'message' => 'Choose a picture to upload'], 422);
        }
        $list = [];
        if (is_array($files['name'])) {
            foreach ($files['name'] as $k => $n) {
                $list[] = ['name' => $n, 'tmp_name' => $files['tmp_name'][$k], 'error' => $files['error'][$k], 'size' => $files['size'][$k]];
            }
        } else {
            $list[] = $files;
        }
        $added = 0;
        $errs = [];
        foreach ($list as $f) {
            if (count($slides) >= 8) {
                break;
            }
            $r = sl_storeImage($f, 'login', 'login', 8 * 1048576);
            if (empty($r['ok'])) {
                $errs[] = $f['name'] . ': ' . $r['error'];
                continue;
            }
            if (!in_array(pathinfo($r['path'], PATHINFO_EXTENSION), ['jpg', 'png', 'webp'], true)) {
                @unlink(CDP_ROOT . '/' . $r['path']);
                $errs[] = $f['name'] . ': use JPG, PNG or WEBP';
                continue;
            }
            $slides[] = ['cls' => '', 'url' => $r['path'], 'title' => '', 'sub' => '', 'on' => true];
            $added++;
            if ($r['w'] < 1280) {
                $errs[] = $f['name'] . ' is small (' . $r['w'] . ' px wide); 1920 px looks best';
            }
        }
        if ($added) {
            $saveSlides($slides);
            $log("Uploaded $added login picture(s)");
        }
        sl_json(['success' => $added > 0, 'message' => $added ? "$added picture(s) added" . ($errs ? '. ' . implode('; ', $errs) : '') : implode('; ', $errs), 'reload' => $added > 0], $added ? 200 : 422);
        break;
    case 'toggle':
        if (!isset($slides[$idx])) {
            sl_json(['success' => false, 'message' => 'Picture not found'], 404);
        }
        $slides[$idx]['on'] = !empty($_POST['value']);
        if (!array_filter($slides, fn($s) => $s['on'])) {
            sl_json(['success' => false, 'message' => 'Keep at least one picture showing'], 422);
        }
        $saveSlides($slides);
        $log('Picture ' . ($idx + 1) . ($slides[$idx]['on'] ? ' shown' : ' hidden'));
        sl_json(['success' => true, 'message' => 'Saved']);
        break;
    case 'up':
        if ($idx <= 0 || !isset($slides[$idx])) {
            sl_json(['success' => false, 'message' => 'Already first'], 422);
        }
        [$slides[$idx - 1], $slides[$idx]] = [$slides[$idx], $slides[$idx - 1]];
        $saveSlides($slides);
        $log('Reordered login pictures');
        sl_json(['success' => true, 'message' => 'Moved up', 'reload' => true]);
        break;
    case 'delete':
        if (!isset($slides[$idx])) {
            sl_json(['success' => false, 'message' => 'Picture not found'], 404);
        }
        if (count($slides) <= 1) {
            sl_json(['success' => false, 'message' => 'Keep at least one picture'], 422);
        }
        $gone = $slides[$idx];
        array_splice($slides, $idx, 1);
        $saveSlides($slides);
        if ($gone['url'] !== '' && preg_match('#^assets/uploads/login/[A-Za-z0-9_.-]+$#', $gone['url']) && is_file(CDP_ROOT . '/' . $gone['url'])) {
            @unlink(CDP_ROOT . '/' . $gone['url']);
        }
        $log('Deleted login picture ' . ($idx + 1));
        sl_json(['success' => true, 'message' => 'Picture deleted', 'reload' => true]);
        break;
    case 'page':
        $pg = (string) ($_POST['page'] ?? '');
        if (!in_array($pg, ['login', 'register', 'forgot', 'otp'], true)) {
            sl_json(['success' => false, 'message' => 'Unknown page'], 422);
        }
        $pages = sl_loginLook()['pages'];
        $pages = array_values(array_diff($pages, [$pg]));
        if (!empty($_POST['value'])) {
            $pages[] = $pg;
        }
        sl_settingSet('login_pages', implode(',', $pages), $by);
        $log('Pictures on ' . $pg . ': ' . (!empty($_POST['value']) ? 'on' : 'off'));
        sl_json(['success' => true, 'message' => 'Saved']);
        break;
    case 'save':
        $errors = [];
        $titles = (array) ($_POST['title'] ?? []);
        $subs = (array) ($_POST['sub'] ?? []);
        foreach ($slides as $i => &$s) {
            if (isset($titles[$i])) {
                $s['title'] = mb_substr(trim(strip_tags((string) $titles[$i])), 0, 80);
            }
            if (isset($subs[$i])) {
                $s['sub'] = mb_substr(trim(strip_tags((string) $subs[$i])), 0, 120);
            }
        }
        unset($s);
        $blur = (int) ($_POST['blur'] ?? 50);
        $dim = (int) ($_POST['dim'] ?? 30);
        $interval = (int) ($_POST['interval'] ?? 6);
        if ($blur < 0 || $blur > 100) $errors['blur'] = '0 to 100';
        if ($dim < 0 || $dim > 80) $errors['dim'] = '0 to 80';
        if (!in_array($interval, [4, 6, 8, 12], true)) $errors['interval'] = 'Pick a value';
        $texts = [];
        foreach (['login', 'register', 'forgot'] as $pg) {
            $hh = mb_substr(trim(strip_tags((string) ($_POST['text_h'][$pg] ?? ''))), 0, 60);
            $bb = mb_substr(trim(strip_tags((string) ($_POST['text_b'][$pg] ?? ''))), 0, 30);
            if ($hh !== '' || $bb !== '') {
                $texts[$pg] = [$hh !== '' ? $hh : null, $bb !== '' ? $bb : null];
            }
        }
        if ($errors) {
            sl_json(['success' => false, 'message' => 'Please check the highlighted fields', 'errors' => $errors], 422);
        }
        $saveSlides($slides);
        sl_settingSet('login_blur', (string) $blur, $by);
        sl_settingSet('login_dim', (string) $dim, $by);
        sl_settingSet('login_interval', (string) $interval, $by);
        sl_settingSet('login_captions', !empty($_POST['captions']) ? '1' : '0', $by);
        sl_settingSet('login_texts', json_encode($texts, JSON_UNESCAPED_UNICODE), $by);
        $log('Saved login page look, captions and texts');
        sl_json(['success' => true, 'message' => 'Saved. Customers and staff now see these pictures on the login page.', 'reload' => true]);
        break;
    case 'reset':
        sl_settingSet('login_slides', '', $by);
        $log('Reset login pictures to the defaults');
        sl_json(['success' => true, 'message' => 'Default pictures restored', 'reload' => true]);
        break;
    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
