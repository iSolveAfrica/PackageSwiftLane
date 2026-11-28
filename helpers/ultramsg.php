<?php
// *************************************************************************
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *************************************************************************

/**
 * UltraMsg client for the in-app WhatsApp screen (whatsapp_web.php,
 * ajax/comms/wa_web_ajax.php).
 *
 * The instance is the one the app already sends with: cdb_settings.api_ws_url
 * (https://api.ultramsg.com/<instance>) and api_ws_token (Settings > General >
 * Messaging). Every call is defensive: a transport error, a refused token or
 * an endpoint the provider does not have (404) comes back as a result with a
 * code, never an exception, and the screen hides that feature with a note
 * (cdp_umCap). Paths used (token always as a parameter):
 *   GET  instance/status, instance/qr (PNG), instance/qrCode, instance/me, instance/settings
 *   POST instance/settings, instance/logout, instance/restart, instance/takeover
 *   GET  chats, chats/messages?chatId=&limit=, contacts, groups, groups/group?groupId=
 *   POST chats/read, messages/chat, messages/image, messages/document
 *
 * A local copy (cdb_wa_chats, cdb_wa_messages, cdb_wa_contacts_cache,
 * cdb_wa_groups; migrations/2026_11_27_wa_web.sql) keeps the screen fast and
 * the history readable while the provider is unreachable. It is filled by
 * polling (throttled here) and by the provider's webhook when it is pointed at
 * webhooks/whatsapp.php (cdp_umWebhookIngest).
 *
 * Contacts saved from WhatsApp go to cdb_comm_contacts, which campaigns can
 * reach (the owner's choice, helpers/comms_campaigns.php). A STOP from a
 * number marks it opted out; only a START from the same number clears it.
 *
 * The normalisers (cdp_umNorm*, cdp_umAck, cdp_umTs, cdp_umPhoneOf) are pure.
 */

require_once __DIR__ . '/ops_db.php';

if (!defined('CDP_UM_SCHEMA')) {
    define('CDP_UM_SCHEMA', '2');
}

// ---------------------------------------------------------------------------
// Configuration, transport
// ---------------------------------------------------------------------------

if (!function_exists('cdp_umConfig')) {

    /** The instance the app sends with. ['configured', 'base', 'token', 'instance', 'active']. */
    function cdp_umConfig()
    {
        static $cfg = null;
        if ($cfg !== null && empty($GLOBALS['cdp_um_cfg_fresh'])) {
            return $cfg;
        }
        $s = function_exists('cdp_getSettingsCourier') ? cdp_getSettingsCourier() : null;
        if (!$s) {
            $s = cdp_opsOne("SELECT api_ws_url, api_ws_token, active_whatsapp FROM cdb_settings LIMIT 1");
        }
        $base = rtrim(trim((string) ($s->api_ws_url ?? '')), '/');
        $token = trim((string) ($s->api_ws_token ?? ''));
        $instance = $base !== '' ? (string) basename((string) parse_url($base, PHP_URL_PATH)) : '';
        $problem = $base !== '' && $token !== '' ? cdp_umUrlProblem($base) : '';
        $cfg = ['configured' => $base !== '' && $token !== '' && $problem === '', 'base' => $base, 'token' => $token,
            'instance' => $instance, 'active' => (int) ($s->active_whatsapp ?? 0) === 1, 'problem' => $problem];
        return $cfg;
    }

    /** A local mock provider may be used only when the installation says so (tests). */
    function cdp_umAllowLocal()
    {
        return (defined('CDP_UM_ALLOW_LOCAL_MOCK') && CDP_UM_ALLOW_LOCAL_MOCK === true) || getenv('CDP_UM_ALLOW_LOCAL_MOCK') === '1';
    }

    /**
     * Why the instance address cannot be used ('' when it can). The token is
     * sent there, so it must be https and a public host (a local mock only
     * with CDP_UM_ALLOW_LOCAL_MOCK).
     */
    function cdp_umUrlProblem($base)
    {
        $base = (string) $base;
        if (!preg_match('#^https?://#i', $base)) {
            return 'The instance address must start with https://, e.g. https://api.ultramsg.com/instance12345.';
        }
        if (cdp_umAllowLocal()) {
            return '';
        }
        if (stripos($base, 'https://') !== 0) {
            return 'The instance address must use https:// so the token is never sent in clear text.';
        }
        $host = strtolower((string) parse_url($base, PHP_URL_HOST));
        if ($host === '' || $host === 'localhost' || substr($host, -6) === '.local' || substr($host, -9) === '.internal' || strpos($host, '.') === false) {
            return 'The instance address must be the provider\'s public address (e.g. https://api.ultramsg.com/instance12345).';
        }
        if (filter_var($host, FILTER_VALIDATE_IP) && !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return 'The instance address points at a private network address. Use the provider\'s public address.';
        }
        return '';
    }

    /**
     * One API call. Never throws.
     *
     * @param string $method GET | POST
     * @param string $path   e.g. 'instance/status'
     * @param array  $o      timeout (s), binary (true: return the body raw)
     * @return array{ok:bool,http:int,data:mixed,raw:string,ctype:string,error:string,code:string}
     *   code: '' | not_configured | transport | auth | unsupported | api
     */
    function cdp_umCall($method, $path, array $params = [], array $o = [])
    {
        $res = ['ok' => false, 'http' => 0, 'data' => null, 'raw' => '', 'ctype' => '', 'error' => '', 'code' => ''];
        $cfg = cdp_umConfig();
        if (!$cfg['configured']) {
            return ['error' => $cfg['problem'] !== '' ? $cfg['problem'] : 'WhatsApp (UltraMsg) is not set up: the instance address and token are empty.', 'code' => 'not_configured'] + $res;
        }
        if (isset($GLOBALS['cdp_umTestTransport']) && is_callable($GLOBALS['cdp_umTestTransport'])) {
            $r = call_user_func($GLOBALS['cdp_umTestTransport'], $method, $path, $params, $o);
            return array_merge($res, (array) $r);
        }
        if (!function_exists('curl_init')) {
            return ['error' => 'The PHP cURL extension is missing.', 'code' => 'transport'] + $res;
        }
        $params = ['token' => $cfg['token']] + $params;
        $url = $cfg['base'] . '/' . ltrim($path, '/');
        $ch = curl_init();
        $opt = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => (int) ($o['timeout'] ?? 12),
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => ['Accept: application/json, image/png;q=0.9, */*;q=0.5'],
        ];
        if (strtoupper($method) === 'POST') {
            $opt[CURLOPT_URL] = $url;
            $opt[CURLOPT_POST] = true;
            $opt[CURLOPT_POSTFIELDS] = http_build_query($params);
            $opt[CURLOPT_HTTPHEADER][] = 'Content-Type: application/x-www-form-urlencoded';
        } else {
            $opt[CURLOPT_URL] = $url . '?' . http_build_query($params);
        }
        curl_setopt_array($ch, $opt);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ctype = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        $res['http'] = $http;
        $res['ctype'] = $ctype;
        if ($body === false || $err !== '') {
            return ['error' => 'Connection failed: ' . rtrim(cdp_opsClip($err ?: 'no answer', 160), '.') . '.', 'code' => 'transport'] + $res;
        }
        $res['raw'] = (string) $body;
        if ($http === 404 || $http === 405 || $http === 501) {
            return ['error' => 'The provider does not offer this (' . $path . ').', 'code' => 'unsupported'] + $res;
        }
        if ($http === 401 || $http === 403) {
            return ['error' => 'The provider refused the instance token.', 'code' => 'auth'] + $res;
        }
        if (!empty($o['binary']) && $http >= 200 && $http < 300 && stripos($ctype, 'json') === false) {
            $res['ok'] = true;
            return $res;
        }
        $data = json_decode((string) $body, true);
        $res['data'] = $data;
        if ($http < 200 || $http >= 300) {
            return ['error' => 'The provider answered HTTP ' . $http . '.', 'code' => $http >= 500 ? 'transport' : 'api'] + $res;
        }
        if (!is_array($data)) {
            return ['error' => 'The provider sent an answer this screen cannot read.', 'code' => 'api'] + $res;
        }
        if (isset($data['error']) && $data['error'] !== '' && $data['error'] !== false && !isset($data[0])) {
            $msg = is_scalar($data['error']) ? (string) $data['error'] : cdp_opsJson($data['error']);
            $code = preg_match('/token|unauthori[sz]ed|forbidden/i', $msg) ? 'auth' : (preg_match('/not\s*found|unknown (path|route|method)|no route/i', $msg) ? 'unsupported' : 'api');
            return ['error' => cdp_opsClip($msg, 200), 'code' => $code] + $res;
        }
        $res['ok'] = true;
        return $res;
    }
}

// ---------------------------------------------------------------------------
// Tables, settings, capabilities
// ---------------------------------------------------------------------------

if (!function_exists('cdp_umEnsureTables')) {

    /** Creates the cache tables once (migrations/2026_11_27_wa_web.sql). */
    function cdp_umEnsureTables()
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        if (cdp_opsSetting('waweb', 'schema', '') === CDP_UM_SCHEMA && cdp_opsTableExists('cdb_wa_chats') && cdp_opsTableExists('cdb_comm_contacts')) {
            return $ready = true;
        }
        $file = dirname(__DIR__) . '/migrations/2026_11_27_wa_web.sql';
        $sql = is_file($file) ? (string) file_get_contents($file) : '';
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        $stmts = array_values(array_filter(array_map('trim', preg_split('/;\s*(\r?\n|$)/', $sql)), function ($s) { return stripos($s, 'CREATE TABLE') === 0; }));
        $ok = $stmts && cdp_opsDdl($stmts);
        if ($ok) {
            // Schema 2: an index for the group authors' names, and the opted-out count of a save.
            if (!cdp_opsVal("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_wa_messages' AND INDEX_NAME = 'idx_author'", [], 0)) {
                $ok = cdp_opsDdl(["ALTER TABLE cdb_wa_messages ADD KEY idx_author (author)"]);
            }
            if ($ok && !cdp_opsColumnExists('cdb_wa_exports', 'opted_out_count')) {
                $ok = cdp_opsDdl(["ALTER TABLE cdb_wa_exports ADD COLUMN opted_out_count INT NOT NULL DEFAULT 0 AFTER saved_count"]);
            }
        }
        if ($ok) {
            cdp_opsSettingSet('waweb', 'schema', CDP_UM_SCHEMA, 0);
        }
        return $ready = (bool) $ok;
    }

    function cdp_umSetting($k, $default = '')
    {
        return cdp_opsSetting('waweb', $k, $default);
    }

    function cdp_umSettingSet($k, $v)
    {
        return cdp_opsSettingSet('waweb', $k, is_array($v) ? cdp_opsJson($v) : (string) $v);
    }

    /**
     * Features the provider turned out not to have (an endpoint answered 404).
     * cdp_umCap('groups') -> true (unknown or available) | false (hidden).
     * cdp_umCap('groups', false) records it for a day.
     */
    function cdp_umCap($name, $set = null)
    {
        $caps = cdp_opsDecode(cdp_umSetting('caps', ''), []);
        if ($set !== null) {
            $caps[$name] = ['on' => (bool) $set, 'at' => time()];
            cdp_umSettingSet('caps', $caps);
            return (bool) $set;
        }
        if (!isset($caps[$name])) {
            return true;
        }
        if (!$caps[$name]['on'] && time() - (int) $caps[$name]['at'] > 86400) {
            return true; // look again once a day: the provider may have added it
        }
        return (bool) $caps[$name]['on'];
    }

    function cdp_umCapsReset()
    {
        cdp_umSettingSet('caps', []);
    }

    /** Throttle: true at most once per $seconds for $key (shared by every viewer). */
    function cdp_umDue($key, $seconds)
    {
        $k = 'due_' . $key;
        $now = time();
        $last = (int) cdp_umSetting($k, '0');
        if ($now - $last < $seconds) {
            return false;
        }
        if (!cdp_opsVal("SELECT COUNT(*) FROM cdb_ops_settings WHERE ns = 'waweb' AND setting_key = :k", [':k' => $k], 0)) {
            cdp_opsX("INSERT IGNORE INTO cdb_ops_settings (ns, setting_key, setting_value, updated_at) VALUES ('waweb', :k, '0', :n)", [':k' => $k, ':n' => cdp_opsNow()]);
        }
        // Atomic claim: of several viewers polling at the same moment, one row update wins.
        $r = cdp_opsX("UPDATE cdb_ops_settings SET setting_value = :v, updated_at = :n WHERE ns = 'waweb' AND setting_key = :k AND CAST(setting_value AS UNSIGNED) <= :cut",
            [':v' => (string) $now, ':n' => cdp_opsNow(), ':k' => $k, ':cut' => $now - $seconds]);
        cdp_opsSettings('waweb', true);
        return !empty($r['ok']) && (int) $r['rows'] === 1;
    }

    /**
     * The built-in quick replies (also what "Restore defaults" brings back).
     * Facts the app cannot know for sure stay placeholders: {hours} is the
     * Support desk's business hours, left in braces when none are saved.
     */
    function cdp_umQuickRepliesDefault()
    {
        return [
            ['title' => 'Greeting', 'body' => 'Hello {first_name}, thank you for contacting Swift Lane. How can we help you today?'],
            ['title' => 'Parcel ready', 'body' => 'Hi {first_name}, your parcel is ready for pickup at our office. Please bring your locker number *{locker}*.'],
            ['title' => 'Ask for tracking', 'body' => 'Could you please send us your tracking number so we can check for you?'],
            ['title' => 'Opening hours', 'body' => 'Our office hours: {hours}.'],
            ['title' => 'Balance due', 'body' => 'Hi {first_name}, the outstanding balance on your account is {balance}. You can pay at the office or online.'],
            ['title' => 'Checking', 'body' => 'Thank you for waiting. A colleague is checking this and will get back to you shortly.'],
        ];
    }

    /** Company facts the quick replies may use ({hours}), '' when not saved. */
    function cdp_umQuickVars()
    {
        $hours = '';
        if (is_file(__DIR__ . '/support.php')) {
            try {
                require_once __DIR__ . '/support.php';
                if (function_exists('cdp_csSetting')) {
                    $hours = trim((string) cdp_csSetting('business_hours', ''));
                }
            } catch (Throwable $e) {
                $hours = '';
            }
        }
        return ['hours' => mb_substr($hours, 0, 200)];
    }

    /** Quick replies [{title, body}] (admins edit them on the page). */
    function cdp_umQuickReplies()
    {
        $q = cdp_opsDecode(cdp_umSetting('quick_replies', ''), null);
        if (!is_array($q)) {
            $q = cdp_umQuickRepliesDefault();
        }
        $out = [];
        foreach ($q as $r) {
            $t = mb_substr(trim((string) ($r['title'] ?? '')), 0, 60);
            $b = mb_substr(trim((string) ($r['body'] ?? '')), 0, 1500);
            if ($t !== '' && $b !== '') {
                $out[] = ['title' => $t, 'body' => $b];
            }
        }
        return array_slice($out, 0, 40);
    }
}

// ---------------------------------------------------------------------------
// Pure normalisers (unit tested)
// ---------------------------------------------------------------------------

if (!function_exists('cdp_umIdOf')) {

    /** A WhatsApp id given as a string or {_serialized, user, server}. */
    function cdp_umIdOf($v)
    {
        if (is_array($v)) {
            if (!empty($v['_serialized'])) {
                return (string) $v['_serialized'];
            }
            if (!empty($v['user'])) {
                return (string) $v['user'] . '@' . (string) ($v['server'] ?? 'c.us');
            }
            return '';
        }
        return trim((string) $v);
    }

    /** Is this a chat id the screen shows (person or group)? */
    function cdp_umValidChatId($id)
    {
        return is_string($id) && preg_match('/^[0-9]{5,20}(-[0-9]{5,14})?@(c|g)\.us$/', $id) === 1;
    }

    /** Digits of a person's chat id ('' for a group). */
    function cdp_umPhoneOf($chatId)
    {
        $chatId = (string) $chatId;
        if (substr($chatId, -5) !== '@c.us') {
            return '';
        }
        return preg_replace('/\D+/', '', substr($chatId, 0, -5));
    }

    /** Seconds since 1970 from seconds, milliseconds or a date string. */
    function cdp_umTs($v)
    {
        if (is_numeric($v)) {
            $n = (int) $v;
            return $n > 100000000000 ? intdiv($n, 1000) : $n;
        }
        if (is_string($v) && $v !== '') {
            $t = strtotime($v);
            return $t ?: 0;
        }
        return 0;
    }

    /** pending | sent | delivered | read | played | failed | '' (incoming). */
    function cdp_umAck($v)
    {
        if ($v === null || $v === '') {
            return '';
        }
        if (is_numeric($v)) {
            $map = [-1 => 'failed', 0 => 'pending', 1 => 'sent', 2 => 'delivered', 3 => 'read', 4 => 'played'];
            return $map[(int) $v] ?? '';
        }
        $s = strtolower(trim((string) $v));
        $map = ['error' => 'failed', 'failed' => 'failed', 'pending' => 'pending', 'queue' => 'pending', 'server' => 'sent', 'sent' => 'sent',
            'device' => 'delivered', 'delivered' => 'delivered', 'read' => 'read', 'viewed' => 'read', 'played' => 'played'];
        return $map[$s] ?? '';
    }

    /** Rank of an ack, so an older poll never moves a tick backwards. */
    function cdp_umAckRank($a)
    {
        return ['' => 0, 'failed' => 1, 'pending' => 2, 'sent' => 3, 'delivered' => 4, 'read' => 5, 'played' => 6][(string) $a] ?? 0;
    }

    /** One chat from the provider -> the screen's shape, or null to skip it. */
    function cdp_umNormChat(array $c)
    {
        $id = cdp_umIdOf($c['id'] ?? ($c['chatId'] ?? ''));
        if (!cdp_umValidChatId($id)) {
            return null;
        }
        $group = !empty($c['isGroup']) || substr($id, -5) === '@g.us';
        $name = trim((string) ($c['name'] ?? ($c['formattedTitle'] ?? ($c['pushname'] ?? ''))));
        $unread = $c['unreadCount'] ?? ($c['unread'] ?? null);
        return [
            'chat_id' => $id, 'is_group' => $group ? 1 : 0, 'name' => mb_substr($name, 0, 190), 'phone' => $group ? '' : cdp_umPhoneOf($id),
            'unread' => $unread === null ? null : max(0, (int) $unread),
            'ts' => cdp_umTs($c['timestamp'] ?? ($c['last_time'] ?? ($c['t'] ?? 0))),
            'pinned' => !empty($c['pinned']) ? 1 : 0, 'muted' => !empty($c['isMuted']) || !empty($c['muted']) ? 1 : 0, 'archived' => !empty($c['archived']) ? 1 : 0,
        ];
    }

    /** text | image | video | audio | document | sticker | location | contact | other. */
    function cdp_umType($t)
    {
        $t = strtolower((string) $t);
        $map = ['chat' => 'text', 'text' => 'text', 'image' => 'image', 'video' => 'video', 'gif' => 'video', 'audio' => 'audio', 'ptt' => 'audio', 'voice' => 'audio',
            'document' => 'document', 'sticker' => 'sticker', 'location' => 'location', 'vcard' => 'contact', 'multi_vcard' => 'contact', 'contact' => 'contact'];
        return $map[$t] ?? ($t === '' ? 'text' : 'other');
    }

    /**
     * One message from the provider (chats/messages, webhook data) -> the
     * screen's shape, or null. $chatId is the chat it was read from.
     */
    function cdp_umNormMessage(array $m, $chatId = '')
    {
        $id = cdp_umIdOf($m['id'] ?? '');
        if ($id === '' || strlen($id) > 190) {
            return null;
        }
        $fromMe = !empty($m['fromMe']) && $m['fromMe'] !== 'false';
        $from = cdp_umIdOf($m['from'] ?? '');
        $to = cdp_umIdOf($m['to'] ?? '');
        $chat = (string) $chatId !== '' ? (string) $chatId : ($fromMe ? $to : $from);
        if (!cdp_umValidChatId($chat)) {
            return null;
        }
        $type = cdp_umType($m['type'] ?? 'chat');
        $body = (string) ($m['body'] ?? '');
        $caption = (string) ($m['caption'] ?? '');
        $thumb = '';
        // whatsapp-web.js style: the body of an image / video / sticker is its base64 thumbnail.
        if (in_array($type, ['image', 'video', 'sticker'], true) && strlen($body) > 200 && preg_match('/^[A-Za-z0-9+\/=\s]+$/', $body)) {
            $thumb = strlen($body) <= 120000 ? 'data:image/jpeg;base64,' . preg_replace('/\s+/', '', $body) : '';
            $body = '';
        }
        if ($type !== 'text' && $caption !== '' && $body === '') {
            $body = $caption;
        }
        if ($type === 'location' && $body === '' && isset($m['location']['latitude'])) {
            $body = $m['location']['latitude'] . ',' . $m['location']['longitude'];
        }
        $media = (string) ($m['media'] ?? ($m['mediaUrl'] ?? ($m['url'] ?? '')));
        if (!preg_match('#^https?://#i', $media)) {
            $media = '';
        }
        $author = '';
        if (substr($chat, -5) === '@g.us' && !$fromMe) {
            $author = cdp_umIdOf($m['author'] ?? ($m['participant'] ?? ''));
        }
        return [
            'msg_id' => $id, 'chat_id' => $chat, 'from_me' => $fromMe ? 1 : 0, 'author' => mb_substr($author, 0, 80),
            'author_name' => mb_substr(trim((string) ($m['pushname'] ?? ($m['notifyName'] ?? ''))), 0, 150),
            'type' => $type, 'body' => mb_substr($body, 0, 20000), 'media_url' => mb_substr($media, 0, 1000), 'thumb' => $thumb,
            'filename' => mb_substr((string) ($m['filename'] ?? ($m['fileName'] ?? '')), 0, 255),
            'ack' => $fromMe ? (cdp_umAck($m['ack'] ?? '') ?: 'sent') : '',
            'ts' => cdp_umTs($m['timestamp'] ?? ($m['time'] ?? ($m['t'] ?? 0))) ?: time(),
        ];
    }

    /** instance/status -> [state, substatus]. state: connected | qr | loading | standby | disconnected | unknown. */
    function cdp_umNormStatus($data)
    {
        $acc = is_array($data) ? ($data['status']['accountStatus'] ?? ($data['accountStatus'] ?? ($data['status'] ?? null))) : null;
        $st = is_array($acc) ? (string) ($acc['status'] ?? '') : (is_string($acc) ? $acc : '');
        $sub = is_array($acc) ? (string) ($acc['substatus'] ?? '') : '';
        $s = strtolower($st);
        $state = 'unknown';
        if ($s === 'authenticated' || $s === 'connected') {
            $state = 'connected';
        } elseif ($s === 'qr' || $s === 'got qr code' || $s === 'unpaired') {
            $state = 'qr';
        } elseif (in_array($s, ['loading', 'initialize', 'init', 'opening', 'pairing', 'retrying'], true)) {
            $state = 'loading';
        } elseif ($s === 'standby' || $s === 'conflict') {
            $state = 'standby';
        } elseif (in_array($s, ['disconnected', 'stopped', 'expired', 'logout'], true)) {
            $state = 'disconnected';
        }
        return ['state' => $state, 'raw' => $st, 'substatus' => $sub];
    }

    /** Participants from groups/group or groups (several shapes) -> [{id, phone, admin}]. */
    function cdp_umNormParticipants($list)
    {
        $out = [];
        foreach ((array) $list as $p) {
            $id = is_array($p) ? cdp_umIdOf($p['id'] ?? ($p['jid'] ?? '')) : cdp_umIdOf($p);
            if ($id === '' || substr($id, -5) !== '@c.us') {
                continue;
            }
            $out[$id] = ['id' => $id, 'phone' => cdp_umPhoneOf($id),
                'admin' => is_array($p) && (!empty($p['isAdmin']) || !empty($p['isSuperAdmin']) || in_array((string) ($p['admin'] ?? ''), ['admin', 'superadmin'], true)) ? 1 : 0];
        }
        return array_values($out);
    }

    /** The participants array inside one group object, whatever its shape. */
    function cdp_umGroupParticipantList(array $g)
    {
        foreach ([['participants'], ['groupMetadata', 'participants'], ['metadata', 'participants'], ['members']] as $path) {
            $cur = $g;
            foreach ($path as $k) {
                $cur = is_array($cur) && array_key_exists($k, $cur) ? $cur[$k] : null;
            }
            if (is_array($cur)) {
                return $cur;
            }
        }
        return null;
    }

    /** WhatsApp text formatting -> safe HTML (*bold*, _italic_, ~strike~, ```mono```). */
    function cdp_umFormatHtml($text)
    {
        $h = htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
        $h = preg_replace('/```([\s\S]+?)```/', '<code>$1</code>', $h);
        $h = preg_replace('/(^|[\s(])\*(?!\s)([^*\n]+?)\*(?=$|[\s.,!?:;)])/u', '$1<b>$2</b>', $h);
        $h = preg_replace('/(^|[\s(])_(?!\s)([^_\n]+?)_(?=$|[\s.,!?:;)])/u', '$1<i>$2</i>', $h);
        $h = preg_replace('/(^|[\s(])~(?!\s)([^~\n]+?)~(?=$|[\s.,!?:;)])/u', '$1<s>$2</s>', $h);
        return nl2br($h);
    }

    /** A spreadsheet-safe display phone: +233 24 123 4567 style is left to the viewer; we keep +digits. */
    function cdp_umPlusPhone($digits)
    {
        $d = preg_replace('/\D+/', '', (string) $digits);
        return $d === '' ? '' : '+' . $d;
    }
}

// ---------------------------------------------------------------------------
// Instance: status, QR, phone, admin actions, webhook
// ---------------------------------------------------------------------------

if (!function_exists('cdp_umStatus')) {

    /** Connection state, cached ~8 s across viewers. */
    function cdp_umStatus($fresh = false)
    {
        $cfg = cdp_umConfig();
        if (!$cfg['configured']) {
            return ['state' => 'not_configured', 'raw' => '', 'substatus' => '', 'error' => '', 'at' => time()];
        }
        $c = cdp_opsDecode(cdp_umSetting('status_cache', ''), []);
        if (!$fresh && !empty($c['at']) && time() - (int) $c['at'] < 8 && isset($c['state'])) {
            return $c;
        }
        $r = cdp_umCall('GET', 'instance/status', [], ['timeout' => 8]);
        if ($r['ok']) {
            $s = cdp_umNormStatus($r['data']) + ['error' => ''];
        } else {
            $s = ['state' => $r['code'] === 'auth' ? 'auth' : 'unreachable', 'raw' => '', 'substatus' => '', 'error' => $r['error']];
        }
        $s['at'] = time();
        cdp_umSettingSet('status_cache', $s);
        return $s;
    }

    /** QR to link the phone: ['image' => data URI | '', 'text' => qr string | '', 'error']. */
    function cdp_umQr()
    {
        $out = ['image' => '', 'text' => '', 'error' => ''];
        $r = cdp_umCall('GET', 'instance/qr', [], ['binary' => true, 'timeout' => 10]);
        if ($r['ok'] && $r['data'] === null && $r['raw'] !== '' && strlen($r['raw']) < 400000) {
            $mime = stripos($r['ctype'], 'svg') !== false ? 'image/svg+xml' : (stripos($r['ctype'], 'jpeg') !== false ? 'image/jpeg' : 'image/png');
            if ($mime === 'image/svg+xml' || @getimagesizefromstring($r['raw']) !== false) {
                $out['image'] = 'data:' . $mime . ';base64,' . base64_encode($r['raw']);
                return $out;
            }
        }
        $t = cdp_umCall('GET', 'instance/qrCode', [], ['timeout' => 8]);
        if ($t['ok'] && is_array($t['data'])) {
            $out['text'] = mb_substr((string) ($t['data']['qrCode'] ?? ($t['data']['qr'] ?? '')), 0, 2000);
        }
        if ($out['text'] === '') {
            $out['error'] = $r['error'] ?: ($t['error'] ?: 'The provider did not return a QR code. Restart the instance and try again.');
        }
        return $out;
    }

    /** The linked phone: ['id', 'phone', 'name', 'picture'] or null. */
    function cdp_umMe()
    {
        $c = cdp_opsDecode(cdp_umSetting('me_cache', ''), []);
        if (!empty($c['at']) && time() - (int) $c['at'] < 300) {
            return $c['me'] ?? null;
        }
        $r = cdp_umCall('GET', 'instance/me', [], ['timeout' => 8]);
        $me = null;
        if ($r['ok'] && is_array($r['data'])) {
            $id = cdp_umIdOf($r['data']['id'] ?? ($r['data']['wid'] ?? ''));
            $pic = (string) ($r['data']['profile_picture'] ?? ($r['data']['profilePicture'] ?? ''));
            $me = ['id' => $id, 'phone' => cdp_umPhoneOf($id) ?: preg_replace('/\D+/', '', explode('@', $id)[0]), 'name' => mb_substr((string) ($r['data']['name'] ?? ($r['data']['pushname'] ?? '')), 0, 120),
                'picture' => preg_match('#^https://#i', $pic) ? $pic : ''];
        } elseif ($r['code'] === 'unsupported') {
            cdp_umCap('me', false);
        }
        cdp_umSettingSet('me_cache', ['at' => time(), 'me' => $me]);
        return $me;
    }

    /** logout | restart | takeover. */
    function cdp_umInstanceAction($op)
    {
        if (!in_array($op, ['logout', 'restart', 'takeover'], true)) {
            return ['ok' => false, 'error' => 'Unknown action.'];
        }
        $r = cdp_umCall('POST', 'instance/' . $op, [], ['timeout' => 20]);
        cdp_umSettingSet('status_cache', []);
        cdp_umSettingSet('me_cache', []);
        if ($r['ok'] && $op !== 'restart') {
            cdp_umCapsReset();
        }
        return ['ok' => $r['ok'], 'error' => $r['ok'] ? '' : $r['error'], 'code' => $r['code']];
    }

    /** Instance webhook settings (only the ones the screen explains). */
    function cdp_umWebhookSettings()
    {
        $r = cdp_umCall('GET', 'instance/settings', [], ['timeout' => 8]);
        if (!$r['ok'] || !is_array($r['data'])) {
            return ['ok' => false, 'error' => $r['error'], 'code' => $r['code']];
        }
        $d = $r['data'];
        $on = function ($k) use ($d) { return isset($d[$k]) && ($d[$k] === true || $d[$k] === 'true' || $d[$k] === 1 || $d[$k] === '1'); };
        $url = (string) ($d['webhook_url'] ?? '');
        return ['ok' => true, 'url_set' => $url !== '', 'points_here' => $url !== '' && strpos($url, '/webhooks/whatsapp.php') !== false,
            'received' => $on('webhook_message_received'), 'create' => $on('webhook_message_create'), 'ack' => $on('webhook_message_ack')];
    }

    /**
     * Point the instance's webhook at this system (received, sent from the
     * phone, ticks), so the screen and the inbox update live.
     */
    function cdp_umWebhookSetup($webhookUrl)
    {
        if (!preg_match('#^https?://#i', (string) $webhookUrl) || strpos((string) $webhookUrl, '?key=') === false) {
            return ['ok' => false, 'error' => 'The webhook address is not complete yet: save Communications > Settings once so it gets its secret key.'];
        }
        $r = cdp_umCall('POST', 'instance/settings', ['webhook_url' => (string) $webhookUrl, 'webhook_message_received' => 'true',
            'webhook_message_create' => 'true', 'webhook_message_ack' => 'true', 'webhook_message_download_media' => 'true'], ['timeout' => 12]);
        return ['ok' => $r['ok'], 'error' => $r['ok'] ? '' : $r['error'], 'code' => $r['code']];
    }
}

// ---------------------------------------------------------------------------
// Local copy: chats, messages, contacts, groups
// ---------------------------------------------------------------------------

if (!function_exists('cdp_umStoreChat')) {

    /** Insert or update one normalised chat. */
    function cdp_umStoreChat(array $c)
    {
        $now = cdp_opsNow();
        $b = [':c' => $c['chat_id'], ':g' => (int) $c['is_group'], ':n' => $c['name'] !== '' ? $c['name'] : null, ':p' => $c['phone'] !== '' ? $c['phone'] : null,
            ':ts' => (int) $c['ts'], ':pi' => (int) $c['pinned'], ':mu' => (int) $c['muted'], ':ar' => (int) $c['archived'], ':now' => $now];
        $unreadSql = '';
        if ($c['unread'] !== null) {
            $b[':u'] = (int) $c['unread'];
            $unreadSql = ', unread = VALUES(unread)';
        } else {
            $b[':u'] = 0;
        }
        return cdp_opsX("INSERT INTO cdb_wa_chats (chat_id, is_group, name, phone, unread, last_ts, pinned, muted, archived, created_at, updated_at)
                         VALUES (:c, :g, :n, :p, :u, :ts, :pi, :mu, :ar, :now, :now)
                         ON DUPLICATE KEY UPDATE
                           updated_at = IF(GREATEST(last_ts, VALUES(last_ts)) <> last_ts" . ($unreadSql !== '' ? " OR unread <> VALUES(unread)" : '') . " OR COALESCE(name,'') <> COALESCE(VALUES(name), name, ''), VALUES(updated_at), updated_at),
                           name = COALESCE(VALUES(name), name), last_ts = GREATEST(last_ts, VALUES(last_ts)),
                           pinned = VALUES(pinned), muted = VALUES(muted), archived = VALUES(archived)$unreadSql", $b);
    }

    /**
     * Refresh the chat list from the provider (at most every $every seconds
     * unless forced). Returns ['ok', 'error', 'code', 'count'].
     */
    function cdp_umSyncChats($force = false, $every = 15)
    {
        if (!cdp_umCap('chats')) {
            return ['ok' => false, 'error' => 'The provider does not list chats.', 'code' => 'unsupported', 'count' => 0];
        }
        if (!$force && !cdp_umDue('chats', $every)) {
            return ['ok' => true, 'error' => '', 'code' => 'cached', 'count' => 0];
        }
        $r = cdp_umCall('GET', 'chats', [], ['timeout' => 15]);
        if (!$r['ok']) {
            if ($r['code'] === 'unsupported') {
                cdp_umCap('chats', false);
            }
            return ['ok' => false, 'error' => $r['error'], 'code' => $r['code'], 'count' => 0];
        }
        $list = isset($r['data']['chats']) && is_array($r['data']['chats']) ? $r['data']['chats'] : $r['data'];
        $n = 0;
        foreach (array_slice((array) $list, 0, 1000) as $c) {
            $c = is_array($c) ? cdp_umNormChat($c) : null;
            if ($c) {
                cdp_umStoreChat($c);
                $n++;
            }
        }
        cdp_umLinkUsers();
        // A first look at chats never opened here, so the list shows their last message (a few per pass).
        foreach (cdp_opsQ("SELECT chat_id FROM cdb_wa_chats WHERE last_preview IS NULL AND msgs_synced_at IS NULL ORDER BY last_ts DESC LIMIT 2") as $c) {
            cdp_umSyncMessages((string) $c->chat_id, 20, true);
        }
        return ['ok' => true, 'error' => '', 'code' => '', 'count' => $n];
    }

    /** Link one-to-one chats to customer accounts (once per chat; phone match on the last 9 digits). */
    function cdp_umLinkUsers($limit = 60)
    {
        foreach (cdp_opsQ("SELECT id, phone FROM cdb_wa_chats WHERE user_checked = 0 AND is_group = 0 AND phone IS NOT NULL LIMIT " . (int) $limit) as $c) {
            $uid = null;
            $digits = preg_replace('/\D+/', '', (string) $c->phone);
            if (strlen($digits) >= 7) {
                $rows = cdp_opsQ("SELECT id FROM cdb_users WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone,'+',''),' ',''),'-',''),'(',''),')','') LIKE :t LIMIT 2",
                    [':t' => '%' . substr($digits, -9)]);
                $uid = count($rows) === 1 ? (int) $rows[0]->id : null;
            }
            cdp_opsX("UPDATE cdb_wa_chats SET user_id = :u, user_checked = 1 WHERE id = :id", [':u' => $uid, ':id' => (int) $c->id]);
        }
    }

    /**
     * Store one normalised message. Returns 'new' | 'updated' | 'same' | 'error'.
     * New incoming messages apply STOP / START to saved contacts.
     */
    function cdp_umStoreMessage(array $m, $sentBy = null)
    {
        $now = cdp_opsNow();
        $old = cdp_opsOne("SELECT id, ack, body, media_url FROM cdb_wa_messages WHERE msg_id = :m", [':m' => $m['msg_id']]);
        if ($old) {
            $set = [];
            $b = [':id' => (int) $old->id, ':now' => $now];
            if ($m['ack'] !== '' && cdp_umAckRank($m['ack']) > cdp_umAckRank((string) $old->ack)) {
                $set[] = 'ack = :a';
                $b[':a'] = $m['ack'];
            }
            if ($m['media_url'] !== '' && (string) $old->media_url === '') {
                $set[] = 'media_url = :mu';
                $b[':mu'] = $m['media_url'];
            }
            if (!$set) {
                return 'same';
            }
            cdp_opsX("UPDATE cdb_wa_messages SET " . implode(', ', $set) . ", updated_at = :now WHERE id = :id", $b);
            if (isset($b[':a'])) {
                cdp_opsX("UPDATE cdb_wa_chats SET last_ack = :a, updated_at = :now WHERE chat_id = :c AND last_from_me = 1 AND last_ts <= :ts",
                    [':a' => $m['ack'], ':now' => $now, ':c' => $m['chat_id'], ':ts' => (int) $m['ts']]);
            }
            return 'updated';
        }
        $r = cdp_opsX("INSERT IGNORE INTO cdb_wa_messages (msg_id, chat_id, from_me, author, author_name, type, body, media_url, thumb, filename, ack, ts, sent_by, created_at, updated_at)
                       VALUES (:m, :c, :f, :a, :an, :t, :b, :mu, :th, :fn, :ack, :ts, :sb, :now, :now)", [
            ':m' => $m['msg_id'], ':c' => $m['chat_id'], ':f' => (int) $m['from_me'], ':a' => $m['author'] !== '' ? $m['author'] : null,
            ':an' => $m['author_name'] !== '' ? $m['author_name'] : null, ':t' => $m['type'], ':b' => $m['body'], ':mu' => $m['media_url'] !== '' ? $m['media_url'] : null,
            ':th' => $m['thumb'] !== '' ? $m['thumb'] : null, ':fn' => $m['filename'] !== '' ? $m['filename'] : null, ':ack' => $m['ack'] !== '' ? $m['ack'] : null,
            ':ts' => (int) $m['ts'], ':sb' => $sentBy ? (int) $sentBy : null, ':now' => $now]);
        if (!$r['ok']) {
            return 'error';
        }
        if ($r['rows'] === 0) {
            return 'same';
        }
        // The chat row exists even when the message came by webhook before any list sync.
        $group = substr($m['chat_id'], -5) === '@g.us';
        cdp_opsX("INSERT IGNORE INTO cdb_wa_chats (chat_id, is_group, phone, last_ts, created_at, updated_at) VALUES (:c, :g, :p, 0, :now, :now)",
            [':c' => $m['chat_id'], ':g' => $group ? 1 : 0, ':p' => $group ? null : cdp_umPhoneOf($m['chat_id']), ':now' => $now]);
        $preview = $m['body'] !== '' ? $m['body'] : '[' . $m['type'] . ($m['filename'] !== '' ? ': ' . $m['filename'] : '') . ']';
        if ($group && !$m['from_me'] && $m['author_name'] !== '') {
            $preview = $m['author_name'] . ': ' . $preview;
        }
        cdp_opsX("UPDATE cdb_wa_chats SET last_preview = :p, last_from_me = :f, last_ack = :a, last_ts = :ts, updated_at = :now WHERE chat_id = :c AND last_ts <= :ts2",
            [':p' => cdp_opsClip($preview, 250), ':f' => (int) $m['from_me'], ':a' => $m['ack'] !== '' ? $m['ack'] : null, ':ts' => (int) $m['ts'], ':ts2' => (int) $m['ts'],
                ':now' => $now, ':c' => $m['chat_id']]);
        if (!$m['from_me'] && !$group && $m['type'] === 'text') {
            cdp_umKeyword(cdp_umPhoneOf($m['chat_id']), $m['body'], (int) $m['ts'], $m['chat_id']);
        }
        if ($m['from_me'] && strpos($m['msg_id'], 'local_') !== 0) {
            // The provider's copy of a message this screen sent: it replaces the provisional bubble (keeping who sent it).
            $tmp = cdp_opsOne("SELECT id, sent_by, media_url, filename FROM cdb_wa_messages WHERE chat_id = :c AND from_me = 1 AND msg_id LIKE 'local\\_%' AND ack <> 'failed'
                               AND COALESCE(body, '') = :b AND ABS(ts - :ts) < 600 ORDER BY id LIMIT 1", [':c' => $m['chat_id'], ':b' => $m['body'], ':ts' => (int) $m['ts']]);
            if ($tmp) {
                cdp_opsX("DELETE FROM cdb_wa_messages WHERE id = :id", [':id' => (int) $tmp->id]);
                // Keep who sent it and the copy of the file stored here (the provider may not return a link for a file sent as base64).
                cdp_opsX("UPDATE cdb_wa_messages SET sent_by = COALESCE(sent_by, :s), media_url = COALESCE(media_url, :mu), filename = COALESCE(filename, :fn) WHERE msg_id = :m",
                    [':s' => $tmp->sent_by ? (int) $tmp->sent_by : null, ':mu' => $tmp->media_url ?: null, ':fn' => $tmp->filename ?: null, ':m' => $m['msg_id']]);
            }
        }
        return 'new';
    }

    /**
     * Refresh the last messages of one chat (at most every $every seconds per
     * chat unless forced). Returns ['ok', 'error', 'code', 'new'].
     */
    function cdp_umSyncMessages($chatId, $limit = 60, $force = false, $every = 4)
    {
        if (!cdp_umValidChatId($chatId)) {
            return ['ok' => false, 'error' => 'Unknown chat.', 'code' => 'invalid', 'new' => 0];
        }
        if (!cdp_umCap('messages')) {
            return ['ok' => false, 'error' => 'The provider does not return chat history.', 'code' => 'unsupported', 'new' => 0];
        }
        if (!$force) {
            $last = cdp_opsVal("SELECT msgs_synced_at FROM cdb_wa_chats WHERE chat_id = :c", [':c' => $chatId], '');
            if ($last !== '' && time() - strtotime((string) $last) < $every) {
                return ['ok' => true, 'error' => '', 'code' => 'cached', 'new' => 0];
            }
        }
        cdp_opsX("UPDATE cdb_wa_chats SET msgs_synced_at = :n WHERE chat_id = :c", [':n' => cdp_opsNow(), ':c' => $chatId]);
        $r = cdp_umCall('GET', 'chats/messages', ['chatId' => $chatId, 'limit' => max(1, min(1000, (int) $limit))], ['timeout' => 15]);
        if (!$r['ok']) {
            if ($r['code'] === 'unsupported') {
                cdp_umCap('messages', false);
            }
            return ['ok' => false, 'error' => $r['error'], 'code' => $r['code'], 'new' => 0];
        }
        $list = isset($r['data']['messages']) && is_array($r['data']['messages']) ? $r['data']['messages'] : $r['data'];
        $new = 0;
        foreach ((array) $list as $m) {
            $m = is_array($m) ? cdp_umNormMessage($m, $chatId) : null;
            if ($m && cdp_umStoreMessage($m) === 'new') {
                $new++;
            }
        }
        return ['ok' => true, 'error' => '', 'code' => '', 'new' => $new];
    }

    /** Mark a chat read on the phone too (best effort) and locally. */
    function cdp_umMarkRead($chatId)
    {
        cdp_opsX("UPDATE cdb_wa_chats SET unread = 0 WHERE chat_id = :c", [':c' => $chatId]);
        if (cdp_umCap('read')) {
            $r = cdp_umCall('POST', 'chats/read', ['chatId' => $chatId], ['timeout' => 6]);
            if (!$r['ok'] && $r['code'] === 'unsupported') {
                cdp_umCap('read', false);
            }
        }
    }

    /** Address book from the provider into cdb_wa_contacts_cache. */
    function cdp_umSyncContacts($force = false)
    {
        if (!cdp_umCap('contacts')) {
            return ['ok' => false, 'error' => 'The provider does not list contacts.', 'code' => 'unsupported', 'count' => 0];
        }
        if (!$force && !cdp_umDue('contacts', 600)) {
            return ['ok' => true, 'error' => '', 'code' => 'cached', 'count' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_wa_contacts_cache")];
        }
        $r = cdp_umCall('GET', 'contacts', [], ['timeout' => 25]);
        if (!$r['ok']) {
            if ($r['code'] === 'unsupported') {
                cdp_umCap('contacts', false);
            }
            cdp_umSettingSet('due_contacts', '0');
            return ['ok' => false, 'error' => $r['error'], 'code' => $r['code'], 'count' => 0];
        }
        $list = isset($r['data']['contacts']) && is_array($r['data']['contacts']) ? $r['data']['contacts'] : $r['data'];
        $n = 0;
        $now = cdp_opsNow();
        foreach (array_slice((array) $list, 0, 20000) as $c) {
            if (!is_array($c)) {
                continue;
            }
            $id = cdp_umIdOf($c['id'] ?? '');
            if (substr($id, -5) !== '@c.us' || !cdp_umValidChatId($id)) {
                continue;
            }
            cdp_opsX("INSERT INTO cdb_wa_contacts_cache (wa_id, phone, name, pushname, is_business, is_my_contact, synced_at) VALUES (:w, :p, :n, :pn, :b, :m, :s)
                      ON DUPLICATE KEY UPDATE name = VALUES(name), pushname = VALUES(pushname), is_business = VALUES(is_business), is_my_contact = VALUES(is_my_contact), synced_at = VALUES(synced_at)",
                [':w' => $id, ':p' => cdp_umPhoneOf($id), ':n' => mb_substr(trim((string) ($c['name'] ?? '')), 0, 190) ?: null,
                    ':pn' => mb_substr(trim((string) ($c['pushname'] ?? '')), 0, 190) ?: null, ':b' => !empty($c['isBusiness']) ? 1 : 0,
                    ':m' => !empty($c['isMyContact']) ? 1 : 0, ':s' => $now]);
            $n++;
        }
        return ['ok' => true, 'error' => '', 'code' => '', 'count' => $n];
    }

    /**
     * Participants of a group: from the provider (groups/group, then the
     * groups list), else the people seen writing in it. Cached 10 minutes.
     *
     * @return array{ok:bool,source:string,note:string,name:string,participants:array}
     */
    function cdp_umGroupParticipants($chatId, $fresh = false)
    {
        $out = ['ok' => false, 'source' => '', 'note' => '', 'name' => '', 'participants' => []];
        if (substr((string) $chatId, -5) !== '@g.us' || !cdp_umValidChatId($chatId)) {
            $out['note'] = 'Not a group.';
            return $out;
        }
        $row = cdp_opsOne("SELECT * FROM cdb_wa_groups WHERE chat_id = :c", [':c' => $chatId]);
        if ($row && !$fresh && time() - strtotime((string) $row->synced_at) < 600) {
            return ['ok' => true, 'source' => (string) $row->source, 'note' => $row->source === 'seen' ? cdp_umSeenNote() : '', 'name' => (string) $row->name,
                'participants' => cdp_opsDecode($row->participants, [])];
        }
        $parts = null;
        $name = '';
        if (cdp_umCap('group')) {
            $r = cdp_umCall('GET', 'groups/group', ['groupId' => $chatId], ['timeout' => 12]);
            if ($r['ok'] && is_array($r['data'])) {
                $g = isset($r['data']['group']) && is_array($r['data']['group']) ? $r['data']['group'] : $r['data'];
                $list = cdp_umGroupParticipantList($g);
                if ($list !== null) {
                    $parts = cdp_umNormParticipants($list);
                    $name = (string) ($g['name'] ?? ($g['subject'] ?? ''));
                }
            } elseif ($r['code'] === 'unsupported') {
                cdp_umCap('group', false);
            }
        }
        if ($parts === null && cdp_umCap('groups')) {
            $r = cdp_umCall('GET', 'groups', [], ['timeout' => 20]);
            if ($r['ok'] && is_array($r['data'])) {
                $list = isset($r['data']['groups']) && is_array($r['data']['groups']) ? $r['data']['groups'] : $r['data'];
                foreach ((array) $list as $g) {
                    if (is_array($g) && cdp_umIdOf($g['id'] ?? '') === $chatId) {
                        $pl = cdp_umGroupParticipantList($g);
                        if ($pl !== null) {
                            $parts = cdp_umNormParticipants($pl);
                            $name = (string) ($g['name'] ?? ($g['subject'] ?? ''));
                        }
                        break;
                    }
                }
            } elseif ($r['code'] === 'unsupported') {
                cdp_umCap('groups', false);
            }
        }
        $source = 'api';
        if ($parts === null) {
            // Fallback: everyone who wrote in the group (the provider gave no member list).
            $parts = [];
            foreach (cdp_opsQ("SELECT DISTINCT author FROM cdb_wa_messages WHERE chat_id = :c AND author IS NOT NULL AND author <> '' LIMIT 2000", [':c' => $chatId]) as $a) {
                $parts = array_merge($parts, cdp_umNormParticipants([$a->author]));
            }
            $source = 'seen';
            $out['note'] = cdp_umSeenNote();
        }
        if ($name === '') {
            $name = (string) cdp_opsVal("SELECT name FROM cdb_wa_chats WHERE chat_id = :c", [':c' => $chatId], '');
        }
        cdp_opsX("INSERT INTO cdb_wa_groups (chat_id, name, participants, participant_count, source, synced_at) VALUES (:c, :n, :p, :pc, :s, :t)
                  ON DUPLICATE KEY UPDATE name = VALUES(name), participants = VALUES(participants), participant_count = VALUES(participant_count), source = VALUES(source), synced_at = VALUES(synced_at)",
            [':c' => $chatId, ':n' => mb_substr($name, 0, 190) ?: null, ':p' => cdp_opsJson($parts), ':pc' => count($parts), ':s' => $source, ':t' => cdp_opsNow()]);
        cdp_opsX("UPDATE cdb_wa_chats SET participants = :n WHERE chat_id = :c", [':n' => count($parts), ':c' => $chatId]);
        return ['ok' => true, 'source' => $source, 'note' => $out['note'], 'name' => $name, 'participants' => $parts];
    }

    function cdp_umSeenNote()
    {
        return 'The provider did not give the member list, so this shows the people who have written in the group. Members who never wrote are missing.';
    }

    /**
     * Names and customer links for a list of phones (digits):
     * [phone => ['name', 'user_id', 'locker', 'customer']].
     */
    function cdp_umPeople(array $phones, $chatId = '')
    {
        $phones = array_values(array_unique(array_filter(array_map(function ($p) { return preg_replace('/\D+/', '', (string) $p); }, $phones))));
        $out = [];
        foreach ($phones as $p) {
            $out[$p] = ['name' => '', 'user_id' => 0, 'locker' => '', 'customer' => ''];
        }
        foreach (array_chunk($phones, 300) as $chunk) {
            $b = [];
            $in = [];
            foreach ($chunk as $i => $p) {
                $in[] = ':p' . $i;
                $b[':p' . $i] = $p;
            }
            if (!$in) {
                continue;
            }
            foreach (cdp_opsQ("SELECT phone, COALESCE(NULLIF(name,''), pushname) n FROM cdb_wa_contacts_cache WHERE phone IN (" . implode(',', $in) . ")", $b) as $r) {
                $out[$r->phone]['name'] = (string) $r->n;
            }
            // Names people gave themselves in groups (idx_author; only this chat when one is given, idx_chat).
            $ab = $b;
            $chatSql = '';
            if ((string) $chatId !== '') {
                $chatSql = ' AND a.chat_id = :chat';
                $ab[':chat'] = (string) $chatId;
            }
            foreach (cdp_opsQ("SELECT a.author, MAX(a.author_name) n FROM cdb_wa_messages a WHERE a.author IN (" . implode(',', array_map(function ($k) { return 'CONCAT(' . $k . ", '@c.us')"; }, $in)) . ")$chatSql AND a.author_name IS NOT NULL GROUP BY a.author", $ab) as $r) {
                $ph = cdp_umPhoneOf($r->author);
                if (isset($out[$ph]) && $out[$ph]['name'] === '') {
                    $out[$ph]['name'] = (string) $r->n;
                }
            }
            foreach (cdp_opsQ("SELECT phone, user_id FROM cdb_wa_chats WHERE phone IN (" . implode(',', $in) . ") AND user_id IS NOT NULL", $b) as $r) {
                $out[$r->phone]['user_id'] = (int) $r->user_id;
            }
        }
        $ids = array_values(array_unique(array_filter(array_column($out, 'user_id'))));
        if ($ids) {
            $users = [];
            foreach (cdp_opsQ("SELECT id, locker, TRIM(CONCAT(COALESCE(fname,''),' ',COALESCE(lname,''))) n FROM cdb_users WHERE id IN (" . implode(',', array_map('intval', $ids)) . ")") as $u) {
                $users[(int) $u->id] = $u;
            }
            foreach ($out as $p => $x) {
                if ($x['user_id'] && isset($users[$x['user_id']])) {
                    $out[$p]['locker'] = (string) $users[$x['user_id']]->locker;
                    $out[$p]['customer'] = (string) $users[$x['user_id']]->n;
                }
            }
        }
        return $out;
    }
}

// ---------------------------------------------------------------------------
// Sending
// ---------------------------------------------------------------------------

if (!function_exists('cdp_umSend')) {

    /**
     * Send text, an image or a document to a chat (person or group) and store
     * it in the local copy. $media: ['kind' => image|document, 'url' => public
     * URL or '', 'path' => local file, 'filename', 'mime'].
     * When the app is not reachable from the internet (localhost, private
     * address) the file goes as base64 instead of a link.
     *
     * @return array{ok:bool,error:string,code:string,msg_id:string}
     */
    function cdp_umSend($chatId, $text, array $media = null, $sentBy = null, $nonce = '')
    {
        if (!cdp_umValidChatId($chatId)) {
            return ['ok' => false, 'error' => 'Unknown chat.', 'code' => 'invalid', 'msg_id' => ''];
        }
        $text = (string) $text;
        $kind = $media ? ($media['kind'] === 'image' ? 'image' : 'document') : 'chat';
        // The bubble is stored first (ack pending), so the provider's copy arriving by webhook or
        // by another viewer's poll while we wait always finds it and replaces it (no double bubble).
        // A client nonce makes the id stable: the same message posted twice is sent once.
        $msgId = 'local_' . (preg_match('/^[A-Za-z0-9\-]{8,64}$/', (string) $nonce) ? substr(sha1('um-send|' . $chatId . '|' . $nonce), 0, 24) : bin2hex(random_bytes(12)));
        $stored = cdp_umStoreMessage(['msg_id' => $msgId, 'chat_id' => $chatId, 'from_me' => 1, 'author' => '', 'author_name' => '', 'type' => $media ? ($kind === 'image' ? 'image' : 'document') : 'text',
            'body' => $text, 'media_url' => $media && !empty($media['url']) ? (string) $media['url'] : '', 'thumb' => '', 'filename' => $media ? (string) ($media['filename'] ?? '') : '',
            'ack' => 'pending', 'ts' => time()], $sentBy);
        if ($stored !== 'new') {
            $ack = (string) cdp_opsVal("SELECT ack FROM cdb_wa_messages WHERE msg_id = :m", [':m' => $msgId], '');
            return ['ok' => $ack !== 'failed', 'duplicate' => true, 'error' => $ack === 'failed' ? 'This message was already tried and not sent.' : '', 'code' => 'duplicate', 'msg_id' => $msgId, 'provider_id' => ''];
        }
        $finish = function ($ok, $err, $code, $pid = '', $raw = '') use ($chatId, $text, $media, $msgId) {
            $ack = $ok ? 'sent' : 'failed';
            cdp_opsX("UPDATE cdb_wa_messages SET ack = :a, updated_at = :n WHERE msg_id = :m AND ack = 'pending'", [':a' => $ack, ':n' => cdp_opsNow(), ':m' => $msgId]);
            cdp_opsX("UPDATE cdb_wa_chats SET last_ack = :a WHERE chat_id = :c AND last_from_me = 1 AND last_ack = 'pending'", [':a' => $ack, ':c' => $chatId]);
            if ($ok) {
                cdp_opsX("UPDATE cdb_wa_chats SET unread = 0 WHERE chat_id = :c", [':c' => $chatId]);
            }
            // Every attempt, sent or not, is in the message log (the Outbox shows it).
            cdp_umLogSend($chatId, $text, $ok, $err, $pid, $media, $raw);
            return ['ok' => $ok, 'error' => $err, 'code' => $code, 'msg_id' => $msgId, 'provider_id' => $pid];
        };
        $cfg = cdp_umConfig();
        if (!$cfg['configured']) {
            return $finish(false, $cfg['problem'] !== '' ? $cfg['problem'] : 'WhatsApp (UltraMsg) is not set up in Settings > General > Messaging.', 'not_configured');
        }
        if (!$cfg['active']) {
            return $finish(false, 'WhatsApp is switched off in Settings > General > Messaging, so nothing is sent.', 'off');
        }
        $state = cdp_umStatus()['state'];
        if ($state !== 'connected') {
            return $finish(false, 'WhatsApp is not connected (the company phone is not linked or the provider does not answer), so nothing was sent.', 'not_connected');
        }
        if ($media) {
            $src = (string) ($media['url'] ?? '');
            if ($src === '' || !cdp_umPublicUrl($src)) {
                $path = (string) ($media['path'] ?? '');
                if ($path === '' || !is_file($path) || filesize($path) > 16 * 1048576) {
                    return $finish(false, 'The file could not be read.', 'invalid');
                }
                $src = 'data:' . ($media['mime'] ?: 'application/octet-stream') . ';base64,' . base64_encode((string) file_get_contents($path));
            }
            $p = ['to' => $chatId, $kind => $src, 'caption' => mb_substr($text, 0, 1024)];
            if ($kind === 'document') {
                $p['filename'] = (string) ($media['filename'] ?? 'document');
            }
            $r = cdp_umCall('POST', 'messages/' . $kind, $p, ['timeout' => 45]);
        } else {
            $r = cdp_umCall('POST', 'messages/chat', ['to' => $chatId, 'body' => mb_substr($text, 0, 4000)], ['timeout' => 15]);
        }
        $ok = $r['ok'] && !(isset($r['data']['sent']) && filter_var($r['data']['sent'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === false);
        $err = $ok ? '' : ($r['error'] ?: (string) ($r['data']['message'] ?? 'WhatsApp did not accept the message.'));
        return $finish($ok, $err, $ok ? '' : ($r['code'] ?: 'api'), $ok ? (string) ($r['data']['id'] ?? '') : '', (string) ($r['raw'] ?? ''));
    }

    /** Is this URL reachable by the provider (http(s), not localhost or a private address)? */
    function cdp_umPublicUrl($url)
    {
        $host = (string) parse_url((string) $url, PHP_URL_HOST);
        if (!preg_match('#^https?://#i', (string) $url) || $host === '' || $host === 'localhost' || substr($host, -6) === '.local') {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }
        return true;
    }

    /** Every message sent from the screen is in the message log (the Outbox shows it). */
    function cdp_umLogSend($chatId, $text, $ok, $err, $providerId, $media, $raw)
    {
        if (!is_file(__DIR__ . '/message_log.php')) {
            return;
        }
        require_once __DIR__ . '/message_log.php';
        if (!function_exists('cdp_msgLog')) {
            return;
        }
        $group = substr($chatId, -5) === '@g.us';
        $chat = cdp_opsOne("SELECT name, user_id, phone FROM cdb_wa_chats WHERE chat_id = :c", [':c' => $chatId]);
        $name = $chat && $chat->name ? (string) $chat->name : ($group ? 'WhatsApp group' : cdp_umPlusPhone(cdp_umPhoneOf($chatId)));
        if (function_exists('cdp_msgSetContext')) {
            cdp_msgSetContext(['source' => 'comms_whatsapp_web', 'source_label' => 'WhatsApp screen', 'entity_type' => $group ? 'whatsapp_group' : 'whatsapp_chat',
                'entity_label' => $name, 'recipient_user_id' => $chat && $chat->user_id ? (int) $chat->user_id : 0, 'recipient_name' => $name]);
        }
        $logId = cdp_msgLog([
            'channel' => 'whatsapp', 'status' => $ok ? 'sent' : 'failed',
            'status_detail' => $ok ? ('Sent from the WhatsApp screen' . ($providerId !== '' ? ' (id ' . $providerId . ')' : '') . ($media ? ' with ' . ($media['kind'] ?? 'file') . ' ' . ($media['filename'] ?? '') : '')) : $err,
            'body' => $text !== '' ? $text : '[' . ($media['kind'] ?? 'file') . ']', 'recipient_user_id' => $chat && $chat->user_id ? (int) $chat->user_id : 0,
            'recipient_name' => $name, 'recipient_to' => $group ? $chatId : cdp_umPhoneOf($chatId), 'provider_response' => $raw !== '' ? mb_substr((string) $raw, 0, 2000) : null,
        ]);
        if (function_exists('cdp_msgClearContext')) {
            cdp_msgClearContext();
        }
        if (!$logId) {
            error_log('[whatsapp screen] the message log row was not written for ' . $chatId);
        }
    }
}

// ---------------------------------------------------------------------------
// Webhook, contacts, STOP
// ---------------------------------------------------------------------------

if (!function_exists('cdp_umWebhookIngest')) {

    /**
     * Store an UltraMsg webhook event in the local copy:
     * message_received / message_create (also sent from the phone) / message_ack.
     * Called by webhooks/whatsapp.php after it answered the provider.
     */
    function cdp_umWebhookIngest(array $j)
    {
        $ev = (string) ($j['event_type'] ?? '');
        if (!in_array($ev, ['message_received', 'message', 'message_create', 'message_ack'], true) || !is_array($j['data'] ?? null)) {
            return 'ignored';
        }
        if (!cdp_umEnsureTables()) {
            return 'no_tables';
        }
        $d = $j['data'];
        if ($ev === 'message_ack') {
            $id = cdp_umIdOf($d['id'] ?? '');
            $ack = cdp_umAck($d['ack'] ?? '');
            if ($id === '' || $ack === '') {
                return 'ignored';
            }
            $row = cdp_opsOne("SELECT id, ack, chat_id, ts FROM cdb_wa_messages WHERE msg_id = :m", [':m' => $id]);
            if ($row && cdp_umAckRank($ack) > cdp_umAckRank((string) $row->ack)) {
                cdp_opsX("UPDATE cdb_wa_messages SET ack = :a, updated_at = :n WHERE id = :id", [':a' => $ack, ':n' => cdp_opsNow(), ':id' => (int) $row->id]);
                cdp_opsX("UPDATE cdb_wa_chats SET last_ack = :a, updated_at = :n WHERE chat_id = :c AND last_from_me = 1 AND last_ts <= :ts",
                    [':a' => $ack, ':n' => cdp_opsNow(), ':c' => $row->chat_id, ':ts' => (int) $row->ts]);
                return 'ack';
            }
            return 'same';
        }
        $m = cdp_umNormMessage($d);
        if (!$m) {
            return 'ignored';
        }
        // helpers/comms.php already applied a STOP / START to the customer for this delivery.
        $GLOBALS['cdp_um_in_webhook'] = true;
        $r = cdp_umStoreMessage($m);
        $GLOBALS['cdp_um_in_webhook'] = false;
        if ($r === 'new' && !$m['from_me']) {
            cdp_opsX("UPDATE cdb_wa_chats SET unread = unread + 1, name = COALESCE(name, :n) WHERE chat_id = :c",
                [':n' => substr($m['chat_id'], -5) === '@c.us' && $m['author_name'] !== '' ? $m['author_name'] : null, ':c' => $m['chat_id']]);
            cdp_umLinkUsers(5);
        }
        return $r;
    }

    /**
     * STOP / START from a number, honoured on saved contacts and, for a number
     * linked to a customer, on the customer's preferences when the message came
     * by polling (a webhook delivery is handled by helpers/comms.php
     * cdp_commHandleInbound). Only the number's newest STOP / START counts, so a
     * history sync that lists messages newest first never lets an old START
     * undo a newer STOP; a START older than the recorded opt-out is ignored.
     */
    function cdp_umKeyword($phone, $body, $ts = 0, $chatId = '')
    {
        $phone = preg_replace('/\D+/', '', (string) $phone);
        $k = strtoupper(trim((string) $body));
        $stops = ['STOP', 'UNSUBSCRIBE', 'STOP ALL', 'OPT OUT', 'OPTOUT'];
        $starts = ['START', 'SUBSCRIBE', 'UNSTOP', 'OPT IN'];
        $kw = in_array($k, $stops, true) ? 'stop' : (in_array($k, $starts, true) ? 'start' : '');
        if ($kw === '' || $phone === '') {
            return '';
        }
        $ts = (int) $ts ?: time();
        $chatId = (string) $chatId !== '' ? (string) $chatId : $phone . '@c.us';
        $newer = (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_wa_messages WHERE chat_id = :c AND from_me = 0 AND ts > :ts
                                   AND UPPER(TRIM(body)) IN ('STOP','UNSUBSCRIBE','STOP ALL','OPT OUT','OPTOUT','START','SUBSCRIBE','UNSTOP','OPT IN')", [':c' => $chatId, ':ts' => $ts], 0);
        if ($newer > 0) {
            return ''; // a later STOP / START of this number decides
        }
        $at = date('Y-m-d H:i:s', $ts);
        if (cdp_opsTableExists('cdb_comm_contacts')) {
            if ($kw === 'stop') {
                cdp_opsX("UPDATE cdb_comm_contacts SET opted_out = 1, opted_out_at = :a, updated_at = :n WHERE phone = :p AND opted_out = 0", [':a' => $at, ':n' => cdp_opsNow(), ':p' => $phone]);
            } else {
                $c = cdp_opsOne("SELECT id, opted_out_at FROM cdb_comm_contacts WHERE phone = :p AND opted_out = 1", [':p' => $phone]);
                if ($c && ($c->opted_out_at === null || strtotime((string) $c->opted_out_at) < $ts)) {
                    cdp_opsX("UPDATE cdb_comm_contacts SET opted_out = 0, opted_out_at = NULL, updated_at = :n WHERE id = :id", [':n' => cdp_opsNow(), ':id' => (int) $c->id]);
                }
            }
        }
        if (empty($GLOBALS['cdp_um_in_webhook'])) {
            $uid = (int) cdp_opsVal("SELECT user_id FROM cdb_wa_chats WHERE chat_id = :c AND user_id IS NOT NULL", [':c' => $chatId], 0);
            if ($uid && is_file(__DIR__ . '/comms.php')) {
                require_once __DIR__ . '/comms.php';
                if (function_exists('cdp_commSavePrefs') && function_exists('cdp_commEnsureTables') && cdp_commEnsureTables()) {
                    // A preference the customer changed after this message is their later choice: keep it.
                    $upd = (string) cdp_opsVal("SELECT updated_at FROM cdb_comm_prefs WHERE user_id = :u", [':u' => $uid], '');
                    if ($upd === '' || strtotime($upd) < $ts) {
                        cdp_commSavePrefs($uid, $kw === 'stop' ? ['whatsapp' => 0, 'mkt_whatsapp' => 0, 'mkt_sms' => 0, 'mkt_email' => 0] : ['whatsapp' => 1, 'opted_out_all' => 0], 'whatsapp_keyword', 0);
                    }
                }
            }
        }
        return $kw;
    }

    /** Did this number's last STOP / START say STOP (in the local copy)? */
    function cdp_umStoppedPhones(array $phones)
    {
        $phones = array_values(array_unique(array_filter($phones)));
        $out = [];
        foreach (array_chunk($phones, 300) as $chunk) {
            $b = [];
            $in = [];
            foreach ($chunk as $i => $p) {
                $in[] = ':c' . $i;
                $b[':c' . $i] = $p . '@c.us';
            }
            foreach (cdp_opsQ("SELECT chat_id, UPPER(TRIM(body)) k FROM cdb_wa_messages WHERE from_me = 0 AND chat_id IN (" . implode(',', $in) . ")
                               AND UPPER(TRIM(body)) IN ('STOP','UNSUBSCRIBE','STOP ALL','OPT OUT','OPTOUT','START','SUBSCRIBE','UNSTOP','OPT IN') ORDER BY ts", $b) as $r) {
                $out[cdp_umPhoneOf($r->chat_id)] = in_array($r->k, ['STOP', 'UNSUBSCRIBE', 'STOP ALL', 'OPT OUT', 'OPTOUT'], true);
            }
        }
        return array_keys(array_filter($out));
    }

    /**
     * Save people as contacts campaigns can reach (cdb_comm_contacts).
     * $people: [['phone' => digits, 'name' => ...], ...]. $source:
     * whatsapp_group:<name> | whatsapp_contacts | whatsapp_chat.
     * A number that said STOP (here or on its customer account) is saved as
     * opted out; an existing opt-out is never cleared.
     *
     * @return array{saved:int,updated:int,opted_out:int,skipped:int}
     */
    function cdp_umSaveContacts(array $people, $source, $actorId, $note = '')
    {
        $res = ['saved' => 0, 'updated' => 0, 'opted_out' => 0, 'skipped' => 0];
        $source = mb_substr(trim((string) $source), 0, 190);
        if ($source === '') {
            return $res;
        }
        $clean = [];
        foreach ($people as $p) {
            $ph = preg_replace('/\D+/', '', (string) ($p['phone'] ?? ''));
            if (strlen($ph) < 8 || strlen($ph) > 15) {
                $res['skipped']++;
                continue;
            }
            $clean[$ph] = mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', '', (string) ($p['name'] ?? ''))), 0, 190);
        }
        if (!$clean) {
            return $res;
        }
        $stopped = array_flip(cdp_umStoppedPhones(array_keys($clean)));
        $info = cdp_umPeople(array_keys($clean));
        $optedUsers = [];
        $uids = array_values(array_unique(array_filter(array_column($info, 'user_id'))));
        if ($uids && cdp_opsTableExists('cdb_comm_prefs')) {
            foreach (cdp_opsQ("SELECT user_id FROM cdb_comm_prefs WHERE (opted_out_all = 1 OR whatsapp = 0) AND user_id IN (" . implode(',', array_map('intval', $uids)) . ")") as $r) {
                $optedUsers[(int) $r->user_id] = true;
            }
        }
        $now = cdp_opsNow();
        $note = mb_substr($note !== '' ? $note : 'Saved from WhatsApp (' . $source . ') by ' . cdp_opsUserName($actorId) . ' on ' . date('Y-m-d') . '. The owner chose that saved WhatsApp contacts may receive campaigns.', 0, 255);
        foreach ($clean as $ph => $name) {
            $uid = (int) ($info[$ph]['user_id'] ?? 0);
            $out = isset($stopped[$ph]) || ($uid && isset($optedUsers[$uid])) ? 1 : 0;
            $name = $name !== '' ? $name : (string) ($info[$ph]['customer'] ?: $info[$ph]['name']);
            $old = cdp_opsOne("SELECT id, sources, opted_out FROM cdb_comm_contacts WHERE phone = :p", [':p' => $ph]);
            if ($old) {
                $srcs = array_values(array_filter(array_map('trim', explode("\n", (string) $old->sources))));
                if (!in_array($source, $srcs, true)) {
                    $srcs[] = $source;
                }
                cdp_opsX("UPDATE cdb_comm_contacts SET name = COALESCE(NULLIF(name, ''), :n), sources = :s, user_id = COALESCE(user_id, :u),
                          opted_out = GREATEST(opted_out, :o), opted_out_at = IF(opted_out = 0 AND :o2 = 1, :now, opted_out_at), updated_at = :now WHERE id = :id",
                    [':n' => $name !== '' ? $name : null, ':s' => mb_substr(implode("\n", $srcs), 0, 60000), ':u' => $uid ?: null, ':o' => $out, ':o2' => $out, ':now' => $now, ':id' => (int) $old->id]);
                $res['updated']++;
                if ($out || (int) $old->opted_out === 1) {
                    $res['opted_out']++;
                }
                continue;
            }
            $r = cdp_opsX("INSERT INTO cdb_comm_contacts (phone, name, source, sources, opted_out, opted_out_at, user_id, consent, consent_note, created_by, created_at, updated_at)
                           VALUES (:p, :n, :s, :s2, :o, :oa, :u, 1, :cn, :cb, :now, :now)",
                [':p' => $ph, ':n' => $name !== '' ? $name : null, ':s' => $source, ':s2' => $source, ':o' => $out, ':oa' => $out ? $now : null, ':u' => $uid ?: null,
                    ':cn' => $note, ':cb' => (int) $actorId ?: null, ':now' => $now]);
            if ($r['ok']) {
                $res['saved']++;
                $res['opted_out'] += $out;
            } else {
                $res['skipped']++;
            }
        }
        return $res;
    }

    /** Record an export or a save (cdb_wa_exports + the activity log). */
    function cdp_umAudit($kind, $target, $format, $rows, $saved = 0, $optedOut = 0)
    {
        // $saved: numbers campaigns can reach; $optedOut: numbers saved as opted out (STOP).
        cdp_opsX("INSERT INTO cdb_wa_exports (kind, target, format, row_count, saved_count, opted_out_count, user_id, ip, created_at) VALUES (:k, :t, :f, :r, :s, :o, :u, :ip, :n)",
            [':k' => $kind, ':t' => mb_substr((string) $target, 0, 190), ':f' => $format, ':r' => (int) $rows, ':s' => (int) $saved, ':o' => (int) $optedOut, ':u' => cdp_opsUid() ?: null,
                ':ip' => mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45), ':n' => cdp_opsNow()]);
        if (is_file(__DIR__ . '/activity_log.php')) {
            require_once __DIR__ . '/activity_log.php';
            if (function_exists('cdp_activityLog')) {
                $what = $format === 'save' ? 'Saved ' . (int) $saved . ' WhatsApp contacts campaigns can reach' . ($optedOut ? ' (' . (int) $optedOut . ' more saved as opted out)' : '') : 'Exported ' . (int) $rows . ' WhatsApp ' . ($kind === 'group' ? 'group members' : 'contacts') . ' (' . strtoupper($format) . ')';
                cdp_activityLog(['module' => 'notifications', 'verb' => $format === 'save' ? 'create' : 'export', 'action' => 'notifications.whatsapp_' . ($format === 'save' ? 'save_contacts' : 'export'),
                    'label' => $format === 'save' ? 'WhatsApp · Save contacts' : 'WhatsApp · Export', 'summary' => $what . ($target !== '' ? ' from ' . $target : '') . '.',
                    'entity_type' => 'whatsapp_' . $kind, 'entity_label' => mb_substr((string) $target, 0, 190), 'meta' => ['rows' => (int) $rows, 'saved' => (int) $saved, 'opted_out' => (int) $optedOut, 'format' => $format]]);
            }
        }
    }
}

// ---------------------------------------------------------------------------
// The customer beside a chat
// ---------------------------------------------------------------------------

if (!function_exists('cdp_umCustomer')) {

    /** Locker, shipments with status, balance of a customer account. */
    function cdp_umCustomer($userId)
    {
        $u = cdp_opsOne("SELECT id, fname, lname, locker, email, phone FROM cdb_users WHERE id = :id", [':id' => (int) $userId]);
        if (!$u) {
            return null;
        }
        $ships = [];
        if (cdp_opsTableExists('cdb_add_order')) {
            foreach (cdp_opsQ("SELECT o.order_id id, CONCAT(COALESCE(o.order_prefix,''), o.order_no) trk, o.order_date d, s.mod_style st, s.color FROM cdb_add_order o
                               LEFT JOIN cdb_styles s ON s.id = o.status_courier WHERE o.sender_id = :u AND o.status_courier <> 21 ORDER BY o.order_id DESC LIMIT 5", [':u' => (int) $u->id]) as $o) {
                $ships[] = ['kind' => 'shipment', 'id' => (int) $o->id, 'tracking' => (string) $o->trk, 'date' => substr((string) $o->d, 0, 10), 'status' => ucwords(str_replace('_', ' ', (string) $o->st)), 'color' => (string) $o->color];
            }
        }
        if (cdp_opsTableExists('cdb_customers_packages')) {
            foreach (cdp_opsQ("SELECT p.order_id id, CONCAT(COALESCE(p.order_prefix,''), p.order_no) trk, p.order_date d, s.mod_style st, s.color FROM cdb_customers_packages p
                               LEFT JOIN cdb_styles s ON s.id = p.status_courier WHERE p.sender_id = :u ORDER BY p.order_id DESC LIMIT 5", [':u' => (int) $u->id]) as $o) {
                $ships[] = ['kind' => 'package', 'id' => (int) $o->id, 'tracking' => (string) $o->trk, 'date' => substr((string) $o->d, 0, 10), 'status' => ucwords(str_replace('_', ' ', (string) $o->st)), 'color' => (string) $o->color];
            }
        }
        usort($ships, function ($a, $b) { return strcmp($b['date'], $a['date']); });
        $bal = null;
        if (cdp_opsTableExists('cdb_consolidate_customer_billing')) {
            $bal = (float) cdp_opsVal("SELECT SUM(GREATEST(0, COALESCE(amount_ghs,0)-COALESCE(discount_ghs,0)-COALESCE(paid_ghs,0))) FROM cdb_consolidate_customer_billing WHERE sender_id = :u", [':u' => (int) $u->id], 0);
        }
        return ['id' => (int) $u->id, 'name' => trim($u->fname . ' ' . $u->lname), 'first_name' => (string) $u->fname, 'locker' => (string) $u->locker, 'email' => (string) $u->email,
            'phone' => (string) $u->phone, 'shipments' => array_slice($ships, 0, 6), 'balance' => $bal];
    }

    /** Facts King may use for a reply suggestion (no customer: none). */
    function cdp_umFacts($userId)
    {
        if (!$userId) {
            return 'The number is not linked to a customer account: do not reveal any shipment details; ask for the tracking number.';
        }
        if (is_file(__DIR__ . '/comms.php')) {
            require_once __DIR__ . '/comms.php';
            if (function_exists('cdp_commBotContext')) {
                return cdp_commBotContext((object) ['user_id' => (int) $userId]);
            }
        }
        return '';
    }
}
