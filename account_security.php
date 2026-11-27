<?php
// My account security (views/sl/pages/AccountSecurity.php): every signed-in role, own account only.
require_once 'loader.php';$user=new User();$core=new Core();require_once 'helpers/sl_view.php';require_once 'helpers/sl_mx.php';sl_gate();
require_once 'views/sl/data/account_security.php';
sl_render('AccountSecurity',sl_mxPage(['id'=>'acctsec','title'=>'My account security','board'=>'AccountSecurity','scripts'=>['dataJs/sl/account_security_mx.js']]),sl_accountSecurityData((int)$_SESSION['userid']));
