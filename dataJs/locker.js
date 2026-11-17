"use strict";


$(function () {
  cdp_load(1);

  // Live search: results update while typing (also on paste, autofill and
  // mobile keyboards, which do not always send key events). Enter and the
  // search button run it immediately.
  $("#search")
    .on("input", cdp_searchDebounced)
    .on("keydown", function (e) {
      if (e.key === "Enter") { e.preventDefault(); clearTimeout(cdp_searchTimer); cdp_load(1); }
    });
  $("#search-btn").on("click", function (e) {
    e.preventDefault(); clearTimeout(cdp_searchTimer); cdp_load(1);
  });
});



// Search runs across many fields; wait for a short pause in typing before querying.
var cdp_searchTimer = null;
function cdp_searchDebounced() {
  clearTimeout(cdp_searchTimer);
  cdp_searchTimer = setTimeout(function () { cdp_load(1); }, 300);
}

var cdp_searchXhr = null;
var cdp_lastSearch = null;

//Cargar datos AJAX
function cdp_load(page) {
  try { localStorage.setItem('currentTablePage-Locker', page); } catch (e) {}
  var search = $.trim($("#search").val() || "");
  if (search === cdp_lastSearch && cdp_searchXhr) { return; } // same term already loading
  cdp_lastSearch = search;

  // Only the newest search may paint the results.
  if (cdp_searchXhr) { cdp_searchXhr.abort(); }

  if (search.length >= 2) {
    $("#search-status").html('<i class="fa fa-spinner fa-spin"></i> Searching&hellip;').removeClass('d-none');
  }

  cdp_searchXhr = $.ajax({
    url: './ajax/locker/locker_search_ajax.php',
    data: { 'search': search },
    cache: false,
    success: function (data) {
      $(".outer_divx").html(data).show();
    },
    error: function (xhr, status) {
      if (status === 'abort') { return; }
      var msg = xhr.status === 401 ? 'Your session has expired. Please log in again.'
              : xhr.status === 403 ? 'You do not have permission to search.'
              : 'Search failed (' + (xhr.status || status) + '). Please try again.';
      $(".outer_divx").html('<div class="alert alert-danger">' + msg + '</div>').show();
    },
    complete: function (xhr, status) {
      if (status === 'abort') { return; }
      cdp_searchXhr = null;
      $("#search-status").addClass('d-none').empty();
    }
  });
}
 

//Grafico de ventas de envios MORRIS LINE CHART AJAX

$(document).ready(() => {
  const loadGraphicsmorris = async () => {
    try {
      const response = await $.ajax({
        url: './ajax/dashboard/shipments/load_graphics_shipments_ajax.php',
        type: 'POST',
        dataType: 'json'
      });

      // Convertimos los datos recibidos en el formato necesario para Morris.js
      const data = [];
      const months = [
            translate_graphic_0,
            translate_graphic_1,
            translate_graphic_2,
            translate_graphic_3,
            translate_graphic_4,
            translate_graphic_5,
            translate_graphic_6,
            translate_graphic_7,
            translate_graphic_8,
            translate_graphic_9,
            translate_graphic_10,
            translate_graphic_11
        ];


      for (let i = 0; i < response.length; i++) {
        data.push({
          month: months[i], // Usamos los nombres de los meses en lugar de "Month 1", "Month 2", etc.
          sales: parseFloat(response[i] || 0) // Convertimos el dato de ventas a número
        });
      }

      // Creamos el gráfico de líneas utilizando Morris.js
      const salesChart = new Morris.Line({
        element: 'morris-sales-chart',
        data: data,
        xkey: 'month',
        ykeys: ['sales'],
        labels: [message_error_form104],
        gridLineColor: '#eef0f2',
        lineColors: ['#2962FF'],
        lineWidth: 1,
        hideHover: 'auto',
        xLabelAngle: 60, // Rotamos los nombres de los meses para evitar superposiciones
        parseTime: false, // Desactivamos el análisis de tiempo para los nombres de los meses
        gridTextSize: 12 // Tamaño del texto en el eje Y (afecta la altura de las líneas horizontales)
      });
    } catch (error) {
      console.error('Error loading sales data:', error);
    }
  };

  loadGraphicsmorris();
  cdp_load(localStorage.getItem('currentTablePage-Locker'));
});



//cdp_eliminar
function cdp_eliminar(id) {

  var parent = $('#item_' + id).parent().parent();
  var name = $(this).attr('data-rel');
  new Messi('<p class="messi-warning"><i class="icon-warning-sign icon-3x pull-left"></i>' + message_delete_confirm + '<br /><strong>' + message_delete_confirm2 + '</strong></p>', {
    title: 'Delete courier',
    titleClass: '',
    modal: true,
    closeButton: true,
    buttons: [{
      id: 0,
      label: message_delete_confirm1,
      class: '',
      val: 'Y'
    }],
    callback: function (val) {
      if (val === 'Y') {
        $.ajax({
          type: 'post',
          url: './ajax/courier/courier_delete_ajax.php',
          data: {
            'id': id,
          },
          beforeSend: function () {
            parent.animate({
              'backgroundColor': '#FFBFBF'
            }, 400);
          },
          success: function (data) {

            $('html, body').animate({
              scrollTop: 0
            }, 600);
            $('#resultados_ajax').html(data);

            cdp_load(localStorage.getItem('currentTablePage-Locker'));
          }
        });
      }
    }

  });
}


$("#send_checkbox_status").on('submit', function (event) {

    $('#guardar_datos').attr("disabled", true);

    var parametros = $(this).serialize();
    var checked_data = (typeof cdpSelGet === 'function') ? cdpSelGet() : [];

    var status = $('#status_courier_modal').val();

    $.ajax({
        type: "GET",
        url: './ajax/courier/courier_update_multiple_ajax.php?status=' + status,

        data: { 'checked_data': JSON.stringify(checked_data) },
        beforeSend: function (objeto) {
        },
        success: function (datos) {
            $("#resultados_ajax").html(datos);
            $('#guardar_datos').attr("disabled", false);
            $('#modalCheckboxStatus').modal('hide');

 
            cdp_load(localStorage.getItem('currentTablePage-Locker'));

            if (typeof cdpSelClear === 'function') cdpSelClear();
            $('html, body').animate({
                scrollTop: 0
            }, 600);


        }
    });
    event.preventDefault();

})



$("#cancel_pickup_form").on('submit', function (event) {

  $('#guardar_datos').attr("disabled", true);

  var parametros = $(this).serialize();
  $.ajax({
    type: "POST",
    url: "ajax/pickup/pickup_cancel_ajax.php",
    data: parametros,
    beforeSend: function (objeto) {
      $("#resultados_ajax").html("Mensaje: load...");
    },
    success: function (datos) {
      $("#resultados_ajax").html(datos);
      $('#guardar_datos').attr("disabled", false);

      $('#myModalCancel').modal('hide');
      cdp_load(localStorage.getItem('currentTablePage-Locker'));

    }
  });
  event.preventDefault();

})


$('#myModalCancel').on('show.bs.modal', function (event) {
  var button = $(event.relatedTarget) // Button that triggered the modal

  var id = button.data('id') // Extract info from data-* attributes

  var modal = $(this)
  $('#id_cancel').val(id)
})





$("#delete_pickup_form").on('submit', function (event) {

  $('#guardar_datos').attr("disabled", true);

  var parametros = $(this).serialize();
  $.ajax({
    type: "POST",
    url: "ajax/pickup/pickup_delete_ajax.php",
    data: parametros,
    beforeSend: function (objeto) {
      $("#resultados_ajax").html("Mensaje: load...");
    },
    success: function (datos) {
      $("#resultados_ajax").html(datos);
      $('#guardar_datos').attr("disabled", false);

      $('#myModalDeletes').modal('hide');
      cdp_load(localStorage.getItem('currentTablePage-Locker'));

    }
  });
  event.preventDefault();

})


$('#myModalDeletes').on('show.bs.modal', function (event) {
  var button = $(event.relatedTarget) // Button that triggered the modal

  var id = button.data('id') // Extract info from data-* attributes

  var modal = $(this)
  $('#id_delete').val(id)
})


$("#driver_update").on('submit', function (event) {
  var parametros = $(this).serialize();

  $.ajax({
    type: "POST",
    url: "ajax/courier/courier_update_driver_ajax.php",
    data: parametros,
    beforeSend: function (objeto) {
      $("#resultados_ajax").html("Load...");
    },
    success: function (datos) {
      $("#resultados_ajax").html(datos);

      $('html, body').animate({
        scrollTop: 0
      }, 600);

      $('#modalDriver').modal('hide');

      cdp_load(localStorage.getItem('currentTablePage-Locker'));


    }
  });
  event.preventDefault();

})


$('#modalDriver').on('show.bs.modal', function (event) {
  var button = $(event.relatedTarget) // Button that triggered the modal
  var id_shipment = button.data('id_shipment') // Extract info from data-* attributes
  var id_sender = button.data('id_sender') // Extract info from data-* attributes
  var modal = $(this)
  $('#id_shipment').val(id_shipment)
  $('#id_senderclient_driver_update').val(id_sender)
})




$("#send_email").on('submit', function (event) {

    $('#guardar_datos').attr("disabled", true);

    var parametros = $(this).serialize();
    $.ajax({
        type: "GET",
        url: "send_email_pdf.php",
        data: parametros,
        beforeSend: function (objeto) {
            Swal.fire({
                title: 'Please wait...',
                text: 'Sending your email with the PDF attached.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading()
                }
            });
        },
        success: function (datos) {
            Swal.close(); // Close the SweetAlert loading message
            $(".resultados_ajax_mail").html(datos);
            $('#guardar_datos').attr("disabled", false);
        }
    });
    event.preventDefault();

});


$('#myModal').on('show.bs.modal', function (event) {
  var button = $(event.relatedTarget) // Button that triggered the modal
  var order = button.data('order') // Extract info from data-* attributes
  var id = button.data('id') // Extract info from data-* attributes
  var email = button.data('email') // Extract info from data-* attributes
  var modal = $(this)
  $('#subject').val("#" + order)
  $('#id').val(id)
  $('#sendto').val(email)
})



$('#charges_list').on('show.bs.modal', function (event) {
  var button = $(event.relatedTarget) // Button that triggered the modal
  var id = button.data('id') // Extract info from data-* attributes
  $('#order_id').val(id);

  $(".resultados_ajax_charges_add_results").html('');

  cdp_load_charges(order_id);//Cargas los pagos 

})

function cdp_load_charges() {

  var id = $('#order_id').val();
  var parametros = { "id": id };
  $.ajax({

    url: 'ajax/accounts_receivable/charges_list_ajax.php',
    data: parametros,
    success: function (data) {
      $(".resultados_ajax_charges_list").html(data).fadeIn('slow');
    }
  });
}
 

$('#charges_add').on('show.bs.modal', function (event) {

  var id = $('#order_id').val();
  var parametros = { "id": id };

  $.ajax({
    url: 'ajax/accounts_receivable/modal_add_charges.php',
    data: parametros,
    success: function (data) {
      $(".resultados_ajax_add_modal").html(data).fadeIn('slow');
    }
  });
})





$("#add_charges").on('submit', function (event) {
  var parametros = $(this).serialize();

  $.ajax({
    type: "POST",
    url: "ajax/accounts_receivable/add_charges_ajax.php",
    data: parametros,
    beforeSend: function (objeto) {
    },
    success: function (datos) {
      $(".resultados_ajax_charges_add_results").html(datos);

      $('#charges_add').modal('hide');
      cdp_load_charges();
      cdp_load(localStorage.getItem('currentTablePage-Locker'));


    }
  });
  event.preventDefault();

})



$('#charges_edit').on('show.bs.modal', function (event) {

  var id = $('#order_id').val();

  var button = $(event.relatedTarget) // Button that triggered the modal
  var id_charge = button.data('id_charge')

  var parametros = { "id": id, 'id_charge': id_charge };

  $.ajax({
    url: 'ajax/accounts_receivable/modal_edit_charges.php',
    data: parametros,
    success: function (data) {
      $(".resultados_ajax_add_modal_edit").html(data).fadeIn('slow');
    }
  });
})


$("#edit_charges").on('submit', function (event) {
  var parametros = $(this).serialize();

  $.ajax({
    type: "POST",
    url: "ajax/accounts_receivable/update_charges_ajax.php",
    data: parametros,
    beforeSend: function (objeto) {
    },
    success: function (datos) {
      $(".resultados_ajax_charges_add_results").html(datos);

      $('#charges_edit').modal('hide');
      cdp_load_charges();
      cdp_load(localStorage.getItem('currentTablePage-Locker'));


    }
  });
  event.preventDefault();

})



//cdp_eliminar
function cdp_delete_charge(id) {

  var parent = $('#item_' + id).parent().parent();
  var name = $(this).attr('data-rel');
  new Messi('<p class="messi-warning"><i class="icon-warning-sign icon-3x pull-left"></i>' + message_delete_confirm + '<br /><strong>' + message_delete_confirm2 + '</strong></p>', {
    title: message_delete_confirm1,
    titleClass: '',
    modal: true,
    closeButton: true,
    buttons: [{
      id: 0,
      label: message_delete_confirm1,
      class: '',
      val: 'Y'
    }],
    callback: function (val) {
      if (val === 'Y') {
        $.ajax({
          type: 'post',
          url: './ajax/accounts_receivable/charge_delete_ajax.php',
          data: {
            'id': id,
          },
          beforeSend: function () {
            parent.animate({
              'backgroundColor': '#FFBFBF'
            }, 400);
          },
          success: function (data) {

            $('html, body').animate({
              scrollTop: 0
            }, 600);
            $('.resultados_ajax_charges_add_results').html(data);
            cdp_load_charges();

            cdp_load(localStorage.getItem('currentTablePage-Locker'));
          }
        });
      }
    }

  });
}




$('#detail_payment_packages').on('show.bs.modal', function (event) {
  var button = $(event.relatedTarget) // Button that triggered the modal
  var id = button.data('id') // Extract info from data-* attributes
  var customer = button.data('customer') // Extract info from data-* attributes

  $('#order_id_confirm_payment').val(id);
  $('#customer_id_confirm_payment').val(customer);

  $(".resultados_ajax_payment_data").html('');

  cdp_load_payment_detail(id);//Cargas los pagos 

})

function cdp_load_payment_detail(id) {

  var parametros = { "id": id };
  $.ajax({

    url: 'ajax/courier/courier_payment_detail_ajax.php',
    data: parametros,
    success: function (data) {
      $(".resultados_ajax_payment_data").html(data).fadeIn('slow');
    }
  });
}



$("#send_payment").on('submit', function (event) {

  $('#save_payment').attr("disabled", true);

  var parametros = $(this).serialize();
  $.ajax({
    type: "POST",
    url: "ajax/courier/courier_confirm_payment.php",
    data: parametros,
    beforeSend: function (objeto) {
    },
    success: function (datos) {

      $('#detail_payment_packages').modal('hide');

      $("#resultados_ajax").html(datos);
      $('#save_payment').attr("disabled", false);

      cdp_load(localStorage.getItem('currentTablePage-Locker'));

    }
  });
  event.preventDefault();

})