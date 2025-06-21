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
                console.log('=== RESPOSTA QUICK SIGNUP ===');
                console.log('Response completa:', response);
                
                if (response.success && response.data) {
                    console.log('response.data:', response.data);
                    
                    // Ação 1: Salva a configuração em segundo plano.
                    saveConfiguration(response.data);
                    
                    // Ação 2: Mostra a tela de sucesso e o QR Code IMEDIATAMENTE.
                    // A função showSuccess já chama displayQRCode e startStatusPolling.
                    showSuccess(response.data);

                } else {
                    console.error('Erro no quick signup:', response);
                    const errorMessage = response.data ? (response.data.message || response.data.error) : wpwevo_quick_signup.messages.error;
                    console.log('Mensagem de erro final:', errorMessage);
                    showError(errorMessage);
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro AJAX quick signup:', {xhr, status, error});
                console.error('Response text:', xhr.responseText);
                
                let errorMessage = wpwevo_quick_signup.messages.error;
                
                // Tenta extrair mensagem específica do erro
                if (xhr.responseText) {
                    try {
                        const errorData = JSON.parse(xhr.responseText);
                        if (errorData.data && errorData.data.message) {
                            errorMessage = errorData.data.message;
                        }
                    } catch (e) {
                        console.log('Não foi possível parsear erro JSON');
                    }
                }
                
                showError(errorMessage);
            }
        });
    }

    function saveConfiguration(signupData) {
        // Etapa 3: Configurando plugin
        updateProgress(2, wpwevo_quick_signup.messages.configuring_plugin);

        console.log('=== DADOS PARA CONFIGURAÇÃO ===');
        console.log('signupData completo:', signupData);
        console.log('api_url:', signupData.api_url);
        console.log('api_key:', signupData.api_key);
        console.log('instance_name:', signupData.instance_name);
        console.log('trial_expires_at:', signupData.trial_expires_at);
        console.log('===============================');

        const configData = {
            action: 'wpwevo_save_quick_config',
            nonce: wpwevo_quick_signup.nonce,
            api_url: signupData.api_url,
            api_key: signupData.api_key,
            instance_name: signupData.instance_name,
            trial_expires_at: signupData.trial_expires_at
        };
        
        console.log('Dados sendo enviados para salvar:', configData);

        $.ajax({
            url: wpwevo_quick_signup.ajax_url,
            type: 'POST',
            data: configData,
            success: function(response) {
                if (response.success) {
                    // Etapa 4: Sucesso no salvamento. Apenas log, sem ação na UI.
                    updateProgress(3, wpwevo_quick_signup.messages.success);
                    console.log('✅ Configuração salva com sucesso em segundo plano.');
                } else {
                    // Loga erro de salvamento, mas não interfere na UI principal.
                    console.error('Falha ao salvar a configuração em segundo plano:', response.data.message);
                }
            },
            error: function() {
                console.error('Erro AJAX ao salvar a configuração em segundo plano.');
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
        
        // ✅ CHAMA A FUNÇÃO DEDICADA PARA MOSTRAR O QR CODE
        displayQRCode(data);
        
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
        // Verifica status a cada 3 segundos para detecção rápida
        statusCheckInterval = setInterval(function() {
            checkPluginStatus();
        }, 3000);
        
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
                console.log('🔍 Status check response:', response.success ? '✅' : '❌');
                
                if (response.success && response.data) {
                    const status = response.data;
                    console.log('📊 WhatsApp conectado:', status.whatsapp_connected, '| Status:', status.status);
                    
                    // Atualiza dias restantes
                    if (status.trial_days_left !== undefined) {
                        $('#trial-days-left').text(status.trial_days_left);
                    }
                    
                    // ✅ USE APENAS whatsapp_connected (campo confiável da nova API)
                    if (status.whatsapp_connected === true) {
                        console.log('✅ WhatsApp CONECTADO! Parando polling.');
                        $('#whatsapp-status').removeClass('disconnected').addClass('connected');
                        $('#connection-indicator').text('✅ WhatsApp Conectado');
                        
                        // Para o polling quando conectado
                        clearInterval(statusCheckInterval);
                        
                        // Mostra próximos passos
                        $('#next-steps').show();
                        
                        // Substitui QR code por mensagem de sucesso
                        if ($('#wpwevo-qr-image').length || $('#wpwevo-qr-iframe').length) {
                            $('#wpwevo-qr-image, #wpwevo-qr-iframe').replaceWith(`
                                <div style="width: 300px; height: 300px; display: flex; align-items: center; justify-content: center; 
                                     background: linear-gradient(135deg, #e8f5e8, #d4edda); border-radius: 12px; color: #155724; 
                                     flex-direction: column; border: 2px solid #c3e6cb;">
                                    <div style="font-size: 48px; margin-bottom: 15px;">✅</div>
                                    <div style="font-weight: bold; font-size: 18px; margin-bottom: 8px;">WhatsApp Conectado!</div>
                                    <div style="font-size: 14px; color: #0f5132;">Pronto para usar</div>
                                </div>
                            `);
                        }
                        
                        // Atualiza a página após 3 segundos para mostrar a nova interface
                        setTimeout(function() {
                            console.log('🔄 Atualizando página para mostrar interface completa...');
                            window.location.reload();
                        }, 3000);
                    } else {
                        $('#whatsapp-status').removeClass('connected').addClass('disconnected');
                        
                        // Mostra mensagem baseada no status atual
                        if (status.display_message) {
                            $('#connection-indicator').text('⏳ ' + status.display_message);
                        } else {
                            $('#connection-indicator').text('📱 Escaneie o QR Code com seu WhatsApp');
                        }
                    }
                } else {
                    console.error('❌ Erro na resposta do status check');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Erro AJAX:', {xhr, status, error});
            }
        });
    }

    /**
     * Mostra o QR Code na tela usando a imagem base64 da API.
     * @param {object} apiData - O objeto 'data' da resposta da API (quick-signup).
     */
    function displayQRCode(apiData) {
        // 1. Encontre o elemento no HTML onde o QR Code deve aparecer.
        const $qrContainer = $('#wpwevo-qr-container');

        // Se o container não for encontrado, não faz nada.
        if (!$qrContainer.length) {
            console.error('ERRO CRÍTICO: O container para o QR Code (#wpwevo-qr-container) não foi encontrado na página.');
            return;
        }

        // 2. Pegue o QR Code base64.
        const qrCodeBase64 = apiData.qr_code_base64;

        if (!qrCodeBase64) {
            console.error("ERRO CRÍTICO: 'qr_code_base64' não foi encontrado na resposta da API.");
            $qrContainer.html('<div style="width: 300px; height: 300px; display: flex; align-items: center; justify-content: center; background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; border-radius: 8px; text-align: center; padding: 15px;">❌ Erro:<br>O QR Code não foi recebido do servidor.</div>');
            $qrContainer.show();
            return;
        }

        // 3. Insira a imagem base64 diretamente.
        $qrContainer.html(`<img src="${qrCodeBase64}" style="width: 300px; height: 300px;" alt="QR Code WhatsApp" title="QR Code de Conexão do WhatsApp">`);
        $qrContainer.show();
        console.log("✅ QR Code inserido na tela usando base64");
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