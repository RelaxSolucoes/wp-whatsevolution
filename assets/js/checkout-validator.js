/**
 * Frontend script for WhatsApp number validation on checkout
 */
jQuery(document).ready(function($) {
    // Inicialização robusta que funciona mesmo com conflitos
    function initializeCheckoutValidation() {
        // Verifica se já foi inicializado
        if (window.wpwevoCheckoutInitialized) {
            return;
        }
        
        // Marca como inicializado
        window.wpwevoCheckoutInitialized = true;
        
        // Inicializa a validação
        initPhoneValidation();
    }

    function initPhoneValidation() {
        // Procura por campos de telefone com debounce
        let initTimeout;
        let isInitialized = false;
        
        function findAndValidatePhoneFields() {
            if (isInitialized) return;
            
            const $phoneFields = $('input[name="billing_phone"], input[name="shipping_phone"], input[type="tel"], input[placeholder*="telefone"], input[placeholder*="phone"]');
            
            if ($phoneFields.length > 0) {
                isInitialized = true;
                
                $phoneFields.each(function() {
                    const $field = $(this);
                    const fieldId = $field.attr('id') || 'phone_' + Math.random().toString(36).substr(2, 9);
                    
                    if (!$field.attr('id')) {
                        $field.attr('id', fieldId);
                    }
                    
                    // Adiciona validação em tempo real
                    $field.on('input keyup paste', function() {
                        const number = $(this).val().replace(/[^0-9]/g, '');
                        $(this).val(number);
                        
                        if (number.length >= 10) {
                            validatePhoneNumber(number, $field);
                        }
                    });
                    
                    // Validação inicial se o campo já tem valor
                    if ($field.val().trim()) {
                        const number = $field.val().replace(/[^0-9]/g, '');
                        if (number.length >= 10) {
                            validatePhoneNumber(number, $field);
                        }
                    }
                });
            }
        }
        
        // Executa imediatamente
        findAndValidatePhoneFields();
        
        // Se não encontrou campos, tenta novamente em 1 segundo
        if (!isInitialized) {
            setTimeout(findAndValidatePhoneFields, 1000);
        }
        
        // MutationObserver para detectar novos campos dinamicamente
        const observer = new MutationObserver(function(mutations) {
            let hasNewPhoneFields = false;
            
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) { // Element node
                            const $node = $(node);
                            if ($node.is('input[name="billing_phone"], input[name="shipping_phone"], input[type="tel"]') ||
                                $node.find('input[name="billing_phone"], input[name="shipping_phone"], input[type="tel"]').length > 0) {
                                hasNewPhoneFields = true;
                            }
                        }
                    });
                }
            });
            
            if (hasNewPhoneFields) {
                clearTimeout(initTimeout);
                initTimeout = setTimeout(function() {
                    isInitialized = false;
                    findAndValidatePhoneFields();
                }, 500);
            }
        });
        
        // Observa mudanças no DOM
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    function validatePhoneNumber(number, $field) {
        // Debounce para evitar muitas requisições
        clearTimeout($field.data('validationTimeout'));
        
        const timeout = setTimeout(function() {
            $.ajax({
                url: wpwevoCheckout.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpwevo_validate_checkout_number',
                    nonce: wpwevoCheckout.nonce,
                    number: number
                },
                success: function(response) {
                    if (response.success) {
                        showValidationResult($field, true, wpwevoCheckout.validation_success);
                    } else {
                        showValidationResult($field, false, wpwevoCheckout.validation_error);
                    }
                },
                error: function() {
                    showValidationResult($field, false, wpwevoCheckout.validation_error);
                }
            });
        }, 1500);
        
        $field.data('validationTimeout', timeout);
    }

    function showValidationResult($field, isValid, message) {
        // Remove classes anteriores
        $field.removeClass('wpwevo-valid wpwevo-invalid');
        
        // Remove mensagem anterior
        $field.siblings('.wpwevo-validation-message').remove();
        
        // Adiciona classe e mensagem
        const className = isValid ? 'wpwevo-valid' : 'wpwevo-invalid';
        const messageClass = isValid ? 'wpwevo-validation-success' : 'wpwevo-validation-error';
        
        $field.addClass(className);
        
        const $message = $('<div class="wpwevo-validation-message ' + messageClass + '">' + message + '</div>');
        $field.after($message);
        
        // Se número é inválido e modal está habilitado, mostra o modal
        if (!isValid && wpwevoCheckout.show_modal === 'yes') {
            showInvalidNumberModal();
        }
        
        // Remove mensagem após 3 segundos
        setTimeout(function() {
            $message.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }

    function showInvalidNumberModal() {
        // Remove modal anterior se existir
        $('.wpwevo-checkout-modal').remove();
        
        // Cria o modal
        const modalHtml = `
            <div class="wpwevo-checkout-modal" style="
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 999999;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            ">
                <div style="
                    background: white;
                    border-radius: 12px;
                    padding: 30px;
                    max-width: 500px;
                    width: 90%;
                    box-shadow: 0 20px 40px rgba(0,0,0,0.3);
                    text-align: center;
                ">
                    <div style="
                        background: #ff6b6b;
                        color: white;
                        width: 60px;
                        height: 60px;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-size: 30px;
                        margin: 0 auto 20px;
                    ">⚠️</div>
                    
                    <h3 style="
                        margin: 0 0 15px 0;
                        color: #2d3748;
                        font-size: 20px;
                        font-weight: 600;
                    ">${wpwevoCheckout.modal_title}</h3>
                    
                    <p style="
                        margin: 0 0 25px 0;
                        color: #4a5568;
                        font-size: 16px;
                        line-height: 1.5;
                    ">${wpwevoCheckout.modal_message}</p>
                    
                    <div style="display: flex; gap: 15px; justify-content: center;">
                        <button type="button" class="wpwevo-modal-continue" style="
                            background: #667eea;
                            color: white;
                            border: none;
                            padding: 12px 24px;
                            border-radius: 8px;
                            font-size: 14px;
                            font-weight: 500;
                            cursor: pointer;
                            transition: background 0.3s;
                        ">${wpwevoCheckout.modal_button_text}</button>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modalHtml);
        
        // Event handlers
        $('.wpwevo-modal-continue').on('click', function() {
            $('.wpwevo-checkout-modal').fadeOut(300, function() {
                $(this).remove();
            });
        });
        
        // Fecha modal ao clicar fora
        $('.wpwevo-checkout-modal').on('click', function(e) {
            if (e.target === this) {
                $(this).fadeOut(300, function() {
                    $(this).remove();
                });
            }
        });
    }

    // Inicialização com múltiplas tentativas
    initializeCheckoutValidation();
    
    // Backup: tenta novamente após 2 segundos
    setTimeout(initializeCheckoutValidation, 2000);
    
    // Backup: tenta novamente após 5 segundos
    setTimeout(initializeCheckoutValidation, 5000);
}); 