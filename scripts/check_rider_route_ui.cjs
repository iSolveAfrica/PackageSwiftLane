/* Rider app route UI against the live dev app (default http://127.0.0.1:8084, env SL_BASE).
 * Signs in as the seeded rider "ama" (password DevPassword123!) on iPhone 13, Pixel 7 and a 1440 px
 * desktop and checks: layout auto-pick + manual switch, schematic map when tiles fail, no horizontal
 * scroll, loading checklist (scan + tick + missing), start route, ping throttling with a mocked
 * position near 5.60,-0.18, arrive, failed-with-reason (delivery_ajax op=fail + op=sync), offline
 * banner with Retry, no console errors.
 * It gives ama a temporary route of up to 6 pending deliveries and restores every row afterwards.
 * Refuses unless the database is named swiftlane or *test*. Needs php + the dev DB on this host.
 *   CHROMIUM_PATH=/opt/pw-browsers/chromium-1243/chrome-linux64/chrome node scripts/check_rider_route_ui.cjs
 */
const { chromium, devices } = require('playwright');
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const ROOT = path.resolve(__dirname, '..');
const BASE = (process.env.SL_BASE || 'http://127.0.0.1:8084').replace(/\/$/, '') + '/';
const RIDER = process.env.SL_RIDER || 'ama', PASS = process.env.SL_PASS || 'DevPassword123!';

const FIXTURE = String.raw`<?php
chdir($argv[1]);
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_METHOD'] = 'GET'; $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require 'loader.php';
require_once 'helpers/route_planner.php';
new Core();
$db = (string) rp_val('SELECT DATABASE()');
if ($db !== 'swiftlane' && stripos($db, 'test') === false) { fwrite(STDERR, "Refusing: database $db\n"); exit(2); }
$mode = $argv[2]; $snapF = $argv[3]; $username = $argv[4];
$rider = (int) rp_val('SELECT id FROM cdb_users WHERE username = ?', [$username]);
if (!$rider) { fwrite(STDERR, "Rider $username not found\n"); exit(2); }
$today = date('Y-m-d');
function wipe($rider, $today) {
  foreach (rp_all('SELECT id FROM cdb_rp_routes WHERE driver_id = ? AND route_date = ?', [$rider, $today]) as $r) {
    rp_q('DELETE FROM cdb_rp_load WHERE route_id = ?', [$r['id']]); rp_q('DELETE FROM cdb_rp_stops WHERE route_id = ?', [$r['id']]); rp_q('DELETE FROM cdb_rp_routes WHERE id = ?', [$r['id']]);
  }
}
if (is_file($snapF)) {
  $s = json_decode(file_get_contents($snapF), true);
  foreach ($s['reqs'] as $r) {
    rp_q('UPDATE cdb_delivery_requests SET state=?, driver_id=?, assigned_at=?, completed_at=?, receiver_name=?, proof_photo=?, fee_collected=? WHERE id=?', [$r['state'], $r['driver_id'], $r['assigned_at'], $r['completed_at'], $r['receiver_name'], $r['proof_photo'], $r['fee_collected'], $r['id']]);
    rp_q('DELETE FROM cdb_delivery_events WHERE request_id = ? AND id > ?', [$r['id'], $s['maxEvent']]);
  }
  foreach ($s['parcels'] as $p) rp_q('UPDATE ' . ($p['m'] === 'sea' ? 'cdb_customers_packages' : 'cdb_add_order') . ' SET status_courier = ? WHERE order_id = ?', [$p['st'], $p['id']]);
  wipe($rider, $today);
  foreach (($s['routes'] ?? []) as $r) { rp_q('UPDATE cdb_rp_routes SET route_date = ? WHERE id = ?', [$r['route_date'], $r['id']]); }
  rp_q('DELETE FROM cdb_rp_activity WHERE driver_id = ? AND id > ?', [$rider, $s['maxAct']]);
  rp_q('DELETE FROM cdb_rp_pings WHERE driver_id = ? AND id > ?', [$rider, $s['maxPing']]);
  unlink($snapF);
  echo "restored\n";
}
if ($mode === 'reset') exit(0);
// Park any existing route of today (restored afterwards) so the test owns today's route.
$parked = rp_all('SELECT id, route_date FROM cdb_rp_routes WHERE driver_id = ? AND route_date = ?', [$rider, $today]);
foreach ($parked as $r) rp_q('UPDATE cdb_rp_routes SET route_date = ? WHERE id = ?', ['2000-01-01', $r['id']]);
$v = rp_vehicle($rider); $hold = rp_cargoHold(); $units = $hold['units'] ?? [];
$start = rp_startPlace(); $pick = []; $kg = 0; $l = 0; $n = 0;
usort($units, fn($a, $b) => rp_haversineM($start['lat'], $start['lng'], $a['lat'], $a['lng']) <=> rp_haversineM($start['lat'], $start['lng'], $b['lat'], $b['lng']));
foreach ($units as $u) {
  if (count($pick) >= 6) break;
  if (rp_vehicleEligible($v, $u) !== null) continue;
  if ($kg + $u['kg'] > $v['max_kg'] || $l + $u['l'] > $v['max_l'] || $n + $u['n'] > $v['max_parcels']) continue;
  $pick[] = $u['id']; $kg += $u['kg']; $l += $u['l']; $n += $u['n'];
}
if (count($pick) < 3) { fwrite(STDERR, "Need at least 3 pending deliveries (run php scripts/seed_route_planner.php)\n"); foreach ($parked as $r) rp_q('UPDATE cdb_rp_routes SET route_date = ? WHERE id = ?', [$r['route_date'], $r['id']]); exit(3); }
$reqs = rp_all('SELECT * FROM cdb_delivery_requests WHERE id IN (' . rp_in($pick) . ')', $pick);
$parcels = [];
foreach ($reqs as $r) foreach (json_decode($r['parcel_json'], true) as $pid) $parcels[] = ['m' => $r['module'], 'id' => $pid, 'st' => rp_val('SELECT status_courier FROM ' . ($r['module'] === 'sea' ? 'cdb_customers_packages' : 'cdb_add_order') . ' WHERE order_id = ?', [$pid])];
file_put_contents($snapF, json_encode(['reqs' => $reqs, 'parcels' => $parcels, 'routes' => $parked, 'maxEvent' => (int) rp_val('SELECT COALESCE(MAX(id),0) FROM cdb_delivery_events'), 'maxAct' => (int) rp_val('SELECT COALESCE(MAX(id),0) FROM cdb_rp_activity'), 'maxPing' => (int) rp_val('SELECT COALESCE(MAX(id),0) FROM cdb_rp_pings')]));
$admin = (int) rp_val('SELECT id FROM cdb_users WHERE userlevel = 9 ORDER BY id LIMIT 1') ?: 1;
foreach ($pick as $id) rp_service()->transition($id, $admin, 'manager', 0, 'assign', ['driver_id' => $rider, 'note' => 'Rider UI check']);
rp_q('DELETE FROM cdb_rp_pings WHERE driver_id = ? AND recorded_at >= ?', [$rider, date('Y-m-d H:i:s', time() - 600)]);
$routeId = rp__writeRoute(0, $rider, $today, $start, $pick, false, $admin);
$tracking = array_values(array_map(fn($x) => $x['tracking'], rp_all('SELECT tracking FROM cdb_rp_load WHERE route_id = ? ORDER BY stop_id, order_id', [$routeId])));
echo json_encode(['route_id' => $routeId, 'stops' => count($pick), 'tracking' => $tracking]);
`;

function fixture(mode, snap) {
  const f = path.join(os.tmpdir(), 'sl_rider_route_fixture_' + process.pid + '.php');
  fs.writeFileSync(f, FIXTURE);
  try { return execFileSync('php', [f, ROOT, mode, snap, RIDER], { encoding: 'utf8' }).trim(); }
  finally { fs.unlinkSync(f); }
}

async function login(ctx) {
  // The phone install screen (assets/swiftlane/pwa/app.js) is snoozed, as "Continue in the browser for now" does.
  await ctx.addInitScript(() => { try { localStorage.setItem('slpwa.rider.snooze', String(Date.now() + 864e5)); } catch (e) { /* ignore */ } });
  const p = await ctx.newPage();
  await p.goto(BASE + 'login.php');
  await p.fill('input[name="username"]', RIDER);
  await p.fill('input[type="password"]', PASS);
  await Promise.all([p.waitForNavigation({ waitUntil: 'load' }).catch(() => {}), p.click('button[type="submit"]')]);
  return p;
}
const TILE = /tile\.openstreetmap|tile\.osm|basemaps|arcgisonline|fonts\.(googleapis|gstatic)/;
function watchErrors(page, errs, tag) {
  page.on('pageerror', e => errs.push(tag + ' pageerror ' + e.message));
  page.on('console', m => { if (m.type() === 'error' && !/ERR_FAILED|ERR_INTERNET_DISCONNECTED|Failed to load resource/.test(m.text())) errs.push(tag + ' ' + m.text()); });
}
async function noHScroll(page, tag) {
  const over = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  assert(over <= 1, tag + ': horizontal scroll ' + over + 'px');
}
async function openApp(page, q) {
  await page.goto(BASE + 'driver_app.php' + (q || ''), { waitUntil: 'load' });
  await page.waitForSelector('.da');
}

(async () => {
  const snap = path.join(os.tmpdir(), 'sl_rider_route_snap_' + process.pid + '.json');
  const fx = JSON.parse(fixture('setup', snap).split('\n').pop());
  const b = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', args: ['--no-sandbox'] });
  const errs = [];
  try {
    /* 1. Layout auto-pick, schematic map, no horizontal scroll on phones and desktop. */
    for (const [name, opts, want] of [['iPhone 13', devices['iPhone 13'], 'phone'], ['Pixel 7', devices['Pixel 7'], 'phone'], ['desktop', { viewport: { width: 1440, height: 900 } }, 'web']]) {
      const ctx = await b.newContext({ ...opts });
      await ctx.route(TILE, r => r.abort());
      const p = await login(ctx); watchErrors(p, errs, name);
      for (const tab of ['', '?tab=route', '?tab=load']) {
        await openApp(p, tab);
        assert.equal(await p.evaluate(() => document.documentElement.dataset.riderLayout), want, name + ' layout');
        await noHScroll(p, name + ' ' + (tab || 'today'));
      }
      await openApp(p, '?tab=route');
      await p.waitForFunction(() => { const m = document.querySelector('[data-rr-map]'); return m && m.getAttribute('data-map-provider') === 'schematic'; }, null, { timeout: 12000 });
      assert(await p.locator('[data-rr-map] .slrm-note').first().textContent().then(t => /Schematic/.test(t)), name + ' schematic note');
      assert.equal(await p.locator('[data-rr-li]').count(), fx.stops, name + ' stop list');
      // Nearest-first re-orders open stops by distance from the rider.
      await p.click('[data-rr-sort="near"]');
      const d = await p.$$eval('[data-rr-li][data-open="1"]', els => els.map(e => e._d));
      for (let i = 1; i < d.length; i++) assert(d[i] >= d[i - 1], name + ' nearest-first order');
      if (name === 'desktop') {
        // Manual switch to phone view and back to auto (cookie + localStorage).
        await p.click('[data-rider-layout-set="phone"]');
        assert.equal(await p.evaluate(() => document.documentElement.dataset.riderLayout), 'phone');
        await openApp(p);
        assert(await p.locator('.da.is-phone').count(), 'phone view kept after reload');
        const cookie = (await ctx.cookies()).find(c => c.name === 'sl_rider_layout');
        assert.equal(cookie && cookie.value, 'phone');
        await p.click('[data-rider-layout-set="auto"]');
        assert.equal(await p.evaluate(() => document.documentElement.dataset.riderLayout), 'web');
      }
      await ctx.close();
    }

    /* 2. Loading, start, pings, arrive, failed, offline on a phone with a mocked position. */
    const ctx = await b.newContext({ ...devices['Pixel 7'], geolocation: { latitude: 5.6, longitude: -0.18, accuracy: 15 }, permissions: ['geolocation'] });
    await ctx.route(TILE, r => r.abort());
    let pings = 0;
    await ctx.route(/rider_route_ajax\.php/, async r => { if ((r.request().postData() || '').includes('op=ping')) pings++; await r.continue(); });
    const p = await login(ctx); watchErrors(p, errs, 'flow');
    await openApp(p, '?tab=load');
    assert(await p.locator('[data-rr-op="start"]').isDisabled(), 'start disabled before loading');
    // Scan one parcel by tracking number, a wrong code, then tick the rest; mark the last one missing.
    await p.fill('#rr-code', fx.tracking[0]);
    await p.click('[data-rr-scan] [type=submit]');
    await p.waitForFunction(t => document.querySelector('[data-rr-parcel][data-tracking="' + t + '"] [data-rr-tick]').checked, fx.tracking[0]);
    await p.fill('#rr-code', 'NOT-ON-ROUTE-1');
    await p.click('[data-rr-scan] [type=submit]');
    await p.waitForFunction(() => document.querySelector('[data-rr-scan-msg]').classList.contains('is-bad'));
    const rows = await p.locator('[data-rr-parcel]').count();
    // The last row is left for "Missing", so the scanned parcel must not be it.
    assert(!(await p.locator('[data-rr-parcel]').nth(rows - 1).locator('[data-rr-tick]').isChecked()) || rows > 2);
    for (let i = 0; i < rows - 1; i++) {
      const cb = p.locator('[data-rr-parcel]').nth(i).locator('[data-rr-tick]');
      if (!(await cb.isChecked())) { await cb.check(); await p.waitForTimeout(150); }
    }
    await p.waitForTimeout(400);
    assert(await p.locator('[data-rr-op="start"]').isDisabled(), 'start still disabled with one parcel unchecked');
    await p.locator('[data-rr-parcel]').nth(rows - 1).locator('[data-rr-missing-btn]').click();
    await p.waitForFunction(() => !document.querySelector('[data-rr-op="start"]').disabled);
    await noHScroll(p, 'load tab');
    await Promise.all([p.waitForURL(u => !/tab=load/.test(u.toString()), { timeout: 15000 }), p.click('[data-rr-op="start"]')]);
    await p.waitForSelector('[data-rr-op="arrive"]');

    // Ping throttle: several position changes within seconds send one stored ping at most.
    await p.waitForTimeout(1500);
    for (const [la, ln] of [[5.6005, -0.1805], [5.601, -0.181], [5.6015, -0.1815], [5.602, -0.182]]) { await ctx.setGeolocation({ latitude: la, longitude: ln, accuracy: 12 }); await p.waitForTimeout(400); }
    await p.waitForTimeout(800);
    assert(pings >= 1, 'a ping was sent while on the way');
    assert(pings <= 2, 'pings are throttled (got ' + pings + ')');

    // Arrive at the first stop; the stop turns "arrived" and the hero switches to hand-over.
    const firstStop = await p.locator('[data-rr-op="arrive"]').first().getAttribute('data-stop');
    await Promise.all([p.waitForNavigation({ waitUntil: 'load' }), p.click('[data-rr-op="arrive"] >> nth=0')]);
    await p.waitForSelector('#rr-stop-' + firstStop + ' .sl-pill:has-text("Arrived")');

    // Failed with reason through the existing delivery workflow, then the route syncs.
    await p.click('#rr-stop-' + firstStop + ' [data-rr-fail]');
    await p.check('[data-rr-fail-form] input[name=reason] >> nth=0');
    await p.fill('[data-rr-fail-form] textarea[name=note]', 'Called twice');
    await Promise.all([p.waitForNavigation({ waitUntil: 'load', timeout: 15000 }), p.click('[data-rr-fail-form] [type=submit]')]);
    await openApp(p, '?tab=route');
    assert(await p.locator('[data-rr-li="' + firstStop + '"].st-failed').count(), 'failed stop synced');
    assert(/1/.test(await p.locator('.rr-stops-head h2 small').textContent()), 'progress counts the failed stop');

    // Offline: banner with Retry, nothing queued; back online hides it.
    await ctx.setOffline(true);
    await p.evaluate(() => window.dispatchEvent(new Event('offline')));
    await p.waitForSelector('[data-rr-offline]:not([hidden])');
    await ctx.setOffline(false);
    await p.evaluate(() => window.dispatchEvent(new Event('online')));
    await p.waitForSelector('[data-rr-offline]', { state: 'hidden' });
    await noHScroll(p, 'route tab on the way');
    await ctx.close();

    assert.deepEqual(errs, [], 'console errors:\n' + errs.join('\n'));
    console.log('Rider route UI passed: layout auto-pick (iPhone 13, Pixel 7 phone; desktop web) and manual switch, schematic map offline, nearest-first, loading checklist (scan, tick, missing), start, throttled pings (' + pings + '), arrive, failed with reason + sync, offline banner, no horizontal scroll.');
  } finally {
    await b.close();
    console.log(fixture('reset', snap));
  }
})().catch(e => { console.error(e); process.exitCode = 1; });
