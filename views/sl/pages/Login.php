<?php
/**
 * Login - generated from docs/handover/artifact-source/Login.dc.html by scripts/sl_convert.py.
 * Shell page: no (plain page)
 * Data contract ($d):
 *   value noop
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="lg">
  <div aria-hidden="true" style="position:absolute;inset:0"><div class="lg-bg bg-plane"></div><div class="lg-bg bg-ship"></div><div class="lg-bg bg-wh"></div></div>
  <div class="lg-dim" aria-hidden="true"></div>
  <div class="lg-top"><div class="sl-brand" style="color:#fff"><span class="sl-logo"><i></i></span>Swift Lane</div><a class="sm fw5" href="<?= sl_route('PublicTracking') ?>">Track a package →</a></div>
  <main class="lg-card">
    <div class="col g6"><h1 style="font-size:28px;font-weight:500;letter-spacing:-.015em;margin:0">Welcome back</h1><p class="mut" style="margin:0">Log in to manage shipments, packages and pickups.</p></div>
    <form class="col g16" data-sl-on="submit:noop">
      <div class="sl-field"><label class="sl-lab" for="e">Email or phone</label><div class="sl-inwrap"><i class="ic ic-sm i-mail" aria-hidden="true"></i><input id="e" class="sl-in" type="email" autocomplete="username" placeholder="you@company.com"></div></div>
      <div class="sl-field"><div class="row between"><label class="sl-lab" for="p">Password</label><a href="<?= sl_route('ForgotPassword') ?>" class="sm fw5" style="color: var(--violet-ink)">Forgot password?</a></div><div class="sl-inwrap"><i class="ic ic-sm i-lock" aria-hidden="true"></i><input id="p" class="sl-in" type="password" autocomplete="current-password" placeholder="Enter your password"><i class="ic ic-sm i-eye" aria-hidden="true" style="margin-left:auto"></i></div></div>
      <label class="row g10" style="cursor:pointer"><span class="sl-toggle on" role="switch" aria-checked="true"></span><span>Keep me signed in on this device</span></label>
      <a class="sl-btn block" href="<?= sl_route('LoginOtp') ?>" style="height:48px;font-size:15px">Log in</a>
    </form>
    <div class="sl-sep" style="margin:0">or</div>
    <a class="sl-btn ghost block" href="<?= sl_route('LoginOtp') ?>" style="height:44px"><i class="ic ic-sm i-phone" aria-hidden="true"></i>Log in with a code by SMS</a>
    <p class="mut sm" style="text-align:center;margin:0">New to Swift Lane? <a href="<?= sl_route('SignUp') ?>" class="fw5" style="color: var(--ink); text-decoration: underline">Create an account</a></p>
  </main>
  <div class="lg-cap" aria-hidden="true"><div class="lg-caps"><div><b>Air freight, twice a week</b><div>New York to Accra in 5 to 7 days</div></div><div><b>Sea freight for big loads</b><div>Monthly containers, Newark to Tema</div></div><div><b>Your own US address</b><div>Shop online, we receive and ship it</div></div></div><div class="lg-dots"><i></i><i></i><i></i></div></div>
</div>
