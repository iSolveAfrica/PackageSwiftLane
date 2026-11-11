# Customer-approved courier deliveries

The delivery flow covers owned air shipments and sea packages:

1. A ready-status message offers **Request delivery**. Customers can also open it
   from their dashboard, Pickup codes or the customer menu.
2. Customers select parcels from one agency/shipment type, choose a Google Maps
   building/address/landmark, confirm the pin, and enter contact/entrance details.
3. The server resolves the place independently with Google Geocoding and obtains
   driving distance from Google Routes. Browser coordinates, distances and prices
   are never used for the quote. Out-of-country, city-only, unserviceable and
   unroutable locations are rejected.
4. A 15-minute quote shows the destination, origin, road distance, package count,
   pricing model, currency and fee breakdown. No dispatch occurs before approval.
5. Approval reserves every parcel atomically. An active zone rider in the same
   agency is assigned, otherwise the request waits in **Delivery requests**.
6. Assigned riders see every request/parcel in **Customer deliveries**, including
   both air and sea jobs, a map with numbered stops and precise Maps navigation.
7. Rider starts require Accounts clearance for air shipments. Failed attempts
   record a reason and return to dispatch for reassignment. Completion requires
   all parcels, customer pickup code(s), receiver name and a handover photo.
   Parcel tracking, code consumption and job completion commit together.

## Pricing and zones

Open **Delivery pricing and Maps** from Last-Mile, Rider dispatch or Rider fees.
Managers choose the active pricing policy:

- **Zone:** base fee + zone fee, charged once per trip.
- **Kilometre:** base fee + Google driving metres / 1000 × kilometre rate.
- **Package:** base fee + selected package count × package rate.

The minimum trip fee applies to each mode. Calculations use integer currency
minor units. Approved prices, rider share, currency and destination are snapshots;
rate/address changes do not silently reprice an approved delivery.

Zones support polygons, radius coverage, exact Google locality names, priorities,
agency scope and a preferred active rider. Polygon matches take precedence over
radius matches, then locality matches. Equal-ranked overlaps fail explicitly.
The older city-based dispatch path now requires exact city matches and excludes
parcels reserved for customer deliveries; inactive riders are not assigned.

Delivery fees are separate from shipping invoices. The current collection method
is payment on delivery: riders record whether the approved fee was collected and
dispatch can review outstanding/collected fees. This does not mark a shipping
invoice as paid. New delivery rider shares are shown on the job; they are not
silently mixed into the older mutable rider-fee settlement calculation.

## Install and activate

The feature ships disabled; no live rates or credentials are guessed.

1. Apply `database/upgrade_existing_database.sql`, or the additive
   `migrations/2026_11_11_courier_delivery.sql` on an already-current installation.
   Fresh-install and upgrade packages have been rebuilt from the migration sources.
2. In Google Cloud, enable billing, Maps JavaScript API, Places API (New),
   Geocoding API and Routes API. Create a browser key restricted to the site
   origins and a separate server key restricted to the server/API services.
3. Save keys in **Delivery pricing and Maps**. Server key values are never
   returned in forms or customer markup and are redacted from activity snapshots.
   Environment overrides: `CDP_GOOGLE_MAPS_BROWSER_KEY`,
   `CDP_GOOGLE_MAPS_SERVER_KEY`. Leave key fields blank to preserve existing keys.
4. Set warehouse Google place ID, country, currency, rates, maximum road distance,
   ready-status IDs and service zones. Optional agency origins map agency IDs to
   their own warehouse Google place IDs.
5. Configure a private writable proof directory outside the document root.
   `CDP_DELIVERY_PROOF_DIR` overrides the default sibling `delivery-proof` folder.
   Photo access goes through an authenticated, owner/rider/agency-scoped controller.
6. Install a once-per-minute cron command:
   `php /path/to/app/scripts/delivery_notifications.php --send`.
   Without `--send`, this command is a dry run. Configure the existing WhatsApp,
   SMS and email providers and inspect Message Logs for delivery results.
7. Enable delivery requests. Verify a real Google address/road quote, one test
   notification per configured channel and a staged handover on a real phone.

Existing ready-message pipelines carry the request link in email/WhatsApp/SMS;
ready-at-customs labels participate in ready messaging. New delivery progress and
rider assignment notifications are written in the same transaction as the event.
In-app notifications are immediate; external messages use the durable outbox.
The worker serializes sends, retries failed channels (not successful ones), caps
attempts at five and records channel responses. A process crash between a provider
accepting a message and saving its result may cause a retry; external delivery is
at-least-once, not an exactly-once guarantee.

## States, permissions and safeguards

States: quoted → pending/assigned → onroute → delivered. A failed attempt returns
to dispatch for reassignment. Customers can cancel before a trip starts. Dispatch
can recall an on-route job with a reason after confirming parcel custody.

Customer identity and rider identity always come from the session. Dispatch is
permission-gated and agency-scoped. Global pricing/zone configuration excludes
agency-restricted accounts. Unique active parcel claims prevent concurrent double
booking. Legacy assignment cannot take over a reserved parcel. Closed/changed
parcels, stale quotes and unrelated customers/riders are rejected. Accounts and
pickup-code protections are preserved. Uploads validate actual image MIME/type,
size, private storage and a generated filename.

## Validation

- `php scripts/check_delivery_pricing.php`: pricing modes, minimum fare, road-metre
  rounding, parcel counts, exact locality matching, ambiguity, priority and polygons.
- `php scripts/check_delivery_google.php`: place/country/precision validation,
  Routes request fields, server distance and unavailable route.
- `php scripts/check_delivery_workflow.php`: disposable test/audit DB only;
  owned air/sea parcels, Google verification, immutable quote, expiry, duplicate
  approval/competing quote rejection, assignment/fallback, rider access, start,
  failure/retry, pickup-code gate, proof metadata, collection and tracking.
- `node scripts/check_delivery_ui.cjs`: real browser runtime with a stub Google
  SDK; module selection, verified place selection, edited-location reset and
  authentication failure.
- Authenticated local UI checks: 12 customer/rider/manager page states at 1440,
  1024, 768, 390 and 360px; a narrow settings-code overflow was fixed. Location
  selection/quote payload, server-key privacy and role denial were checked.
- Existing design runtime, mobile experience, route, page-shell, pickup wallet
  and rider-route checks pass, with PHP/JS syntax and whitespace checks.
- New migration ran twice without altering existing records.

Google SDK and server transports were stubbed in automated tests. No live Google
billing, real customer messages or actual deliveries were performed. API keys,
production migration/cron installation and the real-phone/provider staging checks
above are activation requirements, not work claimed as already deployed.
