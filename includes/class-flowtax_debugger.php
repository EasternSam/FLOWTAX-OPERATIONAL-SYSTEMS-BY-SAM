<?php
/**
 * Flowtax_Debugger
 *
 * MEJORA: Sistema de depuración avanzado para registrar y mostrar información
 * de estado tanto en el backend como en el frontend. Se activa con la constante
 * FLOWTAX_DEBUG_MODE en el archivo principal del plugin.
 *
 * @since 5.1.0
 */
class Flowtax_Debugger {
    private static $logs = [];
    private static $start_time;

    /**
     * Inicia el temporizador para medir el tiempo de ejecución de las peticiones.
     */
    public static function init() {
        self::$start_time = microtime(true);
    }

    /**
     * Agrega un mensaje al registro de depuración.
     *
     * @param mixed  $message El mensaje o datos a registrar (puede ser un string, array, objeto, etc.).
     * @param string $context Un contexto para el mensaje (p. ej., 'AJAX', 'Validation', 'WP_Query').
     */
    public static function log($message, string $context = 'general') {
        if (!defined('FLOWTAX_DEBUG_MODE') || !FLOWTAX_DEBUG_MODE) {
            return;
        }

        self::$logs[] = [
            'timestamp' => date('H:i:s'),
            'context'   => esc_html($context),
            // Si el mensaje no es un string, se convierte a JSON para su correcta visualización.
            'message'   => is_string($message) ? esc_html($message) : wp_json_encode($message, JSON_PRETTY_PRINT),
        ];
    }

    /**
     * Devuelve todos los registros acumulados durante la petición.
     *
     * @return array La lista de registros.
     */
    public static function get_logs(): array {
        if (!defined('FLOWTAX_DEBUG_MODE') || !FLOWTAX_DEBUG_MODE) {
            return [];
        }
        
        $end_time = microtime(true);
        $execution_time = round(($end_time - self::$start_time) * 1000, 2);

        // Agrega un resumen de rendimiento al final de los logs.
        self::log("Petición AJAX completada en {$execution_time} ms.", 'Performance');
        
        return self::$logs;
    }

    /**
     * Adjunta los registros de depuración a un array de datos de respuesta AJAX.
     *
     * @param array $data Los datos de la respuesta original.
     * @return array Los datos de la respuesta con los registros de depuración añadidos.
     */
    public static function send_logs_in_ajax_response(array $data): array {
        if (defined('FLOWTAX_DEBUG_MODE') && FLOWTAX_DEBUG_MODE && current_user_can('manage_options')) {
            $data['debug_logs'] = self::get_logs();
        }
        return $data;
    }
}

// Inicializa el depurador tan pronto como se carga el archivo para empezar a medir el tiempo.
Flowtax_Debugger::init();
