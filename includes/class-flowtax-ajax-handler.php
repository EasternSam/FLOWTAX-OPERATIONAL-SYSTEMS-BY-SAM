<?php
class Flowtax_Ajax_Handler {

    public static function init() {
        $ajax_actions = ['get_view', 'save_form', 'get_search_results', 'delete_post'];
        foreach ($ajax_actions as $action) {
            add_action("wp_ajax_flowtax_{$action}", array(__CLASS__, "handle_{$action}"));
        }
    }

    private static function check_permissions() {
        if (!check_ajax_referer('flowtax_ajax_nonce', 'nonce', false) || !current_user_can('manage_options')) {
            $error_data = ['message' => 'Error de seguridad o permisos.'];
            if (defined('FLOWTAX_DEBUG_MODE') && FLOWTAX_DEBUG_MODE) {
                 wp_send_json_error(Flowtax_Debugger::send_logs_in_ajax_response($error_data), 403);
            } else {
                 wp_send_json_error($error_data, 403);
            }
            wp_die();
        }
    }
    
    public static function handle_get_search_results() {
        self::check_permissions();
        if (defined('FLOWTAX_DEBUG_MODE') && FLOWTAX_DEBUG_MODE) Flowtax_Debugger::log($_POST, 'AJAX: get_search_results');
        
        $search_term = sanitize_text_field($_POST['search_term']);
        $post_type = sanitize_text_field($_POST['post_type']);
        $args = [
            'post_type' => explode(',', $post_type),
            'posts_per_page' => -1,
            's' => $search_term,
        ];
        $query = new WP_Query($args);
        $results = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $results[] = self::format_post_data(get_post());
            }
        }
        wp_reset_postdata();
        wp_send_json_success(Flowtax_Debugger::send_logs_in_ajax_response($results));
    }

    public static function handle_delete_post() {
        self::check_permissions();
        if (defined('FLOWTAX_DEBUG_MODE') && FLOWTAX_DEBUG_MODE) Flowtax_Debugger::log($_POST, 'AJAX: delete_post');

        $post_id = intval($_POST['post_id']);
        if ($post_id > 0) {
            $result = wp_delete_post($post_id, true); // true to bypass trash
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
        if (defined('FLOWTAX_DEBUG_MODE') && FLOWTAX_DEBUG_MODE) Flowtax_Debugger::log($_GET, 'AJAX: get_view');

        $view = isset($_POST['view']) ? sanitize_key($_POST['view']) : 'dashboard';
        $action = isset($_POST['flowtax_action']) ? sanitize_key($_POST['flowtax_action']) : 'list';
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        ob_start();
        $view_path = FLOWTAX_MS_PLUGIN_DIR . "views/view-{$view}.php";
        if (file_exists($view_path)) {
            include $view_path;
        } else {
            if (defined('FLOWTAX_DEBUG_MODE') && FLOWTAX_DEBUG_MODE) Flowtax_Debugger::log("Vista no encontrada: {$view_path}. Cargando dashboard.", 'Warning');
            include FLOWTAX_MS_PLUGIN_DIR . 'views/view-dashboard.php';
        }
        $html = ob_get_clean();
        wp_send_json_success(Flowtax_Debugger::send_logs_in_ajax_response(['html' => $html]));
    }
    
    public static function handle_save_form() {
        self::check_permissions();
        if (defined('FLOWTAX_DEBUG_MODE') && FLOWTAX_DEBUG_MODE) Flowtax_Debugger::log($_POST, 'AJAX: save_form');

        $validator = new Flowtax_Data_Validator($_POST);
        $errors = $validator->validate();

        if (!empty($errors)) {
            if (defined('FLOWTAX_DEBUG_MODE') && FLOWTAX_DEBUG_MODE) Flowtax_Debugger::log($errors, 'Validation Errors');
            wp_send_json_error(Flowtax_Debugger::send_logs_in_ajax_response(['message' => 'Por favor, corrige los errores.', 'errors' => $errors]));
            return;
        }

        $sanitized_data = $validator->get_sanitized_data();
        
        $post_id = isset($sanitized_data['post_id']) ? intval($sanitized_data['post_id']) : 0;
        $post_type = $sanitized_data['post_type'];

        $post_data = [
            'post_type' => $post_type,
            'post_title' => $sanitized_data['post_title'],
            'post_status' => 'publish'
        ];
        
        if ($post_id > 0) {
            $post_data['ID'] = $post_id;
            $result = wp_update_post($post_data, true);
        } else {
            $result = wp_insert_post($post_data, true);
        }
        
        if (is_wp_error($result)) {
            if (defined('FLOWTAX_DEBUG_MODE') && FLOWTAX_DEBUG_MODE) Flowtax_Debugger::log($result->get_error_message(), 'WP_Error on Save');
            wp_send_json_error(Flowtax_Debugger::send_logs_in_ajax_response(['message' => $result->get_error_message()]));
            return;
        }
        
        $new_post_id = $result;
        
        Flowtax_Meta_Boxes::save_meta_data($new_post_id, $sanitized_data);
        
        if (isset($sanitized_data['estado_caso'])) {
            wp_set_post_terms($new_post_id, intval($sanitized_data['estado_caso']), 'estado_caso');
        }

        $view_slug = self::get_view_for_post_type($post_type);
        wp_send_json_success(Flowtax_Debugger::send_logs_in_ajax_response([
            'message' => '¡Datos guardados con éxito!',
            'redirect_view' => $view_slug
        ]));
    }

    private static function get_view_for_post_type($post_type) {
        $map = [
            'impuestos' => 'impuestos',
            'peticion_familiar' => 'inmigracion',
            'ciudadania' => 'inmigracion',
            'renovacion_residencia' => 'inmigracion',
            'nomina' => 'payroll',
            'empleado' => 'payroll',
            'traduccion' => 'traducciones',
            'transaccion' => 'transacciones',
            'cliente' => 'clientes'
        ];
        return $map[$post_type] ?? 'dashboard';
    }
    
    public static function format_post_data($post) {
        $post_id = $post->ID;
        $cliente_id = get_post_meta($post_id, '_cliente_id', true);
        $cliente_nombre = $cliente_id ? get_the_title($cliente_id) : 'N/A';
        $estado_terms = get_the_terms($post_id, 'estado_caso');
        $estado = 'Sin estado';
        $estado_color = 'bg-gray-100 text-gray-800';
        if ($estado_terms && !is_wp_error($estado_terms)) {
            $estado = esc_html($estado_terms[0]->name);
            $estado_color = get_term_meta($estado_terms[0]->term_id, 'color_class', true);
        }
        
        return [
            'ID' => $post_id,
            'title' => get_the_title($post_id),
            'post_type' => get_post_type($post_id),
            'singular_name' => get_post_type_object(get_post_type($post_id))->labels->singular_name,
            'cliente_nombre' => $cliente_nombre,
            'estado' => $estado,
            'estado_color' => $estado_color,
            'fecha' => get_the_date('d/m/Y', $post_id),
            'email' => get_post_meta($post_id, '_email', true),
            'telefono' => get_post_meta($post_id, '_telefono', true),
        ];
    }
}
