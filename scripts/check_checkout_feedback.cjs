/* Exercise the real checkout script with delayed bills and a rejected checkout. */
const {chromium}=require('playwright');const assert=require('node:assert/strict');const path=require('node:path');
(async()=>{const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/usr/bin/chromium',args:['--no-sandbox']});try{
 const page=await browser.newPage();await page.setContent('<div id="pc-config"></div><select id="pc-bill"><option value="1">One</option><option value="2">Two</option></select><div id="pc-packages"></div><div id="pc-summary"></div><b id="pc-total"></b><button id="pc-pay"><span>Pay</span></button>');
 await page.evaluate(()=>{window.calls=0;window.pending={};window.SL={get:(url,args)=>new Promise(resolve=>pending[args.cid]=resolve),post:()=>{calls++;return new Promise(resolve=>window.checkoutResolve=resolve);},toast:message=>window.lastToast=message};});
 await page.addScriptTag({path:path.resolve(__dirname,'../dataJs/sl/pay_checkout.js')});
 await page.selectOption('#pc-bill','2');
 await page.evaluate(()=>pending['2']({ok:true,packages:[{oid:22,tracking:'NEW-BILL',share:25,cleared:false}]}));
 await page.waitForFunction(()=>document.querySelector('#pc-pay').disabled===false);
 await page.evaluate(()=>pending['1']({ok:true,packages:[{oid:11,tracking:'OLD-BILL',share:90,cleared:false}]}));
 assert((await page.locator('#pc-packages').innerText()).includes('NEW-BILL'));assert(!(await page.locator('#pc-packages').innerText()).includes('OLD-BILL'));
 await page.evaluate(()=>{document.querySelector('#pc-pay').click();document.querySelector('#pc-pay').dispatchEvent(new MouseEvent('click'));});
 assert.equal(await page.evaluate(()=>calls),1);assert(await page.locator('#pc-bill').isDisabled());assert(await page.locator('#pc-packages input').isDisabled());
 await page.evaluate(()=>checkoutResolve({ok:false,message:'Please retry'}));await page.waitForFunction(()=>!document.querySelector('#pc-pay').disabled);
 assert(!(await page.locator('#pc-bill').isDisabled()));assert(!(await page.locator('#pc-packages input').isDisabled()));assert.equal(await page.evaluate(()=>lastToast),'Please retry');
 console.log('Checkout feedback checks passed: out-of-order bills, duplicate checkout protection, selection lock and failure recovery.');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
