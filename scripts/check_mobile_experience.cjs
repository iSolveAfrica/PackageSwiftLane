/* Shared phone shell and motion regressions. No accounts or external providers. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const html = `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<link rel="stylesheet" href="/assets/swiftlane/swiftlane.css"><link rel="stylesheet" href="/assets/swiftlane/app.css"><link rel="stylesheet" href="/assets/swiftlane/experience.css"></head>
<body class="sl-body-page" data-mobile-role="customer"><main class="sl-main"><section class="sl-phone-home"><h1>My parcels</h1><a href="#codes">Pickup codes</a></section><section data-sl-reveal id="codes" class="sl-card">Pickup code content stays available without animation</section><div style="height:1200px"></div><section data-sl-reveal id="end">Last parcel</section><input aria-label="Search" id="search"><div class="sl-mobile-sticky"><button class="sl-btn">Continue</button></div></main>
<nav class="sl-mobile-nav" aria-label="Customer app" style="--nav-count:5">${['Home','Track','Codes','Bills','Support'].map((t,i)=>`<a href="#${i}" ${i===0?'aria-current="page"':''}><i class="ic i-box"></i><span>${t}</span></a>`).join('')}</nav>
<iframe id="da-route-map" title="Route" src="about:blank"></iframe><button data-map-address="Accra & Tema" aria-label="Show stop 2 on map">2</button>
<script src="/assets/swiftlane/experience.js"></script><script src="/dataJs/sl/rider_map.js"></script></body></html>`;
const server=http.createServer((req,res)=>{
 if(req.url==='/'){res.setHeader('Content-Type','text/html');res.end(html);return;}
 const file=path.resolve(root,'.'+req.url.split('?')[0]);
 if(!file.startsWith(root+path.sep)||!fs.existsSync(file)){res.writeHead(404);res.end();return;}
 res.setHeader('Content-Type',file.endsWith('.js')?'application/javascript':'text/css');res.end(fs.readFileSync(file));
});
(async()=>{await new Promise(r=>server.listen(0,'127.0.0.1',r));let browser;
try{
 browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/usr/bin/chromium',args:['--no-sandbox']});
 const base=`http://127.0.0.1:${server.address().port}`;
 const page=await browser.newPage({viewport:{width:390,height:844}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.route('https://maps.google.com/**',r=>r.fulfill({contentType:'text/html',body:'Map provider fixture'}));
 await page.goto(base);
 for(const width of [320,360,390,430,767]){
  await page.setViewportSize({width,height:844});assert(await page.locator('.sl-mobile-nav').isVisible());
  assert(await page.locator('.sl-mobile-nav a').evaluateAll(es=>es.every(e=>e.getBoundingClientRect().height>=44&&e.getBoundingClientRect().width>=44)),'Thumb targets stay at least 44px');
  assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
 }
 for(const width of [768,1024,1440]){await page.setViewportSize({width,height:1000});assert(!(await page.locator('.sl-mobile-nav').isVisible()));assert(!(await page.locator('.sl-phone-home').isVisible()));}
 await page.setViewportSize({width:390,height:844});
 await page.evaluate(()=>document.body.dataset.mobileRole='');assert(!(await page.locator('.sl-mobile-nav').isVisible()),'Staff desktop shell has no app navigation');
 await page.evaluate(()=>document.body.dataset.mobileRole='rider');assert(await page.locator('.sl-mobile-nav').isVisible());
 await page.locator('#end').scrollIntoViewIfNeeded();await page.waitForFunction(()=>document.querySelector('#end').classList.contains('sl-revealed'));
 await page.emulateMedia({reducedMotion:'reduce'});assert.equal(await page.locator('#end').evaluate(e=>getComputedStyle(e).animationName),'none');
 await page.locator('[data-map-address]').click();assert.equal(await page.locator('[data-map-address]').getAttribute('aria-pressed'),'true');
 const mapURL=new URL(await page.locator('iframe').getAttribute('src'));assert.equal(mapURL.searchParams.get('q'),'Accra & Tema');
 await page.context().setOffline(true);await page.waitForFunction(()=>document.querySelector('.sl-network-status')&&!document.querySelector('.sl-network-status').hidden);
 await page.context().setOffline(false);await page.waitForFunction(()=>document.querySelector('.sl-network-status').hidden);
 await page.evaluate(()=>document.documentElement.classList.add('sl-keyboard-open'));assert(!(await page.locator('.sl-mobile-nav').isVisible()));
 const noJS=await browser.newPage({javaScriptEnabled:false,viewport:{width:390,height:844}});await noJS.goto(base);assert(await noJS.locator('#codes').isVisible());assert(await noJS.locator('.sl-mobile-nav').isVisible());
 assert.deepEqual(errors,[]);console.log('Mobile experience checks passed: phone/tablet/desktop, targets, no-JS content, reduced motion, map selection, keyboard space, offline feedback.');
}finally{if(browser)await browser.close();await new Promise(r=>server.close(r));}})().catch(e=>{console.error(e);process.exitCode=1;});
