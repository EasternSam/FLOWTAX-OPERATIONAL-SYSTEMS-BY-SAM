<?php
class Flowtax_Ajax_Handler {

    public static function init() {
        $ajax_actions = [
            'get_view', 
            'save_form', 
            'get_search_results', 
            'delete_post', 
            'get_cliente_perfil',
            'get_caso_details',
            'upload_document',
            'delete_document',
            'add_note',
            'get_notifications',
            'toggle_watchman_mode',
            'save_onesignal_player_id'
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
    
    private static function get_json_meta($post_id, $key) {
        $json = get_post_meta($post_id, $key, true);
        if (empty($json)) {
            return [];
        }
        $data = json_decode($json, true);
        return (is_array($data)) ? $data : [];
    }
    
    private static function update_json_meta($post_id, $key, $data) {
        $json = wp_json_encode($data);
        update_post_meta($post_id, $key, $json);
    }

    public static function handle_save_onesignal_player_id() {
        self::check_permissions();
        $player_id = isset($_POST['player_id']) ? sanitize_text_field($_POST['player_id']) : '';
        $user_id = get_current_user_id();

        if (empty($player_id) || $user_id === 0) {
            wp_send_json_error(['message' => 'Faltan datos (Player ID o User ID).']);
            return;
        }

        update_user_meta($user_id, 'onesignal_player_id', $player_id);
        wp_send_json_success(['message' => 'Player ID guardado.']);
    }

    public static function handle_toggle_watchman_mode() {
        self::check_permissions();
        $user_id = get_current_user_id();
        $current_status = (bool) get_user_meta($user_id, 'flowtax_watchman_mode_enabled', true);
        $new_status = !$current_status;
        update_user_meta($user_id, 'flowtax_watchman_mode_enabled', $new_status);
        
        $message = $new_status ? 'Modo Vigilante activado.' : 'Modo Vigilante desactivado.';
        wp_send_json_success(['message' => $message, 'new_status' => $new_status]);
    }

    public static function handle_get_notifications() {
        self::check_permissions();
        $user_id = get_current_user_id();
        $last_checked = get_user_meta($user_id, 'flowtax_last_notification_check', true) ?: 0;
    
        $query_args = [
            'post_type' => 'flowtax_log',
            'posts_per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC',
            'date_query' => [
                ['column' => 'post_date_gmt', 'after' => gmdate('Y-m-d H:i:s', $last_checked), 'inclusive' => false]
            ]
        ];
    
        $log_query = new WP_Query($query_args);
        $unread_count = $log_query->found_posts;
    
        if (isset($_POST['check_only'])) {
            wp_send_json_success(['unread_count' => $unread_count]);
            return;
        }
    
        // Reset query to get the latest 10, regardless of read status
        unset($query_args['date_query']);
        $log_query = new WP_Query($query_args);
        $notifications = [];
        if ($log_query->have_posts()) {
            while ($log_query->have_posts()) {
                $log_query->the_post();
                $notifications[] = [
                    'id' => get_the_ID(),
                    'title' => get_the_title(),
                    'author' => get_post_meta(get_the_ID(), '_user_name', true) ?: 'Sistema',
                    'time_ago' => human_time_diff(get_the_time('U'), current_time('timestamp')) . ' atrás'
                ];
            }
        }
        wp_reset_postdata();
    
        // Update last checked time to now
        update_user_meta($user_id, 'flowtax_last_notification_check', current_time('timestamp'));
    
        wp_send_json_success(Flowtax_Debugger::send_logs_in_ajax_response(['notifications' => $notifications]));
    }


    public static function handle_add_note() {
        self::check_permissions();
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $note_content_raw = isset($_POST['note_content']) ? trim(stripslashes($_POST['note_content'])) : '';

        if (empty($note_content_raw) || $post_id === 0) {
            wp_send_json_error(['message' => 'Faltan datos para añadir la nota. El contenido no puede estar vacío.']);
            return;
        }
        
        $note_content_sanitized = wp_kses_post($note_content_raw);
        $notes = self::get_json_meta($post_id, '_historial_notas');
        $current_user = wp_get_current_user();
        
        $new_note = [
            'author' => $current_user->display_name,
            'content' => $note_content_sanitized,
            'date' => current_time('mysql')
        ];

        array_unshift($notes, $new_note);
        self::update_json_meta($post_id, '_historial_notas', $notes);
        
        // Log the action
        $parent_post = get_post($post_id);
        if ($parent_post) {
            $action_string = sprintf('Añadió una nota al caso "%s".', esc_html($parent_post->post_title));
            Flowtax_Activity_Log::log($action_string, $post_id, $parent_post->post_type);
        }

        wp_send_json_success(Flowtax_Debugger::send_logs_in_ajax_response(['message' => 'Nota añadida.']));
    }

    public static function handle_delete_document() {
        self::check_permissions();
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
    
        if ($post_id === 0 || $attachment_id === 0) {
            wp_send_json_error(['message' => 'Datos inválidos.']);
            return;
        }
    
        $documents = self::get_json_meta($post_id, '_documentos_adjuntos');
        
        // CORRECCIÓN: Usar una función anónima tradicional para compatibilidad con PHP < 7.3
        $updated_documents = array_filter($documents, function($doc) use ($attachment_id) {
            return isset($doc['id']) && intval($doc['id']) !== $attachment_id;
        });
    
        $doc_name = basename(get_attached_file($attachment_id));
        wp_delete_attachment($attachment_id, true);
        self::update_json_meta($post_id, '_documentos_adjuntos', array_values($updated_documents));
    
        // Log the action
        $parent_post = get_post($post_id);
        if ($parent_post) {
            $action_string = sprintf('Eliminó el documento "%s" del caso "%s".', esc_html($doc_name), esc_html($parent_post->post_title));
            Flowtax_Activity_Log::log($action_string, $post_id, $parent_post->post_type);
        }
    
        wp_send_json_success(Flowtax_Debugger::send_logs_in_ajax_response(['message' => 'Documento eliminado con éxito.']));
    }

    public static function handle_upload_document() {
        self::check_permissions();
        
        if (empty($_FILES['document_upload'])) {
            wp_send_json_error(['message' => 'No se ha subido ningún archivo.']);
            return;
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if ($post_id === 0) {
            wp_send_json_error(['message' => 'ID de caso no válido.']);
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
        
        $new_document = [
            'id' => $attachment_id,
            'name' => basename(get_attached_file($attachment_id)),
            'url' => wp_get_attachment_url($attachment_id)
        ];

        $documents[] = $new_document;
        self::update_json_meta($post_id, '_documentos_adjuntos', $documents);
        
        // Log the action
        $parent_post = get_post($post_id);
        if ($parent_post) {
            $action_string = sprintf('Subió el documento "%s" al caso "%s".', esc_html($new_document['name']), esc_html($parent_post->post_title));
            Flowtax_Activity_Log::log($action_string, $post_id, $parent_post->post_type);
        }

        wp_send_json_success(Flowtax_Debugger::send_logs_in_ajax_response(['message' => 'Archivo subido con éxito.']));
    }

    public static function handle_get_caso_details() {
        self::check_permissions();
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if ($post_id === 0) {
            wp_send_json_error(['message' => 'ID de caso no válido.']);
            return;
        }

        $caso_post = get_post($post_id);
        if (!$caso_post) {
            wp_send_json_error(['message' => 'No se encontró el caso.']);
            return;
        }

        $caso_data = self::format_post_data($caso_post);
        $all_meta = get_post_meta($post_id);
        $caso_data['meta'] = $all_meta;

        $docs_json = isset($all_meta['_documentos_adjuntos'][0]) ? $all_meta['_documentos_adjuntos'][0] : '[]';
        $docs_array = json_decode($docs_json, true);
        if (!is_array($docs_array)) { $docs_array = []; }
        
        $notes_json = isset($all_meta['_historial_notas'][0]) ? $all_meta['_historial_notas'][0] : '[]';
        $notes_array = json_decode($notes_json, true);
        if (!is_array($notes_array)) { $notes_array = []; }

        $caso_data['meta']['_documentos_adjuntos'] = [wp_json_encode($docs_array)];
        $caso_data['meta']['_historial_notas'] = [wp_json_encode($notes_array)];

        $cliente_data = null;
        $cliente_id = get_post_meta($post_id, '_cliente_id', true);
        if ($cliente_id) {
            $cliente_post = get_post($cliente_id);
            if ($cliente_post) {
                $cliente_data = self::format_post_data($cliente_post);
            }
        }

        wp_send_json_success(Flowtax_Debugger::send_logs_in_ajax_response(['caso' => $caso_data, 'cliente' => $cliente_data]));
    }


    public static function handle_get_cliente_perfil() {
        self::check_permissions();
        $cliente_id = isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;
        if ($cliente_id === 0) {
            wp_send_json_error(['message' => 'ID de cliente no válido.']);
            return;
        }
    
        $cliente_post = get_post($cliente_id);
        $cliente_data = self::format_post_data($cliente_post);
        $cliente_data['meta'] = get_post_meta($cliente_id);
    
        $casos = [];
        $post_types = ['impuestos', 'peticion_familiar', 'ciudadania', 'renovacion_residencia', 'traduccion'];
    
        foreach ($post_types as $pt) {
            $query = new WP_Query(['post_type' => $pt, 'posts_per_page' => -1, 'meta_key' => '_cliente_id', 'meta_value' => $cliente_id]);
            if ($query->have_posts()) {
                $casos = array_merge($casos, array_map('self::format_post_data', $query->posts));
            }
        }
    
        // CORRECCIÓN: Usar una función anónima tradicional para compatibilidad con PHP < 7.3
        usort($casos, function($a, $b) {
            return strtotime($b['fecha_raw']) - strtotime($a['fecha_raw']);
        });
    
        wp_send_json_success(Flowtax_Debugger::send_logs_in_ajax_response(['cliente' => $cliente_data, 'casos' => $casos]));
    }
    
    public static function handle_get_search_results() {
        self::check_permissions();
        $search_term = sanitize_text_field($_POST['search_term']);
        $post_type = sanitize_text_field($_POST['post_type']);
        $args = ['post_type' => explode(',', $post_type), 'posts_per_page' => -1, 's' => $search_term];
        $query = new WP_Query($args);
        $results = array_map('self::format_post_data', $query->posts);
        wp_reset_postdata();
        wp_send_json_success(Flowtax_Debugger::send_logs_in_ajax_response($results));
    }

    public static function handle_delete_post() {
        self::check_permissions();
        $post_id = intval($_POST['post_id']);
        if ($post_id > 0) {
            $post_to_delete = get_post($post_id);
            if ($post_to_delete) {
                $cpt_object = get_post_type_object($post_to_delete->post_type);
                $cpt_name = $cpt_object ? strtolower($cpt_object->labels->singular_name) : 'registro';
                $action_string = sprintf('Eliminó el %s "%s".', $cpt_name, esc_html($post_to_delete->post_title));
                Flowtax_Activity_Log::log($action_string);
            }

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
            $view_path = '';
            if ($action === 'perfil') {
                $view_path = FLOWTAX_MS_PLUGIN_DIR . "views/view-cliente-perfil.php";
            } elseif ($action === 'manage') {
                $view_path = FLOWTAX_MS_PLUGIN_DIR . "views/view-caso-manage.php";
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

        $post_type = $_POST['post_type'] ?? '';
        
        // Generación de título en el servidor
        if (in_array($post_type, ['peticion_familiar', 'ciudadania', 'renovacion_residencia', 'impuestos', 'traduccion'])) {
            if (!empty($_POST['cliente_id'])) {
                $cliente_name = get_the_title(intval($_POST['cliente_id']));
                
                switch ($post_type) {
                    case 'impuestos':
                        $ano_fiscal = $_POST['ano_fiscal'] ?? date('Y')-1;
                        $_POST['post_title'] = "Impuestos {$ano_fiscal} para {$cliente_name}";
                        break;
                    case 'traduccion':
                         $origen = $_POST['idioma_origen'] ?? '';
                         $_POST['post_title'] = "Traducción ({$origen}) para {$cliente_name}";
                        break;
                    default: // Casos de inmigración
                        $cpt_object = get_post_type_object($post_type);
                        $cpt_name = $cpt_object ? $cpt_object->labels->singular_name : 'Caso';
                        $_POST['post_title'] = "{$cpt_name} para {$cliente_name}";
                        break;
                }
            }
        }
        
        $validator = new Flowtax_Data_Validator($_POST);
        $errors = $validator->validate();

        if (!empty($errors)) {
            wp_send_json_error(Flowtax_Debugger::send_logs_in_ajax_response(['message' => 'Por favor, corrige los errores.', 'errors' => $errors]));
            return;
        }

        $sanitized_data = $validator->get_sanitized_data();
        $post_id = isset($sanitized_data['post_id']) ? intval($sanitized_data['post_id']) : 0;

        $post_data = ['post_type' => $post_type, 'post_title' => $sanitized_data['post_title'], 'post_status' => 'publish'];
        
        $is_update = $post_id > 0;
        
        if ($is_update) {
            $post_data['ID'] = $post_id;
            $result = wp_update_post($post_data, true);
        } else {
            $result = wp_insert_post($post_data, true);
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error(Flowtax_Debugger::send_logs_in_ajax_response(['message' => $result->get_error_message()]));
            return;
        }
        
        $new_post_id = $result;
        
        $cpt_object = get_post_type_object($post_type);
        $cpt_name = $cpt_object ? strtolower($cpt_object->labels->singular_name) : 'registro';
        $action_verb = $is_update ? 'actualizó el' : 'creó el nuevo';
        $action_string = sprintf('%s %s "%s".', ucfirst($action_verb), $cpt_name, esc_html($sanitized_data['post_title']));
        Flowtax_Activity_Log::log($action_string, $new_post_id, $post_type);

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
            'traduccion' => 'traducciones', 'transaccion' => 'transacciones', 'cliente' => 'clientes',
            'flowtax_log' => 'actividad'
        ];
        return $map[$post_type] ?? 'dashboard';
    }
    
    public static function format_post_data($post) {
        if (!$post instanceof WP_Post) return [];
        
        $post_id = $post->ID;
        $post_type = get_post_type($post_id);

        if ($post_type === 'flowtax_log') {
            return [
                'ID' => $post_id,
                'title' => get_the_title($post_id),
                'author' => get_post_meta($post_id, '_user_name', true) ?: 'Sistema',
                'time_ago' => human_time_diff(get_the_time('U', $post_id), current_time('timestamp')) . ' atrás',
                'related_post_id' => get_post_meta($post_id, '_related_post_id', true),
                'related_post_type' => get_post_meta($post_id, '_related_post_type', true),
                'view_slug' => self::get_view_for_post_type(get_post_meta($post_id, '_related_post_type', true))
            ];
        }

        $cliente_id = get_post_meta($post_id, '_cliente_id', true);
        $cliente_nombre = $cliente_id ? get_the_title($cliente_id) : 'N/A';
        
        $estado_terms = get_the_terms($post_id, 'estado_caso');
        $estado = 'Sin estado';
        $estado_color = 'bg-slate-100 text-slate-800';
        
        if (!empty($estado_terms) && !is_wp_error($estado_terms)) {
            $estado_term = $estado_terms[0];
            $estado = esc_html($estado_term->name);
            $color_meta = get_term_meta($estado_term->term_id, 'color_class', true);
            if (!empty($color_meta)) {
                $estado_color = $color_meta;
            }
        }
        
        $post_type_obj = get_post_type_object($post_type);
        $singular_name = $post_type_obj ? $post_type_obj->labels->singular_name : '';
        
        return [
            'ID' => $post_id, 'title' => get_the_title($post_id), 'post_type' => $post_type,
            'singular_name' => $singular_name, 'cliente_nombre' => $cliente_nombre, 'estado' => $estado,
            'estado_color' => $estado_color, 'fecha' => get_the_date('d M Y', $post_id),
            'fecha_raw' => $post->post_date,
            'email' => get_post_meta($post_id, '_email', true), 'telefono' => get_post_meta($post_id, '_telefono', true),
            'ano_fiscal' => get_post_meta($post_id, '_ano_fiscal', true),
            'idioma_origen' => get_post_meta($post_id, '_idioma_origen', true),
            'idioma_destino' => get_post_meta($post_id, '_idioma_destino', true),
            'view_slug' => self::get_view_for_post_type($post_type),
        ];
    }
}

