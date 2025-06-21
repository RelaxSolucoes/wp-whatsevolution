/**
 * Frontend script for WhatsApp number validation on checkout
 */
jQuery(document).ready(function($) {
    'use strict';

    // Debug
    if (wpwevoCheckout.debug) {
        console.log('WP WhatsApp Evolution: Initializing checkout validation');
    }

    // Variável para controlar se o usuário confirmou prosseguir sem WhatsApp
    let userConfirmedNonWhatsApp = false;
    
    // Controle de timeout de digitação
    let typingTimer;
    const doneTypingInterval = 1500; // Aumentado para 1.5s para evitar conflitos
    
    // Controle para evitar múltiplas inicializações
    let isInitialized = false;
    let initializationTimer;

    function initializeValidation() {
        // Evita múltiplas execuções simultâneas
        if (isInitialized) {
            return;
        }
        
        // Debounce para evitar múltiplas chamadas
        clearTimeout(initializationTimer);
        initializationTimer = setTimeout(function() {
            performInitialization();
        }, 100);
    }

    function performInitialization() {
        // Cache fields - incluindo campos do WooCommerce Blocks E SHIPPING
        var $phoneFields = $(
            '.wc-block-checkout__billing-fields input[type="tel"], ' +
            '.wc-block-checkout__shipping-fields input[type="tel"], ' +
            'input[name="billing-phone"], ' +
            'input[name="billing_phone"], ' +
            'input[name="billing_cellphone"], ' +
            'input[name="shipping-phone"], ' +
            'input[name="shipping_phone"], ' +
            'input[id*="phone"], ' +
            'input[id*="telefone"], ' +
            'input[class*="phone"], ' +
            'input[type="tel"]'
        );
        
        // Debug field detection
        if (wpwevoCheckout.debug) {
            console.log('Phone fields found:', $phoneFields.length);
            $phoneFields.each(function() {
                console.log('Field found:', {
                    id: $(this).attr('id'),
                    name: $(this).attr('name'),
                    type: $(this).attr('type'),
                    classes: $(this).attr('class'),
                    value: $(this).val()
                });
            });
        }

        // Se não encontrou campos, tenta novamente em 1 segundo
        if ($phoneFields.length === 0) {
            if (wpwevoCheckout.debug) {
                console.log('No fields found, will try again in 1 second');
            }
            isInitialized = false; // Permite nova tentativa
            setTimeout(initializeValidation, 1000);
            return;
        }
        
        // Marca como inicializado
        isInitialized = true;

        // Adiciona o modal se estiver habilitado
        if (wpwevoCheckout.show_modal === 'yes' && $('#wpwevo-confirmation-modal').length === 0) {
            $('body').append(`
                <div id="wpwevo-confirmation-modal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.7); overflow:auto;">
                    <div style="position:relative; background-color:#fff; margin:10% auto; padding:20px; border-radius:5px; width:80%; max-width:500px;">
                        <span id="wpwevo-close-modal" style="position:absolute; right:15px; top:10px; font-size:22px; cursor:pointer;">&times;</span>
                        <h3 style="margin-top:0; color:#333;">${wpwevoCheckout.modal_title}</h3>
                        <p>${wpwevoCheckout.modal_message}</p>
                        <div style="text-align:right; margin-top:20px;">
                            <button id="wpwevo-proceed-order" style="background-color:#4CAF50; color:#fff; border:none; padding:8px 15px; border-radius:3px; cursor:pointer;">
                                ${wpwevoCheckout.modal_button_text}
                            </button>
                        </div>
                    </div>
                </div>
            `);

            // Eventos do modal
            $('#wpwevo-close-modal').on('click', function() {
                $('#wpwevo-confirmation-modal').hide();
            });

            $('#wpwevo-proceed-order').on('click', function() {
                userConfirmedNonWhatsApp = true;
                $('#wpwevo-confirmation-modal').hide();
            });
        }

        // Add validation class to all fields
        $phoneFields.addClass('wpwevo-phone-field');

        // Adiciona div de feedback para cada campo
        $phoneFields.each(function() {
            var $field = $(this);
            var fieldId = $field.attr('id') || $field.attr('name').replace(/[^a-zA-Z0-9]/g, '_');
            
            if ($('#wpwevo-validation-' + fieldId).length === 0) {
                $field.after('<div id="wpwevo-validation-' + fieldId + '" class="wpwevo-validation-feedback"></div>');
            }
        });

        // Handle field changes
        $phoneFields.on('keyup input paste', function(e) {
            var $field = $(this);
            var fieldId = $field.attr('id') || $field.attr('name').replace(/[^a-zA-Z0-9]/g, '_');
            var $feedback = $('#wpwevo-validation-' + fieldId);

            // Clear previous timeout
            clearTimeout(typingTimer);

            // Reset validation state
            userConfirmedNonWhatsApp = false;
            $field.removeClass('wpwevo-valid wpwevo-invalid wpwevo-validating');

            // Get formatted number - NÃO modifica o campo, apenas extrai números
            var rawValue = $field.val();
            var number = rawValue.replace(/\D/g, '');
            
            // If number is too short, clear feedback
            if (number.length < 8) {
                $feedback.empty();
                return;
            }

            // Show checking message
            $feedback.html('<em>Verificando número...</em>');
            $field.addClass('wpwevo-validating');

            // Set validation timeout
            typingTimer = setTimeout(function() {
                validateNumber(number, $field, $feedback);
            }, doneTypingInterval);
        });

        function validateNumber(number, $field, $feedback) {
            if (wpwevoCheckout.debug) {
                console.log('Validating number:', number);
            }

            $.ajax({
                url: wpwevoCheckout.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpwevo_validate_checkout_number',
                    number: number,
                    nonce: wpwevoCheckout.nonce
                },
                success: function(response) {
                    if (wpwevoCheckout.debug) {
                        console.log('Validation response:', response);
                    }

                    $field.removeClass('wpwevo-validating');
                    
                    if (response.success) {
                        if (response.data && response.data.is_whatsapp) {
                            $field.addClass('wpwevo-valid').removeClass('wpwevo-invalid');
                            let msg = wpwevoCheckout.validation_success;
                            if (response.data.name) {
                                msg += '<div><small>Nome: ' + response.data.name + '</small></div>';
                            }
                            $feedback.html('<span style="color:#4CAF50;">' + msg + '</span>');
                        } else {
                            $field.addClass('wpwevo-invalid').removeClass('wpwevo-valid');
                            $feedback.html('<span style="color:#f44336;">' + wpwevoCheckout.validation_error + '</span>');
                            if (wpwevoCheckout.show_modal === 'yes' && !userConfirmedNonWhatsApp) {
                                $('#wpwevo-confirmation-modal').show();
                            }
                        }
                    } else {
                        $field.addClass('wpwevo-invalid').removeClass('wpwevo-valid');
                        let errorMsg = response.message || wpwevoCheckout.validation_error;
                        $feedback.html('<span style="color:#f44336;">' + errorMsg + '</span>');
                        if (wpwevoCheckout.show_modal === 'yes' && !userConfirmedNonWhatsApp) {
                            $('#wpwevo-confirmation-modal').show();
                        }
                    }
                },
                error: function(xhr, status, error) {
                    if (wpwevoCheckout.debug) {
                        console.error('Validation error:', status, error);
                    }
                    $field.removeClass('wpwevo-validating wpwevo-valid wpwevo-invalid');
                    $feedback.empty(); // Remove qualquer mensagem de feedback
                }
            });
        }

        // Valida número inicial se já estiver preenchido
        $phoneFields.each(function() {
            var $field = $(this);
            var number = $field.val().replace(/\D/g, '');
            if (number.length >= 8) {
                if (wpwevoCheckout.debug) {
                    console.log('Initial validation for field:', $field.attr('id'));
                }
                validateNumber(
                    number, 
                    $field, 
                    $('#wpwevo-validation-' + ($field.attr('id') || $field.attr('name').replace(/[^a-zA-Z0-9]/g, '_')))
                );
            }
        });
    }

    // Inicia a validação
    initializeValidation();

    // Observa mudanças no DOM para campos que podem ser adicionados dinamicamente
    // Mas com controle para evitar spam de requests
    let mutationTimer;
    var observer = new MutationObserver(function(mutations) {
        // Debounce para evitar múltiplas execuções
        clearTimeout(mutationTimer);
        mutationTimer = setTimeout(function() {
            let hasNewPhoneFields = false;
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length) {
                    // Verifica se realmente há novos campos de telefone
                    for (let node of mutation.addedNodes) {
                        if (node.nodeType === 1) { // Element node
                            let phoneFields = $(node).find('input[type="tel"], input[name*="phone"]');
                            if (phoneFields.length > 0) {
                                hasNewPhoneFields = true;
                                break;
                            }
                        }
                    }
                }
            });
            
            if (hasNewPhoneFields) {
                isInitialized = false; // Permite reinicialização
                initializeValidation();
            }
        }, 500); // Debounce de 500ms
    });

    // Observa apenas o formulário de checkout
    var $checkoutForm = $('form.checkout, .wc-block-checkout__form');
    if ($checkoutForm.length) {
        observer.observe($checkoutForm[0], {
            childList: true,
            subtree: true
        });
    }

    // Intercepta o envio do formulário
    $('form.checkout, .wc-block-checkout__form').on('checkout_place_order submit', function() {
        var hasInvalidNumber = false;
        var $invalidField = null;

        $('input.wpwevo-phone-field').each(function() {
            var $field = $(this);
            if ($field.hasClass('wpwevo-invalid')) {
                hasInvalidNumber = true;
                $invalidField = $field;
                return false; // break loop
            }
        });

        if (hasInvalidNumber && !userConfirmedNonWhatsApp) {
            if (wpwevoCheckout.show_modal === 'yes') {
                $('#wpwevo-confirmation-modal').show();
                $invalidField.focus();
                return false;
            }
        }

        return true;
    });

    // Debug de inicialização completa
    if (wpwevoCheckout.debug) {
        console.log('WP WhatsApp Evolution: Checkout validation initialized');
    }
}); 