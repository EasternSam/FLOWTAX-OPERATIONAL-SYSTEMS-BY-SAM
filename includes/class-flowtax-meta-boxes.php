<?php
class Flowtax_Meta_Boxes {

    public static function init() {
        // No se necesitan hooks 'add_meta_box' porque renderizamos directo en la SPA
    }
    
    public static function save_meta_data($post_id, $data) {
        $meta_fields = [
            'cliente' => ['telefono', 'email', 'direccion', 'ciudad', 'estado_provincia', 'codigo_postal', 'tax_id'],
            'deuda' => ['cliente_id', 'monto_deuda', 'estado_deuda', 'fecha_vencimiento', 'link_pago_provider'],
            'empleado' => ['cliente_id', 'salario', 'frecuencia_pago'],
            'nomina' => ['cliente_id', 'fecha_pago', 'monto_total'],
            'impuestos' => ['cliente_id', 'ano_fiscal', 'tipo_declaracion', 'ingresos_detalle', 'deducciones_detalle', 'reembolso_estimado', 'monto_adeudado', 'notas_preparador'],
            'peticion_familiar' => ['cliente_id', 'beneficiario_nombre', 'relacion', 'beneficiario_dob', 'uscis_receipt', 'priority_date', 'service_center', 'fecha_envio', 'fecha_biometricos', 'fecha_entrevista', 'fecha_aprobacion', 'notas_caso'],
            'ciudadania' => ['cliente_id', 'a_number', 'uscis_receipt', 'service_center', 'fecha_envio', 'fecha_biometricos', 'fecha_entrevista', 'fecha_juramentacion', 'notas_caso'],
            'renovacion_residencia' => ['cliente_id', 'a_number', 'card_expiry', 'uscis_receipt', 'fecha_envio', 'fecha_biometricos', 'fecha_tarjeta_enviada', 'notas_caso'],
            'traduccion' => ['cliente_id', 'idioma_origen', 'idioma_destino', 'num_paginas', 'costo_total', 'fecha_entrega', 'estado_pago', 'notas_proyecto'],
            'transaccion' => ['tipo_transaccion', 'nombre_entidad', 'monto_transaccion', 'comision', 'fecha_transaccion']
        ];
        
        $post_type = get_post_type($post_id);

        if (isset($meta_fields[$post_type])) {
            foreach ($meta_fields[$post_type] as $field) {
                if (isset($data[$field])) {
                    update_post_meta($post_id, '_' . $field, $data[$field]);
                }
            }
        }
    }
}

