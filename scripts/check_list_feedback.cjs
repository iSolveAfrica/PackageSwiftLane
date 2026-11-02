const fs = require('node:fs');
const assert = require('node:assert/strict');
const {chromium} = require('playwright');
const root=require('node:path').resolve(__dirname, '..');
const footer=fs.readFileSync(root+'/views/inc/footer.php','utf8');
const start=footer.indexOf('<div id="cdp-table-loader"');
const end=footer.indexOf('</script>',start)+9;
const block=footer.slice(start,end);
const selectStart=footer.indexOf('    // Rows-per-page: inject');
const selectBlock=footer.slice(footer.lastIndexOf('<script>',selectStart),footer.indexOf('</script>',selectStart)+9);
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH || '/usr/bin/chromium',headless:true,args:['--no-sandbox']});
 try {
  const page=await browser.newPage();
  const errors=[]; page.on('pageerror',e=>errors.push(e.message));
  await page.setContent('<!doctype html><div class="page-wrapper"><div id="resultados_ajax">Existing page message</div><div class="row"><div class="col-md-6"><div class="input-group"><input id="search"></div></div></div><div class="outer_div">Existing rows</div><footer class="footer"></footer></div>');
  await page.addScriptTag({path:root+'/assets/template/assets/libs/jquery/dist/jquery.min.js'});
  await page.evaluate(()=>{
   window.pending={}; window.sequence=0;
   jQuery.ajaxTransport('+*', function(options){return {send(headers,complete){pending[++sequence]={options,complete};},abort(){}};});
   window.cdp_load=()=>jQuery.ajax({url:'http://fixture.invalid/list',data:{page:1,search:'test'}});
  });
  await page.evaluate(html=>document.body.insertAdjacentHTML('beforeend',html.replace(/<script>[\s\S]*?<\/script>/g,'')),block);
  const script=block.match(/<script>([\s\S]*?)<\/script>/)[1];
  await page.addScriptTag({content:script});
  await page.addScriptTag({content:selectBlock.match(/<script>([\s\S]*?)<\/script>/)[1]});
  await page.waitForFunction(()=>document.querySelector('#per_page'));
  assert.equal(await page.locator('#per_page').getAttribute('aria-label'),'Rows per page');
  await page.evaluate(()=>{cdp_load();});
  assert.equal(await page.locator('#cdp-table-loader').getAttribute('aria-busy'),'true');
  assert.equal(await page.locator('.outer_div').getAttribute('aria-busy'),'true');
  assert.equal(await page.evaluate(()=>pending[1].options.data),undefined,'GET parameters moved into URL');
  await page.evaluate(()=>pending[1].complete(503,'Service Unavailable',{text:'unavailable'}));
  assert.match(await page.locator('#cdp-list-feedback').innerText(),/could not refresh.*out of date/);
  assert.equal(await page.locator('.outer_div').innerText(),'Existing rows');
  assert.equal(await page.locator('#resultados_ajax').innerText(),'Existing page message');
  assert.equal(await page.locator('#cdp-table-loader').getAttribute('aria-busy'),'false');
  await page.evaluate(()=>{cdp_load();pending[2].complete(401,'Unauthorized',{text:''});});
  assert.equal(await page.locator('#cdp-list-feedback a').getAttribute('href'),'login.php');
  await page.evaluate(()=>{cdp_load();pending[3].complete(403,'Forbidden',{text:''});});
  assert.match(await page.locator('#cdp-list-feedback').innerText(),/do not have permission/);
  await page.evaluate(()=>{cdp_load();pending[4].complete(0,'abort',{text:''});});
  assert.equal(await page.locator('#cdp-list-feedback').isVisible(),false,'Superseded/aborted request stays quiet');
  await page.evaluate(()=>{cdp_load();cdp_load();pending[6].complete(200,'OK',{text:'fresh'});pending[5].complete(500,'Error',{text:''});});
  assert.equal(await page.locator('#cdp-list-feedback').isVisible(),false,'Old failure cannot overwrite newer success');
  await page.evaluate(()=>{jQuery.ajax({url:'http://fixture.invalid/save',method:'POST',data:{id:3}});});
  await page.evaluate(()=>pending[7].complete(500,'Error',{text:''}));
  assert.equal(await page.locator('#cdp-list-feedback').isVisible(),false,'Non-list errors unaffected');
  // Shared CRUD scripts can call cdp_load even on Add/Edit forms. Without a
  // list result target, these background requests must not show list feedback.
  await page.evaluate(()=>{
   document.querySelector('.outer_div').remove();
   document.querySelector('#cdp-list-feedback').remove();
   cdp_load();
  });
  assert.equal(await page.locator('#cdp-table-loader').getAttribute('aria-busy'),'false','Form page request does not start list loader');
  await page.evaluate(()=>pending[8].complete(500,'Error',{text:''}));
  assert.equal(await page.locator('#cdp-list-feedback').count(),0,'Form page failure does not create misleading list error');
  assert.equal(await page.locator('#resultados_ajax').innerText(),'Existing page message');
  // Explicitly marked future list panels receive the same feedback contract.
  await page.evaluate(()=>{
   const panel=document.createElement('div');panel.setAttribute('data-cdp-list','');document.querySelector('.page-wrapper').prepend(panel);
   cdp_load();
  });
  assert.equal(await page.locator('[data-cdp-list]').getAttribute('aria-busy'),'true');
  await page.evaluate(()=>pending[9].complete(503,'Service Unavailable',{text:''}));
  assert.match(await page.locator('#cdp-list-feedback').innerText(),/could not refresh/);
  assert.equal(await page.locator('[data-cdp-list]').getAttribute('aria-busy'),'false');
  assert.deepEqual(errors,[]);
  console.log('Passed: GET list detection, busy state, failure/stale-record warning, session/permission states, abort and race handling, original messages/rows preserved, form/non-list isolation, explicit list panels, rows-per-page name.');
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
