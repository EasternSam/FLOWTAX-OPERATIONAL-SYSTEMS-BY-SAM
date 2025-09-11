<?php
class Flowtax_Data_Validator {
    private $post_data;
    private $sanitized_data = [];
    private $errors = [];

    public function __construct(array $post_data) {
        $this->post_data = $post_data;
    }

    public function validate() {
        if (empty($this->post_data['post_title'])) {
            $this->errors['post_title'] = 'El título es obligatorio.';
        }
        if (empty($this->post_data['post_type'])) {
            $this->errors['post_type'] = 'El tipo de registro es inválido.';
        }
        
        $post_type = sanitize_key($this->post_data['post_type']);
        
        // Ejemplo de validación específica por post_type
        if ($post_type === 'cliente' && !is_email($this->post_data['email'])) {
            $this->errors['email'] = 'Por favor, introduce un email válido.';
        }
        
        if ($post_type === 'impuestos' && !empty($this->post_data['ano_fiscal']) && intval($this->post_data['ano_fiscal']) < 1980) {
            $this->errors['ano_fiscal'] = 'El año fiscal parece incorrecto.';
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
                    $this->sanitized_data[$key] = sanitize_text_field($value);
                }
            }
        }
        return $this->sanitized_data;
    }
}
