<?php
// Invite staff (Swift Lane UI): create a staff account with a role and send the invitation.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('add_user');
require_once 'views/sl/data/staff.php';
sl_render('InviteStaff', ['id' => 'staff', 'title' => 'Invite staff', 'board' => 'InviteStaff'], sl_inviteStaffData($user, $core));
