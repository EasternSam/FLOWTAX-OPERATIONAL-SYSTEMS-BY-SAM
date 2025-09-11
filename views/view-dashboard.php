<?php
// Obtener algunas estadísticas rápidas
$counts = [
    'impuestos' => wp_count_posts('impuestos')->publish,
    'inmigracion' => wp_count_posts('peticion_familiar')->publish + wp_count_posts('ciudadania')->publish + wp_count_posts('renovacion_residencia')->publish,
    'clientes' => wp_count_posts('cliente')->publish,
    'traducciones' => wp_count_posts('traduccion')->publish,
];

// Obtener los 5 casos más recientes
$recent_query = new WP_Query([
    'post_type' => ['impuestos', 'peticion_familiar', 'ciudadania', 'renovacion_residencia', 'traduccion'],
    'posts_per_page' => 5,
    'orderby' => 'date',
    'order' => 'DESC'
]);

?>
<div class="p-8">
    <header class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Panel de Control</h1>
        <p class="text-gray-500 mt-1">Bienvenido de nuevo, aquí tienes un resumen de la actividad reciente.</p>
    </header>

    <!-- KPIs -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <?php
        $kpis = [
            ['title' => 'Casos de Impuestos', 'count' => $counts['impuestos'], 'icon' => 'fa-calculator', 'color' => 'blue'],
            ['title' => 'Casos de Inmigración', 'count' => $counts['inmigracion'], 'icon' => 'fa-flag-usa', 'color' => 'purple'],
            ['title' => 'Clientes Activos', 'count' => $counts['clientes'], 'icon' => 'fa-users', 'color' => 'green'],
            ['title' => 'Traducciones', 'count' => $counts['traducciones'], 'icon' => 'fa-language', 'color' => 'yellow']
        ];
        foreach ($kpis as $kpi) {
            echo <<<HTML
            <div class="card flex items-center p-5">
                <div class="bg-{$kpi['color']}-100 p-4 rounded-full">
                    <i class="fas {$kpi['icon']} fa-xl text-{$kpi['color']}-600"></i>
                </div>
                <div class="ml-4">
                    <p class="text-gray-500 font-medium">{$kpi['title']}</p>
                    <p class="text-3xl font-bold text-gray-800">{$kpi['count']}</p>
                </div>
            </div>
HTML;
        }
        ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Acciones Rápidas -->
        <div class="lg:col-span-1">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Acciones Rápidas</h2>
            <div class="card space-y-3">
                 <?php
                $quick_actions = [
                    ['view' => 'impuestos', 'action' => 'create', 'title' => 'Nuevo Caso de Impuestos', 'icon' => 'fa-plus', 'color' => 'blue'],
                    ['view' => 'inmigracion', 'action' => 'create', 'title' => 'Nuevo Caso de Inmigración', 'icon' => 'fa-plus', 'color' => 'purple'],
                    ['view' => 'clientes', 'action' => 'create', 'title' => 'Añadir Cliente', 'icon' => 'fa-user-plus', 'color' => 'green'],
                    ['view' => 'transacciones', 'action' => 'create', 'title' => 'Registrar Pago', 'icon' => 'fa-dollar-sign', 'color' => 'indigo'],
                ];
                 foreach ($quick_actions as $action) {
                     echo <<<HTML
                    <a href="#" data-spa-link data-view="{$action['view']}" data-action="{$action['action']}" class="block w-full text-left p-3 rounded-lg hover:bg-gray-100 transition-colors">
                        <i class="fas {$action['icon']} text-{$action['color']}-500 mr-3"></i>
                        <span class="font-medium text-gray-700">{$action['title']}</span>
                    </a>
HTML;
                 }
                ?>
            </div>
        </div>
        <!-- Actividad Reciente -->
        <div class="lg:col-span-2">
             <h2 class="text-xl font-semibold text-gray-800 mb-4">Actividad Reciente</h2>
             <div class="card">
                <div class="divide-y divide-gray-100">
                    <?php
                    if ($recent_query->have_posts()) :
                        while ($recent_query->have_posts()) : $recent_query->the_post();
                            $post_id = get_the_ID();
                            $post_type_obj = get_post_type_object(get_post_type());
                            $cliente_id = get_post_meta($post_id, '_cliente_id', true);
                            $cliente_nombre = $cliente_id ? get_the_title($cliente_id) : 'N/A';
                            $view_slug = Flowtax_Ajax_Handler::get_view_for_post_type(get_post_type());
                    ?>
                        <div class="p-3 hover:bg-gray-50 flex justify-between items-center">
                            <div>
                                <a href="#" data-spa-link data-view="<?php echo $view_slug; ?>" data-action="edit" data-id="<?php echo $post_id; ?>" class="font-semibold text-blue-600 hover:underline"><?php the_title(); ?></a>
                                <p class="text-sm text-gray-500">
                                    <?php echo $post_type_obj->labels->singular_name; ?> para <?php echo esc_html($cliente_nombre); ?>
                                </p>
                            </div>
                             <span class="text-sm text-gray-400"><?php echo get_the_date('d M Y'); ?></span>
                        </div>
                    <?php
                        endwhile;
                        wp_reset_postdata();
                    else :
                        echo '<p class="text-center text-gray-500 p-4">No hay actividad reciente.</p>';
                    endif;
                    ?>
                </div>
             </div>
        </div>
    </div>
</div>
