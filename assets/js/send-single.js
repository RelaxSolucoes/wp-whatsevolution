/* Envio único de WhatsApp */

// Função global para inserir variáveis (usada pelos botões de variáveis)
function insertVariable(variable) {
    var textarea = document.getElementById('wpwevo-message');
    if (textarea) {
        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var text = textarea.value;
        var before = text.substring(0, start);
        var after = text.substring(end);
        
        textarea.value = before + variable + after;
        textarea.focus();
        textarea.setSelectionRange(start + variable.length, start + variable.length);
    }
}

jQuery(document).ready(function($) {
    var validateTimeout;
    var $form = $('#wpwevo-send-single-form');
    var $number = $('#wpwevo-number');
    var $message = $('#wpwevo-message');
    var $template = $('#wpwevo-template');
    var $button = $form.find('button[type="submit"]');
    var $spinner = $form.find('.spinner');
    var $result = $('#wpwevo-send-result');
    var $validation = $('#wpwevo-number-validation');

    // Validação do número em tempo real
    $number.on('input', function() {
        var number = $(this).val().replace(/[^0-9]/g, '');
        $(this).val(number);

        clearTimeout(validateTimeout);
        if (number.length >= 10) {
            validateTimeout = setTimeout(function() {
                validateNumber(number);
            }, 500);
        } else {
            $validation.hide();
        }
    });

    // Usar template
    if ($template.length) {
        $template.on('change', function() {
            var message = $(this).val();
            if (message) {
                $message.val(message);
            }
        });
    }

    // Envio do formulário
    $form.on('submit', function(e) {
        e.preventDefault();

        var number = $number.val();
        var message = $message.val();

        if (!number || !message) {
            showResult('error', wpwevoSendSingle.i18n.error + wpwevoSendSingle.i18n.emptyFields);
            return;
        }

        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $result.hide();

        $.ajax({
            url: wpwevoSendSingle.ajaxurl,
            type: 'POST',
            data: {
                action: 'wpwevo_send_single',
                nonce: wpwevoSendSingle.nonce,
                number: number,
                message: message
            },
            success: function(response) {
                if (response.success) {
                    showResult('success', response.data);
                    $form.trigger('reset');
                    $validation.hide();
                } else {
                    showResult('error', wpwevoSendSingle.i18n.error + response.data);
                }
            },
            error: function() {
                showResult('error', wpwevoSendSingle.i18n.error + wpwevoSendSingle.i18n.networkError);
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });

    // Função para validar número
    function validateNumber(number) {
        $validation.html('<span class="validating">' + wpwevoSendSingle.i18n.validating + '</span>');
        $validation.show();

        $.ajax({
            url: wpwevoSendSingle.ajaxurl,
            type: 'POST',
            data: {
                action: 'wpwevo_validate_number',
                nonce: wpwevoSendSingle.validateNonce,
                number: number
            },
            success: function(response) {
                var message = response.success ? wpwevoSendSingle.i18n.validNumber : wpwevoSendSingle.i18n.invalidNumber;
                var className = response.success ? 'valid' : 'invalid';
                $validation.html('<span class="' + className + '">' + message + '</span>');
            },
            error: function() {
                $validation.html('<span class="error">' + wpwevoSendSingle.i18n.networkError + '</span>');
            }
        });
    }

    // Função para mostrar resultado
    function showResult(type, message) {
        var className = type === 'success' ? 'notice-success' : 'notice-error';
        $result.html('<div class="notice ' + className + '"><p>' + message + '</p></div>').fadeIn();
    }
}); 