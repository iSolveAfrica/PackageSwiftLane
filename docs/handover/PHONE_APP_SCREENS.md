# Phone app screens

The owner's rule: **computers and tablets get the web design; phones get the mobile app design** (design canvas
"Swift Lane UI Redesign", Page 2, the 154 `M-*` screens). Phones are also asked to install the app.

## How a request is served

1. `helpers/sl_device.php` reads the device (User-Agent, client hints, and the `sl_dev` cookie that
   `assets/swiftlane/pwa/device.js` writes after looking at the real browser). `sl_isPhone()` is true for phones
   only. `?view=web` (the "View the full website" row on the phone menu) opts a phone out for 30 days;
   `?view=app` returns.
2. Every page renders through `sl_render('View', $page, $d)` (`helpers/sl_view.php`). On a phone, when
   `views/sl/phone/View.<role>.php` or `views/sl/phone/View.php` exists (role = `customer | rider | staff | public`),
   that template is rendered inside the design's frame (`views/sl/phone/_start.php`, `_end.php`) **with the same
   `$d` the controller built for the web page**. Otherwise the web page is shown.
3. Signed-in phone browsers move into the installed-app scope `apps/<role>/app.php/<page>`
   (`assets/swiftlane/pwa/app.js`, `apps/router.php`) and get the install screen with steps for their exact browser.

Legacy controllers that do not call `sl_render` add one line after their permission checks:
`if (sl_phoneView('X') !== '') { sl_render('X', [...], $d); exit; }` (see `consolidate_view.php`,
`master_view.php`, `prealert_add.php`).

## Files

| What | Where |
|---|---|
| Device detection, maps links, install steps per browser | `helpers/sl_device.php`, `assets/swiftlane/pwa/device.js`, `device.css` |
| Phone frame, tab bars (staff, developer, customer pill, rider pill) | `helpers/sl_phone.php`, `views/sl/phone/_start.php`, `_end.php` |
| Every design component (mx-, ra-, rb-, rc-, rd-, rs-, cd-, b1–b4) | `assets/swiftlane/pwa/mx.css` (copied from the design; do not restyle components per page) |
| Small helpers for cards, rows, KPIs, switches | `helpers/sl_mx.php`; shared JS `dataJs/sl/mx.js` |
| Phone templates | `views/sl/phone/*.php` (one per web view, or per role) |
| Per-group extras | `assets/swiftlane/pwa/phone-*.css`, `dataJs/sl/phone/*.js`, `views/sl/data/phone_*.php` |
| Which design screen is served by which page | `views/sl/data/app_screens.php`, shown in Settings › App Screens |

## Screens that exist only in the mobile design

They also have a web page (the same components laid out in two columns on a computer):
rider app (`driver_app.php`, `rider_*.php`), customer `my_account.php`, `notifications.php`, `my_rewards.php`,
`delivery_pricing.php`, `delivery_done.php`, everyone's `account_security.php`, staff `shipping_tariffs.php`,
and the phone menu `menu.php` (phones only).

Database: `migrations/2026_11_27_rider_app.sql` (payout requests, quests, shifts, rider prefs) and
`migrations/2026_11_27_rider_ops.sql` (rider status log, scan checklist), included in `database/*.sql`.

## Rules for a new or changed screen

- Use the design's markup verbatim and fill it from `$d`. Never ship the design's sample values; when the system
  has no data for a block, show an honest empty state or leave the block out.
- Writes go to the existing endpoints with the web form's field names.
- Check at 390 px with a phone User-Agent (phone screen) and at 1440 px (unchanged web page).

## Known gaps (no backend yet)

Rider: self-claiming jobs, decline, boosts, heat map, tips, star ratings, cash on delivery amounts, instant cash-out,
staff screens for quests and the emergency number. Customer: referral code at sign-up, delivery time slots and
speed options, promo codes, close account. Staff: sign-in alerts, ending other sessions, a public quote calculator
fed by the freight rate, payment gateway on/off from the phone. Door-delivery earnings need an exchange rate when
delivery is priced in a different currency from the books.
