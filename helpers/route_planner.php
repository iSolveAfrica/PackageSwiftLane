<?php
/**
 * Route planner: DB layer over helpers/route_engine.php (pure algorithms).
 *
 * - Planning unit = one accepted cdb_delivery_requests row in state 'pending'; its parcels share
 *   an address and move together.
 * - Assigning and delivering always go through DeliveryService::transition (events, customer
 *   messages, pickup codes, proof), so the customer's DeliveryRequest page keeps tracking it.
 * - Distances come from Google Routes when a server key is set, else the offline estimate
 *   (haversine x road factor at Accra speeds); each route records its provider.
 * - All SQL is bound. Tables self-install from migrations/2026_11_26_route_planner.sql.
 */
require_once __DIR__ . '/route_engine.php';
require_once __DIR__ . '/delivery_service.php';
require_once __DIR__ . '/route_google.php';
if (defined('CDP_ROOT') && is_file(CDP_ROOT . '/views/sl/data/riders.php')) require_once CDP_ROOT . '/views/sl/data/riders.php';

const RP_TABLES = ['cdb_rp_settings', 'cdb_rp_vehicles', 'cdb_rp_parcel_meta', 'cdb_rp_plans', 'cdb_rp_routes', 'cdb_rp_stops', 'cdb_rp_load', 'cdb_rp_pings', 'cdb_rp_activity', 'cdb_rp_geocache'];
const RP_DONE_STOP = ['delivered', 'failed', 'returned'];
const RP_ACTIVITY = ['loading', 'onway', 'waiting', 'break'];

/* ------------------------------------------------------------------ DB helpers */

function rp_db(): PDO
{
    return (new Conexion())->dbh;
}

/** Prepared statement with exceptions on (restores the handle's error mode afterwards). */
function rp_q(string $sql, array $params = []): PDOStatement
{
    $db = rp_db();
    $mode = $db->getAttribute(PDO::ATTR_ERRMODE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    try {
        $st = $db->prepare($sql);
        $st->execute($params);
        return $st;
    } finally {
        $db->setAttribute(PDO::ATTR_ERRMODE, $mode);
    }
}
function rp_all(string $sql, array $params = []): array { return rp_q($sql, $params)->fetchAll(PDO::FETCH_ASSOC); }
function rp_one(string $sql, array $params = []): ?array { $r = rp_q($sql, $params)->fetch(PDO::FETCH_ASSOC); return $r ?: null; }
function rp_val(string $sql, array $params = []) { $v = rp_q($sql, $params)->fetchColumn(); return $v === false ? null : $v; }
function rp_in(array $ids): string { return implode(',', array_fill(0, max(1, count($ids)), '?')); }
function rp_now(): string { return date('Y-m-d H:i:s'); }

function rp_service(): DeliveryService
{
    return $GLOBALS['rp_delivery_service'] ?? new DeliveryService(rp_db());
}

/* ------------------------------------------------------------------ schema & settings */

/**
 * CLI scripts (seed, checks, workers) do not build Core, so PHP would stay on UTC while the web
 * pages run on the site time zone (cdb_settings.timezone, set by Core). rp_* rows store local
 * times, so align the CLI clock with the site once.
 */
function rp__alignClock(): void
{
    if (PHP_SAPI !== 'cli') return;
    try {
        $tz = (string) rp_val('SELECT timezone FROM cdb_settings LIMIT 1');
        if ($tz !== '' && in_array($tz, timezone_identifiers_list(), true) && $tz !== date_default_timezone_get()) date_default_timezone_set($tz);
    } catch (Throwable $e) { /* keep the CLI default */ }
}

function rp_ensureSchema(): void
{
    static $done = false;
    if ($done) return;
    require_once __DIR__.'/delivery_storage.php';
    dl_ensureStorage();
    rp__alignClock();
    $have = (int) rp_val('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (' . rp_in(RP_TABLES) . ')', RP_TABLES);
    if ($have < count(RP_TABLES)) {
        $file = dirname(__DIR__) . '/migrations/2026_11_26_route_planner.sql';
        $sql = is_file($file) ? (string) file_get_contents($file) : '';
        foreach (preg_split('/;\s*\n/', $sql) as $stmt) {
            $stmt = trim(preg_replace('/^--.*$/m', '', $stmt));
            if (stripos($stmt, 'CREATE TABLE IF NOT EXISTS') === 0) rp_db()->exec($stmt);
        }
    }
    $done = true;
}

function rp_settings(): array
{
    if (isset($GLOBALS['rp_settings_cache'])) return $GLOBALS['rp_settings_cache'];
    rp_ensureSchema();
    $row = rp_one('SELECT settings_json, revision FROM cdb_rp_settings WHERE id = 1');
    $stored = $row ? (json_decode((string) $row['settings_json'], true) ?: []) : [];
    $s = array_replace_recursive(rp_defaultSettings(), $stored);
    if (!empty($stored['start_places'])) $s['start_places'] = array_values($stored['start_places']);
    $s['revision'] = (int) ($row['revision'] ?? 0);
    return $GLOBALS['rp_settings_cache'] = $s;
}

function rp_startPlace(?string $id = null): array
{
    $s = rp_settings();
    $id = $id ?: (string) $s['default_start'];
    foreach ($s['start_places'] as $p) if ((string) $p['id'] === $id) return $p;
    return $s['start_places'][0] ?? ['id' => 'accra-office', 'label' => 'Swift Lane Accra office', 'lat' => 5.6037, 'lng' => -0.1870];
}

function rp_saveSettings(array $in, int $actor): array
{
    rp_ensureSchema();
    $cur = rp_settings();
    $out = $cur; unset($out['revision']);
    $num = function (string $k, float $min, float $max) use (&$out, $in) {
        if (!isset($in[$k]) || $in[$k] === '') return;
        if (!is_numeric($in[$k]) || $in[$k] < $min || $in[$k] > $max) throw new DomainException("Enter $k between $min and $max");
        $out[$k] = $in[$k] + 0;
    };
    $num('service_min_base', 0, 60); $num('service_min_per_parcel', 0, 30);
    $num('ping_min_s', 5, 600); $num('ping_min_m', 5, 2000);
    $num('stuck_min', 3, 120); $num('late_min', 3, 240); $num('offline_min', 2, 240); $num('idle_loading_min', 5, 240);
    $num('road_factor', 1, 3); $num('max_stops', 1, 60);
    if (isset($in['depart_time']) && $in['depart_time'] !== '') {
        if (rp_timeToSec((string) $in['depart_time']) === null) throw new DomainException('Enter the departure time as HH:MM');
        $out['depart_time'] = substr((string) $in['depart_time'], 0, 5);
    }
    if (isset($in['fill_mode'])) {
        if (!in_array($in['fill_mode'], ['fill', 'balance'], true)) throw new DomainException('Choose fill or balance');
        $out['fill_mode'] = $in['fill_mode'];
    }
    if (isset($in['speeds']) && is_array($in['speeds'])) foreach ($in['speeds'] as $t => $v) {
        if (!in_array($t, RP_VEHICLE_TYPES, true)) continue;
        if (!is_numeric($v) || $v < 3 || $v > 90) throw new DomainException('Speeds must be 3 to 90 km/h');
        $out['speeds'][$t] = $v + 0;
    }
    if (isset($in['vehicle_defaults']) && is_array($in['vehicle_defaults'])) foreach ($in['vehicle_defaults'] as $t => $v) {
        if (!in_array($t, RP_VEHICLE_TYPES, true) || !is_array($v)) continue;
        foreach (['max_kg' => [1, 20000], 'max_l' => [1, 50000], 'max_parcels' => [1, 500], 'cargo_l_cm' => [0, 2000], 'cargo_w_cm' => [0, 1000], 'cargo_h_cm' => [0, 1000]] as $k => [$lo, $hi]) {
            if (!isset($v[$k]) || $v[$k] === '') continue;
            if (!is_numeric($v[$k]) || $v[$k] < $lo || $v[$k] > $hi) throw new DomainException(ucfirst($t) . " $k must be $lo to $hi");
            $out['vehicle_defaults'][$t][$k] = $v[$k] + 0;
        }
        foreach (['can_fragile', 'can_cold', 'can_high_value'] as $k) if (isset($v[$k])) $out['vehicle_defaults'][$t][$k] = !empty($v[$k]) ? 1 : 0;
    }
    if (isset($in['start_places']) && is_array($in['start_places'])) {
        $places = [];
        foreach (array_slice(array_values($in['start_places']), 0, 10) as $i => $p) {
            if (!is_array($p)) continue;
            $label = trim((string) ($p['label'] ?? ''));
            if ($label === '' && ($p['lat'] ?? '') === '') continue;
            if (mb_strlen($label) < 2 || mb_strlen($label) > 80) throw new DomainException('Each start place needs a name (2-80 characters)');
            if (!is_numeric($p['lat'] ?? null) || !is_numeric($p['lng'] ?? null) || abs((float) $p['lat']) > 90 || abs((float) $p['lng']) > 180) throw new DomainException('Start place "' . $label . '" needs valid coordinates');
            $id = preg_replace('/[^a-z0-9-]/', '', strtolower((string) ($p['id'] ?? ''))) ?: 'start-' . ($i + 1);
            $places[] = ['id' => $id, 'label' => $label, 'lat' => round((float) $p['lat'], 7), 'lng' => round((float) $p['lng'], 7)];
        }
        if (!$places) throw new DomainException('Keep at least one start place');
        if (count(array_unique(array_column($places, 'id'))) !== count($places)) throw new DomainException('Start place ids must be unique');
        $out['start_places'] = $places;
    }
    if (isset($in['default_start'])) $out['default_start'] = (string) $in['default_start'];
    if (!in_array($out['default_start'], array_column($out['start_places'], 'id'), true)) $out['default_start'] = $out['start_places'][0]['id'];
    $rev = (int) $cur['revision'] + 1;
    rp_q('INSERT INTO cdb_rp_settings (id, settings_json, revision, updated_by, updated_at) VALUES (1, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE settings_json = VALUES(settings_json), revision = VALUES(revision), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)',
        [json_encode($out, JSON_UNESCAPED_UNICODE), $rev, $actor, rp_now()]);
    unset($GLOBALS['rp_settings_cache']);
    return rp_settings();
}

/* ------------------------------------------------------------------ vehicles & parcels */

/** Legacy installations may only store vehiclecode and have no rider-profile columns. */
function rp__userSelect(array $columns): string
{
    $select=[];
    foreach($columns as $column) {
        if(rp__hasColumn('cdb_users',$column))$select[]=$column;
        elseif($column==='vehicle_type' && rp__hasColumn('cdb_users','vehiclecode'))$select[]='vehiclecode AS vehicle_type';
        else $select[]=($column==='agency_id'?'0':"''").' AS '.$column;
    }
    return implode(', ',$select);
}

function rp_vehicle(int $driverId): array
{
    rp_ensureSchema();
    $u = rp_one('SELECT '.rp__userSelect(['id','vehicle_type','vehiclecode','agency_id']).' FROM cdb_users WHERE id = ?', [$driverId]) ?? [];
    $row = rp_one('SELECT * FROM cdb_rp_vehicles WHERE driver_id = ?', [$driverId]);
    $type = rp_vehicleType($row['vehicle_type'] ?? ($u['vehicle_type'] ?? $u['vehiclecode'] ?? null));
    $def = rp_settings()['vehicle_defaults'][$type] ?? rp_defaultSettings()['vehicle_defaults']['motorbike'];
    $v = ['driver_id' => $driverId, 'vehicle_type' => $type, 'plate' => '', 'make_model' => '', 'docs' => [], 'active' => 1, 'source' => 'default'] + $def;
    if ($row) {
        foreach (['plate', 'make_model'] as $k) $v[$k] = (string) $row[$k];
        foreach (['max_kg', 'max_l'] as $k) $v[$k] = (float) $row[$k] ?: (float) $def[$k];
        foreach (['max_parcels', 'cargo_l_cm', 'cargo_w_cm', 'cargo_h_cm'] as $k) $v[$k] = (int) $row[$k] ?: (int) $def[$k];
        foreach (['can_fragile', 'can_cold', 'can_high_value', 'active'] as $k) $v[$k] = (int) $row[$k];
        $v['docs'] = json_decode((string) $row['docs_json'], true) ?: [];
        $v['source'] = 'saved';
        $v['updated_at'] = $row['updated_at'];
    }
    $v['agency_id'] = (int) ($u['agency_id'] ?? 0);
    // Uploaded rider documents (cdb_driver_files) the vehicle card can reference.
    $v['files'] = sl_tableExists('cdb_driver_files') ? rp_all('SELECT id, name, file_type, date_file FROM cdb_driver_files WHERE driver_id = ? ORDER BY id DESC LIMIT 20', [$driverId]) : [];
    $v['docs_json'] = $v['docs'];
    return $v;
}

function rp_saveVehicle(int $driverId, array $in, int $actor): array
{
    rp_ensureSchema();
    if (!rp_one('SELECT id FROM cdb_users WHERE id = ? AND userlevel IN (' . implode(',', array_map('intval', function_exists('sl_riderRoleIds') ? sl_riderRoleIds() : [3])) . ')', [$driverId])) throw new DomainException('Rider not found');
    $cur = rp_vehicle($driverId);
    $type = (string) ($in['vehicle_type'] ?? $cur['vehicle_type']);
    if (!in_array($type, RP_VEHICLE_TYPES, true)) throw new DomainException('Choose a vehicle type');
    $def = rp_settings()['vehicle_defaults'][$type];
    $plate = strtoupper(trim((string) ($in['plate'] ?? $cur['plate'])));
    if (mb_strlen($plate) > 20 || ($plate !== '' && !preg_match('/^[A-Z0-9 -]+$/', $plate))) throw new DomainException('Plate: letters, digits, spaces and dashes only (max 20)');
    $make = trim((string) ($in['make_model'] ?? $cur['make_model']));
    if (mb_strlen($make) > 80) throw new DomainException('Make and model: 80 characters at most');
    $n = function (string $k, float $lo, float $hi) use ($in, $cur, $def) {
        $v = $in[$k] ?? ($cur['source'] === 'saved' ? $cur[$k] : $def[$k]);
        if ($v === '' || $v === null) $v = $def[$k];
        if (!is_numeric($v) || $v < $lo || $v > $hi) throw new DomainException(str_replace('_', ' ', $k) . " must be $lo to $hi");
        return $v + 0;
    };
    $vals = ['max_kg' => $n('max_kg', 1, 20000), 'max_l' => $n('max_l', 1, 50000), 'max_parcels' => (int) $n('max_parcels', 1, 500), 'cargo_l_cm' => (int) $n('cargo_l_cm', 0, 2000), 'cargo_w_cm' => (int) $n('cargo_w_cm', 0, 1000), 'cargo_h_cm' => (int) $n('cargo_h_cm', 0, 1000)];
    $flag = fn($k) => array_key_exists($k, $in) ? (!empty($in[$k]) && $in[$k] !== '0' ? 1 : 0) : (int) $cur[$k];
    $docs = $cur['docs'];
    if (isset($in['docs'])) {
        $raw = is_string($in['docs']) ? (json_decode($in['docs'], true) ?: []) : (array) $in['docs'];
        $fileIds = array_map(fn($f) => (int) $f['id'], $cur['files']);
        $docs = [];
        foreach (array_slice($raw, 0, 12) as $d) {
            if (!is_array($d)) continue;
            $name = trim((string) ($d['name'] ?? ''));
            if ($name === '') continue;
            $fid = (int) ($d['file_id'] ?? 0);
            if ($fid && !in_array($fid, $fileIds, true)) throw new DomainException('A document refers to a file of another rider');
            $exp = trim((string) ($d['expires'] ?? ''));
            if ($exp !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $exp)) throw new DomainException('Document expiry dates use YYYY-MM-DD');
            $docs[] = ['name' => mb_substr($name, 0, 60), 'file_id' => $fid ?: null, 'expires' => $exp ?: null];
        }
    }
    rp_q('INSERT INTO cdb_rp_vehicles (driver_id, vehicle_type, plate, make_model, max_kg, max_l, max_parcels, cargo_l_cm, cargo_w_cm, cargo_h_cm, can_fragile, can_cold, can_high_value, docs_json, active, updated_by, updated_at)
          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE vehicle_type=VALUES(vehicle_type), plate=VALUES(plate), make_model=VALUES(make_model), max_kg=VALUES(max_kg), max_l=VALUES(max_l), max_parcels=VALUES(max_parcels),
          cargo_l_cm=VALUES(cargo_l_cm), cargo_w_cm=VALUES(cargo_w_cm), cargo_h_cm=VALUES(cargo_h_cm), can_fragile=VALUES(can_fragile), can_cold=VALUES(can_cold), can_high_value=VALUES(can_high_value), docs_json=VALUES(docs_json), active=VALUES(active), updated_by=VALUES(updated_by), updated_at=VALUES(updated_at)',
        [$driverId, $type, $plate, $make, $vals['max_kg'], $vals['max_l'], $vals['max_parcels'], $vals['cargo_l_cm'], $vals['cargo_w_cm'], $vals['cargo_h_cm'], $flag('can_fragile'), $flag('can_cold'), $flag('can_high_value'), json_encode($docs), $flag('active'), $actor, rp_now()]);
    return rp_vehicle($driverId);
}

/** [cm per stored length unit, kg per stored weight unit] from the site settings (units=in, weight_p=lb). */
function rp__unitFactors(): array
{
    static $f = null;
    if ($f !== null) return $f;
    $row = null;
    try { $row = rp_one('SELECT units, weight_p FROM cdb_settings LIMIT 1'); } catch (Throwable $e) { $row = null; }
    $u = strtolower(trim((string) ($row['units'] ?? 'cm')));
    $w = strtolower(trim((string) ($row['weight_p'] ?? 'kg')));
    return $f = [in_array($u, ['in', 'inch', 'inches'], true) ? 2.54 : 1.0, in_array($w, ['lb', 'lbs', 'pound', 'pounds'], true) ? 0.45359237 : 1.0];
}

function rp__hasColumn(string $table, string $col): bool
{
    static $c = [];
    return $c[$table . '.' . $col] ??= (bool) rp_val('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$table, $col]);
}

/** Batch parcel details keyed "module:order_id" (item tables merged with cdb_rp_parcel_meta). */
function rp_parcelDetailsMany(array $refs): array
{
    rp_ensureSchema();
    $by = ['air' => [], 'sea' => []];
    foreach ($refs as $r) { $m = $r[0] === 'sea' ? 'sea' : 'air'; $by[$m][(int) $r[1]] = true; }
    $out = [];
    [$cmPer, $kgPer] = rp__unitFactors();
    $readyCfg = dl_config();
    foreach ($by as $module => $ids) {
        $ids = array_keys($ids);
        if (!$ids) continue;
        $head = $module === 'air' ? 'cdb_add_order' : 'cdb_customers_packages';
        $detail = $module === 'air' ? 'cdb_add_order_item' : 'cdb_customers_packages_detail';
        $orders = [];
        foreach (rp_all("SELECT o.order_id, o.order_prefix, o.order_no, o.total_weight, o.declared_value, o.total_declared_value, o.notes, o.status_courier, o.sender_id" . ($module === 'air' ? ', o.is_pickup, o.is_deleted' : '') . ", s.mod_style AS status_name FROM $head o LEFT JOIN cdb_styles s ON s.id = o.status_courier WHERE o.order_id IN (" . rp_in($ids) . ')', $ids) as $o) $orders[(int) $o['order_id']] = $o;
        $items = [];
        $groupCol = $module === 'air' && rp__hasColumn($detail, 'order_item_weight_group') ? ', order_item_weight_group' : '';
        foreach (rp_all("SELECT order_id, order_item_description, order_item_quantity, order_item_weight, order_item_length, order_item_width, order_item_height, order_item_declared_value$groupCol FROM $detail WHERE order_id IN (" . rp_in($ids) . ') ORDER BY order_item_id', $ids) as $it) $items[(int) $it['order_id']][] = $it;
        $meta = [];
        foreach (rp_all('SELECT * FROM cdb_rp_parcel_meta WHERE module = ? AND order_id IN (' . rp_in($ids) . ')', array_merge([$module], $ids)) as $mt) $meta[(int) $mt['order_id']] = $mt;
        $claims = [];
        if (sl_tableExists('cdb_delivery_items')) foreach (rp_all('SELECT order_id, tracking FROM cdb_delivery_items WHERE module = ? AND order_id IN (' . rp_in($ids) . ')', array_merge([$module], $ids)) as $c) $claims[(int) $c['order_id']] = $c['tracking'];
        foreach ($ids as $id) {
            $o = $orders[$id] ?? null;
            $mt = $meta[$id] ?? [];
            $its = $items[$id] ?? [];
            $kg = 0.0; $vol = 0.0; $desc = []; $value = 0.0; $best = [0, 0, 0]; $bestV = -1; $groups = [];
            foreach ($its as $it) {
                $q = max(1, (float) $it['order_item_quantity']);
                // Same rule as cdp_calculateTotals: weight is per unit (x quantity), except a weight
                // group, whose batch weight is recorded on every member row and counts once.
                $group = trim((string) ($it['order_item_weight_group'] ?? ''));
                if ($group === '') $kg += (float) $it['order_item_weight'] * $q * $kgPer;
                elseif (!isset($groups[$group])) { $groups[$group] = true; $kg += (float) $it['order_item_weight'] * $kgPer; }
                $d = [(float) $it['order_item_length'] * $cmPer, (float) $it['order_item_width'] * $cmPer, (float) $it['order_item_height'] * $cmPer];
                $v = rp_parcelVolumeL($d);
                $vol += $v * $q;
                if ($v > $bestV) { $bestV = $v; $best = $d; }
                if (trim((string) $it['order_item_description']) !== '') $desc[] = trim((string) $it['order_item_description']) . ($q > 1 ? ' x' . (int) $q : '');
                $value += (float) $it['order_item_declared_value'] * $q;
            }
            if ($kg <= 0 && $o) $kg = (float) $o['total_weight'] * $kgPer;
            if ($value <= 0 && $o) $value = (float) ($o['total_declared_value'] ?: $o['declared_value']);
            $l = isset($mt['length_cm']) && $mt['length_cm'] !== null ? (float) $mt['length_cm'] : $best[0];
            $w = isset($mt['width_cm']) && $mt['width_cm'] !== null ? (float) $mt['width_cm'] : $best[1];
            $h = isset($mt['height_cm']) && $mt['height_cm'] !== null ? (float) $mt['height_cm'] : $best[2];
            if (isset($mt['weight_kg']) && $mt['weight_kg'] !== null) $kg = (float) $mt['weight_kg'];
            $estimated = false;
            if (isset($mt['volume_l']) && $mt['volume_l'] !== null) $vol = (float) $mt['volume_l'];
            elseif (isset($mt['length_cm']) && $mt['length_cm'] !== null) $vol = rp_parcelVolumeL([$l, $w, $h]);
            if ($vol <= 0) { $vol = round(max(1.0, $kg * 4), 1); $estimated = true; } // ~250 kg/m3 when no dimensions are on file
            if ($kg <= 0) { $kg = 1.0; $estimated = true; }
            $handling = array_values(array_filter(explode(',', (string) ($mt['handling'] ?? ''))));
            $dims = [$l, $w, $h]; rsort($dims);
            if ($dims[0] > 120 && !in_array('oversize', $handling, true)) $handling[] = 'oversize';
            $valueCents = isset($mt['value_cents']) && $mt['value_cents'] !== null ? (int) $mt['value_cents'] : (int) round($value * 100);
            $ws = isset($mt['window_start']) && $mt['window_start'] !== null ? substr((string) $mt['window_start'], 0, 5) : null;
            $we = isset($mt['window_end']) && $mt['window_end'] !== null ? substr((string) $mt['window_end'], 0, 5) : null;
            $out[$module . ':' . $id] = [
                'module' => $module, 'order_id' => $id,
                'tracking' => $o ? (string) $o['order_prefix'] . $o['order_no'] : (string) ($claims[$id] ?? ('#' . $id)),
                'contents' => (string) (($mt['contents'] ?? '') !== '' ? $mt['contents'] : implode(', ', $desc)),
                'l' => round($l, 1), 'w' => round($w, 1), 'h' => round($h, 1),
                'kg' => round($kg, 2), 'volume_l' => round($vol, 2), 'estimated' => $estimated,
                'handling' => $handling, 'value' => round($valueCents / 100, 2), 'value_cents' => $valueCents,
                'currency' => (string) (($mt['currency'] ?? '') ?: 'USD'),
                'note' => (string) ($mt['customer_note'] ?? ''),
                'window' => ['start' => $ws, 'end' => $we], 'window_start' => $ws, 'window_end' => $we,
                'priority' => (int) ($mt['priority'] ?? 0),
                'status' => (string) ($o['status_name'] ?? ''), 'status_id' => (int) ($o['status_courier'] ?? 0),
                'items' => count($its), 'exists' => (bool) $o,
                // Same rule DeliveryService applies when a request is assigned or started.
                'ready' => $o && empty($o['is_deleted']) && dl_ready((object) ['status_courier' => $o['status_courier'], 'is_pickup' => $o['is_pickup'] ?? 0, 'status_name' => $o['status_name'] ?? ''], $readyCfg),
            ];
        }
    }
    return $out;
}

function rp_parcelDetails(string $module, int $orderId): array
{
    $module = $module === 'sea' ? 'sea' : 'air';
    return rp_parcelDetailsMany([[$module, $orderId]])[$module . ':' . $orderId];
}

function rp_saveParcelMeta(string $module, int $orderId, array $in, int $actor): array
{
    rp_ensureSchema();
    if (!in_array($module, ['air', 'sea'], true)) throw new DomainException('Invalid parcel type');
    $head = $module === 'air' ? 'cdb_add_order' : 'cdb_customers_packages';
    $o = rp_one("SELECT order_id, agency FROM $head WHERE order_id = ?", [$orderId]);
    if (!$o) throw new DomainException('Parcel not found');
    $agency = function_exists('dl_agency') ? dl_agency() : 0;
    if ($agency && (int) $o['agency'] !== $agency) throw new DomainException('Parcel not found');
    $num = function (string $k, float $lo, float $hi) use ($in): ?float {
        if (!isset($in[$k]) || $in[$k] === '' || $in[$k] === null) return null;
        if (!is_numeric($in[$k]) || $in[$k] < $lo || $in[$k] > $hi) throw new DomainException(str_replace('_', ' ', $k) . " must be $lo to $hi");
        return (float) $in[$k];
    };
    $time = function (string $k) use ($in): ?string {
        $v = trim((string) ($in[$k] ?? ''));
        if ($v === '') return null;
        if (rp_timeToSec($v) === null) throw new DomainException('Delivery window times use HH:MM');
        return substr($v, 0, 5) . ':00';
    };
    $handling = $in['handling'] ?? [];
    if (is_string($handling)) $handling = array_filter(array_map('trim', explode(',', $handling)));
    $handling = array_values(array_intersect(RP_HANDLING, (array) $handling));
    $ws = $time('window_start'); $we = $time('window_end');
    if ($ws && $we && $ws >= $we) throw new DomainException('The delivery window must end after it starts');
    $prio = (int) ($in['priority'] ?? 0);
    if ($prio < 0 || $prio > 2) throw new DomainException('Priority is normal, high or urgent');
    $contents = trim((string) ($in['contents'] ?? ''));
    $note = trim((string) ($in['customer_note'] ?? $in['note'] ?? ''));
    if (mb_strlen($contents) > 250 || mb_strlen($note) > 500) throw new DomainException('Contents (250) or note (500) is too long');
    $cur = strtoupper(trim((string) ($in['currency'] ?? '')));
    if ($cur !== '' && !preg_match('/^[A-Z]{3}$/', $cur)) throw new DomainException('Currency is a 3-letter code');
    $value = $num('value', 0, 10000000);
    $l = $num('length_cm', 0, 1000); $w = $num('width_cm', 0, 1000); $h = $num('height_cm', 0, 1000);
    $vol = $num('volume_l', 0, 50000);
    if ($vol === null && $l && $w && $h) $vol = rp_parcelVolumeL([$l, $w, $h]);
    rp_q('INSERT INTO cdb_rp_parcel_meta (module, order_id, length_cm, width_cm, height_cm, weight_kg, volume_l, contents, handling, value_cents, currency, customer_note, window_start, window_end, priority, updated_by, updated_at)
          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE length_cm=VALUES(length_cm), width_cm=VALUES(width_cm), height_cm=VALUES(height_cm), weight_kg=VALUES(weight_kg), volume_l=VALUES(volume_l), contents=VALUES(contents),
          handling=VALUES(handling), value_cents=VALUES(value_cents), currency=VALUES(currency), customer_note=VALUES(customer_note), window_start=VALUES(window_start), window_end=VALUES(window_end), priority=VALUES(priority), updated_by=VALUES(updated_by), updated_at=VALUES(updated_at)',
        [$module, $orderId, $l, $w, $h, $num('weight_kg', 0, 5000), $vol, $contents !== '' ? $contents : null, implode(',', $handling), $value !== null ? (int) round($value * 100) : null, $cur ?: null, $note !== '' ? $note : null, $ws, $we, $prio, $actor, rp_now()]);
    return rp_parcelDetails($module, $orderId);
}

/* ------------------------------------------------------------------ planning */

/** Pending (customer-approved, unassigned) requests as planning units. */
function rp_cargoHold(int $agency = 0): array
{
    rp_ensureSchema();
    if (!sl_tableExists('cdb_delivery_requests')) return ['units' => [], 'unplaceable' => []];
    rp_reconcileStops();
    $reqs = rp_all("SELECT r.*, z.name AS zone_name FROM cdb_delivery_requests r LEFT JOIN cdb_delivery_zones z ON z.id = r.zone_id WHERE r.state = 'pending' AND r.approved_at IS NOT NULL" . ($agency ? ' AND r.agency_id = ?' : '') . ' ORDER BY r.id', $agency ? [$agency] : []);
    return rp__unitsFor($reqs, true);
}

/**
 * Units (with parcels[]) for request rows; split into placeable units and unplaceable ones.
 * $holdCheck (the cargo hold): a request whose parcel was collected, deleted or is otherwise no
 * longer ready cannot be assigned by DeliveryService, so it is unplaceable with the reason.
 */
function rp__unitsFor(array $reqs, bool $holdCheck = false): array
{
    if (!$reqs) return ['units' => [], 'unplaceable' => []];
    $ids = array_map(fn($r) => (int) $r['id'], $reqs);
    $items = [];
    foreach (rp_all('SELECT request_id, module, order_id, tracking FROM cdb_delivery_items WHERE request_id IN (' . rp_in($ids) . ') ORDER BY request_id, order_id', $ids) as $it) $items[(int) $it['request_id']][] = $it;
    $refs = [];
    foreach ($reqs as $r) {
        $list = $items[(int) $r['id']] ?? array_map(fn($oid) => ['module' => $r['module'], 'order_id' => (int) $oid], json_decode((string) $r['parcel_json'], true) ?: []);
        foreach ($list as $it) $refs[] = [$it['module'], (int) $it['order_id']];
    }
    $details = rp_parcelDetailsMany($refs);
    $units = []; $bad = [];
    foreach ($reqs as $r) {
        $list = $items[(int) $r['id']] ?? array_map(fn($oid) => ['module' => $r['module'], 'order_id' => (int) $oid], json_decode((string) $r['parcel_json'], true) ?: []);
        $parcels = [];
        foreach ($list as $it) if (isset($details[$it['module'] . ':' . (int) $it['order_id']])) $parcels[] = $details[$it['module'] . ':' . (int) $it['order_id']];
        $lat = (float) $r['lat']; $lng = (float) $r['lng'];
        $u = rp_unitFromRequest(['id' => (int) $r['id'], 'lat' => abs($lat) + abs($lng) > 0 ? $lat : null, 'lng' => abs($lat) + abs($lng) > 0 ? $lng : null, 'zone_id' => $r['zone_id'], 'agency_id' => $r['agency_id']], $parcels);
        $u += ['request_id' => (int) $r['id'], 'module' => (string) $r['module'], 'address' => (string) $r['address'], 'contact_name' => (string) $r['contact_name'], 'contact_phone' => (string) $r['contact_phone'],
            'instructions' => (string) $r['instructions'], 'zone_name' => (string) ($r['zone_name'] ?? ''), 'area' => (string) ($r['zone_name'] ?? '') ?: rp__area((string) $r['address']), 'customer_id' => (int) $r['customer_id'],
            'state' => (string) $r['state'], 'driver_id' => (int) $r['driver_id'], 'approved_at' => $r['approved_at'], 'amount_cents' => (int) $r['amount_cents'], 'currency' => (string) $r['currency']];
        if ($holdCheck) {
            $notReady = array_values(array_filter($parcels, fn($pc) => empty($pc['ready'])));
            if (!$parcels || count($parcels) < count($list)) { $u['reason'] = 'Parcel not ready: parcel record missing'; $u['not_ready'] = true; $bad[] = $u; continue; }
            if ($notReady) { $u['reason'] = 'Parcel not ready: ' . $notReady[0]['tracking'] . ' is ' . ($notReady[0]['exists'] ? '"' . (str_replace('_', ' ', $notReady[0]['status']) ?: 'status ' . $notReady[0]['status_id']) . '"' : 'missing'); $u['not_ready'] = true; $bad[] = $u; continue; }
        }
        if ($u['lat'] === null) { $u['reason'] = 'No map location'; $bad[] = $u; continue; }
        if (!$r['zone_id']) $u['out_of_zone'] = true;
        $units[] = $u;
    }
    return ['units' => $units, 'unplaceable' => $bad];
}

function rp__area(string $address): string
{
    $parts = array_values(array_filter(array_map('trim', explode(',', $address))));
    return $parts[count($parts) > 2 ? count($parts) - 3 : 0] ?? '';
}

/** Zone id for a rider's free-text zone (cdb_users.zone) by delivery-zone name. */
function rp__zoneIdByName(): array
{
    static $map = null;
    if ($map !== null) return $map;
    $map = [];
    if (sl_tableExists('cdb_delivery_zones')) foreach (rp_all('SELECT id, name FROM cdb_delivery_zones WHERE active = 1') as $z) $map[mb_strtolower(trim((string) $z['name']))] = (int) $z['id'];
    return $map;
}

/** Departure ISO for a plan date: the settings' depart time, or now when that has passed. */
function rp__departIso(string $date, array $settings): string
{
    $planned = strtotime($date . ' ' . ($settings['depart_time'] ?? '09:00'));
    return date('c', $date === date('Y-m-d') ? max(time(), (int) $planned) : (int) $planned);
}

/** Today's route of a rider: the newest open one, else the newest finished one. */
function rp__routeRow(int $driverId, string $date): ?array
{
    // The route under way wins over a newer planned one, so a rider on the road never loses it.
    return rp_one("SELECT * FROM cdb_rp_routes WHERE driver_id = ? AND route_date = ? ORDER BY FIELD(status, 'onroute', 'loading', 'planned', 'done'), id DESC", [$driverId, $date]);
}

function rp__openActivity(int $driverId): ?array
{
    return rp_one('SELECT * FROM cdb_rp_activity WHERE driver_id = ? AND ended_at IS NULL ORDER BY id DESC', [$driverId]);
}

function rp__riders(int $agency = 0): array
{
    $roles = array_map('intval', function_exists('sl_riderRoleIds') ? sl_riderRoleIds() : [3]);
    $rows = rp_all('SELECT '.rp__userSelect(['id','username','fname','lname','phone','active','agency_id','vehicle_type','vehiclecode','zone','rider_status']).' FROM cdb_users WHERE active = 1 AND userlevel IN (' . implode(',', $roles) . ')' . ($agency ? (rp__hasColumn('cdb_users','agency_id')?' AND agency_id = ?':' AND 1=0') : '') . ' ORDER BY fname, lname, id', $agency && rp__hasColumn('cdb_users','agency_id') ? [$agency] : []);
    $out = [];
    foreach ($rows as $r) { $r['name'] = trim($r['fname'] . ' ' . $r['lname']) ?: $r['username']; $out[(int) $r['id']] = $r; }
    return $out;
}

/** Active riders with vehicle, status, current load and zone. */
function rp_ridersForPlanning(string $date, int $agency = 0): array
{
    rp_ensureSchema();
    $zones = rp__zoneIdByName();
    $out = [];
    $riders = rp__riders($agency);
    if (!$riders) return [];
    $ids = array_keys($riders);
    // Current load: requests assigned / on route that are still on board.
    $busy = [];
    if (sl_tableExists('cdb_delivery_requests')) {
        $reqs = rp_all("SELECT * FROM cdb_delivery_requests WHERE state IN ('assigned','onroute') AND driver_id IN (" . rp_in($ids) . ')', $ids);
        // Work on a route of another day is not in the vehicle on $date: a later day's route has not
        // been loaded yet, and when planning a later day today's routes are finished by then.
        // (A route left over from a past day still counts against today.)
        $today = date('Y-m-d');
        $other = rp_all("SELECT s.request_id FROM cdb_rp_stops s JOIN cdb_rp_routes r ON r.id = s.route_id WHERE r.driver_id IN (" . rp_in($ids) . ") AND r.status <> 'done' AND s.status IN ('pending','arrived') AND (r.route_date > ?" . ($date > $today ? ' OR r.route_date < ?' : '') . ')', array_merge($ids, [$date], $date > $today ? [$date] : []));
        if ($other) { $skip = array_flip(array_map(fn($o) => (int) $o['request_id'], $other)); $reqs = array_values(array_filter($reqs, fn($r) => !isset($skip[(int) $r['id']]))); }
        $res = rp__unitsFor($reqs);
        // Requests without a map pin still ride in the vehicle: they count toward its load.
        foreach (array_merge($res['units'], $res['unplaceable']) as $u) {
            $b = &$busy[$u['driver_id']];
            $b = ($b ?? ['kg' => 0, 'l' => 0, 'n' => 0, 'requests' => []]);
            $b['kg'] += $u['kg']; $b['l'] += $u['l']; $b['n'] += $u['n']; $b['requests'][] = $u['id'];
            unset($b);
        }
    }
    $draft = rp_one("SELECT params_json FROM cdb_rp_plans WHERE status = 'draft' AND plan_date = ? AND agency_id = ? ORDER BY id DESC", [$date, $agency]);
    $locked = array_map('intval', (array) ((json_decode((string) ($draft['params_json'] ?? ''), true) ?: [])['lock'] ?? []));
    foreach ($riders as $id => $r) {
        $route = rp__routeRow($id, $date);
        $act = rp__openActivity($id);
        $status = rp__riderStatus($r, $route, $act);
        $zoneName = trim((string) $r['zone']);
        $v = rp_vehicle($id);
        $out[$id] = [
            'id' => $id, 'name' => $r['name'], 'phone' => (string) $r['phone'], 'agency_id' => (int) $r['agency_id'],
            'vehicle' => $v, 'vehicle_type' => $v['vehicle_type'], 'status' => $status, 'rider_status' => (string) $r['rider_status'],
            'zone' => $zones[mb_strtolower($zoneName)] ?? null, 'zone_name' => $zoneName,
            'used' => ['kg' => round($busy[$id]['kg'] ?? 0, 2), 'l' => round($busy[$id]['l'] ?? 0, 2), 'n' => (int) ($busy[$id]['n'] ?? 0)],
            'requests' => $busy[$id]['requests'] ?? [],
            'route_id' => $route ? (int) $route['id'] : 0, 'route_status' => $route['status'] ?? null,
            'locked' => in_array($id, $locked, true), 'units' => [],
        ];
    }
    return $out;
}

/** available | loading | onway | waiting | off | suspended */
function rp__riderStatus(array $r, ?array $route, ?array $act): string
{
    $rs = strtolower((string) ($r['rider_status'] ?? ''));
    if ($rs === 'suspended') return 'suspended';
    if (in_array($rs, ['off', 'offline', 'off_duty', 'leave'], true)) return 'off';
    if ($route && $route['status'] === 'onroute') return ($act && in_array($act['state'], ['waiting', 'break'], true)) ? 'waiting' : 'onway';
    if ($route && $route['status'] === 'loading') return 'loading';
    if ($route && $route['status'] === 'planned') return 'assigned';
    return 'available';
}

function rp__unitLite(array $u): array
{
    return array_intersect_key($u, array_flip(['id', 'lat', 'lng', 'zone_id', 'agency_id', 'kg', 'l', 'n', 'handling', 'value_cents', 'window_start', 'window_end', 'priority', 'max_dims', 'address', 'contact_name', 'area']));
}

/** Matrix for [[lat,lng],...]: Google when a server key works, else the offline estimate. */
function rp_providerMatrix(array $points, string $vehicleType): array
{
    static $cache = [];
    $settings = rp_settings();
    $key = md5(json_encode([$points, $vehicleType]));
    if (isset($cache[$key])) return $cache[$key];
    $serverKey = (string) (getenv('CDP_GOOGLE_MAPS_SERVER_KEY') ?: (function_exists('dl_config') ? dl_config()['server_key'] : ''));
    // After a Google failure, skip it for 5 minutes so an outage does not cost seconds per call.
    $flag = sys_get_temp_dir() . '/rp_google_down_' . md5($serverKey);
    $down = is_file($flag) && filemtime($flag) > time() - 300;
    if ($serverKey !== '' && count($points) >= 2 && count($points) <= 25 && empty($GLOBALS['rp_offline']) && !$down) {
        try {
            // DRIVE everywhere (TWO_WHEELER is not offered in most countries, Ghana included);
            // rp_matrixForVehicle scales the times for motorbikes, vans and so on.
            $g = new RouteGoogle($serverKey);
            return $cache[$key] = rp_matrixForVehicle($g->matrix($points, $points, 'DRIVE'), rp_vehicleType($vehicleType), $settings);
        } catch (Throwable $e) {
            @touch($flag);
            error_log('route planner: Google matrix failed, using estimate for 5 min: ' . $e->getMessage());
        }
    }
    return $cache[$key] = rp_estimateMatrix($points, $vehicleType, $settings);
}

function rp_geocodeText(string $address): ?array
{
    rp_ensureSchema();
    $norm = mb_strtolower(trim(preg_replace('/\s+/', ' ', $address)));
    if ($norm === '') return null;
    $hash = sha1($norm);
    $hit = rp_one('SELECT lat, lng, source FROM cdb_rp_geocache WHERE hash = ?', [$hash]);
    if ($hit) return ['lat' => (float) $hit['lat'], 'lng' => (float) $hit['lng'], 'source' => $hit['source'], 'cached' => true];
    $serverKey = (string) (getenv('CDP_GOOGLE_MAPS_SERVER_KEY') ?: dl_config()['server_key']);
    if ($serverKey === '') return null;
    try {
        $r = (new RouteGoogle($serverKey))->geocodeText($address, (string) (dl_config()['country'] ?? 'GH'));
    } catch (Throwable $e) {
        return null;
    }
    if (!$r) return null;
    rp_q('INSERT IGNORE INTO cdb_rp_geocache (hash, address, lat, lng, source, created_at) VALUES (?,?,?,?,?,?)', [$hash, mb_substr($address, 0, 700), $r['lat'], $r['lng'], 'google', rp_now()]);
    return ['lat' => $r['lat'], 'lng' => $r['lng'], 'source' => 'google', 'cached' => false];
}

/**
 * Run the allocation and save it as the draft plan of the day (older drafts are discarded).
 * $opts: date 'YYYY-MM-DD' (today when omitted; a later day plans that day's routes: riders are
 * free of today's load and stops are timed from that day's departure time; a past day is refused),
 * start_id, mode fill|balance, lock[] rider ids (keep their current draft units),
 * keep[riderId] = request ids pinned to that rider, agency.
 */
function rp_proposal(int $actor, array $opts = []): array
{
    rp_ensureSchema();
    $settings = rp_settings();
    $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($opts['date'] ?? '')) ? $opts['date'] : date('Y-m-d');
    if ($date < date('Y-m-d')) throw new DomainException('Choose today or a later day to plan');
    if (!checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) throw new DomainException('Choose a valid date');
    $agency = (int) ($opts['agency'] ?? (function_exists('dl_agency') ? dl_agency() : 0));
    $start = rp_startPlace($opts['start_id'] ?? null);
    $mode = ($opts['mode'] ?? $settings['fill_mode']) === 'balance' ? 'balance' : 'fill';
    $keep = [];
    foreach ((array) ($opts['keep'] ?? []) as $rid => $reqIds) $keep[(int) $rid] = array_map('intval', (array) $reqIds);

    $prev = rp_one("SELECT * FROM cdb_rp_plans WHERE status = 'draft' AND plan_date = ? AND agency_id = ? ORDER BY id DESC", [$date, $agency]);
    $prevProp = $prev ? (json_decode((string) $prev['proposal_json'], true) ?: []) : [];
    // No 'lock' option: the riders locked in the current draft stay locked (rp_lockRider stored them).
    $lock = array_key_exists('lock', $opts) ? (array) $opts['lock'] : (array) ((json_decode((string) ($prev['params_json'] ?? ''), true) ?: [])['lock'] ?? []);
    $lock = array_values(array_unique(array_map('intval', $lock)));
    foreach ($lock as $rid) if (!isset($keep[$rid])) $keep[$rid] = array_map('intval', (array) ($prevProp['riders'][$rid]['units'] ?? $prevProp['riders'][(string) $rid]['units'] ?? []));

    $hold = rp_cargoHold($agency);
    $units = $hold['units'];
    $planners = rp_ridersForPlanning($date, $agency);
    $riders = [];
    foreach ($planners as $id => $p) {
        $v = $p['vehicle'];
        // Riders already on the road are not planned again (their new stops would need a trip back
        // to the office); a dispatcher can still move a stop onto them by hand.
        $st = in_array($p['status'], ['off', 'suspended'], true) ? $p['status'] : (in_array($p['status'], ['onway', 'waiting'], true) ? 'onway' : 'available');
        $riders[] = ['id' => $id, 'vehicle' => $v, 'used' => $p['used'], 'zone' => $p['zone'], 'locked' => in_array($id, $lock, true), 'status' => $st, 'units' => $keep[$id] ?? []];
    }
    $points = [[(float) $start['lat'], (float) $start['lng']]];
    foreach ($units as $u) $points[] = [(float) $u['lat'], (float) $u['lng']];
    $sortedUnits = $units; usort($sortedUnits, fn($a, $b) => $a['id'] <=> $b['id']);
    $pts = [[(float) $start['lat'], (float) $start['lng']]];
    foreach ($sortedUnits as $u) $pts[] = [(float) $u['lat'], (float) $u['lng']];
    $matrix = rp_providerMatrix($pts, 'car');
    $depart = rp__departIso($date, $settings);
    $res = rp_allocate($sortedUnits, $riders, [(float) $start['lat'], (float) $start['lng']], ['mode' => $mode, 'settings' => $settings, 'matrix' => $matrix, 'depart' => $depart]);
    foreach ($hold['unplaceable'] as $u) $res['unallocated'][] = ['id' => $u['id'], 'reason' => $u['reason']];

    $proposal = $res + ['units' => [], 'vehicles' => [], 'base' => [], 'depart' => $depart, 'date' => $date, 'undo' => null];
    foreach ($units as $u) $proposal['units'][$u['id']] = rp__unitLite($u);
    foreach ($planners as $id => $p) { $proposal['vehicles'][$id] = array_diff_key($p['vehicle'], ['files' => 1, 'docs' => 1, 'docs_json' => 1]); $proposal['base'][$id] = $p['used']; $proposal['zones'][$id] = $p['zone']; $proposal['status'][$id] = $p['status']; }
    $params = ['date' => $date, 'mode' => $mode, 'start_id' => $start['id'], 'lock' => $lock];

    $db = rp_db();
    $own = !$db->inTransaction();
    if ($own) $db->beginTransaction();
    try {
        rp_q("UPDATE cdb_rp_plans SET status = 'discarded' WHERE status = 'draft' AND plan_date = ? AND agency_id = ?", [$date, $agency]);
        rp_q('INSERT INTO cdb_rp_plans (plan_date, agency_id, status, start_json, params_json, proposal_json, created_by, created_at) VALUES (?,?,?,?,?,?,?,?)',
            [$date, $agency, 'draft', json_encode($start), json_encode($params), json_encode($proposal), $actor, rp_now()]);
        $planId = (int) $db->lastInsertId();
        // Two dispatchers proposing at the same moment: only the newest draft of the day stays.
        rp_q("UPDATE cdb_rp_plans SET status = 'discarded' WHERE status = 'draft' AND plan_date = ? AND agency_id = ? AND id < ?", [$date, $agency, $planId]);
        if ($own) $db->commit();
    } catch (Throwable $e) {
        if ($own && $db->inTransaction()) $db->rollBack();
        throw $e;
    }
    return rp__planView($planId, $proposal, $planners, $start, $hold);
}

/** API view of a draft plan: riders (with names/vehicles) + unallocated + KPIs. */
function rp__planView(int $planId, array $proposal, array $planners, array $start, ?array $hold = null): array
{
    $riders = [];
    foreach ($proposal['riders'] as $id => $r) {
        $p = $planners[$id] ?? null;
        $riders[$id] = $r + ['id' => (int) $id, 'name' => $p['name'] ?? ('Rider #' . $id), 'status' => $p['status'] ?? ($proposal['status'][$id] ?? 'available'), 'vehicle' => $p['vehicle'] ?? ($proposal['vehicles'][$id] ?? []), 'used' => $p['used'] ?? ($proposal['base'][$id] ?? [])];
    }
    $allocated = array_sum(array_map(fn($r) => count($r['units']) - count($r['fixed'] ?? []), $proposal['riders']));
    $usedR = array_filter($proposal['riders'], fn($r) => $r['units']);
    $kpis = [
        'units' => count($proposal['units'] ?? []) + count(array_filter($proposal['unallocated'], fn($u) => !isset($proposal['units'][$u['id']]))),
        'allocated' => array_sum(array_map(fn($r) => count($r['units']), $proposal['riders'])),
        'new_allocated' => $allocated,
        'unallocated' => count($proposal['unallocated']),
        'riders_used' => count($usedR),
        'total_km' => round(array_sum(array_map(fn($r) => (int) ($r['route']['total_m'] ?? 0), $usedR)) / 1000, 1),
        'avg_fill' => $usedR ? round(array_sum(array_map(fn($r) => (float) $r['fill']['max'], $usedR)) / count($usedR), 1) : 0,
        'provider' => $proposal['score']['provider'] ?? 'estimate',
    ];
    return ['plan_id' => $planId, 'riders' => $riders, 'unallocated' => array_values($proposal['unallocated']), 'kpis' => $kpis, 'start' => $start, 'score' => $proposal['score'] ?? [], 'depart' => $proposal['depart'] ?? null, 'date' => $proposal['date'] ?? null];
}

function rp__loadDraft(int $planId, bool $lock = false): array
{
    $row = rp_one('SELECT * FROM cdb_rp_plans WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''), [$planId]);
    if (!$row) throw new DomainException('Plan not found');
    $agency = function_exists('dl_agency') ? dl_agency() : 0;
    if ($agency && (int) $row['agency_id'] !== $agency) throw new DomainException('Plan not found');
    if ($row['status'] !== 'draft') throw new DomainException('This plan is already ' . $row['status'] . '. Run Auto-allocate again.');
    $row['proposal'] = json_decode((string) $row['proposal_json'], true) ?: [];
    $row['params'] = json_decode((string) $row['params_json'], true) ?: [];
    $row['start'] = json_decode((string) $row['start_json'], true) ?: rp_startPlace();
    return $row;
}

function rp__saveDraft(int $planId, array $proposal, array $params): void
{
    rp_q("UPDATE cdb_rp_plans SET proposal_json = ?, params_json = ? WHERE id = ? AND status = 'draft'", [json_encode($proposal), json_encode($params), $planId]);
}

/** Load / fill / route of one rider's set inside a draft. */
function rp__planRider(array $proposal, int $riderId, array $unitIds, array $start, bool $locked): array
{
    $settings = rp_settings();
    $v = $proposal['vehicles'][$riderId] ?? rp_vehicle($riderId);
    $load = ($proposal['base'][$riderId] ?? []) + ['kg' => 0, 'l' => 0, 'n' => 0];
    $stops = [];
    foreach ($unitIds as $uid) {
        $u = $proposal['units'][$uid] ?? null;
        if (!$u) continue;
        $load['kg'] += $u['kg']; $load['l'] += $u['l']; $load['n'] += $u['n'];
        $stops[] = ['id' => (int) $uid, 'lat' => $u['lat'], 'lng' => $u['lng'], 'window_start' => $u['window_start'], 'window_end' => $u['window_end'], 'priority' => $u['priority'], 'service_s' => rp_serviceS($u, $settings)];
    }
    $route = null;
    if ($stops) {
        $pts = [[(float) $start['lat'], (float) $start['lng']]];
        foreach ($stops as $s) $pts[] = [(float) $s['lat'], (float) $s['lng']];
        $route = rp_optimiseRoute([(float) $start['lat'], (float) $start['lng']], $stops, rp_providerMatrix($pts, $v['vehicle_type']), (string) ($proposal['depart'] ?? date('c')), ['settings' => $settings, 'vehicle_type' => $v['vehicle_type']]);
    }
    return ['units' => array_values(array_map('intval', $unitIds)), 'fixed' => $proposal['riders'][$riderId]['fixed'] ?? [], 'locked' => $locked,
        'load' => ['kg' => round($load['kg'], 2), 'l' => round($load['l'], 2), 'n' => (int) $load['n']], 'fill' => rp_fill($load, $v), 'route' => $route];
}

/** Capacity overrun of $load + $unit on $vehicle: [] when it fits, else ['weight' => [new, max], ...]. */
function rp__overCapacity(array $load, array $unit, array $v): array
{
    $over = [];
    foreach (['kg' => ['weight', 'max_kg', ' kg'], 'l' => ['volume', 'max_l', ' L'], 'n' => ['parcels', 'max_parcels', '']] as $k => [$label, $max, $unitLabel]) {
        $new = (float) ($load[$k] ?? 0) + (float) ($unit[$k] ?? 0);
        if ($new > (float) ($v[$max] ?? 0) + 1e-6) $over[$label] = ['new' => round($new, 1), 'max' => (float) $v[$max], 'unit' => $unitLabel, 'pct' => (float) $v[$max] > 0 ? round(100 * $new / (float) $v[$max]) : 999];
    }
    return $over;
}

function rp__overText(array $over): string
{
    return implode(', ', array_map(fn($k, $o) => $k . ' ' . rtrim(rtrim(number_format($o['new'], 1, '.', ''), '0'), '.') . '/' . rtrim(rtrim(number_format($o['max'], 1, '.', ''), '0'), '.') . $o['unit'], array_keys($over), $over));
}

/** Most a dispatcher may overload a vehicle by hand (per dimension). */
const RP_FORCE_MAX_PCT = 110;

/**
 * Move one stop (request) to another rider in a draft, or back to the hold ($to = null).
 * Handling / size / agency mismatches and locked riders are hard blocks; a capacity overrun up to
 * RP_FORCE_MAX_PCT and an off-duty or on-the-way rider can be overridden with $force.
 * Returns blocked:true with 'warning' and 'can_force' instead of moving.
 */
function rp_moveUnit(int $planId, int $requestId, ?int $toDriverId, int $actor, bool $force = false): array
{
    $db = rp_db();
    $own = !$db->inTransaction();
    if ($own) $db->beginTransaction();
    try {
        $out = rp__moveUnitTx($planId, $requestId, $toDriverId, $force);
        if ($own) $db->commit();
        return $out;
    } catch (Throwable $e) {
        if ($own && $db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

function rp__moveUnitTx(int $planId, int $requestId, ?int $toDriverId, bool $force): array
{
    $plan = rp__loadDraft($planId, true);
    $p = $plan['proposal'];
    $blocked = fn(string $msg, bool $canForce = false, string $code = 'blocked') => ['plan_id' => $planId, 'blocked' => true, 'can_force' => $canForce, 'code' => $code, 'warning' => $msg];
    if (!isset($p['units'][$requestId])) {
        // A request approved after the proposal: add it to the snapshot from the hold.
        foreach (rp_cargoHold((int) $plan['agency_id'])['units'] as $u) if ($u['id'] === $requestId) $p['units'][$requestId] = rp__unitLite($u);
        if (!isset($p['units'][$requestId])) throw new DomainException('This delivery is no longer waiting in the hold');
    }
    $from = null;
    foreach ($p['riders'] as $rid => $r) if (in_array($requestId, array_map('intval', $r['units']), true)) $from = (int) $rid;
    if ($from === $toDriverId) return ['plan_id' => $planId, 'unchanged' => true, 'blocked' => false, 'warning' => null];
    $lock = array_map('intval', (array) ($plan['params']['lock'] ?? []));
    if ($from !== null && in_array($from, $lock, true)) return $blocked('This rider is locked. Unlock the rider first to change their stops.', false, 'locked');
    $unit = $p['units'][$requestId];
    $warning = null;
    if ($toDriverId !== null) {
        if (in_array($toDriverId, $lock, true)) return $blocked('This rider is locked. Unlock the rider first to change their stops.', false, 'locked');
        if (!isset($p['vehicles'][$toDriverId])) {
            $riders = rp_ridersForPlanning((string) $plan['plan_date'], (int) $plan['agency_id']);
            if (!isset($riders[$toDriverId])) throw new DomainException('Choose an active rider');
            $p['vehicles'][$toDriverId] = array_diff_key($riders[$toDriverId]['vehicle'], ['files' => 1, 'docs' => 1, 'docs_json' => 1]);
            $p['base'][$toDriverId] = $riders[$toDriverId]['used'];
            $p['status'][$toDriverId] = $riders[$toDriverId]['status'];
        }
        $v = $p['vehicles'][$toDriverId];
        if (empty($v['active']) && isset($v['active'])) return $blocked('This vehicle is out of service.', false, 'inactive');
        $why = rp_vehicleEligible($v, $unit);
        if ($why && !preg_match('/^too (heavy|bulky|many)/', $why)) return $blocked('Cannot move: ' . $why . '. Choose a rider whose vehicle can take it.', false, 'ineligible');
        $notes = [];
        $st = $p['status'][$toDriverId] ?? 'available';
        if (in_array($st, ['off', 'suspended'], true)) $notes[] = 'the rider is ' . ($st === 'off' ? 'off duty' : 'suspended');
        if (in_array($st, ['onway', 'waiting'], true)) $notes[] = 'the rider is already on the way and must come back to load it';
        $cur = $p['riders'][$toDriverId]['load'] ?? (($p['base'][$toDriverId] ?? []) + ['kg' => 0, 'l' => 0, 'n' => 0]);
        $over = rp__overCapacity($cur, $unit, $v);
        if ($over) {
            $worst = max(array_column($over, 'pct'));
            if ($worst > RP_FORCE_MAX_PCT) return $blocked('No room: ' . rp__overText($over) . '. That is more than ' . RP_FORCE_MAX_PCT . '% of the vehicle.', false, 'capacity');
            $notes[] = 'over capacity (' . rp__overText($over) . ')';
        }
        if ($notes) {
            if (!$force) return $blocked(ucfirst(implode('; ', $notes)) . '.', true, $over ? 'capacity' : 'status');
            $warning = 'Moved although ' . implode('; ', $notes) . '.';
        }
    }
    $p['undo'] = ['riders' => $p['riders'], 'unallocated' => $p['unallocated']];
    $out = ['plan_id' => $planId, 'blocked' => false, 'warning' => $warning, 'from' => null, 'to' => null];
    if ($from !== null) {
        $ids = array_values(array_diff(array_map('intval', $p['riders'][$from]['units']), [$requestId]));
        $p['riders'][$from] = rp__planRider($p, $from, $ids, $plan['start'], !empty($p['riders'][$from]['locked']));
        $out['from'] = $p['riders'][$from] + ['id' => $from];
    }
    $p['unallocated'] = array_values(array_filter($p['unallocated'], fn($u) => (int) $u['id'] !== $requestId));
    if ($toDriverId !== null) {
        $ids = array_merge(array_map('intval', $p['riders'][$toDriverId]['units'] ?? []), [$requestId]);
        $p['riders'][$toDriverId] = rp__planRider($p, $toDriverId, $ids, $plan['start'], !empty($p['riders'][$toDriverId]['locked']));
        $out['to'] = $p['riders'][$toDriverId] + ['id' => $toDriverId];
    }
    // Moved to the hold by hand: it waits there as an ordinary pending delivery (not "unplaceable").
    rp__saveDraft($planId, $p, $plan['params']);
    return $out;
}

function rp_lockRider(int $planId, int $driverId, bool $locked, int $actor): array
{
    $db = rp_db();
    $own = !$db->inTransaction();
    if ($own) $db->beginTransaction();
    try {
        $plan = rp__loadDraft($planId, true);
        $p = $plan['proposal']; $params = $plan['params'];
        $lock = array_map('intval', (array) ($params['lock'] ?? []));
        $lock = $locked ? array_values(array_unique(array_merge($lock, [$driverId]))) : array_values(array_diff($lock, [$driverId]));
        $params['lock'] = $lock;
        if (isset($p['riders'][$driverId])) $p['riders'][$driverId]['locked'] = $locked;
        rp__saveDraft($planId, $p, $params);
        if ($own) $db->commit();
    } catch (Throwable $e) {
        if ($own && $db->inTransaction()) $db->rollBack();
        throw $e;
    }
    return ['plan_id' => $planId, 'driver_id' => $driverId, 'locked' => $locked, 'lock' => $lock];
}

/**
 * Re-check a draft against the live data: units no longer pending leave the plan, each rider's
 * base load is today's real assigned load, fills are recomputed. Returns the rebased proposal
 * plus 'issues' (stale units, riders over capacity or with ineligible stops). Does not save.
 */
function rp_rebaseDraft(array $plan): array
{
    $p = $plan['proposal'];
    $hold = rp_cargoHold((int) $plan['agency_id']);
    $pending = [];
    foreach ($hold['units'] as $u) $pending[(int) $u['id']] = $u;
    $bad = [];
    foreach ($hold['unplaceable'] as $u) $bad[(int) $u['id']] = $u['reason'];
    $planners = rp_ridersForPlanning((string) $plan['plan_date'], (int) $plan['agency_id']);
    $issues = ['stale' => [], 'over' => [], 'ineligible' => [], 'unavailable' => []];
    foreach ($p['riders'] ?? [] as $rid => $r) {
        $rid = (int) $rid;
        $keep = [];
        foreach (array_map('intval', $r['units']) as $uid) {
            if (isset($pending[$uid])) { $keep[] = $uid; $p['units'][$uid] = rp__unitLite($pending[$uid]); }
            else { $issues['stale'][] = $uid; if (isset($bad[$uid])) $p['unallocated'][] = ['id' => $uid, 'reason' => $bad[$uid]]; unset($p['units'][$uid]); }
        }
        if (isset($planners[$rid])) {
            $p['base'][$rid] = $planners[$rid]['used'];
            $p['vehicles'][$rid] = array_diff_key($planners[$rid]['vehicle'], ['files' => 1, 'docs' => 1, 'docs_json' => 1]);
            $p['status'][$rid] = $planners[$rid]['status'];
        }
        $changed = $keep !== array_map('intval', $r['units']) || ($p['base'][$rid] ?? null) != ($plan['proposal']['base'][$rid] ?? null);
        $r2 = $changed ? rp__planRider($p, $rid, $keep, $plan['start'], !empty($r['locked'])) : $r;
        $r2['fill'] = rp_fill($r2['load'], $p['vehicles'][$rid] ?? []);
        $p['riders'][$rid] = $r2;
        // A rider deactivated, moved to another agency or suspended since the proposal cannot be assigned.
        $gone = !isset($planners[$rid]) ? 'no longer an active rider here' : ($planners[$rid]['status'] === 'suspended' ? 'suspended' : (empty($p['vehicles'][$rid]['active']) && isset($p['vehicles'][$rid]['active']) ? 'out of service (vehicle)' : null));
        if ($keep && $gone) $issues['unavailable'][] = ['driver_id' => $rid, 'name' => $planners[$rid]['name'] ?? ('Rider #' . $rid), 'reason' => $gone, 'stops' => count($keep)];
        if ($keep && ($r2['fill']['kg'] > 100.05 || $r2['fill']['l'] > 100.05 || $r2['fill']['n'] > 100.05)) $issues['over'][$rid] = $r2['fill'];
        foreach ($keep as $uid) {
            $why = rp_vehicleEligible($p['vehicles'][$rid] ?? [], $p['units'][$uid]);
            if ($why && !preg_match('/^too (heavy|bulky|many)/', $why)) $issues['ineligible'][] = ['driver_id' => $rid, 'request_id' => $uid, 'reason' => $why];
        }
    }
    $p['unallocated'] = array_values(array_filter($p['unallocated'] ?? [], fn($u) => isset($pending[(int) $u['id']]) || isset($bad[(int) $u['id']])));
    $issues['stale'] = array_values(array_unique($issues['stale']));
    $issues['out_of_date'] = (bool) ($issues['stale'] || $issues['over'] || $issues['ineligible'] || $issues['unavailable']);
    return ['proposal' => $p, 'issues' => $issues, 'planners' => $planners, 'hold' => $hold];
}

function rp_discardPlan(int $planId, int $actor): void
{
    rp__loadDraft($planId);
    rp_q("UPDATE cdb_rp_plans SET status = 'discarded', confirmed_by = ?, confirmed_at = ? WHERE id = ? AND status = 'draft'", [$actor, rp_now(), $planId]);
}

/**
 * Confirm a draft: assign every request through DeliveryService (each in its own transaction),
 * then write the riders' routes, stops and loading lists.
 */
function rp_confirmPlan(int $planId, int $actor, int $agency = 0, bool $ackOver = false): array
{
    $plan = rp__loadDraft($planId);
    if ((string) $plan['plan_date'] < date('Y-m-d')) throw new DomainException('This plan was made for ' . $plan['plan_date'] . '. Run Auto-allocate again for today or a later day.');
    // Re-check against what changed since the proposal (assignments elsewhere, vehicle edits).
    $rb = rp_rebaseDraft($plan);
    $iss = $rb['issues'];
    if ($iss['stale']) {
        // Deliveries assigned elsewhere meanwhile leave the plan; the planner sees the new loads first.
        rp__saveDraft($planId, $rb['proposal'], $plan['params']);
        throw new DomainException(count($iss['stale']) . ' deliver' . (count($iss['stale']) === 1 ? 'y was' : 'ies were') . ' assigned elsewhere, cancelled or no longer ready since this plan was made. The plan was updated: check it and confirm again.');
    }
    if ($iss['unavailable']) {
        $f = $iss['unavailable'][0];
        throw new DomainException($f['name'] . ' is ' . $f['reason'] . ' and cannot take deliveries. Move their ' . $f['stops'] . ' stop' . ($f['stops'] === 1 ? '' : 's') . ' or run Auto-allocate again.');
    }
    if ($iss['ineligible']) {
        $f = $iss['ineligible'][0];
        throw new DomainException('Delivery #' . $f['request_id'] . ' cannot go with this rider: ' . $f['reason'] . '. Move it first.');
    }
    foreach ($iss['over'] as $rid => $fill) {
        if (max($fill['kg'], $fill['l'], $fill['n']) > RP_FORCE_MAX_PCT + 0.05) throw new DomainException(($rb['planners'][$rid]['name'] ?? 'A rider') . ' is at ' . round(max($fill['kg'], $fill['l'], $fill['n'])) . '% of the vehicle. Move some stops first.');
        if (!$ackOver) throw new DomainException(($rb['planners'][$rid]['name'] ?? 'A rider') . ' is over capacity. Confirm that you accept the overload.');
    }
    if (!array_filter($rb['proposal']['riders'] ?? [], fn($r) => !empty($r['units']))) throw new DomainException('This plan has no stops to assign. Run Auto-allocate again.');
    $plan['proposal'] = $rb['proposal'];
    $claim = rp_q("UPDATE cdb_rp_plans SET status = 'confirming' WHERE id = ? AND status = 'draft'", [$planId]);
    if ($claim->rowCount() !== 1) throw new DomainException('This plan is being confirmed by someone else');
    $p = $plan['proposal'];
    $svc = rp_service();
    $assigned = 0; $errors = []; $routes = [];
    try {
        foreach ($p['riders'] as $rid => $r) {
            $rid = (int) $rid;
            $ok = [];
            foreach (array_map('intval', $r['units']) as $reqId) {
                try {
                    $cur = rp_one('SELECT state, driver_id FROM cdb_delivery_requests WHERE id = ?', [$reqId]);
                    if ($cur && $cur['state'] === 'assigned' && (int) $cur['driver_id'] === $rid) { $ok[] = $reqId; continue; }
                    $svc->transition($reqId, $actor, 'manager', $agency, 'assign', ['driver_id' => $rid, 'note' => 'Assigned by the route planner']);
                    $ok[] = $reqId; $assigned++;
                } catch (Throwable $e) {
                    $errors[] = ['request_id' => $reqId, 'message' => $e instanceof DomainException ? $e->getMessage() : 'Could not assign'];
                    if (!$e instanceof DomainException) error_log('route planner assign failed: ' . $e->getMessage());
                }
            }
            if ($ok) $routes[] = rp__writeRoute($planId, $rid, (string) $plan['plan_date'], $plan['start'], $ok, !empty($r['locked']), $actor);
        }
        rp_q("UPDATE cdb_rp_plans SET status = 'confirmed', confirmed_by = ?, confirmed_at = ? WHERE id = ?", [$actor, rp_now(), $planId]);
    } catch (Throwable $e) {
        rp_q("UPDATE cdb_rp_plans SET status = 'draft' WHERE id = ? AND status = 'confirming'", [$planId]);
        throw $e;
    }
    return ['assigned' => $assigned, 'errors' => $errors, 'routes' => $routes, 'plan_id' => $planId];
}

/** Create (or extend the not-yet-started) route of a rider with stops and load rows. */
function rp__writeRoute(int $planId, int $driverId, string $date, array $start, array $requestIds, bool $locked, int $actor): int
{
    // One open route per rider and day: new stops join the route under way (they load on the
    // rider's next pass at the office) or the one not started yet.
    $existing = rp_one("SELECT * FROM cdb_rp_routes WHERE driver_id = ? AND route_date = ? AND status IN ('onroute','loading','planned') ORDER BY FIELD(status, 'onroute', 'loading', 'planned'), id DESC", [$driverId, $date]);
    $now = rp_now();
    if ($existing) {
        $routeId = (int) $existing['id'];
    } else {
        rp_q('INSERT INTO cdb_rp_routes (plan_id, driver_id, route_date, locked, status, start_json, provider, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)', [$planId, $driverId, $date, $locked ? 1 : 0, 'planned', json_encode($start), 'estimate', $now, $now]);
        $routeId = (int) rp_db()->lastInsertId();
    }
    rp__addStops($routeId, $requestIds);
    rp__recompute($routeId, true);
    return $routeId;
}

function rp__addStops(int $routeId, array $requestIds): void
{
    if (!$requestIds) return;
    $reqs = rp_all('SELECT * FROM cdb_delivery_requests WHERE id IN (' . rp_in($requestIds) . ')', $requestIds);
    $units = rp__unitsFor($reqs);
    $seq = (int) rp_val('SELECT COALESCE(MAX(seq), 0) FROM cdb_rp_stops WHERE route_id = ?', [$routeId]);
    foreach (array_merge($units['units'], $units['unplaceable']) as $u) {
        if (rp_val("SELECT id FROM cdb_rp_stops WHERE route_id = ? AND request_id = ? AND status <> 'removed'", [$routeId, $u['id']])) continue;
        rp_q('INSERT INTO cdb_rp_stops (route_id, request_id, seq, lat, lng, address, contact_name, contact_phone, window_start, window_end, priority, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [$routeId, $u['id'], ++$seq, (float) $u['lat'], (float) $u['lng'], mb_substr($u['address'], 0, 700), mb_substr($u['contact_name'], 0, 150), mb_substr($u['contact_phone'], 0, 40), $u['window_start'] ? $u['window_start'] . ':00' : null, $u['window_end'] ? $u['window_end'] . ':00' : null, (int) $u['priority'], 'pending']);
        $stopId = (int) rp_db()->lastInsertId();
        foreach ($u['parcels'] as $pc) {
            rp_q("INSERT INTO cdb_rp_load (route_id, module, order_id, request_id, stop_id, tracking, load_state, outcome) VALUES (?,?,?,?,?,?, 'pending', 'onboard') ON DUPLICATE KEY UPDATE request_id = VALUES(request_id), stop_id = VALUES(stop_id), load_state = 'pending', outcome = 'onboard', outcome_at = NULL",
                [$routeId, $pc['module'], $pc['order_id'], $u['id'], $stopId, mb_substr($pc['tracking'], 0, 400)]);
        }
    }
}

/**
 * Recompute a route: finished stops keep their order; the arrived stop stays next; pending stops
 * are re-ordered ($reorder) or only re-timed. Starts from $fromPos, else the last ping while on
 * route, else the start place. Writes seq / legs / ETAs / totals / path and bumps the version.
 */
function rp__recompute(int $routeId, bool $reorder, ?array $fromPos = null): array
{
    $route = rp_one('SELECT * FROM cdb_rp_routes WHERE id = ?', [$routeId]);
    if (!$route) throw new DomainException('Route not found');
    $settings = rp_settings();
    $v = rp_vehicle((int) $route['driver_id']);
    $start = json_decode((string) $route['start_json'], true) ?: rp_startPlace();
    $stops = rp_all("SELECT * FROM cdb_rp_stops WHERE route_id = ? AND status <> 'removed' ORDER BY seq, id", [$routeId]);
    $counts = [];
    foreach (rp_all('SELECT stop_id, COUNT(*) n FROM cdb_rp_load WHERE route_id = ? GROUP BY stop_id', [$routeId]) as $c) $counts[(int) $c['stop_id']] = (int) $c['n'];
    $done = array_values(array_filter($stops, fn($s) => in_array($s['status'], RP_DONE_STOP, true)));
    usort($done, fn($a, $b) => [(string) $a['done_at'], (int) $a['seq']] <=> [(string) $b['done_at'], (int) $b['seq']]);
    $arrived = array_values(array_filter($stops, fn($s) => $s['status'] === 'arrived'));
    $pending = array_values(array_filter($stops, fn($s) => $s['status'] === 'pending'));
    $active = array_merge($arrived, $pending);
    $onroute = $route['status'] === 'onroute';
    $from = null;
    if ($fromPos && isset($fromPos[0], $fromPos[1])) $from = [(float) $fromPos[0], (float) $fromPos[1]];
    if (!$from && $onroute) {
        $ping = rp_one('SELECT lat, lng, recorded_at FROM cdb_rp_pings WHERE driver_id = ? AND recorded_at >= ? ORDER BY recorded_at DESC, id DESC', [(int) $route['driver_id'], date('Y-m-d H:i:s', time() - 1800)]);
        if ($ping) $from = [(float) $ping['lat'], (float) $ping['lng']];
        elseif ($done) $from = [(float) end($done)['lat'], (float) end($done)['lng']];
    }
    $from = $from ?: [(float) $start['lat'], (float) $start['lng']];
    $depart = $onroute ? date('c') : rp__departIso((string) $route['route_date'], $settings);
    $result = ['legs' => [], 'total_m' => 0, 'total_s' => 0, 'late' => 0, 'provider' => 'estimate'];
    $order = [];
    if ($active) {
        $sd = [];
        foreach ($active as $s) $sd[] = ['id' => (int) $s['id'], 'lat' => (float) $s['lat'], 'lng' => (float) $s['lng'], 'window_start' => $s['window_start'] ? substr($s['window_start'], 0, 5) : null, 'window_end' => $s['window_end'] ? substr($s['window_end'], 0, 5) : null, 'priority' => (int) $s['priority'], 'service_s' => rp_serviceS(['n' => $counts[(int) $s['id']] ?? 1], $settings)];
        $pts = [$from];
        foreach ($sd as $s) $pts[] = [$s['lat'], $s['lng']];
        $matrix = rp_providerMatrix($pts, $v['vehicle_type']);
        if ($reorder) {
            $result = rp_optimiseRoute($from, $sd, $matrix, $depart, ['settings' => $settings, 'vehicle_type' => $v['vehicle_type'], 'fixed' => array_map(fn($s) => (int) $s['id'], $arrived)]);
            $order = $result['order'];
        } else {
            $result = rp_etas($from, $sd, $matrix, $depart, $settings + ['vehicle_type' => $v['vehicle_type']]);
            $order = array_map(fn($s) => $s['id'], $sd);
        }
    }
    $seq = 0; $path = [[(float) $start['lat'], (float) $start['lng']]]; $doneM = 0; $doneS = 0;
    foreach ($done as $s) {
        rp_q('UPDATE cdb_rp_stops SET seq = ? WHERE id = ?', [++$seq, (int) $s['id']]);
        $path[] = [(float) $s['lat'], (float) $s['lng']];
        $doneM += (int) $s['leg_m']; $doneS += (int) $s['leg_s'];
    }
    if ($onroute && $active) $path[] = $from;
    $legById = [];
    foreach ($result['legs'] as $l) $legById[(int) $l['id']] = $l;
    $byId = [];
    foreach ($active as $s) $byId[(int) $s['id']] = $s;
    foreach ($order as $sid) {
        $l = $legById[(int) $sid] ?? ['m' => 0, 's' => 0, 'eta' => null];
        rp_q('UPDATE cdb_rp_stops SET seq = ?, leg_m = ?, leg_s = ?, eta = ? WHERE id = ?', [++$seq, (int) $l['m'], (int) $l['s'], $l['eta'] ? date('Y-m-d H:i:s', strtotime($l['eta'])) : null, (int) $sid]);
        $path[] = [(float) $byId[(int) $sid]['lat'], (float) $byId[(int) $sid]['lng']];
    }
    $load = rp_one("SELECT COALESCE(SUM(1),0) n FROM cdb_rp_load WHERE route_id = ? AND outcome = 'onboard' AND load_state <> 'missing'", [$routeId]);
    $inv = rp_inventory($routeId);
    rp_q('UPDATE cdb_rp_routes SET provider = ?, total_m = ?, total_s = ?, load_kg = ?, load_l = ?, load_n = ?, version = version + 1, polyline = ?, updated_at = ? WHERE id = ?',
        [$result['provider'] ?? 'estimate', $doneM + (int) $result['total_m'], $doneS + (int) $result['total_s'], $inv['kg'], $inv['l'], (int) ($load['n'] ?? 0), json_encode($path), rp_now(), $routeId]);
    return $result;
}

/* ------------------------------------------------------------------ routes & inventory */

function rp__navUrls(float $lat, float $lng): array
{
    $ll = rtrim(rtrim(sprintf('%.6F', $lat), '0'), '.') . ',' . rtrim(rtrim(sprintf('%.6F', $lng), '0'), '.');
    return ['google' => 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($ll) . '&travelmode=driving', 'apple' => 'https://maps.apple.com/?daddr=' . rawurlencode($ll) . '&dirflg=d'];
}

/** Full route view. $role 'rider' must own the route; 'manager' is agency-scoped by the caller. */
function rp_route(int $routeId, int $viewerId, string $role): array
{
    rp_ensureSchema();
    $route = rp_one('SELECT * FROM cdb_rp_routes WHERE id = ?', [$routeId]);
    if (!$route || ($role === 'rider' && (int) $route['driver_id'] !== $viewerId) || !in_array($role, ['rider', 'manager'], true)) throw new DomainException('Route not found');
    if ($route['status'] !== 'done' && rp_reconcileStops($routeId)) $route = rp_one('SELECT * FROM cdb_rp_routes WHERE id = ?', [$routeId]);
    $driverId = (int) $route['driver_id'];
    $settings = rp_settings();
    $rider = rp_one('SELECT id, fname, lname, username, phone, agency_id, zone, rider_status FROM cdb_users WHERE id = ?', [$driverId]) ?? ['id' => $driverId];
    $rider['name'] = trim(($rider['fname'] ?? '') . ' ' . ($rider['lname'] ?? '')) ?: ($rider['username'] ?? 'Rider');
    $vehicle = rp_vehicle($driverId);
    unset($vehicle['files']);
    $start = json_decode((string) $route['start_json'], true) ?: rp_startPlace();
    $stopRows = rp_all('SELECT * FROM cdb_rp_stops WHERE route_id = ? ORDER BY seq, id', [$routeId]);
    $loads = rp_all('SELECT * FROM cdb_rp_load WHERE route_id = ? ORDER BY stop_id, order_id', [$routeId]);
    $details = rp_parcelDetailsMany(array_map(fn($l) => [$l['module'], (int) $l['order_id']], $loads));
    $reqIds = array_values(array_unique(array_map(fn($s) => (int) $s['request_id'], $stopRows)));
    $reqs = [];
    if ($reqIds) foreach (rp_all('SELECT id, state, instructions, customer_id, module, amount_cents, currency, fee_collected, receiver_name, completed_at, agency_id FROM cdb_delivery_requests WHERE id IN (' . rp_in($reqIds) . ')', $reqIds) as $r) $reqs[(int) $r['id']] = $r;
    $byStop = [];
    foreach ($loads as $l) {
        $d = $details[$l['module'] . ':' . (int) $l['order_id']] ?? ['tracking' => $l['tracking']];
        $byStop[(int) $l['stop_id']][] = $d + ['load_state' => $l['load_state'], 'outcome' => $l['outcome'], 'loaded_at' => $l['loaded_at'], 'request_id' => (int) $l['request_id'], 'stop_id' => (int) $l['stop_id']];
    }
    $stops = [];
    foreach ($stopRows as $s) {
        $parcels = $byStop[(int) $s['id']] ?? [];
        $req = $reqs[(int) $s['request_id']] ?? [];
        $handling = [];
        foreach ($parcels as $pc) foreach ($pc['handling'] ?? [] as $h) $handling[$h] = true;
        $stops[] = [
            'id' => (int) $s['id'], 'stop_id' => (int) $s['id'], 'request_id' => (int) $s['request_id'], 'seq' => (int) $s['seq'],
            'lat' => (float) $s['lat'], 'lng' => (float) $s['lng'], 'address' => $s['address'], 'contact_name' => $s['contact_name'], 'contact_phone' => $s['contact_phone'],
            'label' => $s['contact_name'] ?: rp__area($s['address']), 'area' => rp__area($s['address']),
            'window_start' => $s['window_start'] ? substr($s['window_start'], 0, 5) : null, 'window_end' => $s['window_end'] ? substr($s['window_end'], 0, 5) : null,
            'priority' => (int) $s['priority'], 'leg_m' => (int) $s['leg_m'], 'leg_s' => (int) $s['leg_s'],
            'eta' => $s['eta'] ? date('c', strtotime($s['eta'])) : null, 'status' => $s['status'], 'arrived_at' => $s['arrived_at'], 'done_at' => $s['done_at'], 'fail_reason' => $s['fail_reason'],
            'late' => $s['eta'] && $s['window_end'] && strtotime($s['eta']) > strtotime(date('Y-m-d', strtotime((string) $route['route_date'])) . ' ' . $s['window_end']),
            'request_state' => $req['state'] ?? null, 'instructions' => $req['instructions'] ?? '', 'module' => $req['module'] ?? ($parcels[0]['module'] ?? 'air'),
            'kg' => round(array_sum(array_map(fn($p) => (float) ($p['kg'] ?? 0), $parcels)), 2), 'l' => round(array_sum(array_map(fn($p) => (float) ($p['volume_l'] ?? 0), $parcels)), 2), 'n' => count($parcels),
            'handling' => array_keys($handling), 'parcels' => $parcels, 'nav' => rp__navUrls((float) $s['lat'], (float) $s['lng']),
            'job_url' => 'delivery_job.php?id=' . (int) $s['request_id'],
        ];
    }
    $live = array_values(array_filter($stops, fn($s) => $s['status'] !== 'removed'));
    $ping = rp_one('SELECT lat, lng, accuracy_m, speed_mps, heading, battery, recorded_at FROM cdb_rp_pings WHERE driver_id = ? ORDER BY recorded_at DESC, id DESC', [$driverId]);
    $recent = rp_all('SELECT lat, lng, recorded_at FROM cdb_rp_pings WHERE driver_id = ? AND recorded_at >= ? ORDER BY recorded_at', [$driverId, date('Y-m-d H:i:s', time() - ((int) $settings['stuck_min'] + 2) * 60)]);
    $loadingSince = rp_val("SELECT started_at FROM cdb_rp_activity WHERE driver_id = ? AND state = 'loading' AND ended_at IS NULL ORDER BY id DESC", [$driverId]);
    $act = rp__openActivity($driverId);
    $alerts = rp_detectAlerts($route + ['rider_name' => $rider['name'], 'loading_since' => $loadingSince ?: $route['updated_at'], 'activity' => $act['state'] ?? null], $live, $ping, $recent, $settings, time());
    // Only a fresh position counts as "where the rider is"; an old one (yesterday, before the
    // shift) would put distances and the map marker in the wrong place.
    $fresh = $ping && strtotime($ping['recorded_at']) >= time() - rp__freshPingS($settings) && substr((string) $ping['recorded_at'], 0, 10) === date('Y-m-d');
    $riderPos = $fresh ? ['lat' => (float) $ping['lat'], 'lng' => (float) $ping['lng'], 'at' => $ping['recorded_at'], 'accuracy' => $ping['accuracy_m'] !== null ? (int) $ping['accuracy_m'] : null] : null;
    $open = array_values(array_filter($live, fn($s) => in_array($s['status'], ['pending', 'arrived'], true)));
    $from = $riderPos ? [$riderPos['lat'], $riderPos['lng']] : [(float) $start['lat'], (float) $start['lng']];
    $nearest = array_map(fn($s) => ['id' => $s['id'], 'seq' => $s['seq'], 'label' => $s['label'], 'area' => $s['area'], 'dist_m' => $s['dist_m']], rp_sortByDistanceFrom($from, $open));
    $path = json_decode((string) $route['polyline'], true);
    $doneN = count(array_filter($live, fn($s) => in_array($s['status'], RP_DONE_STOP, true)));
    return [
        'route' => ['id' => (int) $route['id'], 'plan_id' => (int) $route['plan_id'], 'driver_id' => $driverId, 'route_date' => $route['route_date'], 'status' => $route['status'], 'locked' => (bool) $route['locked'],
            'provider' => $route['provider'], 'total_m' => (int) $route['total_m'], 'total_s' => (int) $route['total_s'], 'load_kg' => (float) $route['load_kg'], 'load_l' => (float) $route['load_l'], 'load_n' => (int) $route['load_n'],
            'version' => (int) $route['version'], 'started_at' => $route['started_at'], 'finished_at' => $route['finished_at'], 'updated_at' => $route['updated_at']],
        'rider' => ['id' => $driverId, 'name' => $rider['name'], 'phone' => (string) ($rider['phone'] ?? ''), 'zone' => (string) ($rider['zone'] ?? '')],
        'vehicle' => $vehicle, 'start' => $start, 'stops' => $stops,
        'next_stop' => $open[0] ?? null,
        'progress' => ['done' => $doneN, 'total' => count($live), 'delivered' => count(array_filter($live, fn($s) => $s['status'] === 'delivered')), 'failed' => count(array_filter($live, fn($s) => in_array($s['status'], ['failed', 'returned'], true)))],
        'inventory' => rp_inventory($routeId),
        'legs' => array_map(fn($s) => ['stop_id' => $s['id'], 'm' => $s['leg_m'], 's' => $s['leg_s'], 'eta' => $s['eta']], $live),
        'provider' => $route['provider'], 'alerts' => $alerts, 'nearest' => $nearest, 'nearest_from' => $riderPos ? 'position' : 'start',
        'last_ping_at' => $ping['recorded_at'] ?? null, 'activity' => $act['state'] ?? null,
        'map' => ['depot' => ['lat' => (float) $start['lat'], 'lng' => (float) $start['lng'], 'label' => (string) ($start['label'] ?? 'Start')],
            'stops' => array_map(fn($s) => ['id' => $s['id'], 'seq' => $s['seq'], 'lat' => $s['lat'], 'lng' => $s['lng'], 'label' => $s['label'], 'status' => $s['status'], 'eta' => $s['eta']], $live),
            'path' => is_array($path) ? $path : null, 'rider_pos' => $riderPos],
        'settings' => ['ping_min_s' => (int) $settings['ping_min_s'], 'ping_min_m' => (int) $settings['ping_min_m'], 'late_min' => (int) $settings['late_min'], 'speed_kmh' => (float) ($settings['speeds'][$vehicle['vehicle_type']] ?? 20), 'road_factor' => (float) $settings['road_factor']],
        'generated_at' => date('c'),
    ];
}

/** Age (s) up to which the last ping is shown as the rider's live position. */
function rp__freshPingS(array $settings): int
{
    return max(30 * 60, (int) ($settings['offline_min'] ?? 10) * 3 * 60);
}

function rp_riderTodayRoute(int $driverId, ?string $date = null): ?array
{
    rp_ensureSchema();
    $row = rp__routeRow($driverId, $date ?: date('Y-m-d'));
    return $row ? rp_route((int) $row['id'], $driverId, 'rider') : null;
}

function rp__routeFor(int $routeId, int $actor, string $role, bool $lock = false): array
{
    $route = rp_one('SELECT * FROM cdb_rp_routes WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''), [$routeId]);
    if (!$route || ($role === 'rider' && (int) $route['driver_id'] !== $actor)) throw new DomainException('Route not found');
    if ($role === 'manager') {
        $agency = function_exists('dl_agency') ? dl_agency() : 0;
        if ($agency && (int) rp_val('SELECT agency_id FROM cdb_users WHERE id = ?', [(int) $route['driver_id']]) !== $agency) throw new DomainException('Route not found');
    }
    return $route;
}

function rp_reoptimise(int $routeId, int $actor, string $role, ?array $fromPos = null): array
{
    $route = rp__routeFor($routeId, $actor, $role);
    if ($route['status'] === 'done') throw new DomainException('This route is finished');
    if ($fromPos !== null && (!is_numeric($fromPos[0] ?? null) || !is_numeric($fromPos[1] ?? null) || abs((float) $fromPos[0]) > 90 || abs((float) $fromPos[1]) > 180)) $fromPos = null;
    $r = rp__recompute($routeId, true, $fromPos);
    $view = rp_route($routeId, $actor, $role);
    $view['improved_pct'] = $r['improved_pct'] ?? 0;
    return $view;
}

/**
 * Add a delivery to a confirmed route. Vehicle eligibility (handling, size, agency) and an
 * out-of-service vehicle are hard blocks; capacity up to RP_FORCE_MAX_PCT and an off-duty rider
 * need $force. A blocked add returns ['blocked' => true, 'warning', 'can_force'].
 */
function rp_addStop(int $routeId, int $requestId, int $actor, bool $force = false): array
{
    $route = rp__routeFor($routeId, $actor, 'manager');
    if ($route['status'] === 'done') throw new DomainException('This route is finished');
    $req = rp_one('SELECT * FROM cdb_delivery_requests WHERE id = ?', [$requestId]);
    if (!$req) throw new DomainException('Delivery request not found');
    $driver = (int) $route['driver_id'];
    $already = $req['state'] === 'assigned' && (int) $req['driver_id'] === $driver;
    $unit = rp__unitsFor([$req]);
    $unit = $unit['units'][0] ?? $unit['unplaceable'][0] ?? null;
    if ($unit && !$already) {
        $v = rp_vehicle($driver);
        $block = fn(string $m, bool $f) => ['blocked' => true, 'can_force' => $f, 'warning' => $m];
        if (empty($v['active'])) return $block('This vehicle is out of service.', false);
        $why = rp_vehicleEligible($v, $unit);
        if ($why && !preg_match('/^too (heavy|bulky|many)/', $why)) return $block('Cannot add: ' . $why . '.', false);
        $inv = rp_inventory($routeId);
        $load = ['kg' => $inv['open_kg'], 'l' => $inv['open_l'], 'n' => $inv['open']];
        // Other assigned work of the rider outside this route counts as well.
        $planner = rp_ridersForPlanning((string) $route['route_date'])[$driver] ?? null;
        if ($planner) foreach (['kg', 'l', 'n'] as $k) $load[$k] = max($load[$k], $planner['used'][$k]);
        $notes = [];
        $over = rp__overCapacity($load, $unit, $v);
        if ($over) {
            if (max(array_column($over, 'pct')) > RP_FORCE_MAX_PCT) return $block('No room: ' . rp__overText($over) . '.', false);
            $notes[] = 'over capacity (' . rp__overText($over) . ')';
        }
        $rs = strtolower((string) rp_val('SELECT rider_status FROM cdb_users WHERE id = ?', [$driver]));
        if ($rs === 'suspended') return $block('This rider is suspended.', false);
        if (in_array($rs, ['off', 'offline', 'off_duty', 'leave'], true)) $notes[] = 'the rider is off duty';
        if ($notes && !$force) return $block(ucfirst(implode('; ', $notes)) . '.', true);
    }
    if (!$already) {
        if (!in_array($req['state'], ['pending', 'assigned', 'failed'], true)) throw new DomainException('Only pending, assigned or failed deliveries can be added');
        rp_service()->transition($requestId, $actor, 'manager', function_exists('dl_agency') ? dl_agency() : 0, 'assign', ['driver_id' => $driver, 'note' => 'Added to route #' . $routeId]);
    }
    // A stop of this request on another open route moves here.
    foreach (rp_all("SELECT s.id, s.route_id FROM cdb_rp_stops s JOIN cdb_rp_routes r ON r.id = s.route_id WHERE s.request_id = ? AND s.status IN ('pending','arrived') AND s.route_id <> ? AND r.status <> 'done'", [$requestId, $routeId]) as $o) {
        rp_q("UPDATE cdb_rp_stops SET status = 'removed' WHERE id = ?", [(int) $o['id']]);
        rp_q('DELETE FROM cdb_rp_load WHERE stop_id = ?', [(int) $o['id']]);
        rp__recompute((int) $o['route_id'], false);
    }
    rp__addStops($routeId, [$requestId]);
    rp__recompute($routeId, true);
    return rp_route($routeId, $actor, 'manager');
}

/** Remove a stop. Assigned requests go back to the pending hold; $cancel uses the cancel path. */
function rp_removeStop(int $routeId, int $stopId, int $actor, bool $cancel = false, string $note = ''): array
{
    $route = rp__routeFor($routeId, $actor, 'manager');
    $stop = rp_one('SELECT * FROM cdb_rp_stops WHERE id = ? AND route_id = ?', [$stopId, $routeId]);
    if (!$stop || $stop['status'] === 'removed') throw new DomainException('Stop not found');
    if (in_array($stop['status'], RP_DONE_STOP, true)) throw new DomainException('This stop is already finished');
    $req = rp_one('SELECT * FROM cdb_delivery_requests WHERE id = ?', [(int) $stop['request_id']]);
    if ($cancel) {
        rp_service()->transition((int) $stop['request_id'], $actor, 'manager', function_exists('dl_agency') ? dl_agency() : 0, 'cancel', ['note' => $note ?: 'Cancelled from the route planner']);
    } elseif ($req && $req['state'] === 'onroute') {
        throw new DomainException('The rider already has this parcel on route. Ask the rider to mark it failed, or cancel it with a reason.');
    } elseif ($req && in_array($req['state'], ['assigned', 'failed'], true)) {
        $svc = rp_service();
        $own = !$svc->db->inTransaction();
        if ($own) $svc->db->beginTransaction();
        try {
            rp_q("UPDATE cdb_delivery_requests SET state = 'pending', driver_id = NULL, assigned_at = NULL, updated_at = ? WHERE id = ? AND state IN ('assigned','failed')", [rp_now(), (int) $req['id']]);
            $svc->event((int) $req['id'], $actor, 'pending', 'Removed from the rider\'s route; waiting for dispatch');
            if ($own) $svc->db->commit();
        } catch (Throwable $e) { if ($own && $svc->db->inTransaction()) $svc->db->rollBack(); throw $e; }
    }
    rp_q("UPDATE cdb_rp_stops SET status = 'removed' WHERE id = ?", [$stopId]);
    rp_q('DELETE FROM cdb_rp_load WHERE route_id = ? AND stop_id = ?', [$routeId, $stopId]);
    rp__recompute($routeId, true);
    return rp_route($routeId, $actor, 'manager');
}

function rp_inventory(int $routeId): array
{
    rp_ensureSchema();
    $loads = rp_all("SELECT l.* FROM cdb_rp_load l JOIN cdb_rp_stops s ON s.id = l.stop_id WHERE l.route_id = ? AND s.status <> 'removed'", [$routeId]);
    $det = rp_parcelDetailsMany(array_map(fn($l) => [$l['module'], (int) $l['order_id']], $loads));
    // onboard = physically in the vehicle (loaded, no outcome yet); open = still to deliver
    // (loaded or waiting to be loaded). Missing parcels are neither.
    $inv = ['total' => count($loads), 'by_type' => ['air' => 0, 'sea' => 0, 'fragile' => 0, 'cold' => 0, 'high_value' => 0], 'loaded' => 0, 'pending_load' => 0, 'missing' => 0, 'onboard' => 0, 'open' => 0, 'delivered' => 0, 'failed' => 0, 'returned' => 0, 'kg' => 0.0, 'l' => 0.0, 'onboard_kg' => 0.0, 'onboard_l' => 0.0, 'open_kg' => 0.0, 'open_l' => 0.0];
    foreach ($loads as $l) {
        $d = $det[$l['module'] . ':' . (int) $l['order_id']] ?? ['kg' => 0, 'volume_l' => 0, 'handling' => []];
        $inv['by_type'][$l['module'] === 'sea' ? 'sea' : 'air']++;
        foreach (['fragile', 'cold', 'high_value'] as $h) if (in_array($h, $d['handling'], true)) $inv['by_type'][$h]++;
        $inv[$l['load_state'] === 'loaded' ? 'loaded' : ($l['load_state'] === 'missing' ? 'missing' : 'pending_load')]++;
        if ($l['load_state'] === 'missing') continue;
        if (in_array($l['outcome'], ['delivered', 'failed', 'returned'], true)) { $inv[$l['outcome']]++; continue; }
        $inv['open']++; $inv['open_kg'] += (float) $d['kg']; $inv['open_l'] += (float) $d['volume_l'];
        if ($l['load_state'] === 'loaded') { $inv['onboard']++; $inv['onboard_kg'] += (float) $d['kg']; $inv['onboard_l'] += (float) $d['volume_l']; }
    }
    foreach ($loads as $l) { $d = $det[$l['module'] . ':' . (int) $l['order_id']] ?? ['kg' => 0, 'volume_l' => 0]; $inv['kg'] += (float) $d['kg']; $inv['l'] += (float) $d['volume_l']; }
    foreach (['kg', 'l', 'onboard_kg', 'onboard_l', 'open_kg', 'open_l'] as $k) $inv[$k] = round($inv[$k], 2);
    return $inv;
}

function rp__riderRoute(int $routeId, int $driverId, array $allowed): array
{
    $route = rp__routeFor($routeId, $driverId, 'rider');
    if (!in_array($route['status'], $allowed, true)) throw new DomainException($route['status'] === 'done' ? 'This route is finished' : 'Not possible while the route is ' . $route['status']);
    if ((string) $route['route_date'] > date('Y-m-d')) throw new DomainException('This route is planned for ' . date('D j M', strtotime((string) $route['route_date'])) . '. You can load and start it on that day.');
    return $route;
}

/** After a parcel is (un)loaded on a started route, start its request once all its parcels are on board. */
function rp__startLoadedRequests(array $route, int $driverId): array
{
    $errors = [];
    if ($route['status'] !== 'onroute') return $errors;
    foreach (rp_all("SELECT s.request_id FROM cdb_rp_stops s JOIN cdb_delivery_requests r ON r.id = s.request_id WHERE s.route_id = ? AND s.status = 'pending' AND r.state = 'assigned' AND r.driver_id = ?", [(int) $route['id'], $driverId]) as $s) {
        $left = (int) rp_val("SELECT COUNT(*) FROM cdb_rp_load WHERE route_id = ? AND request_id = ? AND load_state = 'pending'", [(int) $route['id'], (int) $s['request_id']]);
        if ($left) continue;
        try { rp_service()->transition((int) $s['request_id'], $driverId, 'rider', 0, 'start'); }
        catch (DomainException $e) { $errors[] = ['request_id' => (int) $s['request_id'], 'message' => $e->getMessage()]; }
    }
    return $errors;
}

function rp__normCode(string $c): string { return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $c)); }

function rp_loadScan(int $routeId, int $driverId, string $code): array
{
    $route = rp__riderRoute($routeId, $driverId, ['planned', 'loading', 'onroute']);
    $norm = rp__normCode($code);
    if ($norm === '') throw new DomainException('Scan or type a tracking number');
    $hit = null;
    $rows = rp_all("SELECT l.* FROM cdb_rp_load l JOIN cdb_rp_stops s ON s.id = l.stop_id WHERE l.route_id = ? AND s.status <> 'removed'", [$routeId]);
    // Tracking number first; the bare order number only as a fallback and only with 4+ digits,
    // so a short typed number cannot tick a different parcel.
    foreach ($rows as $l) if (rp__normCode((string) $l['tracking']) === $norm) { $hit = $l; break; }
    if (!$hit && ctype_digit($norm) && strlen(ltrim($norm, '0')) >= 4) foreach ($rows as $l) if ((int) $norm === (int) $l['order_id']) { $hit = $l; break; }
    if (!$hit) return ['ok' => false, 'message' => 'Not on your route: ' . mb_substr($code, 0, 40), 'inventory' => rp_inventory($routeId)];
    $already = $hit['load_state'] === 'loaded';
    $res = rp_loadToggle($routeId, $driverId, $hit['module'], (int) $hit['order_id'], true);
    return ['ok' => true, 'already' => $already, 'parcel' => rp_parcelDetails($hit['module'], (int) $hit['order_id']) + ['stop_id' => (int) $hit['stop_id'], 'request_id' => (int) $hit['request_id'], 'load_state' => 'loaded'], 'inventory' => $res['inventory'], 'errors' => $res['errors'] ?? []];
}

function rp_loadToggle(int $routeId, int $driverId, string $module, int $orderId, bool $loaded, bool $missing = false): array
{
    $route = rp__riderRoute($routeId, $driverId, ['planned', 'loading', 'onroute']);
    $row = rp_one('SELECT * FROM cdb_rp_load WHERE route_id = ? AND module = ? AND order_id = ?', [$routeId, $module === 'sea' ? 'sea' : 'air', $orderId]);
    if (!$row) throw new DomainException('This parcel is not on your route');
    if ($row['outcome'] !== 'onboard') throw new DomainException('This parcel is already ' . $row['outcome']);
    $state = $missing ? 'missing' : ($loaded ? 'loaded' : 'pending');
    rp_q('UPDATE cdb_rp_load SET load_state = ?, loaded_at = ?, loaded_by = ? WHERE route_id = ? AND module = ? AND order_id = ?', [$state, $state === 'pending' ? null : rp_now(), $driverId, $routeId, $row['module'], $orderId]);
    if ($route['status'] === 'planned') {
        rp_q("UPDATE cdb_rp_routes SET status = 'loading', updated_at = ? WHERE id = ? AND status = 'planned'", [rp_now(), $routeId]);
        rp_setActivity($driverId, $routeId, 'loading');
        $route['status'] = 'loading';
    }
    $errors = rp__startLoadedRequests($route, $driverId);
    return ['ok' => true, 'load_state' => $state, 'inventory' => rp_inventory($routeId), 'errors' => $errors];
}

/** Start the route: every parcel loaded or marked missing; requests go 'onroute' via DeliveryService. */
function rp_startRoute(int $routeId, int $driverId): array
{
    $route = rp__riderRoute($routeId, $driverId, ['planned', 'loading']);
    $pendingLoad = (int) rp_val("SELECT COUNT(*) FROM cdb_rp_load l JOIN cdb_rp_stops s ON s.id = l.stop_id WHERE l.route_id = ? AND s.status <> 'removed' AND l.load_state = 'pending'", [$routeId]);
    if ($pendingLoad) throw new DomainException($pendingLoad . ' parcel' . ($pendingLoad === 1 ? '' : 's') . ' not loaded yet. Tick each parcel, or mark it missing.');
    $svc = rp_service();
    $errors = []; $started = 0;
    foreach (rp_all("SELECT * FROM cdb_rp_stops WHERE route_id = ? AND status = 'pending' ORDER BY seq", [$routeId]) as $s) {
        $loaded = (int) rp_val("SELECT COUNT(*) FROM cdb_rp_load WHERE stop_id = ? AND load_state = 'loaded'", [(int) $s['id']]);
        $req = rp_one('SELECT state, driver_id FROM cdb_delivery_requests WHERE id = ?', [(int) $s['request_id']]);
        if (!$loaded) {
            // Nothing of this delivery made it on board: back to the hold for dispatch.
            rp_q("UPDATE cdb_rp_stops SET status = 'removed', fail_reason = 'Parcels missing at loading' WHERE id = ?", [(int) $s['id']]);
            if ($req && $req['state'] === 'assigned') {
                rp_q("UPDATE cdb_delivery_requests SET state = 'pending', driver_id = NULL, assigned_at = NULL, updated_at = ? WHERE id = ? AND state = 'assigned'", [rp_now(), (int) $s['request_id']]);
                $svc->event((int) $s['request_id'], $driverId, 'pending', 'Parcel not found at loading; back with dispatch');
            }
            continue;
        }
        if ($req && $req['state'] === 'onroute') { $started++; continue; }
        try { $svc->transition((int) $s['request_id'], $driverId, 'rider', 0, 'start'); $started++; }
        catch (DomainException $e) { $errors[] = ['request_id' => (int) $s['request_id'], 'stop_id' => (int) $s['id'], 'message' => $e->getMessage()]; }
    }
    if (!$started) {
        $route2 = rp_route($routeId, $driverId, 'rider');
        return $route2 + ['started' => false, 'errors' => $errors ?: [['message' => 'No delivery could start']]];
    }
    rp_q("UPDATE cdb_rp_routes SET status = 'onroute', started_at = COALESCE(started_at, ?), updated_at = ? WHERE id = ?", [rp_now(), rp_now(), $routeId]);
    rp_setActivity($driverId, $routeId, 'onway');
    rp__recompute($routeId, true);
    return rp_route($routeId, $driverId, 'rider') + ['started' => true, 'errors' => $errors];
}

function rp_arriveStop(int $routeId, int $stopId, int $driverId): array
{
    rp__riderRoute($routeId, $driverId, ['onroute']);
    $stop = rp_one('SELECT * FROM cdb_rp_stops WHERE id = ? AND route_id = ?', [$stopId, $routeId]);
    if (!$stop || $stop['status'] === 'removed') throw new DomainException('Stop not found');
    if ($stop['status'] === 'arrived') return rp_route($routeId, $driverId, 'rider');
    if ($stop['status'] !== 'pending') throw new DomainException('This stop is already ' . $stop['status']);
    // One stop at a time: an earlier arrival without an outcome goes back to pending.
    rp_q("UPDATE cdb_rp_stops SET status = 'pending', arrived_at = NULL WHERE route_id = ? AND status = 'arrived'", [$routeId]);
    rp_q("UPDATE cdb_rp_stops SET status = 'arrived', arrived_at = ? WHERE id = ?", [rp_now(), $stopId]);
    rp_setActivity($driverId, $routeId, 'waiting');
    $req = rp_one('SELECT state FROM cdb_delivery_requests WHERE id = ?', [(int) $stop['request_id']]);
    if ($req && $req['state'] === 'onroute') rp_service()->event((int) $stop['request_id'], $driverId, 'onroute', 'Rider arrived');
    return rp_route($routeId, $driverId, 'rider');
}

/**
 * Stops whose delivery request changed outside the planner (reset, re-assigned elsewhere,
 * delivered or cancelled from another screen) are brought in line: finished requests are synced,
 * requests no longer assigned to the route's rider leave the route. $routeId null = all open routes.
 */
function rp_reconcileStops(?int $routeId = null): int
{
    if (!sl_tableExists('cdb_delivery_requests')) return 0;
    $rows = rp_all("SELECT s.id, s.route_id, s.request_id, r.driver_id, q.state, q.driver_id AS req_driver FROM cdb_rp_stops s JOIN cdb_rp_routes r ON r.id = s.route_id LEFT JOIN cdb_delivery_requests q ON q.id = s.request_id
        WHERE s.status IN ('pending','arrived') AND r.status <> 'done'" . ($routeId ? ' AND s.route_id = ?' : '') . "
        AND (q.id IS NULL OR q.state NOT IN ('assigned','onroute') OR q.driver_id IS NULL OR q.driver_id <> r.driver_id)", $routeId ? [$routeId] : []);
    $touched = [];
    foreach ($rows as $s) {
        if (in_array($s['state'], ['delivered', 'failed', 'cancelled'], true) && (int) $s['req_driver'] === (int) $s['driver_id']) { rp_syncStopFromRequest((int) $s['request_id']); continue; }
        rp_q("UPDATE cdb_rp_stops SET status = 'removed', fail_reason = 'Delivery changed outside the route' WHERE id = ? AND status IN ('pending','arrived')", [(int) $s['id']]);
        rp_q('DELETE FROM cdb_rp_load WHERE stop_id = ?', [(int) $s['id']]);
        $touched[(int) $s['route_id']] = true;
    }
    foreach (array_keys($touched) as $rid) rp__recompute($rid, false);
    return count($rows);
}

/** Map a request's state onto its stop/load rows after delivery_ajax deliver/fail/cancel. */
function rp_syncStopFromRequest(int $requestId): void
{
    rp_ensureSchema();
    $req = rp_one('SELECT * FROM cdb_delivery_requests WHERE id = ?', [$requestId]);
    if (!$req) return;
    $map = ['delivered' => 'delivered', 'failed' => 'failed', 'cancelled' => 'removed'];
    $status = $map[$req['state']] ?? null;
    if (!$status) return;
    foreach (rp_all("SELECT s.*, r.driver_id, r.status AS route_status FROM cdb_rp_stops s JOIN cdb_rp_routes r ON r.id = s.route_id WHERE s.request_id = ? AND s.status IN ('pending','arrived')", [$requestId]) as $s) {
        $routeId = (int) $s['route_id']; $driver = (int) $s['driver_id'];
        $reason = null;
        if ($status === 'failed') $reason = (string) rp_val("SELECT note FROM cdb_delivery_events WHERE request_id = ? AND state = 'failed' ORDER BY id DESC", [$requestId]);
        rp_q('UPDATE cdb_rp_stops SET status = ?, done_at = ?, fail_reason = ? WHERE id = ?', [$status, rp_now(), $reason !== null ? mb_substr($reason, 0, 300) : null, (int) $s['id']]);
        if ($status === 'removed') rp_q('DELETE FROM cdb_rp_load WHERE stop_id = ?', [(int) $s['id']]);
        else rp_q("UPDATE cdb_rp_load SET outcome = ?, outcome_at = ? WHERE stop_id = ? AND outcome = 'onboard' AND load_state <> 'missing'", [$status, rp_now(), (int) $s['id']]);
        $left = (int) rp_val("SELECT COUNT(*) FROM cdb_rp_stops WHERE route_id = ? AND status IN ('pending','arrived')", [$routeId]);
        if ($s['route_status'] === 'onroute') {
            if ($left) {
                rp_setActivity($driver, $routeId, 'onway');
                rp__recompute($routeId, false);
            } else {
                // Last stop: failed parcels ride back to the office.
                rp_q("UPDATE cdb_rp_load SET outcome = 'returned', outcome_at = ? WHERE route_id = ? AND outcome = 'failed'", [rp_now(), $routeId]);
                rp_q("UPDATE cdb_rp_routes SET status = 'done', finished_at = ?, updated_at = ? WHERE id = ?", [rp_now(), rp_now(), $routeId]);
                rp_setActivity($driver, $routeId, 'off');
            }
        } else {
            rp__recompute($routeId, false);
        }
    }
}

/* ------------------------------------------------------------------ tracking & stats */

/**
 * Store a rider position. Throttled and ordered on SERVER time (a device clock that runs ahead
 * or behind cannot block or backdate pings; the device time is only used for nothing else).
 * Positions are kept only while the rider has a route loading or on the way today, and the
 * history is purged after 14 days.
 */
function rp_recordPing(int $driverId, array $p): array
{
    rp_ensureSchema();
    $settings = rp_settings();
    $now = time();
    $p['lat'] = isset($p['lat']) && is_numeric($p['lat']) ? (float) $p['lat'] : null;
    $p['lng'] = isset($p['lng']) && is_numeric($p['lng']) ? (float) $p['lng'] : null;
    $p['accuracy'] = isset($p['accuracy']) && is_numeric($p['accuracy']) ? (float) $p['accuracy'] : null;
    $route = rp_one("SELECT id FROM cdb_rp_routes WHERE driver_id = ? AND route_date = ? AND status IN ('loading','onroute') ORDER BY FIELD(status, 'onroute', 'loading'), id DESC", [$driverId, date('Y-m-d')]);
    if (!$route) return ['stored' => false, 'next_in_s' => 300, 'reason' => 'not on shift'];
    $last = rp_one('SELECT lat, lng, received_at AS recorded_at FROM cdb_rp_pings WHERE driver_id = ? ORDER BY id DESC', [$driverId]);
    if ($last) $last['recorded_at'] = strtotime($last['recorded_at']);
    $d = rp_shouldStorePing($last, $p, $settings, $now);
    if (!$d['store']) return ['stored' => false, 'next_in_s' => $d['next_in_s'], 'reason' => $d['reason']];
    $clip = fn($v, $lo, $hi) => $v === null || $v === '' || !is_numeric($v) ? null : max($lo, min($hi, $v));
    rp_q('INSERT INTO cdb_rp_pings (driver_id, route_id, lat, lng, accuracy_m, speed_mps, heading, battery, recorded_at, received_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
        [$driverId, (int) $route['id'], round($p['lat'], 7), round($p['lng'], 7), $p['accuracy'] !== null ? (int) round($p['accuracy']) : null,
            ($s = $clip($p['speed'] ?? null, 0, 9999)) !== null ? round((float) $s, 2) : null, ($h = $clip($p['heading'] ?? null, 0, 360)) !== null ? (int) $h : null,
            ($b = $clip($p['battery'] ?? null, 0, 100)) !== null ? (int) round((float) $b) : null, date('Y-m-d H:i:s', $now), date('Y-m-d H:i:s', $now)]);
    // Housekeeping without a cron: about once in 200 stored pings, drop history older than 14 days.
    if (random_int(1, 200) === 1) rp_purgePings(14);
    return ['stored' => true, 'next_in_s' => $d['next_in_s'], 'reason' => $d['reason'], 'route_id' => (int) $route['id']];
}

function rp_setActivity(int $driverId, ?int $routeId, string $state): void
{
    rp_ensureSchema();
    $open = rp__openActivity($driverId);
    if ($open && $open['state'] === $state && (int) $open['route_id'] === (int) $routeId) return;
    $now = rp_now();
    if ($open) rp_q('UPDATE cdb_rp_activity SET ended_at = ? WHERE driver_id = ? AND ended_at IS NULL', [$now, $driverId]);
    if (in_array($state, RP_ACTIVITY, true)) rp_q('INSERT INTO cdb_rp_activity (driver_id, route_id, state, started_at) VALUES (?,?,?,?)', [$driverId, $routeId, $state, $now]);
}

function rp_fleetState(string $date, int $agency = 0): array
{
    rp_ensureSchema();
    $settings = rp_settings();
    $riders = rp_ridersForPlanning($date, $agency);
    $out = ['riders' => [], 'alerts' => [], 'kpis' => [], 'date' => $date, 'generated_at' => date('c')];
    foreach ($riders as $id => $r) {
        $ping = rp_one('SELECT lat, lng, recorded_at, speed_mps, battery FROM cdb_rp_pings WHERE driver_id = ? ORDER BY recorded_at DESC, id DESC', [$id]);
        $route = $r['route_id'] ? rp_one('SELECT * FROM cdb_rp_routes WHERE id = ?', [$r['route_id']]) : null;
        $stops = $route ? rp_all("SELECT id, seq, status, eta, window_end, contact_name AS label, done_at FROM cdb_rp_stops WHERE route_id = ? AND status <> 'removed' ORDER BY seq", [(int) $route['id']]) : [];
        $next = null;
        foreach ($stops as $s) if (in_array($s['status'], ['pending', 'arrived'], true)) { $next = $s; break; }
        $fill = rp_fill(['kg' => $route ? (float) $route['load_kg'] : $r['used']['kg'], 'l' => $route ? (float) $route['load_l'] : $r['used']['l'], 'n' => $route ? (int) $route['load_n'] : $r['used']['n']], $r['vehicle']);
        // Privacy: a position is shown only while the rider works a route and it is recent.
        $active = $route && in_array($route['status'], ['loading', 'onroute'], true);
        $showPos = $ping && $active && strtotime($ping['recorded_at']) >= time() - (int) $settings['offline_min'] * 3 * 60;
        $out['riders'][] = [
            'id' => $id, 'name' => $r['name'], 'status' => $r['status'], 'vehicle_type' => $r['vehicle_type'],
            'pos' => $showPos ? ['lat' => (float) $ping['lat'], 'lng' => (float) $ping['lng']] : null, 'last_ping_at' => $ping['recorded_at'] ?? null, 'battery' => $showPos && isset($ping['battery']) ? (int) $ping['battery'] : null,
            'route_id' => $route ? (int) $route['id'] : 0, 'route_status' => $route['status'] ?? null,
            'progress' => ['done' => count(array_filter($stops, fn($s) => in_array($s['status'], RP_DONE_STOP, true))), 'total' => count($stops)],
            'next_eta' => $next && $next['eta'] ? date('c', strtotime($next['eta'])) : null, 'next_stop' => $next ? ['id' => (int) $next['id'], 'seq' => (int) $next['seq'], 'label' => $next['label']] : null,
            'fill' => $fill,
        ];
        if ($route && in_array($route['status'], ['loading', 'onroute'], true)) {
            $recent = rp_all('SELECT lat, lng, recorded_at FROM cdb_rp_pings WHERE driver_id = ? AND recorded_at >= ? ORDER BY recorded_at', [$id, date('Y-m-d H:i:s', time() - ((int) $settings['stuck_min'] + 2) * 60)]);
            $since = rp_val("SELECT started_at FROM cdb_rp_activity WHERE driver_id = ? AND state = 'loading' AND ended_at IS NULL ORDER BY id DESC", [$id]);
            $act = rp__openActivity($id);
            foreach (rp_detectAlerts($route + ['rider_name' => $r['name'], 'loading_since' => $since ?: $route['updated_at'], 'activity' => $act['state'] ?? null], $stops, $ping, $recent, $settings, time()) as $a) $out['alerts'][] = $a + ['driver_id' => $id, 'name' => $r['name'], 'route_id' => (int) $route['id']];
        }
    }
    $aw = $agency ? ' AND agency_id = ?' : '';
    $ab = $agency ? [$agency] : [];
    $count = fn(string $where, array $b = []) => sl_tableExists('cdb_delivery_requests') ? (int) rp_val("SELECT COUNT(*) FROM cdb_delivery_requests WHERE $where$aw", array_merge($b, $ab)) : 0;
    $d0 = $date . ' 00:00:00'; $d1 = $date . ' 23:59:59';
    $out['kpis'] = [
        'ready' => $count("state = 'pending' AND approved_at IS NOT NULL"),
        'allocated' => $count("state = 'assigned'"),
        'onway' => $count("state = 'onroute'"),
        'delivered_today' => $count("state = 'delivered' AND completed_at BETWEEN ? AND ?", [$d0, $d1]),
        'failed_today' => $count("state = 'failed' AND updated_at BETWEEN ? AND ?", [$d0, $d1]),
    ];
    return $out;
}

/** Time by category, working time per day, distance and outcomes for [from, to]. */
function rp_riderStats(int $driverId, string $from, string $to): array
{
    rp_ensureSchema();
    $t0 = strtotime($from . ' 00:00:00'); $t1 = strtotime($to . ' 23:59:59') + 1;
    $days = [];
    for ($t = $t0; $t < $t1; $t += 86400) $days[date('Y-m-d', $t)] = ['date' => date('Y-m-d', $t), 'work_s' => 0, 'onway_s' => 0, 'loading_s' => 0, 'waiting_s' => 0, 'break_s' => 0, 'stops' => 0, 'distance_m' => 0];
    $cat = ['onway_s' => 0, 'loading_s' => 0, 'waiting_s' => 0, 'break_s' => 0];
    $now = time();
    foreach (rp_all('SELECT state, started_at, ended_at FROM cdb_rp_activity WHERE driver_id = ? AND started_at < ? AND (ended_at IS NULL OR ended_at > ?)', [$driverId, date('Y-m-d H:i:s', $t1), date('Y-m-d H:i:s', $t0)]) as $a) {
        $s = max($t0, strtotime($a['started_at'])); $e = min($t1, $a['ended_at'] ? strtotime($a['ended_at']) : $now);
        $k = $a['state'] . '_s';
        while ($s < $e) {
            $dayEnd = strtotime(date('Y-m-d', $s) . ' 23:59:59') + 1;
            $chunk = min($e, $dayEnd) - $s;
            $dk = date('Y-m-d', $s);
            if (isset($days[$dk][$k])) { $days[$dk][$k] += $chunk; if ($k !== 'break_s') $days[$dk]['work_s'] += $chunk; }
            if (isset($cat[$k])) $cat[$k] += $chunk;
            $s += $chunk;
        }
    }
    $dist = 0; $prev = null;
    foreach (rp_all('SELECT lat, lng, recorded_at FROM cdb_rp_pings WHERE driver_id = ? AND recorded_at BETWEEN ? AND ? ORDER BY recorded_at, id', [$driverId, date('Y-m-d H:i:s', $t0), date('Y-m-d H:i:s', $t1 - 1)]) as $p) {
        $ts = strtotime($p['recorded_at']);
        if ($prev && date('Y-m-d', $ts) === date('Y-m-d', $prev[2]) && $ts - $prev[2] < 1800) {
            $m = rp_haversineM($prev[0], $prev[1], (float) $p['lat'], (float) $p['lng']);
            if ($m < 20000) { $dist += $m; $days[date('Y-m-d', $ts)]['distance_m'] += $m; }
        }
        $prev = [(float) $p['lat'], (float) $p['lng'], $ts];
    }
    $delivered = $failed = 0;
    foreach (rp_all("SELECT s.status, s.done_at FROM cdb_rp_stops s JOIN cdb_rp_routes r ON r.id = s.route_id WHERE r.driver_id = ? AND s.done_at BETWEEN ? AND ? AND s.status IN ('delivered','failed','returned')", [$driverId, date('Y-m-d H:i:s', $t0), date('Y-m-d H:i:s', $t1 - 1)]) as $s) {
        $s['status'] === 'delivered' ? $delivered++ : $failed++;
        $dk = date('Y-m-d', strtotime($s['done_at']));
        if (isset($days[$dk])) $days[$dk]['stops']++;
    }
    return ['by_category' => $cat, 'per_day' => array_values($days), 'distance_m' => $dist, 'delivered' => $delivered, 'failed' => $failed, 'from' => $from, 'to' => $to];
}

function rp_timeline(string $date, int $agency = 0): array
{
    rp_ensureSchema();
    $d0 = $date . ' 00:00:00'; $d1 = $date . ' 23:59:59';
    $out = [];
    foreach (rp__riders($agency) as $id => $r) {
        $blocks = [];
        foreach (rp_all('SELECT state, started_at, ended_at, route_id FROM cdb_rp_activity WHERE driver_id = ? AND started_at <= ? AND (ended_at IS NULL OR ended_at >= ?) ORDER BY started_at', [$id, $d1, $d0]) as $a) {
            $blocks[] = ['state' => $a['state'], 'start' => max($a['started_at'], $d0), 'end' => $a['ended_at'] ? min($a['ended_at'], $d1) : null, 'route_id' => $a['route_id'] ? (int) $a['route_id'] : null];
        }
        $stops = [];
        foreach (rp_all("SELECT s.seq, s.eta, s.done_at, s.status, s.route_id FROM cdb_rp_stops s JOIN cdb_rp_routes r ON r.id = s.route_id WHERE r.driver_id = ? AND r.route_date = ? AND s.status <> 'removed' ORDER BY r.id, s.seq", [$id, $date]) as $s) $stops[] = ['seq' => (int) $s['seq'], 'eta' => $s['eta'], 'done_at' => $s['done_at'], 'status' => $s['status'], 'route_id' => (int) $s['route_id']];
        $out[] = ['driver_id' => $id, 'name' => $r['name'], 'blocks' => $blocks, 'stops' => $stops];
    }
    return $out;
}

function rp_purgePings(int $days = 14): int
{
    rp_ensureSchema();
    return rp_q('DELETE FROM cdb_rp_pings WHERE recorded_at < ?', [date('Y-m-d H:i:s', time() - max(1, $days) * 86400)])->rowCount();
}
