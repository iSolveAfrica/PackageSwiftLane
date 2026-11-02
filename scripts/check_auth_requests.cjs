// Run against the read-only temp preview. All mutation requests are intercepted.
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const base = process.argv[2] || 'http://127.0.0.1:8094';

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
  try {
    const page = await browser.newPage();
    await page.route('https://**', route => route.abort());
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));

    let pending, capture, requests = 0;
    const captured = () => new Promise(resolve => { capture = resolve; });
    const intercept = route => { requests++; pending = route; capture(); };
    await page.route('**/ajax/forgot-password-ajax.php', intercept);
    await page.goto(base + '/forgot-password.php', { waitUntil: 'networkidle' });
    await page.locator('#email').fill('isolated@example.invalid');
    const firstRequest = page.waitForRequest('**/ajax/forgot-password-ajax.php');
    const firstCaptured = captured();
    await page.evaluate(() => { $('#forgotPassword').trigger('submit').trigger('submit'); });
    const request = await firstRequest;
    await firstCaptured;
    assert.equal(requests, 1, 'recovery blocks concurrent submissions');
    assert.match(request.postData(), /_csrf_token=[a-f0-9]{64}/, 'recovery sends its CSRF token');
    assert.equal(await page.locator('#forgotPassword button').isDisabled(), true);
    await pending.fulfill({ status: 403, contentType: 'application/json', body: JSON.stringify({ success: false, errors: 'Your form session expired. Refresh this page and try again.' }) });
    await page.waitForFunction(() => !document.querySelector('#forgotPassword button').disabled);
    assert.match(await page.locator('#resultados_ajax').innerText(), /session expired/);
    assert.equal(await page.locator('#email').inputValue(), 'isolated@example.invalid');
    const secondRequest = page.waitForRequest('**/ajax/forgot-password-ajax.php');
    const secondCaptured = captured();
    await page.evaluate(() => $('#forgotPassword').trigger('submit'));
    await secondRequest;
    await secondCaptured;
    await pending.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: false, errors: '<img src=x onerror="window.authXss=true">' }) });
    await page.waitForFunction(() => !document.querySelector('#forgotPassword button').disabled);
    assert.equal(await page.locator('#resultados_ajax img').count(), 0, 'recovery renders errors as text');
    assert.equal(await page.evaluate(() => !!window.authXss), false);
    console.log('PASS recovery: concurrent submits, CSRF transport, 403 recovery, input retention, safe error text');

    requests = 0;
    await page.route('**/ajax/sign_up_ajax.php', intercept);
    await page.goto(base + '/sign-up.php', { waitUntil: 'networkidle' });
    await page.evaluate(() => {
      $('#fname').val('Isolated'); $('#lname').val('User'); $('#username').val('isolated');
      $('#email').val('isolated@example.invalid'); $('#address').val('Test address'); $('#postal').val('TEST');
      $('#document_number').val('TEST'); $('#pass,#pass2').val('Valid-password1'); $('#terms').prop('checked', true);
      iti.isValidNumber = () => true; iti.getNumber = () => '+15555550101';
    });
    const signupRequest = page.waitForRequest('**/ajax/sign_up_ajax.php');
    const signupCaptured = captured();
    await page.evaluate(() => $('#new_register').trigger('submit').trigger('submit'));
    const signup = await signupRequest;
    await signupCaptured;
    assert.equal(requests, 1, 'registration blocks concurrent submissions');
    assert.match(signup.postData(), /name="phone"\r\n\r\n\+15555550101/, 'registration syncs phone without blur');
    assert.match(signup.postData(), /name="_csrf_token"/, 'registration sends its CSRF token');
    assert.equal(await page.locator('#new_register button').isDisabled(), true);
    await pending.abort('failed');
    await page.waitForFunction(() => !document.querySelector('#new_register button').disabled);
    assert.match(await page.locator('.swal2-html-container').innerText(), /could not confirm your registration/);
    assert.equal(await page.locator('#fname').inputValue(), 'Isolated');
    await page.evaluate(() => Swal.close());

    const state = await page.evaluate(() => {
      cdp_load_states(); cdp_load_states();
      const handlers = $._data(document.getElementById('state'), 'events').change.filter(event => event.namespace === 'swlState').length;
      $('#country').append(new Option('Test country', '1', true, true));
      $('#state').append(new Option('Old state', '2', true, true));
      $('#city').append(new Option('Old city', '3', true, true)).prop('disabled', false);
      $('#country').trigger('change');
      return { handlers, state: $('#state').val(), city: $('#city').val(), disabled: $('#city').prop('disabled') };
    });
    assert.equal(state.handlers, 1, 'dependent selects do not accumulate handlers');
    assert.equal(state.state, null);
    assert.equal(state.city, null);
    assert.equal(state.disabled, true, 'country change clears stale city');
    console.log('PASS registration: concurrent submits, current phone transport, CSRF transport, network recovery, input retention, dependent-select reset');
    assert.deepEqual(errors, [], 'auth pages load without uncaught browser errors');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
