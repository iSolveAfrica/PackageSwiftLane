# Mobile experience and UI polish — 10 November 2026

Based on `temp` at `d973f080`, including its recent mailbox, grouped-navigation,
chart and layout work. The preceding UI audit fixes are included in this branch.
The review target is `temp`.

## Changes

- Shared customer and rider phone navigation on both design pages and the legacy
  bridge. Customer destinations: Home, Track, Codes, Bills (when permitted),
  Support. Rider destinations: Jobs, Route, Earnings, Alerts, Account. Active
  destinations, safe-area padding, keyboard clearance and drawer focus handling
  are included. Viewport/pointer capability detection handles phones and rotation
  without brittle browser-name detection. Desktop/tablet shell geometry is retained.
- Customer home puts tracking and collection first, with a latest-parcel card,
  four quick actions, a ready-for-pickup link and readable parcel cards. Compact
  metric grids, wrapping profile actions, email wrapping, larger address copy
  buttons and mobile bill rows reduce scrolling and horizontal overflow.
- New `pickup_codes.php`: a private, non-cacheable wallet for the current customer's
  active/verified, unexpired codes with unused parcels. Both code and parcel
  ownership are enforced. Used, void, expired and foreign codes are excluded.
  No code issuance or notification is triggered by viewing this page.
- Rider home puts the in-progress/oldest assigned stop first on a phone, followed
  by a large address-based Google Maps preview, numbered selectable stops, and
  call/navigation/handover actions. Each preview shows the next two known
  addresses and their connecting route; individual markers and external
  navigation are available for every known-address stop. Missing addresses,
  empty routes and unavailable jobs have explicit states. No fabricated GPS
  position, optimized route, ETA or live rider location is shown.
- Corrected rider contact/address selection: pickups use the sender and pickup
  address; deliveries use the recipient and delivery address. Shipment snapshots
  take priority over the exact saved address ID, rather than picking an arbitrary
  recipient address. Rider scope and the existing handover/code gates remain.
- Native page transitions where supported, one-time scroll entrances, hover/press
  feedback and visible busy/offline states. Content remains available without
  motion or JavaScript. Reduced-motion preferences disable the new animations.
  Entrances do not leave translated containing blocks around menus/dialogs.
- Mobile checkout/handover actions remain reachable above the navigation bar.
  Checkout ignores out-of-order bill responses and prevents duplicate requests
  and selection changes while checkout is pending. Errors unlock the controls.
  Handover prevents duplicate submissions; camera inputs are keyboard accessible.
- Retained preceding audit fixes: route destinations, responsive overflow,
  support entry, menus, hidden states, focus, validation and request errors.
  See `UI_UX_AUDIT_2026_11_07.md` for that work.

## Validation

- Local authenticated sweep: 131 page states (111 administrative/public routes,
  15 customer states, 5 rider states) at 1440, 1024, 768, 390 and 360px.
  One 360px profile overflow was fixed. All 20 customer/rider states were then
  swept again at all five widths, with no page overflow, uncaught JavaScript
  errors or displayed PHP errors. The only non-200 was the expected 404 for
  a shipment-detail URL without a required record ID.
- Customer and rider bottom-navigation links and available sidebar destinations
  were checked under their respective sessions. New code wallet was checked
  for a forged owner query parameter, no-store response and rider denial.
- Functional browser checks cover the real customer code page, drawer inertness,
  desktop hiding of phone navigation, populated rider routes, stop selection,
  handover form and unavailable jobs.
- `node scripts/check_design_runtime.cjs`
- `node scripts/check_mobile_experience.cjs`
- `node scripts/check_checkout_feedback.cjs`
- `node scripts/check_design_routes.cjs` (132 routes)
- `node scripts/check_page_shell.js`
- `php scripts/check_pickup_code_wallet.php`
- `php scripts/check_rider_routes.php`
- PHP syntax checks, JavaScript syntax checks and `git diff --check`.

The PHP integration checks require a disposable test/audit database and roll back
all fixture changes. Browser regressions use local provider stubs where needed.
The manual integration run used only `swiftlane_ui_audit` and test accounts;
no customer messages or live payments were sent. Local screenshots and sweep
results are in the ignored `dev_reports/mobile-ux-2026-11-10/` directory.

## Deployment checks still needed

This workspace's outbound policy does not allow Google Maps or Paystack. Live map
rendering and directions accuracy, hosted payment completion, and real phone
camera behavior must be checked in staging. The map iframe is lazy-loaded;
plain address details and external navigation links remain available alongside
it. This change provides a responsive web app experience, not offline maps,
background location tracking or an installable native application. Browser
regressions ran in Chromium; Safari/iOS and Android device checks remain.
