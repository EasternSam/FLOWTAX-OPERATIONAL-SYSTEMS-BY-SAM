<?php
/**
 * Plugin Name:       Flow Tax Management System (Advanced SPA Edition)
 * Plugin URI:        https://flowtaxmultiservices.com/
 * Description:       Sistema de gestión integral avanzado con arquitectura modular y una interfaz de Single Page Application (SPA) profesional.
 * Version:           5.2.0
 * Author:            Samuel Diaz Pilier (Mejorado por Gemini)
 * Author URI:        https://90s.agency/sam
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       flowtax-ms
 * Domain Path:       /languages
 */

if (!defined('WPINC')) {
    die;
}

// Control centralizado para activar/desactivar el modo de depuración.
define('FLOWTAX_DEBUG_MODE', true);

/**
 * Clase principal del plugin.
 * Organiza la carga de todos los componentes del sistema.
 */
final class Flow_Tax_Multiservices_Advanced {

    const VERSION = '5.2.0';
    private static $instance;

    private function __construct() {
        $this->setup_constants();
        $this->load_dependencies();
        $this->init_hooks();
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function setup_constants() {
        define('FLOWTAX_MS_VERSION', self::VERSION);
        define('FLOWTAX_MS_PLUGIN_DIR', plugin_dir_path(__FILE__));
        define('FLOWTAX_MS_PLUGIN_URL', plugin_dir_url(__FILE__));
    }

    private function load_dependencies() {
        // Estructura de archivos modular
        require_once FLOWTAX_MS_PLUGIN_DIR . 'includes/class-flowtax-cpts.php';
        require_once FLOWTAX_MS_PLUGIN_DIR . 'includes/class-flowtax-meta-boxes.php';
        require_once FLOWTAX_MS_PLUGIN_DIR . 'includes/class-flowtax-spa-renderer.php';
        require_once FLOWTAX_MS_PLUGIN_DIR . 'includes/class-flowtax-ajax-handler.php';
        require_once FLOWTAX_MS_PLUGIN_DIR . 'includes/class-flowtax-assets.php';
        require_once FLOWTAX_MS_PLUGIN_DIR . 'includes/class-flowtax-validator.php';

        $debugger_path = FLOWTAX_MS_PLUGIN_DIR . 'includes/class-flowtax-debugger.php';
        if (FLOWTAX_DEBUG_MODE && file_exists($debugger_path)) {
            require_once $debugger_path;
        }
    }

    private function init_hooks() {
        // Inicializa todas las clases y sus respectivos hooks
        Flowtax_CPTs::init();
        Flowtax_Meta_Boxes::init();
        Flowtax_SPARenderer::init();
        Flowtax_Ajax_Handler::init();
        Flowtax_Assets::init();

        // Hooks de activación y desactivación
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        add_action('admin_menu', array($this, 'add_admin_menu'));
    }

    public function add_admin_menu() {
        $indicator = FLOWTAX_DEBUG_MODE ? ' <span style="color: #f56565; font-weight: bold;">(Debug)</span>' : '';
        add_menu_page('Flow Tax Manager', 'Flow Tax' . $indicator, 'manage_options', 'flow-tax-manager', array($this, 'admin_dashboard_page'), 'dashicons-businesswoman', 6);
    }

    public function admin_dashboard_page() {
        echo '<div class="wrap"><h1>Acceso Rápido al Sistema</h1>';
        echo '<p>Toda la gestión se realiza a través de la página de inicio. Esta interfaz ofrece una experiencia más rápida e intuitiva.</p>';
        echo '<a href="' . home_url('/inicio/') . '" class="button button-primary" style="font-size: 16px; padding: 10px 20px; height: auto;">Ir al Sistema de Gestión</a></div>';
    }

    public function activate() {
        Flowtax_CPTs::register_all();
        Flowtax_CPTs::insert_initial_terms();
        
        if (!get_page_by_path('inicio')) {
            wp_insert_post([
                'post_title'   => 'Inicio Gestión',
                'post_content' => '<!-- FlowTax SPA Entry Point -->',
                'post_status'  => 'publish',
                'post_author'  => 1,
                'post_type'    => 'page',
                'post_name'    => 'inicio'
            ]);
        }
        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }
}

// Iniciar el plugin
Flow_Tax_Multiservices_Advanced::get_instance();
