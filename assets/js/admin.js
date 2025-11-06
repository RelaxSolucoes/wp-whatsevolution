jQuery(document).ready(function($) {
    // Formulário de configurações
    $('#wpwevo-settings-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $spinner = $form.find('.spinner');
        const $result = $('#wpwevo-validation-result');
        
        // Mostra o spinner
        $spinner.addClass('is-active');
        
        // Esconde mensagem anterior
        $result.hide();
        
        // Coleta os dados do formulário
        const formData = new FormData($form[0]);
        formData.append('action', 'wpwevo_validate_settings');
        formData.append('nonce', wpwevo_admin.nonce);
        
        // Envia a requisição AJAX
        $.ajax({
            url: wpwevo_admin.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                
                let message = '';
                let isSuccess = false;

                if (typeof response === 'object') {
                    isSuccess = response.success;
                    message = response.data ? response.data.message || response.message : response.message;
                    
                    // Adiciona aviso de versão se for V1
                    if (response.data && response.data.connection_status && response.data.connection_status.api_version) {
                        const apiVersion = response.data.connection_status.api_version;
                        if (!apiVersion.is_v2) {
                            message += `\n\n⚠️ ATENÇÃO: NOSSO PLUGIN NÃO É COMPATÍVEL com Evolution API V1 (versão ${apiVersion.version}). Atualize para a V2 para garantir funcionamento completo.`;
                        }
                    }
                } else {
                    message = wpwevo_admin.error_message;
                }

                $result
                    .removeClass('error success')
                    .addClass(isSuccess ? 'success' : 'error')
                    .html(message)
                    .show();
                
                if (isSuccess) {
                    // Atualiza a página após 1 segundo para mostrar o novo status
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                }
            },
            error: function(xhr, status, error) {
                
                $result
                    .removeClass('success')
                    .addClass('error')
                    .html(wpwevo_admin.error_message)
                    .show();
            },
            complete: function() {
                $spinner.removeClass('is-active');
            }
        });
    });

    // NOVO: Validação em tempo real do WhatsApp Admin
    var validateTimeout;
    var $adminWhatsApp = $('#wpwevo-admin-whatsapp');
    var $validation = $('#wpwevo-admin-whatsapp-validation');

    if ($adminWhatsApp.length) {
        $adminWhatsApp.on('input', function() {
            var number = $(this).val().replace(/[^0-9]/g, '');
            $(this).val(number);

            clearTimeout(validateTimeout);

            // Se o campo estiver vazio, esconde validação
            if (number.length === 0) {
                $validation.hide();
                return;
            }

            if (number.length >= 10) {
                validateTimeout = setTimeout(function() {
                    validateAdminNumber(number);
                }, 800);
            } else {
                $validation.hide();
            }
        });
    }

    function validateAdminNumber(number) {
        $validation.html('<span style="color: #4a5568;">⏳ Validando número...</span>');
        $validation.show();

        $.ajax({
            url: wpwevo_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'wpwevo_validate_number',
                nonce: wpwevo_admin.validate_nonce,
                number: number
            },
            success: function(response) {
                if (response.success) {
                    $validation.html('<span style="color: #48bb78;">✅ Número válido do WhatsApp!</span>');
                } else {
                    $validation.html('<span style="color: #f56565;">❌ Número inválido ou não existe no WhatsApp</span>');
                }
            },
            error: function() {
                $validation.html('<span style="color: #f56565;">❌ Erro ao validar número</span>');
            }
        });
    }
});