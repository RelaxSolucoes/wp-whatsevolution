/**
 * JavaScript CORRIGIDO para o Quick Signup do WP WhatsApp Evolution
 * Versão otimizada e padronizada
 */

jQuery(document).ready(function($) {
    // 🔍 DEBUG: Verificar se o objeto está carregado
    console.log('🔧 Plugin inicializado');
    console.log('📦 wpwevo_quick_signup:', typeof wpwevo_quick_signup !== 'undefined' ? 'Carregado' : 'NÃO CARREGADO');
    if (typeof wpwevo_quick_signup !== 'undefined') {
        console.log('🔑 API Key disponível:', wpwevo_quick_signup.api_key ? 'SIM' : 'NÃO');
        console.log('🌐 AJAX URL:', wpwevo_quick_signup.ajax_url);
    }
    
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
    const $statusContainer = $('#wpwevo-status-container');

    // Variáveis de controle
    let statusCheckInterval = null;
    let retryCount = 0;
    const MAX_RETRIES = 3;

    /**
     * ✅ CORRIGIDO: Função unificada para verificar status inicial
     * Usa jQuery.ajax() consistentemente
     */
    function checkInitialStatus() {
        if (typeof wpwevo_quick_signup === 'undefined' || !wpwevo_quick_signup.api_key) {
            $statusContainer.hide();
            return;
        }

        $.ajax({
            url: wpwevo_quick_signup.ajax_url,
            type: 'POST',
            data: {
                action: 'wpwevo_check_plugin_status',
                nonce: wpwevo_quick_signup.nonce,
                api_key: wpwevo_quick_signup.api_key
            },
            success: function(response) {
                if (response.success && response.data) {
                    updateUserInterface(response.data);
                    syncStatusWithWordPress(response.data);
                } else {
                    showError(response.data ? response.data.message : 'Erro ao verificar status');
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro ao verificar status inicial:', error);
                showError('Erro de conexão ao verificar status');
            }
        });
    }

    /**
     * ✅ CORRIGIDO: Sincronização com WordPress
     */
    function syncStatusWithWordPress(statusData) {
        $.ajax({
            url: wpwevo_quick_signup.ajax_url,
            type: 'POST',
            data: {
                action: 'wpwevo_sync_status',
                nonce: wpwevo_quick_signup.nonce,
                status_data: statusData
            },
            success: function(response) {
                if (!response.success) {
                    console.warn('Falha ao sincronizar status com WordPress');
                }
            },
            error: function() {
                console.warn('Erro ao sincronizar status com WordPress');
            }
        });
    }

    // --- PONTO DE ENTRADA ---
    checkInitialStatus();

    // Formulário de quick signup
    $form.on('submit', function(e) {
        e.preventDefault();
        startQuickSignup();
    });

    // Botão retry
    $retryBtn.on('click', function() {
        retryCount = 0;
        hideAllContainers();
        $statusContainer.show();
        checkInitialStatus();
    });

    /**
     * ✅ CORRIGIDO: Função principal de quick signup
     */
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

        updateProgress(0, wpwevo_quick_signup.messages.validating);

        $.ajax({
            url: wpwevo_quick_signup.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success && response.data) {
                    updateProgress(3, wpwevo_quick_signup.messages.success);
                    // CORREÇÃO: Atualiza a API Key do objeto global se vier na resposta
                    if (response.data.api_key) {
                        wpwevo_quick_signup.api_key = response.data.api_key;
                    }
                    showSuccess(response.data);
                } else {
                    const errorMessage = response.data ? (response.data.message || response.data.error) : wpwevo_quick_signup.messages.error;
                    showError(errorMessage);
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = wpwevo_quick_signup.messages.error;
                
                if (xhr.responseText) {
                    try {
                        const errorData = JSON.parse(xhr.responseText);
                        if (errorData.data && errorData.data.message) {
                            errorMessage = errorData.data.message;
                        }
                    } catch (e) {
                        // Erro silencioso para produção
                    }
                }
                
                showError(errorMessage);
            }
        });
    }

    function updateProgress(step, message) {
        currentStep = step;
        const percentage = ((step + 1) / steps.length) * 100;
        
        $progressBar.css('width', percentage + '%');
        $progressText.text(message);
        
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
        
        if (data.dashboard_access) {
            $('#dashboard-url').attr('href', data.dashboard_access.url).text(data.dashboard_access.url);
            $('#dashboard-email').text(data.dashboard_access.email);
            $('#dashboard-password-value').text(data.dashboard_access.password);
            $('#dashboard-info').show();
        } else {
            $('#dashboard-info').hide();
        }
        
        // CORREÇÃO: Usar qr_code_base64 da resposta do quick-signup
        if (data.qr_code_base64) {
            const $qrContainer = $('#wpwevo-qr-container');
            if ($qrContainer.length) {
                $qrContainer.html(`<img src="data:image/png;base64,${data.qr_code_base64}" style="width: 300px; height: 300px;" alt="QR Code WhatsApp" title="QR Code de Conexão do WhatsApp">`);
                $qrContainer.show();
            }
        }
        
        // Inicia polling unificado com a nova API Key se disponível
        console.log('🎯 Quick signup concluído, iniciando polling...');
        startStatusPolling(data.api_key || wpwevo_quick_signup.api_key);
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
        $statusContainer.hide();
    }

    function resetContainers() {
        hideAllContainers();
        $form.show();
    }

    /**
     * ✅ CORRIGIDO: Função unificada de polling
     * Parâmetro apiKey opcional para diferentes contextos
     */
    function startStatusPolling(apiKey = null) {
        console.log('🚀 Iniciando polling de status...');
        
        // Para polling anterior
        if (statusCheckInterval) {
            console.log('🔄 Parando polling anterior...');
            clearInterval(statusCheckInterval);
            statusCheckInterval = null;
        }

        const keyToUse = apiKey || wpwevo_quick_signup.api_key;
        console.log('🔑 API Key para polling:', keyToUse ? keyToUse.substring(0, 10) + '...' : 'NÃO ENCONTRADA');
        
        if (!keyToUse) {
            console.error('❌ API Key não encontrada, não é possível iniciar polling');
            return;
        }

        console.log('⏱️ Configurando polling a cada 3 segundos...');
        
        // Polling de 3 segundos para detecção rápida
        statusCheckInterval = setInterval(function() {
            console.log('🔄 Executando verificação de status...');
            checkPluginStatus(keyToUse);
        }, 3000);
        
        // Primeira verificação imediatamente
        console.log('⚡ Primeira verificação imediata...');
        checkPluginStatus(keyToUse);
        
        // Timeout de segurança (5 minutos)
        setTimeout(function() {
            if (statusCheckInterval) {
                console.log('⏰ Timeout de 5 minutos atingido, parando polling');
                clearInterval(statusCheckInterval);
                statusCheckInterval = null;
            }
        }, 300000);
        
        console.log('✅ Polling iniciado com sucesso!');
    }

    /**
     * ✅ CORRIGIDO: Verificação de status usando jQuery.ajax()
     */
    function checkPluginStatus(apiKey) {
        if (!apiKey) {
            console.error('❌ API Key não fornecida para verificação de status');
            return;
        }

        console.log('📡 Enviando requisição AJAX para verificar status...');
        
        $.ajax({
            url: wpwevo_quick_signup.ajax_url,
            type: 'POST',
            data: {
                action: 'wpwevo_check_plugin_status',
                nonce: wpwevo_quick_signup.nonce,
                api_key: apiKey
            },
            success: function(response) {
                console.log('📥 Resposta recebida:', response);
                
                if (response.success && response.data) {
                    // CORREÇÃO: A Edge Function retorna dados diretamente, não dentro de 'instance'
                    const statusData = response.data;

                    // 🔍 DEBUG: Log dos dados recebidos
                    console.log('📱 Status recebido:', statusData);
                    console.log('🔗 whatsapp_connected:', statusData.whatsapp_connected);
                    console.log('📊 currentStatus:', statusData.currentStatus);
                    console.log('📋 qr_code:', statusData.qr_code ? 'presente' : 'null');

                    // ✅ CORREÇÃO: Verificar tanto whatsapp_connected quanto currentStatus
                    const isConnected = statusData.whatsapp_connected === true || statusData.currentStatus === 'connected';
                    
                    console.log('🎯 isConnected:', isConnected);

                    if (isConnected) {
                        // Conectado com sucesso
                        console.log('✅ WhatsApp conectado! Parando polling...');
                        stopPolling();
                        syncStatusWithWordPress(statusData);
                        updateUserInterface(statusData);
                        
                        // ✅ CORREÇÃO: Mostrar sucesso no container do QR
                        const $qrContainer = $('#wpwevo-qr-container');
                        if ($qrContainer.length) {
                            $qrContainer.html('<div style="width: 300px; height: 300px; display: flex; align-items: center; justify-content: center; background: #d1fae5; border: 1px solid #a7f3d0; color: #065f46; border-radius: 8px; text-align: center; padding: 15px;"><div>✅ WhatsApp Conectado!<br><small>Seu WhatsApp está pronto para uso</small></div></div>');
                            $qrContainer.show();
                        }
                    } else {
                        // Ainda não conectado, atualiza QR code
                        console.log('⏳ WhatsApp ainda não conectado, atualizando QR...');
                        displayQRCode(statusData);
                    }
                } else {
                    // Erro temporário, continua polling
                    console.log('⚠️ Erro na resposta, continuando polling...');
                    console.log('Resposta de erro:', response);
                    retryCount++;
                    if (retryCount >= MAX_RETRIES) {
                        console.error('❌ Máximo de tentativas atingido, parando polling');
                        stopPolling();
                        showError('Erro ao verificar status da instância');
                    } else {
                        console.log(`🔄 Tentativa ${retryCount}/${MAX_RETRIES}, continuando...`);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.log('❌ Erro de conexão:', error);
                console.log('Status:', status);
                console.log('XHR:', xhr);
                retryCount++;
                if (retryCount >= MAX_RETRIES) {
                    console.error('❌ Máximo de tentativas atingido, parando polling');
                    stopPolling();
                    showError('Erro de conexão ao verificar status');
                } else {
                    console.log(`🔄 Tentativa ${retryCount}/${MAX_RETRIES}, continuando...`);
                }
            }
        });
    }

    function stopPolling() {
        console.log('🛑 Parando polling de status...');
        if (statusCheckInterval) {
            clearInterval(statusCheckInterval);
            statusCheckInterval = null;
            console.log('✅ Polling parado com sucesso');
        } else {
            console.log('ℹ️ Nenhum polling ativo para parar');
        }
        retryCount = 0;
        console.log('🔄 Contador de tentativas resetado');
    }

    /**
     * ✅ CORRIGIDO: Exibição do QR Code
     */
    function displayQRCode(apiData) {
        const $qrContainer = $('#wpwevo-qr-container');
        
        if (!$qrContainer.length) {
            return;
        }

        // CORREÇÃO: Aceitar tanto qr_code (do polling) quanto qr_code_base64 (do signup)
        const qrCodeBase64 = apiData.qr_code || apiData.qr_code_base64;
        const isConnected = apiData.whatsapp_connected === true || apiData.currentStatus === 'connected';

        // ✅ CORREÇÃO: Se conectado, não mostrar QR Code
        if (isConnected) {
            console.log('✅ WhatsApp conectado, escondendo QR Code');
            $qrContainer.hide();
            return;
        }

        if (!qrCodeBase64) {
            console.log('⚠️ QR Code não disponível, mostrando mensagem de aguardo');
            $qrContainer.html('<div style="width: 300px; height: 300px; display: flex; align-items: center; justify-content: center; background: #f0f9ff; border: 1px solid #bae6fd; color: #0369a1; border-radius: 8px; text-align: center; padding: 15px;">⏳ Aguardando QR Code...<br><small>Verificando status da instância</small></div>');
            $qrContainer.show();
            return;
        }

        console.log('📱 Exibindo QR Code');
        $qrContainer.html(`<img src="data:image/png;base64,${qrCodeBase64}" style="width: 300px; height: 300px;" alt="QR Code WhatsApp" title="QR Code de Conexão do WhatsApp">`);
        $qrContainer.show();
    }

    // Máscara para WhatsApp
    $('#wpwevo-whatsapp').on('input', function() {
        let value = $(this).val().replace(/\D/g, '');
        
        if (value.length > 11) {
            value = value.substring(0, 11);
        }
        
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

    // Ativa/desativa botão de submit
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

    // ===== LÓGICA DO MODAL DE UPGRADE =====
    const upgradeModal = document.getElementById('wpwevo-upgrade-modal');
    const paymentFeedback = document.getElementById('wpwevo-payment-feedback');

    // Funções globais para onclick do HTML
    window.showUpgradeModal = function() {
        if (upgradeModal) {
            upgradeModal.style.display = 'block';
        }
    }

    window.closeUpgradeModal = function() {
        if (upgradeModal) {
            upgradeModal.style.display = 'none';
            stopPolling();
        }
    }

    window.createPayment = function() {
        const upgradeButton = upgradeModal.querySelector('.wpwevo-upgrade-btn');
        const originalButtonText = upgradeButton.innerHTML;
        upgradeButton.innerHTML = '⏳ Processando...';
        upgradeButton.disabled = true;

        paymentFeedback.style.display = 'none';

        $.ajax({
            url: wpwevo_quick_signup.ajax_url,
            type: 'POST',
            data: {
                action: 'wpwevo_create_payment',
                nonce: wpwevo_quick_signup.nonce
            },
            success: function(response) {
                if (response.success) {
                    const responseData = response.data;
                    
                    if (responseData.pix_qr_code_base64 && responseData.pix_copy_paste) {
                        // PIX
                        upgradeModal.querySelector('.wpwevo-modal-body').style.display = 'none';
                        document.getElementById('wpwevo-pix-payment-info').style.display = 'block';
                        document.getElementById('wpwevo-pix-qr-code').src = 'data:image/png;base64,' + responseData.pix_qr_code_base64;
                        document.getElementById('wpwevo-pix-copy-paste').value = responseData.pix_copy_paste;
                        
                        upgradeButton.style.display = 'none';
                        const cancelButton = upgradeModal.querySelector('.wpwevo-cancel-btn');
                        cancelButton.innerText = 'Fechar';

                        if (responseData.api_key) {
                            startStatusPolling(responseData.api_key);
                        }

                    } else if (responseData.payment_url && responseData.payment_url.startsWith('http')) {
                        // URL de redirecionamento
                        paymentFeedback.style.color = '#155724';
                        paymentFeedback.innerText = '✅ Sucesso! Redirecionando para pagamento...';
                        paymentFeedback.style.display = 'block';
                        
                        setTimeout(function() {
                            window.open(responseData.payment_url, '_blank');
                            closeUpgradeModal();
                        }, 1500);

                    } else {
                        // Erro
                        paymentFeedback.style.color = '#721c24';
                        paymentFeedback.innerText = '❌ Erro: Resposta de pagamento inválida.';
                        paymentFeedback.style.display = 'block';
                        upgradeButton.innerHTML = originalButtonText;
                        upgradeButton.disabled = false;
                    }

                } else {
                    paymentFeedback.style.color = '#721c24';
                    paymentFeedback.innerText = '❌ Erro: ' + (response.data ? response.data.message : 'Ocorreu um erro desconhecido.');
                    paymentFeedback.style.display = 'block';
                    upgradeButton.innerHTML = originalButtonText;
                    upgradeButton.disabled = false;
                }
            },
            error: function() {
                paymentFeedback.style.color = '#721c24';
                paymentFeedback.innerText = '❌ Erro de conexão. Verifique sua internet e tente novamente.';
                paymentFeedback.style.display = 'block';
                upgradeButton.innerHTML = originalButtonText;
                upgradeButton.disabled = false;
            }
        });
    };

    // Copiar código PIX
    $(document).on('click', '#wpwevo-copy-pix-btn', function() {
        const copyText = document.getElementById('wpwevo-pix-copy-paste');
        copyText.select();
        document.execCommand('copy');

        const btn = $(this);
        const originalText = btn.text();
        btn.text('Copiado!');
        setTimeout(function() {
            btn.text(originalText);
        }, 2000);
    });

    // Copiar senha
    $('#copy-password-btn').on('click', function() {
        const password = $('#dashboard-password-value').text();
        navigator.clipboard.writeText(password).then(function() {
            const $btn = $('#copy-password-btn');
            const originalText = $btn.html();
            $btn.html('<span class="dashicons dashicons-yes"></span> ' + wpwevo_quick_signup.messages.copied);
            setTimeout(function() {
                $btn.html(originalText);
            }, 2000);
        }, function(err) {
            console.error('Erro ao copiar senha: ', err);
        });
    });

    /**
     * ✅ CORRIGIDO: Atualização da interface de status
     * Função unificada e otimizada
     */
    function updateUserInterface(apiData) {
        const titleElement = $('#connection-status-message');
        const daysLeftElement = $('#trial-days-left-container');
        const mainContainer = $('#wpwevo-status-container');
        const renewalModal = $('#wpwevo-upgrade-modal');
        const expiredNotice = $('#wpwevo-trial-expired-notice');
        const upgradeButton = $('#wpwevo-upgrade-btn-from-status');

        if (apiData.trial_days_left > 0) {
            // Conta ativa
            if (apiData.user_plan === 'basic') {
                titleElement.text('Plano Basic');
                daysLeftElement.html(`Você tem <strong>${apiData.trial_days_left} dias</strong> restantes.`);
            } else if (apiData.user_plan === 'trial') {
                titleElement.text('Trial Ativo');
                daysLeftElement.html(`Você tem <strong>${apiData.trial_days_left} dias</strong> restantes.`);
            } else {
                const planName = apiData.user_plan ? apiData.user_plan.charAt(0).toUpperCase() + apiData.user_plan.slice(1) : 'Ativo';
                titleElement.text(`${planName} Ativo`);
                daysLeftElement.html(`Você tem <strong>${apiData.trial_days_left} dias</strong> restantes.`);
            }
            
            mainContainer.removeClass('status-expired').addClass('status-active');
            renewalModal.hide();
            expiredNotice.hide();
            upgradeButton.hide();
        } else {
            // Conta expirada
            titleElement.text('Assinatura Expirada');
            daysLeftElement.text('Faça upgrade para reativar sua conta.');
            mainContainer.removeClass('status-active').addClass('status-expired');
            expiredNotice.show();
            
            if (renewalModal.length && renewalModal.is(':hidden')) {
                renewalModal.show();
                if (typeof window.showUpgradeModal === 'function') {
                    window.showUpgradeModal();
                } else {
                    renewalModal[0].style.display = 'block';
                }
            }
            upgradeButton.show();
        }
    }
}); 