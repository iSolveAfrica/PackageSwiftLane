<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software and its source code are proprietary and confidential    *
// * property of iSolveAfrica Ltd. and were developed specifically for     *
// * Swiftlane.                                                            *
// *                                                                       *
// * The software may not be copied, reproduced, modified, distributed,    *
// * sublicensed, published, or used in whole or in part except as         *
// * expressly permitted under the applicable license or written           *
// * agreement with iSolveAfrica Ltd. Any permitted copies or derivative   *
// * works must retain this copyright notice and all applicable            *
// * proprietary notices.                                                  *
// *                                                                       *
// *************************************************************************

 

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/querys.php');
require_login();
require_permission('prealert_list');


$db = new Conexion;
$user = new User;
$core = new Core;
$userData = $user->cdp_getUserData();

$search = cdp_sanitize($_REQUEST['search']);

$sWhere = "";

if ($search != null) {

	$sWhere .= " ";
}

// Row scope by role RANK, not the seed role ids 1/6: prealert_list is customer-facing, and a
// custom Customer/Driver/Agency role used to fall through to the unscoped branch and list every
// customer's prealerts. Pre-alerts carry no driver or agency column, so drivers see none and
// agency users / agency-restricted staff keep the "customers of this agency" filter.
require_once(__DIR__ . '/../../helpers/rbac.php');
$scopeLevel = (int) $userData->userlevel;
$scopeRank = cdp_roleRankById($scopeLevel);
$aid = 0;
$scopeByAgency = false;
if ($scopeRank === 0) {

	$sWhere .= cdp_ajaxListScopeSql('customer_id', null, null);
} else if ($scopeRank === 1 && ($scopeLevel === 3 || cdp_roleHasFlag($scopeLevel, 'is_driver'))) {

	$sWhere .= " and 1=0";
} else if ($scopeRank === 1) {

	$scopeByAgency = true;
	$aid = (int) cdp_getAgencyBranchIdForUser($userData->name_off ?? '');
	if ($aid <= 0) {
		$scopeCtx = cdp_getAgencyContext();
		$aid = (int) ($scopeCtx['agency_id'] ?? 0);
	}
	if ($aid <= 0) {
		$aid = (int) ($userData->agency_id ?? 0);
	}
} else {
	$scopeCtx = cdp_getAgencyContext();
	if (!empty($scopeCtx['is_restricted'])) {
		$scopeByAgency = true;
		$aid = (int) ($scopeCtx['agency_id'] ?? 0);
	}
}
if ($scopeByAgency) {
	// Agencia: solo prealertas de clientes que tienen órdenes/paquetes en esta agencia.
	// No agency at all: show nothing (agency 0 would match every unassigned order's customer).
	$sWhere .= $aid > 0
		? " and (customer_id IN (SELECT sender_id FROM cdb_add_order WHERE agency = " . $aid . ") OR customer_id IN (SELECT sender_id FROM cdb_customers_packages WHERE agency = " . $aid . ") OR customer_id IN (SELECT id FROM cdb_users WHERE name_off = (SELECT name_branch FROM cdb_branchoffices WHERE id = " . $aid . " LIMIT 1)))"
		: " and 1=0";
}



// // pagination variables
$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
$per_page = (($_REQUEST['per_page'] ?? '') === 'all') ? 1000000000 : (in_array((int)($_REQUEST['per_page'] ?? 0), [25, 50, 100], true) ? (int)$_REQUEST['per_page'] : 25); //how much records you want to show
$adjacents  = 4; //gap between pages after number of adjacents
$offset = ($page - 1) * $per_page;


$sql = "SELECT * FROM cdb_pre_alert  where tracking LIKE '%" . $search . "%' $sWhere order by prealert_date desc";


$db->cdp_query("SELECT COUNT(*) AS cdp_total FROM (" . $sql . ") AS cdp_cnt");
$cdp_cnt_row = $db->cdp_registro();
$numrows = $cdp_cnt_row ? (int) $cdp_cnt_row->cdp_total : 0;


$db->cdp_query($sql . " limit $offset, $per_page");
$data = $db->cdp_registros();

$total_pages = ceil($numrows / $per_page);


if ($numrows > 0) { ?>
	<div class="table-responsive">
		<table id="zero_config" class="table table-condensed table-hover table-striped custom-table-checkbox">
			<thead>
				<tr>
					<th class="text-center"><b><?php echo $lang['booking-list3'] ?></b></th>
					<th class="text-center"><b><?php echo $lang['left46'] ?></b></th>
					<?php
					if ($userData->userlevel != 1) { ?>

						<th class="text-center"><b><?php echo $lang['ncustomer'] ?></b></th>
					<?php } ?>
					<th class="text-center"><b><?php echo $lang['left47'] ?></b></th>
					<th class="text-center"><b><?php echo $lang['left48'] ?></b></th>
					<th class="text-center"><b><?php echo $lang['left49'] ?></b></th>
					<th class="text-center"><b><?php echo $lang['add-title15'] ?></b></th>
					<th class="text-center"><b><?php echo $lang['left66'] ?></b></th>
					<th class="text-center"><b><?php echo $lang['deliver-ship9'] ?></b></th>
					<th class="text-center"><b></b></th>

				</tr>
			</thead>
			<tbody id="projects-tbl">


				<?php if (!$data) { ?>
					<tr>
						<td colspan="6">
							<?php echo "
				<i align='center' class='display-3 text-warning d-block'><img src='assets/images/alert/ohh_shipment.png' width='150' /></i>								
				", false; ?>
						</td>
					</tr>
				<?php } else { ?>

					<?php

					$count = 0;
					foreach ($data as $row) {

						$db->cdp_query("SELECT * FROM cdb_users where id= '" . $row->customer_id . "'");
						$sender_data = $db->cdp_registro();


						$db->cdp_query("SELECT * FROM cdb_courier_com where id= '" . $row->courier_com . "'");
						$courier_com = $db->cdp_registro();


					?>
						<tr class="card-hovera">

							<td class="text-center">
								<?php echo date('Y-m-d', strtotime($row->prealert_date)); ?>
							</td>
							<td class="text-center"><b><?php echo $row->tracking; ?></b></td>

							<?php
							if ($userData->userlevel != 1) { ?>
								<td class="text-center">
									<?php echo $sender_data->fname; ?> <?php echo $sender_data->lname; ?>
								</td>
							<?php } ?>

							<td class="text-center"><?php echo $courier_com->name_com; ?></td>

							<td class="text-center">
								<?php echo $row->provider_shop; ?>
							</td>

							<td class="text-center">
								<?php echo $row->package_description; ?>
							</td>

							<td class="text-center">
								<?php echo $row->estimated_date; ?>
							</td>

							<td class="text-center">
								<b><?php echo $core->currency; ?></b> <?php echo cdb_money_format($row->purchase_price); ?>
							</td>
							<td class="text-center">
								<?php if ($row->is_package == 1) { ?>
									<span style="background: #5BE472;" class="label label-large"><?php echo $lang['customer-packages-text2'] ?></span>
								<?php } ?>
								<?php if ($row->is_package == 0) { ?>
									<span style="background: #FD571E;" class="label label-large"><?php echo $lang['customer-packages-text1'] ?></span>
								<?php } ?>
							</td>

							<td align='center'>
								<div class="btn-group">
										<button class="btn btn-block btn-outline-dark btn-sm dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
									        <i class="fas fa-ellipsis-v"></i> <!-- Utiliza el icono de puntos suspensivos -->
									    </button>
										<div class="dropdown-menu" style="overflow-y: auto; max-height: 200px;">

										<?php if ($user->cdp_hasPermission('see_attached_invoice_pre-alerts')) { ?>
										<a class="dropdown-item" href="<?php echo $row->url_invoice; ?>" target="_blank"><i style="color:#343a40" class="fa fa-search"></i>&nbsp;<?php echo $lang['customer-packages-text3'] ?></a>

										<?php } ?>

										<?php

										if ($user->cdp_hasPermission('invoice_prealert')) {
											if ($row->is_package == 0) {
										?>
												<a class="dropdown-item" href="courier_add.php?prealert_id=<?php echo $row->pre_alert_id; ?>"><i style="color:#343a40" class="ti-package"></i>&nbsp;<?php echo $lang['customer-packages-text4'] ?></a>

										<?php }
										}

										?>
									</div>
								</div>
							</td>
						</tr>
					<?php $count++;
					} ?>

				<?php } ?>
			</tbody>

		</table>


		<div class="pull-right">
			<?php echo cdp_paginate($page, $total_pages, $adjacents, $lang, 'prealert_list');	?>
		</div>



	</div>
<?php } ?>