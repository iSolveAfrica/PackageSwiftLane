/* Route planner (admin) browser check against the running app.
 *   CHROMIUM_PATH=... node scripts/check_route_planner_ui.cjs [--no-confirm] [--shots=DIR]
 * Signs in as admin (BASE_URL, default http://127.0.0.1:8084; RP_USER / RP_PASS), then at 1440 and 390 px:
 * auto-allocate (propose), move a stop with the "Move to…" dialog, drag a stop back to the hold, lock a rider,
 * schematic map fallback with map tiles/CDNs aborted (offline), every tab renders, no horizontal scroll,
 * no console errors; finally confirm (unless --no-confirm): requests become assigned with a rider.
 * Needs seeded data: php scripts/seed_route_planner.php (re-run it afterwards to refill the hold). */
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const BASE = (process.env.BASE_URL || 'http://127.0.0.1:8084').replace(/\/$/, '');
const USER = process.env.RP_USER || 'admin', PASS = process.env.RP_PASS || 'DevPassword123!';
const args = process.argv.slice(2), noConfirm = args.includes('--no-confirm');
const shotsArg = args.find(a => a.startsWith('--shots=')), SHOTS = shotsArg ? shotsArg.slice(8).replace(/\/?$/, '/') : null;
const OFFLINE = /fonts\.(googleapis|gstatic)\.com|tile\.openstreetmap\.org|basemaps|maps\.googleapis\.com|maps\.gstatic\.com|unpkg\.com|cdn\.jsdelivr\.net/;

async function login(ctx) {
  const p = await ctx.newPage();
  await p.goto(BASE + '/login.php', { waitUntil: 'domcontentloaded' });
  await p.fill('input[name=username]', USER);
  await p.fill('input[type=password]', PASS);
  await Promise.all([p.waitForNavigation({ waitUntil: 'domcontentloaded' }).catch(() => {}), p.click('button[type=submit]')]);
  return p;
}
function watch(p, errors) {
  p.on('console', m => { if (m.type() === 'error' && !/Failed to load resource|ERR_FAILED|ERR_INTERNET_DISCONNECTED/.test(m.text())) errors.push('console: ' + m.text()); });
  p.on('pageerror', e => errors.push('pageerror: ' + e.message));
  if (process.env.RP_DEBUG) { p.on('console', m => console.log('[console]', m.text())); p.on('request', r => { if (r.url().includes('ajax')) console.log('[req]', r.method(), r.url()); }); }
  p.on('response', r => { if (r.status() >= 500) errors.push(r.status() + ' ' + r.url()); });
}
async function noHScroll(p, label) {
  const [sw, w] = await p.evaluate(() => [document.documentElement.scrollWidth, window.innerWidth]);
  assert(sw <= w + 1, `${label}: horizontal scroll (${sw} > ${w})`);
}
async function shot(p, name) { if (SHOTS) await p.screenshot({ path: SHOTS + name + '.png', fullPage: true }); }
async function waitIdle(p) { await p.waitForLoadState('domcontentloaded'); await p.waitForTimeout(300); await p.waitForSelector('html:not([data-rp-busy])', { timeout: 20000 }); await p.waitForTimeout(200); }

(async () => {
  const b = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', args: ['--no-sandbox'] });
  const errors = [];
  try {
    /* ---------- desktop 1440 ---------- */
    const ctx = await b.newContext({ viewport: { width: 1440, height: 900 } });
    await ctx.route(OFFLINE, r => r.abort());
    const p = await login(ctx);
    watch(p, errors);
    await p.goto(BASE + '/route_planner.php?tab=plan', { waitUntil: 'domcontentloaded' });
    assert(await p.locator('[data-rp-root]').count(), 'route planner root missing');
    assert.equal(await p.locator('[data-rp-empty]').count(), 0, 'engine not installed (empty state shown)');
    const ready = parseInt(await p.locator('[data-rp-kpi="ready"]').textContent(), 10);
    assert(ready > 0, 'nothing ready for delivery: run php scripts/seed_route_planner.php');

    // propose
    const prop = p.waitForResponse(r => r.url().includes('route_planner_ajax.php') && r.request().method() === 'POST');
    await p.click('[data-rp-auto]');
    const pj = await (await prop).json();
    assert.equal(pj.success, true, 'propose failed: ' + JSON.stringify(pj).slice(0, 300));
    await p.waitForSelector('[data-rp-discard]');
    await waitIdle(p);
    assert(/plan=\d+/.test(p.url()), 'URL carries the draft plan id');
    const chips = await p.locator('.rp-uchip').count();
    assert(chips > 0, 'proposal shows stops on riders');
    // capacity never exceeded in the proposal
    for (const r of Object.values(pj.riders || {})) {
      const v = r.vehicle || {}, l = r.load || {};
      if (!(r.units || []).length) continue;
      assert(l.kg <= v.max_kg + 1e-6 && l.l <= v.max_l + 1e-6 && l.n <= v.max_parcels, `rider ${r.id} over capacity`);
    }
    await shot(p, 'plan-1440-proposal');
    // map: schematic or provider must have drawn something for the selected rider
    await p.waitForFunction(() => { const m = document.querySelector('[data-rp-map="sel"]'); return m && (m.querySelector('svg') || m.querySelector('.leaflet-container') || m.children.length > 0); }, null, { timeout: 8000 });

    // drag a proposed chip onto the "back to the hold" bar shown while dragging
    const chip = p.locator('.rp-uchip').first();
    if (await chip.count()) {
      const id = await chip.getAttribute('data-rp-unit');
      const back = p.waitForResponse(r => r.url().includes('route_planner_ajax.php') && r.request().postData()?.includes('move'));
      if (process.env.RP_DEBUG) await p.evaluate(() => ['dragstart', 'dragover', 'drop', 'dragend'].forEach(t => document.addEventListener(t, e => console.log('ev', t, e.target.className, document.documentElement.hasAttribute('data-rp-busy')), true)));
      await chip.scrollIntoViewIfNeeded();
      const cb = await chip.boundingBox();
      await p.mouse.move(cb.x + cb.width / 2, cb.y + cb.height / 2); await p.mouse.down();
      await p.mouse.move(cb.x + cb.width / 2 + 30, cb.y + cb.height / 2 + 20, { steps: 6 });
      await p.waitForTimeout(300);
      assert(await p.locator('#rp-dropbar:not([hidden])').count(), 'drop bar appears while dragging');
      const db = await p.locator('#rp-dropbar').boundingBox();
      await p.mouse.move(db.x + db.width / 2, db.y + db.height / 2, { steps: 8 }); await p.mouse.up();
      const bj = await (await back).json();
      assert.equal(bj.success, true, 'drag to hold failed');
      await waitIdle(p);
      assert(await p.locator(`[data-rp-hold-list] [data-rp-unit="${id}"]`).count(), 'dragged stop is back in the hold');
    }
    // move with the dialog: first proposed stop of the selected rider to another rider (or the hold)
    const mvBtn = p.locator('.rp-card [data-rp-move]').first();
    if (await mvBtn.count()) {
      const unitId = await mvBtn.getAttribute('data-rp-move');
      await mvBtn.click();
      await p.waitForSelector('#rp-move:not([hidden])');
      const target = p.locator('#rp-move [data-rp-move-to]:not([disabled])').first();
      const moved = p.waitForResponse(r => r.url().includes('route_planner_ajax.php') && r.request().postData()?.includes('move'));
      p.once('dialog', d => d.accept());
      await target.click();
      const mj = await (await moved).json();
      assert(mj.success === true || mj.blocked === true, 'move answered: ' + JSON.stringify(mj).slice(0, 200));
      await waitIdle(p);
      assert(await p.locator(`[data-rp-unit="${unitId}"]`).count(), 'moved stop still on the page');
    }
    // lock / unlock
    const lockBtn = p.locator('[data-rp-lock]').first();
    const lk = p.waitForResponse(r => r.url().includes('route_planner_ajax.php') && r.request().postData()?.includes('lock'));
    await lockBtn.click();
    assert.equal((await (await lk).json()).success, true, 'lock failed');
    assert.equal(await lockBtn.getAttribute('aria-pressed'), 'true');
    const uk = p.waitForResponse(r => r.url().includes('route_planner_ajax.php') && r.request().postData()?.includes('lock'));
    await lockBtn.click(); await uk;
    // parcel drawer
    const pb = p.locator('[data-rp-parcels]').first();
    if (await pb.count()) { await pb.click(); await p.waitForSelector('#rp-parcel:not([hidden]) [data-rp-parcel-body] *'); await shot(p, 'plan-1440-parcel'); await p.click('#rp-parcel [data-sl-close]'); }
    await noHScroll(p, 'plan 1440');

    for (const tab of ['live', 'fleet', 'timeline', 'settings']) {
      await p.goto(BASE + '/route_planner.php?tab=' + tab, { waitUntil: 'domcontentloaded' });
      await waitIdle(p);
      assert(await p.locator('[data-rp-root]').count(), tab + ' renders');
      await noHScroll(p, tab + ' 1440');
      await shot(p, tab + '-1440');
    }
    // state polling endpoint (GET)
    const st = await p.evaluate(u => fetch(u + '?op=state', { credentials: 'same-origin' }).then(r => r.json()), BASE + '/ajax/sl/route_planner_ajax.php');
    assert.equal(st.success, true, 'op=state'); assert(st.kpis && 'ready' in st.kpis, 'state has KPIs');

    /* ---------- phone 390 ---------- */
    const m = await b.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 });
    await m.route(OFFLINE, r => r.abort());
    const mp = await login(m);
    watch(mp, errors);
    for (const tab of ['plan', 'live', 'fleet', 'timeline', 'settings']) {
      await mp.goto(BASE + '/route_planner.php?tab=' + tab, { waitUntil: 'domcontentloaded' });
      await waitIdle(mp);
      await noHScroll(mp, tab + ' 390');
      await shot(mp, tab + '-390');
    }
    // offline: schematic note visible once tiles fail
    await mp.goto(BASE + '/route_planner.php?tab=plan', { waitUntil: 'domcontentloaded' });
    if (await mp.locator('[data-rp-map="sel"]').count() && await mp.locator('.rp-card .rp-u').count()) {
      await mp.waitForFunction(() => /schematic/i.test((document.querySelector('[data-rp-map="sel"]') || {}).textContent || ''), null, { timeout: 9000 });
    }

    /* ---------- confirm ---------- */
    if (!noConfirm) {
      await p.goto(BASE + '/route_planner.php?tab=plan', { waitUntil: 'domcontentloaded' });
      if (!(await p.locator('[data-rp-discard]').count())) {
        const pr = p.waitForResponse(r => r.url().includes('route_planner_ajax.php') && r.request().method() === 'POST');
        await p.click('[data-rp-auto]'); await pr; await p.waitForSelector('[data-rp-discard]');
      }
      await p.click('[data-sl-open="rp-confirm"]');
      await p.waitForSelector('#rp-confirm:not([hidden])');
      const cf = p.waitForResponse(r => r.url().includes('route_planner_ajax.php') && r.request().postData()?.includes('confirm'));
      await p.click('[data-rp-confirm]');
      const cj = await (await cf).json();
      assert.equal(cj.success, true, 'confirm failed: ' + JSON.stringify(cj).slice(0, 300));
      assert(cj.assigned > 0, 'confirm assigned requests');
      await p.waitForTimeout(3500);
      const st2 = await p.evaluate(u => fetch(u + '?op=state', { credentials: 'same-origin' }).then(r => r.json()), BASE + '/ajax/sl/route_planner_ajax.php');
      assert(st2.kpis.allocated >= cj.assigned, 'assigned requests counted as allocated');
      assert(st2.riders.some(r => r.route_id > 0), 'a rider has a route');
      await p.goto(BASE + '/route_planner.php?tab=fleet', { waitUntil: 'domcontentloaded' });
      await waitIdle(p);
      await shot(p, 'fleet-1440-confirmed');
      assert(await p.locator('.rp-inv').count(), 'confirmed route shows the inventory on board');
    }
    assert.deepEqual(errors, [], 'browser errors:\n' + errors.join('\n'));
    console.log('Route planner UI passed: propose, move dialog, drag to hold, lock, parcel drawer, tabs at 1440/390, offline schematic' + (noConfirm ? '' : ', confirm') + '.');
  } finally { await b.close(); }
})().catch(e => { console.error(e); process.exitCode = 1; });
