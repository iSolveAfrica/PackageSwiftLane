<?php
/**
 * Shared presentation and data helpers that used to ship as an ionCube-encoded
 * file. Rewritten as readable PHP from the observable behaviour of every call
 * site (see docs/handover/ENCODED_MODULES.md for the inventory).
 */

if (!function_exists('cdp_cleanOut')) {
    /** Normalise a stored template body for output (removes literal line-break escapes). */
    function cdp_cleanOut($text)
    {
        $text = strtr((string) $text, ['\r\n' => '', '\r' => '', '\n' => '']);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = str_replace('<br>', '<br />', $text);
        return stripslashes($text);
    }
}

if (!function_exists('cdp_email_users_notifications')) {
    /** Join a list of e-mail addresses into the comma separated "to" header. */
    function cdp_email_users_notifications($addresses)
    {
        $clean = [];
        foreach ((array) $addresses as $address) {
            $address = trim((string) $address);
            if ($address !== '' && filter_var($address, FILTER_VALIDATE_EMAIL)) {
                $clean[] = $address;
            }
        }
        return implode(',', array_unique($clean));
    }
}

if (!function_exists('_getSizePrefix')) {
    function _getSizePrefix($pos)
    {
        $prefixes = ['', 'kilo', 'mega', 'giga', 'tera', 'peta'];
        return $prefixes[$pos] ?? '?-';
    }
}

if (!function_exists('getSize')) {
    /** Human readable file size, e.g. 1.5 MB. Zero bytes prints "-/-". */
    function getSize($size, $precision = 2, $long_name = false, $real_size = true)
    {
        $size = (float) $size;
        if ($size <= 0) {
            return '-/-';
        }
        $base = $real_size ? 1024 : 1000;
        $pos = 0;
        while ($size >= $base && $pos < 5) {
            $size /= $base;
            $pos++;
        }
        $unit = $long_name ? _getSizePrefix($pos) . 'byte' . ($size == 1 ? '' : 's')
                           : ['B', 'KB', 'MB', 'GB', 'TB', 'PB'][$pos];
        return round($size, $precision) . ' ' . $unit;
    }
}

if (!function_exists('cdp_round_out')) {
    /** Round a weight or amount to two decimals. */
    function cdp_round_out($value)
    {
        return round((float) $value * 100) / 100;
    }
}

if (!function_exists('cdp_sumardias')) {
    /** Add N days to a date. Returns Y-m-d H:i:s (the due_date columns are DATE, MySQL truncates). */
    function cdp_sumardias($date, $days)
    {
        $ts = strtotime((string) $date);
        if ($ts === false) {
            $ts = time();
        }
        return date('Y-m-d H:i:s', strtotime('+' . (int) $days . ' days', $ts));
    }
}

if (!function_exists('cdp_generarCodigo')) {
    /** Random numeric code of N digits (tracking numbers, locker numbers). */
    function cdp_generarCodigo($digits)
    {
        $digits = max(1, min(18, (int) $digits));
        $min = (int) str_pad('1', $digits, '0');
        $max = (int) str_repeat('9', $digits);
        if ($digits === 1) {
            $min = 0;
        }
        return (string) random_int($min, $max);
    }
}

if (!function_exists('cdp_getChecked')) {
    /** Echo checked="checked" when the two values match (radio buttons in settings forms). */
    function cdp_getChecked($value, $expected)
    {
        if ((string) $value === (string) $expected) {
            echo ' checked="checked"';
        }
    }
}

if (!function_exists('cdp_getCheckedLocker')) {
    function cdp_getCheckedLocker($value, $expected)
    {
        cdp_getChecked($value, $expected);
    }
}

if (!function_exists('cdp_statusLabelHtml')) {
    function cdp_statusLabelHtml($active, $labelOn, $labelOff)
    {
        $active = (int) $active === 1;
        $text = htmlspecialchars($active ? $labelOn : $labelOff, ENT_QUOTES, 'UTF-8');
        return '<span class="label ' . ($active ? 'label-success' : 'label-danger') . '">' . $text . '</span>';
    }
}

if (!function_exists('cdp_userStatus')) {
    function cdp_userStatus($active, $id, $lang)
    {
        return cdp_statusLabelHtml($active, $lang['user_manage16'] ?? 'Active', $lang['user_manage17'] ?? 'Inactive');
    }
}

if (!function_exists('cdp_locationStatus')) {
    function cdp_locationStatus($active, $id, $lang)
    {
        return cdp_statusLabelHtml($active, $lang['rolesp11'] ?? 'Active', $lang['rolesp12'] ?? 'Inactive');
    }
}

if (!function_exists('cdp_paymentStatus')) {
    function cdp_paymentStatus($active, $id, $lang)
    {
        return cdp_statusLabelHtml($active, $lang['rolesp11'] ?? 'Active', $lang['rolesp12'] ?? 'Inactive');
    }
}

if (!function_exists('cdp_isAdmin')) {
    /** Role label for a userlevel (role_id) value. */
    function cdp_isAdmin($userlevel, $lang)
    {
        static $roles = null;
        if ($roles === null) {
            $roles = [];
            try {
                $db = new Conexion();
                $db->cdp_query('SELECT role_id, role_name FROM cdb_user_roles');
                $db->cdp_execute();
                foreach ((array) $db->cdp_registros() as $r) {
                    $roles[(int) $r->role_id] = $r->role_name;
                }
            } catch (Throwable $e) {
                $roles = [];
            }
        }
        $name = $roles[(int) $userlevel] ?? ('#' . (int) $userlevel);
        return '<span class="label label-info">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}

/* ── Data helpers used by the legacy settings screens ─────────────────────── */

if (!function_exists('cdp_getPaymentMethodEdit')) {
    function cdp_getPaymentMethodEdit($id)
    {
        $db = new Conexion();
        $db->cdp_query('SELECT * FROM cdb_met_payment WHERE id = :id');
        $db->bind(':id', (int) $id);
        $db->cdp_execute();
        $row = $db->cdp_registro();
        return ['rowCount' => $row ? 1 : 0, 'data' => $row];
    }
}

if (!function_exists('cdp_insertPaymentMethod')) {
    function cdp_insertPaymentMethod($data)
    {
        $db = new Conexion();
        $db->cdp_query('INSERT INTO cdb_met_payment (name_pay, detail_pay, is_active) VALUES (:name_pay, :detail_pay, 1)');
        $db->bind(':name_pay', $data['met_payment'] ?? '');
        $db->bind(':detail_pay', $data['detail'] ?? '');
        return $db->cdp_execute();
    }
}

if (!function_exists('cdp_updatePaymentMethod')) {
    function cdp_updatePaymentMethod($data)
    {
        $db = new Conexion();
        $db->cdp_query('UPDATE cdb_met_payment SET name_pay = :name_pay, detail_pay = :detail_pay WHERE id = :id');
        $db->bind(':name_pay', $data['met_payment'] ?? '');
        $db->bind(':detail_pay', $data['detail'] ?? '');
        $db->bind(':id', (int) ($data['id'] ?? 0));
        return $db->cdp_execute();
    }
}

if (!function_exists('cdp_settingsHasColumn')) {
    function cdp_settingsHasColumn($column)
    {
        static $cache = [];
        if (!array_key_exists($column, $cache)) {
            $db = new Conexion();
            $db->cdp_query('SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c');
            $db->bind(':t', 'cdb_settings');
            $db->bind(':c', $column);
            $db->cdp_execute();
            $r = $db->cdp_registro();
            $cache[$column] = $r && (int) $r->c > 0;
        }
        return $cache[$column];
    }
}

if (!function_exists('cdp_updateConfigSmsNexmo')) {
    /** Legacy Nexmo SMS settings. Columns are optional: only the ones present are written. */
    function cdp_updateConfigSmsNexmo($data)
    {
        $map = ['namesms' => 'namesms', 'api_key' => 'nexmo_api_key', 'api_secret' => 'nexmo_api_secret', 'nexmo_number' => 'nexmo_number', 'active_nex' => 'active_nex'];
        $sets = [];
        foreach ($map as $key => $col) {
            if (cdp_settingsHasColumn($col)) {
                $sets[] = "`$col` = :$key";
            }
        }
        if (!$sets) {
            return false;
        }
        $db = new Conexion();
        $db->cdp_query('UPDATE cdb_settings SET ' . implode(', ', $sets) . ' WHERE id = :id');
        foreach ($map as $key => $col) {
            if (cdp_settingsHasColumn($col)) {
                $db->bind(':' . $key, $data[$key] ?? '');
            }
        }
        $db->bind(':id', (int) ($data['id'] ?? 1));
        return $db->cdp_execute();
    }
}

if (!function_exists('cdp_updateStatusNexmo')) {
    function cdp_updateStatusNexmo($data)
    {
        if (!cdp_settingsHasColumn('active_nex')) {
            return false;
        }
        $db = new Conexion();
        $db->cdp_query('UPDATE cdb_settings SET active_nex = :a WHERE id = :id');
        $db->bind(':a', (int) ($data['active_nex'] ?? 0));
        $db->bind(':id', (int) ($data['id'] ?? 1));
        return $db->cdp_execute();
    }
}

if (!function_exists('cdp_userwebEmailExiste')) {
    /** True when the e-mail is already registered. */
    function cdp_userwebEmailExiste($email)
    {
        $db = new Conexion();
        $db->cdp_query('SELECT id FROM cdb_users WHERE email = :email LIMIT 1');
        $db->bind(':email', $email);
        $db->cdp_execute();
        return $db->cdp_registro() ? true : false;
    }
}
if (!function_exists('cdp_userWebEmailExiste')) {
    function cdp_userWebEmailExiste($email)
    {
        return cdp_userwebEmailExiste($email);
    }
}

if (!function_exists('getCustomerPackageForOrderNo')) {
    /** Customer package (sea) by order id or by prefix+number. */
    function getCustomerPackageForOrderNo($key)
    {
        $db = new Conexion();
        if (is_numeric($key)) {
            $db->cdp_query('SELECT * FROM cdb_customers_packages WHERE order_id = :k LIMIT 1');
            $db->bind(':k', (int) $key);
        } else {
            $db->cdp_query('SELECT * FROM cdb_customers_packages WHERE CONCAT(order_prefix, order_no) = :k LIMIT 1');
            $db->bind(':k', (string) $key);
        }
        $db->cdp_execute();
        return $db->cdp_registro();
    }
}

if (!function_exists('getSenderCourier')) {
    function getSenderCourier($id)
    {
        $db = new Conexion();
        $db->cdp_query('SELECT * FROM cdb_users WHERE id = :id LIMIT 1');
        $db->bind(':id', (int) $id);
        $db->cdp_execute();
        return $db->cdp_registro();
    }
}
