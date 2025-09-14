<?php
class Flowtax_Reminders {

    public static function send_reminder($deuda_id, $method = 'all') {
        $deuda_post = get_post($deuda_id);
        if (!$deuda_post || get_post_type($deuda_post) !== 'deuda') {
            return ['success' => false, 'message' => 'Deuda no encontrada.'];
        }

        $cliente_id = get_post_meta($deuda_id, '_cliente_id', true);
        $cliente_post = get_post($cliente_id);
        if (!$cliente_post) {
            return ['success' => false, 'message' => 'Cliente asociado no encontrado.'];
        }

        $results = [];

        if ($method === 'email' || $method === 'all') {
            $results['email'] = self::send_email_reminder($deuda_post, $cliente_post);
        }

        if ($method === 'whatsapp' || $method === 'all') {
            $results['whatsapp'] = self::send_whatsapp_reminder($deuda_post, $cliente_post);
        }
        
        $log_message = sprintf('Envió un recordatorio de pago para "%s" al cliente %s.', 
            esc_html($deuda_post->post_title),
            esc_html($cliente_post->post_title)
        );
        Flowtax_Activity_Log::log($log_message, $deuda_id, 'deuda');

        return ['success' => true, 'results' => $results];
    }

    private static function get_payment_link_for_deuda($deuda_post) {
        $settings = get_option('flowtax_settings', []);
        $provider = get_post_meta($deuda_post->ID, '_link_pago_provider', true);
        
        if ($provider === 'clover' && !empty($settings['payment_link_clover'])) {
            return $settings['payment_link_clover'];
        } elseif ($provider === 'square' && !empty($settings['payment_link_square'])) {
            return $settings['payment_link_square'];
        }
        return !empty($settings['payment_link_clover']) ? $settings['payment_link_clover'] : (!empty($settings['payment_link_square']) ? $settings['payment_link_square'] : '');
    }

    private static function send_email_reminder($deuda_post, $cliente_post) {
        $settings = get_option('flowtax_settings', []);
        $to = get_post_meta($cliente_post->ID, '_email', true);
        if (empty($to) || !is_email($to)) {
            return ['success' => false, 'message' => 'El cliente no tiene un email válido.'];
        }

        $monto = get_post_meta($deuda_post->ID, '_monto_deuda', true);
        $link_pago = self::get_payment_link_for_deuda($deuda_post);
        if (empty($link_pago)) {
            return ['success' => false, 'message' => 'No hay un enlace de pago configurado en los Ajustes.'];
        }

        $concepto = $deuda_post->post_title;
        $nombre_cliente = $cliente_post->post_title;

        $from_name = !empty($settings['from_name']) ? $settings['from_name'] : get_bloginfo('name');
        $from_email = !empty($settings['from_email']) ? $settings['from_email'] : get_bloginfo('admin_email');
        
        $subject = 'Recordatorio de Pago Pendiente - ' . $from_name;
        
        // CORRECCIÓN: Cambiado a formato HTML para mejor apariencia y un botón de pago.
        $body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family: Arial, sans-serif; color: #333;">';
        $body .= '<div style="max-width: 600px; margin: auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">';
        $body .= '<h2 style="color: #005a9c;">Recordatorio de Pago</h2>';
        $body .= "<p>Hola " . esc_html($nombre_cliente) . ",</p>";
        $body .= "<p>Te escribimos para recordarte amablemente que tienes un pago pendiente con nosotros.</p>";
        $body .= '<hr style="border: 0; border-top: 1px solid #eee;">';
        $body .= '<p style="margin: 20px 0;"><strong>Concepto:</strong> ' . esc_html($concepto) . '<br>';
        $body .= '<strong>Monto:</strong> $' . number_format($monto, 2) . ' USD</p>';
        $body .= '<hr style="border: 0; border-top: 1px solid #eee;">';
        $body .= '<p style="text-align: center; margin: 30px 0;">Puedes realizar tu pago de forma segura haciendo clic en el siguiente botón:</p>';
        $body .= '<p style="text-align: center;"><a href="' . esc_url($link_pago) . '" style="background-color: #0073aa; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;">Pagar Ahora</a></p>';
        $body .= "<p>Si ya has realizado el pago, por favor ignora este mensaje.</p>";
        $body .= "<p>Gracias,<br><strong>El equipo de " . esc_html($from_name) . "</strong></p>";
        $body .= '</div></body></html>';

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>'
        ];

        if (wp_mail($to, $subject, $body, $headers)) {
            return ['success' => true, 'message' => 'Email enviado a ' . $to];
        } else {
            return ['success' => false, 'message' => 'Error del sistema al enviar el email. Asegúrate de tener un plugin SMTP configurado.'];
        }
    }
    
    private static function send_whatsapp_reminder($deuda_post, $cliente_post) {
        $settings = get_option('flowtax_settings', []);
        $api_token = $settings['whatsapp_api_token'] ?? '';
        $phone_id = $settings['whatsapp_phone_id'] ?? '';
        $template_name = $settings['whatsapp_template_name'] ?? '';

        $phone = get_post_meta($cliente_post->ID, '_telefono', true);
        $phone_clean = preg_replace('/[^0-9]/', '', $phone);
        if (empty($phone_clean)) {
            return ['success' => false, 'message' => 'El cliente no tiene un teléfono válido.'];
        }
        if (strlen($phone_clean) == 10 && (substr($phone_clean, 0, 1) === '8' || substr($phone_clean, 0, 1) === '9')) {
            $phone_clean = '1' . $phone_clean;
        }

        if (empty($api_token) || empty($phone_id) || empty($template_name)) {
            return self::generate_manual_whatsapp_link($deuda_post, $cliente_post, $phone_clean);
        }

        $monto = get_post_meta($deuda_post->ID, '_monto_deuda', true);
        $link_pago = self::get_payment_link_for_deuda($deuda_post);
        if (empty($link_pago)) {
            return ['success' => false, 'message' => 'No hay un enlace de pago configurado en los Ajustes.'];
        }

        $concepto = $deuda_post->post_title;
        $nombre_cliente = explode(' ', $cliente_post->post_title)[0];

        $api_url = "https://graph.facebook.com/v18.0/{$phone_id}/messages";

        $payload = ['messaging_product' => 'whatsapp', 'to' => $phone_clean, 'type' => 'template', 'template' => ['name' => $template_name, 'language' => ['code' => 'es'], 'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $nombre_cliente], ['type' => 'text', 'text' => $concepto], ['type' => 'text', 'text' => number_format($monto, 2)], ['type' => 'text', 'text' => $link_pago]]]]]];

        $response = wp_remote_post($api_url, ['method' => 'POST', 'headers' => ['Authorization' => 'Bearer ' . $api_token, 'Content-Type' => 'application/json'], 'body' => json_encode($payload)]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => 'Error de conexión con la API de WhatsApp.'];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['messages'][0]['id'])) {
            return ['success' => true, 'message' => 'Recordatorio enviado por WhatsApp API.', 'method' => 'api'];
        } else {
            $error_message = $data['error']['message'] ?? 'Error desconocido en la API.';
            Flowtax_Debugger::log($data, 'WhatsApp API Error');
            return ['success' => false, 'message' => 'API Error: ' . $error_message];
        }
    }

    private static function generate_manual_whatsapp_link($deuda_post, $cliente_post, $phone_clean) {
        $monto = get_post_meta($deuda_post->ID, '_monto_deuda', true);
        $link_pago = self::get_payment_link_for_deuda($deuda_post);
        if (empty($link_pago)) {
            return ['success' => false, 'message' => 'No hay un enlace de pago configurado en los Ajustes.'];
        }

        $concepto = $deuda_post->post_title;
        $nombre_cliente = $cliente_post->post_title;

        $message = "Hola " . $nombre_cliente . ", te recordamos tu pago pendiente con FlowTax por el concepto de *" . $concepto . "*.";
        $message .= "\n\n*Monto:* $" . number_format($monto, 2) . " USD";
        $message .= "\n\nPuedes pagar de forma segura aquí: " . $link_pago;
        $message .= "\n\n¡Gracias!";

        $whatsapp_url = 'https://wa.me/' . $phone_clean . '?text=' . rawurlencode($message);

        return ['success' => true, 'method' => 'manual', 'url' => $whatsapp_url];
    }
}

