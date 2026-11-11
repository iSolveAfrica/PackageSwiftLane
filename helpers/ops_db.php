<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************

/**
 * Shared plumbing for three modules:
 *
 *   helpers/comms.php     WhatsApp, SMS, e-mail and communication automation
 *   helpers/mgmt.php      "Gerald Master View" management dashboard
 *   helpers/staffops.php  staff, agents and multi-branch management
 *
 * Small query wrappers (the same shape as helpers/support.php), one key/value
 * settings table shared by the three (namespaced), signed public tokens for
 * the links customers open without logging in, and file exports (CSV, Excel
 * .xlsx, PDF) so every report in the three modules downloads the same way.
 *
 * Nothing here needs the database to be loaded until a function is called,
 * and the pure helpers (tokens, CSV cells, xlsx writer) are unit tested in
 * tests/ops/ops_unit_test.php without a database.
 */

if (!function_exists('cdp_opsNow')) {

    function cdp_opsNow($offsetSeconds = 0)
    {
        return date('Y-m-d H:i:s', time() + (int) $offsetSeconds);
    }

    /** SELECT -> rows as objects ([] on failure). */
    function cdp_opsQ($sql, array $bind = [])
    {
        try {
            $db = new Conexion;
            $db->cdp_query($sql);
            foreach ($bind as $k => $v) {
                $db->bind($k, $v);
            }
            $rows = $db->cdp_registros();
            return is_array($rows) ? $rows : [];
        } catch (Throwable $e) {
            error_log('OPS_Q ' . $e->getMessage());
            return [];
        }
    }

    function cdp_opsOne($sql, array $bind = [])
    {
        $r = cdp_opsQ($sql, $bind);
        return $r ? $r[0] : null;
    }

    /** Scalar of the first column of the first row. */
    function cdp_opsVal($sql, array $bind = [], $default = 0)
    {
        $r = cdp_opsOne($sql, $bind);
        if (!$r) {
            return $default;
        }
        $a = get_object_vars($r);
        $v = reset($a);
        return $v === null ? $default : $v;
    }

    /** Write -> ['ok', 'rows', 'id', 'error']. */
    function cdp_opsX($sql, array $bind = [])
    {
        try {
            $db = new Conexion;
            $db->cdp_query($sql);
            foreach ($bind as $k => $v) {
                $db->bind($k, $v);
            }
            $ok = $db->cdp_execute() !== false;
            return ['ok' => $ok, 'rows' => $ok ? (int) $db->cdp_rowCount() : 0,
                'id' => $ok ? (int) $db->dbh->lastInsertId() : 0, 'error' => $ok ? '' : $db->cdp_error()];
        } catch (Throwable $e) {
            error_log('OPS_X ' . $e->getMessage());
            return ['ok' => false, 'rows' => 0, 'id' => 0, 'error' => $e->getMessage()];
        }
    }

    /** Runs a list of DDL statements; true when all succeeded. */
    function cdp_opsDdl(array $statements)
    {
        try {
            $db = new Conexion;
            foreach ($statements as $sql) {
                $db->cdp_query($sql);
                if ($db->cdp_execute() === false) {
                    error_log('OPS_DDL ' . $db->cdp_error() . ' :: ' . substr(preg_replace('/\s+/', ' ', $sql), 0, 120));
                    return false;
                }
            }
            return true;
        } catch (Throwable $e) {
            error_log('OPS_DDL ' . $e->getMessage());
            return false;
        }
    }

    function cdp_opsTableExists($table)
    {
        static $cache = [];
        if (!array_key_exists($table, $cache)) {
            $cache[$table] = (int) cdp_opsVal("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t", [':t' => $table]) > 0;
        }
        return $cache[$table];
    }

    function cdp_opsColumnExists($table, $column)
    {
        static $cache = [];
        $k = $table . '.' . $column;
        if (!array_key_exists($k, $cache)) {
            $cache[$k] = (int) cdp_opsVal("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c",
                [':t' => $table, ':c' => $column]) > 0;
        }
        return $cache[$k];
    }

    function cdp_opsJson($v)
    {
        return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    function cdp_opsDecode($s, $default = [])
    {
        if ($s === null || $s === '') {
            return $default;
        }
        $v = json_decode((string) $s, true);
        return is_array($v) ? $v : $default;
    }

    function cdp_opsEsc($v)
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }

    function cdp_opsClip($v, $n = 160)
    {
        $v = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $v)));
        return mb_strlen($v) > $n ? mb_substr($v, 0, $n - 1) . '…' : $v;
    }

    /** Y-m-d or the default. */
    function cdp_opsDate($v, $default)
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? (string) $v : $default;
    }

    /** A user's display name (company, else first + last, else username). */
    function cdp_opsUserName($id)
    {
        static $cache = [];
        $id = (int) $id;
        if ($id <= 0) {
            return '';
        }
        if (!isset($cache[$id])) {
            $u = cdp_opsOne("SELECT fname, lname, username FROM cdb_users WHERE id = :id", [':id' => $id]);
            $n = $u ? trim($u->fname . ' ' . $u->lname) : '';
            $cache[$id] = $n !== '' ? $n : ($u ? (string) $u->username : '#' . $id);
        }
        return $cache[$id];
    }

    function cdp_opsUid()
    {
        global $user;
        return ($user instanceof User && !empty($user->uid)) ? (int) $user->uid : 0;
    }

    /** Rank: superadmin 4 > admin 3 > staff 2 > driver/agency 1 > client 0. */
    function cdp_opsRank($u = null)
    {
        global $user;
        $u = $u ?: $user;
        if (!($u instanceof User) || empty($u->logged_in)) {
            return -1;
        }
        require_once __DIR__ . '/rbac.php';
        return cdp_roleRankById((int) $u->userlevel);
    }

    /** JSON response and exit. */
    function cdp_opsOut(array $body, $code = 200)
    {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }
        echo cdp_opsJson($body);
        exit;
    }
}

// ---------------------------------------------------------------------------
// Settings (one table, namespaced: comms.*, mgmt.*, staff.*)
// ---------------------------------------------------------------------------

if (!function_exists('cdp_opsSettingsReady')) {

    function cdp_opsSettingsReady()
    {
        static $ready = null;
        if ($ready === null) {
            $ready = cdp_opsDdl(["CREATE TABLE IF NOT EXISTS cdb_ops_settings (
                ns VARCHAR(20) NOT NULL, setting_key VARCHAR(64) NOT NULL, setting_value MEDIUMTEXT NULL,
                updated_by INT NULL, updated_at DATETIME NULL, PRIMARY KEY (ns, setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"]);
        }
        return $ready;
    }

    function cdp_opsSettings($ns, $fresh = false)
    {
        static $cache = [];
        if ($fresh || !isset($cache[$ns])) {
            $cache[$ns] = [];
            if (cdp_opsSettingsReady()) {
                foreach (cdp_opsQ("SELECT setting_key k, setting_value v FROM cdb_ops_settings WHERE ns = :ns", [':ns' => $ns]) as $r) {
                    $cache[$ns][$r->k] = (string) $r->v;
                }
            }
        }
        return $cache[$ns];
    }

    function cdp_opsSetting($ns, $key, $default = '')
    {
        $all = cdp_opsSettings($ns);
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    function cdp_opsSettingSet($ns, $key, $value, $userId = null)
    {
        if (!cdp_opsSettingsReady()) {
            return false;
        }
        $r = cdp_opsX("INSERT INTO cdb_ops_settings (ns, setting_key, setting_value, updated_by, updated_at)
                       VALUES (:ns, :k, :v, :u, :n)
                       ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)",
            [':ns' => $ns, ':k' => $key, ':v' => (string) $value, ':u' => $userId === null ? cdp_opsUid() : (int) $userId, ':n' => cdp_opsNow()]);
        cdp_opsSettings($ns, true);
        return $r['ok'];
    }

    /** Per-install secret for signed links, created on first use. */
    function cdp_opsSecret()
    {
        static $secret = null;
        if ($secret !== null) {
            return $secret;
        }
        $s = cdp_opsSetting('core', 'link_secret', '');
        if (strlen($s) < 32) {
            cdp_opsX("INSERT IGNORE INTO cdb_ops_settings (ns, setting_key, setting_value, updated_at) VALUES ('core', 'link_secret', :v, :n)",
                [':v' => bin2hex(random_bytes(32)), ':n' => cdp_opsNow()]);
            $s = cdp_opsSetting('core', 'link_secret', '');
            if (strlen($s) < 32) {
                $s = (string) cdp_opsVal("SELECT setting_value FROM cdb_ops_settings WHERE ns='core' AND setting_key='link_secret'", [], '');
            }
        }
        return $secret = $s;
    }
}

// ---------------------------------------------------------------------------
// Signed tokens for public links (survey, delivery slot, preferences)
// ---------------------------------------------------------------------------

if (!function_exists('cdp_opsSign')) {

    /**
     * "purpose.id.expiry.mac" — the id and expiry are readable, the MAC binds
     * them to the purpose so a survey token never opens the preferences page.
     */
    function cdp_opsSign($purpose, $id, $ttlDays = 30, $secret = null)
    {
        $secret = $secret ?? cdp_opsSecret();
        $exp = time() + (int) $ttlDays * 86400;
        $payload = $purpose . '.' . (int) $id . '.' . $exp;
        return (int) $id . '.' . $exp . '.' . substr(hash_hmac('sha256', $payload, $secret), 0, 32);
    }

    /** The id the token carries, or 0 when it is forged, for another purpose or expired. */
    function cdp_opsVerify($purpose, $token, $secret = null, $now = null)
    {
        $secret = $secret ?? cdp_opsSecret();
        if (!is_string($token) || !preg_match('/^(\d+)\.(\d+)\.([a-f0-9]{32})$/', $token, $m) || $secret === '') {
            return 0;
        }
        $expected = substr(hash_hmac('sha256', $purpose . '.' . $m[1] . '.' . $m[2], $secret), 0, 32);
        if (!hash_equals($expected, $m[3])) {
            return 0;
        }
        if ((int) $m[2] < ($now ?? time())) {
            return 0;
        }
        return (int) $m[1];
    }

    function cdp_opsSiteUrl()
    {
        static $u = null;
        if ($u === null) {
            $u = '';
            if (function_exists('cdp_getSettingsCourier')) {
                $s = cdp_getSettingsCourier();
                $u = rtrim((string) ($s->site_url ?? ''), '/');
            }
        }
        return $u;
    }
}

// ---------------------------------------------------------------------------
// Exports: CSV, Excel (.xlsx), PDF
// ---------------------------------------------------------------------------

if (!function_exists('cdp_opsCsvCell')) {

    /** A CSV cell that a spreadsheet will not run as a formula. */
    function cdp_opsCsvCell($v)
    {
        $v = (string) $v;
        if ($v !== '' && strpos('=+-@' . "\t\r", $v[0]) !== false && !is_numeric($v)) {
            $v = "'" . $v;
        }
        return '"' . str_replace('"', '""', $v) . '"';
    }

    function cdp_opsCsv(array $head, array $rows)
    {
        $out = "\xEF\xBB\xBF" . implode(',', array_map('cdp_opsCsvCell', $head)) . "\r\n";
        foreach ($rows as $r) {
            $out .= implode(',', array_map('cdp_opsCsvCell', array_values((array) $r))) . "\r\n";
        }
        return $out;
    }

    function cdp_opsXlsxCol($i)
    {
        $s = '';
        $i++;
        while ($i > 0) {
            $m = ($i - 1) % 26;
            $s = chr(65 + $m) . $s;
            $i = intdiv($i - 1, 26);
        }
        return $s;
    }

    /**
     * A real .xlsx (Office Open XML) built with ZipArchive. Several sheets:
     * $sheets = ['Name' => ['head' => [...], 'rows' => [[...], ...]], ...].
     * Numbers stay numbers; text is an inline string (formulas are never written).
     * Returns the file contents, or '' when ZipArchive is missing.
     */
    function cdp_opsXlsx(array $sheets)
    {
        if (!class_exists('ZipArchive')) {
            return '';
        }
        $x = function ($v) { return htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8'); };
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            return '';
        }
        $names = [];
        $i = 0;
        foreach ($sheets as $name => $sheet) {
            $i++;
            $clean = mb_substr(preg_replace('/[\[\]\*\?\/\\\\:]/', ' ', (string) $name), 0, 31);
            $names[$i] = $clean !== '' ? $clean : 'Sheet' . $i;
            $rows = array_merge([array_values((array) ($sheet['head'] ?? []))], array_map(function ($r) { return array_values((array) $r); }, (array) ($sheet['rows'] ?? [])));
            $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
            foreach ($rows as $ri => $row) {
                $xml .= '<row r="' . ($ri + 1) . '">';
                foreach ($row as $ci => $v) {
                    $ref = cdp_opsXlsxCol($ci) . ($ri + 1);
                    $style = $ri === 0 ? ' s="1"' : '';
                    if ($ri > 0 && (is_int($v) || is_float($v) || (is_string($v) && preg_match('/^-?\d{1,15}(\.\d+)?$/', $v)))) {
                        $xml .= '<c r="' . $ref . '"' . $style . '><v>' . $v . '</v></c>';
                    } else {
                        $xml .= '<c r="' . $ref . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' . $x($v) . '</t></is></c>';
                    }
                }
                $xml .= '</row>';
            }
            $xml .= '</sheetData></worksheet>';
            $zip->addFromString('xl/worksheets/sheet' . $i . '.xml', $xml);
        }
        $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $wbSheets = '';
        foreach ($names as $n => $name) {
            $ct .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $wbRels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
            $wbSheets .= '<sheet name="' . $x($name) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
        }
        $styleId = count($names) + 1;
        $wbRels .= '<Relationship Id="rId' . $styleId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
        $ct .= '</Types>';
        $zip->addFromString('[Content_Types].xml', $ct);
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $wbSheets . '</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills><borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="2"><xf fontId="0"/><xf fontId="1" applyFont="1"/></cellXfs></styleSheet>');
        $zip->close();
        $data = (string) file_get_contents($tmp);
        @unlink($tmp);
        return $data;
    }

    /** Simple printable HTML for a report (also the body of the PDF). */
    function cdp_opsReportHtml($title, array $sections, $subtitle = '')
    {
        $e = 'cdp_opsEsc';
        $h = '<page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm"><h2 style="margin:0">' . $e($title) . '</h2>'
            . ($subtitle !== '' ? '<p style="color:#555;margin:2px 0 10px 0">' . $e($subtitle) . '</p>' : '');
        foreach ($sections as $name => $s) {
            $h .= '<h4 style="margin:12px 0 4px 0">' . $e($name) . '</h4><table cellspacing="0" cellpadding="3" style="width:100%;border-collapse:collapse;font-size:9pt">';
            $head = (array) ($s['head'] ?? []);
            if ($head) {
                $w = (int) floor(100 / max(1, count($head)));
                $h .= '<tr>';
                foreach ($head as $c) {
                    $h .= '<th style="width:' . $w . '%;border-bottom:1px solid #333;text-align:left">' . $e($c) . '</th>';
                }
                $h .= '</tr>';
            }
            foreach ((array) ($s['rows'] ?? []) as $r) {
                $h .= '<tr>';
                foreach (array_values((array) $r) as $c) {
                    $h .= '<td style="border-bottom:1px solid #ddd">' . $e($c) . '</td>';
                }
                $h .= '</tr>';
            }
            if (empty($s['rows'])) {
                $h .= '<tr><td colspan="' . max(1, count($head)) . '">No data.</td></tr>';
            }
            $h .= '</table>';
        }
        return $h . '<p style="color:#777;font-size:8pt">Generated ' . $e(date('Y-m-d H:i')) . '</p></page>';
    }

    /**
     * PDF bytes for a report, through helpers/pdf.php (html2pdf). Falls back to
     * '' when the library is missing; callers then offer the HTML instead.
     */
    function cdp_opsPdf($title, array $sections, $subtitle = '')
    {
        require_once __DIR__ . '/pdf.php';
        if (!class_exists('Spipu\Html2Pdf\Html2Pdf')) {
            return '';
        }
        try {
            return swiftlane_render_html_to_pdf(cdp_opsReportHtml($title, $sections, $subtitle), $title . '.pdf', ['orientation' => 'L', 'format' => 'A4', 'lang' => 'en']);
        } catch (Throwable $e) {
            error_log('OPS_PDF ' . $e->getMessage());
            return '';
        }
    }

    /** Send a report in the asked format and exit. $sections as cdp_opsXlsx. */
    function cdp_opsSendExport($format, $basename, $title, array $sections, $subtitle = '')
    {
        $basename = preg_replace('/[^A-Za-z0-9_\-]+/', '-', $basename);
        if ($format === 'xlsx') {
            $data = cdp_opsXlsx($sections);
            if ($data !== '') {
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="' . $basename . '.xlsx"');
                echo $data;
                exit;
            }
            $format = 'csv';
        }
        if ($format === 'pdf') {
            $data = cdp_opsPdf($title, $sections, $subtitle);
            if ($data !== '') {
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $basename . '.pdf"');
                echo $data;
                exit;
            }
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . cdp_opsEsc($title) . '</title></head><body onload="window.print()">'
                . cdp_opsReportHtml($title, $sections, $subtitle) . '</body></html>';
            exit;
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $basename . '.csv"');
        $first = true;
        foreach ($sections as $name => $s) {
            if (!$first) {
                echo "\r\n";
            }
            if (count($sections) > 1) {
                echo cdp_opsCsvCell($name) . "\r\n";
            }
            $csv = cdp_opsCsv((array) ($s['head'] ?? []), (array) ($s['rows'] ?? []));
            echo $first ? $csv : substr($csv, 3);
            $first = false;
        }
        exit;
    }
}

// ---------------------------------------------------------------------------
// Page rendering inside the Swift Lane shell (helpers/sl_view.php)
// ---------------------------------------------------------------------------

if (!function_exists('cdp_opsRender')) {

    /**
     * Render an operations page: the view lives in views/sl/pages/<View>.php
     * and draws containers that the page script fills. jQuery is loaded for
     * these pages only (the shell itself does not use it).
     */
    function cdp_opsRender($view, $navId, $title, array $scripts, array $d = [])
    {
        require_once dirname(__DIR__) . '/helpers/asset.php';
        sl_render($view, ['id' => $navId, 'title' => $title, 'board' => $view, 'wide' => true, 'styles' => ['assets/swiftlane/ops.css'],
            'scripts' => array_merge(['assets/template/assets/libs/jquery/dist/jquery.min.js', 'dataJs/ops_common.js'], $scripts)], $d);
    }
}
