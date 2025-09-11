<?php
class Flowtax_CPTs {
    public static function init() {
        add_action('init', array(__CLASS__, 'register_all'));
    }

    public static function register_all() {
        self::register_post_types();
        self::register_taxonomies();
    }

    private static function register_post_types() {
        $post_types = [
            'cliente' => ['singular' => 'Cliente', 'plural' => 'Clientes', 'icon' => 'dashicons-id-alt'],
            'empleado' => ['singular' => 'Empleado', 'plural' => 'Empleados', 'icon' => 'dashicons-admin-users'],
            'nomina' => ['singular' => 'Nómina', 'plural' => 'Nóminas', 'icon' => 'dashicons-calculator'],
            'impuestos' => ['singular' => 'Declaración', 'plural' => 'Impuestos', 'icon' => 'dashicons-media-spreadsheet'],
            'peticion_familiar' => ['singular' => 'Petición Familiar', 'plural' => 'Peticiones', 'icon' => 'dashicons-groups'],
            'ciudadania' => ['singular' => 'Caso de Ciudadanía', 'plural' => 'Ciudadanía', 'icon' => 'dashicons-flag'],
            'renovacion_residencia' => ['singular' => 'Renovación de Residencia', 'plural' => 'Renovación Residencia', 'icon' => 'dashicons-id'],
            'traduccion' => ['singular' => 'Traducción', 'plural' => 'Traducciones', 'icon' => 'dashicons-translation'],
            'transaccion' => ['singular' => 'Transacción', 'plural' => 'Pagos y Cheques', 'icon' => 'dashicons-money-alt']
        ];

        foreach ($post_types as $slug => $details) {
            register_post_type($slug, [
                'labels' => ['name' => $details['plural'], 'singular_name' => $details['singular']],
                'public' => false, 'show_ui' => true, 'show_in_menu' => 'flow-tax-manager',
                'supports' => ['title', 'editor'], 'menu_icon' => $details['icon']
            ]);
        }
    }

    private static function register_taxonomies() {
        register_taxonomy('estado_caso', ['impuestos', 'peticion_familiar', 'ciudadania', 'renovacion_residencia', 'traduccion'], [
            'hierarchical' => true, 'labels' => ['name' => 'Estado General del Caso'],
            'show_ui' => true, 'show_admin_column' => true
        ]);
    }

    public static function insert_initial_terms() {
        $estados = [
            'Nuevo' => 'bg-blue-100 text-blue-800',
            'En Proceso' => 'bg-yellow-100 text-yellow-800',
            'Pendiente de Cliente' => 'bg-orange-100 text-orange-800',
            'Listo para Revisión' => 'bg-indigo-100 text-indigo-800',
            'Completado' => 'bg-green-100 text-green-800',
            'Archivado' => 'bg-gray-100 text-gray-800',
            'Cancelado' => 'bg-red-100 text-red-800'
        ];
        foreach ($estados as $estado => $color_class) {
            if (!term_exists($estado, 'estado_caso')) {
                $term = wp_insert_term($estado, 'estado_caso');
                if (!is_wp_error($term)) {
                    add_term_meta($term['term_id'], 'color_class', $color_class, true);
                }
            }
        }
    }
}
