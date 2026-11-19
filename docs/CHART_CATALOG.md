# Swift Lane chart library

The [Design System page](../design_system.php#charts) contains 50 animated chart references from the supplied package. Search or filter the gallery to choose a visual pattern. Each card shows its stable ID; the files live in `assets/swiftlane/chart-references/` as an animated GIF and a static PNG for reduced-motion users. `helpers/sl_chart_catalog.php` is the source of the gallery's names and categories.

The GIFs are **visual examples with sample financial figures**. They are not production data charts. For a page, build the chosen pattern with live, permission-scoped Swift Lane data:

- Wrap the result in `.sl-viz-card` with a colour variant (`--blue`, `--violet`, `--mint` or `--amber`). The reusable card style is in `assets/swiftlane/chart-cards.css` and loads on both the Swift Lane shell and legacy pages. The Design System page shows three sample card layouts.
- Use `helpers/sl_charts.php` for SVG line, area, bars, heat levels and conic segments, and `helpers/sl_chart_interactions.php` for accessible chart data and interactions. The Reports implementation in `views/sl/pages/partials/report_charts.php` is a working example.
- Use `cdp_dashChartCard()` and `cdp_dashChartsRender()` from `helpers/dashboard_data.php` for ApexCharts dashboards. The shared renderer in `dataJs/dashboard_charts.js` supports bar, area, line, donut, rings and heatmap charts.
- Use `--ch-1` through `--ch-6`, `--ch-grid` and `--ch-muted` from `assets/swiftlane/color.css` for series and grid colours. These tokens adapt to the site's light and dark themes.
- Keep the chart title, units, time range, empty state and data source truthful. Respect the page's role and agency permissions before querying metrics. Follow reduced-motion preferences when animating a live chart.

The references are grouped by the question they help answer: trends, comparisons, parts of a whole, progress and targets, activity, and ranges and markets. Finance and crypto labels in the examples illustrate visual treatment only; use shipping, warehouse, customer or finance metrics appropriate to the page.

The staff and admin Overview uses the shared card style in `views/sl/pages/partials/overview_insights.php`. Its 7, 14 and 30 day charts are supplied by `views/sl/data/overview_charts.php`; each query follows the viewer's permissions and agency scope. The overview shows registered air shipments and sea packages, air shipment status, top recipient cities and an 84 day intake heatmap.
