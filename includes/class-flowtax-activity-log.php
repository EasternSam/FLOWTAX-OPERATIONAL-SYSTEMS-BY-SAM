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

            // After logging, attempt to send a push notification
            self::maybe_send_push_notification($action_string, $current_user->display_name);
        }

        return $log_post_id;
    }

    private static function maybe_send_push_notification($message, $actor_name) {
        // Get all administrators
        $admins = get_users(['role' => 'administrator']);

        foreach ($admins as $admin) {
            // Check if this admin has watchman mode enabled
            $is_watchman_enabled = get_user_meta($admin->ID, 'flowtax_watchman_mode_enabled', true);

            if ($is_watchman_enabled) {
                $notification_title = "Actividad de {$actor_name}";
                self::send_push_notification($admin->ID, $notification_title, $message);
            }
        }
    }

    private static function send_push_notification($user_id, $title, $body) {
        // This is a placeholder for a real push notification service integration.
        // To implement this, you would need a service like Firebase Cloud Messaging (FCM), OneSignal, etc.
        // 1. The user would need to register their device from a mobile app or PWA, sending the device token to be stored in user meta.
        // 2. You would retrieve that token here.
        // 3. You would make an API call to your chosen push service.
        
        // --- Integraci¨®n con OneSignal ---
        $one_signal_app_id = '912f40e0-b0b4-4182-8be7-3fca929d3769';
        $one_signal_api_key = 'k26his5jcuhcfci5jvxmsvx63';
        
        // El 'player_id' identifica el dispositivo del usuario. Debe ser guardado cuando el usuario acepta recibir notificaciones.
        $player_id = get_user_meta($user_id, 'onesignal_player_id', true);

        if ($player_id && !empty($one_signal_app_id)) {
            $fields = [
                'app_id' => $one_signal_app_id,
                'include_player_ids' => [$player_id],
                'headings' => ['en' => $title],
                'contents' => ['en' => $body],
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8', 'Authorization: Basic ' . $one_signal_api_key]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            $response = curl_exec($ch);
            curl_close($ch);

            Flowtax_Debugger::log("OneSignal API Response: " . $response, 'Watchman Mode');
        
        } else {
             Flowtax_Debugger::log("PUSH NOTIFICATION (SIMULADA - Falta Player ID o App ID) al Usuario ID {$user_id}: [{$title}] - {$body}", 'Watchman Mode');
        }
    }
}

