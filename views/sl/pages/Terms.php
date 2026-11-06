<?php
/** Terms - public terms and conditions (terms.php), plain design page without the application shell. */
$sections = [
    ['Acceptance of terms', 'By accessing and using our website and services, you agree to be bound by these Terms and Conditions. If you disagree with any part of these terms, you should not use our site.'],
    ['Services', 'We provide shipping, tracking and logistics services under the conditions set out in these terms of use. We reserve the right to modify or discontinue any service without prior notice.'],
    ['User responsibility', 'The user is responsible for providing accurate information in all forms and processes on the site. Any attempt at fraud, information tampering, or misuse may result in account suspension.'],
    ['Intellectual property', 'All content on this site, including text, graphics and logos, is the property of ' . $d['site'] . ' or its respective owners and is protected by copyright law.'],
    ['Modifications', 'We reserve the right to modify these Terms at any time. Modifications will take effect as soon as they are published on the website.'],
    ['Governing law', 'These Terms are governed by the laws in effect in the country where our company is registered.'],
];
?>
<div class="sl-wrap" style="max-width:880px;margin:0 auto;padding:32px 16px">
  <div class="row between wrap g12" style="margin-bottom:24px">
    <a class="sl-brand" href="<?= h(sl_route('Login')) ?>" style="color:inherit"><span class="sl-logo"><i></i></span><?= h($d['site']) ?></a>
    <div class="row g8"><a class="sl-btn ghost sm" href="<?= h(sl_route('SignUp')) ?>"><i class="ic ic-sm i-user" aria-hidden="true"></i>Register</a><a class="sl-btn sm" href="<?= h(sl_route('Login')) ?>"><i class="ic ic-sm i-lock" aria-hidden="true"></i>Sign in</a></div>
  </div>
  <section class="sl-card col g16" style="padding:32px">
    <div class="col g6"><span class="sl-pill plain" style="align-self:flex-start">Legal</span><h1 class="sl-title">Terms and conditions</h1></div>
    <?php foreach ($sections as $i => $s): ?>
    <div class="col g6"><h2 class="sl-ct"><?= $i + 1 ?>. <?= h($s[0]) ?></h2><p style="line-height:1.6;color:var(--ink-2, var(--ink))"><?= h($s[1]) ?></p></div>
    <?php endforeach; ?>
    <div class="sl-banner info"><i class="ic ic-sm i-mail" aria-hidden="true"></i><span>For any questions or enquiries, contact us at <b><?= h($d['email']) ?></b></span></div>
  </section>
</div>
