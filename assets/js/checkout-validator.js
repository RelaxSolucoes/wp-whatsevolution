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
                    action: 'wpwevo_validate_phone',
                    nonce: wpwevoCheckout.nonce,
                    number: number
                },
                success: function(response) {
                    if (response.success) {
                        showValidationResult($field, true, 'Número válido');
                    } else {
                        showValidationResult($field, false, 'Número inválido');
                    }
                },
                error: function() {
                    showValidationResult($field, false, 'Erro na validação');
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
        
        // Remove mensagem após 3 segundos
        setTimeout(function() {
            $message.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }

    // Inicialização com múltiplas tentativas
    initializeCheckoutValidation();
    
    // Backup: tenta novamente após 2 segundos
    setTimeout(initializeCheckoutValidation, 2000);
    
    // Backup: tenta novamente após 5 segundos
    setTimeout(initializeCheckoutValidation, 5000);
}); 