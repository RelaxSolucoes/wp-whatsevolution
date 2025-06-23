/* Envio em massa de WhatsApp */
jQuery(document).ready(function($) {
    'use strict';

    // Para o script se não estiver na página de envio em massa
    if ($('#wpwevo-bulk-form').length === 0) {
        return;
    }

    // Variáveis globais
    let isSending = false;
    let currentProgress = 0;
    let totalMessages = 0;
    let sentMessages = 0;
    let failedMessages = 0;
    let currentBatch = 0;
    let totalBatches = 0;
    let customers = [];
    let currentCustomerIndex = 0;
    let sendInterval;
    let progressInterval;
    let csvData = null; // Armazena os dados do CSV processado

    // Tabs
    function initTabs() {
        $('.wpwevo-tab-button').on('click', function(e) {
            e.preventDefault();
            var tab = $(this).data('tab');
            
            // Troca a aba ativa
            $('.wpwevo-tab-button').removeClass('active');
            $('.wpwevo-tab-content').removeClass('active');
            $(this).addClass('active');
            $('#tab-' + tab).addClass('active');
            
            // **NOVO: Troca a exibição das variáveis dinamicamente**
            $('.wpwevo-variables').hide();
            $('.wpwevo-variables[data-source="' + tab + '"]').show();

            // Armazena a aba ativa na sessão
            if (typeof(Storage) !== "undefined") {
                sessionStorage.setItem('wpwevo_bulk_send_active_tab', tab);
            }
        });

        // Restaura a última aba ativa ao carregar a página
        if (typeof(Storage) !== "undefined") {
            var lastTab = sessionStorage.getItem('wpwevo_bulk_send_active_tab');
            if (lastTab) {
                // Simula o clique para acionar toda a lógica, incluindo a troca de variáveis
                $('.wpwevo-tab-button[data-tab="' + lastTab + '"]').trigger('click');
            }
        }
    }

    // **NOVO: Processamento de arquivo CSV**
    function initCsvProcessing() {
        $('input[name="wpwevo_csv_file"]').on('change', function(e) {
            const file = e.target.files[0];
            if (!file) {
                hideCsvColumnMapping();
                return;
            }

            // Verifica se é um arquivo CSV
            if (!file.name.toLowerCase().endsWith('.csv')) {
                alert('Por favor, selecione um arquivo CSV válido.');
                $(this).val('');
                hideCsvColumnMapping();
                return;
            }

            // Processa o arquivo CSV
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const csvContent = e.target.result;
                    processCsvContent(csvContent);
                } catch (error) {
                    console.error('Erro ao processar CSV:', error);
                    alert('Erro ao processar o arquivo CSV. Verifique se o formato está correto.');
                    hideCsvColumnMapping();
                }
            };
            reader.readAsText(file, 'UTF-8');
        });
    }

    // **NOVO: Processa o conteúdo do CSV**
    function processCsvContent(csvContent) {
        try {
            // Detecta o delimitador
            const lines = csvContent.split('\n').filter(line => line.trim());
            if (lines.length < 2) {
                throw new Error('CSV deve ter pelo menos um cabeçalho e uma linha de dados');
            }

            const headerLine = lines[0];
            const delimiter = (headerLine.split(';').length > headerLine.split(',').length) ? ';' : ',';
            
            // Processa o cabeçalho
            const headers = parseCsvLine(headerLine, delimiter);
            const headerMap = headers.map(h => h.toLowerCase().trim());

            // Encontra colunas de telefone
            const phoneColumns = [];
            const phoneKeywords = ['telefone', 'celular', 'fone', 'phone', 'mobile', 'whatsapp', 'contato'];
            
            headerMap.forEach((header, index) => {
                if (phoneKeywords.some(keyword => header.includes(keyword))) {
                    phoneColumns.push({
                        index: index,
                        name: headers[index],
                        header: header
                    });
                }
            });

            // Se não encontrou colunas de telefone, considera todas as colunas
            if (phoneColumns.length === 0) {
                headerMap.forEach((header, index) => {
                    phoneColumns.push({
                        index: index,
                        name: headers[index],
                        header: header
                    });
                });
            }

            // Armazena os dados para uso posterior
            csvData = {
                headers: headers,
                headerMap: headerMap,
                phoneColumns: phoneColumns,
                delimiter: delimiter,
                content: csvContent
            };

            // Mostra interface de seleção de coluna
            showCsvColumnMapping(phoneColumns, headers);

        } catch (error) {
            console.error('Erro ao processar CSV:', error);
            alert('Erro ao processar o arquivo CSV: ' + error.message);
            hideCsvColumnMapping();
        }
    }

    // **NOVO: Parse de linha CSV**
    function parseCsvLine(line, delimiter) {
        const result = [];
        let current = '';
        let inQuotes = false;
        
        for (let i = 0; i < line.length; i++) {
            const char = line[i];
            
            if (char === '"') {
                inQuotes = !inQuotes;
            } else if (char === delimiter && !inQuotes) {
                result.push(current.trim());
                current = '';
            } else {
                current += char;
            }
        }
        
        result.push(current.trim());
        return result;
    }

    // **NOVO: Mostra interface de seleção de coluna**
    function showCsvColumnMapping(phoneColumns, headers) {
        let html = `
            <div class="wpwevo-csv-mapping" style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #dee2e6; margin-top: 15px;">
                <h4 style="margin: 0 0 15px 0; color: #2d3748; font-size: 16px;">
                    📋 <strong>Colunas Encontradas no CSV</strong>
                </h4>
                <div style="margin-bottom: 15px;">
                    <p style="margin: 0 0 10px 0; color: #4a5568; font-size: 14px;">
                        <strong>Selecione qual coluna contém os números de telefone:</strong>
                    </p>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
        `;

        phoneColumns.forEach((col, index) => {
            const isPhoneColumn = col.header.includes('telefone') || col.header.includes('celular') || 
                                 col.header.includes('phone') || col.header.includes('mobile');
            
            html += `
                <label style="display: flex; align-items: center; padding: 10px; border: 2px solid ${isPhoneColumn ? '#4facfe' : '#e2e8f0'}; border-radius: 6px; cursor: pointer; background: ${isPhoneColumn ? '#f0f8ff' : 'white'};">
                    <input type="radio" name="wpwevo_phone_column" value="${col.index}" ${index === 0 ? 'checked' : ''} style="margin-right: 8px;">
                    <div>
                        <div style="font-weight: 600; color: #2d3748;">${col.name}</div>
                        <div style="font-size: 12px; color: #718096;">${isPhoneColumn ? '📱 Coluna de telefone detectada' : 'Coluna geral'}</div>
                    </div>
                </label>
            `;
        });

        html += `
                    </div>
                </div>
                <div style="background: #e8f5e8; padding: 12px; border-radius: 6px; border-left: 4px solid #48bb78;">
                    <p style="margin: 0; font-size: 13px; color: #2f855a;">
                        <strong>✅ Pronto!</strong> Selecione a coluna correta e clique em "Iniciar Envio" quando estiver pronto.
                    </p>
                </div>
            </div>
        `;

        $('#wpwevo-csv-column-mapping').html(html).show();
    }

    // **NOVO: Esconde interface de seleção de coluna**
    function hideCsvColumnMapping() {
        $('#wpwevo-csv-column-mapping').hide().empty();
        csvData = null;
    }

    // Preview de clientes
    function initCustomerPreview() {
        $('#wpwevo-preview-customers').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $preview = $('#wpwevo-customers-preview');
            var $form = $button.closest('form');
            
            var $checkboxes = $form.find('input[name="status[]"]:checked');
            
            if ($checkboxes.length === 0) {
                alert(wpwevoBulkSend.i18n.statusRequired);
                return;
            }

            var originalButtonText = $button.text();
            $button.prop('disabled', true).text(wpwevoBulkSend.i18n.sending);

            // Cria dados manualmente para debug
            var formData = new FormData($form[0]);
            formData.append('action', 'wpwevo_preview_customers');
            formData.append('nonce', wpwevoBulkSend.nonce);

            // Faz a requisição AJAX
            $.ajax({
                url: wpwevoBulkSend.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $preview.html(response.data.html).show();
                    } else {
                        alert('Erro: ' + (response.data.message || response.data));
                    }
                },
                error: function(xhr, status, error) {
                    alert(wpwevoBulkSend.i18n.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalButtonText);
                }
            });
        });
    }

    // Inicializa envio em massa
    function initBulkSend() {
        const sendButton = $('#wpwevo-bulk-form');
        if (sendButton.length) {
            sendButton.on('submit', function(e) {
                e.preventDefault();
                startBulkSend();
            });
        }
    }

    // Inicia envio em massa
    function startBulkSend() {
        if (isSending) {
            alert('Envio já está em andamento.');
            return;
        }

        const formElement = $('#wpwevo-bulk-form')[0];
        const formData = new FormData(formElement);
        
        const message = formData.get('wpwevo_bulk_message');

        if (!message || !message.trim()) {
            alert('Digite uma mensagem.');
            return;
        }

        // Detecta a aba ativa corretamente
        const activeTab = $('.wpwevo-tab-button.active').data('tab');
        
        // Validações específicas por aba
        if (activeTab === 'customers') {
            const statuses = formData.getAll('status[]');
            if (statuses.length === 0) {
                alert('Selecione pelo menos um status.');
                return;
            }
        } else if (activeTab === 'csv') {
            const csvFile = formData.get('wpwevo_csv_file');
            if (!csvFile || !csvFile.name) {
                alert('Selecione um arquivo CSV.');
                return;
            }
            
            // Verifica se o usuário selecionou uma coluna
            const selectedColumn = formData.get('wpwevo_phone_column');
            if (selectedColumn === null || selectedColumn === '') {
                alert('Selecione qual coluna contém os números de telefone.');
                return;
            }
        } else if (activeTab === 'manual') {
            const manualNumbers = formData.get('wpwevo_manual_numbers');
            if (!manualNumbers || !manualNumbers.trim()) {
                alert('Digite pelo menos um número de telefone.');
                return;
            }
        }

        // Confirma o envio
        if (!confirm('Tem certeza que deseja enviar mensagens para todos os contatos selecionados?')) {
            return;
        }

        // Garante que o container de status exista antes de usá-lo
        if ($('#wpwevo-bulk-status').length === 0) {
            const statusContainerHtml = '<div id="wpwevo-bulk-status" style="margin-top: 20px;"></div>';
            // Tenta inserir após o wrapper do botão, se não encontrar, insere no final do form
            const submitWrapper = $('.wpwevo-submit-wrapper');
            if (submitWrapper.length > 0) {
                submitWrapper.after(statusContainerHtml);
            } else {
                $('#wpwevo-bulk-form').append(statusContainerHtml);
            }
        }

        // Inicia o processo
        isSending = true;
        updateSendButton();
        showProgressBar();
        resetProgress();

        // Adiciona campos que podem estar faltando
        formData.append('action', 'wpwevo_bulk_send');
        if (!formData.has('wpwevo_bulk_send_nonce')) {
            formData.append('wpwevo_bulk_send_nonce', wpwevoBulkSend.nonce);
        }
        
        if (!formData.has('active_tab')) {
            formData.append('active_tab', activeTab || 'customers');
        }

        // Envia a requisição
        $.ajax({
            url: wpwevoBulkSend.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                // Anima a barra de progresso durante o envio
                $('#wpwevo-status-text').text('Enviando mensagens...');
                $('#wpwevo-progress-bar').css('width', '50%');
            },
            success: function(response) {
                // Anima a barra para 100%
                $('#wpwevo-status-text').text('Finalizando...');
                $('#wpwevo-progress-bar').css('width', '100%');

                // Adiciona um delay para o usuário ver a conclusão
                setTimeout(function() {
                    isSending = false;
                    updateSendButton();
                    hideProgressBar();
                    
                    if (response.success) {
                        showFinalResult(response.data);
                        updateHistory(); // Atualiza o histórico
                    } else {
                        showResult('Erro: ' + (response.data.message || response.data || 'Erro desconhecido'));
                    }
                }, 500); // 0.5 segundos de delay

            },
            error: function(xhr, status, error) {
                isSending = false;
                updateSendButton();
                hideProgressBar();
                showResult('Erro de comunicação: ' + error);
            }
        });
    }

    // Mostra resultado final
    function showFinalResult(data) {
        const total = data.total || 0;
        const success = data.success || 0;
        const errors = data.errors || [];
        const successRate = total > 0 ? Math.round((success / total) * 100) : 0;
        
        let resultHtml = `
            <div class="wpwevo-result">
                <h3>🎉 Envio Concluído!</h3>
                <div class="wpwevo-result-stats">
                    <div class="wpwevo-stat">
                        <span class="wpwevo-stat-number">${total}</span>
                        <span class="wpwevo-stat-label">Total de Mensagens</span>
                    </div>
                    <div class="wpwevo-stat wpwevo-stat-success">
                        <span class="wpwevo-stat-number">${success}</span>
                        <span class="wpwevo-stat-label">Enviadas com Sucesso</span>
                    </div>
                    <div class="wpwevo-stat wpwevo-stat-error">
                        <span class="wpwevo-stat-number">${errors.length}</span>
                        <span class="wpwevo-stat-label">Falharam</span>
                    </div>
                    <div class="wpwevo-stat wpwevo-stat-rate">
                        <span class="wpwevo-stat-number">${successRate}%</span>
                        <span class="wpwevo-stat-label">Taxa de Sucesso</span>
                    </div>
                </div>
        `;

        if (errors.length > 0) {
            resultHtml += `
                <details class="wpwevo-error-details">
                    <summary><span class="emoji">⚠️</span> ${errors.length} Erro(s) Encontrado(s)</summary>
                    <ul>
            `;
            errors.forEach(function(error) {
                resultHtml += '<li>' + error + '</li>';
            });
            resultHtml += '</ul></details>';
        }

        resultHtml += '</div>';

        $('#wpwevo-customers-preview').html(resultHtml);
    }

    // Atualiza botão de envio
    function updateSendButton() {
        const button = $('#wpwevo-bulk-form button[type="submit"]');
        if (isSending) {
            button.prop('disabled', true).text(wpwevoBulkSend.i18n.sending);
        } else {
            button.prop('disabled', false).text(wpwevoBulkSend.i18n.send);
        }
    }

    // Mostra barra de progresso
    function showProgressBar() {
        $('#wpwevo-bulk-status').show();
    }

    // Esconde barra de progresso
    function hideProgressBar() {
        $('#wpwevo-bulk-status').hide();
    }

    // Reseta progresso
    function resetProgress() {
        const progressHtml = `
            <div class="wpwevo-bulk-status-container">
                <div id="wpwevo-status-text" style="margin-bottom: 10px; font-weight: 500; color: #4a5568;">Iniciando envio...</div>
                <div class="wpwevo-progress-bar-container" style="background: #e2e8f0; border-radius: 9999px; overflow: hidden; height: 12px;">
                    <div id="wpwevo-progress-bar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #4facfe 0%, #00f2fe 100%); transition: width 0.3s ease-in-out;"></div>
                </div>
            </div>
        `;
        $('#wpwevo-bulk-status').html(progressHtml).show();
    }

    // Mostra resultado simples
    function showResult(message) {
        isSending = false;
        updateSendButton();
        hideProgressBar();
        $('#wpwevo-bulk-status').html('<div class="wpwevo-error">' + message + '</div>').show();
    }

    // Inicializa todos os módulos
    initTabs();
    initCustomerPreview();
    initBulkSend();
    initCsvProcessing();
    initHistoryActions();
    updateHistory(); // Carrega o histórico inicial

    function updateHistory() {
        $.ajax({
            url: wpwevoBulkSend.ajaxurl,
            type: 'POST',
            data: {
                action: 'wpwevo_get_history',
                nonce: wpwevoBulkSend.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#wpwevo-history-container').html(response.data.historyHtml);
                }
            }
        });
    }

    // Histórico de envios
    function initHistoryActions() {
        // Usa delegação de eventos para o botão de limpar a partir da seção principal do histórico
        $('#wpwevo-history-section').on('click', '#wpwevo-clear-history', function(e) {
            e.preventDefault();
            if (confirm(wpwevoBulkSend.i18n.confirmClearHistory)) {
                $.ajax({
                    url: wpwevoBulkSend.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wpwevo_clear_history',
                        nonce: wpwevoBulkSend.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            updateHistory();
                        } else {
                            alert('Erro ao limpar histórico.');
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Erro de comunicação ao limpar histórico.');
                    }
                });
            }
        });
    }
}); 