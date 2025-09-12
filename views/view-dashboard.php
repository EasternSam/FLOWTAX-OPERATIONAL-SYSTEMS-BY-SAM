<?php
// Obtener usuario actual
$current_user = wp_get_current_user();

// Obtener estadísticas rápidas
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

// Obtener casos pendientes del cliente
$pending_term = get_term_by('name', 'Pendiente de Cliente', 'estado_caso');
$pending_cases = [];
if ($pending_term) {
    $pending_query = new WP_Query([
        'post_type' => ['impuestos', 'peticion_familiar', 'ciudadania', 'renovacion_residencia', 'traduccion'],
        'posts_per_page' => 3,
        'tax_query' => [
            [
                'taxonomy' => 'estado_caso',
                'field'    => 'term_id',
                'terms'    => $pending_term->term_id,
            ],
        ],
    ]);
    if ($pending_query->have_posts()) {
        $pending_cases = $pending_query->posts;
    }
}

// Formatear la fecha en español
setlocale(LC_TIME, 'es_ES.UTF-8', 'Spanish');
$today_date = strftime('%A, %e de %B de %Y');

// Saludo dinámico según la hora del día (basado en la zona horaria de WordPress)
$current_hour = (int) current_time('H');
$saludo = 'Buen día';
if ($current_hour >= 12 && $current_hour < 19) {
    $saludo = 'Buenas tardes';
} elseif ($current_hour >= 19 || $current_hour < 5) { // Considerar noche desde las 7pm hasta las 5am
    $saludo = 'Buenas noches';
}

?>
<div class="p-4 sm:p-6">
    <!-- Header -->
    <header class="mb-8">
        <h1 class="text-2xl font-bold text-slate-800"><?php echo esc_html($saludo); ?>, <?php echo esc_html($current_user->display_name); ?>!</h1>
        <p class="text-slate-500 mt-1 text-sm">Hoy es <?php echo ucfirst($today_date); ?>. Aquí tienes tu resumen diario.</p>
    </header>

    <!-- KPIs -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <?php
        $kpis = [
            ['title' => 'Clientes Activos', 'count' => $counts['clientes'], 'icon' => 'fa-solid fa-users', 'color' => 'blue', 'view' => 'clientes'],
            ['title' => 'Casos de Impuestos', 'count' => $counts['impuestos'], 'icon' => 'fa-solid fa-calculator', 'color' => 'indigo', 'view' => 'impuestos'],
            ['title' => 'Casos de Inmigración', 'count' => $counts['inmigracion'], 'icon' => 'fa-solid fa-flag-usa', 'color' => 'sky', 'view' => 'inmigracion'],
            ['title' => 'Traducciones', 'count' => $counts['traducciones'], 'icon' => 'fa-solid fa-language', 'color' => 'amber', 'view' => 'traducciones']
        ];
        foreach ($kpis as $kpi) {
            $colors = [
                'blue' => ['border' => 'hover:border-blue-400', 'text' => 'text-blue-600', 'bg' => 'bg-blue-100'],
                'indigo' => ['border' => 'hover:border-indigo-400', 'text' => 'text-indigo-600', 'bg' => 'bg-indigo-100'],
                'sky' => ['border' => 'hover:border-sky-400', 'text' => 'text-sky-600', 'bg' => 'bg-sky-100'],
                'amber' => ['border' => 'hover:border-amber-400', 'text' => 'text-amber-600', 'bg' => 'bg-amber-100'],
            ];
            $color_theme = $colors[$kpi['color']];
            echo <<<HTML
            <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm flex items-start justify-between {$color_theme['border']} transition-colors duration-300">
                <div>
                    <p class="text-sm font-semibold text-slate-500">{$kpi['title']}</p>
                    <p class="text-3xl font-bold text-slate-800 mt-1">{$kpi['count']}</p>
                    <a href="#" data-spa-link data-view="{$kpi['view']}" class="text-xs font-semibold {$color_theme['text']} hover:underline mt-2 inline-block">Ver todos &rarr;</a>
                </div>
                <div class="{$color_theme['bg']} p-3 rounded-lg">
                    <i class="{$kpi['icon']} fa-lg {$color_theme['text']}"></i>
                </div>
            </div>
HTML;
        }
        ?>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Actividad Reciente -->
        <div class="lg:col-span-2">
             <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200/80 h-full">
                <h2 class="text-lg font-semibold text-slate-800 mb-4 border-b pb-2">Actividad Reciente</h2>
                <div class="space-y-1">
                    <?php
                    if ($recent_query->have_posts()) :
                        while ($recent_query->have_posts()) : $recent_query->the_post();
                            $caso = Flowtax_Ajax_Handler::format_post_data(get_post());
                    ?>
                        <a href="#" data-spa-link data-view="<?php echo $caso['view_slug']; ?>" data-action="manage" data-id="<?php echo $caso['ID']; ?>" class="block p-3 rounded-lg hover:bg-slate-50 transition-colors duration-200">
                            <div class="flex justify-between items-center">
                                <div class="flex items-center min-w-0">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3 bg-slate-100 flex-shrink-0">
                                        <i class="fa-solid <?php 
                                            switch($caso['post_type']) {
                                                case 'impuestos': echo 'fa-calculator'; break;
                                                case 'traduccion': echo 'fa-language'; break;
                                                default: echo 'fa-flag-usa';
                                            }
                                        ?> text-slate-500"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-slate-700 text-sm truncate"><?php echo esc_html($caso['title']); ?></p>
                                        <p class="text-xs text-slate-500 truncate">
                                            <?php echo esc_html($caso['singular_name']); ?> para <strong><?php echo esc_html($caso['cliente_nombre']); ?></strong>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right flex-shrink-0 ml-4 hidden sm:block">
                                    <span class="text-xs text-slate-400 font-medium"><?php echo get_the_date('d M Y'); ?></span>
                                    <span class="block mt-1 px-2 py-0.5 text-xs font-semibold rounded-full <?php echo esc_attr($caso['estado_color']); ?>"><?php echo esc_html($caso['estado']); ?></span>
                                </div>
                            </div>
                        </a>
                    <?php
                        endwhile;
                        wp_reset_postdata();
                    else :
                        echo '<p class="text-center text-slate-500 py-8">No hay actividad reciente.</p>';
                    endif;
                    ?>
                </div>
             </div>
        </div>

        <!-- Acciones y Pendientes -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200/80">
                <h2 class="text-lg font-semibold text-slate-800 mb-4 border-b pb-2">Acciones Rápidas</h2>
                <div class="space-y-2">
                 <?php
                $quick_actions = [
                    ['view' => 'clientes', 'action' => 'create', 'title' => 'Añadir Cliente', 'icon' => 'fa-solid fa-user-plus', 'color' => 'green'],
                    ['view' => 'impuestos', 'action' => 'create', 'title' => 'Nuevo Caso de Impuestos', 'icon' => 'fa-solid fa-plus', 'color' => 'indigo'],
                    ['view' => 'inmigracion', 'action' => 'create', 'title' => 'Nuevo Caso Inmigración', 'icon' => 'fa-solid fa-plus', 'color' => 'sky'],
                    ['view' => 'transacciones', 'action' => 'create', 'title' => 'Registrar Pago', 'icon' => 'fa-solid fa-dollar-sign', 'color' => 'emerald'],
                ];
                 foreach ($quick_actions as $action) {
                     $icon_color = 'text-'.$action['color'].'-500';
                     echo <<<HTML
                    <a href="#" data-spa-link data-view="{$action['view']}" data-action="{$action['action']}" class="flex items-center w-full text-left p-3 rounded-lg hover:bg-slate-100 transition-colors text-sm font-medium text-slate-700">
                        <i class="{$action['icon']} {$icon_color} mr-4 fa-fw"></i>
                        <span>{$action['title']}</span>
                        <i class="fa-solid fa-chevron-right text-slate-400 ml-auto text-xs"></i>
                    </a>
HTML;
                 }
                ?>
                </div>
            </div>

            <?php if (!empty($pending_cases)): ?>
            <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200/80">
                <h2 class="text-lg font-semibold text-slate-800 mb-4 border-b pb-2">Pendiente de Cliente</h2>
                <div class="space-y-3">
                <?php foreach($pending_cases as $p_post): 
                    $p_caso = Flowtax_Ajax_Handler::format_post_data($p_post);
                ?>
                    <a href="#" data-spa-link data-view="<?php echo $p_caso['view_slug']; ?>" data-action="manage" data-id="<?php echo $p_caso['ID']; ?>" class="block text-sm p-2 rounded-md hover:bg-slate-50">
                        <p class="font-semibold text-slate-600 truncate"><?php echo esc_html($p_caso['title']); ?></p>
                        <p class="text-xs text-slate-400"><?php echo esc_html($p_caso['cliente_nombre']); ?></p>
                    </a>
                <?php endforeach; wp_reset_postdata(); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

