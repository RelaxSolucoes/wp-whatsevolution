<?php
/**
 * Configurações de integração com o Sistema Principal
 */

// Previne acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Configurações da API do Sistema Principal (Supabase)
define('WHATSEVOLUTION_API_BASE', 'https://ydnobqsepveefiefmxag.supabase.co');
define('WHATSEVOLUTION_API_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Inlkbm9icXNlcHZlZWZpZWZteGFnIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDk2NDkwOTAsImV4cCI6MjA2NTIyNTA5MH0.PlLrBA3eauvanWT-gQoKdvpTaPRrwgtuW8gZhbrlO7o');
define('WHATSEVOLUTION_TIMEOUT', 45); // segundos para quick-signup
define('WHATSEVOLUTION_STATUS_TIMEOUT', 15); // segundos para plugin-status

// Versão mínima do sistema principal compatível
define('WPWEVO_MIN_SYSTEM_VERSION', '1.5.0'); 