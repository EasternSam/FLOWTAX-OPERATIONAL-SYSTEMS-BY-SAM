<?php
class Flowtax_CPTs {
    public static function init() {
        add_action('init', array(__CLASS__, 'register_all'));
        // CORRECCIÓN: Aseguramos que los términos se creen si no existen en cada carga.
        add_action('init', array(__CLASS__, 'insert_initial_terms'));
    }

    public static function register_all() {
        self::register_post_types();
        self::register_taxonomies();
    }

    private static function register_post_types() {
        $post_types = [
            'cliente' => ['singular' => 'Cliente', 'plural' => 'Clientes', 'icon' => 'dashicons-id-alt'],
            'deuda' => ['singular' => 'Deuda', 'plural' => 'Deudas', 'icon' => 'dashicons-money-alt'],
            'impuestos' => ['singular' => 'Declaración', 'plural' => 'Impuestos', 'icon' => 'dashicons-media-spreadsheet'],
            'peticion_familiar' => ['singular' => 'Petición Familiar', 'plural' => 'Peticiones', 'icon' => 'dashicons-groups'],
            'ciudadania' => ['singular' => 'Caso de Ciudadanía', 'plural' => 'Ciudadanía', 'icon' => 'dashicons-flag'],
            'renovacion_residencia' => ['singular' => 'Renovación de Residencia', 'plural' => 'Renovación Residencia', 'icon' => 'dashicons-id'],
            'traduccion' => ['singular' => 'Traducción', 'plural' => 'Traducciones', 'icon' => 'dashicons-translation'],
            'flowtax_log' => ['singular' => 'Registro de Actividad', 'plural' => 'Registros de Actividad', 'icon' => 'dashicons-list-view']
        ];

        foreach ($post_types as $slug => $details) {
            $is_log_cpt = ($slug === 'flowtax_log');
            register_post_type($slug, [
                'labels' => ['name' => $details['plural'], 'singular_name' => $details['singular']],
                'public' => false, 
                'show_ui' => true, 
                'show_in_menu' => $is_log_cpt ? false : 'flow-tax-manager',
                'supports' => ['title'], 
                'menu_icon' => $details['icon']
            ]);
        }
    }

    private static function register_taxonomies() {
        register_taxonomy('estado_caso', 
            ['impuestos', 'peticion_familiar', 'ciudadania', 'renovacion_residencia', 'traduccion'], 
            ['hierarchical' => true, 'labels' => ['name' => 'Estado de Caso'], 'show_ui' => true, 'show_admin_column' => true]
        );

        register_taxonomy('estado_deuda', 
            ['deuda'], 
            ['hierarchical' => true, 'labels' => ['name' => 'Estado de Deuda'], 'show_ui' => true, 'show_admin_column' => true]
        );
    }

    public static function insert_initial_terms() {
        $estados_caso = [
            'Nuevo' => 'bg-blue-100 text-blue-800',
            'En Proceso' => 'bg-yellow-100 text-yellow-800',
            'Pendiente de Cliente' => 'bg-orange-100 text-orange-800',
            'Listo para Revisión' => 'bg-indigo-100 text-indigo-800',
            'Completado' => 'bg-green-100 text-green-800',
            'Archivado' => 'bg-gray-100 text-gray-800',
            'Cancelado' => 'bg-red-100 text-red-800'
        ];
        foreach ($estados_caso as $estado => $color_class) {
            if (!term_exists($estado, 'estado_caso')) {
                $term = wp_insert_term($estado, 'estado_caso');
                if (!is_wp_error($term)) {
                    add_term_meta($term['term_id'], 'color_class', $color_class, true);
                }
            }
        }

        $estados_deuda = [
            'Pendiente' => 'bg-yellow-100 text-yellow-800',
            'Abono' => 'bg-sky-100 text-sky-800',
            'Pagado' => 'bg-green-100 text-green-800',
            'Vencido' => 'bg-red-100 text-red-800',
            'Cancelado' => 'bg-gray-100 text-gray-800'
        ];
        foreach ($estados_deuda as $estado => $color_class) {
            if (!term_exists($estado, 'estado_deuda')) {
                $term = wp_insert_term($estado, 'estado_deuda', ['slug' => sanitize_title($estado)]);
                if (!is_wp_error($term)) {
                    add_term_meta($term['term_id'], 'color_class', $color_class, true);
                }
            }
        }
    }
}
