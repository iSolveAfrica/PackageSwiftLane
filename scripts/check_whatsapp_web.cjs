/* Browser regression for the WhatsApp screen (whatsapp_web.php): the real template
 * (views/sl/pages/WhatsAppWeb.php) and script (dataJs/sl/whatsapp_web.js) against
 * fixture replies of ajax/comms/wa_web_ajax.php. No database, session or provider.
 * Run: CHROMIUM_PATH=... node scripts/check_whatsapp_web.cjs (PHP_BIN for php). */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const http = require('node:http');
const { spawnSync } = require('node:child_process');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const render = role => {
  const r = spawnSync(process.env.PHP_BIN || 'php', [path.join(__dirname, 'fixtures/whatsapp_web.php'), role], { cwd: root, encoding: 'utf8' });
  assert.equal(r.status, 0, r.stderr || r.stdout);
  assert.doesNotMatch(r.stdout, /Fatal error|Warning:|Deprecated:|Notice:/);
  return r.stdout;
};
const pages = { admin: render('admin'), staff: render('staff') };
const now = Math.floor(Date.now() / 1000);
const rows = [
  { id: '233541234567@c.us', group: 0, name: 'Esi Asante', phone: '233541234567', customer: 'Esi Asante', user_id: 9, locker: 'SWL2001', unread: 2, ts: now - 60, preview: 'Hi, is my parcel ready?', from_me: 0, ack: '', pinned: 0, muted: 0, tags: [], status: 'open', assignee: 0, bot: 0, participants: 0 },
  { id: '120363025555555555@g.us', group: 1, name: 'Swift Lane Customers Accra', phone: '', customer: '', user_id: 0, locker: '', unread: 0, ts: now - 600, preview: 'Yaw: Thanks', from_me: 0, ack: '', pinned: 0, muted: 0, tags: [], status: 'open', assignee: 0, bot: 0, participants: 6 },
];
const counts = { all: 2, unread: 1, groups: 1, customers: 1, mine: 0 };
const msgs = id => id.endsWith('@g.us')
  ? [{ id: 11, mid: 'a', me: 0, author: '233246139269', author_name: 'Ama', type: 'text', body: 'Good morning <b>all</b>', media: '', thumb: '', filename: '', ack: '', ts: now - 700, by: '' }]
  : [{ id: 1, mid: 'b', me: 0, author: '', author_name: '', type: 'text', body: 'Hi, is my parcel ready?', media: '', thumb: '', filename: '', ack: '', ts: now - 60, by: '' },
     { id: 2, mid: 'c', me: 1, author: '', author_name: '', type: 'text', body: 'Yes, *come today*', media: '', thumb: '', filename: '', ack: 'read', ts: now - 30, by: 'Test Admin' }];
const chat = id => ({ ok: true, sync: { ok: true, code: '', error: '' }, connected: true,
  chat: { id, group: id.endsWith('@g.us') ? 1 : 0, name: id.endsWith('@g.us') ? 'Swift Lane Customers Accra' : 'Esi Asante', phone: id.endsWith('@g.us') ? '' : '233541234567', tags: [], status: 'open', assignee: 0, assignee_name: '', bot: 0, thread_id: id.endsWith('@g.us') ? 0 : 4, participants: 6, participants_source: 'api' },
  customer: id.endsWith('@g.us') ? null : { id: 9, name: 'Esi Asante', first_name: 'Esi', locker: 'SWL2001', email: 'esi@example.test', phone: '', balance: 12.5, shipments: [], url: '', history_url: 'comms_customers.php?user=9' },
  bot_enabled: false, inbox_url: id.endsWith('@g.us') ? 'comms_inbox.php' : 'comms_inbox.php#c=4', me: 1, messages: msgs(id), sig: 's-' + id });

(async () => {
  const server = http.createServer((req, res) => {
    const u = new URL(req.url, 'http://localhost');
    if (u.pathname === '/' || u.pathname === '/staff') { res.setHeader('Content-Type', 'text/html'); res.end(u.pathname === '/' ? pages.admin : pages.staff); return; }
    const file = path.resolve(root, '.' + u.pathname);
    if (!file.startsWith(root + path.sep) || !fs.existsSync(file) || !/\.(js|css|svg|woff2?)$/.test(file)) { res.writeHead(404).end(); return; }
    res.setHeader('Content-Type', file.endsWith('.js') ? 'application/javascript' : file.endsWith('.css') ? 'text/css' : 'application/octet-stream');
    fs.createReadStream(file).pipe(res);
  });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  const base = 'http://127.0.0.1:' + server.address().port;
  const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', args: ['--no-sandbox'] });
  const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'ww-check-'));
  try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    const errors = [], calls = [];
    page.on('pageerror', e => errors.push(e.message));
    let sendDelay = 0, state = 'connected';
    await page.route('**/ajax/comms/wa_web_ajax.php**', async route => {
      const req = route.request(), u = new URL(req.url());
      const body = req.postData() || '';
      const action = u.searchParams.get('action') || (body.match(/name="action"\r\n\r\n([^\r]+)/) || [])[1] || new URLSearchParams(body).get('action');
      calls.push({ action, method: req.method(), body });
      const json = j => route.fulfill({ json: j });
      switch (action) {
        case 'state': return json({ ok: true, configured: true, problem: '', active: true, instance: 'instance1', state, raw: '', error: '', checked: now, me: { id: '233200000001@c.us', phone: '233200000001', name: 'Swift Lane Support', picture: '' }, is_admin: true, cached: 2, caps: { chats: true, messages: true, contacts: true, groups: true, read: true } });
        case 'chats': return json({ ok: true, sync: { ok: true, code: '', error: '' }, rows, counts, sig: 'L1', me: 1 });
        case 'chat': return json(chat(u.searchParams.get('id')));
        case 'poll': return json({ ok: true, state, sig: 'L1', msig: u.searchParams.get('msig') });
        case 'read': return json({ ok: true });
        case 'participants': return json({ ok: true, source: 'api', note: '', rows: [{ phone: '233246139269', admin: 0, name: 'Ama', locker: '', user_id: 0, saved: '', me: 0 }], name: 'Swift Lane Customers Accra', can_export: true });
        case 'quick_replies': return json({ ok: true, rows: [], vars: { hours: '' }, can_edit: true });
        case 'quick_replies_reset': return json({ ok: true, rows: [{ title: 'Opening hours', body: 'Our office hours: {hours}.' }], vars: { hours: 'Mon-Fri 9-5' }, message: 'The built-in quick replies are back.' });
        case 'send':
          if (sendDelay) await new Promise(r => setTimeout(r, sendDelay));
          return json(state === 'connected' ? { ok: true, msig: 'm2', messages: msgs('233541234567@c.us') } : { ok: false, code: 'not_connected', error: 'Not sent: WhatsApp is not connected', msig: 'm3', messages: msgs('233541234567@c.us') });
        case 'export': return route.fulfill({ status: 200, headers: { 'Content-Type': 'text/csv', 'Content-Disposition': 'attachment; filename="whatsapp-group-test.csv"' }, body: 'Name,Phone\nAma,+233246139269\n' });
        default: return json({ ok: false, error: 'unexpected ' + action });
      }
    });
    await page.goto(base + '/');
    await page.waitForSelector('.ww-row[data-chat]');
    assert.equal(await page.locator('.ww-row').count(), 2, 'chat list drawn');
    // Opening a chat reads it with GET and marks it read with a POST (a link can never mark chats read).
    await page.click('.ww-row[data-chat="233541234567@c.us"]');
    await page.waitForSelector('.ww-msg.out');
    await page.waitForTimeout(200);
    assert.ok(calls.some(c => c.action === 'chat' && c.method === 'GET'));
    assert.ok(calls.some(c => c.action === 'read' && c.method === 'POST'), 'unread chat marked read by POST');
    assert.match(await page.innerHTML('.ww-msg.out .ww-tx'), /<b>come today<\/b>/, 'WhatsApp formatting');
    // At 1440 with room for three panes the details open beside the conversation; the name only opens them.
    const widths = await page.evaluate(() => ({ main: document.querySelector('.ww-main').getBoundingClientRect().width, panel: !document.getElementById('ww_panel').hidden }));
    assert.ok(widths.main >= 480, 'conversation keeps its width: ' + widths.main);
    await page.click('.ww-ct');
    assert.equal(await page.evaluate(() => document.getElementById('ww_panel').hidden), false, 'name opens the details');
    await page.click('.ww-ct');
    assert.equal(await page.evaluate(() => document.getElementById('ww_panel').hidden), false, 'name never closes them');
    assert.equal(await page.getAttribute('.ww-panel a[href^="comms_inbox.php"]', 'href'), 'comms_inbox.php#c=4', 'Inbox link opens this conversation');
    // Enter pressed three times during a slow send posts once, with a nonce.
    sendDelay = 600;
    await page.fill('#ww_text', 'Double enter');
    await page.press('#ww_text', 'Enter'); await page.press('#ww_text', 'Enter'); await page.press('#ww_text', 'Enter');
    await page.waitForTimeout(900);
    const sends = calls.filter(c => c.action === 'send');
    assert.equal(sends.length, 1, 'one send for repeated Enter');
    assert.match(sends[0].body, /name="nonce"\r\n\r\n[\w-]{8,}/, 'send carries a nonce');
    sendDelay = 0;
    // Not connected: the attempt still goes to the server (it records it) and the toast says so.
    state = 'qr';
    await page.fill('#ww_text', 'While offline');
    await page.press('#ww_text', 'Enter');
    await page.waitForTimeout(400);
    assert.equal(calls.filter(c => c.action === 'send').length, 2, 'offline send reaches the server');
    assert.match(await page.textContent('#sl-toast'), /not connected/i);
    state = 'connected';
    // A file type this screen does not send is refused at once, with this screen's list.
    const exe = path.join(tmp, 'tool.exe'); fs.writeFileSync(exe, 'MZ');
    await page.setInputFiles('#ww_file', exe);
    assert.match(await page.textContent('#sl-toast'), /cannot be sent from this screen.*Pictures.*Documents/);
    assert.doesNotMatch(await page.textContent('#sl-toast'), /Videos/);
    assert.equal(await page.isVisible('#ww_att'), false);
    // The ticket form starts again for another chat.
    await page.click('[data-ww="ticket"]');
    const subjA = await page.inputValue('#ww_ticket_form [name=subject]');
    await page.click('#ww_dlg_ticket [data-sl-close]');
    await page.click('.ww-row[data-chat="120363025555555555@g.us"]');
    await page.waitForSelector('.ww-au');
    assert.match(await page.innerHTML('.ww-msg.in .ww-tx'), /&lt;b&gt;all&lt;\/b&gt;/, 'message text escaped');
    await page.click('.ww-ct');
    await page.click('[data-ww="ticket"]');
    const subjB = await page.inputValue('#ww_ticket_form [name=subject]');
    assert.notEqual(subjA, subjB, 'ticket subject follows the chat');
    await page.click('#ww_dlg_ticket [data-sl-close]');
    // Exports are POST with the CSRF token and come back as a download.
    await page.waitForSelector('[data-ww-dl="group"]');
    const [dl] = await Promise.all([page.waitForEvent('download'), page.click('[data-ww-dl="group"][data-format="csv"]')]);
    assert.equal(dl.suggestedFilename(), 'whatsapp-group-test.csv');
    const ex = calls.find(c => c.action === 'export');
    assert.equal(ex.method, 'POST'); assert.match(ex.body, /csrf_token=fixture-token/); assert.match(ex.body, /chat_id=120363025555555555%40g.us/);
    // Quick replies: an empty list offers the built-in ones back.
    await page.click('[data-ww="quick"]');
    await page.waitForSelector('#ww_quick [data-ww="qr-reset"]');
    page.once('dialog', d => d.accept());
    await page.click('#ww_quick [data-ww="qr-reset"]');
    await page.waitForTimeout(200);
    assert.ok(calls.some(c => c.action === 'quick_replies_reset' && c.method === 'POST'));
    // Filters wrap instead of hiding chips behind a scroll.
    const f = await page.evaluate(() => { const b = document.querySelector('.ww-filters'); return [b.scrollWidth, b.clientWidth]; });
    assert.ok(f[0] <= f[1] + 1, 'filter chips fit: ' + f.join('/'));
    // Less room (about what 1440 leaves with the side menu open): the details stay a column beside the conversation.
    await page.setViewportSize({ width: 1100, height: 900 });
    await page.waitForTimeout(200);
    const mid = await page.evaluate(() => ({ pos: getComputedStyle(document.getElementById('ww_panel')).position, main: document.querySelector('.ww-main').getBoundingClientRect(), panel: document.getElementById('ww_panel').getBoundingClientRect() }));
    assert.equal(mid.pos, 'static'); assert.ok(mid.main.width >= 360, 'conversation width at 1100: ' + mid.main.width);
    assert.ok(mid.main.right <= mid.panel.left + 1, 'details do not cover the conversation at 1100');
    // Too little room for three panes: the details float over the conversation instead of squeezing it.
    await page.setViewportSize({ width: 920, height: 900 });
    await page.waitForTimeout(200);
    const narrow = await page.evaluate(() => ({ pos: getComputedStyle(document.getElementById('ww_panel')).position, main: document.querySelector('.ww-main').getBoundingClientRect().width }));
    assert.equal(narrow.pos, 'absolute'); assert.ok(narrow.main >= 480, 'conversation width at 920: ' + narrow.main);
    // Phones: one pane, the filters wrap, no sideways scroll.
    await page.setViewportSize({ width: 390, height: 844 });
    await page.keyboard.press('Escape'); // the details float over the chat on a phone: Escape closes them
    assert.equal(await page.evaluate(() => document.getElementById('ww_panel').hidden), true, 'Escape closes the floating details');
    await page.click('[data-ww="back"]');
    await page.waitForTimeout(200);
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= 390), 'no sideways scroll at 390');
    assert.equal(await page.evaluate(() => getComputedStyle(document.querySelector('.ww-crumb-home')).display), 'none', 'short breadcrumb on phones');
    // Staff: no contacts or export tools.
    const staff = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await staff.goto(base + '/staff');
    assert.equal(await staff.locator('[data-ww="contacts"]').count(), 0, 'staff have no contacts dialog');
    assert.equal(await staff.locator('#ww_dlg_contacts').count(), 0);
    await staff.close();
    assert.deepEqual(errors, []);
    console.log('WhatsApp screen check passed (' + calls.length + ' fixture calls).');
  } finally {
    await browser.close(); server.close(); fs.rmSync(tmp, { recursive: true, force: true });
  }
})().catch(e => { console.error(e); process.exit(1); });
