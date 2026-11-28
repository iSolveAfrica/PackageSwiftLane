<?php
/**
 * Phone screen of RiderEarnings (rider app design, root class rs-r). The markup is the design's components and is
 * shared with the computer page: views/sl/pages/RiderEarnings.php prints it into this frame's .mx-body.
 */
$GLOBALS['slRmPhone'] = true;
$slPhone['cls'] = 'rs-r';
$slPhone['tab'] = 'Earnings';
$slPhone['scripts'][] = 'dataJs/sl/rider_app.js';
include CDP_ROOT . '/views/sl/pages/RiderEarnings.php';
