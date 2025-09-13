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
            'add_note'
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
    
    /**
     * Helper function to safely decode JSON from post meta.
     * @return array Decoded data or an empty array on failure.
     */
    private static function get_json_meta($post_id, $key) {
        $json = get_post_meta($post_id, $key, true);
        if (empty($json)) {
            return [];
        }
        $data = json_decode($json, true);
        return (is_array($data)) ? $data : [];
    }
    
    /**
     * Helper function to safely encode and update JSON post meta.
     */
    private static function update_json_meta($post_id, $key, $data) {
        $json = wp_json_encode($data);
        update_post_meta($post_id, $key, $json);
    }


    public static function handle_add_note() {
        self::check_permissions();
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $note_content_raw = isset($_POST['note_content']) ? trim(stripslashes($_POST['note_content'])) : '';

        if (empty($note_content_raw) || $post_id === 0) {
            wp_send_json_error(['message' => 'Faltan datos para añadir la nota. El contenido no puede estar vacío.']);
            return;
        }
        
        // Sanitize for storage
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

        $updated_documents = array_filter($documents, function($doc) use ($attachment_id) {
            return isset($doc['id']) && intval($doc['id']) !== $attachment_id;
        });

        wp_delete_attachment($attachment_id, true);
        self::update_json_meta($post_id, '_documentos_adjuntos', array_values($updated_documents));

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

        // CORRECCIÓN: Procesar de forma segura los metadatos JSON para garantizar que siempre sean válidos.
        // Se decodifica y re-codifica para limpiar cualquier dato malformado de la DB.
        $docs_json = isset($all_meta['_documentos_adjuntos'][0]) ? $all_meta['_documentos_adjuntos'][0] : '[]';
        $docs_array = json_decode($docs_json, true);
        if (!is_array($docs_array)) { $docs_array = []; }
        
        $notes_json = isset($all_meta['_historial_notas'][0]) ? $all_meta['_historial_notas'][0] : '[]';
        $notes_array = json_decode($notes_json, true);
        if (!is_array($notes_array)) { $notes_array = []; }

        // Sobrescribir los metadatos con una cadena JSON válida, asegurando que el JS no falle.
        $caso_data['meta']['_documentos_adjuntos'] = [wp_json_encode($docs_array)];
        $caso_data['meta']['_historial_notas'] = [wp_json_encode($notes_array)];

        // Obtener datos del cliente asociado
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
        
        // Ordenar casos por fecha de forma descendente
        usort($casos, function($a, $b) {
            return strtotime($b['fecha_raw']) - strtotime($a['fecha_raw']);
        });

        wp_send_json_success(Flowtax_Debugger::send_logs_in_ajax_response(['cliente' => $cliente_data, 'casos' => $casos]));
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

        // Generar título en el servidor como respaldo para casos de inmigración
        $post_type = $_POST['post_type'] ?? '';
        if (in_array($post_type, ['peticion_familiar', 'ciudadania', 'renovacion_residencia'])) {
            if (empty($_POST['post_title']) && !empty($_POST['cliente_id']) && !empty($_POST['post_type'])) {
                $cliente_name = get_the_title(intval($_POST['cliente_id']));
                $cpt_object = get_post_type_object($post_type);
                $cpt_name = $cpt_object ? $cpt_object->labels->singular_name : 'Caso';
                $_POST['post_title'] = "{$cpt_name} para {$cliente_name}";
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
        
        if ($post_id > 0) {
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
        
        if (!empty($estado_terms) && !is_wp_error($estado_terms)) {
            $estado_term = $estado_terms[0];
            $estado = esc_html($estado_term->name);
            $color_meta = get_term_meta($estado_term->term_id, 'color_class', true);
            if (!empty($color_meta)) {
                $estado_color = $color_meta;
            }
        }
        
        $post_type_obj = get_post_type_object(get_post_type($post_id));
        $singular_name = $post_type_obj ? $post_type_obj->labels->singular_name : '';
        
        return [
            'ID' => $post_id, 'title' => get_the_title($post_id), 'post_type' => get_post_type($post_id),
            'singular_name' => $singular_name, 'cliente_nombre' => $cliente_nombre, 'estado' => $estado,
            'estado_color' => $estado_color, 'fecha' => get_the_date('d M Y', $post_id),
            'fecha_raw' => $post->post_date,
            'email' => get_post_meta($post_id, '_email', true), 'telefono' => get_post_meta($post_id, '_telefono', true),
            'ano_fiscal' => get_post_meta($post_id, '_ano_fiscal', true),
            'idioma_origen' => get_post_meta($post_id, '_idioma_origen', true),
            'idioma_destino' => get_post_meta($post_id, '_idioma_destino', true),
            'view_slug' => self::get_view_for_post_type(get_post_type($post_id)),
        ];
    }
}

