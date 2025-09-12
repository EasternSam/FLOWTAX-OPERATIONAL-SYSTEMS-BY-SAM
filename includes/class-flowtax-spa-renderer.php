<?php
/**
 * Flowtax_SPARenderer
 *
 * NOTA: Esta clase se ha vuelto obsoleta. La lógica para renderizar el HTML
 * de la aplicación de una sola página (SPA) ha sido movida a la función
 * que maneja el shortcode en el archivo principal del plugin (flowtax.php).
 * Esto permite que la aplicación se pueda incrustar en cualquier página
 * en lugar de estar fija en una URL específica.
 *
 * @since 5.0.0
 * @version 6.0.0
 */
class Flowtax_SPARenderer {
    public static function init() {
        // La acción 'template_redirect' ya no se engancha aquí.
    }

    /**
     * @deprecated 6.0.0
     */
    public static function render_spa_page() {
        // Esta función ya no se utiliza.
    }
}
