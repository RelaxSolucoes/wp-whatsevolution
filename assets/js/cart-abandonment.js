/* Cart Abandonment WhatsApp */
jQuery(document).ready(function($) {
    // Sistema de Abas (igual ao bulk-sender)
    function initTabs() {
        $('.wpwevo-tab-button').on('click', function(e) {
            e.preventDefault();
            var tab = $(this).data('tab');
            
            // Remove active class from all buttons and contents
            $('.wpwevo-tab-button').removeClass('active');
            $('.wpwevo-tab-content').removeClass('active');
            
            // Add active class to clicked button and corresponding content
            $(this).addClass('active');
            $('#tab-' + tab).addClass('active');
            
            // Store active tab in session
            if (typeof(Storage) !== "undefined") {
                sessionStorage.setItem('wpwevo_cart_abandonment_active_tab', tab);
            }
        });

        // Restore last active tab
        if (typeof(Storage) !== "undefined") {
            var lastTab = sessionStorage.getItem('wpwevo_cart_abandonment_active_tab');
            if (lastTab) {
                $('.wpwevo-tab-button[data-tab="' + lastTab + '"]').trigger('click');
            }
        }
    }

    // Template Functions
    function initTemplate() {
        // Salvar template
        $('#save-template').on('click', function() {
            const template = $('#whatsapp-template').val();
            const button = $(this);
            const originalText = button.text();
            
            const savingText = wpwevoCartAbandonment?.i18n?.saving || 'Salvando...';
            
            button.prop('disabled', true).text(savingText);
            
            $.ajax({
                url: wpwevoCartAbandonment.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpwevo_save_template',
                    template: template,
                    nonce: wpwevoCartAbandonment.nonce
                },
                success: function(response) {
                    const messageDiv = $('#template-message');
                    if (response.success) {
                        messageDiv.html('<div style="color: #28a745; background: #d4edda; padding: 10px; border-radius: 4px; margin-top: 10px;">✅ ' + response.data.message + '</div>');
                    } else {
                        messageDiv.html('<div style="color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 4px; margin-top: 10px;">❌ ' + response.data.message + '</div>');
                    }
                    
                    setTimeout(() => messageDiv.html(''), 3000);
                },
                error: function() {
                    $('#template-message').html('<div style="color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 4px; margin-top: 10px;">❌ Erro ao salvar template</div>');
                },
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        });

        // Preview template
        $('#preview-template').on('click', function() {
            const template = $('#whatsapp-template').val();
            const button = $(this);
            const originalText = button.text();
            
            const generatingText = wpwevoCartAbandonment?.i18n?.generating || 'Gerando...';
            
            button.prop('disabled', true).text(generatingText);
            
            $.ajax({
                url: wpwevoCartAbandonment.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpwevo_preview_template',
                    template: template,
                    nonce: wpwevoCartAbandonment.nonce
                },
                success: function(response) {
                    const previewDiv = $('#message-preview');
                    if (response.success) {
                        previewDiv.text(response.data.preview).css('color', '#000');
                    } else {
                        previewDiv.html('<em style="color: #dc3545;">❌ Erro: ' + response.data.message + '</em>');
                    }
                },
                error: function() {
                    $('#message-preview').html('<em style="color: #dc3545;">❌ Erro ao gerar preview</em>');
                },
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        });

        // Reset template
        $('#reset-template').on('click', function() {
            if (confirm('Tem certeza que deseja resetar o template para o padrão?')) {
                const defaultTemplate = "🛒 Oi {first_name}!\n\nVi que você adicionou estes itens no carrinho:\n📦 {product_names}\n\n💰 Total: {cart_total}\n\n🎁 Use o cupom *{coupon_code}* e ganhe desconto especial!\n⏰ Mas corre que é só por hoje!\n\nFinalize agora:\n👆 {checkout_url}";
                $('#whatsapp-template').val(defaultTemplate);
                $('#message-preview').html('<em style="color: #666;">📱 Clique em "Visualizar" para ver como ficará a mensagem</em>');
            }
        });
    }

    // Test Functions
    function initTest() {
        $('#test-internal-webhook').on('click', function(e) {
            e.preventDefault();
            var $button = $(this);
            var originalText = $button.text();
            
            $button.prop('disabled', true).text('🧪 Testando...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpwevo_test_cart_abandonment_webhook',
                    nonce: wpwevoCartAbandonment?.nonce || ''
                },
                success: function(response) {
                    if (response.success) {
                        alert('✅ Teste realizado com sucesso!\n\n' + response.data);
                    } else {
                        alert('❌ Erro no teste:\n\n' + response.data);
                    }
                },
                error: function() {
                    alert('❌ Erro de comunicação com o servidor.');
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });
    }

    // Log Functions
    function refreshLogs() {
        $.ajax({
            url: wpwevoCartAbandonment.ajaxurl,
            type: 'POST',
            data: {
                action: 'wpwevo_get_logs',
                nonce: wpwevoCartAbandonment.nonce
            },
            success: function(data) {
                $('#webhook-logs').html(data);
            },
            error: function() {
                console.error('Erro ao atualizar logs');
            }
        });
    }

    function clearLogs() {
        if (confirm('Tem certeza que deseja limpar todos os logs?')) {
            $.ajax({
                url: wpwevoCartAbandonment.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpwevo_clear_logs',
                    nonce: wpwevoCartAbandonment.nonce
                },
                success: function() {
                    refreshLogs();
                    alert('✅ Logs limpos com sucesso!');
                },
                error: function() {
                    alert('❌ Erro ao limpar logs');
                }
            });
        }
    }

    // Global Functions
    window.copyWebhookUrl = function() {
        var webhookInput = document.querySelector('input[readonly]');
        if (webhookInput) {
            webhookInput.select();
            document.execCommand('copy');
            alert('URL copiada para a área de transferência!');
        }
    };

    window.refreshLogs = refreshLogs;
    window.clearLogs = clearLogs;

    window.insertShortcode = function(shortcode) {
        const textarea = document.getElementById('whatsapp-template');
        if (textarea) {
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = textarea.value;
            
            textarea.value = text.substring(0, start) + shortcode + text.substring(end);
            textarea.focus();
            textarea.setSelectionRange(start + shortcode.length, start + shortcode.length);
        }
    };

    // Initialize all modules
    initTabs();
    initTemplate();
    initTest();
}); 