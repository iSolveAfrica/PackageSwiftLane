const { chromium } = require('playwright');
(async () => {
  const [,, url, out, w, user, pass] = process.argv;
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: parseInt(w||'1440'), height: 900 }, deviceScaleFactor: 1 });
  const page = await ctx.newPage();
  const errors = [];
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  page.on('response', r => { if (r.status() >= 400) errors.push(r.status() + ' ' + r.url()); });
  if (user) {
    await page.goto('http://127.0.0.1:8084/login.php');
    await page.fill('input[name="username"], #username, input[type="email"], input[name="email"]', user).catch(()=>{});
    await page.fill('input[name="password"], #password, input[type="password"]', pass);
    await Promise.all([page.waitForNavigation({ waitUntil: "load" }).catch(()=>{}), page.click('button[type="submit"], input[type="submit"]')]);
  }
  await page.goto(url, { waitUntil: "load" });
  await page.screenshot({ path: out, fullPage: true });
  console.log('url now:', page.url(), 'height', await page.evaluate(() => document.body.scrollHeight));
  if (errors.length) console.log('ERRORS:\n' + errors.join('\n'));
  await browser.close();
})();
