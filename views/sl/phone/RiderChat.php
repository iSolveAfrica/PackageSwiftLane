<?php
/**
 * Phone screen of RiderChat (rider app design, root class rs-r). The markup is the design's components and is
 * shared with the computer page: views/sl/pages/RiderChat.php prints it into this frame's .mx-body.
 */
$GLOBALS['slRmPhone'] = true;
$slPhone['cls'] = 'rs-r';
$slPhone['tab'] = 'Account';
$slPhone['scripts'][] = 'dataJs/sl/rider_app.js';
include CDP_ROOT . '/views/sl/pages/RiderChat.php';
