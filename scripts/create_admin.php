<?php
/**
 * Creates (or resets) the first super-admin account on a fresh installation.
 *
 *   php scripts/create_admin.php --username=admin --email=you@example.com --name="Jane Doe"
 *
 * The password is read from the terminal (never from the command line or a file).
 */
if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}
require_once dirname(__DIR__) . '/loader.php';
$opts = getopt('', ['username:', 'email:', 'name::']);
$username = trim((string) ($opts['username'] ?? ''));
$email = trim((string) ($opts['email'] ?? ''));
$name = trim((string) ($opts['name'] ?? 'Administrator'));
if ($username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("Usage: php scripts/create_admin.php --username=admin --email=you@example.com [--name=\"Jane Doe\"]\n");
}
echo "Password (min 10 characters): ";
if (function_exists('shell_exec')) { @shell_exec('stty -echo'); }
$password = rtrim((string) fgets(STDIN), "\r\n");
if (function_exists('shell_exec')) { @shell_exec('stty echo'); }
echo "\n";
if (strlen($password) < 10) {
    exit("Password too short.\n");
}
[$fname, $lname] = array_pad(explode(' ', $name, 2), 2, '');
$hash = password_hash($password, PASSWORD_BCRYPT);
$db = new Conexion();
$db->cdp_query('SELECT id FROM cdb_users WHERE username = :u OR email = :e LIMIT 1');
$db->bind(':u', $username); $db->bind(':e', $email);
$db->cdp_execute();
$existing = $db->cdp_registro();
if ($existing) {
    $db->cdp_query('UPDATE cdb_users SET password = :p, userlevel = 9, active = 1 WHERE id = :id');
    $db->bind(':p', $hash); $db->bind(':id', (int) $existing->id);
    $db->cdp_execute();
    echo "Updated existing account #{$existing->id} as super admin.\n";
} else {
    $db->cdp_query("INSERT INTO cdb_users (username, password, userlevel, email, fname, lname, name_off, locker, created, active, agency_id, terms) VALUES (:u, :p, 9, :e, :f, :l, '', '', NOW(), 1, 0, '1')");
    $db->bind(':u', $username); $db->bind(':p', $hash); $db->bind(':e', $email); $db->bind(':f', $fname); $db->bind(':l', $lname);
    if (!$db->cdp_execute()) {
        exit("Could not create the account: " . $db->cdp_error() . "\n");
    }
    echo "Super admin '$username' created.\n";
}
