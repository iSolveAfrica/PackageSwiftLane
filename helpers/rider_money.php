<?php
/**
 * Rider app: preferences, the money ledger and the rider's own schedule.
 * Tables: migrations/2026_11_27_rider_app.sql (cdb_rider_prefs, cdb_rider_payout_requests,
 * cdb_rider_quests, cdb_rider_shifts). Every function degrades when a table is missing.
 *
 *   sl_riderPref($riderId, 'nav_app')          'google' | 'waze'
 *   sl_riderPref($riderId, 'offer_sounds')     '1' | '0'
 *   sl_riderNavUrl($riderId, $address)         directions link in the rider's chosen app
 *   sl_rmBalance($riderId)                     what the rider is owed (used by the wallet and by staff)
 */
require_once __DIR__ . '/sl_view.php';

const SL_RIDER_PREF_DEFAULTS = ['nav_app' => 'google', 'offer_sounds' => '1'];
const SL_RIDER_PAYOUT_OPEN = ['requested', 'approved'];

/** All stored preferences of a rider merged over the defaults. Rider 0 = site-wide rider app settings. */
function sl_riderPrefs(int $riderId, bool $fresh = false): array
{
    static $cache = [];
    if ($fresh || !isset($cache[$riderId])) {
        $out = $riderId > 0 ? SL_RIDER_PREF_DEFAULTS : [];
        if (sl_tableExists('cdb_rider_prefs')) {
            foreach (sl_rows('SELECT pref_key, pref_value FROM cdb_rider_prefs WHERE rider_id = :r', [':r' => $riderId]) as $p) {
                $out[$p->pref_key] = (string) $p->pref_value;
            }
        }
        $cache[$riderId] = $out;
    }
    return $cache[$riderId];
}

/** One preference of a rider, e.g. sl_riderPref($id, 'nav_app') === 'waze'. */
function sl_riderPref(int $riderId, string $key, $default = null)
{
    $all = sl_riderPrefs($riderId);
    return array_key_exists($key, $all) && $all[$key] !== '' ? $all[$key] : ($default ?? (SL_RIDER_PREF_DEFAULTS[$key] ?? ''));
}

function sl_riderPrefSet(int $riderId, string $key, string $value): bool
{
    if (!sl_tableExists('cdb_rider_prefs')) {
        return false;
    }
    $db = new Conexion();
    $db->cdp_query('INSERT INTO cdb_rider_prefs (rider_id, pref_key, pref_value, updated_at) VALUES (:r, :k, :v, :t) ON DUPLICATE KEY UPDATE pref_value = VALUES(pref_value), updated_at = VALUES(updated_at)');
    $db->bind(':r', $riderId);
    $db->bind(':k', $key);
    $db->bind(':v', mb_substr($value, 0, 500));
    $db->bind(':t', date('Y-m-d H:i:s'));
    $ok = (bool) $db->cdp_execute();
    sl_riderPrefs($riderId, true);
    return $ok;
}

/** Directions to an address in the navigation app the rider chose (Google Maps unless Waze is saved). */
function sl_riderNavUrl(int $riderId, string $destination): string
{
    $destination = trim($destination);
    if ($destination === '' || $destination === '—') {
        return '';
    }
    if (sl_riderPref($riderId, 'nav_app') === 'waze') {
        return 'https://waze.com/ul?' . http_build_query(['q' => $destination, 'navigate' => 'yes'], '', '&', PHP_QUERY_RFC3986);
    }
    return 'https://www.google.com/maps/dir/?' . http_build_query(['api' => 1, 'destination' => $destination, 'travelmode' => 'driving'], '', '&', PHP_QUERY_RFC3986);
}

/** 024 555 0101 -> ••• 0101. Never shows more than the last four digits. */
function sl_rmMask(string $number): string
{
    $digits = preg_replace('/\D/', '', $number);
    return strlen($digits) >= 4 ? '••• ' . substr($digits, -4) : ($digits !== '' ? '•••' : '');
}

/** tel: target, or '' when the number has no digits. */
function sl_rmTel(string $phone): string
{
    $p = preg_replace('/[^0-9+]/', '', $phone);
    return preg_match('/\d{3,}/', $p) ? 'tel:' . $p : '';
}

/**
 * Finished trips of a rider in [from, to] (Y-m-d), newest first. Two real sources:
 *  - assigned shipments and pickups (cdb_add_order.driver_id), paid by the fee rules (sl_rdFee);
 *  - customer door deliveries (cdb_delivery_requests), paid by the share stored on the request.
 * A shipment handed over through a door delivery is counted once, as the door delivery.
 */
function sl_rmTrips(int $riderId, string $from, string $to): array
{
    require_once CDP_ROOT . '/views/sl/data/riders.php';
    global $core;
    $trips = [];
    $viaDoor = [];
    $skipped = 0;
    if (sl_tableExists('cdb_delivery_requests')) {
        if (sl_tableExists('cdb_delivery_items')) {
            foreach (sl_rows("SELECT i.order_id FROM cdb_delivery_items i JOIN cdb_delivery_requests r ON r.id = i.request_id WHERE r.driver_id = :u AND r.state = 'delivered' AND i.module = 'air'", [':u' => $riderId]) as $x) {
                $viaDoor[(int) $x->order_id] = true;
            }
        }
        $site = strtoupper(trim((string) ($core->currency ?? '')));
        foreach (sl_rows("SELECT id, contact_name, address, currency, amount_cents, rider_cents, distance_m, assigned_at, completed_at, parcel_json FROM cdb_delivery_requests WHERE driver_id = :u AND state = 'delivered' AND completed_at BETWEEN :f AND :t ORDER BY completed_at DESC LIMIT 2000", [':u' => $riderId, ':f' => $from . ' 00:00:00', ':t' => $to . ' 23:59:59']) as $r) {
            // Door deliveries are priced in the delivery currency (cdb_delivery_config). The books are in the installation
            // currency, so a GHS share is converted at the current exchange rate; any other mismatch is left out and counted.
            $div = 1.0;
            $cur = strtoupper((string) $r->currency);
            if ($site !== '' && $cur !== $site) {
                $rate = $cur === 'GHS' && $site === 'USD' && function_exists('sl_exchangeRateValue') ? sl_exchangeRateValue() : 0.0;
                if ($rate <= 0) {
                    $skipped++;
                    continue;
                }
                $div = $rate;
            }
            $trips[] = ['k' => 'd', 'id' => (int) $r->id, 'name' => trim((string) $r->contact_name) ?: 'Customer', 'addr' => (string) $r->address, 'ref' => 'Delivery #' . (int) $r->id, 'kind' => 'Door delivery', 'at' => (string) $r->completed_at,
                'fee' => round((int) $r->amount_cents / 100 / $div, 4), 'share' => round((int) $r->rider_cents / 100 / $div, 4), 'km' => (int) $r->distance_m > 0 ? round((int) $r->distance_m / 1000, 1) : null, 'started' => (string) $r->assigned_at];
        }
    }
    $s = sl_rdStats($from, $to, $riderId)[$riderId] ?? null;
    foreach ($s['jobs'] ?? [] as $x) {
        $j = $x['j'];
        if ($x['st'][2] !== 'done' || isset($viaDoor[(int) $j->order_id])) {
            continue;
        }
        $trips[] = ['k' => 'o', 'id' => (int) $j->order_id, 'name' => trim((string) $j->cust) ?: 'Customer', 'addr' => (string) ($j->addr ?: ''), 'ref' => $j->order_prefix . $j->order_no, 'kind' => (int) $j->is_pickup ? 'Pickup' : 'Delivery', 'at' => (string) $j->done_at,
            'fee' => (float) $x['f']['fee'], 'share' => (float) $x['f']['rider'], 'pct' => (float) $x['f']['pct'], 'km' => null, 'started' => ''];
    }
    usort($trips, fn($a, $b) => strcmp($b['at'], $a['at']));
    return ['trips' => $trips, 'failed' => (int) ($s['failed'] ?? 0), 'open' => (int) ($s['open'] ?? 0), 'pct' => (float) ($s['pct'] ?? 0), 'skipped' => $skipped];
}

/**
 * The rider's ledger. earned = share of every finished trip; paid = settlements staff marked paid plus payout
 * requests staff marked paid; available = earned - paid - the amount of an open payout request.
 */
function sl_rmBalance(int $riderId): array
{
    $earned = 0.0;
    foreach (sl_rmTrips($riderId, '2000-01-01', date('Y-m-d'))['trips'] as $t) {
        $earned += $t['share'];
    }
    $settledPaid = 0.0;
    $settledPending = 0.0;
    if (sl_tableExists('cdb_rider_settlements')) {
        foreach (sl_rows('SELECT status, SUM(rider_share) AS s FROM cdb_rider_settlements WHERE driver_id = :d GROUP BY status', [':d' => $riderId]) as $r) {
            if ($r->status === 'paid') {
                $settledPaid += (float) $r->s;
            } else {
                $settledPending += (float) $r->s;
            }
        }
    }
    $requestPaid = 0.0;
    $open = null;
    if (sl_tableExists('cdb_rider_payout_requests')) {
        $requestPaid = (float) sl_scalar("SELECT COALESCE(SUM(amount), 0) FROM cdb_rider_payout_requests WHERE rider_id = :r AND status = 'paid'", [':r' => $riderId]);
        $open = sl_rmOpenRequest($riderId);
    }
    $owed = round($earned - $settledPaid - $requestPaid, 2);
    $openAmount = $open ? (float) $open->amount : 0.0;
    return ['earned' => round($earned, 2), 'paid' => round($settledPaid + $requestPaid, 2), 'owed' => $owed, 'pending' => round($settledPending, 2), 'open' => $open, 'available' => max(0.0, round($owed - $openAmount, 2))];
}

/** The rider's payout request that staff have not finished (requested or approved), or null. */
function sl_rmOpenRequest(int $riderId)
{
    if (!sl_tableExists('cdb_rider_payout_requests')) {
        return null;
    }
    return sl_row("SELECT * FROM cdb_rider_payout_requests WHERE rider_id = :r AND status IN ('requested','approved') ORDER BY id DESC", [':r' => $riderId]);
}

/** Payout requests for staff screens (newest first). $riderId null = every rider; $openOnly = waiting for staff. */
function sl_rmPayoutRequests(?int $riderId = null, bool $openOnly = false, int $limit = 50): array
{
    if (!sl_tableExists('cdb_rider_payout_requests')) {
        return [];
    }
    $w = [];
    $b = [];
    if ($riderId) {
        $w[] = 'p.rider_id = :r';
        $b[':r'] = $riderId;
    }
    if ($openOnly) {
        $w[] = "p.status IN ('requested','approved')";
    }
    return sl_rows("SELECT p.*, CONCAT(u.fname, ' ', u.lname) AS rider_name FROM cdb_rider_payout_requests p LEFT JOIN cdb_users u ON u.id = p.rider_id" . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY p.id DESC LIMIT ' . max(1, min(500, $limit)), $b);
}

/** Booked blocks of a rider between two dates, in start order. */
function sl_rmShifts(int $riderId, string $from, string $to): array
{
    if (!sl_tableExists('cdb_rider_shifts')) {
        return [];
    }
    return sl_rows("SELECT * FROM cdb_rider_shifts WHERE rider_id = :r AND status = 'booked' AND day BETWEEN :f AND :t ORDER BY day, start_time", [':r' => $riderId, ':f' => $from, ':t' => $to]);
}

/** Quests that are switched on and not finished more than $graceDays ago, soonest ending first. */
function sl_rmQuests(int $graceDays = 7): array
{
    if (!sl_tableExists('cdb_rider_quests')) {
        return [];
    }
    return sl_rows('SELECT * FROM cdb_rider_quests WHERE active = 1 AND target_trips > 0 AND ends_at >= :g ORDER BY ends_at, id LIMIT 30', [':g' => date('Y-m-d H:i:s', strtotime("-$graceDays day"))]);
}
