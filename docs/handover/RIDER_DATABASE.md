# Rider side: database reference

Everything the rider app, dispatch, door delivery, route planning and rider pay read or write. Riders are rows in
`cdb_users` with `userlevel = 3` (role flag `cdb_user_roles.is_driver`).

## 1. The rider

| Table / columns | What it holds | Created by |
|---|---|---|
| `cdb_users`: `userlevel`, `phone`, `vehicle_type`, `enrollment` (plate), `zone`, `document_number`, **`rider_status`** (online / offline) | The rider's account and vehicle. `rider_status` is the Online switch; door deliveries are not assigned to an offline rider. | base schema, `2026_11_06_people_staff` |
| `cdb_driver_files` (id, driver_id, url, name, file_type, date_file) | Documents uploaded for a rider | base schema |
| `cdb_rider_prefs` (rider_id + pref_key → pref_value) | Rider settings: `nav_app` (google / waze), `offer_sounds`, `payout_method` / `payout_provider` / `payout_name` / `payout_number`, emergency contact. **rider_id 0** holds site-wide settings, e.g. `emergency_number` for SOS. | `2026_11_27_rider_app` |
| `cdb_rider_status_log` (id, user_id, online, changed_at) | Every online / offline change ("online since 11:54") | `2026_11_27_rider_ops` |
| `cdb_rp_vehicles` (driver_id PK, vehicle_type, plate, make_model, max_kg, max_l, max_parcels, cargo size, can_fragile / can_cold / can_high_value, docs_json, active) | Vehicle capacity used by route planning | `2026_11_26_route_planner` |

## 2. Jobs given to riders

| Table / columns | What it holds | Created by |
|---|---|---|
| `cdb_add_order.driver_id` | Air shipments and home pickups assigned to a rider | base schema |
| `cdb_customers_packages.driver_id` | Sea packages assigned to a rider | base schema |
| `cdb_consolidate.driver_id`, `cdb_consolidate_packages.driver_id` | Consolidations handed to a rider | base schema |
| `cdb_delivery_requests` (id, customer_id, agency_id, module, parcel_json, address, lat, lng, distance_m, zone_id, currency, amount_cents, **rider_cents**, state, **driver_id**, contact_name / phone, instructions, approved_at, assigned_at, receiver_name, proof_photo, fee_collected, completed_at) | Customer door deliveries. state: quoted → approved → assigned → onroute → delivered / failed / cancelled. `rider_cents` = the rider's share. | `2026_11_11_courier_delivery` |
| `cdb_delivery_items` (request_id, module, order_id, tracking) | Parcels in each door delivery | `2026_11_11_courier_delivery` |
| `cdb_delivery_claims` (module, order_id → request_id) | Lock so one parcel is in only one door delivery | `2026_11_11_courier_delivery` |
| `cdb_delivery_events` (request_id, actor_id, state, note, created_at) | Timeline of each door delivery (start, on route, delivered, failed) | `2026_11_11_courier_delivery` |
| `cdb_delivery_outbox` (event_key, recipient_id, subject, message, state, attempts) | Messages queued to customers about their delivery | `2026_11_11_courier_delivery` |
| `cdb_rider_scans` (user_id + module + order_id, tracking, scanned_at) | Parcel scan checklist at pickup (Scan tab) | `2026_11_27_rider_ops` |
| `cdb_comm_delivery_slots` (user_id, entity_ref, slot_date, slot_window, status, driver_id) | Delivery time slots customers chose | created by the app on first use (`helpers/comms.php`), not in a migration |
| `cdb_delivery_time` (id, delitime, detail) | Delivery time options | base schema |

## 3. Door delivery prices and zones

| Table | What it holds | Created by |
|---|---|---|
| `cdb_delivery_config` (config_json, revision) | Door delivery on/off, currency, base / per-km / per-parcel / minimum price, **rider_pct** (rider's share), max km, Google keys | `2026_11_11_courier_delivery` |
| `cdb_delivery_zones` (name, city_match, fee, **rider_id**, active) | Delivery zones with flat fee and default rider | `2026_11_06_freight_devops` |
| `cdb_delivery_zone_rules` (zone_id, polygon_json, centre_lat / lng, radius_km, agency_id) | Map shape of each zone | `2026_11_11_courier_delivery` |

## 4. Route planning and live position

| Table | What it holds | Created by |
|---|---|---|
| `cdb_rp_plans` (plan_date, agency_id, status, params_json, proposal_json, confirmed_by) | A day's delivery plan | `2026_11_26_route_planner` |
| `cdb_rp_routes` (plan_id, driver_id, route_date, status, total_m, total_s, load_kg / l / n, polyline, started_at, finished_at) | One rider's route for the day | `2026_11_26_route_planner` |
| `cdb_rp_stops` (route_id, request_id, seq, lat, lng, address, contact, window, eta, status, arrived_at, done_at, fail_reason) | Stops on a route, in order | `2026_11_26_route_planner` |
| `cdb_rp_load` (route_id, module, order_id, stop_id, tracking, load_state, loaded_at, outcome) | Loading checklist: which parcel is on which route | `2026_11_26_route_planner` |
| `cdb_rp_parcel_meta` (module, order_id, size, weight, volume, handling, value, window, priority) | Parcel size and handling for planning | `2026_11_26_route_planner` |
| `cdb_rp_activity` (driver_id, route_id, state, started_at, ended_at) | Rider activity on a route | `2026_11_26_route_planner` |
| `cdb_rp_pings` (driver_id, route_id, lat, lng, accuracy_m, speed_mps, heading, battery, recorded_at) | GPS points during a route (opt-in) | `2026_11_26_route_planner` |
| `cdb_rp_settings` (settings_json), `cdb_rp_geocache` (address → lat, lng) | Planner settings and address cache | `2026_11_26_route_planner` |
| `cdb_rider_positions` (driver_id PK, request_id, latitude, longitude, recorded_at) | Last known position of each rider (opt-in live map) | `2026_11_27_rider_positions` |

## 5. Rider pay

| Table | What it holds | Created by |
|---|---|---|
| `cdb_rider_fee_rules` (rule_key, label, rule_type, amount, **rider_pct**, condition_json, active) | Fee per job and the rider's share (Rider fees page) | `2026_11_06_people_staff` |
| `cdb_rider_settlements` (driver_id, period_start, period_end, deliveries, pickups, gross, rider_share, status, paid_at, paid_by) | Pay the office settled per period | `2026_11_06_people_staff` |
| `cdb_rider_payout_requests` (rider_id, amount, note, status requested / approved / paid / declined / cancelled, staff_note, decided_by, paid_by, paid_at) | Payouts riders ask for in the Wallet; staff act on them on the rider's page | `2026_11_27_rider_app` |
| `cdb_rider_quests` (title, target_trips, reward, starts_at, ends_at, active, created_by) | Quests set on the Rider fees page | `2026_11_27_rider_app` |
| `cdb_rider_shifts` (rider_id, day, start_time, end_time, hub, status booked / cancelled) | Delivery blocks riders book | `2026_11_27_rider_app` |

**What a rider is owed** (`helpers/rider_money.php` `sl_rmBalance()`): the rider's share of every finished job
(fee rules for shipments and pickups, `rider_cents` for door deliveries) minus paid settlements minus paid payout
requests.

## Migrations to run on live for the rider side

All are inside `database/upgrade_existing_database.sql`; the newest five are also in
`database/live_update_2026_11_27.sql`.

| Migration | Rider tables |
|---|---|
| `2026_11_06_people_staff.sql` | `cdb_rider_fee_rules`, `cdb_rider_settlements`, rider columns on `cdb_users` |
| `2026_11_06_freight_devops.sql` | `cdb_delivery_zones` |
| `2026_11_11_courier_delivery.sql` | `cdb_delivery_config`, `cdb_delivery_zone_rules`, `cdb_delivery_requests`, `cdb_delivery_items`, `cdb_delivery_claims`, `cdb_delivery_events`, `cdb_delivery_outbox` |
| `2026_11_26_route_planner.sql` | all `cdb_rp_*` tables |
| `2026_11_27_rider_app.sql` | `cdb_rider_payout_requests`, `cdb_rider_quests`, `cdb_rider_shifts`, `cdb_rider_prefs` |
| `2026_11_27_rider_ops.sql` | `cdb_rider_status_log`, `cdb_rider_scans` |
| `2026_11_27_rider_positions.sql` | `cdb_rider_positions` |
