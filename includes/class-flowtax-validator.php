<?php
class Flowtax_Data_Validator {
    private $post_data;
    private $sanitized_data = [];
    private $errors = [];

    public function __construct(array $post_data) {
        $this->post_data = $post_data;
    }

    public function validate() {
        $post_type = sanitize_key($this->post_data['post_type'] ?? '');

        if (empty($post_type)) {
            $this->errors['post_type'] = 'El tipo de registro es inválido.';
        }

        // Validación del título general
        if (empty($this->post_data['post_title'])) {
            $this->errors['post_title'] = 'El título es obligatorio.';
        }

        // Validaciones específicas por tipo de post
        switch ($post_type) {
            case 'cliente':
                if (empty($this->post_data['post_title'])) {
                    $this->errors['post_title'] = 'El nombre del cliente es obligatorio.';
                }
                if (empty($this->post_data['email']) || !is_email($this->post_data['email'])) {
                    $this->errors['email'] = 'Por favor, introduce un email válido.';
                }
                if (empty($this->post_data['telefono'])) {
                    $this->errors['telefono'] = 'El número de teléfono es obligatorio.';
                }
                break;

            case 'impuestos':
                if (empty($this->post_data['cliente_id'])) {
                    $this->errors['cliente_id'] = 'Debes seleccionar un cliente.';
                }
                if (empty($this->post_data['ano_fiscal'])) {
                    $this->errors['ano_fiscal'] = 'El año fiscal es obligatorio.';
                } elseif (!is_numeric($this->post_data['ano_fiscal']) || intval($this->post_data['ano_fiscal']) < 1980 || intval($this->post_data['ano_fiscal']) > (int)date('Y')) {
                    $this->errors['ano_fiscal'] = 'El año fiscal parece incorrecto.';
                }
                if (strpos($this->post_data['post_title'], 'Cliente No Seleccionado') !== false || strpos($this->post_data['post_title'], 'Nueva Declaración') !== false) {
                    $this->errors['cliente_id'] = 'El título no se generó correctamente. Asegúrate de seleccionar un cliente.';
                }
                break;
            
            case 'traduccion':
                if (empty($this->post_data['cliente_id'])) {
                    $this->errors['cliente_id'] = 'Debes seleccionar un cliente.';
                }
                 if (empty($this->post_data['idioma_origen'])) {
                    $this->errors['idioma_origen'] = 'El idioma de origen es obligatorio.';
                }
                if (empty($this->post_data['idioma_destino'])) {
                    $this->errors['idioma_destino'] = 'El idioma de destino es obligatorio.';
                }
                break;
        }

        return $this->errors;
    }
    
    public function get_sanitized_data() {
        foreach($this->post_data as $key => $value) {
            if (is_array($value)) {
                $this->sanitized_data[$key] = filter_var_array($value, FILTER_SANITIZE_STRING);
            } else {
                 if (strpos($key, 'detalle') !== false) {
                    $this->sanitized_data[$key] = sanitize_textarea_field($value);
                } elseif (strpos($key, 'email') !== false) {
                     $this->sanitized_data[$key] = sanitize_email($value);
                } else {
                    $this->sanitized_data[$key] = sanitize_text_field(stripslashes($value));
                }
            }
        }
        return $this->sanitized_data;
    }
}

