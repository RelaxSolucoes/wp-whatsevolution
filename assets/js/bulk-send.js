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
            
            if (!$button.length || !$preview.length) {
                return;
            }

            var $checkboxes = $('input[name="status[]"]:checked');
            
            if ($checkboxes.length === 0) {
                alert('Por favor, selecione pelo menos um status.');
                return;
            }

            var statusArray = [];
            $checkboxes.each(function() {
                statusArray.push($(this).val());
            });

            var formData = new FormData();
            formData.append('action', 'wpwevo_preview_customers');
            formData.append('nonce', wpwevo_bulk_ajax.nonce);
            
            statusArray.forEach(function(status) {
                formData.append('status[]', status);
            });
            
            formData.append('date_from', $('#wpwevo_date_from').val());
            formData.append('date_to', $('#wpwevo_date_to').val());
            formData.append('min_total', $('#wpwevo_min_total').val());

            $button.prop('disabled', true).text('Carregando...');

            $.ajax({
                url: wpwevo_bulk_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $preview.html(response.data.html).show();
                    } else {
                        alert('Erro: ' + response.data);
                    }
                },
                error: function() {
                    alert('Erro na requisição AJAX');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Visualizar Clientes');
                }
            });
        });
    }

    // Envio em massa
    function initBulkSend() {
        $('#wpwevo-bulk-form').on('submit', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var activeTab = $('.nav-tab-active').data('tab') || 'customers';
            var message = $('#wpwevo_bulk_message').val().trim();
            
            if (!message) {
                alert('Por favor, digite uma mensagem.');
                return;
            }

            // Validação específica por aba
            if (activeTab === 'customers') {
                var $checkboxes = $('input[name="status[]"]:checked');
                if ($checkboxes.length === 0) {
                    alert('Por favor, selecione pelo menos um status de pedido.');
                    return;
                }
                
                var statusArray = [];
                $checkboxes.each(function() {
                    statusArray.push($(this).val());
                });
            }

            var formData = new FormData(this);
            formData.append('action', 'wpwevo_bulk_send');
            formData.append('active_tab', activeTab);
            formData.append('nonce', wpwevo_bulk_ajax.nonce);

            if (activeTab === 'customers' && statusArray) {
                statusArray.forEach(function(status) {
                    formData.append('status[]', status);
                });
            }

            // Desabilita o botão de envio
            var $submitBtn = $form.find('input[type="submit"]');
            var originalText = $submitBtn.val();
            $submitBtn.prop('disabled', true).val('Enviando...');

            // Encontra e prepara o elemento de status
            var $status = $('.wpwevo-bulk-status');
            if ($status.length) {
                // Limpa conteúdo anterior e cria estrutura DOM
                $status.empty();
                
                // Cria elementos usando DOM manipulation
                var statusContainer = document.createElement('div');
                statusContainer.id = 'wpwevo-status-container';
                statusContainer.setAttribute('style', 'background: #ffffff; border: 3px solid #000000; border-radius: 8px; padding: 25px; margin: 25px 0; text-align: center; min-height: 120px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);');
                
                var statusText = document.createElement('div');
                statusText.id = 'wpwevo-status-text';
                statusText.setAttribute('style', 'font-size: 18px; font-weight: bold; color: #000000; margin-bottom: 20px; padding: 10px; border: 2px solid #000000; border-radius: 5px; background: #f8f9fa;');
                statusText.textContent = 'Iniciando envio...';
                
                var progressContainer = document.createElement('div');
                progressContainer.setAttribute('style', 'background: #f0f0f0; border: 2px solid #000000; border-radius: 8px; height: 30px; overflow: hidden; margin-top: 15px; position: relative;');
                
                var progressBar = document.createElement('div');
                progressBar.id = 'wpwevo-progress-bar';
                progressBar.setAttribute('style', 'height: 100%; background: linear-gradient(90deg, #28a745, #20c997); width: 0%; transition: width 0.8s ease-in-out; position: absolute; top: 0; left: 0;');
                
                progressContainer.appendChild(progressBar);
                statusContainer.appendChild(statusText);
                statusContainer.appendChild(progressContainer);
                $status[0].appendChild(statusContainer);
                
                $status.show();
            }

            // Envia requisição AJAX
            $.ajax({
                url: wpwevo_bulk_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        
                        // Atualiza progresso se disponível
                        if (typeof data.progress !== 'undefined') {
                            var progressBar = document.getElementById('wpwevo-progress-bar');
                            var statusText = document.getElementById('wpwevo-status-text');
                            
                            if (progressBar) {
                                progressBar.style.width = data.progress + '%';
                            }
                            
                            if (statusText && data.message) {
                                statusText.textContent = data.message;
                            }
                        }

                        // Se o envio foi concluído
                        if (data.status === 'completed') {
                            // Pequeno delay para mostrar a barra em 100%
                            setTimeout(function() {
                                var completionHtml = '<div style="background: #d4edda; border: 3px solid #28a745; border-radius: 8px; padding: 25px; margin: 25px 0; text-align: center;">';
                                completionHtml += '<h3 style="color: #155724; margin: 0 0 15px 0;">✅ ENVIO CONCLUÍDO!</h3>';
                                completionHtml += '<p style="color: #155724; font-size: 16px; margin: 0;">' + data.message + '</p>';
                                completionHtml += '</div>';
                                
                                $status.html(completionHtml);
                                
                                // Atualiza histórico se disponível
                                if (data.historyHtml) {
                                    $('#wpwevo-bulk-history').html(data.historyHtml);
                                }
                                
                                // Reabilita botão
                                $submitBtn.prop('disabled', false).val(originalText);
                            }, 1000);
                        }
                    } else {
                        $status.html('<div style="background: #f8d7da; border: 3px solid #dc3545; border-radius: 8px; padding: 25px; color: #721c24;"><strong>Erro:</strong> ' + response.data + '</div>');
                        $submitBtn.prop('disabled', false).val(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    $status.html('<div style="background: #f8d7da; border: 3px solid #dc3545; border-radius: 8px; padding: 25px; color: #721c24;"><strong>Erro AJAX:</strong> ' + error + '</div>');
                    $submitBtn.prop('disabled', false).val(originalText);
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