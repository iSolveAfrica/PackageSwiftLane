/* Shared-shell browser regression checks. Uses the app's real template/runtime
 * assets with isolated HTML; no database, authentication or external requests.
 * Run: node scripts/check_ui_runtime.cjs (Playwright + Chromium required). */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const html = `<!doctype html><html lang="en"><meta charset="utf-8"><title>UI regression fixture</title>
<link rel="stylesheet" href="/assets/template/dist/css/style.min.css">
<link rel="stylesheet" href="/assets/css_main_swiftlane/css/swiftlane-tokens.css">
<link rel="stylesheet" href="/assets/css_main_swiftlane/css/swiftlane-ds.css">
<div id="main-wrapper" data-layout="vertical" data-sidebartype="full">
 <a class="swl-skip-link" href="#swl-main-content">Skip to main content</a>
 <header class="topbar"><nav class="navbar top-navbar"><div class="navbar-header">
  <a class="nav-toggler d-block d-md-none" href="javascript:void(0)"><i>Menu</i></a>
 </div></nav></header>
 <aside class="left-sidebar"><div class="swl-brand">
  <a class="swl-brand__logo" href="#brand">Swiftlane</a>
  <a class="swl-brand__toggle sidebartoggler" href="javascript:void(0)">Menu</a>
 </div><div class="scroll-sidebar"><nav class="sidebar-nav"><ul id="sidebarnav">
 <li class="sidebar-item"><a class="sidebar-link" href="#shipments"><i>icon</i><span class="hide-menu">Shipments</span></a></li>
 <li class="sidebar-item"><a class="sidebar-link" href="#customers"><i>icon</i><span class="hide-menu">Customers</span></a></li>
 <li class="sidebar-item"><a class="sidebar-link" href="#explicit" aria-label="Manage settings"><i>icon</i><span class="hide-menu">Settings</span></a></li>
 <li><a class="create-btn" href="#create"><i>+</i><span class="hide-menu">Create Shipment</span></a></li>
 </ul></nav></div></aside>
 <div class="page-wrapper"><div class="container-fluid"><h1>Shipments</h1>
  <input id="search" type="text" placeholder="Search shipments"><button id="content-button">Create shipment</button>
  <div class="input-group" id="legacy-search"><div class="input-group-append"><button class="btn" type="submit"><i></i></button></div><input name="search" placeholder="Search offices" onkeyup="cdp_load(1);"></div>
  <form id="search-form"><div class="input-group"><div class="input-group-append"><button class="btn" type="submit" aria-label="Find records"><i></i></button></div><input name="search" placeholder="Search records" onkeyup="cdp_load(1);"></div></form>
  <div class="input-group" id="owned-search"><div class="input-group-append"><button class="btn" type="button" onclick="window.ownedClicks++" aria-label="Custom search"><i></i></button></div><input name="search" onkeyup="cdp_load(1);"></div>
  <div class="form-group" id="single-control"><label>Office name</label><input type="text"></div>
  <div class="form-group" id="compound-control"><label>Dimensions</label><input type="number"><input type="number"></div>
  <div class="form-group" id="wrapped-control"><label>Notes<textarea></textarea></label></div>
  <div class="form-group" id="named-control"><label>Display name</label><input type="text" aria-label="Existing field name"></div>
  <div id="resultados_ajax"></div>
  <div class="table-responsive" id="wide-table"><table class="table" style="min-width:1200px"><caption>Shipment records</caption><tr><th>Reference</th><th>Actions</th></tr>
  <tr><td>SL-TEST</td><td><div class="btn-group"><button class="btn dropdown-toggle" data-toggle="dropdown"><i></i></button></div></td></tr></table></div>
  <div class="table-responsive" id="small-table"><table class="table"><tr><td>Fits</td></tr></table></div>
 </div></div>
</div>
<script src="/assets/template/assets/libs/jquery/dist/jquery.min.js"></script>
<script src="/assets/template/assets/libs/perfect-scrollbar/dist/perfect-scrollbar.jquery.min.js"></script>
<script src="/assets/template/dist/js/app.min.js"></script>
<script src="/assets/template/dist/js/app.init.js"></script>
<script src="/assets/template/dist/js/feather.min.js"></script>
<script src="/assets/template/assets/extra-libs/sparkline/sparkline.js"></script>
<script src="/assets/template/dist/js/custom.min.js"></script>
<script>window.searchCalls=[]; window.ownedClicks=0; window.formSubmits=0; window.cdp_load=function(page){window.searchCalls.push(page)}; document.getElementById('search-form').addEventListener('submit',function(event){event.preventDefault(); window.formSubmits++});</script>
<script src="/dataJs/swiftlane_ui.js"></script></html>`;

(async () => {
    const server = http.createServer((req, res) => {
        const url = new URL(req.url, 'http://localhost');
        if (url.pathname === '/') { res.setHeader('Content-Type', 'text/html'); res.end(html); return; }
        const file = path.resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!file.startsWith(root + path.sep) || !fs.existsSync(file) || !fs.statSync(file).isFile()) {
            res.writeHead(404); res.end(); return;
        }
        res.setHeader('Content-Type', file.endsWith('.js') ? 'application/javascript' : file.endsWith('.css') ? 'text/css' : 'application/octet-stream');
        fs.createReadStream(file).pipe(res);
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    let browser;
    try {
        browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', args: ['--no-sandbox'] });
        const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.route('**/*', route => new URL(route.request().url()).hostname === '127.0.0.1' ? route.continue() : route.abort());
        await page.goto(`http://127.0.0.1:${server.address().port}/`);
        await page.waitForFunction(() => document.querySelector('.left-sidebar').inert);
        assert.equal(await page.locator('.left-sidebar').getAttribute('aria-hidden'), 'true', 'Hidden mobile menu is absent from accessibility navigation');
        assert.equal(await page.locator('#search').getAttribute('aria-label'), 'Search shipments', 'Search keeps an accessible name after typing');
        assert.equal(await page.locator('#wide-table').getAttribute('tabindex'), '0', 'Overflow table supports keyboard scrolling');
        assert.equal(await page.locator('#wide-table').getAttribute('aria-label'), 'Shipment records');
        assert.equal(await page.locator('#small-table').getAttribute('tabindex'), null, 'A fitting table does not add an unnecessary tab stop');
        assert.equal(await page.locator('.dropdown-toggle').getAttribute('aria-label'), 'Row actions');
        assert.equal(await page.locator('#resultados_ajax').getAttribute('aria-live'), 'polite');
        await page.evaluate(() => { window.cdpSwiftLaneUi.refresh(); window.cdpSwiftLaneUi.refresh(); });
        assert.equal(await page.locator('#legacy-search button').getAttribute('aria-label'), 'Search offices');
        await page.locator('#legacy-search button').click();
        assert.deepEqual(await page.evaluate(() => window.searchCalls), [1], 'Legacy search icon executes the existing search once after repeated refreshes');
        await page.locator('#search-form button').click();
        assert.equal(await page.evaluate(() => window.formSubmits), 1, 'A real form keeps its native submit behavior');
        assert.deepEqual(await page.evaluate(() => window.searchCalls), [1], 'Form search is not wired a second time');
        assert.equal(await page.locator('#search-form button').getAttribute('aria-label'), 'Find records', 'An existing search button name is preserved');
        await page.locator('#owned-search button').click();
        assert.equal(await page.evaluate(() => window.ownedClicks), 1, 'Explicit page search handlers remain authoritative');
        assert.deepEqual(await page.evaluate(() => window.searchCalls), [1]);
        assert.equal(await page.getByRole('textbox', { name: 'Office name', exact: true }).count(), 1, 'A single legacy control is associated with its existing label');
        assert.equal(await page.locator('#single-control label').getAttribute('for'), await page.locator('#single-control input').getAttribute('id'));
        assert.equal(await page.locator('#compound-control label').getAttribute('for'), null, 'Multi-control groups are not guessed or reassociated');
        assert.equal(await page.locator('#wrapped-control label').getAttribute('for'), null, 'An existing wrapped label is preserved');
        assert.equal(await page.locator('#named-control label').getAttribute('for'), null, 'An existing accessible name is preserved');
        await page.locator('.swl-skip-link').focus();
        await page.keyboard.press('Enter');
        assert.equal(await page.locator('.page-wrapper').evaluate(el => el === document.activeElement), true, 'Skip link moves keyboard focus into the main content');
        assert.equal(await page.locator('.page-wrapper').getAttribute('role'), 'main');
        await page.locator('.page-wrapper').evaluate(el => { el.id = 'existing-main'; });
        await page.evaluate(() => window.cdpSwiftLaneUi.refresh());
        assert.equal(await page.locator('.swl-skip-link').getAttribute('href'), '#existing-main', 'Skip link respects a page’s existing target ID');

        const mobileToggle = page.locator('.nav-toggler');
        await mobileToggle.focus();
        await page.keyboard.press('Space');
        await page.waitForFunction(() => document.querySelector('.left-sidebar').getAttribute('aria-modal') === 'true');
        assert.equal(await mobileToggle.getAttribute('aria-expanded'), 'true');
        assert.equal(await page.locator('.page-wrapper').evaluate(el => el.inert), true, 'Background cannot receive focus behind the drawer');
        assert.equal(await page.locator('.left-sidebar').evaluate(el => el.contains(document.activeElement)), true, 'Opening drawer moves focus into it');
        await page.locator('a[href="#create"]').focus();
        await page.keyboard.press('Tab');
        assert.equal(await page.locator('.swl-brand__logo').evaluate(el => el === document.activeElement), true, 'Tab wraps at the end of the drawer');
        await page.keyboard.press('Shift+Tab');
        assert.equal(await page.locator('a[href="#create"]').evaluate(el => el === document.activeElement), true, 'Shift+Tab wraps at the beginning of the drawer');
        await page.keyboard.press('Escape');
        assert.equal(await mobileToggle.evaluate(el => el === document.activeElement), true, 'Escape returns focus to the opener');
        assert.equal(await mobileToggle.getAttribute('aria-expanded'), 'false');
        assert.equal(await page.locator('.page-wrapper').evaluate(el => el.inert), false);
        await mobileToggle.click();
        await page.waitForFunction(() => document.querySelector('.left-sidebar').getAttribute('aria-modal') === 'true');
        await page.mouse.click(380, 700);
        await page.waitForFunction(() => document.querySelector('.left-sidebar').inert);

        await page.setViewportSize({ width: 900, height: 900 });
        await page.waitForFunction(() => document.querySelector('#main-wrapper').getAttribute('data-sidebartype') === 'mini-sidebar');
        assert.equal(await page.getByRole('link', { name: 'Shipments', exact: true }).count(), 1, 'Collapsed rail links retain accessible names before focus');
        assert.equal(await page.getByRole('link', { name: 'Create Shipment', exact: true }).count(), 1, 'Collapsed create action retains its accessible name');
        assert.equal(await page.locator('a[href="#shipments"] i').getAttribute('aria-hidden'), 'true', 'Decorative sidebar icons do not repeat the action name');
        assert.equal(await page.locator('a[href="#explicit"]').getAttribute('aria-label'), 'Manage settings', 'An explicit accessible name is preserved');
        const tabletToggle = page.locator('.swl-brand__toggle');
        await tabletToggle.click();
        await page.waitForFunction(() => document.querySelector('#main-wrapper').getAttribute('data-sidebartype') === 'full');
        await page.keyboard.press('Escape');
        await page.waitForFunction(() => document.querySelector('#main-wrapper').getAttribute('data-sidebartype') === 'mini-sidebar');
        // Closing saved "mini" used to make the observer undo this second open.
        await tabletToggle.click();
        await page.waitForFunction(() => document.querySelector('#main-wrapper').getAttribute('data-sidebartype') === 'full');
        await page.waitForTimeout(50);
        assert.equal(await page.locator('#main-wrapper').getAttribute('data-sidebartype'), 'full', 'Tablet can reopen after a saved collapsed preference');
        await page.evaluate(() => window.jQuery('body').trigger('resize'));
        assert.equal(await page.locator('#main-wrapper').getAttribute('data-sidebartype'), 'full', 'Widget resize events do not dismiss the temporary menu');
        await page.keyboard.press('Escape');

        await page.setViewportSize({ width: 1440, height: 900 });
        await tabletToggle.click();
        await page.waitForFunction(() => document.querySelector('#main-wrapper').getAttribute('data-sidebartype') === 'full');
        await page.reload();
        await page.waitForFunction(() => document.querySelector('#main-wrapper').getAttribute('data-sidebartype') === 'full');
        assert.equal(await page.locator('.left-sidebar').getAttribute('aria-modal'), null, 'Desktop navigation is not a modal');
        assert.equal(await page.locator('.page-wrapper').evaluate(el => el.inert), false);
        await page.locator('#wide-table table').evaluate(el => { el.style.minWidth = '0'; });
        await page.evaluate(() => window.cdpSwiftLaneUi.refresh());
        assert.equal(await page.locator('#wide-table').getAttribute('tabindex'), null, 'Fitting tables remove adapter-owned tab stops');
        assert.deepEqual(errors, [], 'Shared template and adapter have no uncaught browser errors');
        console.log('Shared UI runtime checks passed: skip link, mobile drawer focus/state/keyboard/backdrop, named collapsed links, tablet reopen and resize, desktop preference, search/action names, AJAX feedback, table keyboard scrolling.');
    } finally {
        if (browser) await browser.close();
        await new Promise(resolve => server.close(resolve));
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
