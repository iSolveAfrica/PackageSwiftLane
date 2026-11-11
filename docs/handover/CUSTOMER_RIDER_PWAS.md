# Customer and rider PWAs

Two installable apps are available on the same deployment:

| App | Entry | Manifest ID / scope | Launch screen |
|---|---|---|---|
| Swift Lane — My Parcels | `/apps/customer/` | `/apps/customer/` | Customer home |
| Swift Lane Rider | `/apps/rider/` | `/apps/rider/` | Rider delivery run |

Paths also work beneath a normal application subdirectory. Each app has its own
manifest ID, app name, regular/maskable icons, theme, shortcuts, service-worker
registration, cache namespace, offline screen and installation guidance. The two
apps can be installed separately. A normal Chromium profile reports no manifest
or installability errors for either app on the local secure-context preview.

## Customer experience

Phone home prioritizes ready parcels, pickup codes, delivery requests, bills and
support. It includes tracking search, an active-parcel journey card, a live owned
delivery snapshot (with rider contact when assigned), a concise parcel list and
shipping-address access. Desktop home retains the detailed dashboard.

The new **Parcels** tab combines the customer's air shipments and sea packages,
with search, on-the-way/ready/delivered filters and pagination. Count/list queries
use the same status filter; searches are bound parameters and both sources remain
scoped to the current customer. Parcel details retain the selected bottom tab.

## Rider experience

Phone home puts the next assigned stop first, considering both customer-approved
deliveries and existing rider jobs. The run card shows stops, parcels and completed
jobs, with large navigation, contact and continue actions. International contact
numbers can open WhatsApp without guessing a country code.

Customer-delivery jobs show destination/contact first, one-tap navigation/calling,
collapsible parcel/fee/history details, and a guided PIN → receiver/photo → review
handover. Photo previews remain in memory. Validation, pickup-code verification,
Accounts clearance, private proof storage and transactional completion stay on the
existing server. A submission is not accepted or queued while offline.

The older address-based map preview loads on demand instead of loading a remote
iframe immediately. Configured customer-delivery Google Maps stop markers and
native Google Maps navigation remain available. New PWA work does not bypass the
Google configuration/staging requirements in `COURIER_DELIVERY.md`.

## Routing and authentication

The apps use `/apps/<role>/app.php/<controller>` paths to keep all internal screens
and AJAX requests inside disjoint install scopes. `apps/router.php` adapts existing
controllers; it does not replace or weaken their authorization checks. Local
assets resolve to their original public URLs and existing query/filter links keep
working. External Maps/payment redirects stay external.

Customers cannot open rider content in the rider app and vice versa. A mismatched
session shows a role-switch screen. Staff accounts also cannot use a customer or
rider installation as a staff dashboard. Sign-in, logout, password/OTP pages and
return destinations stay within the chosen scope. History restores revalidate the
session, and PIN/password/file inputs are cleared on leaving an app page.

The apps share the website's same-origin session cookie. They are separate app
identities, not independent simultaneous account sessions. To use two accounts at
once, use separate browser profiles; otherwise use the explicit account-switch
flow. No previous account's private HTML is stored in a service-worker cache.

## Installation and deployment

1. Deploy these files on HTTPS. Localhost is a browser-approved testing exception.
2. Ensure PHP PATH_INFO URLs are supported. These endpoints must return JSON:
   `/apps/customer/app.php/pwa-check.php` and
   `/apps/rider/app.php/pwa-check.php`.
   If a reverse proxy/FastCGI configuration strips PATH_INFO, configure its PHP
   path-info routing. No extra `.htaccess` directives are imposed on the host.
3. Sign in as the relevant customer or rider on a phone. Capable phone browsers
   enter the correct app scope automatically, preserving the current page/query.
   The capability probe keeps the responsive website working if a host does not
   support app routes yet.
4. Tap **Install Swift Lane** or **Install Swift Lane Rider**. Supporting Chromium
   browsers show the native install prompt. Safari/iOS gets Share → Add to Home
   Screen guidance. Each manifest launches only its corresponding app.
5. Verify installation and updates on an actual iPhone and Android device. Test
   sign-in/OTP, account switching, camera capture, Maps, payment-provider return
   and a staged delivery on the deployed HTTPS origin.

The public manifest endpoints must use `application/manifest+json`; workers must
be served as JavaScript without an authentication redirect. Cache/CDN rules must
not cache `app.php` responses, private API responses, manifests as HTML or workers
as immutable files. No database migration is required for this PWA addition.
The earlier courier delivery feature retains its own migration/configuration.

## Offline and update behavior

Workers cache the public offline screen, app icon and versioned first-party UI
assets only. They never cache authenticated pages, pickup codes, API responses,
proof photos, uploads, payments or external Maps requests. A lost connection shows
the correct app's reconnect screen. There is no background mutation queue and no
false offline payment/handover confirmation. In-page failed requests retain the
form so the user can retry.

New workers wait rather than replacing an active app mid-form. An **App update
ready** action applies an update after the user confirms; busy requests prevent
that action. Versioned asset URLs refresh UI resources on subsequent navigation.
When changing worker/offline behavior, bump the worker cache version in
`assets/swiftlane/pwa/worker.js`.

These PWAs do not add Web Push subscriptions, background GPS, offline maps or
independent origin cookies. Existing in-app/external notification services remain.

## Design approach and verification

The interfaces use established courier/ride-hailing patterns: next-stop focus,
actions reachable with one hand, route/contact controls beside the destination,
visible progress and a deliberate proof-of-delivery review. Public Uber Driver and
DoorDash Dasher feature pages were requested as references, but this workspace's
network policy blocked access. No current-screen comparison is claimed.

Checks performed:

- `node scripts/check_pwa_runtime.cjs http://localhost:8084`: actual public PHP
  manifests, PNG sizes, distinct scopes/IDs, scoped workers and installation help,
  isolated caches, role-specific offline fallback, correct reconnect destinations,
  no private caching and no offline mutation queue. Also checks Chromium manifest
  and native installation eligibility in a disposable normal browser profile.
- `node scripts/check_pwa_handover.cjs`: real guided handover JavaScript, validation,
  photo preview, receiver review and online-only confirmation, without backend writes.
- `php scripts/check_customer_parcels.php`: disposable test/audit DB; owner scope
  across air/sea, count/list parity, filters, stable pagination and bound search.
- Authenticated browser sweep: 17 customer/rider page states at 360, 390, 430,
  768 and 1440px, including populated delivery/PIN/proof flow, internal POST
  routing and form preservation after a rejected submission. No viewport overflow,
  PHP errors or uncaught page JavaScript errors.
- Account tests: role mismatch for pages/AJAX, blocked private/traversal routes,
  scoped logout/history and restoration of a customer deep link after login.
- Existing design/mobile/runtime, delivery workflow, customer-code, rider-route,
  checkout, route and page-shell checks, plus PHP/JavaScript syntax and diff checks.

Browser checks use Chromium, test accounts and a disposable local database.
Google SDK responses were stubbed where needed. No real customer messages,
payments or delivery confirmations were sent. Real-device/native integration
checks and live Google/payment behavior remain deployment verification items.
