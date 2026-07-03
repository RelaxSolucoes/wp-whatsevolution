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
                        
                        // ✅ NOVO: Fechar container temporário automaticamente
                        $('#wpwevo-qr-container-temp').fadeOut(500, function() {
                            $(this).remove(); // Remove o elemento do DOM
                        });
                        console.log('✅ Container temporário fechado automaticamente');
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

        // O polling de pagamento é independente: ele só para quando o pagamento
        // é aprovado/cancelado ou no próprio timeout. Parar aqui fazia a UI
        // perder a aprovação quando o WhatsApp conectava durante o pagamento.
    }

    // ✅ Polling de status de pagamento (via admin-ajax, proxy para a API managed)
    let paymentStatusInterval = null;

    function startPaymentStatusPolling(externalReference) {
        console.log('💳 Iniciando polling de status de pagamento...');

        // Parar polling anterior se existir
        if (paymentStatusInterval) {
            clearInterval(paymentStatusInterval);
        }

        // Polling a cada 3 segundos
        paymentStatusInterval = setInterval(function() {
            checkPaymentStatus(externalReference);
        }, 3000);

        // Timeout de 5 minutos
        setTimeout(() => {
            if (paymentStatusInterval) {
                clearInterval(paymentStatusInterval);
                console.log('⏰ Timeout do polling de pagamento');
            }
        }, 300000); // 5 minutos
    }

    function stopPaymentStatusPolling() {
        if (paymentStatusInterval) {
            clearInterval(paymentStatusInterval);
            paymentStatusInterval = null;
            console.log('🛑 Polling de pagamento parado');
        }
    }

    function checkPaymentStatus(externalReference) {
        $.ajax({
            url: wpwevo_quick_signup.ajax_url,
            type: 'POST',
            data: {
                action: 'wpwevo_check_payment_status',
                nonce: wpwevo_quick_signup.nonce,
                external_reference: externalReference
            },
            success: function(response) {
                if (!response.success || !response.data) {
                    console.log('⚠️ Erro na verificação do pagamento, continuando polling...');
                    return;
                }

                const data = response.data;
                console.log('📊 Status do pagamento:', data.payment_status);

                if (data.payment_approved) {
                    console.log('✅ Pagamento aprovado!');
                    stopPaymentStatusPolling();
                    localStorage.removeItem('wpwevo_current_payment');
                    showPaymentSuccessMessage(data.display_message);
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                    return;
                }

                if (data.payment_status === 'cancelled') {
                    console.log('❌ Pagamento cancelado');
                    stopPaymentStatusPolling();
                    localStorage.removeItem('wpwevo_current_payment');
                    showPaymentErrorMessage('Pagamento cancelado. Tente novamente.');
                }
                // pending: continua o polling
            },
            error: function(xhr, status, error) {
                console.error('❌ Erro na verificação de status:', error);
                // Continuar tentando na próxima iteração
            }
        });
    }

    function showPaymentSuccessMessage(message) {
        // Substituir QR Code por mensagem de sucesso
        const pixPaymentInfo = document.getElementById('wpwevo-pix-payment-info');
        if (pixPaymentInfo) {
            pixPaymentInfo.innerHTML = `
                <div style="text-align: center; padding: 20px;">
                    <div style="color: #155724; font-size: 48px; margin-bottom: 15px;">✅</div>
                    <h3 style="color: #155724; margin-bottom: 10px;">Pagamento Aprovado!</h3>
                    <p style="color: #155724; margin-bottom: 15px;">${message}</p>
                    <p style="color: #6c757d; font-size: 14px;">Redirecionando...</p>
                </div>
            `;
        }
    }

    function showPaymentErrorMessage(message) {
        const pixPaymentInfo = document.getElementById('wpwevo-pix-payment-info');
        if (pixPaymentInfo) {
            pixPaymentInfo.innerHTML = `
                <div style="text-align: center; padding: 20px;">
                    <div style="color: #721c24; font-size: 48px; margin-bottom: 15px;">❌</div>
                    <h3 style="color: #721c24; margin-bottom: 10px;">Erro no Pagamento</h3>
                    <p style="color: #721c24; margin-bottom: 15px;">${message}</p>
                    <button onclick="window.createPayment()" style="background: #007cba; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
                        Tentar Novamente
                    </button>
                </div>
            `;
        }
    }

    /**
     * ✅ SOLUÇÃO DEFINITIVA: Exibição inteligente do QR Code
     * Cria container temporário sempre visível
     */
    function displayQRCode(apiData) {
        // ✅ CORREÇÃO CRÍTICA: NÃO mostrar QR Code se conta expirada
        if (apiData.trial_days_left <= 0 || apiData.isTrialExpired) {
            console.log('❌ Conta expirada - NÃO mostrar QR Code de conexão');
            $('#wpwevo-qr-container-temp').hide();
            return;
        }
        
        // ✅ SOLUÇÃO ALTERNATIVA: Criar container temporário sempre visível
        let $qrContainer = $('#wpwevo-qr-container-temp');
        
        // ✅ Se não existir, criar o container temporário
        if (!$qrContainer.length) {
            console.log('🔧 Criando container temporário para QR Code...');
            $qrContainer = $('<div id="wpwevo-qr-container-temp" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); z-index: 99999; border: 2px solid #48bb78;"></div>');
            $('body').append($qrContainer);
        }

        console.log('🔍 Container temporário criado/encontrado, visibilidade:', $qrContainer.is(':visible'));

        // CORREÇÃO: Aceitar tanto qr_code (do polling) quanto qr_code_base64 (do signup)
        const qrCodeBase64 = apiData.qr_code || apiData.qr_code_base64;
        const qrCodeUrl = apiData.qr_code_url;
        const isConnected = apiData.whatsapp_connected === true || apiData.currentStatus === 'connected';

        // ✅ CORREÇÃO: Se conectado, esconder QR Code
        if (isConnected) {
            console.log('✅ WhatsApp conectado, escondendo QR Code');
            $qrContainer.hide();
            
            // ✅ NOVO: Fechar container temporário automaticamente
            $('#wpwevo-qr-container-temp').fadeOut(500, function() {
                $(this).remove(); // Remove o elemento do DOM
            });
            console.log('✅ Container temporário fechado automaticamente');
            return;
        }

        // ✅ MELHORADO: Priorizar QR Code base64 (funciona perfeitamente)
        if (qrCodeBase64) {
            console.log('📱 Exibindo QR Code via base64 (container temporário)');
            $qrContainer.html(`
                <div style="text-align: center;">
                    <h3 style="margin: 0 0 15px 0; color: #2d3748;">📱 Conecte seu WhatsApp</h3>
                    <div style="background: #f7fafc; padding: 20px; border-radius: 10px; display: inline-block;">
                        <img src="data:image/png;base64,${qrCodeBase64}" style="width: 300px; height: 300px;" alt="QR Code WhatsApp" title="QR Code de Conexão do WhatsApp">
                    </div>
                    <p style="margin: 10px 0 0 0; color: #4a5568; font-size: 14px;">
                        <span id="connection-indicator">⏳ Aguardando conexão...</span>
                    </p>
                    <button onclick="$('#wpwevo-qr-container-temp').hide();" style="margin-top: 15px; padding: 8px 16px; background: #e53e3e; color: white; border: none; border-radius: 5px; cursor: pointer;">✕ Fechar</button>
                </div>
            `);
            
            // ✅ GARANTIR VISIBILIDADE
            $qrContainer.show().css({
                'display': 'block',
                'visibility': 'visible',
                'opacity': '1',
                'z-index': '99999'
            });
            console.log('✅ Container temporário agora visível:', $qrContainer.is(':visible'));
            return;
        }

        // ✅ MELHORADO: Fallback para URL apenas se não houver base64
        if (qrCodeUrl) {
            console.log('📱 Exibindo QR Code via URL (fallback - container temporário):', qrCodeUrl);
            $qrContainer.html(`
                <div style="text-align: center;">
                    <h3 style="margin: 0 0 15px 0; color: #2d3748;">📱 Conecte seu WhatsApp</h3>
                    <div style="background: #f7fafc; padding: 20px; border-radius: 10px; display: inline-block;">
                        <iframe src="${qrCodeUrl}" width="300" height="300" style="border: none; border-radius: 8px;"></iframe>
                    </div>
                    <p style="margin: 10px 0 0 0; color: #4a5568; font-size: 14px;">
                        <span id="connection-indicator">⏳ Aguardando conexão...</span>
                    </p>
                    <button onclick="$('#wpwevo-qr-container-temp').hide();" style="margin-top: 15px; padding: 8px 16px; background: #e53e3e; color: white; border: none; border-radius: 5px; cursor: pointer;">✕ Fechar</button>
                </div>
            `);
            
            // ✅ GARANTIR VISIBILIDADE
            $qrContainer.show().css({
                'display': 'block',
                'visibility': 'visible',
                'opacity': '1',
                'z-index': '99999'
            });
            console.log('✅ Container temporário agora visível (URL):', $qrContainer.is(':visible'));
            return;
        }

        // ✅ MELHORADO: Se nenhum QR Code disponível
        console.log('⚠️ QR Code não disponível, mostrando mensagem de aguardo (container temporário)');
        $qrContainer.html(`
            <div style="text-align: center;">
                <div style="width: 300px; height: 300px; display: flex; align-items: center; justify-content: center; background: #f0f9ff; border: 1px solid #bae6fd; color: #0369a1; border-radius: 8px; text-align: center; padding: 15px;">
                    ⏳ Aguardando QR Code...<br>
                    <small>Verificando status da instância</small>
                </div>
                <button onclick="$('#wpwevo-qr-container-temp').hide();" style="margin-top: 15px; padding: 8px 16px; background: #e53e3e; color: white; border: none; border-radius: 5px; cursor: pointer;">✕ Fechar</button>
            </div>
        `);
        
        // ✅ GARANTIR VISIBILIDADE
        $qrContainer.show().css({
            'display': 'block',
            'visibility': 'visible',
            'opacity': '1',
            'z-index': '99999'
        });
        console.log('✅ Container temporário agora visível (aguardando):', $qrContainer.is(':visible'));
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
        window.open(wpwevo_quick_signup.dashboard_url || 'https://www.whatsevolution.com.br/', '_blank');
    });

    // Auto-focus no primeiro campo
    $('#wpwevo-name').focus();

    // ===== LÓGICA DO MODAL DE UPGRADE =====
    const upgradeModal = document.getElementById('wpwevo-upgrade-modal');
    const paymentFeedback = document.getElementById('wpwevo-payment-feedback');

    // ✅ NOVO: Adicionar estilos CSS para o botão de reconexão
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            #wpwevo-reconnect-btn {
                transition: all 0.3s ease;
                box-shadow: 0 4px 15px rgba(245, 101, 101, 0.3);
            }
            #wpwevo-reconnect-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(245, 101, 101, 0.4);
            }
            #wpwevo-reconnect-btn:disabled {
                opacity: 0.7;
                cursor: not-allowed;
                transform: none;
            }
            #wpwevo-reconnect-btn.success {
                background: linear-gradient(135deg, #48bb78 0%, #38a169 100%) !important;
                box-shadow: 0 4px 15px rgba(72, 187, 120, 0.3) !important;
            }
            #wpwevo-reconnect-btn.error {
                background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%) !important;
                box-shadow: 0 4px 15px rgba(245, 101, 101, 0.3) !important;
            }
        `)
        .appendTo('head');

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

                        // ✅ Iniciar polling de status de pagamento
                        if (responseData.external_reference) {
                            localStorage.setItem('wpwevo_current_payment', JSON.stringify({
                                external_reference: responseData.external_reference,
                                payment_id: responseData.payment_id
                            }));

                            startPaymentStatusPolling(responseData.external_reference);
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

        // ✅ NOVO: Exibir status da instância
        let statusText = '';
        if (apiData.currentStatus) {
            const statusMap = {
                'connected': 'Conectado',
                'connecting': 'Conectando...',
                'disconnected': 'Desconectado',
                'disconnecting': 'Desconectando...',
                'qrcode': 'Aguardando QR Code',
                'open': 'Conectado',
                'close': 'Desconectado'
            };
            
            const statusDisplay = statusMap[apiData.currentStatus] || apiData.currentStatus;
            statusText = `Status da Instância: ${statusDisplay}`;
        }

        // ✅ NOVO: Verificar se precisa de reconexão
        const needsReconnection = apiData.currentStatus === 'disconnected' || apiData.currentStatus === 'connecting';
        const isConnected = apiData.currentStatus === 'connected';

        if (apiData.trial_days_left > 0) {
            // Conta ativa
            let planText = '';
            if (apiData.user_plan === 'basic') {
                planText = 'Plano Basic';
            } else if (apiData.user_plan === 'trial') {
                planText = 'Trial Ativo';
            } else {
                const planName = apiData.user_plan ? apiData.user_plan.charAt(0).toUpperCase() + apiData.user_plan.slice(1) : 'Ativo';
                planText = `${planName} Ativo`;
            }
            
            titleElement.text(planText);
            
            // ✅ NOVO: Incluir status da instância na descrição
            let descriptionText = `Você tem <strong>${apiData.trial_days_left} dias</strong> restantes.`;
            if (statusText) {
                descriptionText += `<br><small style="color: #4a5568; font-size: 14px;">${statusText}</small>`;
            }
            daysLeftElement.html(descriptionText);
            
            // ✅ CORREÇÃO: Adicionar botão de reconexão apenas se conta ativa E precisa reconectar
            if (needsReconnection && !isConnected) {
                const reconnectButton = `
                    <div style="margin-top: 15px;">
                        <button id="wpwevo-reconnect-btn" onclick="requestNewQRCode()" 
                                style="background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-size: 14px; cursor: pointer; font-weight: 600;">
                            🔄 Reconectar WhatsApp
                        </button>
                        <p style="margin: 5px 0 0 0; color: #4a5568; font-size: 12px;">
                            Clique para solicitar um novo QR Code de conexão
                        </p>
                    </div>
                `;
                daysLeftElement.append(reconnectButton);
            } else {
                // Remove botão de reconexão se não for necessário
                $('#wpwevo-reconnect-btn').parent().remove();
            }
            
            mainContainer.removeClass('status-expired').addClass('status-active');
            renewalModal.hide();
            expiredNotice.hide();
            upgradeButton.hide();
            
            // ✅ CORRIGIDO: NÃO mostrar QR Code automaticamente. Só mostrar via clique (requestNewQRCode)
            // if (needsReconnection && !isConnected) {
            //     displayQRCode(apiData);
            // } else {
            //     // Esconder QR Code de conexão se conectado ou não precisa reconectar
            //     $('#wpwevo-qr-container-temp').hide();
            // }
            // Nova lógica: sempre esconder QR Code automático
            $('#wpwevo-qr-container-temp').hide();
        } else {
            // Conta expirada
            titleElement.text('Assinatura Expirada');
            
            // ✅ NOVO: Incluir status da instância mesmo quando expirado
            let descriptionText = 'Faça upgrade para reativar sua conta.';
            if (statusText) {
                descriptionText += `<br><small style="color: #4a5568; font-size: 14px;">${statusText}</small>`;
            }
            daysLeftElement.html(descriptionText);
            
            mainContainer.removeClass('status-active').addClass('status-expired');
            expiredNotice.show();
            
            // ✅ CORREÇÃO CRÍTICA: NÃO mostrar QR Code de conexão quando expirado
            // Esconder qualquer QR Code de conexão que possa estar visível
            $('#wpwevo-qr-container-temp').hide();
            
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

    /**
     * ✅ NOVO: Função para solicitar novo QR Code (padronizada com onboarding)
     */
    window.requestNewQRCode = function() {
        // ✅ CORREÇÃO CRÍTICA: Verificar se conta está expirada antes de solicitar QR Code
        const currentStatus = $('#connection-status-message').text();
        const isExpired = currentStatus === 'Assinatura Expirada' || 
                         currentStatus.includes('Expirada') || 
                         currentStatus.includes('Expirado');
        
        if (isExpired) {
            console.log('❌ Conta expirada - NÃO solicitar QR Code de conexão');
            alert('Sua conta expirou. Faça upgrade para reativar o WhatsApp.');
            return;
        }
        
        const reconnectBtn = $('#wpwevo-reconnect-btn');
        const originalText = reconnectBtn.text();
        
        // Mostra loading
        reconnectBtn.text('⏳ Verificando...').prop('disabled', true);
        
        // Chama o mesmo endpoint do polling do onboarding
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
                    console.log('✅ QR Code obtido com sucesso (reconexão):', response.data);
                    
                    // ✅ CORREÇÃO: Verificar se conta ainda está ativa antes de mostrar QR Code
                    if (response.data.trial_days_left <= 0 || response.data.isTrialExpired) {
                        console.log('❌ Conta expirou durante a solicitação - NÃO mostrar QR Code');
                        reconnectBtn.text('❌ Conta Expirada').addClass('error').prop('disabled', false);
                        setTimeout(() => {
                            reconnectBtn.text(originalText).removeClass('error');
                        }, 3000);
                        return;
                    }
                    
                    // Exibe QR Code igual onboarding
                    displayQRCode(response.data);
                    // Reinicia polling automático
                    startStatusPolling(wpwevo_quick_signup.api_key);
                    // Feedback visual
                    reconnectBtn.text('✅ QR Code Atualizado!').removeClass('error').addClass('success');
                    setTimeout(() => {
                        reconnectBtn.text(originalText).prop('disabled', false).removeClass('success');
                    }, 3000);
                } else {
                    reconnectBtn.text('⚠️ QR Code Indisponível').addClass('error').prop('disabled', false);
                    setTimeout(() => {
                        reconnectBtn.text(originalText).removeClass('error');
                    }, 3000);
                    // Mensagem informativa
                    const $qrContainer = $('#wpwevo-qr-container');
                    if ($qrContainer.length) {
                        $qrContainer.html(`
                            <div style="width: 300px; height: 300px; display: flex; align-items: center; justify-content: center; background: #fef3c7; border: 1px solid #f59e0b; color: #92400e; border-radius: 8px; text-align: center; padding: 15px;">
                                <div>
                                    ⚠️ QR Code Indisponível<br>
                                    <small>Aguarde alguns segundos e tente novamente</small>
                                </div>
                            </div>
                        `);
                        $qrContainer.show();
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Erro ao solicitar QR Code:', error);
                reconnectBtn.text('❌ Erro!').addClass('error').prop('disabled', false);
                setTimeout(() => {
                    reconnectBtn.text(originalText).removeClass('error');
                }, 3000);
            }
        });
    };
}); 