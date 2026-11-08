/* Browser regression for real ControlPanels, AirShipments and StaffAI templates.
 * Run with PHP_BIN pointing at a local PHP executable and Playwright available.
 * All replies are fixtures; no database writes or AI-provider requests occur. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { spawnSync } = require('node:child_process');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const rendered = spawnSync(process.env.PHP_BIN || 'php', [path.join(__dirname, 'fixtures/interactions.php')], { cwd: root, encoding: 'utf8' });
assert.equal(rendered.status, 0, rendered.stderr || rendered.stdout);
assert.doesNotMatch(rendered.stdout, /Fatal error|Warning:|Deprecated:/);
const fixture = rendered.stdout;
(async () => {
 const server = http.createServer((req, res) => {
  if (new URL(req.url, 'http://localhost').pathname === '/') { res.setHeader('Content-Type', 'text/html'); res.end(fixture); return; }
  const file = path.resolve(root, '.' + new URL(req.url, 'http://localhost').pathname);
  if (!file.startsWith(root + path.sep) || !fs.existsSync(file)) { res.writeHead(404).end(); return; }
  res.setHeader('Content-Type', file.endsWith('.js') ? 'application/javascript' : file.endsWith('.css') ? 'text/css' : 'text/plain'); fs.createReadStream(file).pipe(res);
 });
 await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
 const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', args: ['--no-sandbox'] });
 try {
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 }, hasTouch: true });
  const errors = [], calls = []; page.on('pageerror', e => errors.push(e.message));
  await page.route('**/ajax/ai_assistant/chat_ajax.php', async route => {
   calls.push(route.request().postData());
   await route.fulfill({ json: { ok: true, conversation_id: 2, answer: '### Shipment review\n\n| Shipment | Action |\n|---|---|\n| SL-TEST-001 | Check tracking |\n\n- Verify location.\n- Confirm pricing.\n- Update the customer.', tools: [{ name: 'count_records', summary: '1 record' }] } });
  });
  await page.goto('http://127.0.0.1:' + server.address().port);
  const chart = page.locator('.cpx-mb').first(), link = chart.locator('..'), tip = page.locator('#sl-chart-tooltip');
  await link.focus(); await page.keyboard.press('ArrowRight');
  assert.match(await tip.innerText(), /Awaiting approval: 0/);
  await page.keyboard.press('Space'); assert.equal(await tip.getAttribute('role'), 'dialog');
  const csvEvent = page.waitForEvent('download'); await tip.getByRole('button', { name: 'Download CSV' }).click();
  const chartCsv = await csvEvent; const csvText = fs.readFileSync(await chartCsv.path(), 'utf8');
  assert.match(csvText, /Awaiting approval/); assert.equal(csvText.split('\r\n').length, 8);
  assert.match(csvText, /"0"/); await page.keyboard.press('Escape'); assert.equal(await tip.isVisible(), false);
  assert.match(await link.getAttribute('href'), /courier_list.php/);
  const original = page.url(); await page.locator('.cpx-mb').nth(1).locator('i').last().tap();
  assert.match(await tip.innerText(), /Held parcels: 160/); assert.equal(page.url(), original);
  assert.equal(await tip.getByRole('link', { name: 'Open related view' }).isVisible(), true);
  await tip.getByRole('button', { name: 'Close', exact: true }).click();
  const mixed = page.locator('#mixed-chart'); await mixed.focus(); await page.keyboard.press('ArrowRight');
  assert.match(await tip.innerText(), /Shipments: 12/); assert.match(await tip.innerText(), /Packages: 4/);
  await page.keyboard.press('End'); assert.match(await tip.innerText(), /Shipments: 8/);
  await page.keyboard.press('Escape');
  await page.locator('#test-heat').focus(); await page.keyboard.press('ArrowRight'); assert.match(await tip.innerText(), /Nov 8: 12 parcels/);
  await page.keyboard.press('Escape'); await page.locator('#test-donut').focus(); await page.keyboard.press('Home'); assert.match(await tip.innerText(), /Parcels: 0/);
  await page.keyboard.press('ArrowRight'); assert.match(await tip.innerText(), /Parcels: 12/); await page.keyboard.press('Escape');
  // The actual shipment template uses ETA and shows the recorded time.
  const shipments = page.locator('#shipment-fixture');
  assert.equal(await shipments.getByRole('columnheader', { name: 'Destination', exact: true }).count(), 0);
  assert.equal(await shipments.getByRole('columnheader', { name: 'ETA', exact: true }).count(), 1);
  assert.match(await shipments.locator('tbody').innerText(), /Nov 15, 2026/); assert.match(await shipments.locator('tbody').innerText(), /08:03/);
  // Saved responses get full Markdown formatting on page load.
  const saved = page.locator('[data-ai-answer]');
  assert.equal(await page.locator('.ai-m.me .ai-response-actions').count(), 0, 'User messages keep their original plain text display');
  assert.equal(await saved.locator('h3').first().innerText(), 'Records to check first');
  assert.equal(await saved.locator('table tbody tr').count(), 2); assert.equal(await saved.locator('ol li').count(), 3);
  assert.equal(await saved.getByRole('link', { name: 'SL-TEST-001' }).getAttribute('href'), 'courier_view.php?id=1');
  assert.equal(await page.locator('.ai-response-sources summary').innerText(), 'Sources checked · 1');
  // Downloads contain precisely the visible response, with working absolute links.
  const reportEvent = page.waitForEvent('download'); await saved.getByRole('button', { name: 'Download report (.html)', exact: true }).click();
  const report = await reportEvent, documentText = fs.readFileSync(await report.path(), 'utf8');
  assert.match(documentText, /<table>/); assert.match(documentText, /Records to check first/); assert.match(documentText, /href="http:\/\/127\.0\.0\.1:\d+\/courier_view.php\?id=1"/); assert.doesNotMatch(documentText, /<script/);
  await page.context().addInitScript(() => { window.print = () => { window.fixturePrinted = true; }; });
  const printEvent = page.waitForEvent('popup'); await saved.getByRole('button', { name: 'Print / save PDF', exact: true }).click();
  const printPage = await printEvent; await printPage.waitForFunction(() => window.fixturePrinted === true);
  assert.equal(await printPage.locator('table tbody tr').count(), 2); assert.equal(await printPage.locator('script,button').count(), 0); await printPage.close();
  const tableEvent = page.waitForEvent('download'); await saved.getByRole('button', { name: 'Download table (.csv)', exact: true }).click();
  const tableDownload = await tableEvent; assert.match(fs.readFileSync(await tableDownload.path(), 'utf8'), /"SL-TEST-001","Example Customer"/);
  // Sanitization and spreadsheet formula escaping.
  await page.evaluate(() => {
   const target = document.createElement('div'); target.id = 'unsafe-response'; document.body.appendChild(target);
   SL.aiResponse.render(target, '### Test\n\n<img src=x onerror="window.xss=1"><script>window.xss=1</script>\n\n[bad](javascript:alert(1)) [external](https://example.com)\n\n| Name | Value |\n|---|---|\n| =SUM(1+1) | safe |');
  });
  const unsafe = page.locator('#unsafe-response');
  assert.equal(await unsafe.locator('img,script,iframe,[onclick],[onerror],a[href^="javascript:"]').count(), 0);
  assert.equal(await page.evaluate(() => window.xss), undefined);
  assert.equal(await unsafe.getByRole('link', { name: 'external' }).getAttribute('rel'), 'noopener noreferrer');
  const formulaEvent = page.waitForEvent('download'); await unsafe.getByRole('button', { name: 'Download table (.csv)' }).click();
  assert.match(fs.readFileSync(await (await formulaEvent).path(), 'utf8'), /"'=SUM\(1\+1\)"/);
  await unsafe.evaluate(node => node.remove());
  // A new reply uses the same renderer without altering the API request contract.
  await page.locator('#ai-q').fill('Which shipment needs attention?'); await page.locator('#ai-form').getByRole('button', { name: 'Send', exact: true }).click();
  await page.locator('.ai-m').last().getByRole('heading', { name: 'Shipment review', exact: true }).waitFor();
  assert.equal(calls.length, 1); assert.match(calls[0], /action=send/);
  assert.equal(await page.locator('.ai-m').last().getByRole('button', { name: 'Download table (.csv)' }).count(), 1);
  // Downloads and formatting never perform provider calls.
  assert.equal(calls.length, 1);
  if (process.env.UI_SCREENSHOTS) { fs.mkdirSync(process.env.UI_SCREENSHOTS, { recursive: true }); await page.locator('#ai-fixture').screenshot({ path: path.join(process.env.UI_SCREENSHOTS, 'ai-desktop.png') }); await page.locator('#control-fixture').screenshot({ path: path.join(process.env.UI_SCREENSHOTS, 'charts-desktop.png') }); }
  await page.setViewportSize({ width: 390, height: 844 }); await page.locator('#ai-fab').click();
  await page.locator('.ai-chat').waitFor({ state: 'visible' });
  await page.waitForFunction(() => getComputedStyle(document.querySelector('.ai-chat')).transform === 'none');
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1), false, 'The mobile page should not scroll horizontally');
  if (process.env.UI_SCREENSHOTS) await page.screenshot({ path: path.join(process.env.UI_SCREENSHOTS, 'ai-mobile.png') });
  assert.deepEqual(errors, []);
  console.log('PASS: chart values/zeroes, touch, keyboard, CSV export, ETA/date-time, saved/live AI formatting, HTML/CSV downloads, XSS/formula escaping and mobile layout.');
 } finally { await browser.close(); await new Promise(resolve => server.close(resolve)); }
})().catch(error => { console.error(error); process.exitCode = 1; });
