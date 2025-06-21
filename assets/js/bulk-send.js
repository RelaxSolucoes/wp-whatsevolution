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
                sessionStorage.setItem('wpwevo_bulk_send_active_tab', tab);
            }
        });

        // Restore last active tab
        if (typeof(Storage) !== "undefined") {
            var lastTab = sessionStorage.getItem('wpwevo_bulk_send_active_tab');
            if (lastTab) {
                $('.wpwevo-tab-button[data-tab="' + lastTab + '"]').trigger('click');
            }
        }
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

            var formData = $form.serialize();
            var data = formData + '&action=wpwevo_preview_customers&nonce=' + wpwevoBulkSend.nonce;

            $.ajax({
                url: wpwevoBulkSend.ajaxurl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        $preview.html(response.data.html).show();
                    } else {
                        alert('Erro: ' + (response.data.message || response.data));
                    }
                },
                error: function() {
                    alert(wpwevoBulkSend.i18n.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalButtonText);
                }
            });
        });
    }

    // Envio em massa
    function initBulkSend() {
        $('#wpwevo-bulk-form').on('submit', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var activeTab = $('.wpwevo-tab-button.active').data('tab');
            var message = $('#wpwevo_bulk_message').val().trim();
            
            if (!message) {
                alert(wpwevoBulkSend.i18n.messageRequired);
                return;
            }

            // Validação específica por aba
            if (activeTab === 'customers') {
                if ($('input[name="status[]"]:checked').length === 0) {
                    alert(wpwevoBulkSend.i18n.statusRequired);
                    return;
                }
            } else if (activeTab === 'csv') {
                if ($('#wpwevo_csv_file').get(0).files.length === 0) {
                    alert(wpwevoBulkSend.i18n.csvRequired);
                    return;
                }
            } else if (activeTab === 'manual') {
                if ($('#wpwevo_number_list').val().trim() === '') {
                    alert(wpwevoBulkSend.i18n.numbersRequired);
                    return;
                }
            }

            var formData = new FormData(this);
            formData.append('action', 'wpwevo_bulk_send');
            formData.append('active_tab', activeTab);
            // O nonce já é adicionado pelo wp_nonce_field no form, mas podemos garantir
            if (!formData.has('wpwevo_bulk_send_nonce')) {
                formData.append('wpwevo_bulk_send_nonce', wpwevoBulkSend.nonce);
            }

            // Desabilita o botão de envio
            var $submitBtn = $form.find('button[type="submit"]');
            var originalText = $submitBtn.text();
            $submitBtn.prop('disabled', true).text(wpwevoBulkSend.i18n.sending);

            // Encontra e prepara o elemento de status
            var $status = $('#wpwevo-bulk-status');
            $status.html('<div class="wpwevo-bulk-status-container"><div id="wpwevo-status-text">Iniciando...</div><div class="wpwevo-progress-bar-container"><div id="wpwevo-progress-bar" style="width: 0%;"></div></div></div>').show();

            // Envia requisição AJAX
            $.ajax({
                url: wpwevoBulkSend.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        checkBulkSendStatus(response.data.batch_id);
                    } else {
                        $('#wpwevo-status-text').text('Erro: ' + (response.data.message || response.data));
                        $submitBtn.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    $('#wpwevo-status-text').text(wpwevoBulkSend.i18n.error);
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        });
    }

    function checkBulkSendStatus(batchId) {
        var $submitBtn = $('#wpwevo-bulk-form').find('button[type="submit"]');
        var originalText = $submitBtn.text();

        var interval = setInterval(function() {
            $.ajax({
                url: wpwevoBulkSend.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpwevo_bulk_send',
                    batch_id: batchId,
                    nonce: wpwevoBulkSend.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        $('#wpwevo-progress-bar').css('width', data.progress + '%');
                        $('#wpwevo-status-text').html(data.message);

                        if (data.status === 'completed' || data.status === 'partial') {
                            clearInterval(interval);
                            
                            setTimeout(function() {
                                var resultsHtml = '<div class="wpwevo-results-container">';
                                resultsHtml += '<h3><span class="emoji">🎉</span> Envio Finalizado!</h3>';
                                resultsHtml += '<p>' + data.message.replace(/<br>/g, '</p><p>') + '</p>';
                                
                                if (data.errors && data.errors.length > 0) {
                                    resultsHtml += '<details class="wpwevo-error-details">';
                                    resultsHtml += '<summary><span class="emoji">⚠️</span> ' + data.errors.length + ' Erro(s) Encontrado(s)</summary>';
                                    resultsHtml += '<ul>';
                                    data.errors.forEach(function(error) {
                                        resultsHtml += '<li>' + error + '</li>';
                                    });
                                    resultsHtml += '</ul></details>';
                                }
                                
                                resultsHtml += '</div>';

                                $('#wpwevo-bulk-status').html(resultsHtml);
                                $submitBtn.prop('disabled', false).text(wpwevoBulkSend.i18n.send);
                                updateHistory();
                            }, 500);
                        }
                    } else {
                        clearInterval(interval);
                        $('#wpwevo-status-text').text('Erro ao verificar status: ' + (response.data.message || response.data));
                        $submitBtn.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    clearInterval(interval);
                    $('#wpwevo-status-text').text(wpwevoBulkSend.i18n.error);
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        }, 2000);
    }

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
                    $('#wpwevo-history-container').html(response.data.html);
                }
            }
        });
    }

    // Histórico de envios
    function initHistoryActions() {
        // Usa delegação de eventos para o botão de limpar, pois o conteúdo é dinâmico
        $('#wpwevo-history-container').on('click', '#wpwevo-clear-history', function(e) {
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
                    }
                });
            }
        });
    }

    // Inicializa todos os módulos
    initTabs();
    initCustomerPreview();
    initBulkSend();
    initHistoryActions();
    updateHistory(); // Carrega o histórico inicial
}); 