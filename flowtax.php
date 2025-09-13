<?php
/**
 * Plugin Name:       Flow Tax Management System (Advanced SPA Edition)
 * Plugin URI:        https://flowtaxmultiservices.com/
 * Description:       Sistema de gestión integral avanzado con arquitectura modular y una interfaz de Single Page Application (SPA) profesional. Ahora funciona con un shortcode.
 * Version:           6.1.0
 * Author:            Samuel Diaz Pilier
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

    const VERSION = '6.1.0';
    private static $instance;
    private static $is_spa_page = false; // Flag para PWA y otras lógicas de página

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

        // Hooks para PWA y ocultar la barra de admin
        add_action('init', array($this, 'handle_manifest_request'));
        add_action('wp', array($this, 'check_for_shortcode')); // Hook para detectar el shortcode
        add_action('wp_head', array($this, 'add_pwa_head_tags'));
        add_filter('show_admin_bar', array($this, 'maybe_hide_admin_bar'));
    }
    
    /**
     * Revisa si la página actual contiene el shortcode para activar la lógica de SPA.
     */
    public function check_for_shortcode() {
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'flowtax_management_system')) {
            self::$is_spa_page = true;
        }
    }

    /**
     * Oculta la barra de admin de WordPress si estamos en la página de la SPA.
     */
    public function maybe_hide_admin_bar($show) {
        if (self::$is_spa_page) {
            return false;
        }
        return $show;
    }

    public function handle_manifest_request() {
        if (isset($_GET['flowtax_manifest'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'name' => 'FlowTax Management System',
                'short_name' => 'FlowTax',
                'start_url' => home_url('/inicio/'),
                'display' => 'standalone',
                'background_color' => '#f1f5f9',
                'theme_color' => '#2563eb',
                'description' => 'Sistema de Gestión FlowTax.',
                'icons' => [
                    [
                        'src' => 'https://placehold.co/192x192/2563eb/ffffff?text=FT',
                        'sizes' => '192x192',
                        'type' => 'image/png',
                    ],
                    [
                        'src' => 'https://placehold.co/512x512/2563eb/ffffff?text=FT',
                        'sizes' => '512x512',
                        'type' => 'image/png',
                    ],
                ],
            ]);
            wp_die();
        }
    }

    public function add_pwa_head_tags() {
        if (!self::$is_spa_page) {
            return;
        }
        ?>
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="FlowTax">
        <meta name="theme-color" content="#2563eb">
        <link rel="manifest" href="<?php echo esc_url(home_url('/?flowtax_manifest=1')); ?>">
        <link rel="apple-touch-icon" sizes="180x180" href="https://placehold.co/180x180/2563eb/ffffff?text=FT">
        <?php
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
        // La detección ahora se hace en el hook 'wp', ya no se necesita la línea `self::$is_spa_page = true;` aquí.

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
             <!-- Overlay para menú móvil -->
            <div id="mobile-menu-overlay" class="fixed inset-0 bg-black/60 z-30 hidden lg:hidden"></div>
            <div class="flex h-screen bg-slate-50" style="min-height: 850px; max-width: 100%;">
                <!-- Sidebar -->
                <aside id="spa-sidebar" class="w-64 bg-white border-r border-slate-200 flex-shrink-0 flex flex-col transition-transform duration-300 fixed lg:static inset-y-0 left-0 z-40 -translate-x-full lg:translate-x-0">
                    <div class="h-16 flex items-center justify-between px-6 border-b border-slate-200">
                         <h1 class="text-xl font-bold text-blue-600">FlowTax</h1>
                         <button id="close-mobile-menu" class="lg:hidden text-slate-500 hover:text-slate-800">
                            <i class="fas fa-times fa-lg"></i>
                         </button>
                    </div>
                    <nav class="flex-1 px-4 py-4 space-y-1">
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
                    <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 sm:px-6 flex-shrink-0">
                        <button id="open-mobile-menu" class="lg:hidden text-slate-600 hover:text-blue-600">
                            <i class="fas fa-bars fa-lg"></i>
                        </button>
                         <div class="flex items-center ml-auto">
                            <span class="text-slate-600 font-medium mr-3 text-sm hidden sm:inline">Hola, <?php echo esc_html($current_user->display_name); ?></span>
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
            
            <!-- Document Viewer Modal -->
            <div id="doc-viewer-modal" class="fixed inset-0 bg-black/70 z-50 items-center justify-center p-2 sm:p-4 hidden">
                <div class="bg-white rounded-lg shadow-2xl w-full max-w-6xl h-full max-h-[95vh] flex flex-col">
                    <header class="flex items-center justify-between p-3 border-b bg-slate-50 rounded-t-lg flex-shrink-0">
                        <h3 id="viewer-title" class="font-semibold text-slate-800 truncate pr-4 text-sm sm:text-base"></h3>
                        <div class="flex items-center space-x-4">
                            <!-- Controles de Zoom para imágenes -->
                            <div id="image-zoom-controls" class="hidden items-center space-x-2">
                                <button data-zoom="out" class="h-8 w-8 rounded-md text-slate-500 hover:bg-slate-200 flex items-center justify-center transition-colors" title="Alejar"><i class="fas fa-search-minus"></i></button>
                                <span id="zoom-level-display" class="text-sm font-medium text-slate-600 w-12 text-center">100%</span>
                                <button data-zoom="in" class="h-8 w-8 rounded-md text-slate-500 hover:bg-slate-200 flex items-center justify-center transition-colors" title="Acercar"><i class="fas fa-search-plus"></i></button>
                            </div>
                            <button id="close-viewer-btn" class="text-slate-500 hover:text-red-600 transition-colors">
                                <i class="fas fa-times fa-lg"></i>
                            </button>
                        </div>
                    </header>
                    <div class="flex-1 bg-slate-200 overflow-hidden relative">
                        <div id="image-viewer-container" class="w-full h-full overflow-auto flex items-center justify-center p-4 hidden">
                           <img id="image-viewer-img" src="" alt="Vista previa de imagen" class="max-w-full max-h-full transition-transform duration-200 origin-center">
                        </div>
                        <iframe id="doc-viewer-iframe" src="about:blank" class="w-full h-full border-0 hidden"></iframe>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Iniciar el plugin
Flow_Tax_Multiservices_Advanced::get_instance();

