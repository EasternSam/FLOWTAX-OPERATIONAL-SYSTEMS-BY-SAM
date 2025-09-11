<?php
/**
 * Flowtax_Assets
 *
 * Se encarga de registrar y cargar los archivos CSS y JavaScript
 * necesarios para la Single Page Application (SPA).
 *
 * @since 5.0.0
 */
class Flowtax_Assets {

    public static function init() {
        // Engancha la función de carga de assets en el hook correcto de WordPress.
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_spa_assets'));
    }

    public static function enqueue_spa_assets() {
        // Solo carga los archivos en la página designada como 'inicio'.
        if (!is_page('inicio')) {
            return;
        }

        // Carga la hoja de estilos principal de la SPA.
        wp_enqueue_style(
            'flowtax-spa-styles',
            FLOWTAX_MS_PLUGIN_URL . 'assets/css/spa-styles.css',
            [],
            FLOWTAX_MS_VERSION
        );

        // Carga el archivo JavaScript principal de la SPA.
        wp_enqueue_script(
            'flowtax-spa-main',
            FLOWTAX_MS_PLUGIN_URL . 'assets/js/spa-main.js',
            [],
            FLOWTAX_MS_VERSION,
            true // Carga el script en el footer.
        );

        // Prepara un array de datos para pasar de PHP a JavaScript.
        $ajax_params = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('flowtax_ajax_nonce'),
            'home_url' => home_url('/inicio/')
        ];

        // MEJORA: Añade la configuración del modo de depuración para el frontend.
        // Esto permite que el JavaScript sepa si debe mostrar la consola de depuración.
        $ajax_params['debug_mode'] = defined('FLOWTAX_DEBUG_MODE') && FLOWTAX_DEBUG_MODE;

        // Hace que los datos de $ajax_params estén disponibles en JavaScript
        // a través del objeto `flowtax_ajax`.
        wp_localize_script(
            'flowtax-spa-main',
            'flowtax_ajax',
            $ajax_params
        );
    }
}
