/* Envio em massa de WhatsApp */
jQuery(document).ready(function($) {
    // Tabs
    $('.wpwevo-tab-button').on('click', function() {
        var tab = $(this).data('tab');
        $('.wpwevo-tab-button').removeClass('active');
        $('.wpwevo-tab-content').removeClass('active');
        $(this).addClass('active');
        $('#tab-' + tab).addClass('active');
    });

    // Agendamento
    $('input[name="wpwevo_schedule_enabled"]').on('change', function() {
        $('.wpwevo-schedule-options').toggle(this.checked);
    });

    // Preview de clientes
    $('#wpwevo-preview-customers').on('click', function() {
        var $button = $(this);
        var $preview = $('#wpwevo-customers-preview');
        
        $button.prop('disabled', true);
        $preview.html('<div class="spinner is-active"></div>');

        $.ajax({
            url: wpwevoBulkSend.ajaxurl,
            type: 'POST',
            data: {
                action: 'wpwevo_preview_customers',
                status: $('select[name="wpwevo_order_status[]"]').val(),
                date_from: $('input[name="wpwevo_date_from"]').val(),
                date_to: $('input[name="wpwevo_date_to"]').val(),
                min_total: $('input[name="wpwevo_min_total"]').val(),
                nonce: wpwevoBulkSend.nonce
            },
            success: function(response) {
                if (response.success) {
                    $preview.html(response.data);
                } else {
                    $preview.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
            },
            error: function() {
                $preview.html('<div class="notice notice-error"><p>Erro ao carregar preview. Tente novamente.</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });

    // Envio em massa
    $('#wpwevo-bulk-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $button = $('#wpwevo-bulk-submit');
        var $spinner = $form.find('.spinner');
        var $progress = $('#wpwevo-bulk-progress');
        var $fill = $progress.find('.wpwevo-progress-fill');
        var $status = $progress.find('.wpwevo-progress-status');

        $button.prop('disabled', true).text(wpwevoBulkSend.sending);
        $spinner.addClass('is-active');
        $progress.show();

        var formData = new FormData(this);
        formData.append('action', 'wpwevo_bulk_send');
        formData.append('active_tab', $('.wpwevo-tab-button.active').data('tab'));

        $.ajax({
            url: wpwevoBulkSend.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $status.html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                    $form.trigger('reset');
                    $('.wpwevo-schedule-options').hide();
                } else {
                    $status.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
            },
            error: function() {
                $status.html('<div class="notice notice-error"><p>Erro ao iniciar envio. Tente novamente.</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false).text(wpwevoBulkSend.send);
                $spinner.removeClass('is-active');
            }
        });
    });
}); 