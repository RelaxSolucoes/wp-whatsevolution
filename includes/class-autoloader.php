<?php

namespace WpWhatsAppEvolution;

/**
 * Autoloader class
 * 
 * Responsável por carregar automaticamente as classes do plugin
 */
class Autoloader {
    /**
     * Registra o autoloader
     */
    public static function register() {
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    /**
     * Autoload callback
     * 
     * @param string $class_name Nome completo da classe incluindo namespace
     */
    public static function autoload($class_name) {
        // Verifica se a classe pertence ao namespace do plugin
        if (strpos($class_name, 'WpWhatsAppEvolution\\') !== 0) {
            return;
        }

        // Remove o namespace do plugin
        $class_name = str_replace('WpWhatsAppEvolution\\', '', $class_name);

        // Converte o nome da classe para o padrão de arquivo
        $file_name = 'class-' . str_replace('_', '-', strtolower($class_name)) . '.php';

        // Caminho completo do arquivo
        $file_path = WPWEVO_PATH . 'includes/' . $file_name;

        // Carrega o arquivo se ele existir
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
} 