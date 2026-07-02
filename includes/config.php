<?php
/**
 * Configurações de integração com o Sistema Principal (WhatsEvolution V2)
 */

// Previne acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// API do modo managed (WhatsEvolution V2)
// Usar sempre o domínio com www — o apex responde 307 para www
// Os guards permitem override via wp-config.php (útil para staging/testes)
if (!defined('WHATSEVOLUTION_API_BASE')) {
	define('WHATSEVOLUTION_API_BASE', 'https://www.whatsevolution.com.br/api');
}
if (!defined('WHATSEVOLUTION_DASHBOARD_URL')) {
	define('WHATSEVOLUTION_DASHBOARD_URL', 'https://www.whatsevolution.com.br');
}
define('WHATSEVOLUTION_TIMEOUT', 45); // segundos para signup
define('WHATSEVOLUTION_STATUS_TIMEOUT', 15); // segundos para status/pagamento
