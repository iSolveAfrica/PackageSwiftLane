<?php
// *************************************************************************
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *************************************************************************

/**
 * Send page (comms_send.php): direct messages and campaigns through the
 * Communication Center outbox (helpers/comms.php).
 *
 * Audience of a campaign (cdb_comm_campaigns.audience, JSON):
 *   base          'all' | a preset key (cdp_cmpPresets) | 'consolidation'
 *   consolidation 'air:<id>' | 'sea:<id>'   owners of the packages inside it
 *   match, rules  filters over cdb_users (cdp_commSegmentSql: city, country,
 *                 last shipment, balance, packages waiting, joined, consent)
 *   customers     false = only WhatsApp contacts (default true)
 *   contacts      {on, source}  contacts saved from WhatsApp (table
 *                 cdb_comm_contacts when it exists). The owner chose that
 *                 these count as reachable for campaigns; STOP / opt-out on
 *                 the contact or on a matching customer account still wins.
 *   agency_id     set for agency-restricted staff: only their customers
 *
 * Message (cdb_comm_campaigns.bodies, JSON): {whatsapp: {body, media},
 * sms: {body}, email: {subject, preheader, html, design_json}, inapp: {body},
 * _meta: {goal}}. The e-mail HTML is sanitised when saved and queued as
 * '<!--sl-html-->' . html (placeholders filled escaped when sent).
 *
 * Status: draft -> pending (waiting for approval) -> scheduled -> sending
 * -> sent; cancelled. cdp_commCampaignTick() (every worker pass, whatever the
 * automations switch says) queues 500 recipients per pass with launch_cursor:
 * >= 0 the last customer id done; < 0 the contacts phase (-1 - last contact id).
 *
 * Campaigns are marketing: a customer needs the channel's marketing consent
 * (cdb_comm_prefs mkt_*), e-mail addresses on the newsletter unsubscribe list
 * are skipped. Direct messages (one person, several people) are service
 * messages: only a channel the customer switched off, or a global opt-out,
 * stops them.
 */

require_once __DIR__ . '/ops_db.php';
require_once __DIR__ . '/comms.php';

if (!function_exists('cdp_cmpPresets')) {

    define('CDP_CMP_BATCH', 500);
    define('CDP_CMP_DIRECT_MAX', 300);

    /** Message channels of the Send page (in-app = the customer's bell in the app). */
    function cdp_cmpChannels()
    {
        return ['whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'email' => 'E-mail', 'inapp' => 'In-app'];
    }

    /** Ready-made audiences: label and a condition over cdb_users u. */
    function cdp_cmpPresets()
    {
        $q90 = "SELECT o.sender_id FROM cdb_add_order o WHERE o.is_deleted = 0 AND o.order_date >= CURDATE() - INTERVAL 90 DAY";
        return [
            'all' => ['label' => 'All active customers', 'sql' => ''],
            'active' => ['label' => 'Shipped in the last 90 days', 'sql' => "u.id IN ($q90)"],
            'quiet' => ['label' => 'Quiet 90 days (win-back)', 'sql' => "u.id NOT IN ($q90)"],
            'due' => ['label' => 'Customers with overdue bills', 'sql' => "u.id IN (SELECT o.sender_id FROM cdb_add_order o WHERE o.is_deleted = 0 AND o.status_invoice = 23 AND o.status_courier <> 21)"],
            'ready' => ['label' => 'Parcels ready for pickup', 'sql' => "u.id IN (SELECT o.sender_id FROM cdb_add_order o WHERE o.is_deleted = 0 AND o.status_courier = 32)"],
            'new' => ['label' => 'Joined this month', 'sql' => "u.created >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"],
            'subscribers' => ['label' => 'Newsletter subscribers', 'sql' => 'u.newsletter = 1'],
        ];
    }

    /** Filters offered by the campaign audience builder. */
    function cdp_cmpFilterFields()
    {
        $f = cdp_commSegmentFields();
        $f['last_shipment_days']['label'] = 'Days since last shipment';
        $f['balance']['label'] = 'Outstanding balance (' . cdp_commSetting('currency') . ')';
        $f['consent']['label'] = 'Opted in to offers on';
        return $f;
    }

    /** Normalise an audience posted by the page (array or JSON). */
    function cdp_cmpAudience($in)
    {
        $a = is_array($in) ? $in : cdp_opsDecode((string) $in, []);
        $presets = cdp_cmpPresets();
        $base = (string) ($a['base'] ?? 'all');
        if ($base !== 'consolidation' && !isset($presets[$base])) {
            $base = 'all';
        }
        $cons = preg_match('/^(air|sea):\d+$/', (string) ($a['consolidation'] ?? '')) ? (string) $a['consolidation'] : '';
        if ($base === 'consolidation' && $cons === '') {
            $base = 'all';
        }
        $fields = cdp_commSegmentFields();
        $rules = [];
        foreach (array_slice((array) ($a['rules'] ?? []), 0, 12) as $r) {
            $f = (string) ($r['field'] ?? '');
            if (!isset($fields[$f])) {
                continue;
            }
            $op = in_array($r['op'] ?? '', ['eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'contains'], true) ? (string) $r['op'] : 'eq';
            $v = $r['value'] ?? '';
            $v = is_scalar($v) ? mb_substr(trim((string) $v), 0, 80) : '';
            if ($v === '' && $fields[$f]['type'] !== 'bool') {
                continue;
            }
            $rules[] = ['field' => $f, 'op' => $op, 'value' => $v];
        }
        $c = (array) ($a['contacts'] ?? []);
        $out = ['base' => $base, 'consolidation' => $base === 'consolidation' ? $cons : '', 'match' => ($a['match'] ?? 'all') === 'any' ? 'any' : 'all',
            'rules' => $rules, 'customers' => !array_key_exists('customers', $a) || !empty($a['customers']) && $a['customers'] !== '0' && $a['customers'] !== 'false',
            'contacts' => ['on' => !empty($c['on']) && $c['on'] !== '0' && $c['on'] !== 'false', 'source' => mb_substr(trim((string) ($c['source'] ?? '')), 0, 190)]];
        if (!cdp_cmpContactsInfo()) {
            $out['contacts']['on'] = false;
        }
        if (!$out['customers'] && !$out['contacts']['on']) {
            $out['customers'] = true;
        }
        if (isset($a['agency_id']) && (int) $a['agency_id'] > 0) {
            $out['agency_id'] = (int) $a['agency_id'];
        }
        return $out;
    }

    /** The signed-in staff member's agency when their view is restricted to one, else 0. */
    function cdp_cmpAgencyScope()
    {
        $ctx = function_exists('cdp_getAgencyContext') ? cdp_getAgencyContext() : [];
        return !empty($ctx['is_restricted']) && !empty($ctx['agency_id']) ? (int) $ctx['agency_id'] : 0;
    }

    /**
     * Campaigns an agency-restricted staff member may see: written by someone of
     * their agency, or aimed at their agency's customers. Everyone else sees all.
     *
     * @return array{0:string,1:array}
     */
    function cdp_cmpScopeSql($alias = 'c')
    {
        $ag = cdp_cmpAgencyScope();
        if (!$ag) {
            return ['1=1', []];
        }
        return ["($alias.created_by IN (SELECT su.id FROM cdb_users su WHERE su.agency_id = :cmp_sag) OR JSON_VALUE($alias.audience, '$.agency_id') = :cmp_sag2)",
            [':cmp_sag' => $ag, ':cmp_sag2' => $ag]];
    }

    /** May the signed-in person see this campaign (agency scope)? */
    function cdp_cmpInScope($id)
    {
        list($w, $b) = cdp_cmpScopeSql();
        return (bool) cdp_opsOne("SELECT c.id FROM cdb_comm_campaigns c WHERE c.id = :cmp_id AND $w", $b + [':cmp_id' => (int) $id]);
    }

    /**
     * WHERE over cdb_users u for the customers of an audience (values bound).
     *
     * @return array{0:string,1:array}
     */
    function cdp_cmpAudienceSql(array $a)
    {
        list($w, $b) = cdp_commSegmentSql(['match' => $a['match'] ?? 'all', 'rules' => $a['rules'] ?? []], cdp_commClientRoles());
        $presets = cdp_cmpPresets();
        $base = (string) ($a['base'] ?? 'all');
        if (isset($presets[$base]) && $presets[$base]['sql'] !== '') {
            $w .= ' AND ' . $presets[$base]['sql'];
        }
        if ($base === 'consolidation' && preg_match('/^(air|sea):(\d+)$/', (string) ($a['consolidation'] ?? ''), $m)) {
            $t = $m[1] === 'sea' ? ['cdb_consolidate_packages_detail', 'cdb_customers_packages'] : ['cdb_consolidate_detail', 'cdb_add_order'];
            $w .= " AND u.id IN (SELECT o.sender_id FROM {$t[0]} d INNER JOIN {$t[1]} o ON o.order_id = d.order_id WHERE d.consolidate_id = :cmp_cid)";
            $b[':cmp_cid'] = (int) $m[2];
        }
        if (!empty($a['agency_id'])) {
            $w .= ' AND u.agency_id = :cmp_ag';
            $b[':cmp_ag'] = (int) $a['agency_id'];
        }
        return [$w, $b];
    }

    /** Plain-words description of an audience (shown in the list and given to King). */
    function cdp_cmpAudienceLabel(array $a)
    {
        $parts = [];
        if (!empty($a['customers']) || !isset($a['customers'])) {
            $base = (string) ($a['base'] ?? 'all');
            if ($base === 'consolidation') {
                $parts[] = 'Owners of the packages in consolidation ' . cdp_cmpConsolidationLabel((string) ($a['consolidation'] ?? ''));
            } else {
                $parts[] = cdp_cmpPresets()[$base]['label'] ?? 'All active customers';
            }
            $fields = cdp_cmpFilterFields();
            $ops = ['eq' => 'is', 'ne' => 'is not', 'gt' => 'over', 'gte' => 'at least', 'lt' => 'under', 'lte' => 'at most', 'contains' => 'contains'];
            $f = [];
            foreach ((array) ($a['rules'] ?? []) as $r) {
                $l = $fields[$r['field']]['label'] ?? $r['field'];
                if ($r['field'] === 'in_warehouse') {
                    $f[] = !empty($r['value']) && $r['value'] !== '0' ? 'has packages waiting' : 'has no packages waiting';
                } elseif ($r['field'] === 'consent') {
                    $f[] = 'opted in to offers on ' . (cdp_cmpChannels()[$r['value']] ?? $r['value']);
                } else {
                    $f[] = lcfirst($l) . ' ' . ($ops[$r['op']] ?? $r['op']) . ' ' . $r['value'];
                }
            }
            if ($f) {
                $parts[count($parts) - 1] .= ' where ' . implode(($a['match'] ?? 'all') === 'any' ? ' or ' : ' and ', $f);
            }
        }
        if (!empty($a['contacts']['on'])) {
            $src = (string) ($a['contacts']['source'] ?? '');
            $parts[] = 'WhatsApp contacts' . ($src !== '' ? ' from ' . cdp_cmpSourceLabel($src) : '');
        }
        return implode(' + ', $parts) ?: 'All active customers';
    }

    function cdp_cmpSourceLabel($src)
    {
        $src = (string) $src;
        if (strpos($src, 'whatsapp_group:') === 0) {
            return 'group "' . substr($src, 15) . '"';
        }
        if ($src === 'whatsapp_chat' || $src === 'whatsapp') {
            return 'WhatsApp chats';
        }
        if ($src === 'whatsapp_contacts' || strpos($src, 'whatsapp_') === 0) {
            return $src === 'whatsapp_contacts' ? 'WhatsApp contacts' : 'WhatsApp ' . str_replace('_', ' ', substr($src, 9));
        }
        return $src;
    }

    /** Consolidations owners can be picked from (newest first). */
    function cdp_cmpConsolidations($limit = 40)
    {
        $out = [];
        foreach (cdp_opsQ("SELECT consolidate_id id, c_prefix, c_no, COALESCE(order_datetime, c_date) created FROM cdb_consolidate ORDER BY consolidate_id DESC LIMIT " . (int) $limit) as $c) {
            $out[] = ['k' => 'air:' . (int) $c->id, 'label' => trim($c->c_prefix . $c->c_no) . ' · air', 'date' => (string) $c->created];
        }
        if (cdp_opsTableExists('cdb_consolidate_packages')) {
            foreach (cdp_opsQ("SELECT consolidate_id id, c_prefix, c_no, COALESCE(order_datetime, c_date) created FROM cdb_consolidate_packages ORDER BY consolidate_id DESC LIMIT " . (int) $limit) as $c) {
                $out[] = ['k' => 'sea:' . (int) $c->id, 'label' => trim($c->c_prefix . $c->c_no) . ' · sea', 'date' => (string) $c->created];
            }
        }
        usort($out, function ($x, $y) { return strcmp($y['date'], $x['date']); });
        return array_slice($out, 0, $limit);
    }

    function cdp_cmpConsolidationLabel($k)
    {
        if (!preg_match('/^(air|sea):(\d+)$/', (string) $k, $m)) {
            return '';
        }
        $t = $m[1] === 'sea' ? 'cdb_consolidate_packages' : 'cdb_consolidate';
        $c = cdp_opsTableExists($t) ? cdp_opsOne("SELECT c_prefix, c_no FROM $t WHERE consolidate_id = :id", [':id' => (int) $m[2]]) : null;
        return $c ? trim($c->c_prefix . $c->c_no) : '#' . (int) $m[2];
    }

    // -----------------------------------------------------------------------
    // WhatsApp contacts (saved from chats and groups by the WhatsApp screen)
    // -----------------------------------------------------------------------

    /**
     * Where saved WhatsApp contacts live, with their column names, or null
     * when no such table exists yet. Looks for cdb_comm_contacts (then
     * cdb_wa_contacts) with a phone column; name, source, opt-out, user and
     * e-mail columns are optional.
     *
     * @return array{table:string,id:string,phone:string,name:?string,source:?string,optout:?string,user:?string,email:?string}|null
     */
    function cdp_cmpContactsInfo()
    {
        static $info = false;
        if ($info !== false) {
            return $info;
        }
        $info = null;
        foreach (['cdb_comm_contacts', 'cdb_wa_contacts'] as $t) {
            if (!cdp_opsTableExists($t)) {
                continue;
            }
            $cols = [];
            foreach (cdp_opsQ("SELECT COLUMN_NAME c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t", [':t' => $t]) as $r) {
                $cols[strtolower((string) $r->c)] = (string) $r->c;
            }
            $pick = function (array $names) use ($cols) {
                foreach ($names as $n) {
                    if (isset($cols[$n])) {
                        return $cols[$n];
                    }
                }
                return null;
            };
            $phone = $pick(['phone', 'phone_number', 'number', 'wa_phone', 'msisdn']);
            $id = $pick(['id', 'contact_id']);
            if (!$phone || !$id) {
                continue;
            }
            $info = ['table' => $t, 'id' => $id, 'phone' => $phone, 'name' => $pick(['name', 'contact_name', 'display_name', 'pushname']),
                'source' => $pick(['source', 'origin']), 'sources' => $pick(['sources']), 'optout' => $pick(['opted_out', 'opt_out', 'unsubscribed', 'stopped']),
                'consent' => $pick(['consent', 'marketing_consent']), 'user' => $pick(['user_id']), 'email' => $pick(['email'])];
            break;
        }
        return $info;
    }

    /**
     * Sources contacts were saved from (e.g. whatsapp_group:<name>), with counts.
     * A contact saved from several groups lists every one of them in `sources`
     * (one per line, helpers/ultramsg.php); `source` keeps only the first.
     */
    function cdp_cmpContactSources()
    {
        $c = cdp_cmpContactsInfo();
        if (!$c || !$c['source']) {
            return [];
        }
        $n = [];
        $col = $c['sources'] ? "COALESCE(NULLIF(`{$c['sources']}`, ''), `{$c['source']}`)" : "`{$c['source']}`";
        foreach (cdp_opsQ("SELECT $col s, COUNT(*) n FROM `{$c['table']}` GROUP BY s") as $r) {
            foreach (array_unique(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $r->s)))) as $k) {
                $n[$k] = ($n[$k] ?? 0) + (int) $r->n;
            }
        }
        arsort($n);
        $out = [];
        foreach (array_slice($n, 0, 100, true) as $k => $cnt) {
            $out[] = ['k' => (string) $k, 'label' => cdp_cmpSourceLabel((string) $k), 'n' => $cnt];
        }
        return $out;
    }

    /**
     * WHERE over the contacts table c for an audience.
     *
     * @return array{0:string,1:array}
     */
    function cdp_cmpContactsWhere(array $a, array $c)
    {
        $w = "TRIM(COALESCE(c.`{$c['phone']}`, '')) <> ''";
        $b = [];
        $src = (string) ($a['contacts']['source'] ?? '');
        if ($src !== '' && $c['source']) {
            $col = $c['sources'] ? "CONCAT('\n', COALESCE(NULLIF(c.`{$c['sources']}`, ''), c.`{$c['source']}`), '\n')" : "CONCAT('\n', c.`{$c['source']}`, '\n')";
            $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], substr($src, -1) === '*' ? substr($src, 0, -1) : $src);
            $w .= " AND REPLACE($col, '\r', '') LIKE :cmp_src";
            $b[':cmp_src'] = '%' . "\n" . $like . (substr($src, -1) === '*' ? '%' : "\n%");
        }
        return [$w, $b];
    }

    /** Last 9 digits of a phone (how cdp_commFindUserByPhone matches a customer). */
    function cdp_cmpPhoneTail($phone)
    {
        $d = preg_replace('/\D+/', '', (string) $phone);
        return strlen($d) >= 7 ? substr($d, -9) : '';
    }

    /**
     * Customers owning these phones, matched like cdp_commFindUserByPhone (the
     * last 9 digits, only when exactly one account has them), in one query.
     *
     * @return array<string,int> tail => user id
     */
    function cdp_cmpPhoneOwners(array $phones)
    {
        $tails = array_values(array_unique(array_filter(array_map('cdp_cmpPhoneTail', $phones))));
        if (!$tails) {
            return [];
        }
        $norm = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone,'+',''),' ',''),'-',''),'(',''),')','')";
        $seen = [];
        foreach (array_chunk($tails, 500) as $chunk) {
            $b = [];
            foreach ($chunk as $i => $t) {
                $b[':t' . $i] = $t;
            }
            foreach (cdp_opsQ("SELECT id, RIGHT($norm, 9) t FROM cdb_users WHERE phone IS NOT NULL AND phone <> '' AND RIGHT($norm, 9) IN (" . implode(',', array_keys($b)) . ")", $b) as $r) {
                $seen[(string) $r->t][] = (int) $r->id;
            }
        }
        $out = [];
        foreach ($seen as $t => $ids) {
            if (count($ids) === 1) {
                $out[(string) $t] = $ids[0];
            }
        }
        return $out;
    }

    /**
     * One page of the contacts of an audience, each with its state on every
     * channel - the same rules the campaign engine applies when it queues them
     * (cdp_cmpRunBatch -> cdp_commQueueRaw):
     *   no_address         no phone (or no e-mail; contacts get no campaign e-mail
     *                      unless the address belongs to a customer, who has the opt-out link)
     *   contact_opted_out  the contact replied STOP
     *   contact_no_consent the contact's consent was withdrawn
     *   customer_contact   the phone belongs to a customer who is in the customer audience
     *   opted_out          the customer the phone belongs to opted out of everything
     *   owner_no_consent   the customer the phone belongs to has not agreed to offers here
     *   unsubscribed       e-mail on the unsubscribe list
     *
     * $after: contacts with an id above it; $q: search (name, phone).
     *
     * @return array<int,array{cid:int,name:string,phone:string,email:string,source:string,owner:int,state:array<string,string>}>
     */
    function cdp_cmpContactRows(array $a, array $channels, $after = 0, $limit = 500, $q = '', $offset = 0)
    {
        $ci = cdp_cmpContactsInfo();
        if (!$ci || empty($a['contacts']['on'])) {
            return [];
        }
        list($w, $b) = cdp_cmpContactsWhere($a, $ci);
        if ($q !== '') {
            $w .= " AND (c.`{$ci['phone']}` LIKE :cmp_cq" . ($ci['name'] ? " OR c.`{$ci['name']}` LIKE :cmp_cq2" : '') . ')';
            $b[':cmp_cq'] = '%' . (preg_replace('/\D+/', '', $q) !== '' && strlen(preg_replace('/\D+/', '', $q)) >= 3 ? preg_replace('/\D+/', '', $q) : $q) . '%';
            if ($ci['name']) {
                $b[':cmp_cq2'] = '%' . $q . '%';
            }
        }
        $cols = "c.`{$ci['id']}` cid, c.`{$ci['phone']}` phone" . ($ci['name'] ? ", c.`{$ci['name']}` name" : '') . ($ci['optout'] ? ", c.`{$ci['optout']}` stop" : '')
            . ($ci['consent'] ? ", c.`{$ci['consent']}` consent" : '') . ($ci['user'] ? ", c.`{$ci['user']}` uid" : '') . ($ci['email'] ? ", c.`{$ci['email']}` email" : '')
            . ($ci['source'] ? ", c.`{$ci['source']}` src" : '');
        $rows = cdp_opsQ("SELECT $cols FROM `{$ci['table']}` c WHERE $w AND c.`{$ci['id']}` > :cmp_last ORDER BY c.`{$ci['id']}` LIMIT " . (int) $offset . ', ' . (int) $limit,
            $b + [':cmp_last' => (int) $after]);
        if (!$rows) {
            return [];
        }
        // Who each phone belongs to (as cdp_commQueueRaw finds it), else the linked account.
        $owners = cdp_cmpPhoneOwners(array_map(function ($r) { return (string) $r->phone; }, $rows));
        $own = [];
        foreach ($rows as $r) {
            $t = cdp_cmpPhoneTail($r->phone);
            $own[(int) $r->cid] = $owners[$t] ?? (int) ($r->uid ?? 0);
        }
        $uids = array_values(array_unique(array_filter($own)));
        $prefs = [];
        $inAud = [];
        $emails = [];
        if ($uids) {
            foreach ($uids as $u) {
                $prefs[$u] = cdp_commPrefs($u);
            }
            if (!empty($a['customers'])) {
                list($aw, $ab) = cdp_cmpAudienceSql($a);
                foreach (cdp_opsQ("SELECT u.id FROM cdb_users u WHERE $aw AND u.id IN (" . implode(',', $uids) . ")", $ab) as $r) {
                    $inAud[(int) $r->id] = true;
                }
            }
            foreach (cdp_opsQ("SELECT id, email FROM cdb_users WHERE id IN (" . implode(',', $uids) . ")") as $r) {
                $emails[(int) $r->id] = (string) $r->email;
            }
        }
        $unsub = [];
        if (in_array('email', $channels, true) && $emails && cdp_opsTableExists('cdb_newsletter_unsubscribes')) {
            $eb = [];
            foreach (array_values(array_unique(array_filter(array_map('strtolower', $emails)))) as $i => $e) {
                $eb[':e' . $i] = $e;
            }
            if ($eb) {
                foreach (cdp_opsQ("SELECT email FROM cdb_newsletter_unsubscribes WHERE email IN (" . implode(',', array_keys($eb)) . ")", $eb) as $r) {
                    $unsub[strtolower((string) $r->email)] = true;
                }
            }
        }
        $out = [];
        foreach ($rows as $r) {
            $cid = (int) $r->cid;
            $owner = $own[$cid];
            $st = [];
            foreach ($channels as $ch) {
                if ($ch === 'inapp') {
                    $s = 'no_address';
                } elseif ($ch === 'email' && !($owner && filter_var($emails[$owner] ?? '', FILTER_VALIDATE_EMAIL))) {
                    $s = 'no_address';
                } elseif (!empty($r->stop)) {
                    $s = 'contact_opted_out';
                } elseif (isset($r->consent) && $r->consent !== null && (int) $r->consent === 0) {
                    $s = 'contact_no_consent';
                } elseif ($owner && isset($inAud[$owner])) {
                    $s = 'customer_contact';
                } elseif ($owner && !empty($prefs[$owner]['opted_out_all'])) {
                    $s = 'opted_out';
                } elseif ($owner && !cdp_commAllowed($prefs[$owner], $ch, 'marketing', 'campaign')) {
                    $s = 'owner_no_consent';
                } elseif ($ch === 'email' && isset($unsub[strtolower($emails[$owner] ?? '')])) {
                    $s = 'unsubscribed';
                } else {
                    $s = 'ok';
                }
                $st[$ch] = $s;
            }
            $out[] = ['cid' => $cid, 'name' => trim((string) ($r->name ?? '')), 'phone' => (string) $r->phone, 'email' => $owner ? (string) ($emails[$owner] ?? '') : '',
                'source' => (string) ($r->src ?? ''), 'owner' => (int) $owner, 'state' => $st];
        }
        return $out;
    }

    // -----------------------------------------------------------------------
    // Reach: who will get the message, who is skipped and why
    // -----------------------------------------------------------------------

    /**
     * SQL fragments that classify one customer for one channel, in the order
     * the engine checks: no address, opted out of everything, channel off /
     * no marketing consent, unsubscribed (e-mail).
     */
    function cdp_cmpReachCase($ch, $kind)
    {
        $addr = $ch === 'email' ? "u.email LIKE '%_@_%'" : "TRIM(COALESCE(u.phone, '')) <> ''";
        $optOut = 'COALESCE(p.opted_out_all, 0) = 1';
        $perm = $kind === 'marketing' ? "COALESCE(p.mkt_$ch, 0) = 1" : "COALESCE(p.$ch, 1) = 1";
        $unsub = $ch === 'email' && cdp_opsTableExists('cdb_newsletter_unsubscribes')
            ? "EXISTS (SELECT 1 FROM cdb_newsletter_unsubscribes ns WHERE ns.email = LOWER(u.email))" : '0';
        return "CASE WHEN NOT ($addr) THEN 'no_address' WHEN $optOut THEN 'opted_out' WHEN NOT ($perm) THEN '" . ($kind === 'marketing' ? 'no_consent' : 'channel_off') . "'"
            . " WHEN $unsub THEN 'unsubscribed' ELSE 'ok' END";
    }

    /** Reasons a person is skipped, in words. */
    function cdp_cmpReasons()
    {
        return ['no_address' => 'No address for this channel', 'opted_out' => 'Opted out of all messages', 'no_consent' => 'Has not agreed to offers on this channel',
            'channel_off' => 'Switched this channel off', 'unsubscribed' => 'Unsubscribed from e-mails', 'contact_opted_out' => 'Contact replied STOP',
            'customer_contact' => 'Also a customer in this audience: reached as that customer', 'contact_no_consent' => 'Contact\'s consent was withdrawn',
            'owner_no_consent' => 'Customer has not agreed to offers', 'not_configured' => 'Channel is not set up (Settings)',
            'not_found' => 'Not a customer and not a valid phone or e-mail'];
    }

    /**
     * Reach of customers matching $where on each channel.
     *
     * @return array{total:int,channels:array<string,array<string,int>>}
     */
    function cdp_cmpReachUsers($where, array $bind, array $channels, $kind)
    {
        $out = ['total' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_users u WHERE $where", $bind), 'channels' => []];
        foreach ($channels as $ch) {
            if ($ch === 'inapp') {
                $n = (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_users u LEFT JOIN cdb_comm_prefs p ON p.user_id = u.id WHERE $where AND COALESCE(p.opted_out_all, 0) = 0", $bind);
                $out['channels']['inapp'] = ['ok' => $n, 'opted_out' => $out['total'] - $n];
                continue;
            }
            $row = ['ok' => 0];
            foreach (cdp_opsQ("SELECT " . cdp_cmpReachCase($ch, $kind) . " r, COUNT(*) n FROM cdb_users u LEFT JOIN cdb_comm_prefs p ON p.user_id = u.id WHERE $where GROUP BY r", $bind) as $r) {
                $row[(string) $r->r] = (int) $r->n;
            }
            $out['channels'][$ch] = $row;
        }
        return $out;
    }

    /**
     * Reach of the WhatsApp contacts of an audience, classified exactly as the
     * engine will treat them (cdp_cmpContactRows).
     */
    function cdp_cmpReachContacts(array $a, array $channels)
    {
        $out = ['total' => 0, 'channels' => []];
        foreach ($channels as $ch) {
            $out['channels'][$ch] = ['ok' => 0];
        }
        if (!cdp_cmpContactsInfo() || empty($a['contacts']['on'])) {
            return $out;
        }
        $after = 0;
        for ($guard = 0; $guard < 400; $guard++) {
            $rows = cdp_cmpContactRows($a, $channels, $after, 1000);
            foreach ($rows as $r) {
                $out['total']++;
                foreach ($r['state'] as $ch => $s) {
                    $out['channels'][$ch][$s] = ($out['channels'][$ch][$s] ?? 0) + 1;
                }
                $after = $r['cid'];
            }
            if (count($rows) < 1000) {
                break;
            }
        }
        return $out;
    }

    /** Reach of a whole campaign audience: customers plus contacts. */
    function cdp_cmpReach(array $a, array $channels)
    {
        $channels = array_values(array_intersect($channels, array_keys(cdp_cmpChannels())));
        $u = ['total' => 0, 'channels' => []];
        if (!empty($a['customers'])) {
            list($w, $b) = cdp_cmpAudienceSql($a);
            $u = cdp_cmpReachUsers($w, $b, $channels, 'marketing');
        }
        $c = cdp_cmpReachContacts($a, $channels);
        $out = ['customers' => $u['total'], 'contacts' => $c['total'], 'total' => $u['total'] + $c['total'], 'channels' => []];
        foreach ($channels as $ch) {
            $row = [];
            foreach ([$u['channels'][$ch] ?? [], $c['channels'][$ch] ?? []] as $part) {
                foreach ($part as $k => $n) {
                    $row[$k] = ($row[$k] ?? 0) + $n;
                }
            }
            $out['channels'][$ch] = $row + ['ok' => 0];
        }
        $out['people'] = cdp_cmpReachPeople($out);
        return $out;
    }

    /** Distinct people reached on at least one channel cannot be counted cheaply; the best channel is a floor. */
    function cdp_cmpReachPeople(array $reach)
    {
        $m = 0;
        foreach ($reach['channels'] as $row) {
            $m = max($m, (int) ($row['ok'] ?? 0));
        }
        return $m;
    }

    /**
     * Sample or page of the people in an audience, with their state on each
     * channel (for "first 5" and "See all").
     */
    function cdp_cmpPeople(array $a, array $channels, $offset = 0, $limit = 5, $q = '')
    {
        $rows = [];
        $channels = array_values(array_intersect($channels, ['whatsapp', 'sms', 'email', 'inapp']));
        if (!empty($a['customers'])) {
            list($w, $b) = cdp_cmpAudienceSql($a);
            if ($q !== '') {
                $w .= " AND (CONCAT(u.fname, ' ', u.lname) LIKE :cmp_q OR u.locker LIKE :cmp_q2 OR u.phone LIKE :cmp_q3 OR u.email LIKE :cmp_q4)";
                $b += [':cmp_q' => '%' . $q . '%', ':cmp_q2' => '%' . $q . '%', ':cmp_q3' => '%' . $q . '%', ':cmp_q4' => '%' . $q . '%'];
            }
            $w0 = $w;
            $sel = [];
            foreach ($channels as $ch) {
                $sel[] = ($ch === 'inapp' ? "CASE WHEN COALESCE(p.opted_out_all, 0) = 1 THEN 'opted_out' ELSE 'ok' END" : cdp_cmpReachCase($ch, 'marketing')) . " r_$ch";
            }
            foreach (cdp_opsQ("SELECT u.id, u.fname, u.lname, u.locker, u.phone, u.email, u.city" . ($sel ? ', ' . implode(', ', $sel) : '') . "
                               FROM cdb_users u LEFT JOIN cdb_comm_prefs p ON p.user_id = u.id WHERE $w ORDER BY u.id LIMIT " . (int) $offset . ', ' . (int) $limit, $b) as $r) {
                $st = [];
                foreach ($channels as $ch) {
                    $st[$ch] = (string) $r->{'r_' . $ch};
                }
                $rows[] = ['id' => (int) $r->id, 'kind' => 'customer', 'name' => trim($r->fname . ' ' . $r->lname) ?: 'Customer', 'locker' => (string) $r->locker,
                    'phone' => (string) $r->phone, 'email' => (string) $r->email, 'city' => (string) $r->city, 'state' => $st];
            }
        }
        // Then the WhatsApp contacts, paged after the customers.
        if (count($rows) < $limit && !empty($a['contacts']['on'])) {
            $custN = 0;
            if (!empty($a['customers'])) {
                $custN = $rows ? (int) $offset + count($rows) : (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_users u WHERE $w0", $b);
            }
            $cOff = max(0, (int) $offset - $custN);
            foreach (cdp_cmpContactRows($a, $channels, 0, $limit - count($rows), $q, $cOff) as $c) {
                $rows[] = ['id' => 0, 'cid' => $c['cid'], 'kind' => 'contact', 'name' => $c['name'] !== '' ? $c['name'] : '+' . ltrim($c['phone'], '+'), 'locker' => '',
                    'phone' => $c['phone'], 'email' => $c['email'], 'city' => $c['source'] !== '' ? cdp_cmpSourceLabel($c['source']) : 'WhatsApp contact', 'state' => $c['state']];
            }
        }
        return $rows;
    }

    /** Chosen customers with their state per channel for a service message. */
    function cdp_cmpPeopleByIds(array $ids, array $channels)
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            return [];
        }
        $channels = array_values(array_intersect($channels, ['whatsapp', 'sms', 'email', 'inapp']));
        $sel = [];
        foreach ($channels as $ch) {
            $sel[] = ($ch === 'inapp' ? "CASE WHEN COALESCE(p.opted_out_all, 0) = 1 THEN 'opted_out' ELSE 'ok' END" : cdp_cmpReachCase($ch, 'transactional')) . " r_$ch";
        }
        $rows = [];
        foreach (cdp_opsQ("SELECT u.id, u.fname, u.lname, u.locker, u.phone, u.email, u.city" . ($sel ? ', ' . implode(', ', $sel) : '') . "
                           FROM cdb_users u LEFT JOIN cdb_comm_prefs p ON p.user_id = u.id WHERE u.id IN (" . implode(',', $ids) . ") ORDER BY FIELD(u.id, " . implode(',', $ids) . ")") as $r) {
            $st = [];
            foreach ($channels as $ch) {
                $st[$ch] = (string) $r->{'r_' . $ch};
            }
            $rows[] = ['id' => (int) $r->id, 'kind' => 'customer', 'name' => trim($r->fname . ' ' . $r->lname) ?: 'Customer', 'locker' => (string) $r->locker,
                'phone' => (string) $r->phone, 'email' => (string) $r->email, 'city' => (string) $r->city, 'state' => $st];
        }
        return $rows;
    }

    /**
     * Resolve a direct send: customer ids plus a pasted list of e-mails and
     * phones. Tokens that match a customer become that customer; the rest
     * stay typed addresses (sent with cdp_commQueueRaw). Every token is
     * normalised first, then resolved in one query for the e-mails and one for
     * the phones (a phone matches a customer the way cdp_commFindUserByPhone
     * does: the last 9 digits, only when exactly one account has them).
     *
     * @return array{ids:int[],raw:array<int,array{to:string,type:string}>,bad:string[]}
     */
    function cdp_cmpDirectRecipients(array $ids, $list)
    {
        $scope = cdp_cmpAgencyScope();
        $sc = $scope ? ' AND u.agency_id = ' . $scope : '';
        $roles = implode(',', array_map('intval', cdp_commClientRoles()));
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $out = ['ids' => [], 'raw' => [], 'bad' => []];
        if ($ids) {
            foreach (cdp_opsQ("SELECT u.id FROM cdb_users u WHERE u.userlevel IN ($roles) AND u.active = 1 AND u.id IN (" . implode(',', array_slice($ids, 0, 2000)) . ")$sc") as $r) {
                $out['ids'][] = (int) $r->id;
            }
        }
        $seen = [];
        $toks = [];
        foreach (preg_split('/[,;\r\n]+/', (string) $list, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $piece) {
            // Phones may contain spaces; e-mail addresses separated by spaces are split.
            $piece = trim($piece);
            foreach (strpos($piece, '@') !== false || !preg_match('/^[+\d\s().-]+$/', $piece) ? preg_split('/\s+/', $piece, -1, PREG_SPLIT_NO_EMPTY) : [$piece] as $t) {
                $toks[] = $t;
            }
        }
        $emails = [];
        $phones = [];
        foreach (array_slice($toks, 0, 2000) as $tok) {
            $tok = trim($tok, " \t\n\r\0\x0B<>\"'");
            if ($tok === '' || isset($seen[strtolower($tok)])) {
                continue;
            }
            $seen[strtolower($tok)] = true;
            if (strpos($tok, '@') !== false) {
                if (!filter_var($tok, FILTER_VALIDATE_EMAIL)) {
                    $out['bad'][] = $tok;
                } else {
                    $emails[strtolower($tok)] = true;
                }
                continue;
            }
            $digits = preg_replace('/\D+/', '', $tok);
            if (strlen($digits) < 7 || strlen($digits) > 15) {
                $out['bad'][] = $tok;
                continue;
            }
            $phones[(strpos($tok, '+') === 0 ? '+' : '') . $digits] = true;
        }
        $emails = array_keys($emails);
        $phones = array_keys($phones);
        $byEmail = [];
        foreach (array_chunk($emails, 500) as $chunk) {
            $b = [];
            foreach ($chunk as $i => $e) {
                $b[':e' . $i] = $e;
            }
            foreach (cdp_opsQ("SELECT u.id, LOWER(u.email) e FROM cdb_users u WHERE u.active = 1 AND u.userlevel IN ($roles) AND LOWER(u.email) IN (" . implode(',', array_keys($b)) . ")$sc ORDER BY u.id", $b) as $r) {
                $byEmail[(string) $r->e] = $byEmail[(string) $r->e] ?? (int) $r->id;
            }
        }
        foreach ($emails as $e) {
            if (isset($byEmail[$e])) {
                $out['ids'][] = $byEmail[$e];
            } else {
                $out['raw'][] = ['to' => $e, 'type' => 'email'];
            }
        }
        $owners = cdp_cmpPhoneOwners($phones);
        $allowed = [];
        if ($owners) {
            foreach (cdp_opsQ("SELECT u.id FROM cdb_users u WHERE u.active = 1 AND u.userlevel IN ($roles) AND u.id IN (" . implode(',', array_map('intval', array_values($owners))) . ")$sc") as $r) {
                $allowed[(int) $r->id] = true;
            }
        }
        foreach ($phones as $p) {
            $uid = $owners[cdp_cmpPhoneTail($p)] ?? 0;
            if ($uid && isset($allowed[$uid])) {
                $out['ids'][] = $uid;
            } else {
                $out['raw'][] = ['to' => $p, 'type' => 'phone'];
            }
        }
        $out['ids'] = array_values(array_unique($out['ids']));
        return $out;
    }

    /**
     * Why a channel cannot deliver right now ('' = it can): switched off or no
     * provider set up. Messages on such a channel are not queued (they would
     * only wait and fail).
     */
    function cdp_cmpChannelProblem($ch)
    {
        $s = function_exists('cdp_getSettingsCourier') ? cdp_getSettingsCourier() : null;
        if (!$s) {
            return '';
        }
        if ($ch === 'email') {
            if (trim((string) ($s->email_address ?? '')) === '') {
                return 'E-mail sender address is not set (Settings)';
            }
            if (($s->mailer ?? '') !== 'PHP' && trim((string) ($s->smtp_host ?? '')) === '') {
                return 'E-mail is not set up: no SMTP server (Settings)';
            }
            return '';
        }
        if ($ch === 'sms') {
            if ((int) ($s->active_sms ?? 0) !== 1) {
                return 'SMS is switched off (Settings)';
            }
            @include_once __DIR__ . '/hubtel_sms.php';
            return function_exists('cdp_smsReady') && cdp_smsReady() ? '' : 'SMS is not set up: no provider (Settings)';
        }
        if ($ch === 'whatsapp') {
            if (cdp_commSetting('wa_provider') === 'cloud') {
                $cs = cdp_commSettings();
                return trim((string) ($cs['wa_cloud_phone_id'] ?? '')) !== '' && trim((string) ($cs['wa_cloud_token'] ?? '')) !== '' ? '' : 'WhatsApp Cloud API is not set up (Settings)';
            }
            if ((int) ($s->active_whatsapp ?? 0) !== 1) {
                return 'WhatsApp is switched off (Settings)';
            }
            return trim((string) ($s->api_ws_url ?? '')) !== '' && trim((string) ($s->api_ws_token ?? '')) !== '' ? '' : 'WhatsApp is not set up (Settings)';
        }
        return '';
    }

    /** A provider error in words people can act on. */
    function cdp_cmpErrorText($e)
    {
        $e = trim((string) $e);
        if (stripos($e, 'setFrom') !== false || stripos($e, 'Invalid address:  (From)') !== false || preg_match('/Invalid address:\s*\(?(setFrom|From)/i', $e)) {
            return 'E-mail sender address is not set (Settings)';
        }
        if (stripos($e, 'SMTP connect() failed') !== false || stripos($e, 'Could not connect to SMTP') !== false) {
            return 'Could not connect to the e-mail server (check SMTP in Settings)';
        }
        if (stripos($e, 'SMTP Error: Could not authenticate') !== false) {
            return 'The e-mail server refused the user name or password (Settings)';
        }
        return $e;
    }

    /** Reach of a direct send (service message: marketing consent not needed). */
    function cdp_cmpReachDirect(array $r, array $channels)
    {
        $channels = array_values(array_intersect($channels, array_keys(cdp_cmpChannels())));
        $out = ['customers' => count($r['ids']), 'contacts' => count($r['raw']), 'total' => count($r['ids']) + count($r['raw']), 'channels' => [], 'bad' => $r['bad']];
        $u = ['channels' => []];
        if ($r['ids']) {
            $u = cdp_cmpReachUsers('u.id IN (' . implode(',', array_map('intval', $r['ids'])) . ')', [], $channels, 'transactional');
        }
        foreach ($channels as $ch) {
            $row = ($u['channels'][$ch] ?? []) + ['ok' => 0];
            foreach ($r['raw'] as $x) {
                $fits = $ch !== 'inapp' && (($ch === 'email') === ($x['type'] === 'email'));
                $k = $fits ? 'ok' : 'no_address';
                $row[$k] = ($row[$k] ?? 0) + 1;
            }
            if ($ch !== 'inapp' && $row['ok'] > 0 && cdp_cmpChannelProblem($ch) !== '') {
                // Not queued at all on a channel that cannot deliver (cdp_cmpSendDirect).
                $row['not_configured'] = ($row['not_configured'] ?? 0) + $row['ok'];
                $row['ok'] = 0;
            }
            $out['channels'][$ch] = $row;
        }
        $out['people'] = cdp_cmpReachPeople($out);
        return $out;
    }

    // -----------------------------------------------------------------------
    // Message
    // -----------------------------------------------------------------------

    /**
     * Normalise the per-channel message posted by the page. E-mail HTML is
     * sanitised here. Returns the bodies JSON shape (see the file comment).
     */
    function cdp_cmpBodies($in)
    {
        $m = is_array($in) ? $in : cdp_opsDecode((string) $in, []);
        $out = [];
        foreach (['whatsapp', 'sms', 'inapp'] as $ch) {
            if (isset($m[$ch]) && is_array($m[$ch])) {
                $out[$ch] = ['body' => mb_substr(str_replace("\r\n", "\n", (string) ($m[$ch]['body'] ?? '')), 0, $ch === 'inapp' ? 350 : 4000)];
            }
        }
        if (isset($out['whatsapp']) && is_array($m['whatsapp']['media'] ?? null)) {
            $md = $m['whatsapp']['media'];
            $url = (string) ($md['url'] ?? '');
            if (preg_match('~^(https?://|files/|uploads/)~i', $url)) {
                $out['whatsapp']['media'] = ['url' => mb_substr($url, 0, 500), 'name' => mb_substr((string) ($md['name'] ?? ''), 0, 160), 'kind' => mb_substr((string) ($md['kind'] ?? ''), 0, 20),
                    'file' => mb_substr((string) ($md['file'] ?? ''), 0, 190)];
            }
        }
        if (isset($m['email']) && is_array($m['email'])) {
            $e = $m['email'];
            $html = (string) ($e['html'] ?? '');
            $design = $e['design_json'] ?? '';
            if (is_array($design)) {
                $design = cdp_opsJson($design);
            }
            $out['email'] = ['subject' => mb_substr(trim((string) ($e['subject'] ?? '')), 0, 190), 'preheader' => mb_substr(trim((string) ($e['preheader'] ?? '')), 0, 255),
                'html' => trim($html) !== '' ? cdp_commSanitizeEmailHtml($html) : '', 'design_json' => strlen((string) $design) < 3000000 ? (string) $design : ''];
        }
        if (isset($m['_meta']) && is_array($m['_meta'])) {
            $out['_meta'] = ['goal' => mb_substr(trim((string) ($m['_meta']['goal'] ?? '')), 0, 600)];
        }
        return $out;
    }

    /**
     * Does a text say anything beyond a greeting and placeholders? "Hi {first_name},"
     * (what the editors start with) does not.
     */
    function cdp_cmpHasText($body)
    {
        $t = preg_replace('/\{[a-z0-9_]+\}|\[[A-Z0-9_]+\]/i', ' ', (string) $body);
        $t = preg_replace('/^\s*(hi|hello|hey|dear|good (morning|afternoon|evening)|greetings)\b/i', '', $t);
        return trim(preg_replace('/[\s\p{P}\p{S}]+/u', '', $t)) !== '';
    }

    /** Problems that stop a message going out on these channels ([] = fine). */
    function cdp_cmpValidateMessage(array $bodies, array $channels, $campaign)
    {
        $err = [];
        if (!$channels) {
            $err[] = 'Switch on at least one channel.';
        }
        foreach ($channels as $ch) {
            $label = cdp_cmpChannels()[$ch] ?? $ch;
            if ($ch === 'email') {
                if (trim($bodies['email']['subject'] ?? '') === '') {
                    $err[] = 'E-mail: enter a subject line.';
                }
                $text = trim(strip_tags(preg_replace('~<(style|head)[^>]*>.*?</\1>~is', '', (string) ($bodies['email']['html'] ?? ''))));
                if ($text === '' && stripos((string) ($bodies['email']['html'] ?? ''), '<img') === false) {
                    $err[] = 'E-mail: add some content to the e-mail.';
                }
            } elseif (!cdp_cmpHasText((string) ($bodies[$ch]['body'] ?? '')) && !($ch === 'whatsapp' && !empty($bodies['whatsapp']['media']))) {
                $err[] = $label . ': write the message.';
            }
        }
        if ($campaign && in_array('whatsapp', $channels, true) && cdp_commSetting('wa_provider') === 'cloud' && trim((string) ($bodies['_wa_template'] ?? '')) === '') {
            $err[] = 'WhatsApp: the Cloud API only delivers campaigns as an approved template. Enter the template name, or switch WhatsApp off.';
        }
        return $err;
    }

    /** Queue options ($o of cdp_commQueueEvent / cdp_commQueueRaw) for a message. */
    function cdp_cmpQueueOptions(array $bodies, array $channels)
    {
        $o = ['channels' => array_values(array_diff($channels, ['inapp'])), 'bodies' => [], 'subjects' => []];
        foreach ($o['channels'] as $ch) {
            if ($ch === 'email') {
                $o['bodies']['email'] = '<!--sl-html-->' . (string) ($bodies['email']['html'] ?? '');
                $o['subjects']['email'] = (string) ($bodies['email']['subject'] ?? '');
            } else {
                $body = (string) ($bodies[$ch]['body'] ?? '');
                if ($ch === 'whatsapp' && !empty($bodies['whatsapp']['media']['url'])) {
                    // The outbox sends text: the attachment goes as a link the phone previews.
                    $url = (string) $bodies['whatsapp']['media']['url'];
                    if (!preg_match('~^https?://~i', $url)) {
                        $url = cdp_opsSiteUrl() . '/' . ltrim($url, '/');
                    }
                    $body = rtrim($body) . ($body !== '' ? "\n\n" : '') . $url;
                }
                $o['bodies'][$ch] = $body;
                $o['subjects'][$ch] = '';
            }
        }
        if (!empty($bodies['_wa_template'])) {
            $o['wa_template'] = ['name' => (string) $bodies['_wa_template'], 'lang' => (string) ($bodies['_wa_lang'] ?? 'en') ?: 'en', 'params' => array_values(array_filter(array_map('trim', explode(',', (string) ($bodies['_wa_params'] ?? '')))))];
        }
        return $o;
    }

    /** The customer's in-app bell (cdb_notifications), filled per customer. */
    function cdp_cmpInApp($userId, $text, array $vars = [])
    {
        $u = cdp_commUser((int) $userId);
        if (!$u) {
            return false;
        }
        $t = trim(strip_tags(cdp_commRender((string) $text, array_merge(cdp_commBaseVars($u), $vars))));
        if ($t === '') {
            return false;
        }
        $r = cdp_opsX("INSERT INTO cdb_notifications (user_id, order_id, notification_description, shipping_type, notification_date) VALUES (:u, 0, :d, 0, :n)",
            [':u' => (int) $userId, ':d' => mb_substr(trim(strip_tags($t)), 0, 350), ':n' => cdp_opsNow()]);   // plain text: the views escape it
        if ($r['ok'] && $r['id']) {
            cdp_opsX("INSERT INTO cdb_notifications_users (user_id, notification_id) VALUES (:u, :n)", [':u' => (int) $userId, ':n' => (int) $r['id']]);
            return true;
        }
        return false;
    }

    // -----------------------------------------------------------------------
    // Direct send (one person / several people)
    // -----------------------------------------------------------------------

    /**
     * Queue a service message to chosen customers and typed addresses.
     * $sendAt: unix time (null = now).
     *
     * @return array{ok:bool,error?:string,queued:int,inapp:int,skipped:array<string,int>,recipients:int,ids:int[]}
     */
    function cdp_cmpSendDirect(array $r, array $bodies, array $channels, $sendAt = null)
    {
        $out = ['ok' => true, 'queued' => 0, 'inapp' => 0, 'skipped' => [], 'recipients' => count($r['ids']) + count($r['raw']), 'ids' => []];
        if ($out['recipients'] === 0) {
            return ['ok' => false, 'error' => 'Choose at least one person.'] + $out;
        }
        if ($out['recipients'] > CDP_CMP_DIRECT_MAX) {
            return ['ok' => false, 'error' => 'That is more than ' . CDP_CMP_DIRECT_MAX . ' people. Send it as a campaign instead.'] + $out;
        }
        if ($sendAt && $sendAt > time() + 60 && in_array('inapp', $channels, true)) {
            // The bell has no queue: it would show at once. Say so instead of posting early.
            return ['ok' => false, 'error' => 'In-app notifications go out at once and cannot wait for a later time. Switch In-app off for a scheduled send, or send it now.'] + $out;
        }
        $o = cdp_cmpQueueOptions($bodies, $channels) + ['kind' => 'transactional', 'entity_type' => 'manual', 'send_after' => $sendAt ?: time()];
        unset($o['wa_template']);
        $skip = function ($why) use (&$out) {
            $out['skipped'][$why] = ($out['skipped'][$why] ?? 0) + 1;
        };
        // A channel that cannot deliver (switched off, not set up) is not queued: the message would only wait and fail.
        $out['not_configured'] = [];
        foreach ($o['channels'] as $ch) {
            $why = cdp_cmpChannelProblem($ch);
            if ($why !== '') {
                $out['not_configured'][$ch] = $why;
            }
        }
        if ($out['not_configured']) {
            $o['channels'] = array_values(array_diff($o['channels'], array_keys($out['not_configured'])));
            foreach ($out['not_configured'] as $ch => $why) {
                $n = count($r['ids']) + count(array_filter($r['raw'], function ($x) use ($ch) { return ($ch === 'email') === ($x['type'] === 'email'); }));
                $out['skipped'][$why] = ($out['skipped'][$why] ?? 0) + $n;
            }
        }
        foreach ($r['ids'] as $uid) {
            if ($o['channels']) {
                $q = cdp_commQueueEvent('manual', $uid, [], $o);
                $out['queued'] += $q['queued'];
                $out['ids'] = array_merge($out['ids'], $q['ids']);
                foreach ($q['skipped'] as $why) {
                    $skip($why);
                }
            }
            if (in_array('inapp', $channels, true)) {
                $p = cdp_commPrefs($uid);
                if (!empty($p['opted_out_all'])) {
                    $skip('Customer preferences');
                } elseif (cdp_cmpInApp($uid, (string) ($bodies['inapp']['body'] ?? ''))) {
                    $out['inapp']++;
                }
            }
        }
        foreach ($r['raw'] as $x) {
            foreach ($o['channels'] as $ch) {
                if (($ch === 'email') !== ($x['type'] === 'email')) {
                    continue;
                }
                $q = cdp_commQueueRaw($x['to'], $ch, [], $o + ['event' => 'manual']);
                $out['queued'] += $q['queued'];
                $out['ids'] = array_merge($out['ids'], $q['ids']);
                foreach ($q['skipped'] as $why) {
                    $skip($why);
                }
            }
        }
        return $out;
    }

    // -----------------------------------------------------------------------
    // Campaigns
    // -----------------------------------------------------------------------

    function cdp_cmpNeedsApproval()
    {
        return cdp_commSetting('campaign_needs_approval') === '1';
    }

    /** Load one campaign with its decoded audience and message. */
    function cdp_cmpGet($id)
    {
        $c = cdp_opsOne("SELECT * FROM cdb_comm_campaigns WHERE id = :id", [':id' => (int) $id]);
        if (!$c) {
            return null;
        }
        $aud = cdp_opsDecode($c->audience ?? '', []);
        if (!$aud && $c->segment_id) {
            // A campaign written on the old screen: its segment's rules.
            $seg = cdp_opsOne("SELECT definition FROM cdb_comm_segments WHERE id = :id", [':id' => (int) $c->segment_id]);
            $aud = $seg ? cdp_opsDecode($seg->definition, []) : [];
        }
        $bodies = cdp_opsDecode($c->bodies ?? '', []);
        $channels = array_values(array_filter(explode(',', (string) $c->channels)));
        if (!$bodies) {
            foreach ($channels as $ch) {
                $bodies[$ch] = $ch === 'email' ? ['subject' => (string) $c->subject, 'html' => nl2br(htmlspecialchars((string) $c->body, ENT_QUOTES, 'UTF-8'), false), 'design_json' => ''] : ['body' => (string) $c->body];
            }
        }
        if (!empty($c->design_json) && isset($bodies['email']) && empty($bodies['email']['design_json'])) {
            $bodies['email']['design_json'] = (string) $c->design_json;
        }
        if (!empty($c->wa_template_name)) {
            $bodies['_wa_template'] = (string) $c->wa_template_name;
        }
        return ['row' => $c, 'audience' => cdp_cmpAudience($aud), 'bodies' => $bodies, 'channels' => $channels];
    }

    /**
     * Create or update a campaign (draft, pending or scheduled may be edited;
     * an edit sends it back to draft).
     *
     * $d: id?, name, audience (array|JSON), bodies (array|JSON), channels[],
     *     scheduled_at ('Y-m-d H:i' | ''), wa_template, wa_lang, wa_params
     *
     * @return array{ok:bool,id?:int,error?:string}
     */
    function cdp_cmpSave(array $d, $uid)
    {
        if (!cdp_commEnsureTables()) {
            return ['ok' => false, 'error' => 'The communication tables are not ready.'];
        }
        $name = trim((string) ($d['name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'error' => 'Give the campaign a name (only your team sees it).'];
        }
        $channels = array_values(array_intersect(array_keys(cdp_cmpChannels()), (array) ($d['channels'] ?? [])));
        $aud = cdp_cmpAudience($d['audience'] ?? []);
        if ($ag = cdp_cmpAgencyScope()) {
            $aud['agency_id'] = $ag;   // the server's scope wins over anything posted
        }
        $bodies = cdp_cmpBodies($d['bodies'] ?? []);
        $wa = preg_replace('/[^a-z0-9_]/', '', strtolower(trim((string) ($d['wa_template'] ?? ''))));
        if ($wa !== '') {
            $bodies['_wa_template'] = $wa;
            $bodies['_wa_lang'] = preg_replace('/[^A-Za-z_]/', '', (string) ($d['wa_lang'] ?? 'en')) ?: 'en';
            $bodies['_wa_params'] = mb_substr(preg_replace('/[^a-z0-9_,]/', '', strtolower((string) ($d['wa_params'] ?? ''))), 0, 255);
        }
        $at = trim((string) ($d['scheduled_at'] ?? ''));
        if ($at !== '') {
            $ts = strtotime(str_replace('T', ' ', $at));
            if (!$ts) {
                return ['ok' => false, 'error' => 'The date and time to send could not be read. Choose it again.'];
            }
            $at = date('Y-m-d H:i:00', $ts);
        } else {
            $at = null;
        }
        $summary = '';
        foreach (['whatsapp', 'sms', 'inapp'] as $ch) {
            if ($summary === '' && in_array($ch, $channels, true)) {
                $summary = (string) ($bodies[$ch]['body'] ?? '');
            }
        }
        if ($summary === '' && isset($bodies['email'])) {
            $summary = trim(html_entity_decode(strip_tags(preg_replace('~<(style|head)[^>]*>.*?</\1>~is', '', (string) $bodies['email']['html'])), ENT_QUOTES, 'UTF-8'));
            $summary = mb_substr(preg_replace('/\s+/', ' ', $summary), 0, 2000);
        }
        $id = (int) ($d['id'] ?? 0);
        $now = cdp_opsNow();
        $bind = [':n' => mb_substr($name, 0, 160), ':c' => implode(',', $channels), ':sub' => mb_substr((string) ($bodies['email']['subject'] ?? ''), 0, 190),
            ':b' => $summary, ':at' => $at, ':t' => $now, ':aud' => cdp_opsJson($aud), ':bo' => cdp_opsJson($bodies),
            ':dj' => (string) ($bodies['email']['design_json'] ?? '') ?: null, ':wa' => $wa !== '' ? $wa : null];
        if ($id > 0) {
            $cur = cdp_opsOne("SELECT status FROM cdb_comm_campaigns WHERE id = :id", [':id' => $id]);
            if (!$cur || !in_array($cur->status, ['draft', 'pending', 'scheduled'], true)) {
                return ['ok' => false, 'error' => 'This campaign has started or was cancelled: duplicate it to change it.'];
            }
            $bind[':id'] = $id;
            // Only while it has not started: the worker may have picked it up since the check above.
            $r = cdp_opsX("UPDATE cdb_comm_campaigns SET name = :n, segment_id = NULL, channels = :c, subject = :sub, body = :b, scheduled_at = :at, status = 'draft', approved_by = NULL,
                             mode = 'campaign', audience = :aud, bodies = :bo, design_json = :dj, wa_template_name = :wa, updated_at = :t
                           WHERE id = :id AND status IN ('draft','pending','scheduled') AND launch_cursor = 0", $bind);
            if (!$r['ok']) {
                return ['ok' => false, 'error' => 'Not saved.'];
            }
            if ($r['rows'] !== 1 && !cdp_opsOne("SELECT id FROM cdb_comm_campaigns WHERE id = :id AND status = 'draft' AND launch_cursor = 0", [':id' => $id])) {
                // 0 rows also means "nothing changed" (saved twice in the same second): only a moved status is an error.
                return ['ok' => false, 'error' => 'This campaign has started or was cancelled: duplicate it to change it.'];
            }
        } else {
            $bind[':u'] = (int) $uid;
            $r = cdp_opsX("INSERT INTO cdb_comm_campaigns (name, channels, subject, body, status, scheduled_at, created_by, created_at, updated_at, mode, audience, bodies, design_json, wa_template_name, launch_cursor)
                           VALUES (:n, :c, :sub, :b, 'draft', :at, :u, :t, :t, 'campaign', :aud, :bo, :dj, :wa, 0)", $bind);
            $id = (int) $r['id'];
            if (!$r['ok'] || $id <= 0) {
                return ['ok' => false, 'error' => 'Not saved.'];
            }
        }
        return ['ok' => true, 'id' => $id];
    }

    /**
     * Ask for it to be sent: checked, counted, then waiting for approval (when
     * the setting asks for it and the writer may not approve their own) or
     * scheduled. The worker sends it from scheduled_at (now when empty).
     */
    function cdp_cmpSubmit($id, $uid, $rank)
    {
        $c = cdp_cmpGet($id);
        if (!$c || $c['row']->status !== 'draft') {
            return ['ok' => false, 'error' => 'Only a draft can be sent.'];
        }
        $err = cdp_cmpValidateMessage($c['bodies'], $c['channels'], true);
        if ($err) {
            return ['ok' => false, 'error' => implode(' ', $err), 'errors' => $err];
        }
        $reach = cdp_cmpReach($c['audience'], $c['channels']);
        if ($reach['people'] === 0) {
            return ['ok' => false, 'error' => 'Nobody in this audience can receive it on the chosen channels. Check the reasons under Who will get it.'];
        }
        $selfOk = $rank >= 4 || ($rank >= 3 && !cdp_cmpNeedsApproval());
        $status = cdp_cmpNeedsApproval() && !$selfOk ? 'pending' : 'scheduled';
        $r = cdp_opsX("UPDATE cdb_comm_campaigns SET status = :s, approved_by = :a, recipients_total = :n, launch_cursor = 0, updated_at = :t WHERE id = :id AND status = 'draft'",
            [':s' => $status, ':a' => $status === 'scheduled' ? (int) $uid : null, ':n' => (int) $reach['total'], ':t' => cdp_opsNow(), ':id' => (int) $id]);
        if (!$r['ok'] || $r['rows'] !== 1) {
            return ['ok' => false, 'error' => 'Not sent: the campaign changed meanwhile.'];
        }
        return ['ok' => true, 'status' => $status, 'reach' => $reach];
    }

    /** Approve a campaign waiting for approval (rank 3+; not your own unless super admin). */
    function cdp_cmpApprove($id, $uid, $rank)
    {
        if ($rank < 3) {
            return ['ok' => false, 'error' => 'Only an administrator approves campaigns.'];
        }
        $c = cdp_opsOne("SELECT * FROM cdb_comm_campaigns WHERE id = :id", [':id' => (int) $id]);
        if (!$c || $c->status !== 'pending') {
            return ['ok' => false, 'error' => 'This campaign is not waiting for approval.'];
        }
        if ((int) $c->created_by === (int) $uid && $rank < 4) {
            return ['ok' => false, 'error' => 'Another administrator must approve a campaign you wrote.'];
        }
        $r = cdp_opsX("UPDATE cdb_comm_campaigns SET status = 'scheduled', approved_by = :a, updated_at = :t WHERE id = :id AND status = 'pending'",
            [':a' => (int) $uid, ':t' => cdp_opsNow(), ':id' => (int) $id]);
        return $r['rows'] === 1 ? ['ok' => true, 'status' => 'scheduled'] : ['ok' => false, 'error' => 'Not approved.'];
    }

    /** Back to draft (pending or scheduled, not started). */
    function cdp_cmpUnschedule($id)
    {
        $r = cdp_opsX("UPDATE cdb_comm_campaigns SET status = 'draft', approved_by = NULL, updated_at = :t WHERE id = :id AND status IN ('pending','scheduled') AND launch_cursor = 0",
            [':t' => cdp_opsNow(), ':id' => (int) $id]);
        return $r['rows'] === 1 ? ['ok' => true] : ['ok' => false, 'error' => 'It has started sending: cancel it instead.'];
    }

    /** Stop a campaign: nothing more is queued and queued messages are cancelled. */
    function cdp_cmpCancel($id)
    {
        // A sent campaign whose messages still wait in the outbox (quiet hours) can be stopped too.
        $r = cdp_opsX("UPDATE cdb_comm_campaigns SET status = 'cancelled', updated_at = :t WHERE id = :id AND (status IN ('draft','pending','scheduled','sending','launching')
                         OR (status = 'sent' AND EXISTS (SELECT 1 FROM cdb_comm_outbox o WHERE o.campaign_id = :id2 AND o.status = 'queued')))",
            [':t' => cdp_opsNow(), ':id' => (int) $id, ':id2' => (int) $id]);
        if ($r['rows'] !== 1) {
            return ['ok' => false, 'error' => 'This campaign cannot be cancelled now.'];
        }
        $x = cdp_opsX("UPDATE cdb_comm_outbox SET status = 'cancelled', last_error = 'Campaign cancelled' WHERE campaign_id = :id AND status = 'queued'", [':id' => (int) $id]);
        return ['ok' => true, 'cancelled_messages' => (int) $x['rows']];
    }

    /** A new draft with the same audience and message. */
    function cdp_cmpDuplicate($id, $uid)
    {
        $c = cdp_cmpGet($id);
        if (!$c) {
            return ['ok' => false, 'error' => 'Campaign not found.'];
        }
        return cdp_cmpSave(['name' => mb_substr('Copy of ' . $c['row']->name, 0, 160), 'audience' => $c['audience'], 'bodies' => $c['bodies'], 'channels' => $c['channels'],
            'wa_template' => $c['bodies']['_wa_template'] ?? '', 'wa_lang' => $c['bodies']['_wa_lang'] ?? 'en', 'wa_params' => $c['bodies']['_wa_params'] ?? ''], $uid);
    }

    function cdp_cmpDelete($id)
    {
        $r = cdp_opsX("DELETE FROM cdb_comm_campaigns WHERE id = :id AND status = 'draft'", [':id' => (int) $id]);
        return $r['rows'] === 1 ? ['ok' => true] : ['ok' => false, 'error' => 'Only a draft can be deleted.'];
    }

    /**
     * Worker step: queue the next batch of every due campaign. Runs on every
     * worker pass whatever the automations switch says (a person approved it).
     *
     * @return array{campaigns:int,queued:int,excluded:int,finished:int}
     */
    function cdp_commCampaignTick($limit = null)
    {
        $limit = max(1, min(2000, (int) ($limit ?? CDP_CMP_BATCH)));
        $out = ['campaigns' => 0, 'queued' => 0, 'excluded' => 0, 'finished' => 0];
        if (!cdp_commEnsureTables()) {
            return $out;
        }
        $due = cdp_opsQ("SELECT id FROM cdb_comm_campaigns WHERE (status = 'scheduled' AND (scheduled_at IS NULL OR scheduled_at <= :n)) OR status IN ('sending', 'launching')
                         ORDER BY updated_at, id LIMIT 5", [':n' => cdp_opsNow()]);
        foreach ($due as $d) {
            $r = cdp_cmpRunBatch((int) $d->id, $limit);
            $out['campaigns']++;
            $out['queued'] += $r['queued'];
            $out['excluded'] += $r['excluded'];
            $out['finished'] += !empty($r['finished']) ? 1 : 0;
        }
        return $out;
    }

    /** Queue one batch of one campaign. */
    function cdp_cmpRunBatch($id, $limit = CDP_CMP_BATCH)
    {
        $res = ['queued' => 0, 'excluded' => 0, 'finished' => false];
        $c = cdp_cmpGet($id);
        if (!$c || !in_array($c['row']->status, ['scheduled', 'sending', 'launching'], true)) {
            return $res;
        }
        $row = $c['row'];
        if ($row->status !== 'sending') {
            $cl = cdp_opsX("UPDATE cdb_comm_campaigns SET status = 'sending', launched_at = COALESCE(launched_at, :n) WHERE id = :id AND status IN ('scheduled','launching')",
                [':n' => cdp_opsNow(), ':id' => (int) $id]);
            if ($cl['rows'] !== 1) {
                return $res;
            }
        }
        $a = $c['audience'];
        $channels = $c['channels'];
        $o = cdp_cmpQueueOptions($c['bodies'], $channels) + ['kind' => 'marketing', 'campaign_id' => (int) $id, 'entity_type' => 'campaign',
            'entity_ref' => 'campaign-' . (int) $id, 'send_after' => cdp_commQuietUntil()];
        $cursor = (int) $row->launch_cursor;
        $unsubOk = cdp_opsTableExists('cdb_newsletter_unsubscribes');

        if ($cursor >= 0 && !empty($a['customers'])) {
            list($w, $b) = cdp_cmpAudienceSql($a);
            $batch = cdp_opsQ("SELECT u.id, u.email FROM cdb_users u WHERE $w AND u.id > :cmp_cur ORDER BY u.id LIMIT " . (int) $limit, $b + [':cmp_cur' => $cursor]);
            $ids = array_map(function ($r) { return (int) $r->id; }, $batch);
            $next = $ids ? end($ids) : $cursor;
            $done = count($ids) < $limit;
            $newCursor = $done ? -1 : $next;
            // Claim the batch before queueing it: a second worker sees the cursor moved
            // (updated_at changes too, so the row always counts as changed).
            $cl = cdp_opsX("UPDATE cdb_comm_campaigns SET launch_cursor = :nc, updated_at = :n WHERE id = :id AND launch_cursor = :oc AND status = 'sending'",
                [':nc' => $newCursor, ':n' => cdp_opsNow(), ':id' => (int) $id, ':oc' => $cursor]);
            if ($cl['rows'] !== 1) {
                return $res;
            }
            // E-mail addresses on the unsubscribe list, in one query.
            $unsub = [];
            if (in_array('email', $o['channels'], true) && $unsubOk && $batch) {
                $eb = [];
                foreach (array_values(array_unique(array_filter(array_map(function ($r) { return strtolower(trim((string) $r->email)); }, $batch)))) as $i => $e) {
                    $eb[':e' . $i] = $e;
                }
                if ($eb) {
                    foreach (cdp_opsQ("SELECT email FROM cdb_newsletter_unsubscribes WHERE email IN (" . implode(',', array_keys($eb)) . ")", $eb) as $r) {
                        $unsub[strtolower((string) $r->email)] = true;
                    }
                }
            }
            foreach ($batch as $br) {
                $uid = (int) $br->id;
                $oo = $o + ['dedupe' => 'cmp' . (int) $id . ':' . $uid];
                if (in_array('email', $oo['channels'], true) && isset($unsub[strtolower(trim((string) $br->email))])) {
                    $oo['channels'] = array_values(array_diff($oo['channels'], ['email']));
                    $res['excluded']++;
                }
                if ($oo['channels']) {
                    $q = cdp_commQueueEvent('campaign', $uid, [], $oo);
                    $res['queued'] += $q['queued'];
                    $res['excluded'] += count($q['skipped']);
                }
                if (in_array('inapp', $channels, true)) {
                    $p = cdp_commPrefs($uid);
                    if (empty($p['opted_out_all']) && cdp_cmpInApp($uid, (string) ($c['bodies']['inapp']['body'] ?? ''))) {
                        $res['queued']++;
                    } else {
                        $res['excluded']++;
                    }
                }
            }
            cdp_cmpBump($id, $res);
            if (!$done) {
                return $res;
            }
            $cursor = -1;
        } elseif ($cursor >= 0) {
            cdp_opsX("UPDATE cdb_comm_campaigns SET launch_cursor = -1, updated_at = :n WHERE id = :id AND launch_cursor = :oc", [':n' => cdp_opsNow(), ':id' => (int) $id, ':oc' => $cursor]);
            $cursor = -1;
        }

        // Contacts phase: the same states as the reach preview (cdp_cmpContactRows).
        if (cdp_cmpContactsInfo() && !empty($a['contacts']['on'])) {
            $last = -$cursor - 1;
            $rows = cdp_cmpContactRows($a, $channels, $last, $limit);
            $done = count($rows) < $limit;
            if ($rows) {
                // Claim only when the cursor moves (an UPDATE that changes nothing reports 0 rows).
                $lastId = (int) end($rows)['cid'];
                $cl = cdp_opsX("UPDATE cdb_comm_campaigns SET launch_cursor = :nc, updated_at = :n WHERE id = :id AND launch_cursor = :oc AND status = 'sending'",
                    [':nc' => -1 - $lastId, ':n' => cdp_opsNow(), ':id' => (int) $id, ':oc' => $cursor]);
                if ($cl['rows'] !== 1) {
                    return $res;
                }
            }
            $part = ['queued' => 0, 'excluded' => 0];
            foreach ($rows as $r) {
                foreach ($o['channels'] as $ch) {
                    if (($r['state'][$ch] ?? '') !== 'ok') {
                        if (($r['state'][$ch] ?? '') !== 'customer_contact') {
                            $part['excluded']++;
                        }
                        continue;
                    }
                    $to = $ch === 'email' ? $r['email'] : $r['phone'];
                    // The owner chose that saved WhatsApp contacts may receive campaigns (consent);
                    // a matching customer account's own choices still apply inside cdp_commQueueRaw.
                    $q = cdp_commQueueRaw($to, $ch, [], $o + ['event' => 'campaign', 'consent' => true, 'name' => $r['name'],
                        'dedupe' => 'cmp' . (int) $id . ':c' . (int) $r['cid']]);
                    $part['queued'] += $q['queued'];
                    $part['excluded'] += count($q['skipped']);
                }
            }
            $res['queued'] += $part['queued'];
            $res['excluded'] += $part['excluded'];
            cdp_cmpBump($id, $part);
            if (!$done) {
                return $res;
            }
        }
        cdp_opsX("UPDATE cdb_comm_campaigns SET status = 'sent', updated_at = :n WHERE id = :id AND status = 'sending'", [':n' => cdp_opsNow(), ':id' => (int) $id]);
        $res['finished'] = true;
        return $res;
    }

    function cdp_cmpBump($id, array $r)
    {
        if (!empty($r['queued']) || !empty($r['excluded'])) {
            cdp_opsX("UPDATE cdb_comm_campaigns SET queued_count = queued_count + :q, excluded_count = excluded_count + :e, updated_at = :n WHERE id = :id",
                [':q' => (int) $r['queued'], ':e' => (int) $r['excluded'], ':n' => cdp_opsNow(), ':id' => (int) $id]);
        }
    }

    /** Status labels and pill classes for the list. */
    function cdp_cmpStatuses()
    {
        return ['draft' => ['Draft', ''], 'pending' => ['Waiting for approval', 'onway'], 'scheduled' => ['Scheduled', 'transit'], 'launching' => ['Starting', 'transit'],
            'sending' => ['Sending', 'sorting'], 'sent' => ['Sent', 'delivered'], 'cancelled' => ['Cancelled', 'pending']];
    }

    /**
     * Campaign list with delivery counts (outbox) and e-mail opens / clicks
     * (cdb_message_events, batch campaign-<id>).
     */
    function cdp_cmpList($status = '', $limit = 60)
    {
        list($w, $b) = cdp_cmpScopeSql();
        if ($status !== '' && isset(cdp_cmpStatuses()[$status])) {
            $w .= ' AND c.status = :s';
            $b[':s'] = $status;
        }
        $rows = cdp_opsQ("SELECT c.id, c.name, c.channels, c.subject, c.status, c.scheduled_at, c.queued_count, c.excluded_count, c.recipients_total, c.created_by, c.approved_by,
                            c.created_at, c.updated_at, c.launched_at, c.audience, c.segment_id, c.wa_template_name
                          FROM cdb_comm_campaigns c WHERE $w ORDER BY c.id DESC LIMIT " . (int) $limit, $b);
        if (!$rows) {
            return [];
        }
        $ids = array_map(function ($r) { return (int) $r->id; }, $rows);
        $stats = [];
        foreach (cdp_opsQ("SELECT campaign_id, SUM(status = 'sent') sent, SUM(status = 'failed') failed, SUM(status IN ('queued','sending')) waiting, SUM(status = 'cancelled') cancelled
                           FROM cdb_comm_outbox WHERE campaign_id IN (" . implode(',', $ids) . ") GROUP BY campaign_id") as $s) {
            $stats[(int) $s->campaign_id] = $s;
        }
        $ev = [];
        if (cdp_opsTableExists('cdb_message_events')) {
            $batches = array_map(function ($i) { return "'campaign-" . (int) $i . "'"; }, $ids);
            foreach (cdp_opsQ("SELECT batch_id, COUNT(DISTINCT CASE WHEN event = 'open' THEN recipient_to END) o, COUNT(DISTINCT CASE WHEN event = 'click' THEN recipient_to END) c
                               FROM cdb_message_events WHERE batch_id IN (" . implode(',', $batches) . ") GROUP BY batch_id") as $e) {
                $ev[(string) $e->batch_id] = $e;
            }
        }
        // Writers and approvers in one query.
        $names = [];
        $pids = array_values(array_unique(array_filter(array_merge(array_map(function ($r) { return (int) $r->created_by; }, $rows), array_map(function ($r) { return (int) $r->approved_by; }, $rows)))));
        if ($pids) {
            foreach (cdp_opsQ("SELECT id, fname, lname, username FROM cdb_users WHERE id IN (" . implode(',', $pids) . ")") as $u) {
                $names[(int) $u->id] = trim($u->fname . ' ' . $u->lname) ?: (string) $u->username;
            }
        }
        $out = [];
        $st = cdp_cmpStatuses();
        foreach ($rows as $r) {
            $s = $stats[(int) $r->id] ?? null;
            $e = $ev['campaign-' . (int) $r->id] ?? null;
            $aud = cdp_opsDecode($r->audience ?? '', []);
            $label = $st[$r->status][0] ?? ucfirst((string) $r->status);
            $pill = $st[$r->status][1] ?? '';
            if ($r->status === 'sent' && $s && (int) $s->waiting > 0) {
                // Everyone is queued but messages still wait (quiet hours, retries): not "Sent" yet.
                $label = (int) $s->sent > 0 ? 'Sending' : 'Waiting to go out';
                $pill = 'sorting';
            }
            $out[] = ['id' => (int) $r->id, 'name' => (string) $r->name, 'channels' => array_values(array_filter(explode(',', (string) $r->channels))),
                'status' => (string) $r->status, 'status_label' => $label, 'pill' => $pill,
                'subject' => (string) $r->subject, 'audience' => $aud ? cdp_cmpAudienceLabel(cdp_cmpAudience($aud)) : ($r->segment_id ? 'Saved segment #' . (int) $r->segment_id : ''),
                'scheduled_at' => (string) $r->scheduled_at, 'created_at' => (string) $r->created_at, 'launched_at' => (string) $r->launched_at,
                'created_by' => (int) $r->created_by, 'created_by_name' => $names[(int) $r->created_by] ?? '', 'approved_by_name' => $names[(int) $r->approved_by] ?? '',
                'recipients' => (int) $r->recipients_total, 'queued' => (int) $r->queued_count, 'excluded' => (int) $r->excluded_count,
                'sent' => $s ? (int) $s->sent : 0, 'failed' => $s ? (int) $s->failed : 0, 'waiting' => $s ? (int) $s->waiting : 0,
                'opens' => $e ? (int) $e->o : 0, 'clicks' => $e ? (int) $e->c : 0, 'batch' => 'campaign-' . (int) $r->id];
        }
        return $out;
    }
}
