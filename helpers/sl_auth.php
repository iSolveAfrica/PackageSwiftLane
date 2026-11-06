<?php
/**
 * Helpers shared by the authentication, public and customer-portal pages of the
 * Swift Lane design (Login, LoginOtp, ForgotPassword, PasswordReset, SignUp,
 * PublicTracking, QuoteCalculator, ClientDashboard, UserHome, MyBills,
 * PayCheckout, CustomerNotifications, AiStudio, LoginSettings).
 *
 * Settings live in cdb_sl_settings (migrations/2026_11_06_auth_portal.sql).
 */
require_once __DIR__ . '/sl_view.php';

/** One value from cdb_sl_settings (empty string when unset or table missing). */
function sl_setting(string $key, $default = '')
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        if (sl_tableExists('cdb_sl_settings')) {
            foreach (sl_rows('SELECT setting_key, setting_value FROM cdb_sl_settings') as $r) {
                $cache[$r->setting_key] = (string) $r->setting_value;
            }
        }
    }
    return array_key_exists($key, $cache) && $cache[$key] !== '' ? $cache[$key] : $default;
}

/** Upsert one value in cdb_sl_settings. */
function sl_settingSet(string $key, $value, ?int $by = null): bool
{
    if (!sl_tableExists('cdb_sl_settings')) {
        return false;
    }
    $db = new Conexion();
    $db->cdp_query('INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_by, updated_at) VALUES (:k, :v, :b, NOW())
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()');
    $db->bind(':k', $key);
    $db->bind(':v', (string) $value);
    $db->bind(':b', $by);
    return (bool) $db->cdp_execute();
}

/** The three default pictures of the design (CSS-drawn, no upload needed). */
function sl_loginDefaultSlides(): array
{
    return [
        ['cls' => 'bg-plane', 'url' => '', 'title' => 'Air freight, twice a week', 'sub' => 'New York to Accra in 5 to 7 days', 'on' => true],
        ['cls' => 'bg-ship', 'url' => '', 'title' => 'Sea freight for big loads', 'sub' => 'Monthly containers, Newark to Tema', 'on' => true],
        ['cls' => 'bg-wh', 'url' => '', 'title' => 'Your own US address', 'sub' => 'Shop online, we receive and ship it', 'on' => true],
    ];
}

/** Every configured slide (on and off), normalised. */
function sl_loginSlidesAll(): array
{
    $raw = json_decode((string) sl_setting('login_slides', ''), true);
    if (!is_array($raw) || !$raw) {
        return sl_loginDefaultSlides();
    }
    $out = [];
    foreach ($raw as $s) {
        if (!is_array($s)) {
            continue;
        }
        $url = (string) ($s['url'] ?? '');
        if ($url !== '' && !preg_match('#^assets/uploads/login/[A-Za-z0-9_.-]+$#', $url)) {
            $url = '';
        }
        $out[] = [
            'cls' => preg_match('/^bg-(plane|ship|wh)$/', (string) ($s['cls'] ?? '')) ? $s['cls'] : '',
            'url' => $url,
            'title' => mb_substr((string) ($s['title'] ?? ''), 0, 80),
            'sub' => mb_substr((string) ($s['sub'] ?? ''), 0, 120),
            'on' => !empty($s['on']),
        ];
    }
    return $out ?: sl_loginDefaultSlides();
}

/** Look and motion settings of the auth pages. */
function sl_loginLook(): array
{
    $pages = array_filter(array_map('trim', explode(',', (string) sl_setting('login_pages', 'login,register,forgot'))));
    return [
        'blur' => max(0, min(100, (int) sl_setting('login_blur', 50))),
        'dim' => max(0, min(80, (int) sl_setting('login_dim', 30))),
        'interval' => max(3, min(30, (int) sl_setting('login_interval', 6))),
        'captions' => sl_setting('login_captions', '1') === '1',
        'pages' => array_values($pages),
        'title' => (string) sl_setting('login_site_title', ''),
        'texts' => json_decode((string) sl_setting('login_texts', ''), true) ?: [],
    ];
}

/**
 * Picture data for one auth page ('login', 'register', 'forgot', 'otp'):
 * ['slides' => [...on slides...], 'style' => inline CSS vars, 'captions' => bool, 'show' => bool].
 */
function sl_loginPictures(string $page): array
{
    $look = sl_loginLook();
    $slides = array_values(array_filter(sl_loginSlidesAll(), fn($s) => $s['on']));
    $n = max(1, count($slides));
    $style = '--sl-blur:' . round($look['blur'] * 0.16, 1) . 'px;--sl-dim:' . round($look['dim'] / 100, 2) . ';--sl-cycle:' . ($look['interval'] * $n) . 's;';
    return [
        'slides' => $slides,
        'style' => $style,
        'captions' => $look['captions'],
        'show' => in_array($page, $look['pages'], true),
        'interval' => $look['interval'],
        'texts' => $look['texts'][$page] ?? [],
    ];
}

/** Password rotation period in days (0 = off). */
function sl_passwordRotationDays(): int
{
    return max(0, (int) sl_setting('password_rotation_days', 0));
}

/**
 * Age info of a user's password: ['days' => n, 'left' => n, 'expired' => bool, 'changed' => 'Y-m-d', 'next' => 'Y-m-d'].
 * Returns null when rotation is off or the column is missing.
 */
function sl_passwordAge($userRow): ?array
{
    $period = sl_passwordRotationDays();
    if ($period <= 0 || !$userRow || !sl_columnExists('cdb_users', 'password_changed_at')) {
        return null;
    }
    $changed = $userRow->password_changed_at ?? null;
    $ts = $changed ? strtotime((string) $changed) : false;
    if (!$ts) {
        $ts = strtotime((string) ($userRow->created ?? 'now')) ?: time();
    }
    $days = (int) floor((time() - $ts) / 86400);
    return [
        'days' => $days,
        'period' => $period,
        'left' => $period - $days,
        'expired' => $days >= $period,
        'changed' => date('Y-m-d', $ts),
        'next' => date('Y-m-d', $ts + $period * 86400),
    ];
}

/** For a logged-in user whose password has expired: send them to the change page. */
function sl_passwordRotationGuard(): void
{
    if (empty($_SESSION['userid']) || basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === 'forgot-reset.php') {
        return;
    }
    if (sl_passwordRotationDays() <= 0) {
        return;
    }
    $row = sl_row('SELECT password_changed_at, created FROM cdb_users WHERE id = :id', [':id' => (int) $_SESSION['userid']]);
    $age = sl_passwordAge($row);
    if ($age && $age['expired']) {
        header('Location: forgot-reset.php?expired=1');
        exit;
    }
}

function sl_maskEmail(string $email): string
{
    if (strpos($email, '@') === false) {
        return $email === '' ? '—' : mb_substr($email, 0, 1) . '•••••';
    }
    [$u, $d] = explode('@', $email, 2);
    return mb_substr($u, 0, 1) . '•••••@' . $d;
}

function sl_maskPhone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone);
    if (strlen($digits) < 4) {
        return $phone === '' ? '—' : '•••';
    }
    $cc = strlen($digits) > 9 ? '+' . substr($digits, 0, strlen($digits) - 9) . ' ' : '';
    return $cc . '•• ••• •• ' . substr($digits, -2);
}

/** Customer-facing status pill text/class for a cdb_styles status id. */
function sl_customerStatus(int $statusId, string $modStyle = ''): array
{
    $labels = [
        1 => 'Pending collection', 2 => 'Received at office', 3 => 'In transit', 4 => 'At warehouse', 5 => 'Out for distribution',
        6 => 'Ready for pickup', 7 => 'On route', 8 => 'Delivered', 10 => 'Approved', 11 => 'Pending', 12 => 'Rejected', 13 => 'Consolidated',
        14 => 'Pickup requested', 15 => 'Picked up', 16 => 'Not picked up', 17 => 'Quotation', 18 => 'Pending quote', 19 => 'Invoiced',
        21 => 'Cancelled', 23 => 'Pending payment', 25 => 'Not shipped', 28 => 'Held', 29 => 'On hold', 32 => 'Ready for pickup',
    ];
    return ['label' => $labels[$statusId] ?? ($modStyle !== '' ? str_replace('_', ' ', $modStyle) : 'Unknown'), 'cls' => sl_statusClass($statusId)];
}

/** 0-100 progress of a shipment on the customer journey (registered → delivered). */
function sl_customerProgress(int $statusId): int
{
    $map = [1 => 10, 2 => 20, 4 => 25, 10 => 30, 13 => 35, 3 => 55, 5 => 75, 7 => 85, 6 => 90, 32 => 90, 19 => 92, 23 => 92, 8 => 100, 15 => 100];
    return $map[$statusId] ?? 15;
}

/** The customer's virtual shipping addresses (cdb_virtual_mailbox_addresses, else the company address from settings). */
function sl_customerAddresses($userData, $core): array
{
    $name = trim(($userData->fname ?? '') . ' ' . ($userData->lname ?? ''));
    $suite = (string) ($userData->locker ?? '');
    $out = [];
    if (sl_tableExists('cdb_virtual_mailbox_addresses')) {
        foreach (sl_rows('SELECT * FROM cdb_virtual_mailbox_addresses ORDER BY default_address DESC, id ASC') as $r) {
            $label = trim((string) $r->country);
            $out[] = [
                'label' => $label !== '' ? $label : 'Warehouse',
                'cc' => strtoupper(mb_substr($label, 0, 2)),
                'icon' => 'i-plane',
                'recipient' => $name,
                'address1' => trim('c/o ' . ($core->site_name ?? 'Swift Lane') . ' · ' . (string) $r->address),
                'address2' => 'Suite ' . $suite,
                'city' => (string) $r->city,
                'zip' => (string) $r->postcode,
                'phone' => (string) ($core->c_phone ?? ''),
                'note' => (string) $r->country,
            ];
        }
    }
    if (!$out) {
        $out[] = [
            'label' => trim((string) ($core->c_country ?? '')) ?: 'Warehouse',
            'cc' => strtoupper(mb_substr((string) ($core->c_country ?? 'WH'), 0, 2)),
            'icon' => 'i-plane',
            'recipient' => $name,
            'address1' => trim('c/o ' . ($core->site_name ?? 'Swift Lane') . ' · ' . (string) ($core->locker_address ?: $core->c_address)),
            'address2' => 'Suite ' . $suite,
            'city' => (string) ($core->c_city ?? ''),
            'zip' => (string) ($core->c_postal ?? ''),
            'phone' => (string) ($core->c_phone ?? ''),
            'note' => (string) ($core->c_country ?? ''),
        ];
    }
    foreach ($out as &$a) {
        $a['full'] = implode("\n", array_filter([$a['recipient'], $a['address1'], $a['address2'], trim($a['city'] . ' ' . $a['zip']), $a['phone']]));
    }
    return $out;
}

/** Catalogue of customer notification events (key → [label, detail, default wa, sms, email]). */
function sl_notificationEvents(): array
{
    return [
        'warehouse' => ['Package arrived at the warehouse', 'With weight and photo', 1, 0, 1],
        'invoice'   => ['Price ready to pay', 'Amount and pay link', 1, 1, 1],
        'shipped'   => ['Shipped from the warehouse', 'Flight or vessel number', 1, 0, 1],
        'arrived'   => ['Arrived in Ghana', 'Cleared at the hub', 1, 0, 0],
        'ready'     => ['Ready for pickup', 'With your pickup code', 1, 1, 1],
        'delivery'  => ['Out for delivery', 'Rider name and phone', 1, 1, 0],
        'delivered' => ['Delivered', 'Proof of delivery photo', 1, 0, 1],
        'prealert'  => ['Pre-alert received', 'We matched your pre-alert', 0, 0, 1],
        'reminder'  => ['Bill reminders', 'Unpaid after 7 days', 1, 0, 1],
    ];
}

/** Store a user preference (cdb_user_preferences). */
function sl_prefSet(int $userId, string $key, string $value): bool
{
    if (!sl_tableExists('cdb_user_preferences')) {
        return false;
    }
    $db = new Conexion();
    $db->cdp_query('INSERT INTO cdb_user_preferences (user_id, pref_key, pref_value, updated_at) VALUES (:u, :k, :v, NOW())
        ON DUPLICATE KEY UPDATE pref_value = VALUES(pref_value), updated_at = NOW()');
    $db->bind(':u', $userId);
    $db->bind(':k', $key);
    $db->bind(':v', mb_substr($value, 0, 190));
    return (bool) $db->cdp_execute();
}

function sl_prefs(int $userId): array
{
    if (!sl_tableExists('cdb_user_preferences')) {
        return [];
    }
    $out = [];
    foreach (sl_rows('SELECT pref_key, pref_value FROM cdb_user_preferences WHERE user_id = :u', [':u' => $userId]) as $r) {
        $out[$r->pref_key] = (string) $r->pref_value;
    }
    return $out;
}

/** Validate and store an uploaded image under assets/uploads/<subdir>/. Returns ['ok', 'path' (relative to app root) | 'error']. */
function sl_storeImage(array $file, string $subdir, string $prefix, int $maxBytes = 5242880): array
{
    if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'No file was uploaded.'];
    }
    if ((int) $file['size'] > $maxBytes) {
        return ['ok' => false, 'error' => 'The file must be under ' . round($maxBytes / 1048576) . ' MB.'];
    }
    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    if (!$info || !isset($types[$info[2]])) {
        return ['ok' => false, 'error' => 'Only JPG, PNG, WEBP or GIF pictures are accepted.'];
    }
    $dir = CDP_ROOT . '/assets/uploads/' . trim($subdir, '/') . '/';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return ['ok' => false, 'error' => 'The upload folder cannot be created.'];
    }
    $name = preg_replace('/[^a-z0-9_]/', '', strtolower($prefix)) . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $types[$info[2]];
    if (!@move_uploaded_file($file['tmp_name'], $dir . $name)) {
        return ['ok' => false, 'error' => 'The file could not be saved.'];
    }
    return ['ok' => true, 'path' => 'assets/uploads/' . trim($subdir, '/') . '/' . $name, 'w' => $info[0], 'h' => $info[1]];
}

/**
 * Background pictures + captions markup for an auth page.
 * $variant 'lg' = Login board (.lg-bg/.lg-cap), 'fs' = the split boards (.fs-bg/.fs-cap).
 * Returns ['bgs' => html, 'caps' => html, 'n' => count, 'every' => seconds, 'style' => css vars] (all pre-escaped).
 */
function sl_loginPicturesHtml(string $page, string $variant = 'fs'): array
{
    $p = sl_loginPictures($page);
    $slides = $p['show'] ? $p['slides'] : [];
    $n = max(1, count($slides));
    $every = $p['interval'];
    $bgCls = $variant === 'lg' ? 'lg-bg' : 'fs-bg';
    $bgs = '';
    $caps = '';
    foreach ($slides as $i => $s) {
        $sty = 'animation-delay:' . ($i * $every) . 's;animation-duration:' . ($n * $every) . 's;filter:blur(var(--sl-blur,8px));';
        if ($s['url'] !== '') {
            $sty .= 'background-image:url(' . h($s['url']) . ');';
        }
        $bgs .= '<div class="' . $bgCls . ' ' . h($s['cls']) . '" style="' . $sty . '"></div>';
        $csty = 'animation-delay:' . ($i * $every) . 's;animation-duration:' . ($n * $every) . 's;';
        if ($variant === 'lg') {
            $caps .= '<div style="' . $csty . '"><b>' . h($s['title']) . '</b><div>' . h($s['sub']) . '</div></div>';
        } else {
            $caps .= '<div style="' . $csty . '"><span class="fs-ic"><i class="ic i-image" aria-hidden="true"></i></span><span class="up" style="color:rgba(255,255,255,.7)">' . h($s['title']) . '</span><h2>' . h($s['sub']) . '</h2></div>';
        }
    }
    $dots = '';
    for ($i = 0; $i < count($slides); $i++) {
        $dots .= '<i class="' . ($i === 0 ? 'on' : '') . '" style="animation-duration:' . ($n * $every) . 's"></i>';
    }
    return ['bgs' => $bgs, 'caps' => $caps, 'dots' => $dots, 'n' => count($slides), 'every' => $every, 'style' => $p['style'], 'captions' => $p['captions'] && $slides, 'texts' => $p['texts']];
}

/**
 * Should a message go out? For senders (WhatsApp/SMS/e-mail) to consult before sending a
 * customer update. $channel whatsapp|sms|email, $event a key of sl_notificationEvents().
 * Security codes must not call this (they are always sent).
 */
function sl_notifAllowed(int $userId, string $channel, string $event): bool
{
    $events = sl_notificationEvents();
    if (!isset($events[$event])) {
        return true;
    }
    $prefs = sl_prefs($userId);
    if (($prefs['notif_paused'] ?? '0') === '1') {
        return false;
    }
    if (in_array($channel, ['whatsapp', 'sms'], true) && ($prefs['notif_quiet'] ?? '1') === '1') {
        $now = date('H:i');
        $from = $prefs['notif_quiet_from'] ?? '21:00';
        $to = $prefs['notif_quiet_to'] ?? '07:00';
        $inQuiet = $from <= $to ? ($now >= $from && $now < $to) : ($now >= $from || $now < $to);
        if ($inQuiet && !in_array($event, ['delivery'], true)) {
            return false;
        }
    }
    if (!sl_tableExists('cdb_user_notification_prefs')) {
        return true;
    }
    $rows = sl_rows("SELECT event_key, enabled FROM cdb_user_notification_prefs WHERE user_id = :u AND channel = :c AND event_key IN ('*', :e)", [':u' => $userId, ':c' => $channel, ':e' => $event]);
    $map = [];
    foreach ($rows as $r) {
        $map[$r->event_key] = (int) $r->enabled;
    }
    if (($map['*'] ?? 1) === 0) {
        return false;
    }
    $idx = ['whatsapp' => 2, 'sms' => 3, 'email' => 4][$channel] ?? 2;
    return ($map[$event] ?? $events[$event][$idx]) === 1;
}
