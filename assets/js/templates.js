jQuery(document).ready(function($) {
    var $form = $('#wpwevo-template-form');
    var $preview = $('#wpwevo-template-preview');
    var currentId = '';

    // Preview do template
    $('#wpwevo-preview-template').on('click', function() {
        var message = $('#wpwevo-template-message').val();
        if (!message) {
            alert('Digite uma mensagem para visualizar');
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpwevo_preview_template',
                message: message,
                nonce: $('#wpwevo_template_nonce').val()
            },
            beforeSend: function() {
                $(this).prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    $preview.html(response.data.preview).show();
                } else {
                    alert(response.data);
                }
            },
            error: function() {
                alert('Erro ao gerar preview');
            },
            complete: function() {
                $(this).prop('disabled', false);
            }
        });
    });

    // Salvar template
    $form.on('submit', function(e) {
        e.preventDefault();
        
        var name = $('#wpwevo-template-name').val();
        var message = $('#wpwevo-template-message').val();
        
        if (!name || !message) {
            alert('Nome e mensagem são obrigatórios');
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpwevo_save_template',
                id: currentId,
                name: name,
                message: message,
                nonce: $('#wpwevo_template_nonce').val()
            },
            beforeSend: function() {
                $form.find('button[type="submit"]').prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data);
                }
            },
            error: function() {
                alert('Erro ao salvar template');
            },
            complete: function() {
                $form.find('button[type="submit"]').prop('disabled', false);
            }
        });
    });

    // Editar template
    $('.wpwevo-edit-template').on('click', function() {
        currentId = $(this).data('id');
        $('#wpwevo-template-name').val($(this).data('name'));
        $('#wpwevo-template-message').val($(this).data('message'));
        $('html, body').animate({ scrollTop: $form.offset().top - 50 }, 500);
    });

    // Excluir template
    $('.wpwevo-delete-template').on('click', function() {
        if (!confirm('Tem certeza que deseja excluir este template?')) {
            return;
        }

        var $button = $(this);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpwevo_delete_template',
                id: $button.data('id'),
                nonce: $('#wpwevo_template_nonce').val()
            },
            beforeSend: function() {
                $button.prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data);
                }
            },
            error: function() {
                alert('Erro ao excluir template');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
}); 