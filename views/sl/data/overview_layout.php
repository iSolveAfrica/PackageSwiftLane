<?php
/** Keep one presentation of the same destination and count on the home page. */
function sl_overviewConsolidate(array $d): array
{
    $count = static fn($value) => str_replace([',', ' '], '', (string) $value);
    foreach (['queues', 'deskStats'] as $source) {
        $remaining = [];
        foreach ($d[$source] ?? [] as $item) {
            $merged = false;
            foreach ($d['brief'] as &$card) {
                // Different filters or counts are different information, even
                // when two links open the same board.
                if ($item['href'] !== $card['href'] || $count($item['n']) !== $count($card['big'])) continue;
                $context = trim($item['label'] . ': ' . $item['sub']);
                if (!in_array($context, $card['context'] ?? [], true)) $card['context'][] = $context;
                $merged = true;
                break;
            }
            unset($card);
            if (!$merged) $remaining[] = $item;
        }
        $d[$source] = $remaining;
    }
    $d['queueTotal'] = array_sum(array_column($d['queues'], 'n'));
    $d['pulse'] = array_values(array_filter($d['pulse'] ?? [], static function ($pulse) use ($d, $count) {
        foreach ($d['deskStats'] as $stat) {
            if ($stat['label'] === $pulse['l'] && $count($stat['n']) === $count($pulse['v'])) return false;
        }
        return true;
    }));
    return $d;
}
