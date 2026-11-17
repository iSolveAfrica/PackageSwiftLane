<?php
// *************************************************************************
// * Pickup Aging — dedicated review page                                 *
// * Full list; the same panel sits on the Warehouse and Packages panels. *
// *************************************************************************

// left_sidebar.php gates its nav blocks on $userData->userlevel — without this
// the whole sidebar renders empty on this page.
$userData = $user->cdp_getUserData();

?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title>Pickup Aging | <?php echo $core->site_name ?></title>
    <?php include 'views/inc/head_scripts.php'; ?>
</head>

<body>
    <?php include 'views/inc/preloader.php'; ?>
    <div id="main-wrapper">
        <?php include 'views/inc/topbar.php'; ?>
        <?php include 'views/inc/left_sidebar.php'; ?>

        <div class="page-wrapper">
            <?php cdp_pageHeader('Pickup Aging'); ?>
            <div class="container-fluid">
                <div class="row">
                    <?php $paLimit = 0; $paTitle = 'Pickup Aging'; include __DIR__ . '/pickup_aging_panel.php'; ?>
                </div>
            </div>
            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <?php include('helpers/languages/translate_to_js.php'); ?>
    <script src="<?= cdp_asset('dataJs/pickup_aging.js') ?>"></script>
</body>

</html>
