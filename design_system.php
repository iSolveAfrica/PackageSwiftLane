<?php
// DesignSystem board: static reference of the Swift Lane design tokens and icons (staff with view_tools).
require_once("loader.php");
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_tools');
$map = fn(array $rows) => array_map(fn($a) => ['name' => $a[0], 'hex' => $a[1], 'use' => $a[2]], $rows);
sl_render('DesignSystem', ['title' => 'Design system', 'board' => 'DesignSystem', 'plain' => true], [
    'surfaces' => $map([['bg', '#F3F2EF', 'App ground'], ['main', '#F0EFEB', 'Content panel'], ['panel', '#FAF9F7', 'Sidebar'], ['card', '#FBFAF8', 'Cards'], ['field', '#FFFEFC', 'Inputs'], ['hover', '#F2F0EC', 'Row hover'], ['active', '#ECEAE5', 'Selected'], ['line', '#E7E5DF', 'Hairline'], ['line-2', '#D9D7D0', 'Field border']]),
    'inks' => $map([['ink', '#161615', 'Text, dark button'], ['ink-2', '#44433F', 'Secondary text'], ['muted', '#6B6A65', 'Labels, captions'], ['faint', '#A9A8A2', 'Decorative only']]),
    'signals' => $map([['green', '#0EAD78', 'Healthy, delivered'], ['yellow', '#F2C400', 'Filling'], ['red', '#E5484D', 'Near limit, failed'], ['orange', '#D9792B', 'Chart line'], ['sky', '#2F7FC8', 'In transit'], ['violet', '#6C4BDD', 'Sorting, promo']]),
    'icons' => array_map(fn($n) => ['name' => $n, 'cls' => 'i-' . $n], explode(' ', 'grid box plane ship truck layers inbox warehouse search users user wheel shield card receipt dollar chart clock message sliders tag bell sun file sidebar right down left up plus ext check x filter download upload print pin scan mail phone lock eye calendar refresh more edit copy alert info send logout globe help book trash archive newdoc folder key route arrow trend image link apple')),
]);
