<?php
/**
 * Flowtax_Assets
 *
 * NOTA: Esta clase se ha vuelto obsoleta. La lógica para cargar assets
 * ahora reside en la función que renderiza el shortcode en el archivo principal
 * del plugin (flowtax.php) para asegurar que los scripts y estilos solo se
 * carguen cuando el shortcode [flowtax_management_system] está presente en la página.
 *
 * @since 5.0.0
 * @version 6.0.0
 */
class Flowtax_Assets {

    public static function init() {
        // La acción 'wp_enqueue_scripts' ya no se engancha aquí.
    }

    /**
     * @deprecated 6.0.0
     */
    public static function enqueue_spa_assets() {
        // Esta función ya no se utiliza.
    }
}
