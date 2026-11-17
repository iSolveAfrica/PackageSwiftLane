<?php

$userData = $user->cdp_getUserData();
$statusrow = $core->cdp_getStatus();


?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <!-- Tell the browser to be responsive to screen width -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">
    <!-- Favicon icon -->
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title><?php echo $lang['lockersearch'] ?> | <?php echo $core->site_name ?></title>
    <!-- This Page CSS -->
    <!-- Custom CSS -->
    <?php include 'views/inc/head_scripts.php'; ?>
</head>

<body>
    <!-- ============================================================== -->
    <!-- Preloader - style you can find in spinners.css -->
    <!-- ============================================================== -->


    <?php include 'views/inc/preloader.php'; ?>
    <!-- ============================================================== -->
    <!-- Main wrapper - style you can find in pages.scss -->
    <!-- ============================================================== -->
    <div id="main-wrapper">
        <!-- ============================================================== -->
        <!-- Topbar header - style you can find in pages.scss -->
        <!-- ============================================================== -->

        <!-- ============================================================== -->
        <!-- Preloader - style you can find in spinners.css -->
        <!-- ============================================================== -->

        <?php include 'views/inc/topbar.php'; ?>

        <!-- End Topbar header -->


        <!-- Left Sidebar - style you can find in sidebar.scss  -->

        <?php include 'views/inc/left_sidebar.php'; ?>


        <!-- End Left Sidebar - style you can find in sidebar.scss  -->

        <!-- Page wrapper  -->
        <!-- ============================================================== -->
        <div class="page-wrapper">

            <div class="page-breadcrumb">
                <div class="row">
                    <div class="col-md-5 align-self-center">
                        <h4 class="page-title"> <?php echo $lang['lockersearch']; ?></h4>

                    </div>
                </div>
            </div>

            <div class="container-fluid">
                <div class="row">
                    <!-- Column -->

                    <div class="col-lg-12 col-xl-12 col-md-12">

                        <div class="card">
                            <div class="card-body">
                                <div id="resultados_ajax"></div>

                            <div class="row mb-3">

                                <?php
                                    // Define la URL del enlace basándote en el nivel de usuario
                                    $add_courier_url = ($userData->userlevel == 9) ? "courier_add.php" : "courier_add_client.php";
                                ?>

                                <div class=" col-sm-12 col-md-6 mb-2">
                                    <div class="input-group">
                                        <input type="search" name="search" id="search" class="form-control input-sm float-right" placeholder="<?php echo $lang['left21552'] ?>" autocomplete="off" autofocus>
                                        <div class="input-group-append input-sm">
                                            <button type="button" id="search-btn" class="btn btn-outline-dark" aria-label="Search"><i class="fa fa-search"></i></button>
                                        </div>

                                    </div>
                                    <small id="search-status" class="text-muted d-none" aria-live="polite"></small>
                                    <input type="hidden" id="per_page" value="100"><?php /* stops the footer adding a rows-per-page dropdown: search shows up to 100 per section */ ?>
                                </div><!-- /.col -->

<?php
                                    // Status list for the filter (shipment statuses).
                                    $srch_status = $core->cdp_getStatusByType(1) ?: array();
                                    $srch_today  = date('Y-m-d');
                                ?>
                                <div class="col-sm-12 col-md-6 mb-2">
                                    <div class="row">
                                        <div class="col-6 col-lg-3 pr-1">
                                            <label for="date_from" class="small mb-0">From date</label>
                                            <input type="date" id="date_from" class="form-control input-sm srch-filter" max="<?php echo $srch_today; ?>">
                                        </div>
                                        <div class="col-6 col-lg-3 pl-1 pr-lg-1">
                                            <label for="time_from" class="small mb-0">From time</label>
                                            <input type="time" id="time_from" class="form-control input-sm srch-filter">
                                        </div>
                                        <div class="col-6 col-lg-3 pr-1 pl-lg-1">
                                            <label for="date_to" class="small mb-0">To date</label>
                                            <input type="date" id="date_to" class="form-control input-sm srch-filter" max="<?php echo $srch_today; ?>">
                                        </div>
                                        <div class="col-6 col-lg-3 pl-1">
                                            <label for="time_to" class="small mb-0">To time</label>
                                            <input type="time" id="time_to" class="form-control input-sm srch-filter">
                                        </div>
                                    </div>
                                    <div class="mt-1 small">
                                        <a href="#" class="date-preset mr-2 text-nowrap" data-hours="1">Last hour</a>
                                        <a href="#" class="date-preset mr-2 text-nowrap" data-days="0">Today</a>
                                        <a href="#" class="date-preset mr-2 text-nowrap" data-yesterday="1">Yesterday</a>
                                        <a href="#" class="date-preset mr-2 text-nowrap" data-days="7">Last 7 days</a>
                                        <a href="#" class="date-preset mr-2 text-nowrap" data-days="30">Last 30 days</a>
                                        <a href="#" class="date-preset mr-2 text-nowrap" data-month="1">This month</a>
                                        <a href="#" id="date-clear" class="text-danger text-nowrap"><i class="fa fa-times"></i> Clear date &amp; time</a>
                                    </div>
                                </div><!-- /.col -->

                                <div class="col-6 col-md-3 mb-2">
                                    <label for="f_type" class="small mb-0">Type</label>
                                    <select id="f_type" class="form-control custom-select srch-filter">
                                        <option value="">All</option>
                                        <option value="ship">Shipments</option>
                                        <option value="pickup">Pickups</option>
                                        <option value="pkg">Customer packages</option>
                                    </select>
                                </div>
                                <div class="col-6 col-md-3 mb-2">
                                    <label for="f_status" class="small mb-0">Status</label>
                                    <select id="f_status" class="form-control custom-select srch-filter">
                                        <option value="0">All statuses</option>
                                        <?php foreach ($srch_status as $st) { ?>
                                            <option value="<?php echo (int) $st->id; ?>"><?php echo htmlspecialchars(str_replace('_', ' ', $st->mod_style), ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="col-6 col-md-3 mb-2">
                                    <label for="f_invoice" class="small mb-0">Invoice</label>
                                    <select id="f_invoice" class="form-control custom-select srch-filter">
                                        <option value="0">All invoices</option>
                                        <option value="1"><?php echo $lang['invoice_paid'] ?? 'Paid'; ?></option>
                                        <option value="2"><?php echo $lang['invoice_pending'] ?? 'Pending'; ?></option>
                                        <option value="3"><?php echo $lang['verify_payment'] ?? 'Verify payment'; ?></option>
                                    </select>
                                </div>
                                <div class="col-6 col-md-3 mb-2 d-flex align-items-end">
                                    <button type="button" id="filters-reset" class="btn btn-outline-secondary btn-block"><i class="fa fa-undo"></i> Reset all</button>
                                </div>

                                
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="col-md-12 hide" id="div-actions-checked">
                                        <!-- <div class="form-group"> -->
                                        <div class="btn-group mt-2">
                                            <span class="mt-2 mr-4"><strong> <?php echo $lang['global-2'] ?></strong> <strong id="countChecked"> 0</strong></span>&nbsp;&nbsp;
                                            <button class="btn btn-info dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                <?php echo $lang['global-1'] ?>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#modalCheckboxStatus"><i class="ti-reload"></i>&nbsp;<?php echo $lang['left21550'] ?></a>
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#modalDriverCheckbox"><i class="fas fa-car"></i>&nbsp;<?php echo $lang['left208'] ?></a>
                                                <a class="dropdown-item" onclick="cdp_printMultipleLabel();" target="_blank"> <i class="ti-printer"></i>&nbsp;<?php echo $lang['toollabel'] ?> </a>
                                            </div>
                                        </div>
                                        <!-- </div> -->
                                    </div>
                                </div>

                            </div>


                            <div class="outer_divx"></div>


                        </div>
                    </div>
                </div>
                <!-- Column -->
            </div>
        </div>

        <?php
        include('views/modals/modal_update_status_checked.php');
        ?>
        <?php include('views/modals/modal_send_email.php'); ?>

        <?php include('views/modals/modal_update_driver.php'); ?>
        <?php include('views/modals/modal_update_driver_checked.php'); ?>
        <?php include('views/modals/modal_verify_payment_packages.php'); ?>

        <?php include('views/modals/modal_cancel_pickup.php'); ?>

        <?php include('views/modals/modal_delete_pickup.php'); ?>


        <?php include('views/modals/modal_charges_list.php'); ?>
        <?php include('views/modals/modal_charges_add.php'); ?>
        <?php include('views/modals/modal_charges_edit.php'); ?>
        <?php include 'views/inc/footer.php'; ?>

    </div>
    <!-- ============================================================== -->
    <!-- End Page wrapper  -->
    <!-- ============================================================== -->
    </div>
    <!-- ============================================================== -->
    <!-- End Wrapper -->
    <!-- ============================================================== -->
    <?php include('helpers/languages/translate_to_js.php'); ?>

    <script src="<?= cdp_asset('dataJs/locker.js') ?>"></script>
    <script src="<?= cdp_asset('dataJs/locker_ajax.js') ?>"></script>

</body>

</html>