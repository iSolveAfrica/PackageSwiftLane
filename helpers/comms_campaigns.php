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
        return $src === 'whatsapp_chat' || $src === 'whatsapp' ? 'WhatsApp chats' : $src;
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
                'source' => $pick(['source', 'origin']), 'optout' => $pick(['opted_out', 'opt_out', 'unsubscribed', 'stopped']),
                'user' => $pick(['user_id']), 'email' => $pick(['email'])];
            break;
        }
        return $info;
    }

    /** Sources contacts were saved from (e.g. whatsapp_group:<name>), with counts. */
    function cdp_cmpContactSources()
    {
        $c = cdp_cmpContactsInfo();
        if (!$c || !$c['source']) {
            return [];
        }
        $out = [];
        foreach (cdp_opsQ("SELECT `{$c['source']}` s, COUNT(*) n FROM `{$c['table']}` GROUP BY `{$c['source']}` ORDER BY n DESC LIMIT 100") as $r) {
            if ((string) $r->s !== '') {
                $out[] = ['k' => (string) $r->s, 'label' => cdp_cmpSourceLabel((string) $r->s), 'n' => (int) $r->n];
            }
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
            if (substr($src, -1) === '*') {
                $w .= " AND c.`{$c['source']}` LIKE :cmp_src";
                $b[':cmp_src'] = str_replace(['%', '_'], ['\\%', '\\_'], substr($src, 0, -1)) . '%';
            } else {
                $w .= " AND c.`{$c['source']}` = :cmp_src";
                $b[':cmp_src'] = $src;
            }
        }
        return [$w, $b];
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
            'customer_contact' => 'Also a customer: reached as a customer', 'not_found' => 'Not a customer and not a valid phone or e-mail'];
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
     * Reach of the WhatsApp contacts of an audience (WhatsApp and SMS; e-mail
     * when the table has an e-mail column). Contacts whose phone belongs to a
     * customer are reached as customers when customers are targeted too.
     */
    function cdp_cmpReachContacts(array $a, array $channels)
    {
        $c = cdp_cmpContactsInfo();
        $out = ['total' => 0, 'channels' => []];
        if (!$c || empty($a['contacts']['on'])) {
            return $out;
        }
        list($w, $b) = cdp_cmpContactsWhere($a, $c);
        $out['total'] = (int) cdp_opsVal("SELECT COUNT(*) FROM `{$c['table']}` c WHERE $w", $b);
        $stop = $c['optout'] ? "COALESCE(c.`{$c['optout']}`, 0) = 1" : '0';
        $cust = !empty($a['customers']) && $c['user'] ? "COALESCE(c.`{$c['user']}`, 0) > 0" : '0';
        foreach ($channels as $ch) {
            if ($ch === 'inapp' || ($ch === 'email' && !$c['email'])) {
                $out['channels'][$ch] = ['ok' => 0, 'no_address' => $out['total']];
                continue;
            }
            $addr = $ch === 'email' ? "c.`{$c['email']}` LIKE '%_@_%'" : '1';
            $row = ['ok' => 0];
            foreach (cdp_opsQ("SELECT CASE WHEN NOT ($addr) THEN 'no_address' WHEN $stop THEN 'contact_opted_out' WHEN $cust THEN 'customer_contact' ELSE 'ok' END r, COUNT(*) n
                               FROM `{$c['table']}` c WHERE $w GROUP BY r", $b) as $r) {
                $row[(string) $r->r] = (int) $r->n;
            }
            $out['channels'][$ch] = $row;
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
     * stay typed addresses (sent with cdp_commQueueRaw).
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
        foreach ($toks as $tok) {
            $tok = trim($tok, " \t\n\r\0\x0B<>\"'");
            if ($tok === '' || isset($seen[strtolower($tok)])) {
                continue;
            }
            $seen[strtolower($tok)] = true;
            if (strpos($tok, '@') !== false) {
                if (!filter_var($tok, FILTER_VALIDATE_EMAIL)) {
                    $out['bad'][] = $tok;
                    continue;
                }
                $u = cdp_opsOne("SELECT u.id FROM cdb_users u WHERE u.active = 1 AND u.userlevel IN ($roles) AND u.email = :e$sc LIMIT 1", [':e' => $tok]);
                if ($u) {
                    $out['ids'][] = (int) $u->id;
                } else {
                    $out['raw'][] = ['to' => strtolower($tok), 'type' => 'email'];
                }
                continue;
            }
            $digits = preg_replace('/\D+/', '', $tok);
            if (strlen($digits) < 7 || strlen($digits) > 15) {
                $out['bad'][] = $tok;
                continue;
            }
            $u = cdp_opsOne("SELECT u.id FROM cdb_users u WHERE u.active = 1 AND u.userlevel IN ($roles)
                             AND REPLACE(REPLACE(REPLACE(REPLACE(u.phone,'+',''),' ',''),'-',''),'(','') LIKE :p$sc LIMIT 1", [':p' => '%' . substr($digits, -9)]);
            if ($u) {
                $out['ids'][] = (int) $u->id;
            } else {
                $out['raw'][] = ['to' => (strpos($tok, '+') === 0 ? '+' : '') . $digits, 'type' => 'phone'];
            }
        }
        $out['ids'] = array_values(array_unique($out['ids']));
        return $out;
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
            } elseif (trim($bodies[$ch]['body'] ?? '') === '' && !($ch === 'whatsapp' && !empty($bodies['whatsapp']['media']))) {
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
        $t = trim(cdp_commRender((string) $text, array_merge(cdp_commBaseVars($u), $vars)));
        if ($t === '') {
            return false;
        }
        $r = cdp_opsX("INSERT INTO cdb_notifications (user_id, order_id, notification_description, shipping_type, notification_date) VALUES (:u, 0, :d, 0, :n)",
            [':u' => (int) $userId, ':d' => mb_substr(htmlspecialchars($t, ENT_QUOTES, 'UTF-8'), 0, 350), ':n' => cdp_opsNow()]);
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
        $o = cdp_cmpQueueOptions($bodies, $channels) + ['kind' => 'transactional', 'entity_type' => 'manual', 'send_after' => $sendAt ?: time()];
        unset($o['wa_template']);
        $skip = function ($why) use (&$out) {
            $out['skipped'][$why] = ($out['skipped'][$why] ?? 0) + 1;
        };
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
        if (!isset($aud['agency_id']) && ($ag = cdp_cmpAgencyScope())) {
            $aud['agency_id'] = $ag;
        }
        $bodies = cdp_cmpBodies($d['bodies'] ?? []);
        $wa = preg_replace('/[^a-z0-9_]/', '', strtolower(trim((string) ($d['wa_template'] ?? ''))));
        if ($wa !== '') {
            $bodies['_wa_template'] = $wa;
            $bodies['_wa_lang'] = preg_replace('/[^A-Za-z_]/', '', (string) ($d['wa_lang'] ?? 'en')) ?: 'en';
            $bodies['_wa_params'] = mb_substr(preg_replace('/[^a-z0-9_,]/', '', strtolower((string) ($d['wa_params'] ?? ''))), 0, 255);
        }
        $at = trim((string) ($d['scheduled_at'] ?? ''));
        $at = preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}$/', $at) ? str_replace('T', ' ', $at) . ':00' : null;
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
            $r = cdp_opsX("UPDATE cdb_comm_campaigns SET name = :n, segment_id = NULL, channels = :c, subject = :sub, body = :b, scheduled_at = :at, status = 'draft', approved_by = NULL,
                             mode = 'campaign', audience = :aud, bodies = :bo, design_json = :dj, wa_template_name = :wa, updated_at = :t WHERE id = :id", $bind);
            if (!$r['ok']) {
                return ['ok' => false, 'error' => 'Not saved.'];
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
                         ORDER BY id LIMIT 5", [':n' => cdp_opsNow()]);
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
            $ids = array_map(function ($r) { return (int) $r->id; },
                cdp_opsQ("SELECT u.id FROM cdb_users u WHERE $w AND u.id > :cmp_cur ORDER BY u.id LIMIT " . (int) $limit, $b + [':cmp_cur' => $cursor]));
            $next = $ids ? end($ids) : $cursor;
            $done = count($ids) < $limit;
            $newCursor = $done ? -1 : $next;
            // Claim the batch before queueing it: a second worker sees the cursor moved.
            $cl = cdp_opsX("UPDATE cdb_comm_campaigns SET launch_cursor = :nc WHERE id = :id AND launch_cursor = :oc AND status = 'sending'",
                [':nc' => $newCursor, ':id' => (int) $id, ':oc' => $cursor]);
            if ($cl['rows'] !== 1) {
                return $res;
            }
            foreach ($ids as $uid) {
                $oo = $o + ['dedupe' => 'cmp' . (int) $id . ':' . $uid];
                if (in_array('email', $oo['channels'], true) && $unsubOk) {
                    $em = (string) cdp_opsVal("SELECT email FROM cdb_users WHERE id = :id", [':id' => $uid], '');
                    if ($em !== '' && cdp_opsOne("SELECT 1 x FROM cdb_newsletter_unsubscribes WHERE email = :e LIMIT 1", [':e' => strtolower($em)])) {
                        $oo['channels'] = array_values(array_diff($oo['channels'], ['email']));
                        $res['excluded']++;
                    }
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
            cdp_opsX("UPDATE cdb_comm_campaigns SET launch_cursor = -1 WHERE id = :id AND launch_cursor = :oc", [':id' => (int) $id, ':oc' => $cursor]);
            $cursor = -1;
        }

        // Contacts phase.
        $ci = cdp_cmpContactsInfo();
        if ($ci && !empty($a['contacts']['on'])) {
            $last = -$cursor - 1;
            list($w, $b) = cdp_cmpContactsWhere($a, $ci);
            $cols = "c.`{$ci['id']}` cid, c.`{$ci['phone']}` phone" . ($ci['name'] ? ", c.`{$ci['name']}` name" : '') . ($ci['optout'] ? ", c.`{$ci['optout']}` stop" : '')
                . ($ci['user'] ? ", c.`{$ci['user']}` uid" : '') . ($ci['email'] ? ", c.`{$ci['email']}` email" : '');
            $rows = cdp_opsQ("SELECT $cols FROM `{$ci['table']}` c WHERE $w AND c.`{$ci['id']}` > :cmp_last ORDER BY c.`{$ci['id']}` LIMIT " . (int) $limit, $b + [':cmp_last' => $last]);
            $done = count($rows) < $limit;
            $lastId = $rows ? (int) end($rows)->cid : $last;
            $cl = cdp_opsX("UPDATE cdb_comm_campaigns SET launch_cursor = :nc WHERE id = :id AND launch_cursor = :oc AND status = 'sending'",
                [':nc' => -1 - $lastId, ':id' => (int) $id, ':oc' => $cursor]);
            if ($cl['rows'] !== 1) {
                return $res;
            }
            $part = ['queued' => 0, 'excluded' => 0];
            foreach ($rows as $r) {
                if (!empty($r->stop) || (!empty($a['customers']) && !empty($r->uid))) {
                    $part['excluded']++;
                    continue;
                }
                foreach ($o['channels'] as $ch) {
                    $to = $ch === 'email' ? (string) ($r->email ?? '') : (string) $r->phone;
                    if ($to === '') {
                        continue;
                    }
                    // The owner chose that saved WhatsApp contacts may receive campaigns (consent);
                    // a matching customer account's own choices still apply inside cdp_commQueueRaw.
                    $q = cdp_commQueueRaw($to, $ch, [], $o + ['event' => 'campaign', 'consent' => true, 'name' => (string) ($r->name ?? ''),
                        'dedupe' => 'cmp' . (int) $id . ':c' . (int) $r->cid]);
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
        $w = '1=1';
        $b = [];
        if ($status !== '' && isset(cdp_cmpStatuses()[$status])) {
            $w = 'c.status = :s';
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
        $names = [];
        $out = [];
        $st = cdp_cmpStatuses();
        foreach ($rows as $r) {
            $s = $stats[(int) $r->id] ?? null;
            $e = $ev['campaign-' . (int) $r->id] ?? null;
            foreach ([(int) $r->created_by, (int) $r->approved_by] as $p) {
                if ($p > 0 && !isset($names[$p])) {
                    $names[$p] = cdp_opsUserName($p);
                }
            }
            $aud = cdp_opsDecode($r->audience ?? '', []);
            $out[] = ['id' => (int) $r->id, 'name' => (string) $r->name, 'channels' => array_values(array_filter(explode(',', (string) $r->channels))),
                'status' => (string) $r->status, 'status_label' => $st[$r->status][0] ?? ucfirst((string) $r->status), 'pill' => $st[$r->status][1] ?? '',
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
