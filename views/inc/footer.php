<?php require_once __DIR__ . '/../../helpers/asset.php'; ?>
<!----Footer--->
<footer class="footer text-center py-3">
    &copy <?php echo date('Y') . ' ' . $core->site_name; ?> - <?php echo $lang['foot'] ?>
</footer>
<!----Footer End--->

<script src="<?= cdp_asset('assets/template/assets/libs/jquery/dist/jquery.min.js') ?>"></script>
<!-- Design-system runtime adapter: turns inline-coloured status labels into tinted pills and
     dresses list-page search inputs — re-runs on every AJAX re-render (MutationObserver). -->
<script src="<?= cdp_asset('dataJs/swiftlane_ui.js') ?>"></script>
<!-- Persistent multi-select for AJAX list tables (checked rows survive filter/search/pagination). -->
<script src="<?= cdp_asset('dataJs/persist_selection.js') ?>"></script>
<!-- Sender auto-fill: selecting a sender fills the first address/recipient/recipient-address on every form. -->
<script src="<?= cdp_asset('dataJs/sender_autofill.js') ?>"></script>
<!-- Barcode-scanner Enter guard: a scan's trailing Enter must not submit the shipment forms
     (it was firing the "Enter package description" error on every scan). #invoice_form only. -->
<script src="<?= cdp_asset('dataJs/scanner_guard.js') ?>"></script>
<?php /* Staff presence beacon — staff roles only, switched on/off from the Staff
         Productivity settings. Records "this minute had input", nothing else.
         See helpers/staff_activity.php::cdp_spBeaconWanted(). */
require_once __DIR__ . '/../../helpers/staff_activity.php';
if (cdp_spBeaconWanted()) : ?>
<script>window.CDP_PRESENCE = { url: 'ajax/reports/staff_presence_ping_ajax.php', every: <?php echo (int) cdp_spSetting('ping_seconds'); ?> };</script>
<script src="<?= cdp_asset('dataJs/presence_beacon.js') ?>"></script>
<?php endif; ?>
<script>
    (function ($) {
        if (!$ || !$.ajaxSetup) return;

        function csrfToken() {
            return $('meta[name="csrf-token"]').attr('content') || '';
        }

        function csrfParam() {
            return $('meta[name="csrf-param"]').attr('content') || '_csrf_token';
        }

        // ajaxPrefilter (not ajaxSetup.beforeSend): a per-request beforeSend in
        // $.ajax options REPLACES the global one, so ~150 dataJs files with their
        // own spinner beforeSend were posting WITHOUT the token (= 419).
        // Prefilters always run and cannot be overridden per-request.
        $.ajaxPrefilter(function (options, originalOptions, jqXHR) {
            var method = ((options || {}).type || 'GET').toUpperCase();
            if (['POST', 'PUT', 'PATCH', 'DELETE'].indexOf(method) === -1) return;

            var token = csrfToken();
            if (!token) return;

            jqXHR.setRequestHeader('X-CSRF-Token', token);

            if (options && options.data && typeof FormData !== 'undefined' && options.data instanceof FormData) {
                options.data.append(csrfParam(), token);
            }
        });
    })(window.jQuery);
</script>

<!-- Global table-load indicator: a spinner shown while any paginated list table
     is being (re)populated via AJAX. Scoped to list-load requests (those that
     send a `page` param) so it doesn't fire for select2/OTP/other AJAX. -->
<div id="cdp-table-loader" role="status" aria-live="polite" aria-busy="false"
     style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;z-index:90000;pointer-events:none;">
    <div style="position:absolute;top:42%;left:50%;transform:translate(-50%,-50%);background:rgba(255,255,255,.94);border:1px solid #e6e6e6;border-radius:10px;padding:13px 20px;box-shadow:0 6px 22px rgba(0,0,0,.14);font-family:Roboto,Arial,Helvetica,sans-serif;">
        <span aria-hidden="true" style="display:inline-block;width:16px;height:16px;border:2px solid #cfd8ec;border-top-color:#336aea;border-radius:50%;animation:cdpTableSpin .7s linear infinite;vertical-align:middle;"></span>
        <span style="margin-left:9px;font-size:13px;color:#333;vertical-align:middle;">Loading…</span>
    </div>
</div>
<style>@keyframes cdpTableSpin { to { transform: rotate(360deg); } }</style>
<script>
    (function ($) {
        if (!$ || !$.ajaxPrefilter) return;
        function listTargets() {
            return $('.outer_div, [data-cdp-list]');
        }
        // GET data is moved into the URL before ajaxSend runs. Remember the
        // request's purpose while its original parameters are still available.
        // Some CRUD scripts also call cdp_load on their form pages. A page
        // parameter alone does not establish that this page displays a list.
        $.ajaxPrefilter(function (options, originalOptions) {
            var data = originalOptions && originalOptions.data;
            var hasPage = !!(data && typeof data === 'object' && 'page' in data) ||
                (typeof data === 'string' && /(?:^|&)page=/.test(data)) ||
                /[?&]page=/.test(options.url || '');
            options.cdpListLoad = hasPage && listTargets().length > 0;
        });
        var active = 0, latest = 0;
        function feedback() {
            var $message = $('#cdp-list-feedback');
            if ($message.length) return $message;
            var $anchor = listTargets().first();
            if (!$anchor.length) return $();
            $message = $('<div>', {
                id: 'cdp-list-feedback',
                'class': 'alert alert-danger cdp-list-feedback',
                role: 'alert',
                'aria-atomic': 'true'
            }).hide();
            $message.insertBefore($anchor);
            return $message;
        }
        $(document).ajaxSend(function (e, xhr, settings) {
            if (!settings.cdpListLoad) return;
            xhr.cdpListSequence = ++latest;
            active++;
            feedback().hide().empty();
            listTargets().attr('aria-busy', 'true');
            $('#cdp-table-loader').attr('aria-busy', 'true').stop(true, true).fadeIn(120);
        }).ajaxSuccess(function (e, xhr, settings) {
            if (settings.cdpListLoad && xhr.cdpListSequence === latest) feedback().hide().empty();
        }).ajaxError(function (e, xhr, settings, error) {
            if (!settings.cdpListLoad || xhr.cdpListSequence !== latest || error === 'abort' || xhr.statusText === 'abort') return;
            var $message = feedback().empty();
            if (xhr.status === 401 || xhr.status === 419) {
                $message.text('Your session expired. ')
                    .append($('<a>', { href: 'login.php', text: 'Sign in again' }))
                    .append(document.createTextNode(' to load these records.'));
            } else if (xhr.status === 403) {
                $message.text('You do not have permission to load these records. Contact your administrator if you need access.');
            } else {
                $message.text('We could not refresh these records. The list may be out of date. Try your search or filters again.');
            }
            $message.show();
        }).ajaxComplete(function (e, xhr, settings) {
            if (!settings.cdpListLoad) return;
            active = Math.max(0, active - 1);
            if (active === 0) {
                listTargets().attr('aria-busy', 'false');
                $('#cdp-table-loader').attr('aria-busy', 'false').stop(true, true).fadeOut(120);
            }
        });
    })(window.jQuery);
</script>

<script>
    // Rows-per-page: append the chosen #per_page value to every list-load AJAX
    // request (those whose object `data` contains a `page` key). jQuery serializes
    // object data to a query string BEFORE prefilters run, so appending to
    // options.data here is safe. No-ops when the page has no #per_page dropdown
    // or when per_page was already sent explicitly (e.g. courier/warehouse).
    (function ($) {
        if (!$ || !$.ajaxPrefilter) return;
        $.ajaxPrefilter(function (options, originalOptions) {
            var od = originalOptions && originalOptions.data;
            if (!od || typeof od !== 'object' || !('page' in od)) return;
            if (/(?:^|&)per_page=/.test(options.data || '')) return;
            var $pp = $('#per_page');
            if (!$pp.length) return;
            options.data = (options.data ? options.data + '&' : '') +
                'per_page=' + encodeURIComponent($pp.val() || 25);
        });
    })(window.jQuery);
</script>

<script>
    // Rows-per-page: inject a 25/50/100/All dropdown into the filter row of every
    // paginated list page that doesn't already ship a static one (warehouse,
    // courier_list and dashboard shipments do). Anchored next to #search so it
    // lands on the same row as the other filters.
    (function ($) {
        if (!$) return;
        $(function () {
            if (typeof cdp_load !== 'function') return;   // paginated list pages only
            if ($('#per_page').length) return;            // already has a dropdown
            var $search = $('#search');
            if (!$search.length) return;                  // no filter row to attach to
            var $anchor = $search.closest('[class*="col-"]');
            if (!$anchor.length) $anchor = $search.closest('.input-group');
            if (!$anchor.length) return;
            $anchor.after(
                '<div class="col-sm-12 col-md-2 mb-2"><div class="input-group">' +
                '<select onchange="cdp_load(1);" class="form-control custom-select" id="per_page" name="per_page" aria-label="Rows per page">' +
                '<option value="25">25 rows</option>' +
                '<option value="50">50 rows</option>' +
                '<option value="100">100 rows</option>' +
                '<option value="all">All</option>' +
                '</select></div></div>'
            );
        });
    })(window.jQuery);
</script>

<!-- Bootstrap tether Core JavaScript -->
<script src="<?= cdp_asset('assets/template/assets/libs/popper.js/dist/umd/popper.min.js') ?>"></script>
<script src="<?= cdp_asset('assets/template/assets/libs/bootstrap/dist/js/bootstrap.min.js') ?>"></script>
<!-- apps -->
<script src="<?= cdp_asset('assets/template/dist/js/app.min.js') ?>"></script>
<script src="<?= cdp_asset('assets/template/dist/js/app.init.js') ?>"></script>
<script src="<?= cdp_asset('assets/template/dist/js/app-style-switcher.js') ?>"></script>
<!-- slimscrollbar scrollbar JavaScript -->
<script src="<?= cdp_asset('assets/template/assets/libs/perfect-scrollbar/dist/perfect-scrollbar.jquery.min.js') ?>"></script>
<?php /* custom.min.js calls $('.lastmonth').sparkline() on every page although no view
         has such an element; a no-op stub stands in for the 43KB plugin. */ ?>
<script>if (window.jQuery && !jQuery.fn.sparkline) { jQuery.fn.sparkline = function () { return this; }; }</script>
<!--Wave Effects -->
<script src="<?= cdp_asset('assets/template/dist/js/waves.js') ?>"></script>
<!--Menu sidebar -->
<script src="<?= cdp_asset('assets/template/dist/js/sidebarmenu.js') ?>"></script>
<!--Custom JavaScript -->
<script src="<?= cdp_asset('assets/template/dist/js/feather.min.js') ?>"></script>
<script src="<?= cdp_asset('assets/template/dist/js/custom.min.js') ?>"></script>

<?php /* Chart.js 2.8 (157KB) was loaded on every page; the dashboards draw with
         ApexCharts and nothing calls new Chart(). */ ?>
<script src="<?= cdp_asset('dataJs/load_notifications_all.js') ?>"> </script>
<script src="<?= cdp_asset('assets/template/dist/js/global.js') ?>"></script>

<!-- start - This is for export functionality only -->
<!-- solar icons -->
<?php /* iconify-icon is already loaded (deferred) by head_scripts.php. The un-minified
         DataTables build from cdn.datatables.net was fetched on every page and no
         view or script ever calls .DataTable(). */ ?>
<?php /* SweetAlert2 v11 (assets/vendor) replaces the template's v7 bundle: the
         codebase widely uses v10+ APIs (result.isConfirmed, didOpen,
         Swal.showValidationMessage) that silently no-op'd under v7. */ ?>
<script src="<?= cdp_asset('assets/vendor/libs/sweetalert2/sweetalert2.js') ?>"></script>
<script>
    /* Back-compat shim for legacy v7 call styles still present in older files:
       maps {type:...} to {icon:...} and restores the lowercase swal() alias. */
    (function () {
        if (!window.Swal || !window.Swal.fire) return;
        var orig = window.Swal.fire.bind(window.Swal);
        window.Swal.fire = function () {
            var a = arguments;
            if (a.length === 1 && a[0] && typeof a[0] === 'object' && a[0].type && !a[0].icon) {
                a[0].icon = a[0].type;
                delete a[0].type;
            }
            return orig.apply(null, a);
        };
        if (!window.swal) { window.swal = window.Swal.fire; }
    })();
</script>
<script src="<?= cdp_asset('assets/template/assets/libs/select2/dist/js/select2.full.min.js') ?>"></script>

<script src="<?= cdp_asset('assets/template/assets/libs/intlTelInput/intlTelInput.js') ?>"></script>

<?php include 'views/modals/modal_user_update_address.php'; ?>
<?php include 'views/modals/modal_user_update_phone.php'; ?>
<?php include 'views/modals/modal_phone_update_otp.php'; ?>
<?php include 'views/modals/modal_user_update_document.php'; ?>

<?php /* Forced account setup (WhatsApp number verification etc.) — customers only */ ?>
<?php if (isset($_SESSION['userlevel']) && (int)$_SESSION['userlevel'] === 1): ?>
<script src="<?= cdp_asset('dataJs/check_user_update.js') ?>"></script>
<?php endif; ?>

<?php /* Real-time "cleared, awaiting delivery" badge on the Warehouse nav item */ ?>
<script src="<?= cdp_asset('dataJs/warehouse_nav_badge.js') ?>"></script>

<?php /* Pickup codes: the code modal, the "Pickup Codes" list and the handover gate on deliver forms — staff only */ ?>
<?php if (isset($_SESSION['userlevel']) && (int)$_SESSION['userlevel'] !== 1): ?>
<script src="<?= cdp_asset('dataJs/pickup_code.js') ?>"></script>
<?php endif; ?>

<?php /* Customer Service: the chat button for customers (it decides for itself whether to show) */ ?>
<?php include __DIR__ . '/../support/widget.php'; ?>

<?php /* The design system is linked again here, after every page-level stylesheet and inline
         <style> block (about 80 views add their own after head_scripts.php), so its rules
         are last in the cascade on every page. The browser does not fetch the file twice. */ ?>
<link href="<?= cdp_asset('assets/css_main_swiftlane/css/swiftlane-tokens.css') ?>" rel="stylesheet" type="text/css" />
<link href="<?= cdp_asset('assets/css_main_swiftlane/css/swiftlane-ds.css') ?>" rel="stylesheet" type="text/css" />
<style>
    .swal2-container {
    z-index: 99999 !important;
}

.swal2-backdrop-show {
    background: rgba(0, 0, 0, 0.6) !important;
}
</style>
