# Design critique: whole-site layout pass

Date: 2026-11-08. Scope: 245 admin routes from the menu and route list, plus key driver pages. Each was rendered at 1440px, 2000px and 390px (mobile). A Playwright script measured, for every grid or wrapping flex row, the empty space under each card (row bottom minus card bottom). It also checked for horizontal overflow on mobile. Screenshots: `scratchpad/critique/before_*` and `after_*`.

## Overall Impression

The design system is calm and consistent: warm neutrals, one accent, mono numerals, 18px card radius. Most of the "unfinished" feeling came from one problem. Once pages went full width (`.sl-wrap{max-width:none}`), every card grid and main/side split used `align-items:start`. Cards in the same row therefore ended at different heights, and large white holes opened under the shorter cards before the next section. Before this pass, 85 of 245 pages had at least one hole over 120px, and the worst was 6,466px on `dev_logs`. 29 pages also scrolled sideways on a phone. After the pass, the measured holes are 0 and mobile overflow is 0.

## Usability

| Finding | Severity | Recommendation |
|---|---|---|
| Hub pages (AI centre, Billing, Comms, Exec, Last-mile, Loyalty, Security, Support): `.hb-g` card grids leave 200–1,350px holes under short cards | High | Masonry flow, so short cards stack into the free space. The last card in each column extends to the grid's bottom edge. (Fixed) |
| Main/side `.split` layouts (pre-alerts, quote summaries, recipients, rider dispatch, send prices, tools, logs…): side panel ends far above the main list | High | Within 720px: stretch the shorter column's last card so the bottoms line up. Beyond that: make the side panel sticky, and cap it with an inner scroll when it is taller than the screen, so actions like **Send prices** stay reachable. (Fixed) |
| Long send/summary side panels taller than the viewport scroll out of view while working down a 60-row list | Medium | Sticky with an inner scroll (as above). (Fixed) |
| 29 pages scroll horizontally at 390px: consolidation lists (+456px), dev issues (+785px), freight partners, newsletter preview tables, rider fee rows, alert/cron rows, segmented controls | High | Two kinds of fix. Root causes in shared CSS: the screen-reader chart label was positioned off-page, and tick bars did not clip values over 100%. A phone safety net handles the rest: min-width:0 on split children, scrolling segmented controls, and fixed-column rows that reflow. (Fixed) |
| Long lists inside hub cards (taught facts, page ideas) make one card tower over its row | Medium | `.sl-capped` utility (max-height 340px, inner scroll). Applied on AI centre. |
| Customer pages could not be checked: the `customers / Customer123!` login is rejected on this database | Medium | Confirm the seeded customer credentials and re-run the sweep for the customer role. |

## Visual Hierarchy

- Page headers, KPI rows and section headings read well. The KPI cards line up and use one shared pattern (`.sl-metric`).
- Below the KPIs, hierarchy broke down because cards ended at uneven heights. The eye followed the ragged bottom edges instead of the content. Aligned bottoms now give each row a clear end before the next section heading, for example "All 14 features and where they live".
- Card headers that carry a button sit 4–6px lower than headers without one, for example "Page ideas from questions" with its "Analyse questions" button. All `.hb-p` headers now have a 32px minimum height and are vertically centred.
- In the AI centre usage card, six one-line stats were stacked into a tall list. They are now a two-column key/value grid (`.sl-kv2`), which halves the card's height.

## Consistency

| Element | Issue | Recommendation |
|---|---|---|
| Card radius | Page CSS uses 8, 9, 10, 12, 14, 16 and 18px for card-like boxes (12px ×151, 16px ×126, 14px ×116, 18px ×58) | Use only `--r-md` (14) and `--r-lg` (18) for containers and 999px for pills. Replace literal values when you next edit a page. |
| Card padding | `.sl-card` is 18/20px, `.hb-p` 16px, and page cards 14/16, 12/14 or 10/12px | Two tokens: card 18/20 and dense card 12/14. |
| Duplicated layout CSS | `.hb-g`, `.hb-p`, `.fr-split`, `.al-row` and `.cron` are copy-pasted into 8–14 page stylesheets, so a fix in one file does not reach the others | Move them into `swiftlane.css` once and delete the copies. The masonry, balance and phone rules in this pass are already central. |
| Grid alignment | Every grid used `align-items:start`, with no shared rule for equal heights | Shared runtime: `.sl-masonry` for card walls and `.sl-balance` (plus the existing split classes) for side-by-side columns |
| Header controls | Buttons in card headers use `sm` in some places and default size in others | Use `.sl-btn.sm` in every card header. |

## Accessibility

- Disabled feature tiles (`.hb-i.off`) use `opacity:.45`. Their grey-on-grey text falls below 4.5:1 in both themes. Use `--muted` colour on full opacity plus the "Off" pill instead.
- Sticky side panels keep their DOM order, so the keyboard and screen-reader order is unchanged. A panel capped with an inner scroll is focusable through its controls.
- The `.sl-chart-sr` screen-reader text is still announced. It is now anchored at `left:0;top:0`, so it no longer widens the page.
- Segmented controls scroll sideways on phones instead of overflowing. Their touch targets stay at 30px; 44px is recommended on mobile.

## What Works Well

- One clear token set (`--ink`, `--muted`, `--line`, `--card`, radii) with a dark theme that holds up. Every fixed page was re-checked in dark mode.
- KPI strips, pills, mono figures and table styling are consistent across 150 templates.
- Empty states are written as sentences ("No ideas yet. The assistant files one when…") rather than blank boxes. Stretched cards now hold them comfortably.
- Driver pages (dashboard, app, courier and pickup lists) had no gaps or overflow even before this pass.

## Priority Recommendations

1. Merge the duplicated hub and freight layout CSS into `swiftlane.css`. That keeps this pass's fixes from regressing per page.
2. Settle card radius and padding on the two tokens each.
3. Replace opacity dimming on "off" tiles with muted colour, for contrast.
4. Re-run the sweep as the customer role once the credentials are confirmed.
5. Use `.sl-masonry` or `.sl-balance` for new card grids rather than a bare `align-items:start`.

## Fixed in this pass

Shared, in `assets/swiftlane/swiftlane.js`, `swiftlane.css` and `chart-interactions.css`:
- **Masonry flow** for `.hb-g` and the new `.sl-masonry`. Short cards fill the free space and each column's last card meets the grid bottom. Pages: AI centre, Billing hub, Comms hub, Exec dashboard, Last-mile, Loyalty & rewards, Security centre, Support hub.
- **Column balancing** for `.split`, `.fr-split`, `.mt`, `.au`, `.dt`, `.eb`, `.nc-wrap`, `.ix`, `.ho`, `.cpx-featured-grid`, `.grid.cols2/3` and the new `.sl-balance`. It stretches the shorter column, or makes it sticky with a cap when the difference is very large, and re-runs on resize, load and clicks. Pages include: API connections, all payment-mode and SMS/WhatsApp config pages, activity logs, AI studio, API access and traffic, the Control panels and all dashboard_admin_* pages, warehouse dashboard, courier add (client and multiple), customer packages from pre-alert and multiple, users, customers and user permissions, dev deployments, infrastructure, integrations and logs, documents, financial overview, freight partners and schedule, import from Excel, insurance claims, message costs, notifications list, pay checkout, pickup add, pre-alerts, recipients, rider dispatch, rider settings, send prices, shipping addresses, staff activity, support channels, rules and settings, system logs, the email, SMS and WhatsApp templates, tools, warehouse, automations, delivery tracking, newsletter, notification centre, package intake, hand-out packages.
- **New utilities**: `.sl-capped` (scroll cap for long lists) and `.sl-kv2` (two-column key/value). Every `.hb-p` header now has a 32px minimum height so titles line up.
- **Mobile overflow** (now 0 of 245 pages):
  - `.sl-chart-sr` anchored, and `.sl-ticks` set to clip. Together these fixed all the consolidation lists, freight schedule, dev metrics and warehouse delivery.
  - min-width:0 on split and grid children (dev issues, broadcast/push, waybills).
  - Scrolling `.sl-seg` (freight partners, login settings).
  - Reflowed `.al-row`, `.cron`, `.rf-row`, `.ls-sl` and `.wb-h` (dev alerts and infrastructure, which also overflowed at 1440; rider settings; login settings; waybills).
  - `.eb-b` email tables (newsletter), `.sl-head` actions (staff productivity) and `.cx-col` bars (customers).
- **AI centre** (`views/sl/pages/AiCenter.php`, `AiCenter.css`): facts list and page ideas capped with `.sl-capped`, and the usage stats in a two-column grid.

## Not fixed

- **Customer role pages**: the provided `customers / Customer123!` login is rejected, and I did not guess other credentials. They are covered only where admin can open the same view, for example pre-alerts.
- **Radius and padding token clean-up, and de-duplicating the page CSS**: these touch about 100 stylesheets with visible changes everywhere. They are recommended above, not done in a layout pass.
- **Low-contrast "off" tiles** (`opacity:.45`): this changes the meaning the design reference gives them (disabled), so it is left for a design decision.
- **Newsletter at 390px**: a 4px overflow from the user-menu theme switch inside the closed popover. It cannot be seen and does not affect content.
- **Masonry and balancing need JavaScript**: without it, pages fall back to the previous top-aligned grid. Nothing breaks.
