/* Smart Routing browser check against the running app.
 *   CHROMIUM_PATH=... node scripts/check_smart_routing.cjs [--no-deliver] [--shots=DIR]
 * Signs in as admin (BASE_URL, default http://127.0.0.1:8084; SR_USER / SR_PASS), then:
 *  1440 px: overview (riders, status pills, load bars, schematic map with map tiles aborted), rider pick,
 *           "Create route" dialog (running total, create for one rider), route details (add a stop, move a
 *           stop, optimise, remove a stop, save, send to the rider: a message is queued), the share-between-
 *           riders preview (then discarded), a rider's finished stop (delivered through the delivery
 *           workflow by scripts/seed_smart_routing_delivery.php): proof dialog with photo, staff note saved,
 *           failed stop shows the reason; "Riders and vehicles" (vehicle card, statistics, star, vehicle form).
 *  390 px:  the three views have no horizontal scroll; dark theme renders.
 * Needs seeded data: php scripts/seed_route_planner.php --history (re-run it afterwards: this check
 * assigns and delivers a few seeded requests). */
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const path = require('node:path');
const BASE = (process.env.BASE_URL || 'http://127.0.0.1:8084').replace(/\/$/, '');
const USER = process.env.SR_USER || 'admin', PASS = process.env.SR_PASS || 'DevPassword123!';
const args = process.argv.slice(2), noDeliver = args.includes('--no-deliver');
const shotsArg = args.find(a => a.startsWith('--shots=')), SHOTS = shotsArg ? shotsArg.slice(8).replace(/\/?$/, '/') : null;
const OFFLINE = /fonts\.(googleapis|gstatic)\.com|tile\.openstreetmap\.org|basemaps|maps\.googleapis\.com|maps\.gstatic\.com|unpkg\.com|cdn\.jsdelivr\.net/;
const AJAX = 'smart_routing_ajax.php';

async function login(ctx) {
  const p = await ctx.newPage();
  await p.goto(BASE + '/login.php?form=1', { waitUntil: 'domcontentloaded' });
  await p.fill('input[name=username]', USER);
  await p.fill('input[type=password]', PASS);
  await Promise.all([p.waitForNavigation({ waitUntil: 'domcontentloaded' }).catch(() => {}), p.click('button[type=submit]')]);
  return p;
}
function watch(p, errors) {
  p.on('console', m => { if (m.type() === 'error' && !/Failed to load resource|ERR_FAILED|ERR_INTERNET_DISCONNECTED|ERR_TUNNEL/.test(m.text())) errors.push('console: ' + m.text()); });
  p.on('pageerror', e => { if (!/Transition was skipped/.test(e.message)) errors.push('pageerror: ' + e.message); });
  p.on('response', r => { if (r.status() >= 500) errors.push(r.status() + ' ' + r.url()); });
}
async function noHScroll(p, label) {
  const [sw, w] = await p.evaluate(() => [document.documentElement.scrollWidth, window.innerWidth]);
  assert(sw <= w + 1, `${label}: horizontal scroll (${sw} > ${w})`);
}
async function shot(p, name) {
  if (!SHOTS) return;
  await p.evaluate(() => { const g = document.getElementById('pwa-gate'); if (g) g.remove(); document.documentElement.classList.remove('pwa-gated'); });
  await p.screenshot({ path: SHOTS + name + '.png', fullPage: true });
}
async function idle(p) { await p.waitForTimeout(300); await p.waitForSelector('html:not([data-sr-busy])', { timeout: 30000 }); await p.waitForTimeout(700); }
async function open(p, q) { await p.goto(BASE + '/smart_routing.php' + (q || ''), { waitUntil: 'domcontentloaded' }); await p.waitForSelector('[data-sr-root]'); await p.waitForTimeout(400); }
const post = p => p.waitForResponse(r => r.url().includes(AJAX) && r.request().method() === 'POST');
async function act(p, click) { const r = post(p); await click(); const j = await (await r).json(); await idle(p); return j; }
const stops = p => p.$$eval('[data-sr-stop]', els => els.map(e => e.getAttribute('data-sr-stop')));

(async () => {
  const b = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined, args: ['--no-sandbox'] });
  const errors = [];
  try {
    /* ---------- desktop 1440 ---------- */
    const ctx = await b.newContext({ viewport: { width: 1440, height: 1000 } });
    await ctx.route(OFFLINE, r => r.abort());
    const p = await login(ctx);
    watch(p, errors);
    await open(p);
    assert.equal(await p.locator('[data-sr-empty]').count(), 0, 'engine not installed (empty state shown)');
    assert(/Smart Routing/.test(await p.textContent('.sr-title')), 'title');
    const riders = await p.locator('[data-sr-rider]').count();
    assert(riders > 0, 'no riders: run php scripts/seed_route_planner.php');
    assert.equal(await p.locator('[data-sr-rider] .sl-pill').count(), riders, 'every rider has a status pill');
    assert.equal(await p.locator('[data-sr-rider] [role=meter]').count(), riders, 'every rider has a load bar');
    assert(await p.locator('.sr-tabs a[href*="route_planner.php"]').count(), 'link to the route planner');
    assert(await p.locator('a[href*="smart_routing.php"]').count() > 0, 'Smart Routing is in the menu');
    const waiting = parseInt(await p.textContent('[data-sr-kpi="waiting"]'), 10);
    assert(waiting >= 4, 'fewer than 4 deliveries waiting: run php scripts/seed_route_planner.php');
    // map: schematic once the tiles fail, with one pin per waiting delivery and the start place
    await p.waitForFunction(() => /schematic/i.test((document.querySelector('[data-sr-map]') || {}).textContent || ''), null, { timeout: 12000 });
    assert(await p.locator('[data-sr-map] .slrm-stop.sr-wait').count() >= waiting, 'waiting deliveries are on the map');
    assert(await p.locator('[data-sr-map] .slrm-depot').count(), 'start place is on the map');
    await noHScroll(p, 'overview 1440');
    await shot(p, 'overview-1440');
    // search and filter
    await p.fill('[data-sr-rider-search]', 'zzzz-nobody');
    assert.equal(await p.locator('[data-sr-rider]:not([hidden])').count(), 0, 'search hides riders');
    assert(await p.locator('[data-sr-riders-none]:not([hidden])').count(), 'search shows the empty line');
    await p.fill('[data-sr-rider-search]', '');
    await p.click('[data-sr-filter-toggle]');
    await p.click('[data-sr-filter="available"]');
    const avail = await p.locator('[data-sr-rider]:not([hidden])').count();
    assert(avail > 0, 'no available rider today: re-seed, or finish the open routes');
    // pick the first available rider
    const riderId = await p.locator('[data-sr-rider]:not([hidden])').first().getAttribute('data-sr-rider');
    await p.click(`[data-sr-pick="${riderId}"]`);
    assert(await p.locator(`[data-sr-rider="${riderId}"].sel`).count(), 'picked rider is highlighted');
    assert(new URL(p.url()).searchParams.get('rider') === riderId, 'URL keeps the picked rider');

    // share between riders: preview only, then walk away
    await p.click('.sr-head [data-sr-create]');
    await p.selectOption('[data-sr-f-rider]', '0');
    assert(await p.locator('[data-sr-auto-note]:not([hidden])').count(), 'auto note shown without a rider');
    const pj = await act(p, () => p.click('[data-sr-create-go]'));
    assert.equal(pj.success, true, 'auto propose failed: ' + JSON.stringify(pj).slice(0, 300));
    assert(pj.plan_id > 0, 'proposal has a plan');
    await shot(p, 'create-auto-1440');
    const dj = await act(p, () => p.click('#sr-create .sr-modal-f [data-sl-close]'));
    assert.equal(dj.success, true, 'the proposal is discarded when the dialog is closed');

    // create a route for the picked rider: tick deliveries while they fit
    await p.click('.sr-panel-foot [data-sr-create]');
    assert.equal(await p.inputValue('[data-sr-f-rider]'), riderId, 'dialog opens on the picked rider');
    const boxes = p.locator('#sr-create .sr-del:not([hidden]) input');
    let ticked = 0;
    for (let i = 0, n = await boxes.count(); i < n && ticked < 3; i++) {
      await boxes.nth(i).check();
      if (await p.locator('[data-sr-tally].bad').count()) await boxes.nth(i).uncheck(); else ticked++;
    }
    assert(ticked >= 2, 'could not find two deliveries that fit the rider');
    assert(new RegExp('^' + ticked + ' stops').test(await p.textContent('[data-sr-tally-text]')), 'running total counts the ticked deliveries');
    await shot(p, 'create-1440');
    let cj = await act(p, () => p.click('[data-sr-create-go]'));
    // A delivery the vehicle may not carry (fragile, cold, too long) is refused by name: swap it for another one.
    for (let tries = 0, next = 0; cj.blocked && !cj.can_force && tries < 8; tries++) {
      const bad = (/Delivery #(\d+)/.exec(cj.message) || [])[1];
      assert(bad, 'create route refused: ' + cj.message);
      await p.uncheck(`#sr-create input[value="${bad}"]`);
      await p.locator(`#sr-create .sr-del:has(input[value="${bad}"])`).evaluate(el => el.setAttribute('data-refused', '1'));
      const spare = p.locator('#sr-create .sr-del:not([hidden]):not([data-refused]) input:not(:checked)');
      for (const n = await spare.count(); next < n; next++) {
        await spare.nth(0).check();
        if (await p.locator('[data-sr-tally].bad').count()) { await spare.nth(0).uncheck(); await spare.nth(0).evaluate(el => el.closest('.sr-del').setAttribute('data-refused', '1')); } else break;
      }
      ticked = await p.locator('#sr-create input[name="request_ids[]"]:checked').count();
      cj = await act(p, () => p.click('[data-sr-create-go]'));
    }
    if (cj.blocked && cj.can_force) { await p.waitForSelector('#sr-ask:not([hidden])'); cj = await act(p, () => p.click('[data-sr-ask-ok]')); }
    assert.equal(cj.success, true, 'create route failed: ' + JSON.stringify(cj).slice(0, 300));
    const routeId = cj.route_id;
    await p.waitForSelector(`[data-sr-route="${routeId}"]`);
    assert.equal((await stops(p)).length, ticked, 'route shows its stops');
    assert(await p.locator('.sr-stop.is-start').count(), 'timeline starts at the start place');
    assert(/\d\d:\d\d/.test(await p.locator('[data-sr-stop] .sr-time').first().textContent()), 'stops carry an arrival time');
    await p.waitForFunction(() => document.querySelectorAll('[data-sr-map] .sr-line-in').length > 0, null, { timeout: 12000 });
    await shot(p, 'route-1440');

    // add a stop from the waiting list (the first one the vehicle accepts)
    let added = false;
    for (let i = 0; i < 6 && !added; i++) {
      await p.click('[data-sl-open="sr-add"]');
      const row = p.locator('[data-sr-add]').nth(i);
      if (!(await row.count())) break;
      const aj = await act(p, () => row.click());
      if (aj.success) added = true;
      else if (aj.blocked && aj.can_force) { await p.waitForSelector('#sr-ask:not([hidden])'); await p.click('[data-sr-ask-cancel]'); }
    }
    assert(added, 'a waiting delivery can be added to the route');
    assert.equal((await stops(p)).length, ticked + 1, 'added stop is on the timeline');
    // edit: move the last stop up, optimise, remove one
    await p.click('[data-sr-edit]');
    assert(await p.locator('[data-sr-editbar]:not([hidden])').count(), 'edit bar opens');
    const before = await stops(p);
    const mj = await act(p, () => p.locator('[data-sr-stop][data-movable]').last().locator('[data-sr-move="up"]').click());
    assert.equal(mj.success, true, 'reorder failed: ' + JSON.stringify(mj));
    const after = await stops(p);
    assert.notDeepEqual(after, before, 'order changed');
    assert.equal(after[after.length - 1], before[before.length - 2], 'the stop moved up one place');
    assert(await p.locator('[data-sr-editbar]:not([hidden])').count(), 'edit mode survives the refresh');
    const oj = await act(p, () => p.click('[data-sr-reopt]'));
    assert.equal(oj.success, true, 'optimise failed');
    await p.locator('[data-sr-remove]').last().click();
    await p.waitForSelector('#sr-ask:not([hidden])');
    const rj = await act(p, () => p.click('[data-sr-ask-ok]'));
    assert.equal(rj.success, true, 'remove failed: ' + JSON.stringify(rj));
    assert.equal((await stops(p)).length, ticked, 'removed stop left the timeline');
    await p.click('[data-sr-edit]');
    // pin and row stay in step
    const firstStop = (await stops(p))[0];
    await p.hover(`[data-sr-stop="${firstStop}"] [data-sr-stop-open]`);
    assert(await p.locator(`[data-sr-map] .slrm-stop.is-focus[data-stop="${firstStop}"]`).count(), 'pointing at a stop highlights its pin');
    await p.locator(`[data-sr-map] .slrm-stop[data-stop="${firstStop}"]`).click();
    assert(await p.locator(`[data-sr-stop="${firstStop}"].flash`).count(), 'clicking a pin highlights its row');
    // save, then send
    const sj = await act(p, () => p.click('[data-sr-save]'));
    assert.equal(sj.success, true, 'save failed');
    assert(/Draft saved/.test(await p.textContent('[data-sr-sent]')), 'saved draft is shown');
    await p.click('[data-sr-send]');
    await p.waitForSelector('#sr-ask:not([hidden])');
    const tj = await act(p, () => p.click('[data-sr-ask-ok]'));
    assert.equal(tj.success, true, 'send failed: ' + JSON.stringify(tj).slice(0, 300));
    assert(tj.channels.some(c => c.channel === 'app' && c.status === 'queued' && c.id > 0), 'in-app notification queued for the rider');
    assert(/rider_route|rider app/i.test(tj.text), 'message points the rider to the route');
    assert(/Sent .* by /.test((await p.textContent('[data-sr-sent]')).replace(/\s+/g, ' ')), 'last sent time is shown');
    assert(await p.locator('.sr-ch').count() >= 1, 'message status is shown per channel');
    await shot(p, 'route-sent-1440');

    // the rider delivers the first stop and fails the second (delivery workflow, as the rider app does)
    if (!noDeliver) {
      const out = JSON.parse(execFileSync('php', [path.join(__dirname, 'seed_smart_routing_delivery.php'), String(routeId), '--fail'], { encoding: 'utf8' }).trim().split('\n').pop());
      assert.equal(out.success, true, 'rider delivery failed: ' + out.message);
      await open(p, '?route=' + routeId);
      assert(await p.locator(`[data-sr-stop="${out.delivered.stop_id}"].is-delivered .sr-tick`).count(), 'delivered stop has a tick');
      assert(await p.locator('.sr-stop.is-start.is-done').count(), 'progress line is filled from the start');
      await p.click(`[data-sr-stop-open="${out.delivered.stop_id}"]`);
      await p.waitForSelector('[data-sr-note-form]');
      assert.equal((await p.textContent('[data-sr-stop-title]')).trim(), 'Delivery success', 'proof dialog title');
      assert(/Delivered to /.test(await p.textContent('[data-sr-stop-body]')), 'who received it');
      const img = p.locator('[data-sr-proof="photo"] img');
      assert(await img.count(), 'delivery photo is shown');
      await p.waitForFunction(() => { const i = document.querySelector('[data-sr-proof="photo"] img'); return i && i.complete && i.naturalWidth > 0; }, null, { timeout: 8000 });
      assert(await p.locator('.sr-loc[href*="google.com/maps"]').count(), 'location link');
      assert.equal(await p.locator('.sr-acc').count(), 2, 'recipient and order sections');
      const note = 'Checked by ' + USER + ' at ' + Date.now();
      await p.fill('[data-sr-note-form] textarea', note);
      const nj = await act(p, () => p.click('[data-sr-note-form] button[type=submit]'));
      assert.equal(nj.success, true, 'note save failed');
      await shot(p, 'proof-1440');
      await p.keyboard.press('Escape');
      await p.click(`[data-sr-stop-open="${out.delivered.stop_id}"]`);
      await p.waitForSelector('[data-sr-note-form]');
      assert.equal(await p.inputValue('[data-sr-note-form] textarea'), note, 'note is kept');
      await p.keyboard.press('Escape');
      if (out.failed) {
        await p.click(`[data-sr-stop-open="${out.failed.stop_id}"]`);
        await p.waitForSelector('[data-sr-fail]');
        assert(/not at home/i.test(await p.textContent('[data-sr-fail]')), 'failed stop shows the reason');
        await shot(p, 'failed-1440');
        await p.keyboard.press('Escape');
      }
    }

    // riders and vehicles
    await open(p, '?view=riders&rider=' + riderId);
    assert(await p.locator('.sr-rvg').count() >= 2, 'riders are grouped (favourites + vehicle types)');
    assert(await p.locator('[data-sr-vcard] .sr-vsvg').count(), 'vehicle card with a drawing');
    assert(await p.locator('[data-sr-vcard] dl div').count() >= 2, 'vehicle figures');
    assert(await p.locator('#sr-stats').count(), 'statistics block');
    assert((await p.locator('.sr-stack span').count()) > 0 || (await p.locator('#sr-stats .sr-emptybox').count()) > 0, 'time by category or an honest empty state');
    if (await p.locator('.sr-bars').count()) assert(await p.locator('.sr-bars[data-sl-chart]').count(), 'working-time chart uses the shared chart tooltip');
    await p.click('.sr-range a >> nth=1');
    await p.waitForSelector('.sr-range a.on >> text=M');
    await shot(p, 'riders-1440');
    const star = p.locator(`[data-sr-fav="${riderId}"]`).first();
    await Promise.all([p.waitForNavigation({ waitUntil: 'domcontentloaded' }), star.click()]);
    assert(await p.locator(`[data-sr-rvg="fav"] [data-sr-fav="${riderId}"]`).count(), 'starred rider is under Favourites');
    await Promise.all([p.waitForNavigation({ waitUntil: 'domcontentloaded' }), p.locator(`[data-sr-fav="${riderId}"]`).first().click()]);
    assert.equal(await p.locator(`[data-sr-rvg="fav"] [data-sr-fav="${riderId}"]`).count(), 0, 'star removed');
    await p.click('.sr-rvfoot [data-sr-veh-open]');
    await p.waitForSelector('#sr-vehicle:not([hidden])');
    assert(parseFloat(await p.inputValue('#sr-vehicle [name=max_kg]')) > 0, 'vehicle form is filled from the rider');
    await p.keyboard.press('Escape');
    await noHScroll(p, 'riders 1440');

    /* ---------- dark ---------- */
    await p.evaluate(() => window.SL && SL.theme && SL.theme('dark'));
    await open(p, '?route=' + routeId);
    assert(await p.evaluate(() => document.documentElement.classList.contains('sl-dark')), 'dark theme is on');
    await p.waitForTimeout(600);
    await shot(p, 'route-1440-dark');
    await open(p, '?view=riders&rider=' + riderId);
    await shot(p, 'riders-1440-dark');
    await p.evaluate(() => window.SL && SL.theme && SL.theme('light'));

    /* ---------- phone 390 ---------- */
    const m = await b.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 });
    await m.route(OFFLINE, r => r.abort());
    const mp = await login(m);
    watch(mp, errors);
    for (const [name, q] of [['overview', ''], ['route', '?route=' + routeId], ['riders', '?view=riders&rider=' + riderId]]) {
      await open(mp, q);
      await mp.waitForTimeout(500);
      await noHScroll(mp, name + ' 390');
      await shot(mp, name + '-390');
    }
    await open(mp, '?route=' + routeId);
    const order = await mp.evaluate(() => { const a = document.querySelector('.sr-mapwrap').getBoundingClientRect(), b2 = document.querySelector('.sr-detail').getBoundingClientRect(); return [a.top < b2.top, getComputedStyle(document.querySelector('.sr-head-t')).display]; });
    assert.deepEqual(order, [true, 'none'], 'phone route details: map strip on top, title block hidden');

    assert.deepEqual(errors, [], 'browser errors:\n' + errors.join('\n'));
    console.log('Smart Routing passed: overview, create route, auto-allocate preview, add / move / optimise / remove, save, send' + (noDeliver ? '' : ', proof of delivery, note, failed stop') + ', riders and vehicles, dark, 390 px.');
  } finally { await b.close(); }
})().catch(e => { console.error(e); process.exitCode = 1; });
