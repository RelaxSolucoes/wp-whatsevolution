<?php
/**
 * Arquivo de desinstalação do plugin WP WhatsApp Sender.
 *
 * Este arquivo é executado quando o plugin é desinstalado.
 */

// Se este arquivo for chamado diretamente, abortar.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

// Remove todas as opções do plugin
$options = array(
    'wp_whatsapp_sender_api_key',
    'wp_whatsapp_sender_api_url',
    'wp_whatsapp_sender_api_phone'
);

foreach ($options as $option) {
    delete_option($option);
}

// Remove todos os templates
global $wpdb;
$template_options = $wpdb->get_results(
    "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'wp_whatsapp_sender_template_%'"
);

if (!empty($template_options)) {
    foreach ($template_options as $option) {
        delete_option($option->option_name);
    }
}

// Limpa o cache de reescrita
flush_rewrite_rules(); 