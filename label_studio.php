<?php
// Label Studio (Swift Lane UI: views/sl/pages/LabelStudio.php): label options and live preview.
require_once("loader.php");
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate(['print_label', 'view_tools']);
require_once 'views/sl/data/labels.php';
$d = sl_labelStudioData($user, (int) ($_GET['id'] ?? 0), trim((string) ($_GET['q'] ?? '')), (string) ($_GET['view'] ?? ''));
sl_render('LabelStudio', ['id' => 'lblstudio', 'title' => 'Label Studio', 'board' => 'LabelStudio'], $d);
