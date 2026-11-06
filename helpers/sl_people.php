<?php
/**
 * Helpers shared by the people / staff / rider pages of the Swift Lane UI
 * (views/sl/data/staff*.php, riders.php, ajax/sl/staff_*_ajax.php).
 */
require_once __DIR__ . '/sl_view.php';
require_once __DIR__ . '/rbac.php';

/** Active roles that are not customer roles, keyed by role_id. */
function sl_staffRoles(): array
{
    $out = [];
    foreach (cdp_roleFlagsMap() as $rid => $r) {
        if (cdp_roleIsClient($rid) || (int) $rid === 1) continue;
        $out[(int) $rid] = $r;
    }
    return $out;
}

/** Role ids the viewer may assign (rank rules of helpers/rbac.php). */
function sl_assignableRoles($viewer): array
{
    return array_filter(sl_staffRoles(), fn($r, $rid) => cdp_canAssignRole($viewer, $rid), ARRAY_FILTER_USE_BOTH);
}

/** Plain-English description of a role for the invite screen. */
function sl_roleBlurb($role): string
{
    $rid = (int) $role->role_id;
    if (cdp_roleHasFlag($rid, 'is_superadmin')) return 'Everything, including settings and developer pages';
    if (cdp_roleHasFlag($rid, 'is_admin')) return 'Everything except sensitive configuration';
    if (cdp_roleHasFlag($rid, 'is_driver')) return 'Pickups and deliveries on the rider app';
    if (cdp_roleHasFlag($rid, 'is_agency')) return 'Their own agency data only';
    return $role->description ?: 'Limited access, no sensitive configuration';
}

/** SQL fragment matching staff accounts (every active non-customer role). */
function sl_staffWhere(string $alias = 'u'): string
{
    $ids = array_keys(sl_staffRoles());
    return $ids ? "$alias.userlevel IN (" . implode(',', array_map('intval', $ids)) . ")" : "$alias.userlevel <> 1";
}

/**
 * Send one HTML e-mail with the installation's mailer (cdb_settings: PHP mail
 * or SMTP via PHPMailer), mirroring ajax/users/users_add_ajax.php. Returns
 * true when the message was handed to the mailer.
 */
function sl_sendMail(string $to, string $subject, string $html): bool
{
    global $core;
    if (!$core) $core = new Core();
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $from = $core->email_address ?: $core->site_email;
    if (($core->mailer ?? 'PHP') === 'SMTP' && !empty($core->smtp_host)) {
        foreach (['Exception', 'PHPMailer', 'SMTP'] as $c) require_once CDP_ROOT . '/helpers/phpmailer/PHPMailer/src/' . $c . '.php';
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $core->smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $core->smtp_user;
            $mail->Password = $core->smtp_password;
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;
            $mail->setFrom($from, $core->site_name);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body = $html;
            return $mail->send();
        } catch (Throwable $e) {
            error_log('sl_sendMail: ' . $e->getMessage());
            return false;
        }
    }
    $header = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: " . $core->site_name . " <" . $from . ">\r\n";
    return @mail($to, $subject, $html, $header);
}

/** Load one user row the viewer may manage, or null. */
function sl_managedUser($viewer, int $id)
{
    $row = sl_row('SELECT * FROM cdb_users WHERE id = :id', [':id' => $id]);
    if (!$row || !cdp_canManageUser($viewer, (int) $row->userlevel)) return null;
    return $row;
}

/** Riders = users with a driver-type role. */
function sl_riderRoleIds(): array
{
    $ids = [];
    foreach (cdp_roleFlagsMap() as $rid => $r) if (cdp_roleHasFlag($rid, 'is_driver')) $ids[] = (int) $rid;
    return $ids ?: [3];
}

/**
 * Invitation e-mail for a new or re-invited staff account, using the
 * "You have been registered" template (cdb_email_templates #3) like the legacy
 * users_add endpoint. $password is the one-time password set on the account.
 */
function sl_staffInviteMail($target, string $password): bool
{
    global $core;
    if (!$core) $core = new Core();
    require_once CDP_ROOT . '/helpers/querys.php';
    $tpl = function_exists('cdp_getEmailTemplatesdg1i4') ? cdp_getEmailTemplatesdg1i4(3) : null;
    $name = trim(($target->fname ?? '') . ' ' . ($target->lname ?? ''));
    $url = rtrim((string) ($core->site_url ?? ''), '/') . '/login.php';
    if ($tpl && !empty($tpl->body)) {
        $body = str_replace(['[USERNAME]', '[PASSWORD]', '[BRANCHOFFICES]', '[NAME]', '[SITE_NAME]', '[URL]'], [$target->username, $password, $target->name_off ?? '', $name, $core->site_name, $url], $tpl->body);
        $subject = $tpl->subject ?: 'You have been registered';
        $body = function_exists('cdp_cleanOut') ? cdp_cleanOut($body) : $body;
    } else {
        $subject = 'Your ' . $core->site_name . ' account';
        $body = '<p>Hello ' . h($name) . ',</p><p>An account was created for you. Username: <b>' . h($target->username) . '</b>, temporary password: <b>' . h($password) . '</b>.</p><p>Sign in at <a href="' . h($url) . '">' . h($url) . '</a> and change your password.</p>';
    }
    return sl_sendMail((string) $target->email, $subject, $body);
}
