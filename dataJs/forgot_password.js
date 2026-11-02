"use strict";

$("#forgotPassword").on('submit', function (event) {
    event.preventDefault();

    var $form = $(this);
    if ($form.data('submitting')) return;
    var parametros = $form.serialize();
    var $submit = $form.find(':submit');
    var $feedback = $("#resultados_ajax");
    function showFeedback(message, variant) {
        $feedback.empty().append($('<div>').addClass('alert alert-' + variant).text(message));
    }
    $form.data('submitting', true).attr('aria-busy', 'true');
    $submit.prop('disabled', true);
    $.ajax({
        type: "POST",
        url: "./ajax/forgot-password-ajax.php",
        data: parametros,
        dataType: "json",
        timeout: 30000,
        beforeSend: function () {
            showFeedback('Requesting your reset code…', 'info');
        },
        success: function (data) {
            if (data.success) {
                showFeedback(data.messages || 'Your reset code has been sent.', 'success');
                window.location.href = data.redirect;
            } else {
                showFeedback(data.errors || 'Unable to request a reset code. Please try again.', 'danger');
                $feedback.focus();
            }
        },
        error: function (xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.errors;
            showFeedback(typeof message === 'string' ? message : 'We could not confirm the request. Check your connection, then try again.', 'danger');
            $feedback.focus();
        },
        complete: function () {
            $form.data('submitting', false).removeAttr('aria-busy');
            $submit.prop('disabled', false);
        }
    });
});
