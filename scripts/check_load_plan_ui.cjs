const {chromium}=require('playwright');
const {spawn}=require('node:child_process');
const assert=require('node:assert/strict');
(async()=>{
 const server=spawn('php',['-S','127.0.0.1:3018','-t',process.cwd()],{stdio:'ignore'});
 let browser;
 try {
  for(let i=0;i<50;i++){try{await fetch('http://127.0.0.1:3018/scripts/fixtures/consolidation_load_plan.php');break;}catch{await new Promise(r=>setTimeout(r,100));}}
  browser=await chromium.launch({headless:true});
  const page=await browser.newPage();
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  let calls=0;
  await page.route('**/ajax/sl/consolidation_load_plan.php',async route=>{
   const body=new URLSearchParams(route.request().postData());calls++;
   assert.equal(body.get('revision'),'2');
   assert.equal(JSON.parse(body.get('positions')).length,2);
   await route.fulfill({status:409,contentType:'application/json',body:JSON.stringify({success:false,message:'Another dispatcher changed this plan. Refresh before saving.'})});
  });
  await page.goto('http://127.0.0.1:3018/scripts/fixtures/consolidation_load_plan.php');
  await page.locator('.clp-editor summary').click();
  const rows=page.locator('[data-load-row]');
  for(let i=0;i<2;i++)for(const axis of ['bay','row','tier'])await rows.nth(i).locator('[name="'+axis+'"]').fill('1');
  await page.locator('[type=submit]').click();
  await page.waitForFunction(()=>document.querySelector('[data-load-feedback]').textContent.includes('Two packages'));
  assert.equal(calls,0);
  await rows.nth(1).locator('[name=row]').fill('2');
  await page.locator('[type=submit]').click();
  await page.waitForFunction(()=>document.querySelector('[data-load-feedback]').textContent.includes('Another dispatcher'));
  assert.equal(calls,1);
  for(const view of ['side','3d','top']){await page.locator('[data-load-view="'+view+'"]').click();assert.equal(await page.locator('[data-load-plan]').getAttribute('data-view'),view);}
  for(const width of [1440,390]){await page.setViewportSize({width,height:900});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2));}
  assert.deepEqual(errors,[]);
  console.log('Load-plan browser checks passed');
 } finally {if(browser)await browser.close();server.kill();}
})().catch(e=>{console.error(e);process.exit(1);});
