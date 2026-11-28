<?php
/**
 * Data for Communications > Automations (comms_automations.php) and
 * Communications > Status messages (comms_statuses.php), shared with their
 * endpoint ajax/comms/automation_ajax.php.
 *
 * One model (helpers/comms_triggers.php): every message is a trigger key.
 *   status:<id>      a status; its text is cdb_comm_templates (saved) or the
 *                    message drafted for it (helpers/notify_drafts.php)
 *   status:*         any status without a channel of its own
 *   claim_reminder, payment_reminder, driver_arrival, survey   comms events
 *   legacy:<source>  sent by an old screen; switch here, text in the legacy tables
 *
 * Status texts are also mirrored into cdb_notify_content (the Notification
 * Studio store the old status screens read through cdp_notifyResolve), so an
 * edit made here is what customers get whoever sends status messages
 * (comms.status_owner legacy or comms). The in-app bell line lives only there.
 */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/comms.php';
require_once CDP_ROOT . '/helpers/comms_triggers.php';
require_once CDP_ROOT . '/helpers/comms_gate.php';
require_once CDP_ROOT . '/helpers/notify_studio.php';
require_once CDP_ROOT . '/helpers/notify_drafts.php';

const SL_CA_CHANNELS = ['whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'email' => 'E-mail'];
const SL_CA_LANGS = ['en' => 'English', 'fr' => 'French', 'es' => 'Spanish', 'ar' => 'Arabic'];

/** May this person change switches and texts? Administrators, or the notification-rules permission. */
function sl_caCanEdit($u = null): bool
{
    global $user;
    $u = $u ?: $user;
    if (cdp_opsRank($u) >= 3) {
        return true;
    }
    return is_object($u) && method_exists($u, 'cdp_hasPermission') && cdp_opsRank($u) >= 2 && $u->cdp_hasPermission('manage_notification_rules');
}

/** Administrators only: the master switch, predefined messages switch. */
function sl_caIsAdmin($u = null): bool
{
    return cdp_opsRank($u) >= 3;
}

/** Messages logged this month, by message-log source. */
function sl_caSentCounts(): array
{
    static $out = null;
    if ($out !== null) {
        return $out;
    }
    $out = [];
    if (!sl_tableExists('cdb_message_log')) {
        return $out;
    }
    foreach (sl_rows("SELECT source, COUNT(*) n FROM cdb_message_log WHERE created_at >= :d AND status <> 'skipped' GROUP BY source", [':d' => date('Y-m-01 00:00:00')]) as $r) {
        $out[(string) $r->source] = (int) $r->n;
    }
    return $out;
}

/** Sent this month for one trigger key. */
function sl_caSentFor(string $key): int
{
    $c = sl_caSentCounts();
    if (strpos($key, 'legacy:') === 0) {
        return (int) ($c[substr($key, 7)] ?? 0);
    }
    if ($key === 'status:*') {
        $n = 0;
        foreach ($c as $src => $v) {
            if (strpos($src, 'comms_status_') === 0) {
                $n += $v;
            }
        }
        return $n;
    }
    return (int) ($c[cdp_commTriggerSource($key)] ?? 0);
}

/**
 * cdb_notify_content rows of status keys: key => channel => row (with body and
 * subject; cdp_notifyAll() leaves those out). $fresh re-reads after a write.
 */
function sl_caNotifyRows(bool $fresh = false): array
{
    static $rows = null;
    if ($rows === null || $fresh) {
        $rows = [];
        if (cdp_notifyEnsureTable()) {
            foreach (sl_rows("SELECT trigger_key, channel, enabled, subject, body, updated_at FROM cdb_notify_content WHERE trigger_key LIKE 'status:%'") as $r) {
                $rows[(string) $r->trigger_key][(string) $r->channel] = $r;
            }
        }
    }
    return $rows;
}

/** Does the old status screen still send this channel (no switched-off Studio row)? */
function sl_caLegacyOn(string $key, string $ch): bool
{
    $r = sl_caNotifyRows()[$key][$ch] ?? null;
    return !$r || (int) $r->enabled === 1;
}

/**
 * Mirror one status channel into cdb_notify_content, which the old status
 * screens read (cdp_notifyResolve): off -> a switched-off row; on with a saved
 * text -> that text (e-mail as compiled HTML, no blocks); on without -> no row
 * (the drafted message). English only: the old screens send English.
 */
function sl_caMirrorLegacy(string $key, string $ch, bool $on, int $uid): bool
{
    if (cdp_commTriggerStatusId($key) <= 0 || !cdp_notifyEnsureTable()) {
        return false;
    }
    $tpl = cdp_commTriggerTemplate($key, $ch, 'en');
    $saved = $tpl && $tpl['source'] === 'saved';
    if ($on && !$saved) {
        $ok = (bool) cdp_notifyReset($key, $ch)['ok'];
        sl_caNotifyRows(true);
        return $ok;
    }
    $body = $saved ? (string) $tpl['body'] : '';
    if (strpos($body, '<!--sl-html-->') === 0) {
        $body = substr($body, 14);
    }
    $ok = cdp_opsX("INSERT INTO cdb_notify_content (trigger_key, channel, enabled, subject, body, blocks, updated_by, updated_at)
                    VALUES (:k, :c, :e, :s, :b, NULL, :u, :n)
                    ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), subject = VALUES(subject), body = VALUES(body), blocks = NULL,
                      updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)",
        [':k' => $key, ':c' => $ch, ':e' => $on ? 1 : 0, ':s' => $saved && $tpl['subject'] !== '' ? $tpl['subject'] : null, ':b' => $body,
            ':u' => $uid ?: null, ':n' => date('Y-m-d H:i:s')])['ok'];
    cdp_notifyResolve(null);
    sl_caNotifyRows(true);
    return (bool) $ok;
}

/**
 * Status switches that wait for the hand-over (comms.v2_status_intent).
 *
 * While the old screens send status messages (comms.status_owner = legacy) a
 * status switch must never land in comms.rules: the worker would queue the
 * same message the old screen sends. It is mirrored into the old screens'
 * store (cdb_notify_content) and kept here; cdp_commSetStatusOwner('comms')
 * copies it into comms.rules at the hand-over (an explicit rule wins).
 */
function sl_caIntent(): array
{
    $v = cdp_opsDecode((string) cdp_opsSetting('comms', 'v2_status_intent', ''), []);
    return is_array($v) ? $v : [];
}

/** Run $fn with the comms settings locked and freshly read (two quick switches never overwrite each other). */
function sl_caLocked(callable $fn)
{
    $got = (int) cdp_opsVal("SELECT GET_LOCK('sl_comms_rules', 5)") === 1;
    cdp_opsSettings('comms', true);
    try {
        return $fn();
    } finally {
        if ($got) {
            cdp_opsVal("SELECT RELEASE_LOCK('sl_comms_rules')");
        }
    }
}

/**
 * Switch one channel of a status key (status:N or status:*), whoever sends
 * status messages:
 *   comms   the Automation Center rule; the first write of status:N copies
 *           what every channel does today (its own rule or "any other
 *           status"), so the channels not touched keep their state
 *   legacy  the old screens' store (status:N) plus the hand-over intent;
 *           comms.rules is left alone, so nothing is sent twice
 */
function sl_caSetStatusSwitch(string $key, string $ch, bool $on, int $uid): bool
{
    return (bool) sl_caLocked(function () use ($key, $ch, $on, $uid) {
        $sid = cdp_commTriggerStatusId($key);
        $owner = cdp_commStatusOwner();
        $rules = cdp_commRules();
        if ($owner === 'comms') {
            $seed = [];
            foreach (array_keys(SL_CA_CHANNELS) as $c) {
                $seed[$c] = $sid > 0 ? (sl_caStatusOn($key, $c, $rules, 'comms')['on'] ? 1 : 0) : (!empty($rules[$key][$c]) ? 1 : 0);
            }
            $seed[$ch] = $on ? 1 : 0;
            $ok = cdp_commSaveRules([$key => $seed]);
            if ($ok && $sid > 0) {
                sl_caMirrorLegacy($key, $ch, $on, $uid);
            }
            return $ok;
        }
        if ($sid > 0 && !sl_caMirrorLegacy($key, $ch, $on, $uid)) {
            return false;
        }
        $intent = sl_caIntent();
        $seed = [];
        foreach (array_keys(SL_CA_CHANNELS) as $c) {
            $seed[$c] = $sid > 0 ? (sl_caLegacyOn($key, $c) ? 1 : 0)
                : (int) ($intent[$key][$c] ?? (!empty($rules[$key][$c]) ? 1 : 0));
        }
        $seed[$ch] = $on ? 1 : 0;
        $intent[$key] = $seed;
        $ok = cdp_opsSettingSet('comms', 'v2_status_intent', cdp_opsJson($intent), $uid);
        if ($ok && isset($rules[$key])) {
            // A rule an earlier build wrote while the old screens send: it moves to the intent.
            unset($rules[$key]);
            cdp_commSaveRules($rules, true);
        }
        return (bool) $ok;
    });
}

/** "Any other status" switches as the Status messages page shows them (the intent while the old screens send). */
function sl_caAnyStatus(): array
{
    $src = cdp_commStatusOwner() === 'comms' ? cdp_commRules() : sl_caIntent() + cdp_commRules();
    $out = [];
    foreach (array_keys(SL_CA_CHANNELS) as $c) {
        $out[$c] = !empty($src['status:*'][$c]);
    }
    return $out;
}

/**
 * Is a status channel on? With comms.status_owner = comms the Automation
 * Center rule decides; with legacy the old screens do (a Studio row switched
 * off stops them). 'via' says when status:* ("any other status") covers it.
 */
function sl_caStatusOn(string $key, string $ch, array $rules, string $owner): array
{
    if ($owner === 'comms') {
        if (isset($rules[$key][$ch])) {
            return ['on' => (int) $rules[$key][$ch] === 1, 'via' => ''];
        }
        $any = !empty($rules['status:*'][$ch]) && !array_filter((array) ($rules[$key] ?? []));
        return ['on' => $any, 'via' => $any ? 'any' : ''];
    }
    return ['on' => sl_caLegacyOn($key, $ch), 'via' => ''];
}

/** Every status with its channel states for the Status messages grid. */
function sl_caStatusRows(): array
{
    $owner = cdp_commStatusOwner();
    $rules = cdp_commRules();
    $notify = sl_caNotifyRows();
    $colors = [];
    foreach (sl_rows('SELECT id, color FROM cdb_styles') as $r) {
        $colors[(int) $r->id] = (string) $r->color;
    }
    // With "Predefined messages" off a status without its own text sends the one shared message.
    $plain = cdp_notifyDraftsOn() ? 'drafted' : 'shared';
    $out = [];
    foreach (cdp_commStatusTriggers() as $s) {
        $cells = [];
        foreach (array_keys(SL_CA_CHANNELS) as $ch) {
            $st = sl_caStatusOn($s['key'], $ch, $rules, $owner);
            $custom = ($s['custom'][$ch] ?? 'predefined') === 'custom';
            $textOff = ($s['custom'][$ch] ?? '') === 'off';
            $cells[$ch] = ['on' => $st['on'] && !$textOff, 'via' => $st['via'], 'state' => (!$st['on'] || $textOff) ? 'off' : ($custom ? 'custom' : $plain)];
        }
        $in = $notify[$s['key']]['inapp'] ?? null;
        $cells['inapp'] = ['on' => !$in || (int) $in->enabled === 1, 'via' => '', 'state' => $in ? ((int) $in->enabled === 1 ? 'custom' : 'off') : $plain];
        $out[] = $s + ['cells' => $cells, 'color' => $colors[$s['id']] ?? '', 'sent' => sl_caSentFor($s['key'])];
    }
    usort($out, function ($a, $b) { return strcasecmp($a['label'], $b['label']); });
    return $out;
}

/**
 * Sample values that fill the previews of a trigger. $ch = 'sms' gives the
 * values an SMS really carries: the pickup code line without WhatsApp bold and
 * in its short form, as cdp_notifyBuild() sends it.
 */
function sl_caSample(string $key, string $ch = ''): array
{
    $sid = cdp_commTriggerStatusId($key);
    if ($sid > 0) {
        $v = cdp_notifySampleVars(cdp_commStatusName($sid));
        return $ch === 'sms' ? sl_caSmsVars($v) : $v;
    }
    if (strpos($key, 'legacy:') === 0) {
        $s = cdp_notifySampleVars();
        $site = rtrim((string) (cdp_opsSiteUrl() ?? ''), '/');
        return [
            'NAME' => $s['name'], 'CUSTOMER_FULLNAME' => $s['name'], 'USERNAME' => 'efua.owusu', 'TRACKING' => $s['tracking'], 'TRACKING_NUMBER' => $s['tracking'],
            'TRACK' => $s['tracking'], 'COURIER' => 'USPS', 'COURIER_NAME' => 'USPS', 'DELIVERY_TIME' => '5 to 7 days', 'NEW_ADDRESS' => 'Accra office',
            'COMMENT' => 'Collect from counter 2', 'URL' => $site, 'URL_SHIP' => $s['track_url'], 'TRACK_URL' => $s['track_url'], 'SITE_NAME' => $s['company'],
            'COMPANY_NAME' => $s['company'], 'SERVICE_TYPE' => 'Air express', 'ORIGIN_OFFICE' => 'New York warehouse', 'EXTRA_DETAILS' => "• Weight: 4.20 lb",
            'CHANGED_FIELDS' => 'Status: In transit', 'PACKAGES_SECTION' => 'SL-100482, SL-100483', 'TOTAL_AMOUNT' => '$42.00', 'DAYS' => '12',
            'MESSAGE' => 'Your pickup code is 482193.', 'SUBJECT' => 'Invoice INV-1042', 'VIRTUAL_LOCKER' => 'SL-1042', 'PASSWORD' => '482193',
            'IP' => '41.66.0.10', 'TTL' => '10', 'STATUS' => 'In transit', 'LINK' => $s['track_url'],
            'URL_LINK' => (string) ((function_exists('cdp_getSettingsCourier') ? (cdp_getSettingsCourier()->logo ?? '') : '') ?: 'logo.png'),
        ];
    }
    $v = cdp_notifyCommsSampleVars($key, cdp_notifySampleVars());
    return $ch === 'sms' ? sl_caSmsVars($v) : $v;
}

/** The SMS form of the pickup code values (the same rewrite as cdp_notifyBuild for 'sms'). */
function sl_caSmsVars(array $v): array
{
    if (!isset($v['pickup_code'])) {
        return $v;
    }
    $c = str_replace(['*', 'to our driver when'], ['', 'to our rider when'], (string) $v['pickup_code']);
    $v['pickup_code'] = preg_replace([
        '/^Pickup code: (\S+?)\. Show this code when you collect your packages?\. Do not share it with anyone else\./m',
        '/^Pickup code: (\S+?)\. Give this code to our (?:driver|rider) when your packages? arrives?\. Do not share it with anyone else\./m',
    ], ['Pickup code: $1. Show it at the counter; do not share it.', 'Pickup code: $1. Give it to our rider; do not share it.'], $c);
    return $v;
}

/** Placeholder chips for the editor: {name: description} per channel (e-mail also gets *_html). */
function sl_caTags(string $key): array
{
    if (strpos($key, 'legacy:') === 0) {
        $t = cdp_commLegacyTriggers()[substr($key, 7)] ?? null;
        $tags = [];
        foreach ((array) ($t['tags'] ?? []) as $tag) {
            $tags[$tag] = ucfirst(strtolower(str_replace('_', ' ', trim($tag, '[]'))));
        }
        return $tags;
    }
    $out = [];
    foreach (array_keys(SL_CA_CHANNELS) as $ch) {
        $out[$ch] = cdp_commTriggerPlaceholders($key, $ch);
    }
    return $out;
}

/** A canvas design from what a template row holds (design, studio blocks, HTML, or text). */
function sl_caDesignOf(array $t): array
{
    if (!empty($t['design_json'])) {
        $d = json_decode((string) $t['design_json'], true);
        if (is_array($d) && isset($d['blocks'])) {
            return $d;
        }
    }
    if (!empty($t['blocks']) && is_array($t['blocks'])) {
        return cdp_commBlocksToCanvas($t['blocks']);
    }
    $body = (string) ($t['body'] ?? '');
    if (strpos($body, '<!--sl-html-->') === 0) {
        return ['settings' => new stdClass(), 'blocks' => [['id' => 'b1', 'type' => 'html', 'props' => ['code' => substr($body, 14), 'pt' => 0, 'pb' => 0, 'px' => 0]]]];
    }
    return cdp_commBlocksToCanvas([['type' => 'text', 'text' => str_replace('*', '', $body)]]);
}

/**
 * What the editor opens with for a status or event key: values per channel,
 * where each text comes from (saved | draft | default), and the switches.
 */
function sl_caTemplateValues(string $key, string $lang = 'en'): array
{
    $values = [];
    $sources = [];
    foreach (array_keys(SL_CA_CHANNELS) as $ch) {
        $t = cdp_commTriggerTemplate($key, $ch, $lang);
        $sources[$ch] = $t ? ($t['language'] !== $lang && $t['source'] === 'saved' ? 'saved_en' : $t['source']) : 'none';
        if ($ch === 'email') {
            $values['email'] = ['subject' => $t['subject'] ?? '', 'preheader' => '', 'design' => $t ? sl_caDesignOf($t) : ['settings' => new stdClass(), 'blocks' => []]];
        } else {
            $values[$ch] = ['body' => $t ? (string) $t['body'] : ''];
            if ($ch === 'whatsapp') {
                $values[$ch] += ['wa_template_name' => $t['wa_template_name'] ?? '', 'wa_template_lang' => $t['wa_template_lang'] ?? '', 'wa_template_params' => $t['wa_template_params'] ?? ''];
            }
        }
    }
    $enabled = [];
    $sid = cdp_commTriggerStatusId($key);
    $rules = cdp_commRules();
    $owner = cdp_commStatusOwner();
    foreach (array_keys(SL_CA_CHANNELS) as $ch) {
        $enabled[$ch] = $sid > 0 ? sl_caStatusOn($key, $ch, $rules, $owner)['on'] && $sources[$ch] !== 'none' : !empty($rules[$key][$ch]);
    }
    $out = ['key' => $key, 'label' => cdp_commTriggerLabel($key), 'language' => $lang, 'values' => $values, 'sources' => $sources, 'enabled' => $enabled,
        'tags' => sl_caTags($key), 'sample' => sl_caSample($key), 'sample_sms' => sl_caSample($key, 'sms')];
    if ($sid > 0) {
        $name = cdp_commStatusName($sid);
        $kind = cdp_commStatusKind($sid, $name);
        $in = sl_caNotifyRows()[$key]['inapp'] ?? null;
        $def = cdp_notifyDraft($name, 'inapp');
        $out['status'] = ['id' => $sid, 'name' => $name, 'label' => cdp_commStatusLabel($name), 'kind' => $kind,
            'kind_label' => cdp_notifyDraftKindLabels()[$kind] ?? $kind, 'urgent' => in_array($kind, cdp_commUrgentKinds(), true)];
        $out['inapp'] = ['on' => !$in || (int) $in->enabled === 1, 'custom' => $in && (int) $in->enabled === 1,
            'body' => $in && trim((string) $in->body) !== '' ? (string) $in->body : (string) ($def['body'] ?? '')];
    }
    return $out;
}

/** A legacy template row (cdb_email_templates, cdb_sms_templates, whatsapp_templates) or null. */
function sl_caLegacyRow(string $table, int $id): ?array
{
    $map = ['email' => 'cdb_email_templates', 'sms' => 'cdb_sms_templates', 'whatsapp' => 'whatsapp_templates'];
    if (!isset($map[$table]) || $id <= 0) {
        return null;
    }
    $sql = $table === 'whatsapp' ? 'SELECT id, title AS name, description AS subject, body FROM whatsapp_templates WHERE id = :id'
        : 'SELECT id, name, subject, body FROM ' . $map[$table] . ' WHERE id = :id';
    $r = sl_row($sql, [':id' => $id]);
    return $r ? ['id' => (int) $r->id, 'name' => (string) $r->name, 'subject' => (string) $r->subject, 'body' => (string) $r->body] : null;
}

/** Plain text of a legacy SMS/WhatsApp body (old editors stored &nbsp; and stray tabs). */
function sl_caLegacyText(string $body): string
{
    $t = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body)), ENT_QUOTES, 'UTF-8');
    $t = str_replace(["\xC2\xA0", '\\n'], [' ', "\n"], $t);
    $t = preg_replace("/[ \t]+/", ' ', $t);
    return trim(preg_replace("/ *\n */", "\n", $t));
}

/** The rows a legacy trigger's text lives in, per channel, for the "Other triggers" editor. */
function sl_caLegacyTemplates(string $src): array
{
    $t = sl_caLegacyTrigger($src);
    if (!$t) {
        return [];
    }
    $out = [];
    foreach ((array) $t['templates'] as $ch => $loc) {
        $rows = [];
        foreach ((array) $loc['ids'] as $id) {
            $r = sl_caLegacyRow((string) $loc['table'], (int) $id);
            $row = ['table' => (string) $loc['table'], 'id' => (int) $id, 'missing' => $r === null, 'name' => $r['name'] ?? '', 'subject' => $r['subject'] ?? '',
                'body' => $r ? ($ch === 'email' ? $r['body'] : sl_caLegacyText($r['body'])) : '', 'design' => null];
            if ($r && $ch === 'email') {
                // The canvas design saved with this e-mail, while the text is still the one saved from the canvas.
                $saved = json_decode((string) cdp_opsSetting('comms_legacy', 'design_email_' . (int) $id, ''), true);
                if (is_array($saved) && ($saved['hash'] ?? '') === md5($r['body']) && is_array($saved['design'] ?? null)) {
                    $row['design'] = $saved['design'];
                }
            }
            $rows[] = $row;
        }
        $out[$ch] = $rows;
    }
    return $out;
}

/**
 * Old template rows no trigger uses any more (the old template list pages now
 * redirect here, so this is the only way to reach them), per channel:
 * ['table' => ..., 'ids' => [...]].
 */
function sl_caOrphanTemplates(): array
{
    static $out = null;
    if ($out !== null) {
        return $out;
    }
    $used = ['email' => [], 'sms' => [], 'whatsapp' => []];
    foreach (cdp_commLegacyTriggers() as $t) {
        foreach ((array) $t['templates'] as $loc) {
            foreach ((array) $loc['ids'] as $id) {
                $used[(string) $loc['table']][(int) $id] = true;
            }
        }
    }
    $tables = ['whatsapp' => 'whatsapp_templates', 'sms' => 'cdb_sms_templates', 'email' => 'cdb_email_templates'];
    $out = [];
    foreach ($tables as $ch => $tbl) {
        if (!sl_tableExists($tbl)) {
            continue;
        }
        $ids = [];
        foreach (sl_rows("SELECT id FROM $tbl ORDER BY id") as $r) {
            if (empty($used[$ch][(int) $r->id])) {
                $ids[] = (int) $r->id;
            }
        }
        if ($ids) {
            $out[$ch] = ['table' => $ch, 'ids' => $ids];
        }
    }
    return $out;
}

/**
 * A legacy trigger by source, or the "unassigned" pseudo trigger that holds
 * the orphan template rows (no switches: whichever screen still uses one of
 * them sends it).
 */
function sl_caLegacyTrigger(string $src): ?array
{
    if ($src === 'unassigned') {
        $tpl = sl_caOrphanTemplates();
        $tags = [];
        foreach ($tpl as $loc) {
            foreach ($loc['ids'] as $id) {
                $r = sl_caLegacyRow($loc['table'], $id);
                if ($r && preg_match_all('/\[[A-Z_]{2,40}\]/', $r['subject'] . ' ' . $r['body'], $m)) {
                    $tags = array_merge($tags, $m[0]);
                }
            }
        }
        $tags = array_values(array_unique($tags));
        sort($tags);
        return ['label' => 'Unassigned old templates', 'group' => 'other', 'status_family' => false, 'locked' => true, 'unassigned' => true,
            'desc' => 'Old template rows no trigger on this page uses. A screen may still send one of them; edit the text here.',
            'channels' => array_keys($tpl), 'templates' => $tpl, 'tags' => $tags];
    }
    return cdp_commLegacyTriggers()[$src] ?? null;
}

/** The legacy trigger and channel whose template rows include table row #$id (for the old *_edit.php links). */
function sl_caLegacyForTemplate(string $ch, int $id): string
{
    foreach (cdp_commLegacyTriggers() as $src => $t) {
        if (in_array($id, array_map('intval', (array) ($t['templates'][$ch]['ids'] ?? [])), true)) {
            return 'legacy:' . $src;
        }
    }
    return in_array($id, (array) (sl_caOrphanTemplates()[$ch]['ids'] ?? []), true) ? 'legacy:unassigned' : '';
}

/** A query parameter as a string (an array in the URL gives ''). */
function sl_caGet(string $k): string
{
    $v = $_GET[$k] ?? '';
    return is_string($v) ? $v : '';
}

/** What the page needs to know about King (the AI assistant). */
function sl_caAi(): array
{
    require_once CDP_ROOT . '/helpers/comms_ai.php';
    $a = cdp_commAiAvailable();
    $tones = [];
    foreach (cdp_notifyAiTones() as $k => $t) {
        $tones[] = [$k, $t[0]];
    }
    return ['ok' => (bool) $a['ok'], 'code' => (string) $a['code'], 'message' => (string) $a['message'], 'setup_url' => (string) $a['setup_url'],
        'name' => (string) $a['assistant'], 'tones' => $tones];
}

/** Quiet hours summary line. */
function sl_caQuietLine(array $s): string
{
    $a = (string) ($s['quiet_start'] ?? '');
    $b = (string) ($s['quiet_end'] ?? '');
    if ($a === '' || $b === '' || $a === $b) {
        return 'No quiet hours: messages go out as soon as they are due.';
    }
    return 'Quiet hours ' . $a . ' to ' . $b . ': messages due then wait until ' . $b . ' (rider arrival and failed delivery go out at once).';
}

/** Page data: Automations. */
function sl_commsAutomationsData($user): array
{
    $ready = cdp_commEnsureTables();
    $s = cdp_commSettings();
    $cat = $ready ? cdp_commTriggerCatalogue() : [];
    $statusRows = $ready ? sl_caStatusRows() : [];
    $inappOn = count(array_filter($statusRows, function ($r) { return $r['cells']['inapp']['on']; }));
    $draftsOn = cdp_notifyDraftsOn();
    foreach ($cat as &$g) {
        foreach ($g['triggers'] as &$t) {
            $t['sent'] = $t['engine'] === 'custom' ? 0 : sl_caSentFor((string) $t['key']);
            $t['editable'] = $t['engine'] === 'comms' && $t['key'] !== 'status:*'
                || ($t['engine'] === 'legacy' && !empty($t['templates']));
            // Old-screen channels whose template rows are all missing send nothing
            // (status-family screens still send the status's own message while
            // predefined messages are on). Channels with no template row at all use
            // wording built into the screen.
            $t['missing'] = [];
            $t['builtin'] = [];
            if ($t['engine'] === 'legacy' && !empty($t['templates'])) {
                foreach ((array) $t['channels'] as $c) {
                    $loc = $t['templates'][$c] ?? null;
                    if (!$loc) {
                        $t['builtin'][$c] = true;
                        continue;
                    }
                    $found = false;
                    foreach ((array) $loc['ids'] as $id) {
                        if (sl_caLegacyRow((string) $loc['table'], (int) $id)) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found && !(!empty($t['status_family']) && $draftsOn)) {
                        $t['missing'][$c] = true;
                    }
                }
            }
            if ($t['key'] === 'status:*') {
                // What is really sent today (owner-aware), not only the Automation Center rules.
                $sending = count(array_filter($statusRows, function ($r) { return $r['cells']['whatsapp']['on'] || $r['cells']['sms']['on'] || $r['cells']['email']['on']; }));
                $t['summary'] = ['on' => $sending, 'total' => count($statusRows)];
                $t['desc'] = $sending . ' of ' . count($statusRows) . ' statuses send a message. Each status has its own switches and text.';
                $t['inapp'] = ['on' => $inappOn, 'total' => count($statusRows)];
            }
        }
        unset($t);
    }
    unset($g);
    if ($ready && ($orph = sl_caOrphanTemplates())) {
        $u = sl_caLegacyTrigger('unassigned');
        $n = array_sum(array_map(function ($l) { return count($l['ids']); }, $orph));
        $cat[] = ['id' => 'unassigned', 'label' => 'Old templates not tied to a trigger', 'desc' => 'Kept so their text can still be edited.', 'triggers' => [
            ['key' => 'legacy:unassigned', 'label' => $u['label'], 'desc' => $n . ' template rows (' . implode(', ', array_map(function ($c) use ($orph) {
                return count($orph[$c]['ids']) . ' ' . SL_CA_CHANNELS[$c]; }, array_keys($orph))) . '). ' . $u['desc'],
                'engine' => 'legacy', 'channels' => [], 'rules' => [], 'locked' => false, 'unassigned' => true, 'tags' => $u['tags'], 'templates' => $orph,
                'link' => '', 'status_family' => false, 'sent' => 0, 'editable' => true, 'missing' => [], 'builtin' => []]]];
    }
    $tab = sl_caGet('tab');
    $ch = sl_caGet('ch');
    $edit = sl_caGet('edit');
    $id = (int) sl_caGet('id');
    return [
        'ready' => $ready, 'catalogue' => $cat, 'master' => ($s['enabled'] ?? '0') === '1', 'quiet' => sl_caQuietLine($s),
        'owner' => cdp_commStatusOwner(), 'canEdit' => sl_caCanEdit($user), 'isAdmin' => sl_caIsAdmin($user), 'ai' => sl_caAi(),
        'tab' => in_array($tab, ['all', 'other'], true) ? $tab : 'all', 'ch' => isset(SL_CA_CHANNELS[$ch]) ? $ch : '',
        'edit' => cdp_commTriggerKeyValid($edit) ? $edit : '', 'editAsked' => mb_substr($edit, 0, 60), 'tplId' => $id > 0 ? $id : 0,
        'workerLast' => (string) cdp_opsSetting('comms', 'worker_last_run', ''), 'langs' => SL_CA_LANGS,
        'statusesUrl' => sl_route('CommsStatuses'), 'settingsUrl' => sl_route('CommsSettings'),
    ];
}

/** Page data: Status messages. */
function sl_commsStatusesData($user): array
{
    $ready = cdp_commEnsureTables();
    $rows = $ready ? sl_caStatusRows() : [];
    $any = $ready ? sl_caAnyStatus() : [];
    $counts = ['drafted' => 0, 'shared' => 0, 'custom' => 0, 'off' => 0];
    foreach ($rows as $r) {
        foreach ($r['cells'] as $c) {
            $counts[$c['state']]++;
        }
    }
    $open = (int) sl_caGet('status');
    return [
        'ready' => $ready, 'rows' => $rows, 'any' => $any, 'counts' => $counts, 'owner' => cdp_commStatusOwner(), 'openAsked' => $open,
        'master' => cdp_commSetting('enabled') === '1', 'draftsOn' => cdp_notifyDraftsOn(), 'canEdit' => sl_caCanEdit($user), 'isAdmin' => sl_caIsAdmin($user),
        'ai' => sl_caAi(), 'open' => $open > 0 && cdp_commStatusName($open) !== '' ? $open : 0, 'langs' => SL_CA_LANGS,
        'automationsUrl' => sl_route('CommsAutomations'), 'settingsUrl' => sl_route('CommsSettings'),
    ];
}
