/**
 * JavaScript para a administração do plugin WP WhatsApp Sender
 */
(function($) {
    'use strict';

    // Quando o documento estiver pronto
    $(document).ready(function() {
        
        // Formatação de números de telefone
        $('#wp_whatsapp_sender_to').on('input', function() {
            var phoneNumber = $(this).val();
            
            // Remove caracteres não numéricos para validação
            var numericOnly = phoneNumber.replace(/[^0-9]/g, '');
            
            // Valida o comprimento do número
            if (numericOnly.length >= 10 && numericOnly.length <= 13) {
                $(this).removeClass('invalid').addClass('valid');
            } else {
                $(this).removeClass('valid').addClass('invalid');
            }
        });
        
        // Contador de caracteres para mensagens
        $('#wp_whatsapp_sender_message').on('input', function() {
            var message = $(this).val();
            var charCount = message.length;
            
            // Atualiza o contador se ele existir, ou cria um novo
            var counterElement = $(this).next('.char-counter');
            if (counterElement.length) {
                counterElement.text(charCount + ' caracteres');
            } else {
                $(this).after('<span class="char-counter description">' + charCount + ' caracteres</span>');
            }
        });
        
        // Confirmação de exclusão de templates
        $('.delete-template').on('click', function(e) {
            if (!confirm('Tem certeza que deseja excluir este template?')) {
                e.preventDefault();
            }
        });
        
        // Validação do nome do template
        $('#wp_whatsapp_sender_template_name').on('input', function() {
            var templateName = $(this).val();
            
            // Permite apenas letras, números e underscore
            if (!/^[a-zA-Z0-9_]+$/.test(templateName) && templateName !== '') {
                if (!$(this).next('.template-name-error').length) {
                    $(this).after('<p class="template-name-error description" style="color: red;">Use apenas letras, números e underscores.</p>');
                }
            } else {
                $(this).next('.template-name-error').remove();
            }
        });
        
        // Pré-visualização de templates
        $('#wp_whatsapp_sender_template_content').on('input', function() {
            var template = $(this).val();
            var previewElement = $('#template-preview');
            
            // Cria a área de pré-visualização se não existir
            if (!previewElement.length) {
                $(this).after('<div id="template-preview-container"><h4>Pré-visualização:</h4><div id="template-preview" class="wp-whatsapp-sender-card"></div></div>');
                previewElement = $('#template-preview');
            }
            
            // Atualiza a pré-visualização
            previewElement.text(template);
        });
    });

})(jQuery); 