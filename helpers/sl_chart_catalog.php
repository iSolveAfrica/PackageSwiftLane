<?php
/**
 * Animated chart references supplied for the Swift Lane design system.
 * These are examples, not live metrics. Build production charts with real data
 * and the shared chart tokens/helpers instead of embedding sample figures.
 */
function sl_chartCatalog(): array
{
    $groups = [
        'Trends' => [
            'btc-24h', 'cash-flow', 'coin-lines', 'daily-spend', 'earnings-glow',
            'expected-earnings', 'fx-rate', 'gas-tracker', 'gradient-line',
            'income-expense', 'liquidity-waves', 'revenue-cost', 'sentiment-lines',
            'volatility-waves', 'yield-curve',
        ],
        'Comparisons' => [
            'allocation-stack', 'budget-bars', 'expense-stack', 'top-holdings',
            'volume-bars', 'trades-count',
        ],
        'Parts of a whole' => [
            'coin-rings', 'dominance-rings', 'market-pie', 'portfolio-donut',
            'spend-doughnut',
        ],
        'Progress and targets' => [
            'budget-left', 'budget-pills', 'credit-gauge', 'credit-score',
            'fear-greed', 'goal-rings', 'loan-track', 'profit-target',
            'risk-zone', 'saving-streak', 'savings-ring', 'signals', 'trades-arc',
        ],
        'Activity' => [
            'activity-heatmap', 'block-grid', 'dca-dots', 'mining-dots',
        ],
        'Ranges and markets' => [
            'candlestick', 'market-depth', 'market-session', 'price-band',
            'price-ruler', 'range-52w', 'range-selector',
        ],
    ];

    $titles = [
        'btc-24h' => 'BTC 24h', 'dca-dots' => 'DCA dots',
        'fx-rate' => 'FX rate', 'range-52w' => '52-week range',
    ];
    $catalog = [];
    foreach ($groups as $group => $ids) {
        foreach ($ids as $id) {
            $catalog[] = [
                'id' => $id,
                'title' => $titles[$id] ?? ucwords(str_replace('-', ' ', $id)),
                'group' => $group,
                'gif' => 'assets/swiftlane/chart-references/' . $id . '.gif',
                'poster' => 'assets/swiftlane/chart-references/' . $id . '.png',
            ];
        }
    }
    return $catalog;
}
