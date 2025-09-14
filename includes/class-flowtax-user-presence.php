<?php
/**
 * Flowtax_User_Presence
 *
 * Gestiona la lógica para rastrear la actividad de los usuarios en tiempo real.
 *
 * @since 8.2.0
 */
class Flowtax_User_Presence {

    /**
     * Actualiza la actividad de un usuario, indicando su ubicación actual.
     *
     * @param int    $user_id  El ID del usuario.
     * @param string $location Una descripción de la ubicación/acción actual del usuario.
     */
    public static function update_user_activity($user_id, $location) {
        if (empty($user_id)) {
            return;
        }
        $user_data = get_userdata($user_id);
        if (!$user_data) {
            return;
        }

        $presence_info = [
            'user_id'      => $user_id,
            'display_name' => $user_data->display_name,
            'avatar_url'   => get_avatar_url($user_id),
            'location'     => $location,
            'timestamp'    => time(),
        ];
        
        // Almacena la actividad del usuario por 2 minutos. Si no hay más actividad, se considerará offline.
        set_transient('flowtax_user_online_' . $user_id, $presence_info, 2 * MINUTE_IN_SECONDS);
    }

    /**
     * Obtiene una lista de todos los usuarios actualmente en línea.
     *
     * @return array La lista de usuarios en línea.
     */
    public static function get_online_users() {
        global $wpdb;
        $online_users = [];
        $transient_prefix = '_transient_flowtax_user_online_';
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE %s",
                $wpdb->esc_like($transient_prefix) . '%'
            )
        );

        $current_user_id = get_current_user_id();

        foreach ($results as $result) {
            $user_info = maybe_unserialize($result->option_value);
            // No mostrar al usuario actual en la lista de "otros" usuarios en línea.
            if (is_array($user_info) && isset($user_info['user_id']) && $user_info['user_id'] != $current_user_id) {
                $online_users[] = $user_info;
            }
        }

        return $online_users;
    }
}
