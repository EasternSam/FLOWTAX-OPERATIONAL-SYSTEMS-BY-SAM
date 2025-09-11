<?php
/**
 * Plugin Name:       Flow Tax Management System (SPA Edition)
 * Plugin URI:        https://flowtaxmultiservices.com/
 * Description:       Sistema de gestión integral que funciona como una Single Page Application (SPA) en el frontend.
 * Version:           4.0.1
 * Author:            Samuel Diaz Pilier
 * Author URI:        https://90s.agency/sam
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       flowtax-ms
 * Domain Path:       /languages
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Clase principal del plugin.
 */
final class Flow_Tax_Multiservices {

    const VERSION = '4.0.1';
    private static $instance;

    private function __construct() {
        $this->setup_constants();
        $this->init_hooks();
    }
    
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function setup_constants() {
        define( 'FLOWTAX_MS_VERSION', self::VERSION );
        define( 'FLOWTAX_MS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
    }

    private function init_hooks() {
        add_action( 'init', array( $this, 'register_post_types' ) );
        add_action( 'init', array( $this, 'register_taxonomies' ) );
        
        // AJAX Handlers for SPA functionality
        add_action( 'wp_ajax_flowtax_get_view', array( $this, 'ajax_get_view_handler' ) );
        add_action( 'wp_ajax_flowtax_save_form', array( $this, 'ajax_save_form_handler' ) );

        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_all_meta_data' ) );

        add_shortcode( 'flowtax_dashboard', array( $this, 'render_dashboard_shortcode' ) );
        add_action( 'template_redirect', array( $this, 'render_full_width_page_if_inicio' ) );

        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
    }

    // --- SECCIÓN DE CUSTOM POST TYPES Y TAXONOMÍAS ---
    public function register_post_types() {
        // CPT para Clientes
        $labels_cliente = ['name' => 'Clientes', 'singular_name' => 'Cliente', 'add_new' => 'Añadir Nuevo', 'add_new_item' => 'Añadir Nuevo Cliente', 'edit_item' => 'Editar Cliente', 'all_items' => 'Todos los Clientes'];
        register_post_type( 'cliente', ['labels' => $labels_cliente, 'public' => false, 'show_ui' => true, 'show_in_menu' => 'flow-tax-manager', 'supports' => ['title', 'editor', 'thumbnail'], 'menu_icon' => 'dashicons-id-alt']);

        // CPT para Casos de Servicio (Genérico)
        $labels_caso = ['name' => 'Casos de Servicio', 'singular_name' => 'Caso', 'add_new' => 'Añadir Nuevo', 'add_new_item' => 'Añadir Nuevo Caso', 'edit_item' => 'Editar Caso', 'all_items' => 'Todos los Casos'];
        register_post_type( 'caso_servicio', ['labels' => $labels_caso, 'public' => false, 'show_ui' => true, 'show_in_menu' => 'flow-tax-manager', 'supports' => ['title', 'editor'], 'menu_icon' => 'dashicons-briefcase']);
        
        // --- MÓDULO PAYROLL ---
        $labels_empleado = ['name' => 'Empleados', 'singular_name' => 'Empleado', 'add_new' => 'Añadir Nuevo', 'add_new_item' => 'Añadir Nuevo Empleado', 'edit_item' => 'Editar Empleado', 'all_items' => 'Todos los Empleados'];
        register_post_type( 'empleado', ['labels' => $labels_empleado, 'public' => false, 'show_ui' => true, 'show_in_menu' => 'flow-tax-manager', 'supports' => ['title'], 'menu_icon' => 'dashicons-admin-users']);
        $labels_nomina = ['name' => 'Nóminas', 'singular_name' => 'Nómina', 'add_new' => 'Añadir Nueva', 'add_new_item' => 'Añadir Nueva Nómina', 'edit_item' => 'Editar Nómina', 'all_items' => 'Todas las Nóminas'];
        register_post_type( 'nomina', ['labels' => $labels_nomina, 'public' => false, 'show_ui' => true, 'show_in_menu' => 'flow-tax-manager', 'supports' => ['title', 'editor'], 'menu_icon' => 'dashicons-calculator']);

        // --- MÓDULO IMPUESTOS ---
        $labels_impuestos = ['name' => 'Declaraciones de Impuestos', 'singular_name' => 'Declaración', 'menu_name' => 'Impuestos'];
        register_post_type( 'impuestos', ['labels' => $labels_impuestos, 'public' => false, 'show_ui' => true, 'show_in_menu' => 'flow-tax-manager', 'supports' => ['title'], 'menu_icon' => 'dashicons-media-spreadsheet']);

        // --- MÓDULO INMIGRACIÓN ---
        $labels_peticion = ['name' => 'Peticiones Familiares', 'singular_name' => 'Petición Familiar', 'menu_name' => 'Peticiones'];
        register_post_type( 'peticion_familiar', ['labels' => $labels_peticion, 'public' => false, 'show_ui' => true, 'show_in_menu' => 'flow-tax-manager', 'supports' => ['title'], 'menu_icon' => 'dashicons-groups']);
        $labels_ciudadania = ['name' => 'Casos de Ciudadanía', 'singular_name' => 'Caso de Ciudadanía', 'menu_name' => 'Ciudadanía'];
        register_post_type( 'ciudadania', ['labels' => $labels_ciudadania, 'public' => false, 'show_ui' => true, 'show_in_menu' => 'flow-tax-manager', 'supports' => ['title'], 'menu_icon' => 'dashicons-flag']);
        $labels_renovacion = ['name' => 'Renovación de Residencia', 'singular_name' => 'Renovación', 'menu_name' => 'Renovación Residencia'];
        register_post_type( 'renovacion_residencia', ['labels' => $labels_renovacion, 'public' => false, 'show_ui' => true, 'show_in_menu' => 'flow-tax-manager', 'supports' => ['title'], 'menu_icon' => 'dashicons-id']);

        // --- MÓDULO TRADUCCIONES ---
        $labels_traduccion = ['name' => 'Traducciones', 'singular_name' => 'Traducción'];
        register_post_type( 'traduccion', ['labels' => $labels_traduccion, 'public' => false, 'show_ui' => true, 'show_in_menu' => 'flow-tax-manager', 'supports' => ['title'], 'menu_icon' => 'dashicons-translation']);
        
        // --- MÓDULO TRANSACCIONES ---
        $labels_transaccion = ['name' => 'Transacciones', 'singular_name' => 'Transacción', 'menu_name' => 'Pagos y Cheques'];
        register_post_type( 'transaccion', ['labels' => $labels_transaccion, 'public' => false, 'show_ui' => true, 'show_in_menu' => 'flow-tax-manager', 'supports' => ['title'], 'menu_icon' => 'dashicons-money-alt']);
    }

    public function register_taxonomies() {
        register_taxonomy( 'tipo_servicio', 'caso_servicio', ['hierarchical' => true, 'labels' => ['name' => 'Tipos de Servicio'], 'show_ui' => true, 'show_admin_column' => true]);
        register_taxonomy( 'estado_caso', 'caso_servicio', ['hierarchical' => true, 'labels' => ['name' => 'Estados del Caso'], 'show_ui' => true, 'show_admin_column' => true]);
        register_taxonomy( 'estado_impuesto', 'impuestos', ['hierarchical' => true, 'labels' => ['name' => 'Estado Declaración'], 'show_ui' => true, 'show_admin_column' => true]);
        register_taxonomy( 'estado_inmigracion', ['peticion_familiar', 'ciudadania', 'renovacion_residencia'], ['hierarchical' => true, 'labels' => ['name' => 'Estado del Caso'], 'show_ui' => true, 'show_admin_column' => true]);
    }

    // --- SECCIÓN DE MENÚ Y PÁGINA DE ADMINISTRACIÓN ---
    public function add_admin_menu() {
        add_menu_page('Flow Tax Manager', 'Flow Tax', 'manage_options', 'flow-tax-manager', array( $this, 'admin_dashboard_page' ), 'dashicons-businesswoman', 6);
    }
    
    public function admin_dashboard_page() {
        echo '<h1>Acceso Rápido al Sistema</h1>';
        echo '<p>Toda la gestión se realiza a través de la página de inicio. Haz clic en el botón de abajo para ir.</p>';
        echo '<a href="' . home_url('/inicio/') . '" class="button button-primary">Ir al Sistema de Gestión</a>';
    }

    // --- SECCIÓN DE CAMPOS PERSONALIZADOS (META BOXES) ---
    public function add_meta_boxes() {
        add_meta_box('cliente_details', 'Detalles del Cliente', array($this, 'render_cliente_meta_box'), 'cliente', 'normal', 'high');
        add_meta_box('caso_details', 'Detalles del Caso', array($this, 'render_caso_meta_box'), 'caso_servicio', 'normal', 'high');
        add_meta_box('empleado_details', 'Detalles del Empleado', array($this, 'render_empleado_meta_box'), 'empleado', 'normal', 'high');
        add_meta_box('nomina_details', 'Detalles de la Nómina', array($this, 'render_nomina_meta_box'), 'nomina', 'normal', 'high');
        add_meta_box('impuestos_details', 'Detalles de la Declaración de Impuestos', array($this, 'render_impuestos_meta_box'), 'impuestos', 'normal', 'high');
        add_meta_box('peticion_familiar_details', 'Detalles de la Petición Familiar', array($this, 'render_peticion_familiar_meta_box'), 'peticion_familiar', 'normal', 'high');
        add_meta_box('ciudadania_details', 'Detalles del Caso de Ciudadanía', array($this, 'render_ciudadania_meta_box'), 'ciudadania', 'normal', 'high');
        add_meta_box('renovacion_residencia_details', 'Detalles de la Renovación de Residencia', array($this, 'render_renovacion_residencia_meta_box'), 'renovacion_residencia', 'normal', 'high');
        add_meta_box('traduccion_details', 'Detalles de la Traducción', array($this, 'render_traduccion_meta_box'), 'traduccion', 'normal', 'high');
        add_meta_box('transaccion_details', 'Detalles de la Transacción', array($this, 'render_transaccion_meta_box'), 'transaccion', 'normal', 'high');
    }

    // --- RENDERIZADO DE META BOXES ---
    
    public function render_cliente_meta_box($post) {
        wp_nonce_field('cliente_details_nonce', 'cliente_nonce');
        $telefono = $post ? get_post_meta($post->ID, '_telefono', true) : '';
        $email = $post ? get_post_meta($post->ID, '_email', true) : '';
        ?>
        <p>
            <label for="telefono"><strong>Teléfono:</strong></label><br>
            <input type="text" id="telefono" name="telefono" value="<?php echo esc_attr($telefono); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1">
        </p>
        <p>
            <label for="email"><strong>Email:</strong></label><br>
            <input type="email" id="email" name="email" value="<?php echo esc_attr($email); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1">
        </p>
        <?php
    }
    
    public function render_caso_meta_box($post) {
        wp_nonce_field('caso_details_nonce', 'caso_nonce');
        $cliente_id = $post ? get_post_meta($post->ID, '_cliente_id', true) : '';
        $clientes = get_posts(['post_type' => 'cliente', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        ?>
        <p>
            <label for="cliente_id"><strong>Asignar a Cliente:</strong></label><br>
            <select name="cliente_id" id="cliente_id" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1">
                <option value="">Seleccionar un cliente...</option>
                <?php foreach ($clientes as $cliente) : ?>
                    <option value="<?php echo $cliente->ID; ?>" <?php selected($cliente_id, $cliente->ID); ?>>
                        <?php echo esc_html($cliente->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php
    }
    
    public function render_empleado_meta_box($post) {
        wp_nonce_field('empleado_details_nonce', 'empleado_nonce');
        $cliente_id = $post ? get_post_meta($post->ID, '_cliente_id', true) : '';
        $salario = $post ? get_post_meta($post->ID, '_salario', true) : '';
        $frecuencia = $post ? get_post_meta($post->ID, '_frecuencia_pago', true) : '';
        $clientes = get_posts(['post_type' => 'cliente', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        ?>
        <p>
            <label for="cliente_id"><strong>Empresa / Cliente:</strong></label><br>
            <select name="cliente_id" id="cliente_id" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1">
                <option value="">Seleccionar cliente...</option>
                <?php foreach ($clientes as $cliente) : ?>
                    <option value="<?php echo $cliente->ID; ?>" <?php selected($cliente_id, $cliente->ID); ?>>
                        <?php echo esc_html($cliente->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="salario"><strong>Salario / Tarifa por Hora:</strong></label><br>
            <input type="number" step="0.01" id="salario" name="salario" value="<?php echo esc_attr($salario); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1">
        </p>
        <p>
            <label for="frecuencia_pago"><strong>Frecuencia de Pago:</strong></label><br>
            <select name="frecuencia_pago" id="frecuencia_pago" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1">
                <option value="semanal" <?php selected($frecuencia, 'semanal'); ?>>Semanal</option>
                <option value="quincenal" <?php selected($frecuencia, 'quincenal'); ?>>Quincenal</option>
                <option value="mensual" <?php selected($frecuencia, 'mensual'); ?>>Mensual</option>
            </select>
        </p>
        <?php
    }

    public function render_nomina_meta_box($post) {
        wp_nonce_field('nomina_details_nonce', 'nomina_nonce');
        $cliente_id = $post ? get_post_meta($post->ID, '_cliente_id', true) : '';
        $fecha_pago = $post ? get_post_meta($post->ID, '_fecha_pago', true) : '';
        $monto_total = $post ? get_post_meta($post->ID, '_monto_total', true) : '';
        $clientes = get_posts(['post_type' => 'cliente', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        ?>
         <p>
            <label for="cliente_id"><strong>Cliente:</strong></label><br>
            <select name="cliente_id" id="cliente_id" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1">
                <option value="">Seleccionar cliente...</option>
                <?php foreach ($clientes as $cliente) : ?>
                    <option value="<?php echo $cliente->ID; ?>" <?php selected($cliente_id, $cliente->ID); ?>>
                        <?php echo esc_html($cliente->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="fecha_pago"><strong>Fecha de Pago:</strong></label><br>
            <input type="date" id="fecha_pago" name="fecha_pago" value="<?php echo esc_attr($fecha_pago); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1">
        </p>
        <p>
            <label for="monto_total"><strong>Monto Total de la Nómina:</strong></label><br>
            <input type="number" step="0.01" id="monto_total" name="monto_total" value="<?php echo esc_attr($monto_total); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1">
        </p>
        <?php
    }

    public function render_impuestos_meta_box($post) {
        wp_nonce_field('impuestos_details_nonce', 'impuestos_nonce');
        $cliente_id = $post ? get_post_meta($post->ID, '_cliente_id', true) : '';
        $clientes = get_posts(['post_type' => 'cliente', 'numberposts' => -1, 'orderby'=>'title', 'order'=>'ASC']);
        $ano_fiscal = $post ? get_post_meta($post->ID, '_ano_fiscal', true) : '';
        $tipo_declaracion = $post ? get_post_meta($post->ID, '_tipo_declaracion', true) : '';
        ?>
        <div class="space-y-6">
            <div class="p-4 border rounded-lg bg-gray-50 space-y-4">
                <h3 class="font-bold text-lg border-b pb-2">Información General</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                     <div>
                        <label for="cliente_id" class="font-semibold">Cliente Principal:</label>
                        <select name="cliente_id" id="cliente_id" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1">
                            <option value="">-- Seleccionar --</option>
                            <?php foreach($clientes as $cliente) : ?>
                                <option value="<?php echo $cliente->ID; ?>" <?php selected($cliente_id, $cliente->ID); ?>><?php echo esc_html($cliente->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="ano_fiscal" class="font-semibold">Año Fiscal:</label>
                        <input type="number" name="ano_fiscal" value="<?php echo esc_attr($ano_fiscal); ?>" min="2000" max="<?php echo date('Y'); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1">
                    </div>
                    <div>
                        <label for="tipo_declaracion" class="font-semibold">Tipo de Declaración:</label>
                        <select name="tipo_declaracion" id="tipo_declaracion" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1">
                            <option value="personal" <?php selected($tipo_declaracion, 'personal'); ?>>Personal</option>
                            <option value="negocios" <?php selected($tipo_declaracion, 'negocios'); ?>>Negocios</option>
                            <option value="mixta" <?php selected($tipo_declaracion, 'mixta'); ?>>Mixta</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="p-4 border rounded-lg bg-gray-50"><label class="font-semibold">Ingresos (W2, 1099, etc.):</label><textarea name="ingresos_detalle" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1 h-28" placeholder="Ej: W2 de Acme Corp - $50,000&#10;1099-NEC de Pro Services - $15,000"><?php echo esc_textarea($post ? get_post_meta($post->ID, '_ingresos_detalle', true) : ''); ?></textarea></div>
            <div class="p-4 border rounded-lg bg-gray-50"><label class="font-semibold">Deducciones y Créditos:</label><textarea name="deducciones_detalle" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1 h-28" placeholder="Ej: Intereses de préstamo estudiantil - $2,500&#10;Donación a caridad - $500"><?php echo esc_textarea($post ? get_post_meta($post->ID, '_deducciones_detalle', true) : ''); ?></textarea></div>
            <div class="p-4 border rounded-lg bg-gray-50"><label class="font-semibold">Información de Negocio (Schedule C):</label><textarea name="negocio_detalle" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1 h-28" placeholder="Ingresos Brutos: $100,000&#10;Gastos de Publicidad: $5,000&#10;Millas de Vehículo: 10,000"><?php echo esc_textarea($post ? get_post_meta($post->ID, '_negocio_detalle', true) : ''); ?></textarea></div>
            <div class="p-4 border rounded-lg bg-gray-50">
                <h3 class="font-bold text-lg border-b pb-2">Resumen Financiero</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
                    <div><label class="font-semibold">Reembolso Estimado ($):</label><input type="number" step="0.01" name="reembolso_estimado" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_reembolso_estimado', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                    <div><label class="font-semibold">Monto Adeudado ($):</label><input type="number" step="0.01" name="monto_adeudado" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_monto_adeudado', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_peticion_familiar_meta_box($post) {
        wp_nonce_field('peticion_familiar_nonce', 'peticion_familiar_nonce');
        $cliente_id = $post ? get_post_meta($post->ID, '_cliente_id', true) : '';
        $clientes = get_posts(['post_type' => 'cliente', 'numberposts' => -1, 'orderby'=>'title', 'order'=>'ASC']);
        ?>
        <div class="space-y-6">
            <div class="p-4 border rounded-lg bg-gray-50 space-y-4">
                 <h3 class="font-bold text-lg border-b pb-2">Peticionario y Beneficiario</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div><label class="font-semibold">Peticionario (Cliente):</label><select name="cliente_id" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"><option value="">-- Seleccionar --</option><?php foreach($clientes as $cliente) : ?><option value="<?php echo $cliente->ID; ?>" <?php selected($cliente_id, $cliente->ID); ?>><?php echo esc_html($cliente->post_title); ?></option><?php endforeach; ?></select></div>
                    <div><label class="font-semibold">Nombre del Beneficiario:</label><input type="text" name="beneficiario_nombre" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_beneficiario_nombre', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                    <div><label class="font-semibold">Relación:</label><input type="text" name="relacion" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_relacion', true) : ''); ?>" placeholder="Ej: Esposo(a)" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                    <div><label class="font-semibold">Fecha Nacimiento Beneficiario:</label><input type="date" name="beneficiario_dob" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_beneficiario_dob', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                </div>
            </div>
            <div class="p-4 border rounded-lg bg-gray-50 space-y-4">
                 <h3 class="font-bold text-lg border-b pb-2">Detalles del Caso (USCIS)</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                     <div><label class="font-semibold">Número de Recibo:</label><input type="text" name="uscis_receipt" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_uscis_receipt', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                     <div><label class="font-semibold">Fecha de Prioridad:</label><input type="date" name="priority_date" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_priority_date', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                     <div><label class="font-semibold">Centro de Servicio:</label><input type="text" name="service_center" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_service_center', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                </div>
            </div>
            <div class="p-4 border rounded-lg bg-gray-50 space-y-4">
                 <h3 class="font-bold text-lg border-b pb-2">Línea de Tiempo</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div><label class="font-semibold">Fecha Envío:</label><input type="date" name="fecha_envio" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_fecha_envio', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                    <div><label class="font-semibold">Fecha Biométricos:</label><input type="date" name="fecha_biometricos" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_fecha_biometricos', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                    <div><label class="font-semibold">Fecha Entrevista:</label><input type="date" name="fecha_entrevista" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_fecha_entrevista', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                    <div><label class="font-semibold">Fecha Aprobación:</label><input type="date" name="fecha_aprobacion" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_fecha_aprobacion', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function render_ciudadania_meta_box($post) {
        wp_nonce_field('ciudadania_nonce', 'ciudadania_nonce');
        $cliente_id = $post ? get_post_meta($post->ID, '_cliente_id', true) : '';
        $clientes = get_posts(['post_type' => 'cliente', 'numberposts' => -1, 'orderby'=>'title', 'order'=>'ASC']);
        ?>
        <div class="space-y-6">
            <div class="p-4 border rounded-lg bg-gray-50 space-y-4">
                 <h3 class="font-bold text-lg border-b pb-2">Información del Aplicante</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div><label class="font-semibold">Aplicante (Cliente):</label><select name="cliente_id" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"><option value="">-- Seleccionar --</option><?php foreach($clientes as $cliente) : ?><option value="<?php echo $cliente->ID; ?>" <?php selected($cliente_id, $cliente->ID); ?>><?php echo esc_html($cliente->post_title); ?></option><?php endforeach; ?></select></div>
                    <div><label class="font-semibold">Número de Residente (A-Number):</label><input type="text" name="a_number" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_a_number', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                </div>
            </div>
            <div class="p-4 border rounded-lg bg-gray-50 space-y-4">
                 <h3 class="font-bold text-lg border-b pb-2">Detalles del Caso (USCIS N-400)</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                     <div><label class="font-semibold">Número de Recibo:</label><input type="text" name="uscis_receipt" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_uscis_receipt', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                     <div><label class="font-semibold">Centro de Servicio:</label><input type="text" name="service_center" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_service_center', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                </div>
            </div>
            <div class="p-4 border rounded-lg bg-gray-50 space-y-4">
                 <h3 class="font-bold text-lg border-b pb-2">Línea de Tiempo</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div><label class="font-semibold">Fecha Envío:</label><input type="date" name="fecha_envio" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_fecha_envio', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                    <div><label class="font-semibold">Fecha Biométricos:</label><input type="date" name="fecha_biometricos" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_fecha_biometricos', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                    <div><label class="font-semibold">Fecha Entrevista:</label><input type="date" name="fecha_entrevista" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_fecha_entrevista', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                    <div><label class="font-semibold">Fecha Juramentación:</label><input type="date" name="fecha_juramentacion" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_fecha_juramentacion', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_renovacion_residencia_meta_box($post) {
        wp_nonce_field('renovacion_residencia_nonce', 'renovacion_residencia_nonce');
        $cliente_id = $post ? get_post_meta($post->ID, '_cliente_id', true) : '';
        $clientes = get_posts(['post_type' => 'cliente', 'numberposts' => -1, 'orderby'=>'title', 'order'=>'ASC']);
        ?>
        <div class="space-y-6">
            <div class="p-4 border rounded-lg bg-gray-50 space-y-4">
                 <h3 class="font-bold text-lg border-b pb-2">Información del Aplicante</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div><label class="font-semibold">Aplicante (Cliente):</label><select name="cliente_id" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"><option value="">-- Seleccionar --</option><?php foreach($clientes as $cliente) : ?><option value="<?php echo $cliente->ID; ?>" <?php selected($cliente_id, $cliente->ID); ?>><?php echo esc_html($cliente->post_title); ?></option><?php endforeach; ?></select></div>
                    <div><label class="font-semibold">A-Number:</label><input type="text" name="a_number" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_a_number', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                    <div><label class="font-semibold">Expiración Tarjeta:</label><input type="date" name="card_expiry" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_card_expiry', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                </div>
            </div>
            <div class="p-4 border rounded-lg bg-gray-50">
                 <h3 class="font-bold text-lg border-b pb-2">Detalles del Caso (USCIS I-90)</h3>
                <label class="font-semibold">Número de Recibo:</label><input type="text" name="uscis_receipt" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_uscis_receipt', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1">
            </div>
            <div class="p-4 border rounded-lg bg-gray-50 space-y-4">
                 <h3 class="font-bold text-lg border-b pb-2">Línea de Tiempo</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div><label class="font-semibold">Fecha Envío:</label><input type="date" name="fecha_envio" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_fecha_envio', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                    <div><label class="font-semibold">Fecha Biométricos:</label><input type="date" name="fecha_biometricos" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_fecha_biometricos', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                    <div><label class="font-semibold">Tarjeta Enviada:</label><input type="date" name="fecha_tarjeta_enviada" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_fecha_tarjeta_enviada', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_traduccion_meta_box($post) {
        wp_nonce_field('traduccion_nonce', 'traduccion_nonce');
        $cliente_id = $post ? get_post_meta($post->ID, '_cliente_id', true) : '';
        $clientes = get_posts(['post_type' => 'cliente', 'numberposts' => -1, 'orderby'=>'title', 'order'=>'ASC']);
        ?>
        <div class="space-y-6">
            <div class="p-4 border rounded-lg bg-gray-50 space-y-4">
                 <h3 class="font-bold text-lg border-b pb-2">Detalles del Documento</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="lg:col-span-2"><label class="font-semibold">Cliente:</label><select name="cliente_id" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"><option value="">-- Seleccionar --</option><?php foreach($clientes as $cliente) : ?><option value="<?php echo $cliente->ID; ?>" <?php selected($cliente_id, $cliente->ID); ?>><?php echo esc_html($cliente->post_title); ?></option><?php endforeach; ?></select></div>
                    <div><label class="font-semibold">Idioma Original:</label><input type="text" name="idioma_origen" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_idioma_origen', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                    <div><label class="font-semibold">Idioma Destino:</label><input type="text" name="idioma_destino" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_idioma_destino', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                </div>
            </div>
            <div class="p-4 border rounded-lg bg-gray-50 space-y-4">
                 <h3 class="font-bold text-lg border-b pb-2">Costos y Fechas</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div><label class="font-semibold"># de Páginas:</label><input type="number" name="num_paginas" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_num_paginas', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                    <div><label class="font-semibold">Costo Total ($):</label><input type="number" step="0.01" name="costo_total" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_costo_total', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                    <div><label class="font-semibold">Fecha de Entrega:</label><input type="date" name="fecha_entrega" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_fecha_entrega', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                    <div><label class="font-semibold">Estado del Pago:</label><select name="estado_pago" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"><option value="pendiente" <?php selected($post ? get_post_meta($post->ID, '_estado_pago', true) : '', 'pendiente'); ?>>Pendiente</option><option value="pagado" <?php selected($post ? get_post_meta($post->ID, '_estado_pago', true) : '', 'pagado'); ?>>Pagado</option></select></div>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_transaccion_meta_box($post) {
        wp_nonce_field('transaccion_nonce', 'transaccion_nonce');
        ?>
        <div class="space-y-6">
            <div class="p-4 border rounded-lg bg-gray-50 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="font-semibold">Tipo de Transacción:</label>
                        <select name="tipo_transaccion" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1">
                            <option value="">-- Seleccionar --</option>
                            <option value="pago_bill" <?php selected($post ? get_post_meta($post->ID, '_tipo_transaccion', true) : '', 'pago_bill'); ?>>Pago de Bill</option>
                            <option value="cambio_cheque" <?php selected($post ? get_post_meta($post->ID, '_tipo_transaccion', true) : '', 'cambio_cheque'); ?>>Cambio de Cheque</option>
                        </select>
                    </div>
                    <div><label class="font-semibold">Fecha:</label><input type="date" name="fecha_transaccion" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_fecha_transaccion', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                </div>
            </div>
             <div class="p-4 border rounded-lg bg-gray-50 space-y-4">
                 <h3 class="font-bold text-lg border-b pb-2">Detalles</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div><label class="font-semibold">Cliente/Compañía:</label><input type="text" name="nombre_entidad" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_nombre_entidad', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                    <div><label class="font-semibold">Monto ($):</label><input type="number" step="0.01" name="monto_transaccion" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_monto_transaccion', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                    <div><label class="font-semibold">Comisión / Fee ($):</label><input type="number" step="0.01" name="comision" value="<?php echo esc_attr($post ? get_post_meta($post->ID, '_comision', true) : ''); ?>" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1"></div>
                </div>
            </div>
        </div>
        <?php
    }

    // --- GUARDADO DE METADATOS (Centralizado) ---
    public function save_all_meta_data($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ( empty($_POST) ) return;
        
        // Nonce verification happens in the AJAX handler OR here if submitted from backend
        $post_type = get_post_type($post_id);
        $nonce_action = $post_type . '_details_nonce';
        $nonce_name = $post_type . '_nonce';

        if ( isset($_POST[$nonce_name]) && wp_verify_nonce($_POST[$nonce_name], $nonce_action) ) {
            if (!current_user_can('edit_post', $post_id)) return;
            // The rest of the saving logic for meta fields
            switch($post_type) {
                case 'cliente':
                    if (isset($_POST['telefono'])) update_post_meta($post_id, '_telefono', sanitize_text_field($_POST['telefono']));
                    if (isset($_POST['email'])) update_post_meta($post_id, '_email', sanitize_email($_POST['email']));
                    break;
                case 'caso_servicio':
                    if (isset($_POST['cliente_id'])) update_post_meta($post_id, '_cliente_id', intval($_POST['cliente_id']));
                    break;
                case 'empleado':
                    if (isset($_POST['cliente_id'])) update_post_meta($post_id, '_cliente_id', intval($_POST['cliente_id']));
                    if (isset($_POST['salario'])) update_post_meta($post_id, '_salario', sanitize_text_field($_POST['salario']));
                    if (isset($_POST['frecuencia_pago'])) update_post_meta($post_id, '_frecuencia_pago', sanitize_text_field($_POST['frecuencia_pago']));
                    break;
                case 'nomina':
                    if (isset($_POST['cliente_id'])) update_post_meta($post_id, '_cliente_id', intval($_POST['cliente_id']));
                    if (isset($_POST['fecha_pago'])) update_post_meta($post_id, '_fecha_pago', sanitize_text_field($_POST['fecha_pago']));
                    if (isset($_POST['monto_total'])) update_post_meta($post_id, '_monto_total', sanitize_text_field($_POST['monto_total']));
                    break;
                case 'impuestos':
                    if (isset($_POST['cliente_id'])) update_post_meta($post_id, '_cliente_id', intval($_POST['cliente_id']));
                    if (isset($_POST['ano_fiscal'])) update_post_meta($post_id, '_ano_fiscal', sanitize_text_field($_POST['ano_fiscal']));
                    if (isset($_POST['tipo_declaracion'])) update_post_meta($post_id, '_tipo_declaracion', sanitize_text_field($_POST['tipo_declaracion']));
                    if (isset($_POST['ingresos_detalle'])) update_post_meta($post_id, '_ingresos_detalle', sanitize_textarea_field($_POST['ingresos_detalle']));
                    if (isset($_POST['deducciones_detalle'])) update_post_meta($post_id, '_deducciones_detalle', sanitize_textarea_field($_POST['deducciones_detalle']));
                    if (isset($_POST['negocio_detalle'])) update_post_meta($post_id, '_negocio_detalle', sanitize_textarea_field($_POST['negocio_detalle']));
                    if (isset($_POST['reembolso_estimado'])) update_post_meta($post_id, '_reembolso_estimado', sanitize_text_field($_POST['reembolso_estimado']));
                    if (isset($_POST['monto_adeudado'])) update_post_meta($post_id, '_monto_adeudado', sanitize_text_field($_POST['monto_adeudado']));
                    break;
                case 'peticion_familiar':
                    if (isset($_POST['cliente_id'])) update_post_meta($post_id, '_cliente_id', intval($_POST['cliente_id']));
                    if (isset($_POST['beneficiario_nombre'])) update_post_meta($post_id, '_beneficiario_nombre', sanitize_text_field($_POST['beneficiario_nombre']));
                    if (isset($_POST['relacion'])) update_post_meta($post_id, '_relacion', sanitize_text_field($_POST['relacion']));
                    if (isset($_POST['beneficiario_dob'])) update_post_meta($post_id, '_beneficiario_dob', sanitize_text_field($_POST['beneficiario_dob']));
                    if (isset($_POST['uscis_receipt'])) update_post_meta($post_id, '_uscis_receipt', sanitize_text_field($_POST['uscis_receipt']));
                    if (isset($_POST['priority_date'])) update_post_meta($post_id, '_priority_date', sanitize_text_field($_POST['priority_date']));
                    if (isset($_POST['service_center'])) update_post_meta($post_id, '_service_center', sanitize_text_field($_POST['service_center']));
                    if (isset($_POST['fecha_envio'])) update_post_meta($post_id, '_fecha_envio', sanitize_text_field($_POST['fecha_envio']));
                    if (isset($_POST['fecha_biometricos'])) update_post_meta($post_id, '_fecha_biometricos', sanitize_text_field($_POST['fecha_biometricos']));
                    if (isset($_POST['fecha_entrevista'])) update_post_meta($post_id, '_fecha_entrevista', sanitize_text_field($_POST['fecha_entrevista']));
                    if (isset($_POST['fecha_aprobacion'])) update_post_meta($post_id, '_fecha_aprobacion', sanitize_text_field($_POST['fecha_aprobacion']));
                    break;
                case 'ciudadania':
                    if (isset($_POST['cliente_id'])) update_post_meta($post_id, '_cliente_id', intval($_POST['cliente_id']));
                    if (isset($_POST['a_number'])) update_post_meta($post_id, '_a_number', sanitize_text_field($_POST['a_number']));
                    if (isset($_POST['uscis_receipt'])) update_post_meta($post_id, '_uscis_receipt', sanitize_text_field($_POST['uscis_receipt']));
                    if (isset($_POST['service_center'])) update_post_meta($post_id, '_service_center', sanitize_text_field($_POST['service_center']));
                    if (isset($_POST['fecha_envio'])) update_post_meta($post_id, '_fecha_envio', sanitize_text_field($_POST['fecha_envio']));
                    if (isset($_POST['fecha_biometricos'])) update_post_meta($post_id, '_fecha_biometricos', sanitize_text_field($_POST['fecha_biometricos']));
                    if (isset($_POST['fecha_entrevista'])) update_post_meta($post_id, '_fecha_entrevista', sanitize_text_field($_POST['fecha_entrevista']));
                    if (isset($_POST['fecha_juramentacion'])) update_post_meta($post_id, '_fecha_juramentacion', sanitize_text_field($_POST['fecha_juramentacion']));
                    break;
                case 'renovacion_residencia':
                    if (isset($_POST['cliente_id'])) update_post_meta($post_id, '_cliente_id', intval($_POST['cliente_id']));
                    if (isset($_POST['a_number'])) update_post_meta($post_id, '_a_number', sanitize_text_field($_POST['a_number']));
                    if (isset($_POST['card_expiry'])) update_post_meta($post_id, '_card_expiry', sanitize_text_field($_POST['card_expiry']));
                    if (isset($_POST['uscis_receipt'])) update_post_meta($post_id, '_uscis_receipt', sanitize_text_field($_POST['uscis_receipt']));
                    if (isset($_POST['fecha_envio'])) update_post_meta($post_id, '_fecha_envio', sanitize_text_field($_POST['fecha_envio']));
                    if (isset($_POST['fecha_biometricos'])) update_post_meta($post_id, '_fecha_biometricos', sanitize_text_field($_POST['fecha_biometricos']));
                    if (isset($_POST['fecha_tarjeta_enviada'])) update_post_meta($post_id, '_fecha_tarjeta_enviada', sanitize_text_field($_POST['fecha_tarjeta_enviada']));
                    break;
                case 'traduccion':
                    if (isset($_POST['cliente_id'])) update_post_meta($post_id, '_cliente_id', intval($_POST['cliente_id']));
                    if (isset($_POST['idioma_origen'])) update_post_meta($post_id, '_idioma_origen', sanitize_text_field($_POST['idioma_origen']));
                    if (isset($_POST['idioma_destino'])) update_post_meta($post_id, '_idioma_destino', sanitize_text_field($_POST['idioma_destino']));
                    if (isset($_POST['num_paginas'])) update_post_meta($post_id, '_num_paginas', sanitize_text_field($_POST['num_paginas']));
                    if (isset($_POST['costo_total'])) update_post_meta($post_id, '_costo_total', sanitize_text_field($_POST['costo_total']));
                    if (isset($_POST['fecha_entrega'])) update_post_meta($post_id, '_fecha_entrega', sanitize_text_field($_POST['fecha_entrega']));
                    if (isset($_POST['estado_pago'])) update_post_meta($post_id, '_estado_pago', sanitize_text_field($_POST['estado_pago']));
                    break;
                case 'transaccion':
                    if (isset($_POST['tipo_transaccion'])) update_post_meta($post_id, '_tipo_transaccion', sanitize_text_field($_POST['tipo_transaccion']));
                    if (isset($_POST['nombre_entidad'])) update_post_meta($post_id, '_nombre_entidad', sanitize_text_field($_POST['nombre_entidad']));
                    if (isset($_POST['monto_transaccion'])) update_post_meta($post_id, '_monto_transaccion', sanitize_text_field($_POST['monto_transaccion']));
                    if (isset($_POST['comision'])) update_post_meta($post_id, '_comision', sanitize_text_field($_POST['comision']));
                    if (isset($_POST['fecha_transaccion'])) update_post_meta($post_id, '_fecha_transaccion', sanitize_text_field($_POST['fecha_transaccion']));
                    break;
            }
        }
    }

    // --- SECCIÓN DE PÁGINA DE INICIO PERSONALIZADA ---
    public function render_full_width_page_if_inicio() {
        if ( ! is_page('inicio') ) return;
        if ( ! current_user_can('manage_options') ) {
            wp_redirect( wp_login_url( get_permalink() ) );
            exit;
        }
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo( 'charset' ); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php wp_title( '|', true, 'right' ); ?></title>
            <script src="https://cdn.tailwindcss.com"></script>
            <?php wp_head(); ?>
            <style> #wpadminbar { display: none; } </style>
        </head>
        <body <?php body_class( 'bg-gray-100' ); ?>>
        <?php
            if ( function_exists( 'wp_body_open' ) ) wp_body_open();
            
            $current_user = wp_get_current_user();
            ?>
            <div class="wrap bg-gray-100 p-6 font-sans min-h-screen">
                <header class="bg-blue-800 text-white p-6 rounded-lg shadow-md mb-8">
                    <div class="flex justify-between items-center">
                        <div>
                            <h1 class="text-3xl font-bold">¡Bienvenido, <?php echo esc_html($current_user->display_name); ?>!</h1>
                            <p class="mt-1">Sistema de Gestión Integral Flow Tax.</p>
                        </div>
                        <a href="<?php echo wp_logout_url(home_url()); ?>" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded">Salir</a>
                    </div>
                </header>
                <main id="flowtax-main-content">
                    <?php // El contenido se cargará aquí vía AJAX ?>
                </main>
            </div>

            <script type="text/javascript">
                const flowtax_ajax = {
                    ajax_url: "<?php echo admin_url('admin-ajax.php'); ?>",
                    nonce: "<?php echo wp_create_nonce('flowtax_ajax_nonce'); ?>"
                };

                document.addEventListener('DOMContentLoaded', function() {
                    const mainContent = document.getElementById('flowtax-main-content');
                    const initialUrl = new URL(window.location);
                    
                    function showLoading() {
                        mainContent.innerHTML = '<div class="text-center py-10"><p class="text-lg font-semibold">Cargando...</p></div>';
                    }

                    async function loadView(url) {
                        showLoading();
                        try {
                            const response = await fetch(url);
                            if (!response.ok) throw new Error('Network response was not ok.');
                            const html = await response.text();
                            mainContent.innerHTML = html;
                        } catch (error) {
                            mainContent.innerHTML = '<p class="text-red-500">Error al cargar la vista. Por favor, intente de nuevo.</p>';
                            console.error('Fetch error:', error);
                        }
                    }

                    function handleNavigation(event) {
                        const target = event.target.closest('a');
                        if (target && target.href && (target.href.includes('?view=') || target.href.includes('/inicio/'))) {
                            event.preventDefault();
                            const url = new URL(target.href);
                            history.pushState(null, '', url.toString());
                            const ajaxUrl = new URL(flowtax_ajax.ajax_url);
                            ajaxUrl.searchParams.set('action', 'flowtax_get_view');
                            ajaxUrl.searchParams.set('nonce', flowtax_ajax.nonce);
                            url.searchParams.forEach((value, key) => ajaxUrl.searchParams.set(key, value));
                            loadView(ajaxUrl);
                        }
                    }

                    async function handleFormSubmit(event) {
                        const form = event.target.closest('#flowtax-frontend-form');
                        if (form) {
                            event.preventDefault();
                            const submitButton = form.querySelector('button[type="submit"]');
                            submitButton.disabled = true;
                            submitButton.textContent = 'Guardando...';

                            const formData = new FormData(form);
                            formData.append('action', 'flowtax_save_form');

                            try {
                                const response = await fetch(flowtax_ajax.ajax_url, {
                                    method: 'POST',
                                    body: formData
                                });
                                const result = await response.json();
                                if (result.success) {
                                    const newUrl = new URL(result.data.redirect_url);
                                    history.pushState(null, '', newUrl.toString());
                                    const ajaxUrl = new URL(flowtax_ajax.ajax_url);
                                    ajaxUrl.searchParams.set('action', 'flowtax_get_view');
                                    ajaxUrl.searchParams.set('nonce', flowtax_ajax.nonce);
                                    newUrl.searchParams.forEach((value, key) => ajaxUrl.searchParams.set(key, value));
                                    loadView(ajaxUrl);
                                } else {
                                    throw new Error(result.data.message || 'Error desconocido al guardar.');
                                }
                            } catch (error) {
                                alert('Error al guardar: ' + error.message);
                                submitButton.disabled = false;
                                submitButton.textContent = form.querySelector('input[name="post_id"]').value > 0 ? 'Actualizar' : 'Guardar';
                            }
                        }
                    }

                    mainContent.addEventListener('click', handleNavigation);
                    mainContent.addEventListener('submit', handleFormSubmit);

                    window.addEventListener('popstate', function() {
                        const popUrl = new URL(window.location);
                        const ajaxUrl = new URL(flowtax_ajax.ajax_url);
                        ajaxUrl.searchParams.set('action', 'flowtax_get_view');
                        ajaxUrl.searchParams.set('nonce', flowtax_ajax.nonce);
                        popUrl.searchParams.forEach((value, key) => ajaxUrl.searchParams.set(key, value));
                        loadView(ajaxUrl);
                    });
                    
                    // Carga inicial
                    const ajaxUrl = new URL(flowtax_ajax.ajax_url);
                    ajaxUrl.searchParams.set('action', 'flowtax_get_view');
                    ajaxUrl.searchParams.set('nonce', flowtax_ajax.nonce);
                    initialUrl.searchParams.forEach((value, key) => ajaxUrl.searchParams.set(key, value));
                    if (!initialUrl.searchParams.get('view')) {
                         ajaxUrl.searchParams.set('view', 'main');
                    }
                    loadView(ajaxUrl);
                });
            </script>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
        exit();
    }

    // --- SECCIÓN DEL DASHBOARD Y SHORTCODE ---
    public function render_dashboard_shortcode() {
        if (!current_user_can('manage_options')) {
            return '<p>No tienes permiso para ver esta página.</p>';
        }
        // El shortcode ahora solo es un punto de entrada. La renderización principal la hace render_full_width_page_if_inicio
        return ''; 
    }
    
    // --- MANEJADORES AJAX ---
    
    public function ajax_get_view_handler() {
        if (!check_ajax_referer('flowtax_ajax_nonce', 'nonce', false)) {
            wp_send_json_error('Nonce inválido.');
            wp_die();
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos.');
            wp_die();
        }

        $view = isset($_REQUEST['view']) ? sanitize_key($_REQUEST['view']) : 'main';
        $action = isset($_REQUEST['flowtax_action']) ? sanitize_key($_REQUEST['flowtax_action']) : 'list'; // <-- CAMBIO CLAVE
        $id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
        
        switch ($view) {
            case 'impuestos':
                ($action === 'new' || ($action === 'edit' && $id > 0)) ? $this->render_frontend_form('impuestos', $id) : $this->render_dashboard_view_template('Módulo de Impuestos', 'impuestos', ['Declaración', 'Cliente', 'Estado'], 'Nueva Declaración');
                break;
            case 'inmigracion':
                if ($action === 'new') {
                     $post_type = isset($_REQUEST['post_type']) ? sanitize_key($_REQUEST['post_type']) : '';
                     if ($post_type) {
                         $this->render_frontend_form($post_type, 0);
                     } else {
                         $this->render_new_inmigracion_choice();
                     }
                } elseif ($action === 'edit' && $id > 0) {
                    $post_type_for_edit = isset($_REQUEST['post_type']) ? sanitize_key($_REQUEST['post_type']) : get_post_type($id);
                    if (in_array($post_type_for_edit, ['peticion_familiar', 'ciudadania', 'renovacion_residencia'])) {
                        $this->render_frontend_form($post_type_for_edit, $id);
                    }
                } else {
                    $this->render_dashboard_view_template('Módulo de Inmigración', ['peticion_familiar', 'ciudadania', 'renovacion_residencia'], ['Caso', 'Cliente', 'Estado'], 'Nuevo Caso');
                }
                break;
            case 'payroll':
                 ($action === 'new' || ($action === 'edit' && $id > 0)) ? $this->render_frontend_form('nomina', $id) : $this->render_dashboard_view_template('Módulo de Payroll', 'nomina', ['Nómina', 'Cliente', 'Fecha de Pago'], 'Registrar Nómina');
                break;
            case 'traducciones':
                ($action === 'new' || ($action === 'edit' && $id > 0)) ? $this->render_frontend_form('traduccion', $id) : $this->render_dashboard_view_template('Módulo de Traducciones', 'traduccion', ['Documento', 'Cliente', 'Estado Pago'], 'Nueva Traducción');
                break;
             case 'transacciones':
                ($action === 'new' || ($action === 'edit' && $id > 0)) ? $this->render_frontend_form('transaccion', $id) : $this->render_dashboard_view_template('Módulo de Transacciones', 'transaccion', ['Transacción', 'Entidad', 'Tipo'], 'Nueva Transacción');
                break;
            case 'casos':
                ($action === 'new' || ($action === 'edit' && $id > 0)) ? $this->render_frontend_form('caso_servicio', $id) : $this->render_dashboard_view_template('Servicios Generales', 'caso_servicio', ['Caso', 'Cliente', 'Estado'], 'Nuevo Caso');
                break;
             case 'clientes':
                ($action === 'new' || ($action === 'edit' && $id > 0)) ? $this->render_frontend_form('cliente', $id) : $this->render_dashboard_view_template('Módulo de Clientes', 'cliente', ['Nombre', 'Email', 'Teléfono'], 'Nuevo Cliente');
                break;
            default:
                $this->render_main_dashboard_view();
                break;
        }
        wp_die(); // Termina la ejecución de AJAX
    }

    public function ajax_save_form_handler() {
        if ( !isset($_POST['flowtax_form_nonce']) || !wp_verify_nonce($_POST['flowtax_form_nonce'], 'flowtax_save_post') ) {
             wp_send_json_error(['message' => 'Falló la verificación de seguridad.']);
             return;
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No tienes permiso para realizar esta acción.']);
            return;
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';
        $post_title = isset($_POST['post_title']) ? sanitize_text_field($_POST['post_title']) : '';

        if(empty($post_type) || empty($post_title)) {
            wp_send_json_error(['message' => 'Faltan datos requeridos (tipo o título).']);
            return;
        }

        $post_data = ['post_type' => $post_type, 'post_title' => $post_title, 'post_status' => 'publish'];

        if ($post_id > 0) {
            $post_data['ID'] = $post_id;
            $new_post_id = wp_update_post($post_data, true);
        } else {
            $new_post_id = wp_insert_post($post_data, true);
        }

        if (is_wp_error($new_post_id)) {
            wp_send_json_error(['message' => 'Hubo un error al guardar: ' . $new_post_id->get_error_message()]);
            return;
        }
        
        // save_all_meta_data() se disparará automáticamente con el hook 'save_post'
        
        // Guardar taxonomías
        $taxonomies = get_object_taxonomies($post_type, 'objects');
        foreach ($taxonomies as $tax) {
            if (isset($_POST[$tax->name]) && !empty($_POST[$tax->name])) {
                wp_set_post_terms($new_post_id, intval($_POST[$tax->name]), $tax->name);
            }
        }
        
        $view = $this->get_view_for_post_type($post_type);
        $redirect_url = add_query_arg(['view' => $view, 'message' => 'success'], home_url('/inicio/'));
        
        wp_send_json_success(['redirect_url' => $redirect_url]);
    }
    
    private function render_main_dashboard_view() {
        ?>
        <h2 class="text-2xl font-semibold text-gray-800 mb-6">Módulos del Sistema</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <a href="?view=impuestos" class="block bg-white p-6 rounded-lg shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300"><div class="flex items-center"><div class="bg-red-100 p-3 rounded-full"><svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg></div><h3 class="ml-4 text-xl font-bold text-gray-800">Declaración de Impuestos</h3></div><p class="mt-4 text-gray-600">Gestiona declaraciones personales y de negocios.</p></a>
            <a href="?view=inmigracion" class="block bg-white p-6 rounded-lg shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300"><div class="flex items-center"><div class="bg-purple-100 p-3 rounded-full"><svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2h10a2 2 0 002-2v-1a2 2 0 012-2h1.945M7.737 16.95l.707-.707a1 1 0 00-1.414-1.414l-.707.707a1 1 0 001.414 1.414zM12 21a1 1 0 01-1-1v-2a1 1 0 112 0v2a1 1 0 01-1 1zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div><h3 class="ml-4 text-xl font-bold text-gray-800">Inmigración</h3></div><p class="mt-4 text-gray-600">Control sobre peticiones, ciudadanía y residencia.</p></a>
            <a href="?view=payroll" class="block bg-white p-6 rounded-lg shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300"><div class="flex items-center"><div class="bg-green-100 p-3 rounded-full"><svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v.01M12 6v-1m0 1H9m3 0h3m-3 10v-1m0 1h.01M12 18h.01M12 18v-1m0-13a9 9 0 110 18 9 9 0 010-18z"></path></svg></div><h3 class="ml-4 text-xl font-bold text-gray-800">Payroll</h3></div><p class="mt-4 text-gray-600">Administra empleados y registra nóminas.</p></a>
            <a href="?view=traducciones" class="block bg-white p-6 rounded-lg shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300"><div class="flex items-center"><div class="bg-yellow-100 p-3 rounded-full"><svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h-3.246a1.209 1.209 0 01-1.21-1.454l.009-.057l.324-1.944a.75.75 0 00-.375-.833l-3.48-2.01a.75.75 0 00-1.036.633l-.001.127v4.432A1.209 1.209 0 004.25 18h5.5a2.5 2.5 0 002.5-2.5v-2.134l.334-1.668a.75.75 0 00-.75-.833l-3.48-2.01a.75.75 0 00-1.036.633l-.001.127v4.432a1.209 1.209 0 001.209 1.209h3.246a.75.75 0 01.75.75v.008a.75.75 0 01-.75.75z"></path></svg></div><h3 class="ml-4 text-xl font-bold text-gray-800">Traducciones</h3></div><p class="mt-4 text-gray-600">Registra y sigue el progreso de las traducciones.</p></a>
            <a href="?view=transacciones" class="block bg-white p-6 rounded-lg shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300"><div class="flex items-center"><div class="bg-indigo-100 p-3 rounded-full"><svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg></div><h3 class="ml-4 text-xl font-bold text-gray-800">Pagos y Cheques</h3></div><p class="mt-4 text-gray-600">Registro rápido para pagos de facturas y cheques.</p></a>
            <a href="?view=clientes" class="block bg-white p-6 rounded-lg shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300"><div class="flex items-center"><div class="bg-pink-100 p-3 rounded-full"><svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-pink-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg></div><h3 class="ml-4 text-xl font-bold text-gray-800">Clientes</h3></div><p class="mt-4 text-gray-600">Administra la base de datos de tus clientes.</p></a>
        </div>
        <?php
    }

    private function render_dashboard_view_template($title, $post_types, $columns, $new_button_label) {
        $post_types = (array) $post_types;
        $view_name = esc_attr($_GET['view']);
        ?>
        <div class="flex justify-between items-center mb-6">
            <div><a href="/inicio/" class="text-blue-600 hover:underline">&larr; Volver</a><h2 class="text-2xl font-semibold text-gray-800 mt-2"><?php echo $title; ?></h2></div>
            <a href="?view=<?php echo $view_name; ?>&flowtax_action=new" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors"><?php echo $new_button_label; ?></a>
        </div>
        <div class="bg-white p-5 rounded-lg shadow-sm overflow-x-auto"><table class="w-full text-left"><thead><tr class="border-b"><?php foreach($columns as $column) : ?><th class="pb-2 pr-4"><?php echo $column; ?></th><?php endforeach; ?><th>Acciones</th></tr></thead><tbody>
        <?php
        $query = new WP_Query(['post_type' => $post_types, 'posts_per_page' => 10, 'orderby' => 'date', 'order' => 'DESC']);
        if ($query->have_posts()) : while ($query->have_posts()) : $query->the_post();
            ?>
            <tr class="border-b hover:bg-gray-50">
                <?php if ($view_name === 'clientes'): ?>
                    <td class="py-3 pr-4"><?php the_title(); ?></td>
                    <td class="py-3 pr-4"><?php echo esc_html(get_post_meta(get_the_ID(), '_email', true)); ?></td>
                    <td class="py-3 pr-4"><?php echo esc_html(get_post_meta(get_the_ID(), '_telefono', true)); ?></td>
                <?php else: 
                    $post_type = get_post_type(); 
                    $post_type_obj = get_post_type_object($post_type);
                    $cliente_id = get_post_meta(get_the_ID(), '_cliente_id', true);
                    $cliente_nombre = $cliente_id ? get_the_title($cliente_id) : 'N/A';
                    $taxonomies = get_object_taxonomies($post_type); 
                    $estado = 'Sin estado';
                    foreach ($taxonomies as $tax) { 
                        if (strpos($tax, 'estado_') === 0) { 
                            $terms = get_the_terms(get_the_ID(), $tax); 
                            if ($terms && !is_wp_error($terms)) { $estado = esc_html($terms[0]->name); } 
                            break; 
                        } 
                    }
                ?>
                    <td class="py-3 pr-4"><?php the_title(); ?><?php if(count($post_types) > 1): ?><br><small class="text-gray-500"><?php echo esc_html($post_type_obj->labels->singular_name); ?></small><?php endif; ?></td>
                    <td class="py-3 pr-4"><?php echo esc_html($cliente_nombre); ?></td>
                    <td class="py-3 pr-4"><?php echo $estado; ?></td>
                <?php endif; ?>
                <td class="py-3 pr-4"><a href="?view=<?php echo $view_name; ?>&flowtax_action=edit&id=<?php the_ID(); ?>" class="text-blue-500 hover:underline">Editar</a></td>
            </tr>
        <?php endwhile; wp_reset_postdata(); else : echo '<tr><td colspan="' . (count($columns) + 1) . '" class="text-center py-4">No hay registros.</td></tr>'; endif;
        ?>
        </tbody></table></div>
        <?php
    }

    private function render_frontend_form($post_type, $post_id = 0) {
        $post = ($post_id > 0) ? get_post($post_id) : null;
        $post_type_obj = get_post_type_object($post_type);
        $view_name = $this->get_view_for_post_type($post_type);
        ?>
        <a href="?view=<?php echo $view_name; ?>" class="text-blue-600 hover:underline mb-6 inline-block">&larr; Volver al listado</a>
        <h2 class="text-2xl font-semibold text-gray-800 mb-4"><?php echo ($post_id > 0 ? 'Editar' : 'Nuevo') . ' ' . $post_type_obj->labels->singular_name; ?></h2>
        <form id="flowtax-frontend-form" method="POST" action="">
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                <input type="hidden" name="post_type" value="<?php echo $post_type; ?>">
                <?php wp_nonce_field('flowtax_save_post', 'flowtax_form_nonce'); ?>
                <div class="mb-4">
                    <label for="post_title" class="font-bold text-lg text-gray-800"><?php echo $post_type_obj->labels->singular_name; ?> Título:</label>
                    <input type="text" id="post_title" name="post_title" value="<?php echo $post ? esc_attr($post->post_title) : ''; ?>" required class="w-full border-2 border-gray-300 p-2 rounded-md mt-1">
                </div>
                <?php
                $meta_box_callback = 'render_' . $post_type . '_meta_box';
                if (method_exists($this, $meta_box_callback)) { $this->$meta_box_callback($post); }
                $taxonomies = get_object_taxonomies($post_type, 'objects');
                foreach ($taxonomies as $tax) {
                    if ($tax->show_ui) {
                        $terms = get_terms(['taxonomy' => $tax->name, 'hide_empty' => false]);
                        if ($terms) {
                            $current_term = $post ? wp_get_post_terms($post->ID, $tax->name, ['fields' => 'ids']) : [];
                            echo '<div class="mt-4"><label for="'.$tax->name.'" class="font-bold">'.$tax->labels->singular_name.':</label>';
                            echo '<select name="'.$tax->name.'" id="'.$tax->name.'" class="w-full border-2 border-gray-300 p-2 rounded-md mt-1">';
                            echo '<option value="">-- Seleccionar --</option>';
                            foreach($terms as $term) { $selected = (!empty($current_term) && $current_term[0] == $term->term_id) ? 'selected' : ''; echo '<option value="'.$term->term_id.'" '.$selected.'>'.esc_html($term->name).'</option>'; }
                            echo '</select></div>';
                        }
                    }
                }
                ?>
                 <button type="submit" class="mt-6 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors"><?php echo $post_id > 0 ? 'Actualizar' : 'Guardar'; ?></button>
            </div>
        </form>
        <?php
    }
    
    private function render_new_inmigracion_choice() {
         ?>
        <a href="?view=inmigracion" class="text-blue-600 hover:underline mb-6 inline-block">&larr; Volver al listado</a>
        <h2 class="text-2xl font-semibold text-gray-800 mb-6">Selecciona el tipo de caso a crear</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <a href="?view=inmigracion&flowtax_action=new&post_type=peticion_familiar" class="block bg-white p-6 rounded-lg shadow-lg hover:shadow-xl text-center"><h3 class="text-xl font-bold text-gray-800">Petición Familiar</h3></a>
            <a href="?view=inmigracion&flowtax_action=new&post_type=ciudadania" class="block bg-white p-6 rounded-lg shadow-lg hover:shadow-xl text-center"><h3 class="text-xl font-bold text-gray-800">Ciudadanía</h3></a>
            <a href="?view=inmigracion&flowtax_action=new&post_type=renovacion_residencia" class="block bg-white p-6 rounded-lg shadow-lg hover:shadow-xl text-center"><h3 class="text-xl font-bold text-gray-800">Renovación de Residencia</h3></a>
        </div>
        <?php
    }

    private function get_view_for_post_type($post_type) {
        $map = [ 'impuestos' => 'impuestos', 'peticion_familiar' => 'inmigracion', 'ciudadania' => 'inmigracion', 'renovacion_residencia' => 'inmigracion', 'nomina' => 'payroll', 'empleado' => 'payroll', 'traduccion' => 'traducciones', 'transaccion' => 'transacciones', 'caso_servicio' => 'casos', 'cliente' => 'clientes'];
        return $map[$post_type] ?? 'main';
    }

    // --- SECCIÓN DE ACTIVACIÓN / DESACTIVACIÓN ---
    public function activate() {
        $this->register_post_types();
        $this->register_taxonomies();
        flush_rewrite_rules();
        $this->insert_initial_terms();
        $this->create_dashboard_page();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }
    
    private function insert_initial_terms() {
        $tipos_de_servicio = ['Declaración de Impuestos', 'Servicios de Payroll', 'Peticiones Familiares', 'Ciudadanía', 'Renovación de Residencia', 'Traducción', 'Pago de Bill', 'Cambio de Cheques'];
        foreach($tipos_de_servicio as $tipo) if (!term_exists($tipo, 'tipo_servicio')) wp_insert_term($tipo, 'tipo_servicio');
        $estados_del_caso = ['Nuevo', 'En Proceso', 'Pendiente de Información', 'Completado', 'Archivado'];
        foreach($estados_del_caso as $estado) if (!term_exists($estado, 'estado_caso')) wp_insert_term($estado, 'estado_caso', ['slug' => sanitize_title($estado)]);
        $estados_impuesto = ['Borrador', 'Pendiente de Documentos', 'Listo para Revisión', 'Enviado (Filed)', 'Aceptado por IRS', 'Rechazado'];
        foreach($estados_impuesto as $estado) if (!term_exists($estado, 'estado_impuesto')) wp_insert_term($estado, 'estado_impuesto');
        $estados_inmigracion = ['Consulta Inicial', 'Recopilando Documentos', 'Formularios Enviados', 'Recibo Recibido', 'Cita Biométricos', 'Solicitud de Evidencia (RFE)', 'Entrevista Programada', 'Aprobado', 'Denegado'];
        foreach($estados_inmigracion as $estado) if (!term_exists($estado, 'estado_inmigracion')) wp_insert_term($estado, 'estado_inmigracion');
    }

    private function create_dashboard_page() {
        if (get_page_by_path('inicio')) return;
        wp_insert_post([ 'post_title' => 'Inicio Gestión', 'post_content' => '[flowtax_dashboard]', 'post_status' => 'publish', 'post_author' => 1, 'post_type' => 'page', 'post_name' => 'inicio']);
    }
}

Flow_Tax_Multiservices::get_instance();

