<?php
/** Insurance and claims (Swift Lane design: InsuranceClaims). */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_freight');
require_once 'views/sl/data/freight.php';
sl_render('InsuranceClaims', ['id' => 'frins', 'title' => 'Insurance and claims', 'board' => 'InsuranceClaims'], sl_insuranceClaimsData($user, $core));
