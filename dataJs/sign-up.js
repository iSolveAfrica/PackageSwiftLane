"use strict";

$(function () {
  cdp_load_countries();
  cdp_load_states();
  cdp_load_cities();
});

function cdp_labelSignupSelect(id) {
  var $selection = $('#' + id).next('.select2-container').find('.select2-selection');
  var $rendered = $selection.find('.select2-selection__rendered');
  // The rendered value is plain text within the combobox, not a second textbox.
  $rendered.removeAttr('role aria-readonly');
  $selection.attr('aria-labelledby', id + '-label ' + $rendered.attr('id'));
  $selection.attr('aria-required', 'true');
}

function cdp_load_countries() {
  $("#country")
    .select2({
      ajax: {
        url: "ajax/select2_countries.php",
        dataType: "json",

        delay: 250,
        data: function (params) {
          return {
            q: params.term, // search term
          };
        },
        processResults: function (data) {
          return {
            results: data,
          };
        },
        cache: true,
      },
      placeholder: translate_search_country,
      allowClear: true,
    })
    .off("change.swlCountry")
    .on("change.swlCountry", function (e) {
      var country = $("#country").val();
      $("#state").attr("disabled", true);
      $("#state").val(null).trigger('change.select2');
      $("#city").val(null).prop('disabled', true).trigger('change.select2');
      if (country != null) {
        $("#state").attr("disabled", false);
      }
      cdp_load_states();
    });
  cdp_labelSignupSelect('country');
}

function cdp_load_states() {
  var country = $("#country").val();

  $("#state")
    .select2({
      ajax: {
        url: "ajax/select2_states.php?id=" + country,
        dataType: "json",

        delay: 250,
        data: function (params) {
          return {
            q: params.term, // search term
          };
        },
        processResults: function (data) {
          return {
            results: data,
          };
        },
        cache: true,
      },
      placeholder: translate_search_state,
      allowClear: true,
    })
    .off("change.swlState")
    .on("change.swlState", function (e) {
      var state = $("#state").val();

      $("#city").attr("disabled", true);
      $("#city").val(null).trigger('change.select2');

      if (state != null) {
        $("#city").attr("disabled", false);
      }

      cdp_load_cities();
    });
  cdp_labelSignupSelect('state');
}

function cdp_load_cities() {
  var state = $("#state").val();

  $("#city").select2({
    ajax: {
      url: "ajax/select2_cities.php?id=" + state,
      dataType: "json",
      delay: 250,
      data: function (params) {
        return {
          q: params.term, // search term
        };
      },
      processResults: function (data) {
        return {
          results: data,
        };
      },
      cache: true,
    },
    placeholder: translate_search_city,
    allowClear: true,
  });
  cdp_labelSignupSelect('city');
}

function cdp_showError(errors) {
  Swal.fire({
    title: message_error,
    text: errors,
    icon: "error",
    allowOutsideClick: false,
    confirmButtonText: "OK",
    confirmButtonColor: '#f27474',
  });
}

function cdp_showSuccess(messages) {
  Swal.fire({
    title: messages,
    icon: "success",
    allowOutsideClick: false,
    confirmButtonText: "OK",
    confirmButtonColor: '#336aea',
    type: "success",
  }).then((okay) => {
    if (okay) {
      if (window.signupRedirect) {
        window.location.href = window.signupRedirect;
      } else {
        window.location.href = "index.php";
      }
    }
  });
}

var errorMsg = document.querySelector("#error-msg");
var validMsg = document.querySelector("#valid-msg");

// here, the index maps to the error code returned from getValidationError - see readme
var errorMap = [
  "Invalid number",
  "Invalid country code",
  "Too short",
  "Too long",
  "Invalid number",
];

var input = document.querySelector("#phone_custom");
var iti = window.intlTelInput(input, {
  geoIpLookup: function (callback) {
    $.get("https://ipinfo.io", function () {}, "jsonp").always(function (resp) {
      var countryCode = resp && resp.country ? resp.country : "";
      callback(countryCode);
    });
  },
  initialCountry: "auto",
  nationalMode: true,
  separateDialCode: true,
  utilsScript: "assets/template/assets/libs/intlTelInput/utils.js",
});

var reset = function () {
  input.classList.remove("is-invalid");
  input.removeAttribute('aria-invalid');
  $("#phone").val('');
  errorMsg.innerHTML = "";
  errorMsg.classList.add("hide");
  validMsg.classList.add("hide");
};

// on blur: validate
input.addEventListener("blur", function () {
  reset();
  if (input.value.trim()) {
    if (iti.isValidNumber()) {
      $("#phone").val(iti.getNumber());
      validMsg.classList.remove("hide");
    } else {
      input.classList.add("is-invalid");
      input.setAttribute('aria-invalid', 'true');
      var errorCode = iti.getValidationError();
      errorMsg.textContent = errorMap[errorCode] || 'Enter a valid phone number.';
      errorMsg.classList.remove("hide");
    }
  }
});

// on keyup / change flag: reset
input.addEventListener("change", reset);
input.addEventListener("keyup", reset);


// Validación de fortaleza de la contraseña en tiempo real
  $("#pass").on("input", function () {
    var password = $(this).val();
    var strength = calculatePasswordStrength(password);
    var meter = $("#password-strength-meter");

    var text = "";
    var colorClass = "";
    if (password.length < 9) {
      text = message_error_form94;
      colorClass = "weak";
    } else if (strength < 20) {
      text = message_error_form95;
      colorClass = "weaks";
    } else if (strength < 40) {
      text = message_error_form96;
      colorClass = "medium";
    } else if (strength < 60) {
      text = message_error_form97;
      colorClass = "good";
    } else if (strength < 80) {
      text = message_error_form98;
      colorClass = "strong";
    } else {
      text = message_error_form99;
      colorClass = "very-strong";
    }

    meter.removeClass().addClass(colorClass).text(text + " (" + password.length + " "+ message_error_form100 +")");
  });

  // Validación de coincidencia de contraseñas
  $("#pass2").on("input", function () {
    var password = $("#pass").val();
    var password2 = $(this).val();
    if (password !== password2) {
      $("#passwordMatch").text(message_error_form101);
    } else {
      $("#passwordMatch").text("");
    }
  });

  // Función para calcular la fortaleza de la contraseña
  function calculatePasswordStrength(password) {
    var score = 0;
    if (password.length > 7) {
      score++;
    }
    if ((/[a-z]/.test(password)) && (/[A-Z]/.test(password))) {
      score++;
    }
    if (/[0-9]/.test(password)) {
      score++;
    }
    if (/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) {
      score++;
    }
    return score * 20;
  }
  
  

$("#new_register").on("submit", function (event) {
  event.preventDefault();
  var $form = $(this);
  if ($form.data('submitting')) return;

  // Validación de la contraseña
  var password = $("#pass").val();
  var strength = calculatePasswordStrength(password);
  if (strength < 40) {
    Swal.fire({
      title: message_error_form102,
      text: message_error_form103,
      icon: 'error',
      allowOutsideClick: false,
      confirmButtonColor: "#f27474",
      confirmButtonText: "OK",
    });
    return;
  }

  // Use FormData to support file uploads (avatar, document_photo)
  if (iti.isValidNumber()) {
    // Autofill and Enter can submit without blur; always serialize the current number.
    $("#phone").val(iti.getNumber());
    var formData = new FormData(this);
    $form.data('submitting', true).attr('aria-busy', 'true');
    $form.find(':submit').prop('disabled', true);

    $.ajax({
      type: "POST",
      url: "./ajax/sign_up_ajax.php",
      data: formData,
      dataType: "json",
      timeout: 30000,
      // Required for FormData — let the browser set the correct multipart boundary
      processData: false,
      contentType: false,
      beforeSend: function (objeto) {
        Swal.fire({
          title: message_loading,
          allowOutsideClick: false,
          didOpen: () => {
            Swal.showLoading();
          },
        });
      },
      success: function (data) {
        if (data.success) {
          window.signupRedirect = data.redirect || null;
          cdp_showSuccess(data.messages);
        } else {
          cdp_showError(data.errors);
        }
      },
      error: function (xhr) {
        var message = xhr.responseJSON && xhr.responseJSON.errors;
        cdp_showError(typeof message === 'string' ? message : 'We could not confirm your registration. Check your connection and try again. Your entered details are still here.');
      },
      complete: function () {
        $form.data('submitting', false).removeAttr('aria-busy');
        $form.find(':submit').prop('disabled', false);
      },
    });
  } else {
    input.classList.add("is-invalid");
    input.setAttribute('aria-invalid', 'true');
    var errorCode = iti.getValidationError();
    errorMsg.textContent = errorMap[errorCode] || 'Enter a valid phone number.';
    errorMsg.classList.remove("hide");
    $("#phone_custom").focus();
  }
});
