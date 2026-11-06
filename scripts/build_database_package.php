<?php
/**
 * Builds the operator database package from the shipped baseline dump and the
 * migrations folder:
 *   database/fresh_install_schema.sql      schema + reference seeds + all migrations (empty installs)
 *   database/upgrade_existing_database.sql all migrations in order (existing installs)
 *
 *   php scripts/build_database_package.php
 *
 * The baseline is install.done/swiftlane_database.sql (MySQL 8.0.40 dump of the
 * vendor demo). Business rows (users, orders, packages, payments, news) are NOT
 * copied; secrets in cdb_settings / cdb_met_payment are blanked.
 */
$root = dirname(__DIR__);
$dump = file_get_contents($root . '/install.done/swiftlane_database.sql');
if ($dump === false) {
    fwrite(STDERR, "baseline dump not found\n");
    exit(1);
}
$dump = str_replace("\r\n", "\n", $dump);

// 1. DDL: CREATE TABLE blocks
preg_match_all('/^CREATE TABLE `([a-z_0-9]+)` \((?:.|\n)*?\) ENGINE=[^;]*;/m', $dump, $m, PREG_SET_ORDER);
$creates = [];
foreach ($m as $c) {
    $sql = $c[0];
    // MyISAM/latin1 legacy tables are created as InnoDB utf8mb4 on fresh installs.
    $sql = preg_replace('/ENGINE=MyISAM/', 'ENGINE=InnoDB', $sql);
    $sql = preg_replace('/DEFAULT CHARSET=latin1[^;]*/', 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci', $sql);
    $sql = preg_replace('/DEFAULT CHARSET=utf8mb3( COLLATE=utf8mb3_bin)?/', 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci', $sql);
    $creates[$c[1]] = str_replace('CREATE TABLE `', 'CREATE TABLE IF NOT EXISTS `', $sql);
}
// 2. Keys, auto increment, constraints: ALTER TABLE statements
preg_match_all('/^ALTER TABLE `([a-z_0-9]+)`(?:.|\n)*?;/m', $dump, $a, PREG_SET_ORDER);
$alters = [];
foreach ($a as $s) {
    $alters[] = $s[0];
}
// 3. Reference seeds
$seedTables = ['cdb_user_roles', 'cdb_user_module_permissions', 'cdb_user_module_actions', 'cdb_user_role_permissions',
    'cdb_styles', 'cdb_shipping_mode', 'cdb_delivery_time', 'cdb_packaging', 'cdb_category', 'cdb_courier_com', 'cdb_incoterm',
    'cdb_shipping_line', 'cdb_payment_methods', 'cdb_email_templates', 'cdb_sms_templates', 'whatsapp_templates',
    'default_notification_templates', 'cdb_countries', 'cdb_states', 'cdb_cities', 'cdb_zone', 'cdb_meta_seo', 'cdb_info_ship_default',
    'cdb_offices', 'cdb_branchoffices', 'cdb_shipping_fees'];
$seeds = [];
/** Extract every INSERT statement of a table without regex backtracking (statements can hold thousands of rows). */
function sl_dumpInserts(string $dump, string $table): array
{
    $out = [];
    $needle = "\nINSERT INTO `$table` (";
    $pos = 0;
    while (($i = strpos($dump, $needle, $pos)) !== false) {
        $start = $i + 1;
        $end = strpos($dump, ");\n", $start);
        while ($end !== false) {
            $next = substr($dump, $end + 3, 1);
            if ($next === "\n" || $next === '-' || $next === 'I' || $next === '' ) break;
            $end = strpos($dump, ");\n", $end + 1);
        }
        if ($end === false) break;
        $out[] = substr($dump, $start, $end + 2 - $start);
        $pos = $end + 2;
    }
    return $out;
}
foreach ($seedTables as $t) {
    if ($t === 'cdb_meta_seo') {
        $seeds[] = "INSERT IGNORE INTO `cdb_meta_seo` (`id`, `meta_description`, `meta_keywords`, `og_title`, `og_description`, `og_type`, `og_url`, `og_image`, `updated_at`) VALUES (1, '', '', '', '', 'website', '', '', NOW());";
        continue;
    }
    foreach (sl_dumpInserts($dump, $t) as $stmt) {
        $seeds[] = str_replace('INSERT INTO `', 'INSERT IGNORE INTO `', $stmt);
    }
}
// cdb_settings: keep the row but blank secrets and installation specific values
preg_match('/^INSERT INTO `cdb_settings` \(([^)]*)\) VALUES\n\((?:.|\n)*?\);$/m', $dump, $sm);
$settingsSql = '';
if ($sm) {
    $cols = array_map(fn($c) => trim($c, '` '), explode(',', $sm[1]));
    // parse the VALUES tuple with a small state machine (quoted strings may contain commas)
    $tuple = substr($sm[0], strpos($sm[0], "VALUES\n(") + 8);
    $tuple = rtrim($tuple, ");\n");
    $vals = []; $cur = ''; $inq = false; $i = 0; $len = strlen($tuple);
    while ($i < $len) {
        $ch = $tuple[$i];
        if ($inq) {
            if ($ch === '\\') { $cur .= $ch . $tuple[$i + 1]; $i += 2; continue; }
            if ($ch === "'") { $inq = false; $cur .= $ch; $i++; continue; }
            $cur .= $ch; $i++; continue;
        }
        if ($ch === "'") { $inq = true; $cur .= $ch; $i++; continue; }
        if ($ch === ',') { $vals[] = trim($cur); $cur = ''; $i++; continue; }
        $cur .= $ch; $i++;
    }
    $vals[] = trim($cur);
    $blank = ['smtp_password', 'smtp_user', 'smtp_host', 'email_address', 'api_ws_url', 'api_ws_token', 'twilio_number', 'twilio_sms_sid', 'twilio_sms_token', 'twilio_sms_number', 'site_email', 'c_nit', 'c_phone', 'cell_phone', 'c_address', 'locker_address', 'site_url'];
    foreach ($cols as $k => $c) {
        if (in_array($c, $blank, true)) $vals[$k] = "''";
        if ($c === 'site_name') $vals[$k] = "'Swift Lane'";
        if ($c === 'active_whatsapp' || $c === 'active_sms' || $c === 'backup') $vals[$k] = '0';
        if ($c === 'mailer') $vals[$k] = "'mail'";
    }
    $settingsSql = 'INSERT IGNORE INTO `cdb_settings` (`' . implode('`, `', $cols) . "`) VALUES\n(" . implode(', ', $vals) . ");";
}
// cdb_met_payment: names only, keys blank, inactive
preg_match_all('/^INSERT INTO `cdb_met_payment` \(([^)]*)\) VALUES\n((?:.|\n)*?);$/m', $dump, $pm, PREG_SET_ORDER);
$gatewaySql = '';
foreach ($pm as $g) {
    $cols = array_map(fn($c) => trim($c, '` '), explode(',', $g[1]));
    $rows = preg_split('/\),\n\(/', trim($g[2], "()"));
    $out = [];
    foreach ($rows as $r) {
        // split on top-level commas (values are simple here except detail_pay text)
        $parts = []; $cur = ''; $inq = false;
        for ($i = 0, $n = strlen($r); $i < $n; $i++) {
            $ch = $r[$i];
            if ($inq) { if ($ch === '\\') { $cur .= $ch . ($r[$i + 1] ?? ''); $i++; continue; } if ($ch === "'") $inq = false; $cur .= $ch; continue; }
            if ($ch === "'") { $inq = true; $cur .= $ch; continue; }
            if ($ch === ',') { $parts[] = trim($cur); $cur = ''; continue; }
            $cur .= $ch;
        }
        $parts[] = trim($cur);
        foreach ($cols as $k => $c) {
            if (in_array($c, ['paypal_client_id', 'public_key', 'secret_key'], true)) $parts[$k] = 'NULL';
            if ($c === 'is_active') $parts[$k] = '0';
        }
        $out[] = '(' . implode(', ', $parts) . ')';
    }
    $gatewaySql .= 'INSERT IGNORE INTO `cdb_met_payment` (`' . implode('`, `', $cols) . "`) VALUES\n" . implode(",\n", $out) . ";\n";
}

// 4. Migrations in order
$migrations = glob($root . '/migrations/*.sql');
sort($migrations);
// Foundation first: the UI core and the legacy drift fill in what every later file relies on.
$first = ['2026_11_06_swiftlane_ui_core.sql', '2026_11_06_legacy_drift.sql'];
usort($migrations, function ($a, $b) use ($first) {
    $ia = array_search(basename($a), $first, true); $ib = array_search(basename($b), $first, true);
    $ia = $ia === false ? 99 : $ia; $ib = $ib === false ? 99 : $ib;
    return $ia === $ib ? strcmp($a, $b) : $ia <=> $ib;
});
$migSql = '';
foreach ($migrations as $f) {
    $migSql .= "\n-- >>> migrations/" . basename($f) . "\n" . file_get_contents($f) . "\n";
}

$header = "-- Swift Lane database package, generated " . date('Y-m-d') . " by scripts/build_database_package.php\n-- Engine: MySQL 8.0+ (tested on 9.7) or MariaDB 10.6+. Character set utf8mb4.\n";
$fresh = $header . "-- FRESH INSTALL: schema + reference data + every migration. Run on an EMPTY database only.\n"
    . "-- Creates no user accounts: run `php scripts/create_admin.php` afterwards.\n\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n"
    . "-- ---------------------------------------------------------------- tables\n" . implode("\n\n", $creates)
    . "\n\n-- ---------------------------------------------------------------- keys, auto-increment, constraints\n" . implode("\n", $alters)
    . "\n\n-- ---------------------------------------------------------------- reference data\n" . $settingsSql . "\n" . $gatewaySql . implode("\n", $seeds)
    . "\n\nSET FOREIGN_KEY_CHECKS = 1;\n\n-- ---------------------------------------------------------------- migrations\n" . $migSql;
file_put_contents($root . '/database/fresh_install_schema.sql', $fresh);
$upgrade = $header . "-- UPGRADE of an existing Swift Lane database (baseline: the vendor dump of 2026-02-28 or any later production schema).\n"
    . "-- Every statement is idempotent; the file can be run again after a failure.\n-- Take a backup first (see database/README.md).\n" . $migSql;
file_put_contents($root . '/database/upgrade_existing_database.sql', $upgrade);
printf("tables: %d, alters: %d, seed statements: %d, migrations: %d\nfresh: %d bytes, upgrade: %d bytes\n", count($creates), count($alters), count($seeds), count($migrations), strlen($fresh), strlen($upgrade));
