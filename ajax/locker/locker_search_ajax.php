<?php
if (!function_exists('cdp_asset')) { $d = __DIR__; while ($d !== dirname($d) && !is_file($d . '/helpers/asset.php')) { $d = dirname($d); } if (is_file($d . '/helpers/asset.php')) require_once $d . '/helpers/asset.php'; }
// ajax/locker_search_ajax.php
require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/querys.php');
require_once(__DIR__ . '/../../helpers/pickup_code.php');
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('view_shipment_list');

$db    = new Conexion;
$core  = new Core;
$user  = new User;
$userData = $user->cdp_getUserData();

// Platform-wide search. One term is matched against most text fields of a
// package: customer (name, locker, e-mail, phone, ID document, username),
// recipient (name, e-mail, phone), item descriptions, sender/recipient address
// (country, state, city, zip, street), notes, the person who received it and
// the online-store provider. Tracking and packaging numbers are deliberately
// NOT matched.
//
// Each source table is scanned ONCE for the term (small, flat queries) and the
// matching ids are then used to pick the shipments/packages. A field that does
// not exist on this installation's schema is skipped instead of failing the
// whole search, and any database error is reported rather than swallowed.
$term = trim((string) ($_REQUEST['search'] ?? ''));

// Optional date range on the order date (YYYY-MM-DD from <input type="date">).
// Works with a search term, or on its own to list everything in the range.
$validDate = function ($d) {
    $d = trim((string) $d);
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return ($dt && $dt->format('Y-m-d') === $d) ? $d : '';
};
$dateFrom = $validDate($_REQUEST['date_from'] ?? '');
$dateTo   = $validDate($_REQUEST['date_to'] ?? '');
if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    list($dateFrom, $dateTo) = array($dateTo, $dateFrom);
}
$hasDate = ($dateFrom !== '' || $dateTo !== '');
$hasTerm = strlen($term) >= 2;

if (!$hasTerm && !$hasDate) {
    echo '<div class="alert alert-info mb-0">Start typing to search (2+ characters): customer name, locker ID, phone, e-mail, item description, address, notes&hellip; or pick a date range.</div>';
    exit;
}

$dateWhere = '';
$dateBinds = array();
if ($dateFrom !== '') { $dateWhere .= ' AND a.order_date >= :df'; $dateBinds[':df'] = $dateFrom; }
if ($dateTo !== '')   { $dateWhere .= ' AND a.order_date <= :dt'; $dateBinds[':dt'] = $dateTo; }

$like  = '%' . str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $term) . '%';
$limit = 100;   // rows shown per section
$cap   = 2000;  // ids collected per source table

$searchErrors = array();

// Run the first SQL variant that the database accepts; returns rows (array).
$cdp_srch = function (array $variants, array $binds) use ($db, &$searchErrors) {
    $last = '';
    foreach ($variants as $sql) {
        $db->cdp_query($sql);
        foreach ($binds as $k => $v) {
            if (strpos($sql, $k) !== false) { $db->bind($k, $v); }
        }
        if ($db->cdp_execute()) {
            return $db->cdp_registros() ?: array();
        }
        $last = $db->cdp_error();
    }
    $searchErrors[] = $last;
    return array();
};

$ids = function ($rows, $field) {
    $out = array();
    foreach ($rows as $r) { $out[] = $r->$field; }
    return array_values(array_unique($out));
};

$userIds = $recipIds = $shipItemIds = $pkgItemIds = $tracks = array();
if ($hasTerm) {
// 1) Customers (senders / package owners)
$userIds = array_map('intval', $ids($cdp_srch(array(
    "SELECT id FROM cdb_users WHERE CONCAT_WS(' ', fname, lname, locker, email, phone, document_number, username) LIKE :t LIMIT $cap",
    "SELECT id FROM cdb_users WHERE CONCAT_WS(' ', fname, lname, locker, email, phone) LIKE :t LIMIT $cap",
), array(':t' => $like)), 'id'));

// 2) Recipients
$recipIds = array_map('intval', $ids($cdp_srch(array(
    "SELECT id FROM cdb_recipients WHERE CONCAT_WS(' ', fname, lname, email, phone) LIKE :t LIMIT $cap",
), array(':t' => $like)), 'id'));

// 3) Item descriptions
$shipItemIds = array_map('intval', $ids($cdp_srch(array(
    "SELECT DISTINCT order_id FROM cdb_add_order_item WHERE order_item_description LIKE :t LIMIT $cap",
), array(':t' => $like)), 'order_id'));
$pkgItemIds = array_map('intval', $ids($cdp_srch(array(
    "SELECT DISTINCT order_id FROM cdb_customers_packages_detail WHERE order_item_description LIKE :t LIMIT $cap",
), array(':t' => $like)), 'order_id'));

// 4) Addresses (linked by the full order number, e.g. AWB100900)
$tracks = $ids($cdp_srch(array(
    "SELECT DISTINCT order_track FROM cdb_address_shipments
      WHERE CONCAT_WS(' ', sender_country, sender_state, sender_city, sender_zip_code, sender_address,
                           recipient_country, recipient_state, recipient_city, recipient_zip_code, recipient_address) LIKE :t
      LIMIT $cap",
), array(':t' => $like)), 'order_track');
}

$inInts = function ($list) { return $list ? implode(',', $list) : '0'; };
$trackBinds = array();
$trackPh = array();
foreach ($tracks as $i => $t) { $trackPh[] = ':k' . $i; $trackBinds[':k' . $i] = (string) $t; }
$trackIn = $trackPh ? implode(',', $trackPh) : "''";

// Same visibility rules as the shipment / package lists: a customer sees only
// their own, a driver only those assigned to them, an agency its own branch.
$scope = '';
if ($userData->userlevel == 1) {
    $scope = ' AND a.sender_id = ' . (int) $_SESSION['userid'];
} elseif ($userData->userlevel == 3) {
    $scope = ' AND a.driver_id = ' . (int) $_SESSION['userid'];
} elseif ($userData->userlevel == 6) {
    $scope = ' AND a.agency = ' . (int) cdp_getAgencyBranchIdForUser($userData->name_off ?? '');
}

// Courier shipments
$shipSelect = "SELECT 
            a.order_incomplete, a.status_invoice, a.is_consolidate, a.is_pickup, a.total_order,
            a.order_id, a.order_prefix, a.order_no, a.order_date, a.sender_id, a.receiver_id,
            a.order_courier, a.order_pay_mode, a.status_courier, a.driver_id,
            a.order_service_options, a.order_deli_time, b.mod_style, b.color,
            %s AS tracking_number, %s AS estimated_eta
        FROM cdb_add_order AS a
        INNER JOIN cdb_styles AS b ON a.status_courier = b.id
        %s
        WHERE a.status_courier != 14 $scope $dateWhere
          %s
        ORDER BY a.order_id DESC
        LIMIT $limit";
$shipMatch = function ($extra) use ($hasTerm, $inInts, $userIds, $recipIds, $shipItemIds, $trackIn) {
    if (!$hasTerm) { return ''; }
    return "AND (a.sender_id IN (" . $inInts($userIds) . ")
               OR a.receiver_id IN (" . $inInts($recipIds) . ")
               OR a.order_id IN (" . $inInts($shipItemIds) . ")
               OR CONCAT(a.order_prefix, a.order_no) IN ($trackIn)
               $extra)";
};
$joinTn = "LEFT JOIN cdb_package_tracking_number AS c ON a.order_id = c.order_id";
$orders = $cdp_srch(array(
    sprintf($shipSelect, "COALESCE(NULLIF(c.tracking_number, ''), a.tracking_num)", 'c.estimated_eta', $joinTn, $shipMatch("OR CONCAT_WS(' ', a.notes, a.person_receives) LIKE :t")),
    sprintf($shipSelect, "COALESCE(NULLIF(c.tracking_number, ''), a.tracking_num)", 'c.estimated_eta', $joinTn, $shipMatch('')),
    sprintf($shipSelect, "''", "''", '', $shipMatch('')),
), array(':t' => $like) + $trackBinds + $dateBinds);

// Customer packages
$pkgSelect = "SELECT a.order_id, a.order_prefix, a.order_no, a.order_date, a.sender_id,
            a.status_courier, a.is_consolidate, a.total_weight, a.total_order, b.mod_style, b.color,
            (SELECT GROUP_CONCAT(d.order_item_description SEPARATOR ', ')
               FROM cdb_customers_packages_detail AS d WHERE d.order_id = a.order_id) AS descriptions
        FROM cdb_customers_packages AS a
        INNER JOIN cdb_styles AS b ON a.status_courier = b.id
        WHERE 1 = 1 $scope $dateWhere
          %s
        ORDER BY a.order_id DESC
        LIMIT $limit";
$pkgMatch = function ($extra) use ($hasTerm, $inInts, $userIds, $pkgItemIds, $trackIn) {
    if (!$hasTerm) { return ''; }
    return "AND (a.sender_id IN (" . $inInts($userIds) . ")
               OR a.order_id IN (" . $inInts($pkgItemIds) . ")
               OR CONCAT(a.order_prefix, a.order_no) IN ($trackIn)
               $extra)";
};
$packages = $cdp_srch(array(
    sprintf($pkgSelect, $pkgMatch("OR CONCAT_WS(' ', a.notes, a.person_receives, a.provider_purchase) LIKE :t")),
    sprintf($pkgSelect, $pkgMatch('')),
), array(':t' => $like) + $trackBinds + $dateBinds);

if ($searchErrors && $userData->userlevel != 1) {
    echo '<div class="alert alert-danger">Part of the search failed: ' . htmlspecialchars(implode(' | ', array_filter($searchErrors))) . '</div>';
}

// Consolidation membership for the whole list in one query: a shipment inside a
// consolidation reports the CONSOLIDATION's status and ETA.
cdp_prefetchConsolidations(array_map(function ($r) { return $r->order_no; }, $orders ?: array()));

if (!$orders && !$packages) {
    echo '<div class="alert alert-warning">No shipments or packages match' . ($hasTerm ? ' &ldquo;' . htmlspecialchars($term) . '&rdquo;' : '') . ($hasDate ? ' between ' . htmlspecialchars($dateFrom ?: 'the start') . ' and ' . htmlspecialchars($dateTo ?: 'today') : '') . '.</div>';
    exit;
}
?>

<?php if ($orders) { ?>
<h5 class="mt-2">Shipments (<?php echo count($orders) >= $limit ? 'first ' . $limit : count($orders); ?>)</h5>
<div class="table-responsive">
    <table id="zero_config" class="table table-condensed table-hover table-striped custom-table-checkbox">
        <thead>
            <tr>
                <?php if ($userData->userlevel == 9) { ?>
                    <th>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input sl-all" id="cstall">
                            <label class="custom-control-label" for="cstall"></label>
                        </div>
                    </th>
                <?php } ?>
                <th><b><?php echo $lang['ltracking']; ?></b></th>
                <th><b><?php echo $lang['customTracking']; ?></b></th>
                <th><b><?php echo $lang['ddate']; ?></b></th>
                <th><b><?php echo $lang['eta']; ?></b></th>
                <?php if ($userData->userlevel == 9 || $userData->userlevel == 2) { ?>
                    <th><b><?php echo $lang['left498']; ?></b></th>
                <?php } ?>
                <th><b><?php echo $lang['left499']; ?></b></th>
                <?php if ($userData->userlevel == 9 || $userData->userlevel == 2) { ?>
                    <th><b><?php echo $lang['lorigin']; ?></b></th>
                <?php } ?>
                <th><b><?php echo $lang['ldestination']; ?></b></th>
                <th><b><?php echo $lang['lstatusshipment']; ?></b></th>
                <th><b><?php echo $lang['ship-all5']; ?></b></th>
                <th></th>
                <th><b><?php echo $lang['global-3']; ?></b></th>
                <th><b></b></th>
            </tr>
        </thead>
        <tbody id="projects-tbl">
            <?php if (!$orders) { ?>
                <tr>
                    <td colspan="6">
                        <?php echo "<i align='center' class='display-3 text-warning d-block'><img src='assets/images/alert/ohh_shipment.png' width='150' /></i>", false; ?>
                    </td>
                </tr>
            <?php } else { 
                $count = 0;
                foreach ($orders as $row) {

                    // Get sender, recipient, driver, etc.
                    $db->cdp_query("SELECT * FROM cdb_users WHERE id = '" . $row->sender_id . "'");
                    $sender_data = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_recipients WHERE id = '" . $row->receiver_id . "'");
                    $receiver_data = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_users WHERE id = '" . $row->driver_id . "'");
                    $driver_data = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_courier_com WHERE id = '" . $row->order_courier . "'");
                    $courier_com = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_met_payment WHERE id = '" . $row->order_pay_mode . "'");
                    $met_payment = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_shipping_mode WHERE id = '" . $row->order_service_options . "'");
                    $order_service_options = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_styles WHERE id = '14'");
                    $status_style_pickup = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_styles WHERE id = '13'");
                    $status_style_consolidate = $db->cdp_registro();

                    $text_status = ""; $label_class = "";
                    if ($row->status_invoice == 1) {
                        $text_status = $lang['invoice_paid'];
                        $label_class = "label-success";
                    } else if ($row->status_invoice == 2) {
                        $text_status = $lang['invoice_pending'];
                        $label_class = "label-warning";
                    } else if ($row->status_invoice == 3) {
                        $text_status = $lang['verify_payment'];
                        $label_class = "label-info";
                    }

                    $db->cdp_query("SELECT * FROM cdb_address_shipments WHERE order_track='" . $row->order_prefix . $row->order_no . "'");
                    $address_order = $db->cdp_registro();
                    ?>
                    <tr class="card-hovera">
                        <?php if ($userData->userlevel == 9) { ?>
                            <td class="chb">
                                <?php if ($row->status_courier != 8 && $row->status_courier != 21) { ?>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" value="<?php echo $row->order_no; ?>" name="checkbox[]" id="cst_<?php echo $count; ?>">
                                        <label class="custom-control-label" for="cst_<?php echo $count; ?>">&nbsp;</label>
                                    </div>
                                <?php } ?>
                            </td>
                        <?php } ?>
                        <td>
                            <b>
                                <a href="courier_view.php?id=<?php echo $row->order_id; ?>&tracking_number=<?php echo $row->tracking_number; ?>&eta=<?php echo $row->estimated_eta; ?>">
                                    <?php echo $row->order_prefix . $row->order_no; ?>
                                </a>
                            </b>
                        </td>
                        <td><?php echo $row->tracking_number; ?></td>
                        <td><?php echo $row->order_date; ?></td>
                        <td><?php echo cdp_getEffectiveEta($row->order_id, $row->order_no, $row->order_deli_time ?? null, $row->is_consolidate); ?></td>
                        <?php if ($userData->userlevel == 9 || $userData->userlevel == 2) { ?>
                            <td><?php echo $sender_data->fname; ?> <?php echo $sender_data->lname; ?></td>
                        <?php } ?>
                        <td><?php echo empty($receiver_data->fname) ? $sender_data->fname : $receiver_data->fname; ?> <?php echo empty($receiver_data->lname) ? $sender_data->lname : $receiver_data->lname; ?></td>
                        <?php if ($userData->userlevel == 9 || $userData->userlevel == 2) { ?>
                            <td><?php echo $address_order->sender_country; ?>-<?php echo $address_order->sender_city; ?></td>
                        <?php } ?>
                        <td><?php echo $address_order->recipient_country; ?>-<?php echo $address_order->recipient_city; ?></td>
                        <td>
                            <?php $eff = cdp_getEffectiveStatus($row->order_no, $row->status_courier, $row->is_consolidate); ?>
                            <span style="background: <?php echo $eff->color; ?>;" class="label label-large"><?php echo $eff->mod_style; ?></span>
                            <br>
                            <?php if ($row->is_pickup) { ?>
                                <span style="background: <?php echo $status_style_pickup->color; ?>;" class="label label-large">
                                    <?php echo $status_style_pickup->mod_style; ?>
                                </span>
                            <?php } ?>
                            <?php if ($eff->in_consolidation) { ?>
                                <span style="background: <?php echo $status_style_consolidate->color; ?>;" class="label label-large">
                                    <?php echo $status_style_consolidate->mod_style; ?>
                                </span>
                            <?php } ?>
                            <br>
                            <?php if ($row->order_incomplete == 0 && $row->is_pickup == 0 && $userData->userlevel != 1) { ?>
                                <span style="background: #5BE472;" class="label label-large">
                                    <?php echo $lang['leftorder34']; ?>
                                </span>
                            <?php } ?>
                            <?php if ($row->order_incomplete == 0 && $row->is_pickup == 0 && $userData->userlevel != 9 && $userData->userlevel == 1) { ?>
                                <span style="background: #FC5239;" class="label label-large">
                                    <?php echo $lang['left1018']; ?>
                                </span>
                            <?php } ?>
                        </td>
                        <td>
                            <b><?php echo $core->currency; ?></b> <?php echo cdb_money_format($row->total_order); ?>
                        </td>
                        <td>
                            <?php if ($row->status_invoice == 2 && $userData->userlevel == 1) { ?>
                                <a style="background: #34e89e;" class="label" href="add_payment_gateways_courier.php?id_order=<?php echo $row->order_id; ?>">
                                    <i style="color:#343a40" class="fas fa-dollar-sign"></i>&nbsp;<?php echo $lang['leftorder35']; ?>
                                </a>
                            <?php } ?>
                        </td>
                        <td>
                            <span class="label label-large <?php echo $label_class; ?>">
                                <?php echo $text_status; ?>
                            </span>
                        </td>
                        <td align='center'>
                            <div class="btn-group">
                                <button class="btn btn-block btn-outline-dark btn-sm dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu" style="overflow-y: auto; max-height: 200px;">
                                    <?php echo cdp_pickupCodeMenuItem('air', $row->order_id, 'locker_search', $row->status_courier ?? 0); ?>
                                    <a class="dropdown-item" href="courier_view.php?id=<?php echo $row->order_id; ?>&tracking_number=<?php echo $row->tracking_number; ?>&eta=<?php echo $row->estimated_eta; ?>" title="<?php echo $lang['tooledit']; ?>">
                                        <i style="color:#343a40" class="fa fa-search"></i>&nbsp;<?php echo $lang['leftorder266']; ?>
                                    </a>
                                    <?php if ($row->status_invoice == 2 && $userData->userlevel == 1) { ?>
                                        <a class="dropdown-item" href="add_payment_gateways_courier.php?id_order=<?php echo $row->order_id; ?>">
                                            <i style="color:#343a40" class="fas fa-dollar-sign"></i>&nbsp;<?php echo $lang['leftorder32']; ?>
                                        </a>
                                    <?php } ?>
                                    <?php if ($row->status_invoice == 3 && $userData->userlevel != 1) { ?>
                                        <a class="dropdown-item" data-toggle="modal" data-target="#detail_payment_packages" data-id="<?php echo $row->order_id; ?>" data-customer="<?php echo $row->sender_id; ?>">
                                            <i style="color:#343a40" class="fas fa-dollar-sign"></i>&nbsp;<?php echo $lang['leftorder33']; ?>
                                        </a>
                                    <?php } ?>
                                    <?php if ($row->order_incomplete == 0 && $row->is_pickup == 0 && $userData->userlevel != 1) { ?>
                                        <a class="dropdown-item" href="courier_accept.php?id=<?php echo $row->order_id; ?>" title="<?php echo $lang['tooledit']; ?>">
                                            <i style="color:#343a40" class="ti-pencil"></i>&nbsp;<?php echo $lang['leftorder34']; ?>
                                        </a>
                                        <a class="dropdown-item" href="print_label_ship.php?id=<?php echo $row->order_id; ?>" target="_blank">
                                            <i style="color:#343a40" class="ti-printer"></i>&nbsp;<?php echo $lang['toollabel']; ?>
                                        </a>
                                    <?php } ?>
                                    <?php if ($row->order_incomplete == 1 && $userData->userlevel != 1 && $row->is_consolidate == 0) { ?>
                                        <?php if (($userData->userlevel == 9 || $userData->userlevel == 2) && $row->status_courier != 8) { ?>
                                            <a class="dropdown-item" href="courier_edit.php?id=<?php echo $row->order_id; ?>&tracking_number=<?php echo $row->tracking_number; ?>&eta=<?php echo $row->estimated_eta; ?>" title="<?php echo $lang['tooledit']; ?>">
                                                <i style="color:#343a40" class="ti-pencil"></i>&nbsp;<?php echo $lang['tooledit']; ?>
                                            </a>
                                        <?php } ?>
                                        <?php if ($userData->userlevel == 9 || $userData->userlevel == 2) { ?>
                                            <?php if ($row->status_courier != 21 || $row->status_courier != 12) { ?>
                                                <?php if ($row->status_invoice != 1 && $row->status_invoice != 3) { ?>
                                                    <a class="dropdown-item" data-toggle="modal" data-target="#charges_list" data-id="<?php echo $row->order_id; ?>">
                                                        <i style="color:#343a40" class="fas fa-dollar-sign"></i>&nbsp;<?php echo $lang['leftorder35']; ?>
                                                    </a>
                                                <?php } ?>
                                            <?php } ?>
                                        <?php } ?>
                                        <?php if ($userData->userlevel == 9 || $userData->userlevel == 2) { ?>
                                            <?php if ($row->status_courier != 8 && $row->status_courier != 21 && $row->status_courier != 12) { ?>
                                                <a class="dropdown-item" data-toggle="modal" data-target="#modalDriver" data-id_shipment="<?php echo $row->order_id; ?>">
                                                    <i style="color:#ff0000" class="fas fa-car"></i>&nbsp;<?php echo $lang['left208']; ?>
                                                </a>
                                            <?php } ?>
                                        <?php } ?>
                                        <?php if ($row->status_courier != 21 && $row->status_courier != 12) { ?>
                                            <a class="dropdown-item" target="_blank" href="print_inv_ship.php?id=<?php echo $row->order_id; ?>&tracking_number=<?php echo $row->tracking_number; ?>&eta=<?php echo $row->estimated_eta; ?>">
                                                <i style="color:#343a40" class="ti-printer"></i>&nbsp;<?php echo $lang['toolprint']; ?>
                                            </a>
                                            <a class="dropdown-item" href="print_label_ship.php?id=<?php echo $row->order_id; ?>" target="_blank">
                                                <i style="color:#343a40" class="ti-printer"></i>&nbsp;<?php echo $lang['toollabel']; ?>
                                            </a>
                                            <?php if ($userData->userlevel == 9 || $userData->userlevel == 3 || $userData->userlevel == 2) { ?>
                                                <?php if ($row->is_consolidate == 0 && $row->status_courier != 8 && $row->status_courier != 21 && $row->status_courier != 12) { ?>
                                                    <a class="dropdown-item" href="courier_shipment_tracking.php?id=<?php echo $row->order_id; ?>" title="<?php echo $lang['toolupdate']; ?>">
                                                        <i style="color:#20c997" class="ti-reload"></i>&nbsp;<?php echo $lang['toolupdate']; ?>
                                                    </a>
                                                    <a class="dropdown-item" href="courier_deliver_shipment.php?id=<?php echo $row->order_id; ?>" title="<?php echo $lang['tooldeliver']; ?>">
                                                        <i style="color:#2962FF" class="ti-package"></i>&nbsp;<?php echo $lang['tooldeliver']; ?>
                                                    </a>
                                                <?php } ?>
                                            <?php } ?>
                                            <?php if ($userData->userlevel == 9 || $userData->userlevel == 2) { ?>
                                                <?php if ($row->is_consolidate == 0 && $row->status_courier != 8 && $row->status_courier != 21 && $row->status_courier != 12) { ?>
                                                    <a class="dropdown-item" data-id="<?php echo $row->order_id; ?>" href="#" data-toggle="modal" data-target="#myModalCancel">
                                                        <i style="color:#f62d51" class="fas fa-times-circle"></i>&nbsp;<?php echo $lang['leftorder34444']; ?>
                                                    </a>
                                                    <a class="dropdown-item" data-id="<?php echo $row->order_id; ?>" href="#" data-toggle="modal" data-target="#myModalDeletes">
                                                        <i style="color:#f62d51" class="ti-trash"></i>&nbsp;<?php echo $lang['leftorder34445']; ?>
                                                    </a>
                                                <?php } ?>
                                            <?php } ?>
                                            <?php if ($userData->userlevel == 9 || $userData->userlevel == 2 || $userData->userlevel == 3) { ?>
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-id="<?php echo $row->order_id; ?>" data-email="<?php echo $sender_data->email; ?>" data-order="<?php echo $row->order_prefix . $row->order_no; ?>" data-target="#myModal">
                                                    <i class="fas fa-envelope"></i>&nbsp;<?php echo $lang['leftorder36']; ?>
                                                </a>
                                            <?php } ?>
                                        <?php } ?>
                                    <?php } ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php $count++; } ?>
            <?php } ?>
        </tbody>
    </table>
    <script src="<?= cdp_asset('dataJs/locker_ajax.js') ?>"></script>
</div>
<?php } ?>

<?php if ($packages) {
    // Consolidation membership for customer packages (they live in their own table).
    cdp_prefetchConsolidations(array_map(function ($r) { return $r->order_no; }, $packages), true);
?>
<h5 class="mt-4">Packages (<?php echo count($packages) >= $limit ? 'first ' . $limit : count($packages); ?>)</h5>
<div class="table-responsive">
    <table class="table table-condensed table-hover table-striped">
        <thead>
            <tr>
                <th><b><?php echo $lang['ltracking']; ?></b></th>
                <th><b><?php echo $lang['ddate']; ?></b></th>
                <th><b><?php echo $lang['ncustomer']; ?></b></th>
                <th><b>Locker</b></th>
                <th><b>Description</b></th>
                <th><b>Weight</b></th>
                <th><b><?php echo $lang['lstatusshipment']; ?></b></th>
                <th><b><?php echo $lang['ship-all5']; ?></b></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($packages as $pk) {
                $db->cdp_query("SELECT fname, lname, locker FROM cdb_users WHERE id = :id");
                $db->bind(':id', (int) $pk->sender_id);
                $owner = $db->cdp_registro();
                $peff = cdp_getEffectiveStatus($pk->order_no, $pk->status_courier, $pk->is_consolidate, true);
            ?>
                <tr>
                    <td><b><a href="customer_packages_view.php?id=<?php echo (int) $pk->order_id; ?>"><?php echo htmlspecialchars($pk->order_prefix . $pk->order_no); ?></a></b></td>
                    <td><?php echo htmlspecialchars((string) $pk->order_date); ?></td>
                    <td><?php echo $owner ? htmlspecialchars($owner->fname . ' ' . $owner->lname) : ''; ?></td>
                    <td><?php echo $owner ? htmlspecialchars((string) $owner->locker) : ''; ?></td>
                    <td><?php echo htmlspecialchars((string) $pk->descriptions); ?></td>
                    <td><?php echo htmlspecialchars((string) $pk->total_weight); ?></td>
                    <td><span style="background: <?php echo $peff->color; ?>;" class="label label-large"><?php echo $peff->mod_style; ?></span></td>
                    <td><b><?php echo $core->currency; ?></b> <?php echo cdb_money_format($pk->total_order); ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
<?php } ?>