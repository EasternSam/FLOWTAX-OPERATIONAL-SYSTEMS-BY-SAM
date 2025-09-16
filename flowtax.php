<?php
/**
 * Plugin Name:       Flow Tax Management System (Advanced SPA Edition)
 * Plugin URI:        https://flowtaxmultiservices.com/
 * Description:       Sistema de gestión integral avanzado con arquitectura modular y una interfaz de Single Page Application (SPA) profesional. Ahora funciona con un shortcode.
 * Version:           8.2.0
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

define('FLOWTAX_DEBUG_MODE', true);

final class Flow_Tax_Multiservices_Advanced {

    const VERSION = '8.2.0';
    private static $instance;
    private static $is_spa_page = false;

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
        require_once FLOWTAX_MS_PLUGIN_DIR . 'includes/class-flowtax-cpts.php';
        require_once FLOWTAX_MS_PLUGIN_DIR . 'includes/class-flowtax-meta-boxes.php';
        require_once FLOWTAX_MS_PLUGIN_DIR . 'includes/class-flowtax-ajax-handler.php';
        require_once FLOWTAX_MS_PLUGIN_DIR . 'includes/class-flowtax-validator.php';
        require_once FLOWTAX_MS_PLUGIN_DIR . 'includes/class-flowtax-activity-log.php';
        require_once FLOWTAX_MS_PLUGIN_DIR . 'includes/class-flowtax-reminders.php';
        require_once FLOWTAX_MS_PLUGIN_DIR . 'includes/class-flowtax-user-presence.php';

        if (FLOWTAX_DEBUG_MODE && file_exists(FLOWTAX_MS_PLUGIN_DIR . 'includes/class-flowtax-debugger.php')) {
            require_once FLOWTAX_MS_PLUGIN_DIR . 'includes/class-flowtax-debugger.php';
        }
    }

    private function init_hooks() {
        Flowtax_CPTs::init();
        Flowtax_Meta_Boxes::init();
        Flowtax_Ajax_Handler::init();

        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_shortcode('flowtax_management_system', array($this, 'render_spa_shortcode'));

        add_action('wp', array($this, 'check_for_shortcode'));
        add_filter('show_admin_bar', array($this, 'maybe_hide_admin_bar'));
    }
    
    public function check_for_shortcode() {
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'flowtax_management_system')) {
            self::$is_spa_page = true;
        }
    }

    public function maybe_hide_admin_bar($show) {
        return self::$is_spa_page ? false : $show;
    }

    public function add_admin_menu() {
        $indicator = FLOWTAX_DEBUG_MODE ? ' <span style="color: #f56565; font-weight: bold;">(Debug)</span>' : '';
        add_menu_page('Flow Tax Manager', 'Flow Tax' . $indicator, 'manage_options', 'flow-tax-manager', array($this, 'admin_settings_page'), 'dashicons-businesswoman', 6);
        add_submenu_page('flow-tax-manager', 'Ajustes', 'Ajustes', 'manage_options', 'flow-tax-settings', array($this, 'admin_settings_page'));
    }

    public function admin_settings_page() {
        if (isset($_POST['flowtax_settings_nonce']) && wp_verify_nonce($_POST['flowtax_settings_nonce'], 'flowtax_settings_action')) {
            $settings = [
                'payment_link_clover' => sanitize_url($_POST['payment_link_clover'] ?? ''),
                'payment_link_square' => sanitize_url($_POST['payment_link_square'] ?? ''),
                'from_name' => sanitize_text_field($_POST['from_name'] ?? ''),
                'from_email' => sanitize_email($_POST['from_email'] ?? ''),
                'whatsapp_api_token' => sanitize_text_field($_POST['whatsapp_api_token'] ?? ''),
                'whatsapp_phone_id' => sanitize_text_field($_POST['whatsapp_phone_id'] ?? ''),
                'whatsapp_template_name' => sanitize_text_field($_POST['whatsapp_template_name'] ?? 'reminder'),
            ];
            update_option('flowtax_settings', $settings);
            echo '<div class="notice notice-success is-dismissible"><p>Ajustes guardados.</p></div>';
        }
        $options = get_option('flowtax_settings', []);
        ?>
        <div class="wrap">
            <h1>Ajustes del Sistema de Gestión FlowTax</h1>
            <p>Configura las opciones principales para el funcionamiento del sistema.</p>
            <form method="post" action="">
                <?php wp_nonce_field('flowtax_settings_action', 'flowtax_settings_nonce'); ?>
                
                <h2 class="title">Enlaces de Pago</h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="payment_link_clover">Enlace de Pago (Clover)</label></th>
                        <td><input type="url" id="payment_link_clover" name="payment_link_clover" value="<?php echo esc_attr($options['payment_link_clover'] ?? ''); ?>" class="regular-text"/></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="payment_link_square">Enlace de Pago (Square)</label></th>
                        <td><input type="url" id="payment_link_square" name="payment_link_square" value="<?php echo esc_attr($options['payment_link_square'] ?? ''); ?>" class="regular-text"/></td>
                    </tr>
                </table>

                <h2 class="title">Configuración de Email</h2>
                <table class="form-table">
                     <tr valign="top">
                        <th scope="row"><label for="from_name">Nombre del Remitente</label></th>
                        <td><input type="text" id="from_name" name="from_name" value="<?php echo esc_attr($options['from_name'] ?? get_bloginfo('name')); ?>" class="regular-text"/></td>
                    </tr>
                     <tr valign="top">
                        <th scope="row"><label for="from_email">Email del Remitente</label></th>
                        <td><input type="email" id="from_email" name="from_email" value="<?php echo esc_attr($options['from_email'] ?? get_bloginfo('admin_email')); ?>" class="regular-text"/></td>
                    </tr>
                </table>

                <h2 class="title">API de WhatsApp Business</h2>
                <p>Completa estos campos para activar el envío automático de recordatorios por WhatsApp.</p>
                 <table class="form-table">
                     <tr valign="top">
                        <th scope="row"><label for="whatsapp_api_token">Token de Acceso Permanente</label></th>
                        <td><input type="password" id="whatsapp_api_token" name="whatsapp_api_token" value="<?php echo esc_attr($options['whatsapp_api_token'] ?? ''); ?>" class="regular-text"/></td>
                    </tr>
                     <tr valign="top">
                        <th scope="row"><label for="whatsapp_phone_id">ID del Número de Teléfono</label></th>
                        <td><input type="text" id="whatsapp_phone_id" name="whatsapp_phone_id" value="<?php echo esc_attr($options['whatsapp_phone_id'] ?? ''); ?>" class="regular-text"/></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="whatsapp_template_name">Nombre de la Plantilla</label></th>
                        <td><input type="text" id="whatsapp_template_name" name="whatsapp_template_name" value="<?php echo esc_attr($options['whatsapp_template_name'] ?? 'reminder'); ?>" class="regular-text"/>
                        <p class="description">La plantilla debe tener 4 variables en el cuerpo: {{1}} Nombre Cliente, {{2}} Concepto, {{3}} Monto, {{4}} Link de Pago.</p></td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
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

        wp_enqueue_script('tailwindcss-cdn', 'https://cdn.tailwindcss.com?plugins=forms', [], null, false);
        wp_enqueue_style('flowtax-spa-styles', FLOWTAX_MS_PLUGIN_URL . 'assets/css/spa-styles.css', [], FLOWTAX_MS_VERSION . rand(1,999));
        wp_enqueue_script('flowtax-spa-main', FLOWTAX_MS_PLUGIN_URL . 'assets/js/spa-main.js', [], FLOWTAX_MS_VERSION . rand(1,999), true);

        wp_localize_script('flowtax-spa-main', 'flowtax_ajax', [
            'ajax_url'   => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('flowtax_ajax_nonce'),
            'home_url'   => get_permalink(),
            'debug_mode' => defined('FLOWTAX_DEBUG_MODE') && FLOWTAX_DEBUG_MODE,
            'watchman_mode_status' => (bool) get_user_meta(get_current_user_id(), 'flowtax_watchman_mode_enabled', true)
        ]);
        wp_enqueue_style('fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css');
        
        ob_start();
        $current_user = wp_get_current_user();
        ?>
        <div id="flowtax-container-wrapper" class="antialiased text-slate-700">
            <div id="mobile-menu-overlay" class="fixed inset-0 bg-black/60 z-30 hidden lg:hidden"></div>
            <div class="flex h-screen bg-slate-100">
                <aside id="spa-sidebar" class="w-64 bg-white border-r border-slate-200/80 flex-shrink-0 flex flex-col transition-transform duration-300 fixed lg:static inset-y-0 left-0 z-40 -translate-x-full lg:translate-x-0">
                    <div class="h-16 flex items-center justify-between px-4 border-b border-slate-200/80">
                        <div class="flex items-center gap-2 overflow-hidden">
                             <img src="https://90s.agency/flowtax/wp-content/uploads/2025/09/LOGO-FLOWTAX@150x.png" alt="FlowTax Logo" class="w-auto flex-shrink-0" style="height: 40px;">
                        </div>
                         <button id="close-mobile-menu" class="lg:hidden text-slate-500 hover:text-slate-800">
                            <i class="fas fa-times fa-lg"></i>
                         </button>
                    </div>
                    <nav class="flex-1 px-3 py-4 space-y-1.5">
                        <?php
                        $modules = [
                            ['view' => 'dashboard', 'title' => 'Dashboard', 'icon' => 'fa-solid fa-chart-pie'],
                            ['view' => 'clientes', 'title' => 'Clientes', 'icon' => 'fa-solid fa-users'],
                            ['view' => 'cuentas-por-cobrar', 'title' => 'Cuentas por Cobrar', 'icon' => 'fa-solid fa-file-invoice-dollar'],
                            ['view' => 'impuestos', 'title' => 'Impuestos', 'icon' => 'fa-solid fa-calculator'],
                            ['view' => 'inmigracion', 'title' => 'Inmigración', 'icon' => 'fa-solid fa-flag-usa'],
                            ['view' => 'traducciones', 'title' => 'Traducciones', 'icon' => 'fa-solid fa-language'],
                            ['view' => 'supervision', 'title' => 'Supervisión', 'icon' => 'fa-solid fa-binoculars'],
                            ['view' => 'actividad', 'title' => 'Registro de Actividad', 'icon' => 'fa-solid fa-history'],
                        ];
                        foreach ($modules as $module) {
                            echo '<a href="#" data-spa-link data-view="'.$module['view'].'" class="sidebar-link flex items-center px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100 rounded-md transition-all duration-200">
                                <i class="'.$module['icon'].' fa-fw w-6 text-center mr-2.5 text-slate-400"></i>
                                <span>'.$module['title'].'</span>
                            </a>';
                        }
                        ?>
                    </nav>
                     <div class="px-3 py-3 border-t border-slate-200/80">
                        <div class="mb-2 p-2 rounded-md hover:bg-slate-50">
                             <label for="watchman-mode-toggle" class="flex items-center justify-between cursor-pointer">
                                <span class="flex items-center text-sm font-medium text-slate-600">
                                    <i class="fa-solid fa-shield-halved fa-fw w-6 text-center mr-2.5 text-slate-400"></i>
                                    <span>Modo Vigilante</span>
                                </span>
                                <div class="relative">
                                    <input type="checkbox" id="watchman-mode-toggle" class="sr-only">
                                    <div class="block bg-slate-300 w-10 h-6 rounded-full"></div>
                                    <div class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition"></div>
                                </div>
                            </label>
                        </div>
                        <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="sidebar-link-logout flex items-center px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 rounded-md transition-colors duration-200">
                           <i class="fa-solid fa-arrow-right-from-bracket fa-fw w-6 text-center mr-2.5 text-slate-400"></i>
                           <span>Salir del sistema</span>
                        </a>
                    </div>
                </aside>

                <div class="flex-1 flex flex-col overflow-hidden">
                    <header class="h-16 bg-white/80 backdrop-blur-sm border-b border-slate-200/80 flex items-center justify-between px-4 sm:px-6 flex-shrink-0 sticky top-0 z-20">
                        <button id="open-mobile-menu" class="lg:hidden text-slate-600 hover:text-blue-600">
                            <i class="fas fa-bars fa-lg"></i>
                        </button>
                         <div class="flex items-center ml-auto">
                            <div id="online-users-container" class="flex items-center space-x-[-12px] mr-4 pr-2">
                                <!-- Los avatares de usuarios en línea se insertarán aquí -->
                            </div>
                            <div class="relative mr-4" id="notification-bell-container">
                                <button id="notification-bell-btn" class="text-slate-500 hover:text-slate-800 h-10 w-10 flex items-center justify-center rounded-full hover:bg-slate-200/70 transition-colors">
                                    <i class="fas fa-bell"></i>
                                    <span id="notification-indicator" class="absolute top-2 right-2.5 block h-2 w-2 rounded-full bg-blue-500 ring-2 ring-white hidden"></span>
                                </button>
                                <div id="notification-dropdown" class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl border border-slate-200 z-50 hidden">
                                    <div class="p-3 border-b border-slate-100"><h4 class="font-semibold text-sm text-slate-800">Notificaciones</h4></div>
                                    <div id="notification-list" class="max-h-96 overflow-y-auto"></div>
                                    <div class="p-2 bg-slate-50 border-t border-slate-100 rounded-b-lg">
                                        <a href="#" data-spa-link data-view="actividad" id="view-all-notifications-link" class="block text-center text-sm font-semibold text-blue-600 hover:underline">Ver todo el registro</a>
                                    </div>
                                </div>
                            </div>
                            <span class="text-slate-600 font-medium mr-3 text-sm hidden sm:inline"><?php echo esc_html($current_user->display_name); ?></span>
                            <img class="h-10 w-10 rounded-full object-cover ring-2 ring-offset-1 ring-slate-200" src="<?php echo esc_url(get_avatar_url($current_user->ID)); ?>" alt="User Avatar">
                        </div>
                    </header>
                    <main class="flex-1 overflow-x-hidden overflow-y-auto relative">
                         <div class="absolute inset-0 bg-grid-slate-200/50 [mask-image:linear-gradient(to_bottom,white,transparent)]"></div>
                         <div id="flowtax-app-root" class="relative"></div>
                    </main>
                </div>
            </div>
            <div id="notification-area" class="fixed top-5 right-5 z-[10000] w-80"></div>
            
            <div id="doc-viewer-modal" class="fixed inset-0 bg-black/70 z-50 items-center justify-center p-2 sm:p-4 hidden">
                <div class="bg-white rounded-lg shadow-2xl w-full max-w-6xl h-full max-h-[95vh] flex flex-col">
                    <header class="flex items-center justify-between p-3 border-b bg-slate-50 rounded-t-lg flex-shrink-0">
                        <h3 id="viewer-title" class="font-semibold text-slate-800 truncate pr-4 text-sm sm:text-base"></h3>
                        <div class="flex items-center space-x-4">
                            <div id="image-zoom-controls" class="hidden items-center space-x-2">
                                <button data-zoom="out" class="h-8 w-8 rounded-md text-slate-500 hover:bg-slate-200 flex items-center justify-center" title="Alejar"><i class="fas fa-search-minus"></i></button>
                                <span id="zoom-level-display" class="text-sm font-medium text-slate-600 w-12 text-center">100%</span>
                                <button data-zoom="in" class="h-8 w-8 rounded-md text-slate-500 hover:bg-slate-200 flex items-center justify-center" title="Acercar"><i class="fas fa-search-plus"></i></button>
                            </div>
                            <button id="close-viewer-btn" class="text-slate-500 hover:text-red-600"><i class="fas fa-times fa-lg"></i></button>
                        </div>
                    </header>
                    <div class="flex-1 bg-slate-200 overflow-hidden relative">
                        <div id="image-viewer-container" class="w-full h-full overflow-auto flex items-center justify-center p-4 hidden"><img id="image-viewer-img" src="" alt="Vista previa de imagen" class="max-w-full max-h-full transition-transform duration-200 origin-center"></div>
                        <iframe id="doc-viewer-iframe" src="about:blank" class="w-full h-full border-0 hidden"></iframe>
                    </div>
                </div>
            </div>

            <div id="reminder-modal" class="fixed inset-0 bg-black/60 z-50 items-center justify-center p-4 hidden">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-sm">
                    <header class="flex items-center justify-between p-4 border-b">
                        <h3 class="font-semibold text-slate-800 text-lg">Enviar Recordatorio</h3>
                        <button id="close-reminder-modal" class="text-slate-400 hover:text-red-500"><i class="fas fa-times fa-lg"></i></button>
                    </header>
                    <div class="p-6 text-center">
                        <p class="text-slate-600 mb-6">¿Cómo quieres enviar el recordatorio de pago?</p>
                        <div class="flex flex-col space-y-3">
                            <button data-reminder-method="whatsapp" class="w-full flex items-center justify-center p-3 rounded-lg bg-green-500 text-white font-bold hover:bg-green-600 transition-colors"><i class="fab fa-whatsapp fa-lg mr-3"></i>Enviar por WhatsApp</button>
                            <button data-reminder-method="email" class="w-full flex items-center justify-center p-3 rounded-lg bg-blue-500 text-white font-bold hover:bg-blue-600 transition-colors"><i class="fas fa-envelope fa-lg mr-3"></i>Enviar por Email</button>
                            <button data-reminder-method="all" class="w-full flex items-center justify-center p-3 rounded-lg bg-slate-600 text-white font-bold hover:bg-slate-700 transition-colors"><i class="fas fa-paper-plane fa-lg mr-3"></i>Enviar por Ambos</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="payment-modal" class="fixed inset-0 bg-black/60 z-50 items-center justify-center p-4 hidden">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-sm">
                    <header class="flex items-center justify-between p-4 border-b">
                        <h3 class="font-semibold text-slate-800 text-lg">Registrar Pago/Abono</h3>
                        <button id="close-payment-modal" class="text-slate-400 hover:text-red-500"><i class="fas fa-times fa-lg"></i></button>
                    </header>
                    <div class="p-6">
                        <div class="text-center mb-4">
                            <p class="text-sm text-slate-500">Deuda Total</p>
                            <p id="payment-modal-total" class="text-3xl font-bold text-slate-800">USD$ 0.00</p>
                             <p class="text-sm text-slate-500 mt-2">Restante: <span id="payment-modal-restante" class="font-semibold">USD$ 0.00</span></p>
                        </div>
                        <form id="payment-form">
                            <input type="hidden" id="payment-modal-deuda-id" name="deuda_id">
                            <div>
                                <label for="payment-modal-monto" id="payment-modal-currency-label" class="form-label">Monto a abonar (USD)*</label>
                                <input type="text" id="payment-modal-monto" name="monto_abono" class="form-input w-full text-center text-lg" placeholder="0.00" required>
                            </div>
                            <div class="mt-6">
                                <button type="submit" class="w-full btn-primary"><i class="fas fa-check-circle mr-2"></i>Registrar Pago</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

Flow_Tax_Multiservices_Advanced::get_instance();
