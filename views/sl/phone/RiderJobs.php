<?php
/** Phone screen of the rider app. The markup lives in views/sl/pages/RiderJobs.php, which prints the design's phone frame parts when $roPhone is set. */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$roPhone = true;
$slPhone['cls'] = 'rs-r';
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-rider-flow.css')) ?>">
<?php include CDP_ROOT . '/views/sl/pages/RiderJobs.php';
