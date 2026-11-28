<?php
require_once CDP_ROOT . '/helpers/sl_mobile.php';
$mobileLinks = sl_mobileLinks($user);
if ($mobileLinks):
$mobilePath = sl_self();
?>
<nav class="sl-mobile-nav" aria-label="<?= sl_mobileRole() === 'rider' ? 'Rider' : (sl_mobileRole() === 'customer' ? 'Customer' : 'Staff') ?> app" style="--nav-count:<?= count($mobileLinks) ?>">
<?php $mobileFab = count($mobileLinks) === 5 && in_array(sl_bodyRole(), ['staff', 'customer', 'rider'], true) ? 2 : -1; $mobileFamily = sl_mobileTabFamily(); // centre tab raised (design: ControlPanelsMobile) ?>
<?php foreach ($mobileLinks as $mobileI => [$label, $icon, $href]):
    if ($href === '#menu'): ?>
<button type="button"<?= $mobileI === $mobileFab ? ' class="is-fab"' : '' ?> data-sl-nav aria-controls="sl-navigation" aria-expanded="false" aria-label="Open the menu"><i class="ic i-<?= h($icon) ?>" aria-hidden="true"></i><span><?= h($label) ?></span></button>
<?php continue; endif;
    $parts = parse_url($href); parse_str($parts['query'] ?? '', $params);
    $active = basename($parts['path']) === $mobilePath;
    if ($label === 'Home' && sl_bodyRole() === 'staff') $active = $active || $mobilePath === 'index.php';
    if ($mobilePath === 'driver_app.php') $active = $active && !isset($_GET['job']);
    if (in_array($mobilePath, $mobileFamily[$label] ?? [], true)) $active = true;
    if ($label === 'Jobs' && $mobilePath === 'driver_app.php' && isset($_GET['job'])) $active = true;
?>
<a href="<?= h($href) ?>"<?= $mobileI === $mobileFab ? ' class="is-fab"' : '' ?><?= $active ? ' aria-current="page"' : '' ?>><i class="ic i-<?= h($icon) ?>" aria-hidden="true"></i><span><?= h($label) ?></span></a>
<?php endforeach; ?>
</nav>
<?php endif; ?>
