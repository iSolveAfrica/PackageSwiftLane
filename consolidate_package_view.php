<?php
// Sea consolidation detail moved to consolidate_view.php?type=sea.
require_once 'loader.php';
$user = new User();
require_once 'helpers/sl_view.php';
sl_gate('view_consolidate_package');
header('Location: consolidate_view.php?type=sea&id=' . (int) ($_GET['id'] ?? 0) . (!empty($_GET['legacy']) ? '&legacy=1' : ''));
exit;
