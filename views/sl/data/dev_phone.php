<?php
/**
 * Small helpers for the developer console phone screens (views/sl/phone/Dev*.php, MessageCosts.php):
 * design sparklines (.mx-sp), bar charts (.mx-bc) and status pills from data the web pages already
 * build. Reads only what the console pages read (request recorder helpers/dev_metrics.php), after the
 * page's own gate has run.
 */

/** Heights (0-100) of the last $n values for a .mx-sp sparkline; nulls count as 0. Empty when nothing was measured. */
function sl_phDevSpark(array $vals, int $n = 12): array
{
    $vals = array_slice(array_values($vals), -$n);
    $nums = array_map(fn($v) => $v === null ? 0.0 : (float) $v, $vals);
    $has = count(array_filter($vals, fn($v) => $v !== null)) > 0;
    $mx = $nums ? max($nums) : 0;
    if (!$has || $mx <= 0) {
        return [];
    }
    $out = [];
    $last = count($nums) - 1;
    foreach ($nums as $i => $v) {
        $out[] = ['h' => max(6, (int) round($v / $mx * 100)), 'c' => $i === $last ? 'now' : ''];
    }
    return $out;
}

/** Prints a .mx-sp sparkline (nothing when there is no data). */
function sl_phDevSparkHtml(array $sp): string
{
    if (!$sp) {
        return '';
    }
    $o = '<div class="mx-sp" aria-hidden="true">';
    foreach ($sp as $q) {
        $o .= '<i class="' . h($q['c']) . '" style="height:' . (int) $q['h'] . '%"></i>';
    }
    return $o . '</div>';
}

/**
 * Groups a series into $n bars for a .mx-bc chart: each bar is the sum ($agg 'sum') or the largest value
 * ($agg 'max') of its group, labelled with the group's first label. The last bar is the current one.
 */
function sl_phDevBars(array $vals, array $labels, int $n = 7, string $agg = 'sum'): array
{
    $vals = array_values($vals);
    $labels = array_values($labels);
    $cnt = count($vals);
    if (!$cnt) {
        return [];
    }
    $n = min($n, $cnt);
    $size = (int) ceil($cnt / $n);
    $start = $cnt - $size * $n; // drop the oldest points so every bar covers the same span
    $bars = [];
    for ($b = 0; $b < $n; $b++) {
        $from = $start + $b * $size;
        $vs = [];
        for ($i = max(0, $from); $i < $from + $size && $i < $cnt; $i++) {
            if ($vals[$i] !== null) {
                $vs[] = (float) $vals[$i];
            }
        }
        $v = $vs ? ($agg === 'max' ? max($vs) : array_sum($vs)) : null;
        $bars[] = ['v' => $v, 'l' => (string) ($labels[max(0, $from)] ?? '')];
    }
    $mx = max(array_map(fn($x) => (float) ($x['v'] ?? 0), $bars));
    if ($mx <= 0) {
        return [];
    }
    $last = count($bars) - 1;
    foreach ($bars as $i => &$x) {
        $x['h'] = $x['v'] === null ? 0 : max(3, (int) round($x['v'] / $mx * 100));
        $x['c'] = $i === $last ? 'now' : '';
    }
    unset($x);
    return $bars;
}

/** Prints the .mx-bc bars of a .mx-bars card ($fmt formats a value for the accessible title). */
function sl_phDevBarsHtml(array $bars, callable $fmt): string
{
    $o = '<div class="mx-bc">';
    foreach ($bars as $b) {
        $o .= '<span class="' . h($b['c']) . '" title="' . h($b['l'] . ': ' . ($b['v'] === null ? 'no data' : $fmt($b['v']))) . '"><i style="height:' . (int) $b['h'] . '%"></i><small>' . h($b['l']) . '</small></span>';
    }
    return $o . '</div>';
}

/** Console status class (ok | warn | bad | idle | off) -> design pill class. */
function sl_phDevPill(string $cls): string
{
    return ['ok' => 'delivered', 'warn' => 'pending', 'bad' => 'overdue', 'idle' => 'transit'][$cls] ?? 'plain';
}

/** Request series of the recorder for a range (cached per request). */
function sl_phDevSeries(string $range): array
{
    static $memo = [];
    if (!isset($memo[$range])) {
        $memo[$range] = function_exists('cdp_reqmSeries') ? cdp_reqmSeries($range) : ['points' => [], 'samples' => 0];
    }
    return $memo[$range];
}

/** "More actions" in the app bar of a developer phone screen: the same page in the full web layout. */
function sl_phDevMore(string $label = 'Open the full page'): string
{
    return sl_phoneIcon('more', $label, sl_url(['view' => 'web']));
}
