<?php
class Flowtax_Ajax_Handler {

    public static function init() {
        $ajax_actions = ['get_view', 'save_form', 'get_search_results', 'delete_post', 'get_cliente_perfil'];
        foreach ($ajax_actions as $action) {
            add_action("wp_ajax_flowtax_{$action}", array(__CLASS__, "handle_{$action}"));
        }
    }

    private static function check_permissions() {
        if (!check_ajax_referer('flowtax_ajax_nonce', 'nonce', false) || !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Error de seguridad o permisos.'], 403);
            wp_die();
        }
    }

    public static function handle_get_cliente_perfil() {
        self::check_permissions();
        $cliente_id = isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;
        if ($cliente_id === 0) {
            wp_send_json_error(['message' => 'ID de cliente no válido.']);
            return;
        }

        // Datos del cliente
        $cliente_post = get_post($cliente_id);
        $cliente_data = self::format_post_data($cliente_post);
        $cliente_data['meta'] = get_post_meta($cliente_id);

        // Casos asociados
        $casos = [];
        $post_types = ['impuestos', 'peticion_familiar', 'ciudadania', 'renovacion_residencia', 'traduccion'];
        
        foreach($post_types as $pt) {
            $query = new WP_Query([
                'post_type' => $pt,
                'posts_per_page' => -1,
                'meta_key' => '_cliente_id',
                'meta_value' => $cliente_id
            ]);
            if ($query->have_posts()) {
                 $casos = array_merge($casos, array_map('self::format_post_data', $query->posts));
            }
        }

        wp_send_json_success(['cliente' => $cliente_data, 'casos' => $casos]);
    }
    
    public static function handle_get_search_results() {
        self::check_permissions();
        $search_term = sanitize_text_field($_POST['search_term']);
        $post_type = sanitize_text_field($_POST['post_type']);
        $args = [
            'post_type' => explode(',', $post_type),
            'posts_per_page' => -1,
            's' => $search_term,
        ];
        $query = new WP_Query($args);
        $results = array_map('self::format_post_data', $query->posts);
        wp_reset_postdata();
        wp_send_json_success(Flowtax_Debugger::send_logs_in_ajax_response($results));
    }

    public static function handle_delete_post() {
        self::check_permissions();
        $post_id = intval($_POST['post_id']);
        if ($post_id > 0) {
            $result = wp_delete_post($post_id, true);
            if ($result) {
                wp_send_json_success(Flowtax_Debugger::send_logs_in_ajax_response(['message' => 'Registro eliminado con éxito.']));
            } else {
                wp_send_json_error(Flowtax_Debugger::send_logs_in_ajax_response(['message' => 'No se pudo eliminar el registro.']));
            }
        }
        wp_send_json_error(Flowtax_Debugger::send_logs_in_ajax_response(['message' => 'ID de registro inválido.']));
    }

    public static function handle_get_view() {
        self::check_permissions();
        $view = isset($_POST['view']) ? sanitize_key($_POST['view']) : 'dashboard';
        $action = isset($_POST['flowtax_action']) ? sanitize_key($_POST['flowtax_action']) : 'list';
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        ob_start();
        try {
            // MEJORA: Lógica para cargar vista de perfil de cliente
            if ($view === 'clientes' && $action === 'perfil') {
                $view_path = FLOWTAX_MS_PLUGIN_DIR . "views/view-cliente-perfil.php";
            } else {
                $view_path = FLOWTAX_MS_PLUGIN_DIR . "views/view-{$view}.php";
            }
            
            if (file_exists($view_path)) {
                include $view_path;
            } else {
                include FLOWTAX_MS_PLUGIN_DIR . 'views/view-dashboard.php';
            }
            $html = ob_get_clean();
            wp_send_json_success(Flowtax_Debugger::send_logs_in_ajax_response(['html' => $html]));

        } catch (Throwable $e) {
            ob_end_clean();
            $error_message = 'Error fatal al renderizar la vista: ' . $e->getMessage();
            if(defined('FLOWTAX_DEBUG_MODE') && FLOWTAX_DEBUG_MODE) Flowtax_Debugger::log($error_message, 'Fatal Error');
            wp_send_json_error(Flowtax_Debugger::send_logs_in_ajax_response(['message' => $error_message]), 500);
        }
    }
    
    public static function handle_save_form() {
        self::check_permissions();
        $validator = new Flowtax_Data_Validator($_POST);
        $errors = $validator->validate();

        if (!empty($errors)) {
            wp_send_json_error(Flowtax_Debugger::send_logs_in_ajax_response(['message' => 'Por favor, corrige los errores.', 'errors' => $errors]));
            return;
        }

        $sanitized_data = $validator->get_sanitized_data();
        $post_id = isset($sanitized_data['post_id']) ? intval($sanitized_data['post_id']) : 0;
        $post_type = $sanitized_data['post_type'];

        $post_data = ['post_type' => $post_type, 'post_title' => $sanitized_data['post_title'], 'post_status' => 'publish'];
        
        $result = $post_id > 0 ? wp_update_post(array_merge($post_data, ['ID' => $post_id]), true) : wp_insert_post($post_data, true);
        
        if (is_wp_error($result)) {
            wp_send_json_error(Flowtax_Debugger::send_logs_in_ajax_response(['message' => $result->get_error_message()]));
            return;
        }
        
        $new_post_id = $result;
        Flowtax_Meta_Boxes::save_meta_data($new_post_id, $sanitized_data);
        if (isset($sanitized_data['estado_caso'])) {
            wp_set_post_terms($new_post_id, intval($sanitized_data['estado_caso']), 'estado_caso');
        }

        $view_slug = self::get_view_for_post_type($post_type);
        wp_send_json_success(Flowtax_Debugger::send_logs_in_ajax_response(['message' => '¡Datos guardados con éxito!', 'redirect_view' => $view_slug, 'post_id' => $new_post_id]));
    }

    public static function get_view_for_post_type($post_type) {
        $map = [
            'impuestos' => 'impuestos', 'peticion_familiar' => 'inmigracion', 'ciudadania' => 'inmigracion',
            'renovacion_residencia' => 'inmigracion', 'nomina' => 'payroll', 'empleado' => 'payroll',
            'traduccion' => 'traducciones', 'transaccion' => 'transacciones', 'cliente' => 'clientes'
        ];
        return $map[$post_type] ?? 'dashboard';
    }
    
    public static function format_post_data($post) {
        if (!$post instanceof WP_Post) return [];
        $post_id = $post->ID;
        $cliente_id = get_post_meta($post_id, '_cliente_id', true);
        $cliente_nombre = $cliente_id ? get_the_title($cliente_id) : 'N/A';
        $estado_terms = get_the_terms($post_id, 'estado_caso');
        $estado = 'Sin estado';
        $estado_color = 'bg-slate-100 text-slate-800';
        if ($estado_terms && !is_wp_error($estado_terms)) {
            $estado = esc_html($estado_terms[0]->name);
            $estado_color = get_term_meta($estado_terms[0]->term_id, 'color_class', true);
        }
        
        $post_type_obj = get_post_type_object(get_post_type($post_id));
        $singular_name = $post_type_obj ? $post_type_obj->labels->singular_name : '';
        
        return [
            'ID' => $post_id, 'title' => get_the_title($post_id), 'post_type' => get_post_type($post_id),
            'singular_name' => $singular_name, 'cliente_nombre' => $cliente_nombre, 'estado' => $estado,
            'estado_color' => $estado_color, 'fecha' => get_the_date('d/m/Y', $post_id),
            'email' => get_post_meta($post_id, '_email', true), 'telefono' => get_post_meta($post_id, '_telefono', true),
            'ano_fiscal' => get_post_meta($post_id, '_ano_fiscal', true),
            'idioma_origen' => get_post_meta($post_id, '_idioma_origen', true),
            'idioma_destino' => get_post_meta($post_id, '_idioma_destino', true),
            'view_slug' => self::get_view_for_post_type(get_post_type($post_id)),
        ];
    }
}
