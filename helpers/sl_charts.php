<?php
/**
 * Tiny SVG chart helpers for the Swift Lane dashboards. They produce the same
 * primitives the approved design draws by hand (polylines, areas, bars, heatmaps).
 */

/** Scale values to y coordinates (0 at the bottom). */
function sl_chartScale(array $values, float $h, float $top = 0, ?float $max = null): array
{
    $max = $max ?? (max($values ?: [0]) ?: 1);
    $max = $max > 0 ? $max : 1;
    return array_map(fn($v) => round($h - ((float) $v / $max) * ($h - $top), 1), $values);
}

/** "x,y x,y …" for a polyline across the width. */
function sl_svgPoints(array $values, float $w, float $h, float $top = 0, ?float $max = null, float $padX = 0): string
{
    $n = count($values);
    if ($n === 0) {
        return '';
    }
    $ys = sl_chartScale($values, $h, $top, $max);
    $pts = [];
    foreach ($ys as $i => $y) {
        $x = $n > 1 ? $padX + ($w - 2 * $padX) * $i / ($n - 1) : $w / 2;
        $pts[] = round($x, 1) . ',' . $y;
    }
    return implode(' ', $pts);
}

/** Smooth-ish path "M x,y L …" for a line chart. */
function sl_svgLinePath(array $values, float $w, float $h, float $top = 0, ?float $max = null, float $x0 = 0): string
{
    $n = count($values);
    if ($n === 0) {
        return '';
    }
    $ys = sl_chartScale($values, $h, $top, $max);
    $d = '';
    foreach ($ys as $i => $y) {
        $x = $n > 1 ? $x0 + ($w - $x0) * $i / ($n - 1) : $w / 2;
        $d .= ($i === 0 ? 'M' : ' L') . round($x, 1) . ',' . $y;
    }
    return $d;
}

/** Closed area path under a line. */
function sl_svgAreaPath(array $values, float $w, float $h, float $top = 0, ?float $max = null, float $x0 = 0): string
{
    $line = sl_svgLinePath($values, $w, $h, $top, $max, $x0);
    if ($line === '') {
        return '';
    }
    return $line . ' L' . $w . ',' . $h . ' L' . $x0 . ',' . $h . ' Z';
}

/** Heat level 0-3 for a daily count relative to the series peak. */
function sl_heatLevel($count, $peak): string
{
    $count = (int) $count;
    if ($count <= 0) return 'h0';
    $peak = max(1, (int) $peak);
    $r = $count / $peak;
    if ($r >= 0.66) return 'h3';
    if ($r >= 0.33) return 'h2';
    return 'h1';
}

/**
 * Daily counts for the last $days days from a table. Returns [date => count] with gaps filled.
 */
function sl_dailyCounts(string $table, string $dateCol, int $days, string $where = ''): array
{
    $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
    $rows = sl_rows("SELECT DATE($dateCol) AS d, COUNT(*) AS c FROM $table WHERE $dateCol >= :from $where GROUP BY DATE($dateCol)", [':from' => $from]);
    $map = [];
    foreach ($rows as $r) {
        $map[$r->d] = (int) $r->c;
    }
    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-$i days"));
        $out[$day] = $map[$day] ?? 0;
    }
    return $out;
}

/** Monthly totals (12 entries, Jan..Dec) for the given year. */
function sl_monthlyCounts(string $table, string $dateCol, int $year, string $where = '', string $expr = 'COUNT(*)'): array
{
    $rows = sl_rows("SELECT MONTH($dateCol) AS m, $expr AS c FROM $table WHERE YEAR($dateCol) = :y $where GROUP BY MONTH($dateCol)", [':y' => $year]);
    $out = array_fill(1, 12, 0);
    foreach ($rows as $r) {
        $out[(int) $r->m] = (float) $r->c;
    }
    return array_values($out);
}

/** conic-gradient() string for a donut from [[color, value], ...]. */
function sl_conic(array $segments): string
{
    $total = array_sum(array_map(fn($s) => (float) $s[1], $segments)) ?: 1;
    $acc = 0;
    $parts = [];
    foreach ($segments as $s) {
        $pct = (float) $s[1] / $total * 100;
        $parts[] = $s[0] . ' ' . round($acc, 1) . '% ' . round($acc + $pct, 1) . '%';
        $acc += $pct;
    }
    return 'conic-gradient(' . implode(', ', $parts) . ')';
}
