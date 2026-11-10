<?php
require_once CDP_ROOT . '/helpers/sl_mobile.php';
$mobileLinks = sl_mobileLinks($user);
if ($mobileLinks):
$mobilePath = sl_self();
?>
<nav class="sl-mobile-nav" aria-label="<?= sl_mobileRole() === 'rider' ? 'Rider' : 'Customer' ?> app" style="--nav-count:<?= count($mobileLinks) ?>">
<?php foreach ($mobileLinks as [$label, $icon, $href]):
    $parts = parse_url($href); parse_str($parts['query'] ?? '', $params);
    $active = basename($parts['path']) === $mobilePath;
    if ($mobilePath === 'driver_app.php') $active = $active && ($params['tab'] ?? 'home') === ($_GET['tab'] ?? 'home');
    if ($label === 'Track' && $mobilePath === 'tracking.php') $active = true;
    if ($label === 'Bills' && in_array($mobilePath, ['pay_checkout.php', 'payment_return.php'], true)) $active = true;
?>
<a href="<?= h($href) ?>"<?= $active ? ' aria-current="page"' : '' ?>><i class="ic i-<?= h($icon) ?>" aria-hidden="true"></i><span><?= h($label) ?></span></a>
<?php endforeach; ?>
</nav>
<?php endif; ?>
