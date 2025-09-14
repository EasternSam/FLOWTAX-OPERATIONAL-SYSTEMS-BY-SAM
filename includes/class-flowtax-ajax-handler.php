<?php
class Flowtax_Ajax_Handler {

    public static function init() {
        $ajax_actions = [
            'get_view', 'save_form', 'get_search_results', 'delete_post', 
            'get_cliente_perfil', 'get_caso_details', 'upload_document', 
            'delete_document', 'add_note', 'get_notifications', 
            'toggle_watchman_mode', 'get_live_activity', 'send_reminder'
        ];
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
    
    public static function handle_send_reminder() {
        self::check_permissions();
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $method = isset($_POST['method']) ? sanitize_key($_POST['method']) : 'all';

        if ($post_id === 0) {
            wp_send_json_error(['message' => 'ID de deuda no válido.']);
            return;
        }

        $reminder = new Flowtax_Reminders($post_id);
        $results = [];

        if ($method === 'whatsapp' || $method === 'all') {
            $results['whatsapp'] = $reminder->send('whatsapp');
        }
        if ($method === 'email' || $method === 'all') {
            $results['email'] = $reminder->send('email');
        }

        $success_messages = [];
        $error_messages = [];

        foreach ($results as $channel => $result) {
            if ($result['success']) {
                $success_messages[] = ucfirst($channel) . ": " . $result['message'];
            } else {
                $error_messages[] = ucfirst($channel) . ": " . $result['message'];
            }
        }

        if (!empty($success_messages)) {
            $log_message = 'Envió recordatorio para la deuda "' . get_the_title($post_id) . '" por ' . implode(' y ', array_keys($results)) . '.';
            Flowtax_Activity_Log::log($log_message, $post_id, 'deuda');
            wp_send_json_success(['message' => implode("\n", $success_messages)]);
        } else {
            wp_send_json_error(['message' => implode("\n", $error_messages)]);
        }
    }

    private static function get_json_meta($post_id, $key) {
        $json = get_post_meta($post_id, $key, true);
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }
    
    private static function update_json_meta($post_id, $key, $data) {
        update_post_meta($post_id, $key, wp_json_encode($data));
    }

    public static function handle_upload_document() {
        self::check_permissions();
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if ($post_id === 0 || empty($_FILES['document_upload'])) {
            wp_send_json_error(['message' => 'Faltan datos para la subida.']);
            return;
        }

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $attachment_id = media_handle_upload('document_upload', $post_id);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error(['message' => $attachment_id->get_error_message()]);
            return;
        }

        $documents = self::get_json_meta($post_id, '_documentos_adjuntos');
        $new_doc = [
            'id' => $attachment_id,
            'name' => basename(get_attached_file($attachment_id)),
            'url' => wp_get_attachment_url($attachment_id)
        ];
        $documents[] = $new_doc;
        self::update_json_meta($post_id, '_documentos_adjuntos', $documents);

        $parent_post = get_post($post_id);
        if ($parent_post) {
            $action_string = sprintf('Subió el documento "%s" al caso "%s".', esc_html($new_doc['name']), esc_html($parent_post->post_title));
            Flowtax_Activity_Log::log($action_string, $post_id, $parent_post->post_type);
        }

        wp_send_json_success(Flowtax_Debugger::send_logs_in_ajax_response(['message' => 'Documento subido con éxito.', 'document' => $new_doc]));
    }

    public static function handle_toggle_watchman_mode() {
        self::check_permissions();
        $user_id = get_current_user_id();
        $new_status = !((bool) get_user_meta($user_id, 'flowtax_watchman_mode_enabled', true));
        update_user_meta($user_id, 'flowtax_watchman_mode_enabled', $new_status);
        wp_send_json_success(['message' => $new_status ? 'Modo Vigilante activado.' : 'Modo Vigilante desactivado.', 'new_status' => $new_status]);
    }
    
    public static function handle_get_live_activity() {
        self::check_permissions();
        $query = new WP_Query(['post_type' => 'flowtax_log', 'posts_per_page' => 20, 'orderby' => 'date', 'order' => 'DESC']);
        $logs_data = array_map([__CLASS__, 'format_post_data'], $query->posts);
        wp_reset_postdata();
        wp_send_json_success(Flowtax_Debugger::send_logs_in_ajax_response(['logs' => $logs_data]));
    }

    public static function handle_get_notifications() {
        self::check_permissions();
        $user_id = get_current_user_id();
        $last_checked = (int) get_user_meta($user_id, 'flowtax_last_notification_check', true);
        
        $query_args = ['post_type' => 'flowtax_log', 'posts_per_page' => 10, 'orderby' => 'date', 'order' => 'DESC'];
        if ($last_checked > 0) {
            $query_args['date_query'] = [['column' => 'post_date_gmt', 'after' => gmdate('Y-m-d H:i:s', $last_checked)]];
        }
        
        $unread_count = (new WP_Query($query_args))->found_posts;
        if (isset($_POST['check_only'])) {
            wp_send_json_success(['unread_count' => $unread_count]);
            return;
        }
    
        unset($query_args['date_query']);
        $recent_query = new WP_Query($query_args);
        $notifications = array_map([__CLASS__, 'format_post_data'], $recent_query->posts);
        wp_reset_postdata();
        update_user_meta($user_id, 'flowtax_last_notification_check', current_time('timestamp'));
        wp_send_json_success(Flowtax_Debugger::send_logs_in_ajax_response(['notifications' => $notifications]));
    }
    
    public static function handle_add_note() {
        self::check_permissions();
        $post_id = intval($_POST['post_id'] ?? 0);
        $note_content = trim(stripslashes($_POST['note_content'] ?? ''));

        if (empty($note_content) || $post_id === 0) {
            wp_send_json_error(['message' => 'El contenido de la nota no puede estar vacío.']);
            return;
        }
        
        $notes = self::get_json_meta($post_id, '_historial_notas');
        $current_user = wp_get_current_user();
        array_unshift($notes, ['author' => $current_user->display_name, 'content' => wp_kses_post($note_content), 'date' => current_time('mysql')]);
        self::update_json_meta($post_id, '_historial_notas', $notes);
        
        $parent_post = get_post($post_id);
        if ($parent_post) {
            Flowtax_Activity_Log::log(sprintf('Añadió una nota al caso "%s".', $parent_post->post_title), $post_id, $parent_post->post_type);
        }
        wp_send_json_success(Flowtax_Debugger::send_logs_in_ajax_response(['message' => 'Nota añadida.']));
    }

    public static function handle_delete_document() {
        self::check_permissions();
        $post_id = intval($_POST['post_id'] ?? 0);
        $attachment_id = intval($_POST['attachment_id'] ?? 0);
    
        if ($post_id === 0 || $attachment_id === 0) {
            wp_send_json_error(['message' => 'Datos inválidos.']);
            return;
        }
    
        $documents = self::get_json_meta($post_id, '_documentos_adjuntos');
        $updated_documents = array_values(array_filter($documents, fn($doc) => intval($doc['id']) !== $attachment_id));
        
        $doc_name = basename(get_attached_file($attachment_id));
        wp_delete_attachment($attachment_id, true);
        self::update_json_meta($post_id, '_documentos_adjuntos', $updated_documents);
    
        $parent_post = get_post($post_id);
        if ($parent_post) {
            Flowtax_Activity_Log::log(sprintf('Eliminó el documento "%s" del caso "%s".', $doc_name, $parent_post->post_title), $post_id, $parent_post->post_type);
        }
        wp_send_json_success(Flowtax_Debugger::send_logs_in_ajax_response(['message' => 'Documento eliminado.']));
    }

    public static function handle_get_caso_details() {
        self::check_permissions();
        $post_id = intval($_POST['post_id'] ?? 0);
        if ($post_id === 0 || !($caso_post = get_post($post_id))) {
            wp_send_json_error(['message' => 'No se encontró el caso.']);
            return;
        }

        $caso_data = self::format_post_data($caso_post);
        $caso_data['meta'] = get_post_meta($post_id);

        $cliente_data = null;
        if ($cliente_id = get_post_meta($post_id, '_cliente_id', true)) {
            if ($cliente_post = get_post($cliente_id)) {
                $cliente_data = self::format_post_data($cliente_post);
            }
        }
        wp_send_json_success(Flowtax_Debugger::send_logs_in_ajax_response(['caso' => $caso_data, 'cliente' => $cliente_data]));
    }

    public static function handle_get_cliente_perfil() {
        self::check_permissions();
        $cliente_id = intval($_POST['cliente_id'] ?? 0);
        if ($cliente_id === 0 || !($cliente_post = get_post($cliente_id))) {
            wp_send_json_error(['message' => 'No se encontró el cliente.']);
            return;
        }
    
        $cliente_data = self::format_post_data($cliente_post);
        $cliente_data['meta'] = get_post_meta($cliente_id);
    
        $casos_query = new WP_Query(['post_type' => ['impuestos', 'peticion_familiar', 'ciudadania', 'renovacion_residencia', 'traduccion', 'deuda'], 'posts_per_page' => -1, 'meta_key' => '_cliente_id', 'meta_value' => $cliente_id]);
        $casos = array_map([__CLASS__, 'format_post_data'], $casos_query->posts);
        usort($casos, fn($a, $b) => strtotime($b['fecha_raw']) - strtotime($a['fecha_raw']));

        $case_ids = wp_list_pluck($casos, 'ID');
        $historial = [];
        if (!empty($case_ids)) {
            $log_query = new WP_Query(['post_type' => 'flowtax_log', 'posts_per_page' => 10, 'meta_query' => [['key' => '_related_post_id', 'value' => $case_ids, 'compare' => 'IN']], 'orderby' => 'date', 'order' => 'DESC']);
            $historial = array_map([__CLASS__, 'format_post_data'], $log_query->posts);
        }
    
        wp_send_json_success(Flowtax_Debugger::send_logs_in_ajax_response(['cliente' => $cliente_data, 'casos' => $casos, 'historial' => $historial]));
    }
    
    public static function handle_get_search_results() {
        self::check_permissions();
        $search_term = sanitize_text_field($_POST['search_term']);
        $post_type = sanitize_text_field($_POST['post_type']);
        $args = ['post_type' => explode(',', $post_type), 'posts_per_page' => -1, 's' => $search_term];
        
        $query = new WP_Query($args);
        $results = array_map([__CLASS__, 'format_post_data'], $query->posts);
        wp_reset_postdata();
        wp_send_json_success(Flowtax_Debugger::send_logs_in_ajax_response($results));
    }

    public static function handle_delete_post() {
        self::check_permissions();
        $post_id = intval($_POST['post_id']);
        if ($post_to_delete = get_post($post_id)) {
            $cpt_object = get_post_type_object($post_to_delete->post_type);
            $cpt_name = $cpt_object ? strtolower($cpt_object->labels->singular_name) : 'registro';
            Flowtax_Activity_Log::log(sprintf('Eliminó el %s "%s".', $cpt_name, $post_to_delete->post_title));
            
            if (wp_delete_post($post_id, true)) {
                wp_send_json_success(Flowtax_Debugger::send_logs_in_ajax_response(['message' => 'Registro eliminado.']));
            }
        }
        wp_send_json_error(Flowtax_Debugger::send_logs_in_ajax_response(['message' => 'No se pudo eliminar el registro.']));
    }

    public static function handle_get_view() {
        self::check_permissions();
        $view = sanitize_key($_POST['view'] ?? 'dashboard');
        $action = sanitize_key($_POST['flowtax_action'] ?? 'list');
        $id = intval($_POST['id'] ?? 0);
        
        ob_start();
        $view_map = [
            'perfil' => 'view-cliente-perfil.php',
            'manage' => 'view-caso-manage.php',
            'default' => "views/view-{$view}.php"
        ];
        $view_path = FLOWTAX_MS_PLUGIN_DIR . ($view_map[$action] ?? $view_map['default']);
        
        if (file_exists($view_path)) {
            include $view_path;
        } else {
            include FLOWTAX_MS_PLUGIN_DIR . 'views/view-dashboard.php';
        }
        $html = ob_get_clean();
        wp_send_json_success(Flowtax_Debugger::send_logs_in_ajax_response(['html' => $html]));
    }
    
    public static function handle_save_form() {
        self::check_permissions();
        
        $validator = new Flowtax_Data_Validator($_POST);
        if ($errors = $validator->validate()) {
            wp_send_json_error(Flowtax_Debugger::send_logs_in_ajax_response(['message' => 'Por favor, corrige los errores.', 'errors' => $errors]));
            return;
        }

        $sanitized_data = $validator->get_sanitized_data();
        $post_id = intval($sanitized_data['post_id'] ?? 0);
        $post_type = $sanitized_data['post_type'];

        $post_data = ['post_type' => $post_type, 'post_title' => $sanitized_data['post_title'], 'post_status' => 'publish'];
        if ($post_id > 0) $post_data['ID'] = $post_id;
        
        $result = $post_id > 0 ? wp_update_post($post_data, true) : wp_insert_post($post_data, true);
        
        if (is_wp_error($result)) {
            wp_send_json_error(Flowtax_Debugger::send_logs_in_ajax_response(['message' => $result->get_error_message()]));
            return;
        }
        
        $new_post_id = $result;
        
        $cpt_object = get_post_type_object($post_type);
        $cpt_name = $cpt_object ? strtolower($cpt_object->labels->singular_name) : 'registro';
        $action_verb = $post_id > 0 ? 'actualizó el' : 'creó el nuevo';
        Flowtax_Activity_Log::log(sprintf('%s %s "%s".', ucfirst($action_verb), $cpt_name, $sanitized_data['post_title']), $new_post_id, $post_type);

        Flowtax_Meta_Boxes::save_meta_data($new_post_id, $sanitized_data);
        if (isset($sanitized_data['estado_caso'])) {
            wp_set_post_terms($new_post_id, intval($sanitized_data['estado_caso']), 'estado_caso');
        }
        if (isset($sanitized_data['estado_deuda'])) {
            wp_set_post_terms($new_post_id, intval($sanitized_data['estado_deuda']), 'estado_deuda');
        }

        $view_slug = self::get_view_for_post_type($post_type);
        
        $action_slug = 'list'; // Valor por defecto
        if ($post_type === 'cliente') {
            $action_slug = 'perfil';
        } elseif (in_array($post_type, ['impuestos', 'peticion_familiar', 'ciudadania', 'renovacion_residencia', 'traduccion'])) {
             $action_slug = 'manage';
        }

        wp_send_json_success(Flowtax_Debugger::send_logs_in_ajax_response(['message' => '¡Datos guardados!', 'redirect_view' => $view_slug, 'post_id' => $new_post_id, 'redirect_action' => $action_slug]));
    }

    public static function get_view_for_post_type($post_type) {
        $map = [
            'impuestos' => 'impuestos', 'peticion_familiar' => 'inmigracion', 'ciudadania' => 'inmigracion',
            'renovacion_residencia' => 'inmigracion', 'traduccion' => 'traducciones', 
            'cliente' => 'clientes', 'flowtax_log' => 'actividad', 'deuda' => 'cuentas-por-cobrar'
        ];
        return $map[$post_type] ?? 'dashboard';
    }
    
    public static function format_post_data($post) {
        if (!$post instanceof WP_Post) return [];
        
        $post_id = $post->ID;
        $post_type = $post->post_type;

        if ($post_type === 'flowtax_log') {
            $related_post_id = get_post_meta($post_id, '_related_post_id', true);
            $related_post_type = get_post_meta($post_id, '_related_post_type', true);
            $action_slug = '';
            if ($related_post_id && $related_post_type) {
                if ($related_post_type === 'cliente') {
                    $action_slug = 'perfil';
                } elseif($related_post_type === 'deuda') {
                    $action_slug = 'list';
                } else {
                    $action_slug = 'manage';
                }
            }
            return [
                'ID' => $post_id, 'title' => get_the_title($post_id), 'author' => get_post_meta($post_id, '_user_name', true) ?: 'Sistema',
                'time_ago' => human_time_diff(get_the_time('U', $post_id), current_time('timestamp')) . ' atrás',
                'related_id' => $related_post_id, 'view_slug' => self::get_view_for_post_type($related_post_type),
                'action' => $action_slug
            ];
        }

        $cliente_id = get_post_meta($post_id, '_cliente_id', true);
        $data = [
            'ID' => $post_id, 'title' => get_the_title($post_id), 'post_type' => $post_type,
            'singular_name' => get_post_type_object($post_type)->labels->singular_name,
            'cliente_nombre' => $cliente_id ? get_the_title($cliente_id) : 'N/A',
            'fecha' => get_the_date('d M Y', $post_id), 'fecha_raw' => $post->post_date,
            'view_slug' => self::get_view_for_post_type($post_type),
        ];

        $meta_keys = ['email', 'telefono', 'ano_fiscal', 'idioma_origen', 'idioma_destino', 'monto', 'fecha_vencimiento'];
        foreach ($meta_keys as $key) {
            $data[$key] = get_post_meta($post_id, "_{$key}", true);
        }

        $taxonomy = ($post_type === 'deuda') ? 'estado_deuda' : 'estado_caso';
        $terms = get_the_terms($post_id, $taxonomy);
        if (!empty($terms) && !is_wp_error($terms)) {
            $data['estado'] = esc_html($terms[0]->name);
            $data['estado_color'] = get_term_meta($terms[0]->term_id, 'color_class', true) ?: 'bg-slate-100 text-slate-800';
        } else {
            $data['estado'] = 'Sin estado';
            $data['estado_color'] = 'bg-slate-100 text-slate-800';
        }
        
        return $data;
    }
}

