<?php
class Flowtax_Activity_Log {
    
    public static function log($action_string, $related_post_id = 0, $related_post_type = '') {
        $current_user = wp_get_current_user();
        
        $log_post_id = wp_insert_post([
            'post_title'  => $action_string,
            'post_status' => 'publish',
            'post_type'   => 'flowtax_log',
            'post_author' => $current_user->ID,
        ]);

        if ($log_post_id && !is_wp_error($log_post_id)) {
            update_post_meta($log_post_id, '_user_name', $current_user->display_name);
            if ($related_post_id > 0) {
                update_post_meta($log_post_id, '_related_post_id', $related_post_id);
            }
            if (!empty($related_post_type)) {
                update_post_meta($log_post_id, '_related_post_type', $related_post_type);
            }

            self::maybe_send_push_notification($action_string, $current_user->display_name, $current_user->ID, $related_post_id, $related_post_type);
        }

        return $log_post_id;
    }

    private static function maybe_send_push_notification($message, $actor_name, $actor_id, $related_post_id, $related_post_type) {
        $admins = get_users(['role' => 'administrator']);

        foreach ($admins as $admin) {
            if ($admin->ID === $actor_id) {
                continue;
            }

            $is_watchman_enabled = get_user_meta($admin->ID, 'flowtax_watchman_mode_enabled', true);

            if ($is_watchman_enabled) {
                $notification_title = "Actividad de {$actor_name}";
                self::send_push_notification($admin->ID, $notification_title, $message, $related_post_id, $related_post_type);
            }
        }
    }

    private static function send_push_notification($user_id, $title, $body, $related_post_id, $related_post_type) {
        $one_signal_app_id = defined('FLOWTAX_ONESIGNAL_APP_ID') ? FLOWTAX_ONESIGNAL_APP_ID : '';
        $one_signal_api_key = defined('FLOWTAX_ONESIGNAL_API_KEY') ? FLOWTAX_ONESIGNAL_API_KEY : '';
        $player_id = get_user_meta($user_id, 'onesignal_player_id', true);

        if ($player_id && !empty($one_signal_app_id) && !empty($one_signal_api_key)) {
            $fields = [
                'app_id' => $one_signal_app_id,
                'include_player_ids' => [$player_id],
                'headings' => ['en' => $title],
                'contents' => ['en' => $body],
            ];
            
            // MEJORA: Añadir URL para que la notificación push sea clickeable
            if ($related_post_id > 0) {
                $view_slug = Flowtax_Ajax_Handler::get_view_for_post_type($related_post_type);
                $action = ($related_post_type === 'cliente') ? 'perfil' : 'manage';
                // La URL debe apuntar a la página que contiene el shortcode de la SPA.
                // Asumimos que está en /inicio/ como en la configuración del plugin.
                $base_url = home_url('/inicio/');
                $fields['url'] = "{$base_url}{$view_slug}/{$action}/{$related_post_id}/";
            }


            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json; charset=utf-8',
                'Authorization: Basic ' . $one_signal_api_key
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
            $response = curl_exec($ch);
            curl_close($ch);

            Flowtax_Debugger::log("OneSignal API Response: " . $response, 'Watchman Mode');
        
        } else {
             $log_message = "PUSH NOTIFICATION (SIMULADA) al Usuario ID {$user_id}: [{$title}] - {$body}.";
             if(empty($one_signal_app_id) || empty($one_signal_api_key)) {
                $log_message .= " Razón: No se han definido las constantes FLOWTAX_ONESIGNAL_APP_ID o FLOWTAX_ONESIGNAL_API_KEY.";
             }
             if(empty($player_id)){
                 $log_message .= " Razón: Falta el Player ID para el usuario.";
             }
             Flowtax_Debugger::log($log_message, 'Watchman Mode');
        }
    }
}

