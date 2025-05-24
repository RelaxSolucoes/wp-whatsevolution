/* Envio por status do pedido */
jQuery(document).ready(function($) {
    var $form = $('#wpwevo-status-messages-form');
    var $button = $form.find('button[type="submit"]');
    var $spinner = $form.find('.spinner');
    var $result = $('#wpwevo-save-result');

    // Salvar configurações
    $form.on('submit', function(e) {
        e.preventDefault();

        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $result.hide();

        $.ajax({
            url: wpwevoSendByStatus.ajaxurl,
            type: 'POST',
            data: {
                action: 'wpwevo_save_status_messages',
                nonce: wpwevoSendByStatus.nonce,
                status: $(this).serializeArray()
            },
            success: function(response) {
                if (response.success) {
                    showResult('success', response.data);
                } else {
                    showResult('error', wpwevoSendByStatus.i18n.error + response.data);
                }
            },
            error: function() {
                showResult('error', wpwevoSendByStatus.i18n.error + wpwevoSendByStatus.i18n.networkError);
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });

    // Preview de mensagem
    $('.wpwevo-preview-message').on('click', function() {
        var $button = $(this);
        var status = $button.data('status');
        var $preview = $('#preview-' + status);
        var message = $('textarea[name="status[' + status + '][message]"]').val();

        if (!message) {
            $preview.html('<div class="notice notice-warning"><p>' + 
                wpwevoSendByStatus.i18n.emptyMessage + '</p></div>').show();
            return;
        }

        $button.prop('disabled', true);
        $preview.html('<div class="notice notice-info"><p>' + 
            wpwevoSendByStatus.i18n.preview + '</p></div>').show();

        $.ajax({
            url: wpwevoSendByStatus.ajaxurl,
            type: 'POST',
            data: {
                action: 'wpwevo_preview_message',
                nonce: wpwevoSendByStatus.previewNonce,
                status: status,
                message: message
            },
            success: function(response) {
                if (response.success) {
                    $preview.html('<div class="notice notice-info"><p>' + response.data + '</p></div>');
                } else {
                    $preview.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
            },
            error: function() {
                $preview.html('<div class="notice notice-error"><p>' + 
                    wpwevoSendByStatus.i18n.networkError + '</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });

    // Função para mostrar resultado
    function showResult(type, message) {
        var className = type === 'success' ? 'notice-success' : 'notice-error';
        $result.html('<div class="notice ' + className + '"><p>' + message + '</p></div>').fadeIn();
    }
}); 