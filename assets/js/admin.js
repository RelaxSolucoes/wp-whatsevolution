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
});