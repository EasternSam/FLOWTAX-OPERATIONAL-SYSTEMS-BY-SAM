<?php
/**
 * Plugin Name:       Flow Tax Management System (Advanced SPA Edition)
 * Plugin URI:        https://flowtaxmultiservices.com/
 * Description:       Sistema de gestión integral avanzado con arquitectura modular y una interfaz de Single Page Application (SPA) profesional. Ahora funciona con un shortcode.
 * Version:           6.0.0
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

    const VERSION = '6.0.0';
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
        // Inicializa las clases necesarias
        Flowtax_CPTs::init();
        Flowtax_Meta_Boxes::init();
        Flowtax_Ajax_Handler::init();
        
        // Los renderizadores y assets ahora se manejan en el shortcode
        Flowtax_SPARenderer::init();
        Flowtax_Assets::init();


        // Hooks de activación y desactivación
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Registrar el shortcode que renderizará la SPA
        add_shortcode('flowtax_management_system', array($this, 'render_spa_shortcode'));
    }

    public function add_admin_menu() {
        $indicator = FLOWTAX_DEBUG_MODE ? ' <span style="color: #f56565; font-weight: bold;">(Debug)</span>' : '';
        add_menu_page('Flow Tax Manager', 'Flow Tax' . $indicator, 'manage_options', 'flow-tax-manager', array($this, 'admin_dashboard_page'), 'dashicons-businesswoman', 6);
    }

    public function admin_dashboard_page() {
        echo '<div class="wrap"><h1>Sistema de Gestión FlowTax</h1>';
        echo '<p>Para utilizar el sistema, añade el siguiente shortcode a cualquier página de tu sitio:</p>';
        echo '<p><input type="text" value="[flowtax_management_system]" readonly onfocus="this.select();" style="width: 300px; text-align: center; font-size: 16px; padding: 8px;"></p>';
        echo '<p>Hemos creado una página de <a href="' . esc_url(home_url('/inicio/')) . '" class="button button-secondary">"Inicio Gestión"</a> por ti, que ya contiene este shortcode.</p></div>';
    }

    public function activate() {
        Flowtax_CPTs::register_all();
        Flowtax_CPTs::insert_initial_terms();
        
        if (!get_page_by_path('inicio')) {
            wp_insert_post([
                'post_title'   => 'Inicio Gestión',
                'post_content' => '[flowtax_management_system]',
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

    public function render_spa_shortcode($atts) {
        if (!current_user_can('manage_options')) {
            return '<p>Debes iniciar sesión con una cuenta de administrador para usar este sistema. <a href="' . esc_url(wp_login_url(get_permalink())) . '">Iniciar Sesión</a></p>';
        }

        // --- Carga de Assets (CSS y JS) ---
        wp_enqueue_script('tailwindcss-cdn', 'https://cdn.tailwindcss.com?plugins=forms', [], null, false);
        wp_enqueue_style('flowtax-spa-styles', FLOWTAX_MS_PLUGIN_URL . 'assets/css/spa-styles.css', [], FLOWTAX_MS_VERSION);
        wp_enqueue_script('flowtax-spa-main', FLOWTAX_MS_PLUGIN_URL . 'assets/js/spa-main.js', [], FLOWTAX_MS_VERSION, true);

        // Pasamos datos de PHP a JavaScript. Es crucial usar get_permalink() para que la SPA sepa en qué página está.
        $ajax_params = [
            'ajax_url'   => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('flowtax_ajax_nonce'),
            'home_url'   => get_permalink(),
            'debug_mode' => defined('FLOWTAX_DEBUG_MODE') && FLOWTAX_DEBUG_MODE
        ];
        wp_localize_script('flowtax-spa-main', 'flowtax_ajax', $ajax_params);
        wp_enqueue_style('fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css');


        // --- Renderizado del HTML de la SPA ---
        $current_user = wp_get_current_user();
        
        ob_start();
        ?>
        <div id="flowtax-container-wrapper">
            <div class="flex h-screen bg-slate-50" style="min-height: 850px; max-width: 100%;">
                <!-- Sidebar -->
                <aside id="spa-sidebar" class="w-56 bg-white border-r border-slate-200 flex-shrink-0 flex flex-col transition-all duration-300">
                    <div class="h-16 flex items-center justify-center border-b border-slate-200">
                         <h1 class="text-xl font-bold text-blue-600">FlowTax</h1>
                    </div>
                    <nav class="flex-1 px-3 py-4 space-y-1">
                        <?php
                        $modules = [
                            ['view' => 'dashboard', 'title' => 'Dashboard', 'icon' => 'fa-solid fa-chart-pie'],
                            ['view' => 'clientes', 'title' => 'Clientes', 'icon' => 'fa-solid fa-users'],
                            ['view' => 'impuestos', 'title' => 'Impuestos', 'icon' => 'fa-solid fa-calculator'],
                            ['view' => 'inmigracion', 'title' => 'Inmigración', 'icon' => 'fa-solid fa-flag-usa'],
                            ['view' => 'payroll', 'title' => 'Payroll', 'icon' => 'fa-solid fa-money-check-dollar'],
                            ['view' => 'traducciones', 'title' => 'Traducciones', 'icon' => 'fa-solid fa-language'],
                            ['view' => 'transacciones', 'title' => 'Pagos y Cheques', 'icon' => 'fa-solid fa-cash-register'],
                        ];
                        foreach ($modules as $module) {
                            echo '<a href="#" data-spa-link data-view="'.$module['view'].'" class="sidebar-link flex items-center px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-blue-600 rounded-md transition-colors duration-200">
                                <i class="'.$module['icon'].' fa-fw w-6 text-center mr-3 text-slate-400"></i>
                                <span>'.$module['title'].'</span>
                            </a>';
                        }
                        ?>
                    </nav>
                     <div class="px-3 py-3 border-t border-slate-200">
                        <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="sidebar-link-logout flex items-center px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100 rounded-md transition-colors duration-200">
                           <i class="fa-solid fa-arrow-right-from-bracket fa-fw w-6 text-center mr-3 text-slate-400"></i>
                           <span>Salir del sistema</span>
                        </a>
                    </div>
                </aside>

                <!-- Main Content -->
                <div class="flex-1 flex flex-col overflow-hidden">
                    <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-end px-6 flex-shrink-0">
                         <div class="flex items-center">
                            <span class="text-slate-600 font-medium mr-3 text-sm">Hola, <?php echo esc_html($current_user->display_name); ?></span>
                            <img class="h-9 w-9 rounded-full object-cover ring-2 ring-offset-2 ring-slate-200" src="<?php echo esc_url(get_avatar_url($current_user->ID)); ?>" alt="User Avatar">
                        </div>
                    </header>
                    <main class="flex-1 overflow-x-hidden overflow-y-auto bg-slate-100">
                         <div id="flowtax-app-root">
                            <div class="flex justify-center items-center h-full">
                                <i class="fas fa-spinner fa-spin fa-2x text-slate-400"></i>
                            </div>
                        </div>
                    </main>
                </div>
            </div>
            <div id="notification-area" class="fixed top-5 right-5 z-[10000] w-80"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Iniciar el plugin
Flow_Tax_Multiservices_Advanced::get_instance();


