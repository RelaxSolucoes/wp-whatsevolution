<?php
/**
 * Arquivo de desinstalação do WP WhatsApp Evolution
 * 
 * Este arquivo é executado quando o plugin é desinstalado via WordPress.
 * Garante que todos os dados sejam removidos completamente.
 */

// Se este arquivo for chamado diretamente, abortar
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

global $wpdb;

// Remove a tabela de logs
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wpwevo_logs");

// Lista de todas as opções do plugin
$options_to_delete = [
    // Configurações básicas
    'wpwevo_version',
    'wpwevo_api_url',
    'wpwevo_api_key',
    'wpwevo_instance',
    'wpwevo_instance_name', // Remove também a versão antiga se existir
    'wpwevo_status_messages',
    
    // Configurações de checkout
    'wpwevo_checkout_enabled',
    'wpwevo_checkout_validation',
    'wpwevo_checkout_show_modal',
    'wpwevo_checkout_modal_title',
    'wpwevo_checkout_modal_message',
    'wpwevo_checkout_modal_button_text',
    'wpwevo_checkout_validation_success',
    'wpwevo_checkout_validation_error',
    
    // Configurações de carrinho abandonado
    'wpwevo_cart_abandonment_enabled',
    'wpwevo_cart_abandonment_delay',
    'wpwevo_cart_abandonment_template',
    'wpwevo_abandoned_cart_minutes',
    'wpwevo_abandoned_cart_message',
    'wpwevo_abandoned_cart_enabled',
    'wpwevo_abandoned_carts',
    'wpwevo_recovered_carts',
    
    // Configurações de envio em massa
    'wpwevo_bulk_history',
    
    // Configurações de sequência de emails (futuras)
    'wpwevo_email_sequence_enabled',
    'wpwevo_sequence_max_retries',
    'wpwevo_sequence_fallback_email',
    'wpwevo_sequence_default_coupon',
    'wpwevo_sequence_generate_coupon',
    'wpwevo_sequence_template_1',
    'wpwevo_sequence_template_2',
    'wpwevo_sequence_template_3'
];

// Remove todas as opções
foreach ($options_to_delete as $option) {
    delete_option($option);
}

// Remove todas as opções que começam com wpwevo_ (para casos não previstos)
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wpwevo_%'");

// Remove todos os transients do plugin
delete_transient('wpwevo_connection_status');
delete_transient('wpwevo_instance_status');

// Remove todos os transients que começam com wpwevo_
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpwevo_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wpwevo_%'");

// Remove opções de agendamento (cron jobs)
wp_clear_scheduled_hook('wpwevo_cleanup_logs');
wp_clear_scheduled_hook('wpwevo_abandoned_cart_cron');
wp_clear_scheduled_hook('wpwevo_bulk_send_cron');

// Remove user meta relacionados ao plugin (se existirem)
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'wpwevo_%'");

// Remove post meta relacionados ao plugin (se existirem)
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'wpwevo_%'");

// Limpa o cache de reescrita
flush_rewrite_rules();

// Log da desinstalação (apenas se WooCommerce estiver ativo)
if (function_exists('wc_get_logger')) {
    $logger = wc_get_logger();
    $logger->info('WP WhatsApp Evolution foi completamente desinstalado', ['source' => 'wpwevo-uninstall']);
} 