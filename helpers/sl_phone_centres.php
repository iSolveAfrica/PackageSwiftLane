<?php
/**
 * Small helpers for the staff phone screens of the riders/last-mile and command-centre groups
 * (views/sl/phone/Drivers.php … LoyaltyRewards.php): the design's sparkline (.mx-sp) and bar chart (.mx-bc),
 * plus a few read-only, scoped series the web pages do not need.
 */
require_once __DIR__ . '/sl_view.php';

/** Sparkline (.mx-sp) from a list of numbers; the last bar is lit. Empty string when there is nothing to draw. */
function slpc_spark(array $vals): string
{
    $vals = array_values(array_map('floatval', $vals));
    if (!$vals || max($vals) <= 0) return '';
    $mx = max($vals);
    $out = '<div class="mx-sp" aria-hidden="true">';
    foreach ($vals as $k => $v) {
        $out .= '<i class="' . ($k === count($vals) - 1 ? 'now' : '') . '" style="height:' . max(8, (int) round($v / $mx * 100)) . '%"></i>';
    }
    return $out . '</div>';
}

/** Bar chart body (.mx-bc): $vals and $labels of the same length; the last bar is lit. */
function slpc_bars(array $vals, array $labels): string
{
    $vals = array_values(array_map('floatval', $vals));
    $mx = $vals ? max($vals) * 1.15 : 0;
    $out = '<div class="mx-bc">';
    foreach ($vals as $k => $v) {
        $h = $mx > 0 ? max(6, (int) round($v / $mx * 100)) : 6;
        $out .= '<span class="' . ($k === count($vals) - 1 ? 'now' : '') . '" title="' . h((string) ($labels[$k] ?? '') . ': ' . (float) $v) . '"><i style="height:' . $h . '%"></i><small>' . h((string) ($labels[$k] ?? '')) . '</small></span>';
    }
    return $out . '</div>';
}

/**
 * Packages finished by riders per day for the last $days days (oldest first), in the viewer's agency scope.
 * Uses the same "done" statuses and finish time as views/sl/data/riders.php.
 */
function slpc_riderDaily(int $days, ?int $driverId = null): array
{
    if (!function_exists('sl_rdDoneAt')) require_once CDP_ROOT . '/views/sl/data/riders.php';
    $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
    $done = sl_rdDoneAt('o');
    $w = 'o.is_deleted = 0 AND o.driver_id > 0 AND o.status_courier IN (' . implode(',', SL_RD_DONE) . ')' . sl_rdScope() . ($driverId ? ' AND o.driver_id = ' . (int) $driverId : '');
    $rows = sl_rows("SELECT DATE($done) AS d, COUNT(*) AS n FROM cdb_add_order o WHERE $w AND $done >= :f GROUP BY DATE($done)", [':f' => $from . ' 00:00:00']);
    $by = [];
    foreach ($rows as $r) $by[(string) $r->d] = (int) $r->n;
    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $out[] = ['d' => $d, 'l' => date('D', strtotime($d))[0], 'n' => $by[$d] ?? 0];
    }
    return $out;
}

/** Phone number as a tel: link target (digits and a leading +), or '' when there is none. */
function slpc_tel(?string $phone): string
{
    $p = preg_replace('/[^0-9+]/', '', (string) $phone);
    return strlen((string) $p) >= 6 ? 'tel:' . $p : '';
}

/** WhatsApp link for a phone number, or ''. */
function slpc_wa(?string $phone): string
{
    $p = preg_replace('/[^0-9]/', '', (string) $phone);
    return strlen((string) $p) >= 6 ? 'https://wa.me/' . $p : '';
}
