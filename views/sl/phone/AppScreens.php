<?php
/**
 * App screens: every phone screen of the design, grouped as on the canvas, each row opening the page that
 * serves it. Same $d as the web page (app_screens.php): rows = [group, code, title, page, role, exists].
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['nav'] = 'staff';
$slPhone['tab'] = 'Menu';
$groups = [];
foreach ($d['rows'] as $r) $groups[$r[0]][] = $r;
$open = true;
// screen titles on the canvas carry sample names and numbers ("Good morning, Kofi", "SL-A240913"): show the screen name instead
$name = function (array $r): string {
    $t = (string) $r[2];
    if (preg_match('/^[A-Z]{2,3}-[A-Z]?\d/', $t)) return trim(preg_replace('/(?<!^)([A-Z])/', ' $1', substr((string) $r[1], 2)));
    return preg_replace('/,\s*\p{Lu}\p{Ll}+$/u', '', $t);
};
$roleLabel = ['public' => 'Signed out', 'staff' => 'Staff', 'customer' => 'Customer', 'rider' => 'Rider'];
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-settings.css')) ?>">
<?= sl_phoneTop('App screens', count($d['rows']) . ' screens', ['back' => sl_route('SettingsHub')]) ?>
<main class="mx-body">
<div class="mx-srch"><label><i class="ic i-search" aria-hidden="true"></i><input type="search" aria-label="Find a screen" placeholder="Find a screen" data-mx-menu-q></label></div>
<div class="mx-ban info"><i class="ic ic-sm i-grid" aria-hidden="true"></i><span>Every screen of the phone app and the page that shows it. Screens for other roles open as that role sees them only when signed in as one.</span></div>
<?php foreach ($groups as $label => $rows): ?>
<details class="mx-grp"<?= $open ? ' open' : '' ?> data-mx-menu-group><summary><span><?= h($label) ?></span><small><?= count($rows) ?></small><i class="ic ic-sm i-down" aria-hidden="true"></i></summary><section class="mx-card mx-list">
<?php foreach ($rows as $r): $ok = !empty($r[5]); $tag = $ok ? 'a' : 'div'; ?>
<<?= $tag ?> class="mx-row"<?= $ok ? ' href="' . h($r[3]) . '"' : '' ?> data-mx-menu-item><span class="mx-li"><i class="ic i-<?= $r[4] === 'customer' ? 'user' : ($r[4] === 'rider' ? 'truck' : ($r[4] === 'public' ? 'lock' : 'grid')) ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($name($r)) ?></b><small><span class="ph-code"><?= h($r[1]) ?></span> · <?= h($r[3]) ?> · <?= h($roleLabel[$r[4]] ?? $r[4]) ?></small></span><?php if ($ok): ?><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i><?php else: ?><span class="mx-rr"><span class="sl-pill plain">No page</span></span><?php endif; ?></<?= $tag ?>>
<?php endforeach; ?>
</section></details>
<?php $open = false; endforeach; ?>
</main>
