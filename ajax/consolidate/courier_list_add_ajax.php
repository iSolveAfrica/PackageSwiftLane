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
require_permission('view_consolidate_list');
// The view permission above is soft-passed for customers, drivers and agency users, and this
// lists every customer's unconsolidated shipments (sender names, contents, weights) for the
// consolidation add/edit screens: staff only, with the add or edit consolidation permission.
require_staff();
require_permission(['add_consolidate_shipment', 'edit_consolidate']);


$db = new Conexion;
$user = new User;
$core = new Core;
$userData = $user->cdp_getUserData();

$search = cdp_sanitize($_REQUEST['search'] ?? '');

// Dangerous-goods filter. A consolidation may only group ONE kind, so Find
// Shipments shows either dangerous goods (dg=1) or normal goods (dg=0), never
// both. Cast to int so it is safe to inline in the query below.
$dg = (isset($_REQUEST['dg']) && intval($_REQUEST['dg']) === 1) ? 1 : 0;
$dg_style = cdp_getDangerousGoodsStyle();

$sWhere = "";

if ($search != null) {
	$sWhere .= " and  CONCAT(a.order_prefix,a.order_no) LIKE '%" . $search . "%'";
}

// Agency-restricted staff only see (and so only consolidate) their own agency's shipments.
$cdpAgencyCtx = cdp_getAgencyContext();
if (!empty($cdpAgencyCtx['is_restricted']) && !empty($cdpAgencyCtx['agency_id'])) {
	$sWhere .= " and a.agency = " . (int) $cdpAgencyCtx['agency_id'];
}



// pagination variables
$page = max(1, (int) ($_REQUEST['page'] ?? 1));
$per_page = (($_REQUEST['per_page'] ?? '') === 'all') ? 1000000000 : (in_array((int)($_REQUEST['per_page'] ?? 0), [25, 50, 100], true) ? (int)$_REQUEST['per_page'] : 25); //how much records you want to show
$adjacents  = 4; //gap between pages after number of adjacents
$offset = ($page - 1) * $per_page;


$sql = "SELECT a.volumetric_percentage, a.is_pickup,  a.total_order, a.order_id, a.order_prefix, a.order_no, a.order_date, a.sender_id, a.receiver_id, a.order_courier, a.is_consolidate, a.is_dangerous_good, a.order_pay_mode, a.status_courier, a.driver_id, a.order_service_options,  b.mod_style, b.color FROM
			 cdb_add_order as a
			 INNER JOIN cdb_styles as b ON a.status_courier = b.id
			 $sWhere
			  and a.status_courier = 10 and a.is_consolidate = 0 and a.is_dangerous_good = $dg
			 order by a.order_id desc";


$db->cdp_query("SELECT COUNT(*) AS cdp_total FROM (" . $sql . ") AS cdp_cnt");
$cdp_cnt_row = $db->cdp_registro();
$numrows = $cdp_cnt_row ? (int) $cdp_cnt_row->cdp_total : 0;


$db->cdp_query($sql . " limit $offset, $per_page");
$data = $db->cdp_registros();

$total_pages = ceil($numrows / $per_page);


if ($numrows > 0) { ?>
	<div class="table-responsive">
		<div id="selection_bar" style="display:none; margin-bottom: 10px;">
			<button type="button" id="add_checked_packages" class="btn btn-success btn-sm"><i class="fa fa-plus"></i> Add Selected</button>
			<span id="selection_count" class="ml-3" style="font-weight: bold; color: #333;"></span>
				<span id="selection_totals" class="ml-3" style="font-weight: bold; color: #555;"></span>
		</div>

		<table id="zero_config" class="table table-condensed custom-table-checkbox">
			<thead>
				<tr>
					<th style="width: 40px;">
						<div class="custom-control custom-checkbox">
							<input type="checkbox" class="custom-control-input sl-all" id="select_all_packages">
							<label class="custom-control-label" for="select_all_packages"></label>
						</div>
					</th>
					<th><b><?php echo 'Sender Name' ?></b></th>
					<th><b><?php echo $lang['ltracking'] ?></b></th>
					<th><b>Carrier Tracking</b></th>
					<th><b><?php echo 'Contents' ?></b></th>
					<th><b><?php echo $lang['left215'] ?></b></th>
					<th><b><?php echo $lang['lstatusshipment'] ?></b></th>
				</tr>
			</thead>
			<tbody id="projects-tbl">


				<?php if (!$data) { ?>
					<tr>
						<td colspan="7">
							<?php echo "
				<i align='center' class='display-3 text-warning d-block'><img src='assets/images/alert/ohh_shipment.png' width='150' /></i>
				", false; ?>
						</td>
					</tr>
				<?php } else { ?>

					<?php

					// Item descriptions and customer names are customer-entered and may be stored raw or
					// already HTML-encoded (cdp_sanitize). Normalise to the plain text once ($cdpText),
					// encode it once for this table, and hand cdp_add_item() the plain text: it escapes
					// every text value itself before building its row HTML (dataJs/consolidate*_add/edit.js),
					// so neither path can be used for stored XSS on the staff screen.
					$cdpText = function ($v) {
						return html_entity_decode((string) $v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
					};
					$cdpHtml = function ($v) use ($cdpText) {
						return htmlspecialchars($cdpText($v), ENT_QUOTES, 'UTF-8');
					};
					// data-* attribute: getAttribute() returns the plain text.
					$cdpAttr = $cdpHtml;
					// JS string literal inside onclick: the JS value is the plain text. Line breaks and
					// U+2028/2029 are escaped too, or a multi-line description breaks the literal.
					$cdpJs = function ($v) use ($cdpText) {
						$js = str_replace(["\r", "\n", "\u{2028}", "\u{2029}"], ['\\r', '\\n', '\\u2028', '\\u2029'], addslashes($cdpText($v)));
						return htmlspecialchars($js, ENT_QUOTES, 'UTF-8');
					};
					$count = 0;
					foreach ($data as $row) {
                        // Get package total price.
                        $db->cdp_query("SELECT user_id, sender_id FROM cdb_add_order WHERE order_id='" . $row->order_id . "'");
                        $order_details = $db->cdp_registro();
                        
                        // Get order item description.
                        $db->cdp_query("SELECT order_item_description FROM cdb_add_order_item WHERE order_id = '" . $row->order_id . "'");
                        $description = $db->cdp_registro();
                        
                        $db->cdp_query("SELECT order_item_quantity FROM cdb_add_order_item WHERE order_id = '" . $row->order_id . "'");
                        $quantity = $db->cdp_registro();

                        // Get sender info.
                        $db->cdp_query("SELECT * FROM cdb_users WHERE id='" . $order_details->sender_id . "'");
                        $sender = $db->cdp_registro();

						// Overall PACKAGE weight (cdb_add_order.total_weight) — NOT the sum
						// of item weights. Item weights are the pricing weight; the
						// consolidation footer total must reflect each package's own weight.
						$db->cdp_query("SELECT IFNULL(total_weight, 0) as weight FROM cdb_add_order where order_id= '" . $row->order_id . "'");
						$order_weight = $db->cdp_registro();

						$weight = number_format($order_weight->weight, 2, '.', '');

						$db->cdp_query("SELECT IFNULL(sum(order_item_length), 0) as length from  cdb_add_order_item where order_id= '" . $row->order_id . "'");
						$order_length = $db->cdp_registro();

						$db->cdp_query("SELECT IFNULL(sum(order_item_height), 0) as height from cdb_add_order_item where order_id= '" . $row->order_id . "'");
						$order_height = $db->cdp_registro();

						$db->cdp_query("SELECT IFNULL(sum(order_item_width), 0) as width from cdb_add_order_item where order_id= '" . $row->order_id . "'");
						$order_width = $db->cdp_registro();

						$length = $order_length->length;
						$width = $order_width->width;
						$height = $order_height->height;

						$total_metric = $length * $width * $height / $row->volumetric_percentage;

						$db->cdp_query("SELECT * FROM cdb_styles where id= '14'");
						$status_style_pickup = $db->cdp_registro();

						$tracking = $row->order_prefix . $row->order_no;

					?>
						<tr class="card-hover" id="tb_row_id_<?php echo $row->order_id; ?>"
							data-order-id="<?php echo $row->order_id; ?>"
							data-total-metric="<?php echo $total_metric; ?>"
							data-weight="<?php echo $weight; ?>"
							data-length="<?php echo $length; ?>"
							data-width="<?php echo $width; ?>"
							data-height="<?php echo $height; ?>"
							data-tracking="<?php echo $cdpAttr($tracking); ?>"
							data-order-no="<?php echo $cdpAttr($row->order_no); ?>"
							data-order-prefix="<?php echo $cdpAttr($row->order_prefix); ?>"
                            data-sender="<?php echo $cdpAttr(cdp_nameWithLocker($sender)); ?>"
                            data-description="<?php echo $cdpAttr($description->order_item_description); ?>"
                            data-quantity="<?php echo $quantity->order_item_quantity; ?>"
                            data-total-order="<?php echo cdb_money_format($row->total_order); ?>"
                            data-total-order-raw="<?php echo $row->total_order; ?>"
                            data-dg="<?php echo (int)$row->is_dangerous_good; ?>"
                            >

								<td style="width: 40px;">
									<div class="custom-control custom-checkbox">
										<input type="checkbox" class="custom-control-input pkg-checkbox" value="<?php echo $row->order_id; ?>" id="pkg_<?php echo $row->order_id; ?>">
										<label class="custom-control-label" for="pkg_<?php echo $row->order_id; ?>"></label>
									</div>
								</td>
							<td><?php echo $cdpHtml(cdp_nameWithLocker($sender)); ?></td>

							<td><?php echo $cdpHtml($row->order_prefix . $row->order_no); ?></td>
							<?php $__pt = cdp_getPackageTrackingLegacyAware((int) $row->order_id); ?>
							<td><?php echo ($__pt && !empty($__pt->tracking_number)) ? htmlspecialchars((string) $__pt->tracking_number) : 'N/A'; ?></td>

                            <td><?php echo $cdpHtml($description->order_item_description); ?></td>

							<td><?php echo $weight; ?></td>

							<input type="hidden" id="total_ship_<?php echo $row->order_id; ?>" value="<?php echo cdb_money_format($row->total_order); ?>">

							<td>

								<span style="background: <?php echo $row->color; ?>;" class="label label-large"><?php echo $row->mod_style; ?></span>
								<br>

								<?php if ((int)$row->is_dangerous_good === 1 && $dg_style) { ?>
									<span style="background: <?php echo htmlspecialchars($dg_style->color, ENT_QUOTES, 'UTF-8'); ?>;" class="label label-large"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars(str_replace('_', ' ', $dg_style->mod_style), ENT_QUOTES, 'UTF-8'); ?></span>
									<br>
								<?php } ?>

								<?php
								if ($row->is_pickup == true) { ?>
									<span style="background: <?php echo $status_style_pickup->color; ?>;" class="label label-large"><?php echo $status_style_pickup->mod_style; ?></span>
								<?php
								}
								?>
							</td>

							<td class="text-right">
								<button type="button" name="add_row" id="add_row"
									onclick="cdp_add_item('<?php echo $row->order_id; ?>','<?php echo $total_metric; ?>', '<?php echo $weight; ?>', '<?php echo $length; ?>', '<?php echo $width; ?>', '<?php echo $height; ?>', '<?php echo $cdpJs($tracking); ?>', '<?php echo $cdpJs($row->order_no); ?>','<?php echo $cdpJs($row->order_prefix); ?>', '<?php echo $cdpJs(cdp_nameWithLocker($sender)); ?>', '<?php echo $cdpJs($description->order_item_description); ?>', '<?php echo cdb_money_format(($row->total_order)); ?>', '<?php echo $quantity->order_item_quantity; ?>', '<?php echo $row->total_order; ?>');
									$('#tb_row_id_<?php echo $row->order_id; ?>').remove();"
									class="btn btn-outline-success btn-sm add_row">
									<i class="fa fa-plus"></i>
								</button>
							</td>
						</tr>
					<?php $count++;
					} ?>

				<?php } ?>
			</tbody>

		</table>

		<div class="pull-right">
			<?php echo cdp_paginate($page, $total_pages, $adjacents, $lang, ''); ?>
		</div>
		<div class="clearfix"></div>

		<script>
			var count = 0;

			function updateSelectionBar() {
				var $checkboxes = $('.pkg-checkbox:checked');
				var checkedCount = $checkboxes.length;
				if (checkedCount > 0) {
					$('#selection_bar').show();
					$('#selection_count').html('Selected: ' + checkedCount);
					var totalWeight = 0, totalCost = 0;
					$checkboxes.each(function () {
						var $r = $(this).closest('tr');
						totalWeight += parseFloat($r.attr('data-weight')) || 0;
						totalCost += parseFloat($r.attr('data-total-order-raw')) || 0;
					});
					$('#selection_totals').html('Total weight: ' + totalWeight.toFixed(2) + ' <?php echo $core->weight_p; ?> &nbsp;|&nbsp; Total cost: ' + (typeof format_currency !== 'undefined' ? format_currency(totalCost) : totalCost.toFixed(2)));
				} else {
					$('#selection_bar').hide();
				}
			}

			// Row click to toggle checkbox
			$('#projects-tbl tr').not(':has(img)').on('click', function(e) {
				if (e.target.tagName === 'INPUT' || e.target.tagName === 'LABEL') return; // Don't toggle if clicking the checkbox itself
				var checkbox = $(this).find('.pkg-checkbox');
				checkbox.prop('checked', !checkbox.is(':checked')).trigger('change');
			});

			$(".sl-all").on('click', function() {
				$('.custom-table-checkbox input:checkbox').not(this).prop('checked', this.checked);
				$('.pkg-checkbox').trigger('change');
			});

			$('.pkg-checkbox').on('change', function() {
				if ($(this).is(':checked')) {
					$(this).parents('tr').css('background', '#fff8e1');
				} else {
					$(this).parents('tr').css('background', '');
				}
				updateSelectionBar();
			});

			$(document).ready(function() {
				updateSelectionBar();
			});
		</script>

		<script>
			$("#send_checkbox_status").submit(function(event) {

				$('#guardar_datos').attr("disabled", true);

				var parametros = $(this).serialize();
				var checked_data = new Array();
				$('.custom-table-checkbox').find('tr > td:first-child').find('input[type=checkbox]:checked').each(function() {
					checked_data.push($(this).val());
				});

				var status = $('#status_courier_modal').val();

				$.ajax({
					type: "GET",
					url: './ajax/courier/courier_update_multiple_ajax.php?status=' + status,

					data: {
						'checked_data': JSON.stringify(checked_data)
					},
					beforeSend: function(objeto) {},
					success: function(datos) {
						$("#resultados_ajax").html(datos);
						$('#guardar_datos').attr("disabled", false);
						$('#modalCheckboxStatus').modal('hide');


						cdp_load(1);

						$('#div-actions-checked').addClass('hide');
						$('#countChecked').addClass('hide');
						$('html, body').animate({
							scrollTop: 0
						}, 600);


					}
				});
				event.preventDefault();

			})
		</script>

		<script>
			//cdp_eliminar
			function cdp_printMultipleLabel() {

				var checked_data = new Array();
				$('.custom-table-checkbox').find('tr > td:first-child').find('input[type=checkbox]:checked').each(function() {
					checked_data.push($(this).val());
				});

				var name = $(this).attr('data-rel');
				new Messi('<b></i>' + message_print_confirm2 + '</b>', {
					title: message_print_confirm1,
					titleClass: '',
					modal: true,
					closeButton: true,
					buttons: [{
						id: 0,
						label: message_print_confirm3,
						class: '',
						val: 'Y'
					}],
					callback: function(val) {

						if (val === 'Y') {

							window.open('print_label_ship_multiple.php?data=' + JSON.stringify(checked_data), "_blank");

						}
					}

				});
			}
		</script>


	</div>
<?php } else { ?>
	<div class="text-center">
		<h4>There's no package ready for consolidation</h4>
	</div>
<?php } ?>