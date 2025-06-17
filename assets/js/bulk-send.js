/* Envio em massa de WhatsApp */
jQuery(document).ready(function($) {
    // Tabs
    function initTabs() {
        $('.wpwevo-tab-button').on('click', function(e) {
            e.preventDefault();
            var tab = $(this).data('tab');
            
            // Remove active class from all buttons and contents
            $('.wpwevo-tab-button').removeClass('active');
            $('.wpwevo-tab-content').removeClass('active');
            
            // Add active class to clicked button and corresponding content
            $(this).addClass('active');
            $('#tab-' + tab).addClass('active');
            
            // Store active tab in session
            if (typeof(Storage) !== "undefined") {
                sessionStorage.setItem('wpwevo_active_tab', tab);
            }
        });

        // Restore last active tab
        if (typeof(Storage) !== "undefined") {
            var lastTab = sessionStorage.getItem('wpwevo_active_tab');
            if (lastTab) {
                $('.wpwevo-tab-button[data-tab="' + lastTab + '"]').trigger('click');
            }
        }
    }

    // Preview de clientes
    function initCustomerPreview() {
        $('#wpwevo-preview-customers').on('click', function(e) {
            e.preventDefault();
            
            console.group('Clique no botão Visualizar Clientes');
            
            var $button = $(this);
            var $preview = $('#wpwevo-customers-preview');
            
            // Debug dos elementos do formulário
            console.log('Botão:', $button.length ? 'Encontrado' : 'Não encontrado');
            console.log('Preview:', $preview.length ? 'Encontrado' : 'Não encontrado');
            
            // Debug dos checkboxes
            var totalCheckboxes = $('.wpwevo-status-input').length;
            var checkedCheckboxes = $('.wpwevo-status-input:checked').length;
            console.log('Total de checkboxes:', totalCheckboxes);
            console.log('Checkboxes marcados:', checkedCheckboxes);
            
            // Coleta os status selecionados
            var selectedStatus = [];
            $('.wpwevo-status-input:checked').each(function() {
                selectedStatus.push($(this).val());
                console.log('Checkbox marcado:', {
                    id: $(this).attr('id'),
                    name: $(this).attr('name'),
                    value: $(this).val()
                });
            });
            
            console.log('Status selecionados:', selectedStatus);
            
            // Validação do status
            if (!selectedStatus || selectedStatus.length === 0) {
                console.warn('Nenhum status selecionado');
                alert(wpwevoBulkSend.i18n.statusRequired);
                console.groupEnd();
                return;
            }
            
            $button.prop('disabled', true);
            $preview.html('<div class="spinner is-active"></div>');

            // Prepara os dados para envio
            var data = new FormData();
            data.append('action', 'wpwevo_preview_customers');
            data.append('nonce', wpwevoBulkSend.nonce);

            // Adiciona cada status como um item do array
            selectedStatus.forEach(function(status) {
                data.append('status[]', status);
            });

            // Adiciona os outros campos
            data.append('date_from', $('input[name="wpwevo_date_from"]').val());
            data.append('date_to', $('input[name="wpwevo_date_to"]').val());
            data.append('min_total', $('input[name="wpwevo_min_total"]').val());

            console.log('Dados sendo enviados (FormData):');
            for (var pair of data.entries()) {
                console.log(pair[0] + ': ' + pair[1]);
            }

            // Envia a requisição
            $.ajax({
                url: wpwevoBulkSend.ajaxurl,
                type: 'POST',
                data: data,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log('Resposta recebida:', response);
                    if (response.success) {
                        $preview.html(response.data);
                    } else {
                        $preview.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro na requisição:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });
                    $preview.html('<div class="notice notice-error"><p>' + wpwevoBulkSend.i18n.error + '</p></div>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    console.groupEnd();
                }
            });
        });
    }

    // Envio em massa
    function initBulkSend() {
        $('#wpwevo-bulk-form').on('submit', function(e) {
            e.preventDefault();
            console.log('Envio do formulário');

            var activeTab = $('.wpwevo-tab-button.active').data('tab');
            var message = $('#wpwevo-bulk-message').val();
            
            console.log('Aba ativa:', activeTab);
            console.log('Mensagem:', message);

            // Create FormData from the form
            var formData = new FormData(this);
            
            // Remove any existing status entries
            for (var pair of formData.entries()) {
                if (pair[0] === 'status[]') {
                    formData.delete(pair[0]);
                }
            }
            
            // Add status entries only once
            var statusArray = [];
            $('.wpwevo-status-input:checked').each(function() {
                statusArray.push($(this).val());
                formData.append('status[]', $(this).val());
            });
            
            console.log('Status selecionados:', statusArray);

            // Add additional data
            formData.append('action', 'wpwevo_bulk_send');
            formData.append('active_tab', activeTab);
            formData.append('nonce', wpwevoBulkSend.nonce);

            // Debug output
            console.log('Dados sendo enviados (FormData):');
            for (var pair of formData.entries()) {
                console.log(pair[0] + ':', pair[1]);
            }

            // Validação básica
            if (!message) {
                console.warn('Mensagem vazia');
                alert(wpwevoBulkSend.i18n.messageRequired);
                return false;
            }
            
            if (activeTab === 'customers' && (!statusArray || statusArray.length === 0)) {
                console.warn('Nenhum status selecionado na aba de clientes');
                alert(wpwevoBulkSend.i18n.statusRequired);
                return false;
            }
            
            if (activeTab === 'csv' && !$('input[name="wpwevo_csv_file"]').val()) {
                console.warn('Nenhum arquivo CSV selecionado');
                alert(wpwevoBulkSend.i18n.csvRequired);
                return false;
            }
            
            if (activeTab === 'manual' && !$('textarea[name="wpwevo_manual_numbers"]').val().trim()) {
                console.warn('Lista de números vazia');
                alert(wpwevoBulkSend.i18n.numbersRequired);
                return false;
            }

            var $form = $(this);
            var $button = $('.wpwevo-bulk-submit');
            var $status = $('.wpwevo-bulk-status');
            
            // Adiciona a barra de progresso
            $status.html(`
                <div class="wpwevo-progress-wrapper">
                    <div class="wpwevo-progress-bar" style="width: 0%"></div>
                    <div class="wpwevo-progress-text">Iniciando envio...</div>
                </div>
                <div class="wpwevo-progress-warning">
                    <strong>Atenção:</strong> Não feche esta página até o envio ser concluído.
                </div>
            `);

            // Desabilita o formulário
            $form.find('input, textarea, select, button').prop('disabled', true);
            $button.text(wpwevoBulkSend.i18n.sending);

            // Envia a requisição
            $.ajax({
                url: wpwevoBulkSend.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log('Resposta recebida:', response);
                    if (response.success) {
                        var data = response.data;
                        
                        // Atualiza a barra de progresso
                        $('.wpwevo-progress-bar').css('width', data.progress + '%');
                        $('.wpwevo-progress-text').text(data.message);

                        if (data.status === 'completed') {
                            // Atualiza a barra de progresso para verde
                            $('.wpwevo-progress-bar').css({
                                'background-color': '#00a32a',
                                'width': '100%'
                            });
                            
                            // Remove o aviso de não fechar a página
                            $('.wpwevo-progress-warning').remove();
                            
                            // Mostra mensagem de conclusão
                            $status.append(`
                                <div class="notice notice-success">
                                    <p>${data.message}</p>
                                    ${data.errors.length > 0 ? `
                                        <p>Erros encontrados: ${data.errors.length}</p>
                                        <ul class="wpwevo-error-list">
                                            ${data.errors.map(error => `<li>${error}</li>`).join('')}
                                        </ul>
                                    ` : ''}
                                </div>
                            `);

                            // Atualiza o histórico
                            if (data.historyHtml) {
                                $('#wpwevo-history-container').html(data.historyHtml);
                            }

                            // Reativa o formulário
                            $form.find('input, textarea, select, button').prop('disabled', false);
                            $button.text(wpwevoBulkSend.i18n.send);
                            
                            // Limpa o formulário
                            $form.trigger('reset');
                            $('#wpwevo-customers-preview').empty();
                        }
                    } else {
                        $status.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                        // Reativa o formulário em caso de erro
                        $form.find('input, textarea, select, button').prop('disabled', false);
                        $button.text(wpwevoBulkSend.i18n.send);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro na requisição:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });
                    $status.html('<div class="notice notice-error"><p>' + wpwevoBulkSend.i18n.error + '</p></div>');
                    // Reativa o formulário em caso de erro
                    $form.find('input, textarea, select, button').prop('disabled', false);
                    $button.text(wpwevoBulkSend.i18n.send);
                }
            });
        });
    }

    // Inicializa todos os módulos
    initTabs();
    initCustomerPreview();
    initBulkSend();
    initHistoryActions();

    // Histórico de envios
    function initHistoryActions() {
        // Delegação de evento para o botão de limpar histórico
        $(document).on('click', '#wpwevo-clear-history', function(e) {
            e.preventDefault();
            
            if (!confirm(wpwevoBulkSend.i18n.confirmClearHistory)) {
                return;
            }
            
            var $button = $(this);
            $button.prop('disabled', true);
            
            $.ajax({
                url: wpwevoBulkSend.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpwevo_clear_history',
                    nonce: wpwevoBulkSend.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Atualiza o container do histórico para mostrar mensagem vazia
                        $('#wpwevo-history-container').html(`
                            <div class="wpwevo-bulk-history">
                                <div class="wpwevo-history-header">
                                    <h3>${wpwevoBulkSend.i18n.historyTitle}</h3>
                                </div>
                                <p>${wpwevoBulkSend.i18n.noHistory}</p>
                            </div>
                        `);
                    } else {
                        alert(response.data);
                    }
                },
                error: function() {
                    alert(wpwevoBulkSend.i18n.error);
                    $button.prop('disabled', false);
                }
            });
        });
    }
}); 