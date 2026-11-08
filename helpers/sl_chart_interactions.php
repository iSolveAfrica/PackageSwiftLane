<?php
/** Metadata contains only the values used in the rendered chart. Never infer
 * counts from pixel heights: a minimum-height zero bar still represents zero. */
function sl_chartAttrs(array $series, array $labels = [], string $title = '', ?float $start = null, ?float $end = null, string $kind = 'linear'): string
{
    $data = ['series' => $series, 'labels' => $labels, 'title' => $title, 'start' => $start, 'end' => $end, 'kind' => $kind];
    return 'data-sl-chart="' . htmlspecialchars(json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE), ENT_QUOTES, 'UTF-8') . '"';
}

/** Dated bars for the compact metric sparklines. */
function sl_sparkBars(array $values, int $minimum = 4, array $labels = []): array
{
    $peak = max(1, max($values ?: [0])); $out = []; $n = count($values);
    foreach (array_values($values) as $i => $value) {
        $label = $labels[$i] ?? date('M j, Y', strtotime('-' . ($n - 1 - $i) . ' days'));
        $out[] = ['s' => 'height:' . max($minimum, round(100 * (float) $value / $peak)) . '%', 't' => $label . ': ' . number_format((float) $value, (float) $value == (int) $value ? 0 : 2)];
    }
    return $out;
}
