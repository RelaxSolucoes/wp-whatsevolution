/* Envio por status do pedido */

// Função para redimensionar automaticamente os textareas
function autoResizeTextarea(textarea) {
    if (!textarea) return;
    
    // Reseta a altura para calcular a nova
    textarea.style.height = 'auto';
    
    // Calcula a nova altura baseada no conteúdo
    var newHeight = Math.max(50, textarea.scrollHeight + 4); // mínimo 50px
    
    // Define a nova altura
    textarea.style.height = newHeight + 'px';
}

jQuery(document).ready(function($) {
    console.log('=== WP WhatsEvolution - Send By Status CARREGADO ===');
    console.log('jQuery disponível:', typeof $ !== 'undefined');
    console.log('Botões .wpwevo-variable encontrados:', $('.wpwevo-variable').length);
    console.log('Textareas encontrados:', $('.wpwevo-auto-resize-textarea').length);
    
    var $form = $('#wpwevo-status-messages-form');
    var $button = $form.find('button[type="submit"]');
    var $spinner = $form.find('.spinner');
    var $result = $('#wpwevo-save-result');
    
    // ========================================
    // 1. AUTO-RESIZE DOS TEXTAREAS
    // ========================================
    $('.wpwevo-auto-resize-textarea').each(function() {
        var textarea = this;
        
        // Redimensiona inicialmente
        autoResizeTextarea(textarea);
        
        // Adiciona eventos para redimensionar conforme o usuário digita
        $(textarea).on('input keyup paste', function() {
            autoResizeTextarea(this);
        });
    });

    // ========================================
    // 2. CLIQUE NOS SHORTCODES - ADICIONA NO TEXTAREA
    // ========================================
    
    // Método mais agressivo para capturar cliques
    $(document).on('click', '.wpwevo-variable, .wpwevo-variable *', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('=== CLIQUE DETECTADO ===');
        
        // Se clicou em um elemento filho, pega o pai .wpwevo-variable
        var $button = $(this).hasClass('wpwevo-variable') ? $(this) : $(this).closest('.wpwevo-variable');
        
        if ($button.length === 0) {
            console.log('Botão não encontrado!');
            return;
        }
        
        var variable = $button.data('variable') || $button.find('code').text().trim();
        console.log('Variável extraída:', variable);
        
        // BUSCA TEXTAREA DE FORMA MAIS ROBUSTA
        var $textarea = null;
        
        // Primeiro tenta encontrar no mesmo container
        var $container = $button.closest('div[style*="border-left: 4px solid"]');
        if ($container.length > 0) {
            $textarea = $container.find('textarea.wpwevo-auto-resize-textarea');
            console.log('Textarea encontrado no container:', $textarea.length);
        }
        
        // Se não encontrou, procura o textarea atualmente focado
        if ($textarea === null || $textarea.length === 0) {
            $textarea = $('textarea.wpwevo-auto-resize-textarea:focus');
            console.log('Textarea focado encontrado:', $textarea.length);
        }
        
        // Se ainda não encontrou, pega o primeiro textarea visível
        if ($textarea === null || $textarea.length === 0) {
            $textarea = $('textarea.wpwevo-auto-resize-textarea:visible').first();
            console.log('Primeiro textarea visível:', $textarea.length);
        }
        
        if ($textarea && $textarea.length > 0) {
            console.log('Adicionando variável ao textarea...');
            
            // Obtém a posição atual do cursor ou fim do texto
            var cursorPos = 0;
            try {
                cursorPos = $textarea[0].selectionStart || $textarea.val().length;
            } catch(e) {
                cursorPos = $textarea.val().length;
            }
            
            var textBefore = $textarea.val().substring(0, cursorPos);
            var textAfter = $textarea.val().substring(cursorPos);
            
            // Adiciona a variável na posição do cursor
            var newText = textBefore + variable + textAfter;
            $textarea.val(newText);
            
            // Reposiciona o cursor após a variável inserida
            try {
                var newCursorPos = cursorPos + variable.length;
                $textarea[0].setSelectionRange(newCursorPos, newCursorPos);
                $textarea.focus();
            } catch(e) {
                $textarea.focus();
            }
            
            // Redimensiona o textarea
            autoResizeTextarea($textarea[0]);
            
            // FEEDBACK VISUAL GARANTIDO
            showVariableAddedFeedback($button, variable);
            
            console.log('Variável adicionada com sucesso!');
        } else {
            console.log('ERRO: Nenhum textarea encontrado!');
            alert('Erro: Não foi possível encontrar o campo de mensagem. Clique primeiro no campo onde deseja adicionar a variável.');
        }
    });
    
    // ========================================
    // 3. FUNÇÃO DE FEEDBACK VISUAL
    // ========================================
    function showVariableAddedFeedback($button, variable) {
        // Animação no botão
        $button.css({
            'background': '#48bb78 !important',
            'color': 'white !important',
            'transform': 'scale(1.1)',
            'transition': 'all 0.2s'
        });
        
        // Notificação no topo
        var $notification = $('<div>')
            .css({
                'position': 'fixed',
                'top': '20px',
                'right': '20px',
                'background': '#48bb78',
                'color': 'white',
                'padding': '15px 25px',
                'border-radius': '8px',
                'z-index': '999999',
                'box-shadow': '0 4px 20px rgba(72, 187, 120, 0.4)',
                'font-family': 'system-ui, -apple-system, sans-serif',
                'font-size': '14px',
                'font-weight': '600',
                'animation': 'slideInRight 0.3s ease-out'
            })
            .html('✅ <strong>' + variable + '</strong> adicionado!')
            .appendTo('body');
        
        // Remove feedback do botão
        setTimeout(function() {
            $button.css({
                'background': '',
                'color': '',
                'transform': '',
                'transition': ''
            });
        }, 400);
        
        // Remove notificação
        setTimeout(function() {
            $notification.fadeOut(400, function() {
                $(this).remove();
            });
        }, 3000);
    }

    // ========================================
    // 4. BOTÃO RESTAURAR PADRÃO
    // ========================================
    $(document).on('click', '.wpwevo-reset-message', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var defaultMessage = $button.data('default');
        
        // Encontra o textarea no mesmo container
        var $container = $button.closest('div[style*="border-left: 4px solid"]');
        var $textarea = $container.find('textarea.wpwevo-auto-resize-textarea');
        
        if ($textarea.length > 0) {
            var confirmText = 'Deseja restaurar a mensagem padrão? Isso substituirá o conteúdo atual.';
            if (typeof wpwevoSendByStatus !== 'undefined' && wpwevoSendByStatus.i18n && wpwevoSendByStatus.i18n.confirmReset) {
                confirmText = wpwevoSendByStatus.i18n.confirmReset;
            }
            
            if (confirm(confirmText)) {
                $textarea.val(defaultMessage);
                autoResizeTextarea($textarea[0]);
                $textarea.focus();
                
                // Feedback visual
                showRestoreSuccessFeedback($button);
            }
        }
    });
    
    // ========================================
    // 5. FUNÇÃO DE FEEDBACK RESTAURAR
    // ========================================
    function showRestoreSuccessFeedback($button) {
        $button.css({
            'background': '#667eea !important',
            'color': 'white !important',
            'transform': 'scale(1.1)',
            'transition': 'all 0.2s'
        });
        
        var $notification = $('<div>')
            .css({
                'position': 'fixed',
                'top': '20px',
                'right': '20px',
                'background': '#667eea',
                'color': 'white',
                'padding': '15px 25px',
                'border-radius': '8px',
                'z-index': '999999',
                'box-shadow': '0 4px 20px rgba(102, 126, 234, 0.4)',
                'font-family': 'system-ui, -apple-system, sans-serif',
                'font-size': '14px',
                'font-weight': '600'
            })
            .html('🔄 <strong>Mensagem padrão restaurada!</strong>')
            .appendTo('body');
        
        setTimeout(function() {
            $button.css({
                'background': '',
                'color': '',
                'transform': '',
                'transition': ''
            });
        }, 400);
        
        setTimeout(function() {
            $notification.fadeOut(400, function() {
                $(this).remove();
            });
        }, 3000);
    }

    // ========================================
    // 6. SALVAR FORMULÁRIO
    // ========================================
    $form.on('submit', function(e) {
        e.preventDefault();

        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $result.hide();

        var formData = {
            action: 'wpwevo_save_status_messages',
            nonce: wpwevoSendByStatus.nonce,
            status: {}
        };
        
        // Processa todos os status
        $form.find('div[style*="border-left: 4px solid"]').each(function() {
            var $statusBlock = $(this);
            var $checkbox = $statusBlock.find('input[type="checkbox"]');
            var $textarea = $statusBlock.find('textarea');
            var matches = $checkbox.attr('name').match(/status\[(.*?)\]/);
            if (!matches) return;
            
            var status = matches[1];
            formData.status[status] = {
                enabled: $checkbox.is(':checked'),
                message: $textarea.val() || ''
            };
        });

        $.ajax({
            url: wpwevoSendByStatus.ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showResult('success', response.data.message);
                } else {
                    showResult('error', response.data.message || 'Erro ao salvar configurações');
                }
            },
            error: function(xhr, status, error) {
                showResult('error', 'Erro ao salvar configurações: ' + error);
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });

    // ========================================
    // 7. MOSTRAR RESULTADO DO SALVAMENTO
    // ========================================
    function showResult(type, message) {
        var bgColor = type === 'success' ? '#d4edda' : '#f8d7da';
        var borderColor = type === 'success' ? '#c3e6cb' : '#f5c6cb';
        var textColor = type === 'success' ? '#155724' : '#721c24';
        var icon = type === 'success' ? '✅' : '❌';
        
        $result.html(
            '<div style="background: ' + bgColor + '; border: 1px solid ' + borderColor + '; color: ' + textColor + '; padding: 12px; border-radius: 6px; margin-top: 15px;">' +
            '<span style="margin-right: 8px;">' + icon + '</span>' + message +
            '</div>'
        ).fadeIn();
        
        if (type === 'success') {
            setTimeout(function() {
                window.location.reload();
            }, 1500);
        }
    }
    
    // CSS para animação
    $('<style>').prop('type', 'text/css').html(`
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    `).appendTo('head');
    
    console.log('=== WP WhatsEvolution - Send By Status PRONTO ===');
}); 