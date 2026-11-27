<?php
// *************************************************************************
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *************************************************************************

/**
 * One switch for every message: the transport-layer gate.
 *
 * The old per-screen code (status screens, registration, invoices ...) sends
 * on its own. The Automation Center (helpers/comms.php) also announces status
 * changes. To avoid double sends and to let an administrator switch any
 * message off in one place, every transport asks this gate first:
 *
 *   sendNotificationWhatsApp_v2()   ajax/notify_whatsapp/api_whatsapp_service_v2.php
 *   cdp_sendSms()                   helpers/hubtel_sms.php (Hubtel and mNotify)
 *   cdp_smsSendNotification()       helpers/hubtel_sms.php (legacy ClickSend path)
 *   PHPMailer::send()               helpers/phpmailer/class.phpmailer.php
 *   cdp_sendTemplateEmail()         helpers/querys.php (PHP mail() path)
 *
 * cdp_commLegacyAllowed($channel) is false when
 *   - comms.status_owner = comms and the message is a status announcement of
 *     the old code (source tracking_update, bulk_status_update, delivery,
 *     consolidation_update, shipment_updated): the Automation Center sends it;
 *   - rules['legacy:<source>'][$channel] === 0: the administrator switched that
 *     old message off on the Automations page.
 * It never blocks the Automation Center itself (sources comms_*), one-time
 * codes (otp), campaigns, push, or a message to a member of staff. It fails
 * open: any error means "allowed".
 *
 * A blocked send is written to the message log as 'skipped' so the Outbox
 * shows it ("Sent by Automation Center" / "Switched off in Automations").
 */

if (!function_exists('cdp_commLegacyAllowed')) {

    /** 'legacy' (old per-screen code sends status messages) or 'comms'. */
    function cdp_commStatusOwner()
    {
        try {
            require_once __DIR__ . '/ops_db.php';
            return cdp_opsSetting('comms', 'status_owner', 'legacy') === 'comms' ? 'comms' : 'legacy';
        } catch (Throwable $e) {
            return 'legacy';
        }
    }

    /**
     * Hand status messages to the Automation Center (or back). Refused unless
     * automations are on and the worker ran in the last 15 minutes, so a
     * switch never leaves customers without messages.
     *
     * @return array{ok:bool,error?:string,owner?:string}
     */
    function cdp_commSetStatusOwner($owner, $userId = null)
    {
        require_once __DIR__ . '/comms.php';
        $owner = $owner === 'comms' ? 'comms' : 'legacy';
        if ($owner === 'comms') {
            if (cdp_commSetting('enabled') !== '1') {
                return ['ok' => false, 'error' => 'Switch automations on first.'];
            }
            $last = (string) cdp_opsSetting('comms', 'worker_last_run', '');
            if ($last === '' || strtotime($last) < time() - 900) {
                return ['ok' => false, 'error' => 'The message worker has not run in the last 15 minutes (cron/comms_worker.php every 5 minutes). Set up the cron job first.'];
            }
        }
        $ok = cdp_opsSettingSet('comms', 'status_owner', $owner, $userId);
        if ($ok && $owner === 'comms') {
            // Statuses the old screens sent with a Notification Studio text keep
            // being announced on those channels (once; an explicit switch wins).
            $intent = cdp_opsDecode((string) cdp_opsSetting('comms', 'v2_status_intent', ''), []);
            if ($intent) {
                $rules = cdp_commRules();
                $add = [];
                foreach ($intent as $key => $chs) {
                    if (!isset($rules[$key]) && is_array($chs)) {
                        $add[$key] = $chs;
                    }
                }
                if ($add) {
                    cdp_commSaveRules($add);
                }
                cdp_opsSettingSet('comms', 'v2_status_intent', '', $userId);
            }
            cdp_commRunStatusNow();
        }
        return $ok ? ['ok' => true, 'owner' => $owner] : ['ok' => false, 'error' => 'Not saved.'];
    }

    /** The source of the message being sent now: context first, else the endpoint. */
    function cdp_commGateSource()
    {
        $src = '';
        if (function_exists('cdp_msgContext')) {
            $src = (string) (cdp_msgContext()['source'] ?? '');
        }
        if ($src === '' && function_exists('cdp_msgInferSource')) {
            $src = (string) (cdp_msgInferSource()[0] ?? '');
        }
        return $src;
    }

    /** Sources the gate never blocks. */
    function cdp_commGateExempt($source)
    {
        $source = (string) $source;
        foreach (['comms_', 'otp', 'campaign', 'push', 'newsletter', 'sms_test'] as $p) {
            if (strpos($source, $p) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Pure decision, unit tested: may the old code send on $channel?
     *
     * @return array{0:bool,1:string} [allowed, reason when blocked]
     */
    function cdp_commGateDecide($source, $channel, $owner, array $rules, array $statusFamily)
    {
        $source = (string) $source;
        if ($source === '' || cdp_commGateExempt($source)) {
            return [true, ''];
        }
        if ($owner === 'comms' && in_array($source, $statusFamily, true)) {
            return [false, 'Sent by Automation Center'];
        }
        if (isset($rules['legacy:' . $source][$channel]) && (int) $rules['legacy:' . $source][$channel] === 0) {
            return [false, 'Switched off in Automations'];
        }
        return [true, ''];
    }

    /** Is this recipient a member of staff (never blocked)? */
    function cdp_commGateIsStaff($recipientUserId = 0, $to = '')
    {
        try {
            require_once __DIR__ . '/ops_db.php';
            $u = null;
            if ((int) $recipientUserId > 0) {
                $u = cdp_opsOne("SELECT userlevel FROM cdb_users WHERE id = :id", [':id' => (int) $recipientUserId]);
            } elseif (filter_var((string) $to, FILTER_VALIDATE_EMAIL)) {
                $u = cdp_opsOne("SELECT userlevel FROM cdb_users WHERE email = :e LIMIT 1", [':e' => (string) $to]);
            }
            if (!$u) {
                return false;
            }
            require_once __DIR__ . '/rbac.php';
            return function_exists('cdp_roleRankById') && cdp_roleRankById((int) $u->userlevel) >= 2;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * May the old per-screen code send this message? Call at a transport
     * before anything goes out. $who: ['id' => recipient user id, 'to' => address,
     * 'name' => ..., 'body' => ..., 'subject' => ...] for the skipped log row
     * and the staff exemption. Logs a 'skipped' row when it says no
     * (unless $who['no_log']).
     */
    function cdp_commLegacyAllowed($channel, array $who = [])
    {
        try {
            require_once __DIR__ . '/ops_db.php';
            $settings = cdp_opsSettings('comms');
            $owner = ($settings['status_owner'] ?? 'legacy') === 'comms' ? 'comms' : 'legacy';
            $rules = json_decode((string) ($settings['rules'] ?? '{}'), true);
            $rules = is_array($rules) ? $rules : [];
            // Fast path: no old-screen message switched off and statuses still with the old code.
            if ($owner === 'legacy' && !preg_match('/"legacy:[^"]*":\{[^}]*:0/', (string) ($settings['rules'] ?? ''))) {
                return true;
            }
            require_once __DIR__ . '/comms_triggers.php';
            $source = cdp_commGateSource();
            [$ok, $reason] = cdp_commGateDecide($source, (string) $channel, $owner, $rules, cdp_commStatusFamilySources());
            if ($ok) {
                return true;
            }
            if ($owner === 'comms' && $reason === 'Sent by Automation Center') {
                cdp_commRunStatusNow();   // same speed as the old instant send
            }
            if (cdp_commGateIsStaff((int) ($who['id'] ?? 0), (string) ($who['to'] ?? ''))) {
                return true;
            }
            if (empty($who['no_log']) && function_exists('cdp_msgLog')) {
                cdp_msgLog(['channel' => (string) $channel, 'status' => 'skipped', 'status_detail' => $reason,
                    'recipient_user_id' => (int) ($who['id'] ?? 0), 'recipient_name' => (string) ($who['name'] ?? ''),
                    'recipient_to' => (string) ($who['to'] ?? ''), 'body' => (string) ($who['body'] ?? ''), 'subject' => (string) ($who['subject'] ?? '')]);
            }
            return false;
        } catch (Throwable $e) {
            error_log('comms gate: ' . $e->getMessage());
            return true;
        }
    }

    /**
     * With status_owner = comms, a status screen's message goes out right
     * after the response instead of waiting for the 5-minute worker: one
     * status scan plus up to 20 due outbox rows. Registered at most once per
     * request; the outbox dedupe key makes a concurrent cron pass harmless.
     */
    function cdp_commRunStatusNow()
    {
        static $registered = false;
        if ($registered || cdp_commStatusOwner() !== 'comms') {
            return false;
        }
        $registered = true;
        require_once __DIR__ . '/after_response.php';
        cdp_afterResponse(function () {
            try {
                require_once __DIR__ . '/comms.php';
                if (cdp_commSetting('enabled') === '1' && cdp_commEnsureTables()) {
                    cdp_commScanStatusHistory();
                    cdp_commDeliverDue(20);
                }
            } catch (Throwable $e) {
                error_log('comms run now: ' . $e->getMessage());
            }
        });
        return true;
    }
}
