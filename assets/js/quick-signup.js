/**
 * JavaScript para o Quick Signup do WP WhatsApp Evolution
 */

jQuery(document).ready(function($) {
    // Estado do processo de signup
    let currentStep = 0;
    const steps = [
        'validating',
        'creating_account', 
        'configuring_plugin',
        'success'
    ];

    // Elementos DOM
    const $form = $('#wpwevo-quick-signup-form');
    const $progressContainer = $('#wpwevo-progress-container');
    const $progressBar = $('#wpwevo-progress-bar');
    const $progressText = $('#wpwevo-progress-text');
    const $successContainer = $('#wpwevo-success-container');
    const $errorContainer = $('#wpwevo-error-container');
    const $retryBtn = $('#wpwevo-retry-btn');
    const $qrContainer = $('#wpwevo-qr-container');

    // Formulário de quick signup
    $form.on('submit', function(e) {
        e.preventDefault();
        startQuickSignup();
    });

    // Botão retry
    $retryBtn.on('click', function() {
        resetForm();
        startQuickSignup();
    });

    // Polling para verificar status
    let statusCheckInterval;

    function startQuickSignup() {
        resetContainers();
        showProgress();
        
        const formData = {
            action: 'wpwevo_quick_signup',
            nonce: wpwevo_quick_signup.nonce,
            name: $('#wpwevo-name').val(),
            email: $('#wpwevo-email').val(),
            whatsapp: $('#wpwevo-whatsapp').val()
        };

        // Etapa 1: Validando dados
        updateProgress(0, wpwevo_quick_signup.messages.validating);

        $.ajax({
            url: wpwevo_quick_signup.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    // Etapa 2: Conta criada
                    updateProgress(1, wpwevo_quick_signup.messages.creating_account);
                    
                    setTimeout(function() {
                        saveConfiguration(response.data);
                    }, 1000);
                } else {
                    showError(response.data.message || wpwevo_quick_signup.messages.error);
                }
            },
            error: function() {
                showError(wpwevo_quick_signup.messages.error);
            }
        });
    }

    function saveConfiguration(signupData) {
        // Etapa 3: Configurando plugin
        updateProgress(2, wpwevo_quick_signup.messages.configuring_plugin);

        const configData = {
            action: 'wpwevo_save_quick_config',
            nonce: wpwevo_quick_signup.nonce,
            api_url: signupData.api_url,
            api_key: signupData.api_key,
            instance_name: signupData.instance_name,
            trial_expires_at: signupData.trial_expires_at
        };

        $.ajax({
            url: wpwevo_quick_signup.ajax_url,
            type: 'POST',
            data: configData,
            success: function(response) {
                if (response.success) {
                    // Etapa 4: Sucesso
                    updateProgress(3, wpwevo_quick_signup.messages.success);
                    
                    setTimeout(function() {
                        showSuccess(signupData);
                    }, 1000);
                } else {
                    showError(response.data.message || wpwevo_quick_signup.messages.error);
                }
            },
            error: function() {
                showError(wpwevo_quick_signup.messages.error);
            }
        });
    }

    function updateProgress(step, message) {
        currentStep = step;
        const percentage = ((step + 1) / steps.length) * 100;
        
        $progressBar.css('width', percentage + '%');
        $progressText.text(message);
        
        // Atualiza indicadores visuais dos steps
        $('.wpwevo-step').each(function(index) {
            const $step = $(this);
            if (index <= step) {
                $step.addClass('active');
            } else {
                $step.removeClass('active');
            }
            
            if (index < step) {
                $step.addClass('completed');
            }
        });
    }

    function showProgress() {
        hideAllContainers();
        $progressContainer.show();
    }

    function showSuccess(data) {
        hideAllContainers();
        $successContainer.show();
        
        // Preenche dados do sucesso
        $('#trial-days-left').text(data.trial_days_left || 7);
        
        // Configura QR Code se disponível
        if (data.qr_code_url) {
            $('#wpwevo-qr-iframe').attr('src', data.qr_code_url);
            $qrContainer.show();
        }
        
        // Inicia polling para verificar conexão do WhatsApp
        startStatusPolling();
    }

    function showError(message) {
        hideAllContainers();
        $errorContainer.show();
        $('#wpwevo-error-message').text(message);
    }

    function hideAllContainers() {
        $progressContainer.hide();
        $successContainer.hide();
        $errorContainer.hide();
        $form.hide();
    }

    function resetContainers() {
        hideAllContainers();
        $form.show();
    }

    function resetForm() {
        currentStep = 0;
        clearInterval(statusCheckInterval);
        resetContainers();
        
        // Reset progress indicators
        $('.wpwevo-step').removeClass('active completed');
        $progressBar.css('width', '0%');
        $progressText.text('');
    }

    function startStatusPolling() {
        // Verifica status a cada 10 segundos
        statusCheckInterval = setInterval(function() {
            checkPluginStatus();
        }, 10000);
        
        // Primeira verificação imediatamente
        checkPluginStatus();
    }

    function checkPluginStatus() {
        $.ajax({
            url: wpwevo_quick_signup.ajax_url,
            type: 'POST',
            data: {
                action: 'wpwevo_check_plugin_status',
                nonce: wpwevo_quick_signup.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    const status = response.data.data;
                    
                    // Atualiza dias restantes
                    if (status.trial_days_left !== undefined) {
                        $('#trial-days-left').text(status.trial_days_left);
                    }
                    
                    // Atualiza status de conexão
                    if (status.whatsapp_connected) {
                        $('#whatsapp-status').removeClass('disconnected').addClass('connected');
                        $('#connection-indicator').text('✅ WhatsApp Conectado');
                        
                        // Para o polling quando conectado
                        clearInterval(statusCheckInterval);
                        
                        // Mostra próximos passos
                        $('#next-steps').show();
                    } else {
                        $('#whatsapp-status').removeClass('connected').addClass('disconnected');
                        $('#connection-indicator').text('⏳ Aguardando conexão...');
                    }
                }
            },
            error: function() {
                console.log('Erro ao verificar status do plugin');
            }
        });
    }

    // Máscara para WhatsApp
    $('#wpwevo-whatsapp').on('input', function() {
        let value = $(this).val().replace(/\D/g, '');
        
        // Limita a 11 dígitos (formato brasileiro)
        if (value.length > 11) {
            value = value.substring(0, 11);
        }
        
        // Formata conforme o usuário digita
        if (value.length >= 2) {
            value = '(' + value.substring(0, 2) + ') ' + value.substring(2);
        }
        if (value.length >= 10) {
            value = value.substring(0, 10) + '-' + value.substring(10);
        }
        
        $(this).val(value);
    });

    // Validação de email em tempo real
    $('#wpwevo-email').on('blur', function() {
        const email = $(this).val();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (email && !emailRegex.test(email)) {
            $(this).addClass('error');
            $('#email-error').text('Email inválido').show();
        } else {
            $(this).removeClass('error');
            $('#email-error').hide();
        }
    });

    // Validação de campos obrigatórios
    function validateForm() {
        let isValid = true;
        
        $form.find('input[required]').each(function() {
            const $field = $(this);
            const value = $field.val().trim();
            
            if (!value) {
                $field.addClass('error');
                isValid = false;
            } else {
                $field.removeClass('error');
            }
        });
        
        return isValid;
    }

    // Ativa/desativa botão de submit baseado na validação
    $form.find('input').on('input', function() {
        const isValid = validateForm();
        $('#wpwevo-signup-btn').prop('disabled', !isValid);
    });

    // Link para upgrade
    $(document).on('click', '#upgrade-link', function(e) {
        e.preventDefault();
        window.open('https://whats-evolution.vercel.app/', '_blank');
    });

    // Auto-focus no primeiro campo
    $('#wpwevo-name').focus();
}); 