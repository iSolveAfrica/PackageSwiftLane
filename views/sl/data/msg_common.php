<?php
/** Shared data for the messaging pages: event catalogue, channel health, template lookups. */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/querys.php';

/** System events that send messages, grouped as the Notification Center shows them. */
function sl_msgEvents(): array
{
    return [
        'shipment.registered'     => ['group' => 'Air shipments', 'name' => 'Shipment registered', 'desc' => 'When a new air shipment is created for a customer.', 'audience' => 'Customer', 'when' => 'on create'],
        'shipment.tracking'       => ['group' => 'Air shipments', 'name' => 'Shipment status updated', 'desc' => 'Every tracking step: in transit, arrived, ready for pickup.', 'audience' => 'Customer', 'when' => 'on status change'],
        'shipment.delivered'      => ['group' => 'Air shipments', 'name' => 'Shipment delivered', 'desc' => 'When the parcel is handed over or delivered.', 'audience' => 'Customer', 'when' => 'on delivery'],
        'pickup.requested'        => ['group' => 'Air shipments', 'name' => 'Pickup requested', 'desc' => 'A customer books a pickup, or staff create one for them.', 'audience' => 'Customer', 'when' => 'on request'],
        'package.registered'      => ['group' => 'Sea packages', 'name' => 'Package registered', 'desc' => 'When a package arrives in the customer locker.', 'audience' => 'Customer', 'when' => 'on create'],
        'package.tracking'        => ['group' => 'Sea packages', 'name' => 'Package status updated', 'desc' => 'Every status step of a sea package.', 'audience' => 'Customer', 'when' => 'on status change'],
        'package.delivered'       => ['group' => 'Sea packages', 'name' => 'Package delivered', 'desc' => 'When the sea package is handed over.', 'audience' => 'Customer', 'when' => 'on delivery'],
        'consolidation.created'   => ['group' => 'Consolidations', 'name' => 'Consolidation created', 'desc' => 'Owners of the packages inside a new consolidation.', 'audience' => 'Package owners', 'when' => 'on create'],
        'consolidation.tracking'  => ['group' => 'Consolidations', 'name' => 'Consolidation status updated', 'desc' => 'Each step of the consolidated load.', 'audience' => 'Package owners', 'when' => 'on status change'],
        'consolidation.delivered' => ['group' => 'Consolidations', 'name' => 'Consolidation delivered', 'desc' => 'When the consolidated load is delivered.', 'audience' => 'Package owners', 'when' => 'on delivery'],
        'prealert.created'        => ['group' => 'Customer account', 'name' => 'Pre-alert received', 'desc' => 'Confirmation that a pre-alert was recorded.', 'audience' => 'Customer', 'when' => 'on create'],
        'payment.pending'         => ['group' => 'Customer account', 'name' => 'Pending payment reminder', 'desc' => 'Reminder sent from Accounts Receivable.', 'audience' => 'Customer', 'when' => 'manual / reminder'],
        'account.welcome'         => ['group' => 'Customer account', 'name' => 'Welcome e-mail', 'desc' => 'After sign-up.', 'audience' => 'Customer', 'when' => 'on sign-up'],
        'account.activated'       => ['group' => 'Customer account', 'name' => 'Account activated', 'desc' => 'When staff approve the account.', 'audience' => 'Customer', 'when' => 'on approval'],
        'account.password_reset'  => ['group' => 'Customer account', 'name' => 'Password reset', 'desc' => 'Reset link or code.', 'audience' => 'Customer', 'when' => 'on request'],
        'admin.new_user'          => ['group' => 'Staff', 'name' => 'New user registered (admin)', 'desc' => 'Tells the admin a customer signed up.', 'audience' => 'Admin', 'when' => 'on sign-up'],
        'push.manual'             => ['group' => 'Staff', 'name' => 'Push notification (manual)', 'desc' => 'The message typed on the Push notifications page.', 'audience' => 'Chosen customers', 'when' => 'manual'],
    ];
}

/** Which log sources belong to which event (for "sent this month"). */
function sl_msgEventSources(string $event): array
{
    $map = [
        'shipment.registered' => ['shipment_registered'], 'shipment.tracking' => ['tracking_update', 'bulk_status_update', 'shipment_updated'],
        'shipment.delivered' => ['delivery', 'pickup_code'], 'pickup.requested' => ['shipment_registered'],
        'package.registered' => ['shipment_registered'], 'package.tracking' => ['tracking_update', 'bulk_status_update'], 'package.delivered' => ['delivery'],
        'consolidation.created' => ['consolidation_update'], 'consolidation.tracking' => ['consolidation_update'], 'consolidation.delivered' => ['consolidation_update'],
        'prealert.created' => ['prealert'], 'payment.pending' => ['pending_payments'], 'account.welcome' => ['signup'], 'account.activated' => ['user_account'],
        'account.password_reset' => ['otp', 'user_account'], 'admin.new_user' => ['signup'], 'push.manual' => ['push_notification', 'push_notification_consolidation', 'push_notification_invoice'],
    ];
    return $map[$event] ?? [];
}

/** rules: event => channel => ['enabled'=>bool,'template_id'=>int|null] */
function sl_msgRules(): array
{
    $out = [];
    if (!sl_tableExists('cdb_notification_rules')) {
        return $out;
    }
    foreach (sl_rows('SELECT event_key, channel, enabled, template_id FROM cdb_notification_rules') as $r) {
        $out[$r->event_key][$r->channel] = ['enabled' => (int) $r->enabled === 1, 'template_id' => $r->template_id !== null ? (int) $r->template_id : null];
    }
    return $out;
}

/** Is an event enabled on a channel? (absent rule = on). For the senders. */
function sl_msgRuleEnabled(string $event, string $channel): bool
{
    static $rules = null;
    if ($rules === null) {
        $rules = sl_msgRules();
    }
    return $rules[$event][$channel]['enabled'] ?? true;
}

/** Template lists per channel: id => [name, subject, body]. */
function sl_msgTemplates(string $channel): array
{
    $out = [];
    if ($channel === 'email') {
        $rows = sl_rows('SELECT id, name, subject, body, help FROM cdb_email_templates ORDER BY id');
    } elseif ($channel === 'sms') {
        $rows = sl_rows('SELECT id, name, subject, body, help FROM cdb_sms_templates ORDER BY id');
    } else {
        $rows = sl_rows("SELECT id, title AS name, description AS subject, body, '' AS help FROM whatsapp_templates ORDER BY id");
    }
    foreach ($rows as $r) {
        $out[(int) $r->id] = ['id' => (int) $r->id, 'name' => (string) $r->name, 'subject' => (string) $r->subject, 'body' => (string) $r->body, 'help' => (string) $r->help];
    }
    return $out;
}

/** Channel health for the top cards. */
function sl_msgChannelHealth(): array
{
    $s = cdp_getSettingsCourier();
    $smtp = ($s->mailer ?? '') === 'PHP' || trim((string) ($s->smtp_host ?? '')) !== '';
    $sms = false;
    $smsLabel = 'SMS';
    if (is_file(CDP_ROOT . '/helpers/hubtel_sms.php')) {
        @include_once CDP_ROOT . '/helpers/hubtel_sms.php';
        if (function_exists('cdp_smsReady')) {
            $sms = (bool) cdp_smsReady() && (int) ($s->active_sms ?? 0) === 1;
            if (function_exists('cdp_smsDefaultProvider')) {
                $p = (string) cdp_smsDefaultProvider();
                $smsLabel = 'SMS · ' . ($p === 'mnotify' ? 'mNotify' : 'Hubtel');
            }
        }
    }
    $wa = (int) ($s->active_whatsapp ?? 0) === 1 && trim((string) ($s->api_ws_url ?? '')) !== '' && trim((string) ($s->api_ws_token ?? '')) !== '';
    $month = [];
    if (sl_tableExists('cdb_message_log')) {
        foreach (sl_rows("SELECT channel, SUM(status='sent') AS sent, COUNT(*) AS n FROM cdb_message_log WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') GROUP BY channel") as $r) {
            $month[$r->channel] = ['sent' => (int) $r->sent, 'n' => (int) $r->n];
        }
    }
    $f = fn($ch) => ['sent' => $month[$ch]['sent'] ?? 0, 'n' => $month[$ch]['n'] ?? 0, 'rate' => ($month[$ch]['n'] ?? 0) ? sl_pct($month[$ch]['sent'], $month[$ch]['n']) : 0];
    return [
        'email' => ['label' => 'E-mail · ' . (($s->mailer ?? '') === 'PHP' ? 'PHP mail' : 'SMTP'), 'ok' => $smtp, 'pill' => $smtp ? 'ok' : 'bad', 'state' => $smtp ? 'Connected' : 'Not set up', 'month' => $f('email')],
        'sms' => ['label' => $smsLabel, 'ok' => $sms, 'pill' => $sms ? 'ok' : 'warn', 'state' => $sms ? 'Connected' : ((int) ($s->active_sms ?? 0) === 1 ? 'No provider' : 'Switched off'), 'month' => $f('sms')],
        'whatsapp' => ['label' => 'WhatsApp API', 'ok' => $wa, 'pill' => $wa ? 'ok' : 'warn', 'state' => $wa ? 'Connected' : 'Not set up', 'month' => $f('whatsapp')],
    ];
}

/** Sample customer used by previews. */
function sl_msgSample(): array
{
    $u = sl_row("SELECT fname, lname, email, phone, locker FROM cdb_users WHERE userlevel = 1 AND active = 1 ORDER BY id DESC");
    return ['name' => $u ? trim($u->fname . ' ' . $u->lname) : 'Efua Owusu', 'first' => $u ? $u->fname : 'Efua', 'email' => $u->email ?? 'efua@example.com', 'phone' => $u->phone ?? '+233 24 000 0000', 'locker' => $u->locker ?? 'SL-100480'];
}

/** Fill the legacy [VARIABLES] of a template with sample values for previews. */
function sl_msgPreviewFill(string $body, array $sample): string
{
    global $core;
    $map = ['[USERNAME]' => $sample['name'], '[NAME]' => $sample['name'], '[FIRST_NAME]' => $sample['first'], '[SITE_NAME]' => $core->site_name ?? 'Swift Lane', '[TRACKING]' => 'SL-100480', '[TRACKING_NUMBER]' => 'SL-100480',
        '[URL]' => 'https://' . ($_SERVER['HTTP_HOST'] ?? 'swiftlane.gh'), '[URL_SHIP]' => 'https://' . ($_SERVER['HTTP_HOST'] ?? 'swiftlane.gh') . '/track.php', '[STATUS]' => 'Ready for pickup', '[MESSAGE]' => 'Your parcel is ready.', '[SUBJECT]' => 'Parcel update',
        '[LOCKER]' => $sample['locker'], '[EMAIL]' => $sample['email'], '[CODE]' => '482193', '[PICKUP_CODE]' => '482193', '[AMOUNT]' => sl_money(420), '[DATE]' => date('M j, Y')];
    return strtr($body, $map);
}

function sl_msgPlain(string $html): string
{
    $t = preg_replace('/<br\s*\/?>/i', "\n", $html);
    $t = preg_replace('/<\/(p|div|tr|h[1-6]|li)>/i', "\n", $t);
    return trim(html_entity_decode(strip_tags($t), ENT_QUOTES, 'UTF-8'));
}
