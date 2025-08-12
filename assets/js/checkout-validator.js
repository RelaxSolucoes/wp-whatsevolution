/**
 * Frontend script for WhatsApp number validation on checkout
 * Otimizado para compatibilidade com Cart Abandonment Recovery
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
            
            // Valida APENAS campos de telefone. EXCLUI cpf/cnpj e similares
            const $phoneFields = $(
                'input[name="billing_phone"], input[name="shipping_phone"], input[type="tel"], input[name*="phone" i]'
            ).filter(function() {
                const name = (this.name || '').toLowerCase();
                const id = (this.id || '').toLowerCase();
                const placeholder = (this.placeholder || '').toLowerCase();
                // Excluir campos de CPF/CNPJ/Documento
                const blacklist = ['cpf', 'cnpj', 'document', 'documento', 'taxvat', 'vat', 'ssn'];
                return !blacklist.some(b => name.includes(b) || id.includes(b) || placeholder.includes(b));
            });
            
            if ($phoneFields.length > 0) {
                isInitialized = true;
                
                $phoneFields.each(function() {
                    const $field = $(this);
                    const fieldId = $field.attr('id') || 'phone_' + Math.random().toString(36).substr(2, 9);
                    
                    if (!$field.attr('id')) {
                        $field.attr('id', fieldId);
                    }
                    
                    // SOLUÇÃO INTELIGENTE: Usa eventos que não interferem no Cart Abandonment Recovery
                    // 1. Validação no blur (quando sai do campo) - não interfere no rastreamento
                    $field.on('blur.wpwevo', function() {
                        const rawValue = $(this).val();
                        const number = rawValue.replace(/[^0-9]/g, '');
                        
                        if (number.length >= 10) {
                            validatePhoneNumber(number, $field);
                        }
                    });
                    
                    // 2. Validação com debounce no input (sem modificar o valor)
                    let inputTimeout;
                    $field.on('input.wpwevo', function() {
                        clearTimeout(inputTimeout);
                        const rawValue = $(this).val();
                        const number = rawValue.replace(/[^0-9]/g, '');
                        
                        // Só valida se tiver pelo menos 10 dígitos
                        if (number.length >= 10) {
                            inputTimeout = setTimeout(function() {
                                validatePhoneNumber(number, $field);
                            }, 2000); // 2 segundos de debounce
                        }
                    });
                    
                    // 3. Validação inicial se o campo já tem valor (sem modificar)
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
        
        // MutationObserver menos agressivo - só observa, não interfere
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
                }, 1000); // Aumentado para ser menos agressivo
            }
        });
        
        // Observa mudanças no DOM (menos agressivo)
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    function validatePhoneNumber(number, $field) {
        clearTimeout($field.data('validationTimeout'));
        $field.removeData('apiValidated');
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
                    $field.data('apiValidated', true);
                    if (response.success) {
                        showValidationResult($field, true, wpwevoCheckout.validation_success);
                    } else {
                        showValidationResult($field, false, wpwevoCheckout.validation_error);
                        // Modal só aparece DEPOIS da resposta da API e apenas se for inválido
                        if (wpwevoCheckout.show_modal === 'yes') {
                            showInvalidNumberModal();
                        }
                    }
                },
                error: function(xhr) {
                    $field.data('apiValidated', true);
                    // Em caso de erro na API, mostra mensagem de erro personalizada, mas NUNCA mostra modal
                    showValidationResult($field, false, wpwevoCheckout.validation_error);
                }
            });
        }, 2000);
        $field.data('validationTimeout', timeout);
    }

    function showValidationResult($field, isValid, message) {
        $field.removeClass('wpwevo-valid wpwevo-invalid');
        $field.siblings('.wpwevo-validation-message').remove();
        const className = isValid ? 'wpwevo-valid' : 'wpwevo-invalid';
        const messageClass = isValid ? 'wpwevo-validation-success' : 'wpwevo-validation-error';
        $field.addClass(className);
        const $message = $('<div class="wpwevo-validation-message ' + messageClass + '">' + message + '</div>');
        $field.after($message);
        // Não apaga mais automaticamente!
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

    // Inicialização com múltiplas tentativas (reduzido para ser menos agressivo)
    initializeCheckoutValidation();
    
    // Backup: tenta novamente após 3 segundos (aumentado)
    setTimeout(initializeCheckoutValidation, 3000);
    
    // Backup: tenta novamente após 8 segundos (aumentado)
    setTimeout(initializeCheckoutValidation, 8000);
}); 