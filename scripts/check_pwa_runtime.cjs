/* Public, read-only checks against a local PHP preview. No account credentials. */
const {chromium}=require('playwright'),assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const base=process.argv[2]||'http://localhost:8084';
if(!['localhost','127.0.0.1','[::1]'].includes(new URL(base).hostname))throw Error('Run this check against a local preview only');
(async()=>{const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/usr/bin/chromium',args:['--no-sandbox']});try{
 const context=await browser.newContext({viewport:{width:390,height:844}}),manifests=[];
 await context.route('**/*',route=>route.request().url().startsWith(base)?route.continue():route.abort());
 const pages={};
 for(const role of ['customer','rider']){
  const scope=`${base}/apps/${role}/`,manifest=await (await context.request.get(scope+'manifest.php')).json();manifests.push(manifest);
  assert.equal(manifest.id,`/apps/${role}/`);assert.equal(manifest.scope,'./');assert.equal(manifest.display,'standalone');assert(manifest.icons.some(i=>i.sizes==='192x192'));assert(manifest.icons.some(i=>i.sizes==='512x512'&&i.purpose==='maskable'));
  for(const icon of manifest.icons){const response=await context.request.get(new URL(icon.src,scope+'manifest.php').href);assert.equal(response.status(),200);const data=await response.body();assert.equal(data.readUInt32BE(16),parseInt(icon.sizes));assert.equal(data.readUInt32BE(20),parseInt(icon.sizes));}
  const page=await context.newPage();pages[role]=page;const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto(scope+'app.php/login.php');await page.evaluate(async()=>{await navigator.serviceWorker.ready;if(!navigator.serviceWorker.controller)await new Promise(resolve=>navigator.serviceWorker.addEventListener('controllerchange',resolve,{once:true}));});
  assert.equal(await page.evaluate(()=>document.baseURI),scope+'app.php/');assert.equal(await page.locator('meta[name=swiftlane-pwa]').getAttribute('content'),role);
  const registrationScope=await page.evaluate(async()=>{const r=await navigator.serviceWorker.ready;return r.scope;});assert.equal(registrationScope,scope);
  await page.locator('[data-pwa-install]').click();assert(await page.locator('#pwa-install-dialog').isVisible());await page.keyboard.press('Escape');await page.waitForFunction(()=>document.querySelector('#pwa-install-dialog').hidden);
  // No AJAX request or credential is cacheable, even though the worker controls the document.
  await page.evaluate(async()=>{await fetch('ajax/sl/delivery_ajax.php').catch(()=>{});await fetch('pickup_codes.php').catch(()=>{});});
  assert.deepEqual(errors,[]);
 }
 assert.notEqual(manifests[0].id,manifests[1].id);assert.notEqual(manifests[0].start_url,manifests[1].start_url);
 const cached=await pages.customer.evaluate(async()=>{const all=[];for(const name of await caches.keys()){const c=await caches.open(name);all.push({name,urls:(await c.keys()).map(r=>r.url)});}return all;});
 assert(cached.some(c=>c.name.includes('customer')));assert(cached.some(c=>c.name.includes('rider')));
 for(const cache of cached)for(const url of cache.urls)assert(!/app\.php|ajax\/|pickup_codes|pay_checkout|delivery_proof|uploads/.test(url),'Private content in cache: '+url);
 await context.setOffline(true);
 for(const role of ['customer','rider']){
  const page=pages[role];await page.goto(`${base}/apps/${role}/app.php/index.php`);assert((await page.locator('h1').innerText()).includes('reconnect'));assert.equal((await page.locator('.eyebrow').textContent()).trim(),role==='rider'?'Swift Lane Rider':'Swift Lane');
  const posted=await page.evaluate(async()=>{try{await fetch('app.php/ajax/sl/delivery_ajax.php',{method:'POST',body:'op=deliver'});return true;}catch(e){return false;}});assert.equal(posted,false,'Offline delivery mutations must not be queued or falsely accepted');
  const retry=await page.locator('.primary').getAttribute('href');assert.equal(new URL(retry,page.url()).pathname,`/apps/${role}/app.php/${role==='rider'?'driver_app.php':'index.php'}`);
 }
 await context.setOffline(false);
 // Incognito contexts deliberately block native installation. Check real eligibility
 // separately in a disposable normal Chromium profile.
 const profile=fs.mkdtempSync(path.join(os.tmpdir(),'swiftlane-pwa-install-'));
 let normal;
 try{
   normal=await chromium.launchPersistentContext(profile,{executablePath:process.env.CHROMIUM_PATH||'/usr/bin/chromium',headless:true,args:['--no-sandbox']});
   await normal.route('**/*',r=>r.request().url().startsWith(base)?r.continue():r.abort());
   for(const role of ['customer','rider']){
     const p=await normal.newPage();await p.goto(`${base}/apps/${role}/app.php/login.php`);await p.evaluate(()=>navigator.serviceWorker.ready);
     const cdp=await normal.newCDPSession(p);const manifest=await cdp.send('Page.getAppManifest');assert.deepEqual(manifest.errors,[]);
     const eligibility=await cdp.send('Page.getInstallabilityErrors');assert.deepEqual(eligibility.installabilityErrors,[],role+' installability');await p.close();
   }
 }finally{if(normal)await normal.close();fs.rmSync(profile,{recursive:true,force:true});}
 console.log('PWA checks passed: distinct IDs/scopes/icons, scoped sign-in, workers, installation help, isolated caches, role-specific offline screens and no private/offline mutation caching.');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1});
