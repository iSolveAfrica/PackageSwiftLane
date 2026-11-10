/* Actual design-system runtime with isolated HTTP fixtures; no real account or writes.
 * Run: node scripts/check_design_runtime.cjs (Playwright + Chromium required). */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const html = `<!doctype html><html lang="en"><meta charset="utf-8"><title>Design runtime regression</title>
<meta name="csrf-token" content="fixture-token"><meta name="csrf-param" content="csrf_token">
<link rel="stylesheet" href="/assets/swiftlane/swiftlane.css"><link rel="stylesheet" href="/assets/swiftlane/app.css">
<body class="sl-body-page"><a class="sl-skip" href="#sl-main">Skip to main content</a>
<div class="sl-app dk" id="sl-app"><input type="checkbox" id="navtg" class="nav-tg">
<header class="sl-top"><button type="button" class="sl-ib nav-open" data-sl-nav aria-controls="sl-navigation">Menu</button></header>
<div class="sl-body"><label class="nav-bd" for="navtg"></label>
<aside class="sl-side" id="sl-navigation"><div class="nav-x"><button type="button" data-sl-nav>Close</button></div><a href="#sl-main">Dashboard</a><a href="#last" id="nav-last">Last navigation item</a></aside>
<main class="sl-main" id="sl-main" tabindex="-1"><div class="sl-wrap">
<button type="button" id="theme" data-sl-theme>Theme</button><input id="password" type="password" aria-label="Password"><span role="button" tabindex="0" data-sl-peek="#password" id="peek" aria-label="Show password">Show password</span>
<button type="button" id="opener" data-sl-open="dialog">Open dialog</button>
<div class="sl-dialog" id="dialog" hidden><section class="sl-modal"><h2>Fixture dialog</h2><input id="dialog-first" aria-label="Name"><button type="button" data-sl-close id="dialog-last">Cancel</button></section></div>
<div data-sl-scope><input data-sl-filter-q id="query" aria-label="Search"><div class="sl-seg" data-sl-filter-seg><button type="button" data-f="all" class="on">All</button><button type="button" data-f="air" id="filter-air">Air</button></div>
<div class="sl-card" data-sl-row data-f="air" id="air">Air parcel</div><div class="sl-card" data-sl-row data-f="sea" id="sea">Sea parcel</div><div class="sl-empty" data-sl-empty hidden id="empty">No matches</div></div>
<div data-sl-tabs><button type="button" data-sl-tab="a" class="on" id="tab-a">A</button><span role="button" tabindex="0" data-sl-tab="b" id="tab-b">B</span><div data-sl-tabpanel="a" id="panel-a">Panel A</div><div data-sl-tabpanel="b" hidden id="panel-b">Panel B</div></div>
<span class="sl-toggle" role="switch" aria-label="Remote preference" aria-checked="false" data-sl-save="/toggle" id="switch"><input type="hidden" value="0"></span>
<span class="sl-toggle" role="switch" aria-label="Static preference" aria-checked="false" data-static id="static"></span>
<form data-sl-form action="/form" id="form"><label>Name<input name="name" id="name" required></label><button type="submit" id="submit">Save</button></form>
<button type="button" data-sl-action="/action" id="action">Run action</button>
<div class="sl-tw" style="width:200px;height:60px;overflow:auto" id="table"><table style="width:700px"><tr><td>Scrollable record<details class="sl-menu" data-sl-pop id="row-menu"><summary id="row-opener">Row actions</summary><div class="pop"><button type="button" id="row-action">Open record</button></div></details></td></tr></table></div>
</div></main></div></div><div class="sl-toast" id="sl-toast" role="status" hidden></div>
<script src="/assets/swiftlane/swiftlane.js"></script><script src="/dataJs/sl/auth.js"></script></body></html>`;
let toggleMode = 'fail', formMode = 'invalid';
const calls = { action: 0, form: 0, bodies: [] };
const server = http.createServer((req, res) => {
  if (req.url === '/' || req.url === '/#b') { res.setHeader('Content-Type', 'text/html'); res.end(html); return; }
  if (req.url === '/expired') { res.writeHead(302, { Location: '/login.php' }); res.end(); return; }
  if (req.url === '/login.php') { res.setHeader('Content-Type', 'text/html'); res.end('<form>Sign in</form>'); return; }
  if (req.method === 'POST') {
    let body = ''; req.on('data', b => { body += b; }); req.on('end', () => {
      calls.bodies.push({ url: req.url, body, csrf: req.headers['x-csrf-token'] });
      res.setHeader('Content-Type', 'application/json');
      if (req.url === '/toggle') {
        if (toggleMode === 'network') { req.socket.destroy(); return; }
        res.end(JSON.stringify({ success: toggleMode === 'ok', message: 'Preference result' })); return;
      }
      if (req.url === '/form') {
        calls.form++;
        if (formMode === 'invalid') { res.writeHead(422); res.end(JSON.stringify({ success: false, errors: { name: 'Use a full name' } })); }
        else setTimeout(() => res.end(JSON.stringify({ success: true })), 80);
        return;
      }
      if (req.url === '/action') { calls.action++; setTimeout(() => res.end(JSON.stringify({ success: true })), 80); return; }
      if (req.url === '/http-error') { res.writeHead(500); res.end(JSON.stringify({ success: true, status: 'success', ok: true })); return; }
      res.end(JSON.stringify({ success: true }));
    }); return;
  }
  const file = path.resolve(root, '.' + new URL(req.url, 'http://localhost').pathname);
  if (!file.startsWith(root + path.sep) || !fs.existsSync(file) || !fs.statSync(file).isFile()) { res.writeHead(404); res.end(); return; }
  res.setHeader('Content-Type', file.endsWith('.js') ? 'application/javascript' : 'text/css'); fs.createReadStream(file).pipe(res);
});
(async () => {
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  let browser;
  try {
    browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', args: ['--no-sandbox'] });
    const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
    const errors = []; page.on('pageerror', e => errors.push(e.message));
    await page.goto(`http://127.0.0.1:${server.address().port}/`);
    assert.equal(await page.locator('#sl-navigation').evaluate(el => el.inert), true);
    assert.equal(await page.locator('#empty').isVisible(), false, 'Hidden empty states stay hidden despite component display rules');
    assert.equal(await page.locator('#sl-app').evaluate(el => el.classList.contains('dk')), true);
    await page.click('#theme');
    assert.equal(await page.locator('#sl-app').evaluate(el => el.classList.contains('dk')), false, 'Dark-design pages respond to theme choice');
    await page.focus('#peek'); await page.keyboard.press('Enter');
    assert.equal(await page.locator('#password').getAttribute('type'), 'text');
    assert.equal(await page.locator('#peek').getAttribute('aria-pressed'), 'true');
    await page.keyboard.press('Space');
    assert.equal(await page.locator('#password').getAttribute('type'), 'password');
    await page.focus('.nav-open'); await page.keyboard.press('Enter');
    assert.equal(await page.locator('.nav-open').getAttribute('aria-expanded'), 'true');
    assert.equal(await page.locator('#sl-main').evaluate(el => el.inert), true);
    await page.focus('#nav-last'); await page.keyboard.press('Tab');
    assert.equal(await page.locator('.nav-x button').evaluate(el => el === document.activeElement), true, 'Mobile menu traps focus');
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('.nav-open').evaluate(el => el === document.activeElement), true);
    assert.equal(await page.locator('#sl-navigation').evaluate(el => el.inert), true);
    await page.click('#opener'); await page.waitForFunction(() => document.activeElement.id === 'dialog-first');
    assert.equal(await page.locator('#dialog .sl-modal').getAttribute('aria-modal'), 'true');
    await page.focus('#dialog-last'); await page.keyboard.press('Tab');
    assert.equal(await page.locator('#dialog-first').evaluate(el => el === document.activeElement), true);
    await page.keyboard.press('Escape'); await page.waitForFunction(() => document.activeElement.id === 'opener');
    assert.equal(await page.locator('#dialog').isVisible(), false);
    await page.click('#row-opener');
    await page.waitForFunction(() => document.querySelector('#row-menu .pop').matches(':popover-open'));
    assert.equal(await page.locator('#row-action').isVisible(), true, 'A table menu escapes its clipping container');
    await page.click('#row-action');
    await page.keyboard.press('Escape');
    await page.waitForFunction(() => !document.querySelector('#row-menu').open);
    await page.click('#filter-air'); assert.equal(await page.locator('#sea').isVisible(), false);
    await page.fill('#query', 'nothing'); assert.equal(await page.locator('#air').isVisible(), false); assert.equal(await page.locator('#empty').isVisible(), true);
    await page.fill('#query', 'parcel'); assert.equal(await page.locator('#empty').isVisible(), false);
    await page.focus('#tab-b'); await page.keyboard.press('Enter');
    assert.equal(await page.locator('#panel-b').isVisible(), true, 'Address-style tabs work by keyboard');
    assert.equal(await page.locator('#panel-a').isVisible(), false);
    await page.click('#switch'); await page.waitForFunction(() => !document.querySelector('#switch').hasAttribute('aria-busy'));
    assert.equal(await page.locator('#switch').getAttribute('aria-checked'), 'false');
    assert.equal(await page.locator('#switch input').inputValue(), '0');
    toggleMode = 'network'; await page.click('#switch'); await page.waitForFunction(() => !document.querySelector('#switch').hasAttribute('aria-busy'));
    assert.equal(await page.locator('#switch').getAttribute('aria-checked'), 'false', 'Network failures roll back both switch value and accessibility state');
    toggleMode = 'ok'; await page.click('#switch'); await page.waitForFunction(() => !document.querySelector('#switch').hasAttribute('aria-busy'));
    assert.equal(await page.locator('#switch').getAttribute('aria-checked'), 'true');
    assert.equal(await page.locator('#static').getAttribute('tabindex'), '-1');
    await page.evaluate(() => { document.querySelector('#action').click(); document.querySelector('#action').click(); });
    await page.waitForFunction(() => !document.querySelector('#action').hasAttribute('aria-busy'));
    assert.equal(calls.action, 1, 'A pending action cannot be sent twice');
    await page.fill('#name', 'Test'); await page.click('#submit'); await page.waitForFunction(() => !document.querySelector('#form').hasAttribute('aria-busy'));
    assert.equal(await page.locator('#name').getAttribute('aria-invalid'), 'true'); assert.equal(await page.locator('.sl-err').innerText(), 'Use a full name');
    formMode = 'ok';
    await page.evaluate(() => { document.querySelector('#form').requestSubmit(); document.querySelector('#form').requestSubmit(); });
    await page.waitForFunction(() => !document.querySelector('#form').hasAttribute('aria-busy'));
    assert.equal(calls.form, 2, 'Only one retry submits while pending'); assert.equal(await page.locator('#name').getAttribute('aria-invalid'), null);
    assert.equal(await page.locator('.sl-err').count(), 0);
    const failed = await page.evaluate(() => SL.post('/http-error', {}));
    assert.equal(failed.success, false); assert.equal(failed.ok, false); assert.equal(failed.status, 'error');
    assert.equal(await page.evaluate(() => SL.post('/expired', {}).then(() => '', e => e.message)), 'Your session expired. Sign in again before saving.');
    assert.equal(await page.evaluate(() => SL.fromHtml('', true).success), false);
    assert.equal(await page.evaluate(() => SL.fromHtml('<form>Sign in</form>', true).success), false);
    assert.equal(await page.evaluate(() => SL.fromHtml('<div class="alert-success">Saved</div>', true).success), true);
    assert.equal(await page.locator('#table').getAttribute('tabindex'), '0');
    assert(calls.bodies.filter(r => ['/action','/form','/toggle'].includes(r.url)).every(r => r.body.includes('fixture-token') && r.csrf === 'fixture-token'), 'Mutation requests retain CSRF protection');
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.waitForFunction(() => !document.querySelector('#sl-navigation').inert);
    assert.equal(await page.locator('#sl-navigation').evaluate(el => el.inert), false, 'Desktop navigation reopens after resizing');
    await page.click('.nav-open'); assert.equal(await page.locator('#sl-navigation').evaluate(el => el.inert), true);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.waitForFunction(() => matchMedia('(max-width: 900px)').matches && !document.querySelector('#navtg').checked);
    assert.equal(await page.locator('#sl-navigation').evaluate(el => el.inert), true, 'Desktop collapse does not open the phone drawer');
    assert.deepEqual(errors, []);
    console.log('Design runtime checks passed: navigation, dialogs, filters, tabs, theme, switch rollback, form validation, duplicate actions, HTTP/session errors, CSRF, table keyboard access.');
  } finally { if (browser) await browser.close(); await new Promise(resolve => server.close(resolve)); }
})().catch(e => { console.error(e); process.exitCode = 1; });
