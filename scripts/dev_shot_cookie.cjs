// Screenshot with a cookie-jar login (works with any login form): node shot2.cjs <url> <out> <width> [cookiefile]
const { chromium } = require('playwright'); const fs = require('fs');
(async () => {
  const [,, url, out, w, jar] = process.argv;
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: parseInt(w || '1440'), height: 900 } });
  if (jar) { const lines = fs.readFileSync(jar, 'utf8').split('\n').map(l => l.replace(/^#HttpOnly_/, '')).filter(l => l && !l.startsWith('#')); const cookies = lines.map(l => { const p = l.split('\t'); return { name: p[5], value: p[6], domain: '127.0.0.1', path: p[2] }; }); await ctx.addCookies(cookies); }
  const page = await ctx.newPage(); const errors = [];
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  page.on('response', r => { if (r.status() >= 400) errors.push(r.status() + ' ' + r.url()); });
  await page.goto(url, { waitUntil: 'networkidle' });
  await page.screenshot({ path: out, fullPage: true });
  console.log('url now:', page.url(), 'height', await page.evaluate(() => document.body.scrollHeight));
  if (errors.length) console.log('ERRORS:\n' + errors.slice(0, 8).join('\n'));
  await browser.close();
})();
