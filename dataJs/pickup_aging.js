"use strict";
/* ===========================================================================
   Pickup Aging — the list panel (views/reports/pickup_aging/pickup_aging_panel.php)
   shown on the Warehouse Panel, the Packages Control Panel and the Pickup Aging
   page.

   There is deliberately NO dashboard pop-up any more: a pop-up that offered to
   notify every customer let one stray click message everyone.

   Every notify goes through the same confirmation: the server says how many
   packages and customers it would touch, the staff member has to type that
   customer count back, and the server refuses the send if the typed number
   does not match what it would message at that moment.
   =========================================================================== */

var PA_AJAX = "./ajax/pickup_aging/pickup_aging_ajax.php";

/* Ask the server what a notify would touch, then ask the staff member to type
   the customer count back before anything is sent. */
function cdpPaConfirmAndNotify(payload, onDone) {
    $.ajax({
        url: PA_AJAX,
        method: "POST",
        data: $.extend({ action: "scope" }, payload),
        dataType: "json"
    }).done(function (s) {
        if (!s || !s.ok) { Swal.fire({ icon: "error", text: "Could not check the list." }); return; }
        if (!s.packages) {
            Swal.fire({ icon: "info", text: "None of these packages is due a reminder any more." });
            return;
        }
        var all = payload.scope === "all";
        var c = s.customers, p = s.packages;
        Swal.fire({
            icon: "warning",
            title: all ? "Message every customer on this list?" : "Message these customers?",
            html: "This sends a WhatsApp and e-mail reminder to <b>" + c + " customer" + (c === 1 ? "" : "s")
                + "</b> about <b>" + p + " package" + (p === 1 ? "" : "s") + "</b>, and sets those packages to "
                + "<b>Pending Collection</b>. It cannot be undone.<br><br>"
                + "Type <b>" + c + "</b> below to confirm.",
            input: "text",
            inputAttributes: { inputmode: "numeric", autocomplete: "off", "aria-label": "Number of customers to message" },
            inputPlaceholder: "Number of customers",
            showCancelButton: true,
            focusCancel: true,
            reverseButtons: true,
            confirmButtonText: "Send to " + c + " customer" + (c === 1 ? "" : "s"),
            confirmButtonColor: "#d33",
            cancelButtonText: "Cancel",
            preConfirm: function (v) {
                if (String(v || "").trim() !== String(c)) {
                    Swal.showValidationMessage("Type " + c + " to confirm, or press Cancel.");
                    return false;
                }
                return String(c);
            }
        }).then(function (res) {
            if (!res.isConfirmed) return;
            cdpPaSend($.extend({ action: "notify", confirm_customers: res.value }, payload), onDone);
        });
    }).fail(function () { Swal.fire({ icon: "error", text: "Request failed." }); });
}

function cdpPaSend(data, onDone) {
    $.ajax({ url: PA_AJAX, method: "POST", data: data, dataType: "json" })
        .done(function (r) {
            if (r && r.ok) {
                Swal.fire({
                    icon: "success",
                    title: "Customers notified",
                    html: "<b>" + (r.customers || 0) + "</b> customer(s) messaged, "
                        + "<b>" + (r.updated || 0) + "</b> package(s) set to Pending Collection, "
                        + "<b>" + (r.sent || 0) + "</b> message(s) sent"
                        + (r.skipped ? ", " + r.skipped + " skipped" : "") + "."
                });
                if (typeof onDone === "function") onDone();
            } else {
                Swal.fire({ icon: "error", text: (r && r.error) || "Could not notify customers." });
            }
        })
        .fail(function (x) {
            var r = x && x.responseJSON;
            Swal.fire({ icon: "error", text: (r && r.error) || "Request failed. Nothing was sent." });
        });
}

function cdpPaReloadSoon() { setTimeout(function () { location.reload(); }, 1600); }

/* ---- Notify the customers of the ticked packages ---- */
function cdpPaNotifySelected(btn) {
    var box = btn ? $(btn).closest(".pa-panel") : $(document);
    var ids = [];
    box.find(".pa-check:checked").each(function () { ids.push(parseInt($(this).val(), 10)); });
    if (!ids.length) {
        Swal.fire({ icon: "warning", text: "Tick at least one package first." });
        return;
    }
    cdpPaConfirmAndNotify({ order_ids: JSON.stringify(ids) }, cdpPaReloadSoon);
}

/* ---- Notify every customer whose package is due the 14-day reminder ---- */
function cdpPaNotifyAll() {
    cdpPaConfirmAndNotify({ scope: "all" }, cdpPaReloadSoon);
}

/* ---- Panel niceties: select-all box and the ticked count on the button ---- */
$(function () {
    $(document).on("change", ".pa-check-all", function () {
        $(this).closest(".pa-panel").find(".pa-check").prop("checked", this.checked).trigger("pa:changed");
    });
    $(document).on("change pa:changed", ".pa-check", function () {
        var panel = $(this).closest(".pa-panel");
        var n = panel.find(".pa-check:checked").length;
        panel.find(".pa-selected-count").text(n);
        panel.find(".pa-notify-selected").prop("disabled", n === 0);
    });
});
