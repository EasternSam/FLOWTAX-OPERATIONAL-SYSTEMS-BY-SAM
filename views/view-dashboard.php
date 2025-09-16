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
        'posts_per_page' => 5,
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

// Obtener deudas pendientes y vencidas
$deudas_pendientes_query = new WP_Query([
    'post_type' => 'deuda',
    'posts_per_page' => -1,
    'tax_query' => [
        [
            'taxonomy' => 'estado_deuda',
            'field'    => 'slug',
            'terms'    => ['pendiente', 'vencido', 'abono'],
        ],
    ],
]);

$total_deudas_pendientes = $deudas_pendientes_query->found_posts;
$total_pendiente_usd = 0;
$total_pendiente_dop = 0;

if ($deudas_pendientes_query->have_posts()) {
    while ($deudas_pendientes_query->have_posts()) {
        $deudas_pendientes_query->the_post();
        $monto_deuda = (float) get_post_meta(get_the_ID(), '_monto_deuda', true);
        $monto_abonado = (float) get_post_meta(get_the_ID(), '_monto_abonado', true);
        $restante = $monto_deuda - $monto_abonado;
        $divisa = get_post_meta(get_the_ID(), '_divisa', true) ?: 'USD';
        
        if ($divisa === 'USD') {
            $total_pendiente_usd += $restante;
        } else {
            $total_pendiente_dop += $restante;
        }
    }
}
wp_reset_postdata();

// Saludo dinámico según la hora del día (basado en la zona horaria de WordPress)
$current_hour = (int) current_time('H');
$saludo = 'Buen día';
if ($current_hour >= 12 && $current_hour < 19) {
    $saludo = 'Buenas tardes';
} elseif ($current_hour >= 19 || $current_hour < 5) {
    $saludo = 'Buenas noches';
}

?>
<div class="p-4 sm:p-6 lg:p-8 animate-fade-in-up">
    <!-- Header -->
    <header class="mb-8">
        <h1 class="text-3xl font-bold text-slate-800"><?php echo esc_html($saludo); ?>, <?php echo esc_html($current_user->display_name); ?>!</h1>
        <p class="text-slate-500 mt-1">Aquí tienes un resumen de la actividad de tu negocio.</p>
    </header>

    <!-- KPIs -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
        <?php
        $kpis = [
            ['title' => 'Clientes Activos', 'count' => $counts['clientes'], 'icon' => 'fa-solid fa-users', 'color' => 'blue', 'view' => 'clientes'],
            ['title' => 'Casos de Impuestos', 'count' => $counts['impuestos'], 'icon' => 'fa-solid fa-calculator', 'color' => 'violet', 'view' => 'impuestos'],
            ['title' => 'Casos de Inmigración', 'count' => $counts['inmigracion'], 'icon' => 'fa-solid fa-flag-usa', 'color' => 'green', 'view' => 'inmigracion'],
            ['title' => 'Traducciones', 'count' => $counts['traducciones'], 'icon' => 'fa-solid fa-language', 'color' => 'amber', 'view' => 'traducciones']
        ];
        foreach ($kpis as $kpi) {
            $colors = [
                'blue' => ['from' => 'from-blue-500', 'to' => 'to-cyan-400', 'shadow' => 'shadow-blue-500/30'],
                'violet' => ['from' => 'from-violet-500', 'to' => 'to-purple-400', 'shadow' => 'shadow-violet-500/30'],
                'green' => ['from' => 'from-green-500', 'to' => 'to-emerald-400', 'shadow' => 'shadow-green-500/30'],
                'amber' => ['from' => 'from-amber-500', 'to' => 'to-orange-400', 'shadow' => 'shadow-amber-500/30'],
            ];
            $color_theme = $colors[$kpi['color']];
            echo <<<HTML
            <a href="#" data-spa-link data-view="{$kpi['view']}" class="block bg-gradient-to-br {$color_theme['from']} {$color_theme['to']} p-5 rounded-2xl shadow-lg {$color_theme['shadow']} text-white hover:-translate-y-1.5 transition-all duration-300 relative overflow-hidden group">
                <div class="absolute -right-3 -bottom-3 text-white/20 group-hover:scale-110 transition-transform duration-300">
                    <i class="{$kpi['icon']} fa-4x"></i>
                </div>
                <div class="relative z-10">
                    <p class="text-3xl font-bold">{$kpi['count']}</p>
                    <p class="text-xs sm:text-sm font-semibold mt-1">{$kpi['title']}</p>
                </div>
            </a>
HTML;
        }
        ?>
    </div>

    <!-- Cuentas por Cobrar Resumen -->
    <div class="mb-8">
        <a href="#" data-spa-link data-view="cuentas-por-cobrar" class="block bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-xl hover:border-slate-300 hover:-translate-y-1 transition-all duration-300">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center">
                <div class="mb-4 sm:mb-0">
                    <div class="flex items-center">
                        <span class="bg-rose-100 text-rose-600 p-3.5 rounded-lg mr-4">
                            <i class="fa-solid fa-file-invoice-dollar fa-lg"></i>
                        </span>
                        <div>
                            <h2 class="text-lg font-semibold text-slate-800">Cuentas por Cobrar</h2>
                            <p class="text-sm text-slate-500"><?php echo $total_deudas_pendientes; ?> deudas pendientes de pago</p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-6 text-right w-full sm:w-auto">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">Pendiente DOP</p>
                        <p class="text-xl font-bold text-rose-600">RD$ <?php echo number_format($total_pendiente_dop, 2); ?></p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-500">Pendiente USD</p>
                        <p class="text-xl font-bold text-rose-600">USD$ <?php echo number_format($total_pendiente_usd, 2); ?></p>
                    </div>
                     <i class="fa-solid fa-chevron-right text-slate-400 hidden sm:block"></i>
                </div>
            </div>
        </a>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Actividad Reciente -->
        <div class="lg:col-span-2">
             <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200/80 h-full">
                <h2 class="text-lg font-semibold text-slate-800 mb-4">Actividad Reciente</h2>
                <div class="space-y-1">
                    <?php
                    if ($recent_query->have_posts()) :
                        while ($recent_query->have_posts()) : $recent_query->the_post();
                            $caso = Flowtax_Ajax_Handler::format_post_data(get_post());
                    ?>
                        <a href="#" data-spa-link data-view="<?php echo $caso['view_slug']; ?>" data-action="manage" data-id="<?php echo $caso['ID']; ?>" class="block p-3 rounded-lg hover:bg-slate-50 transition-colors duration-200">
                            <div class="flex justify-between items-center">
                                <div class="flex items-center min-w-0">
                                    <div class="w-9 h-9 rounded-full flex items-center justify-center mr-3 bg-slate-100 flex-shrink-0">
                                        <i class="fa-solid <?php 
                                            switch($caso['post_type']) {
                                                case 'impuestos': echo 'fa-calculator text-violet-500'; break;
                                                case 'traduccion': echo 'fa-language text-amber-500'; break;
                                                default: echo 'fa-flag-usa text-green-500';
                                            }
                                        ?>"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-slate-700 text-sm truncate" title="<?php echo esc_attr($caso['title']); ?>"><?php echo esc_html($caso['title']); ?></p>
                                        <p class="text-xs text-slate-500 truncate">
                                            <?php echo esc_html($caso['singular_name']); ?> para <strong><?php echo esc_html($caso['cliente_nombre']); ?></strong>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right flex-shrink-0 ml-4 hidden sm:block">
                                    <span class="block px-2.5 py-0.5 text-xs font-semibold rounded-full <?php echo esc_attr($caso['estado_color']); ?>"><?php echo esc_html($caso['estado']); ?></span>
                                </div>
                            </div>
                        </a>
                    <?php
                        endwhile;
                        wp_reset_postdata();
                    else : ?>
                        <div class="text-center py-12">
                             <div class="text-5xl text-slate-300 mb-4"><i class="fas fa-history"></i></div>
                            <h3 class="text-lg font-semibold text-slate-700">No hay actividad reciente</h3>
                            <p class="text-slate-500 text-sm mt-1">Las últimas acciones aparecerán aquí.</p>
                        </div>
                    <?php endif; ?>
                </div>
             </div>
        </div>

        <!-- Casos Pendientes -->
        <div class="lg:col-span-1">
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200/80 h-full">
                <h2 class="text-lg font-semibold text-slate-800 mb-4">Pendiente de Cliente</h2>
                <div class="space-y-3">
                <?php if (!empty($pending_cases)):
                    foreach($pending_cases as $p_post): 
                        $p_caso = Flowtax_Ajax_Handler::format_post_data($p_post);
                    ?>
                        <a href="#" data-spa-link data-view="<?php echo $p_caso['view_slug']; ?>" data-action="manage" data-id="<?php echo $p_caso['ID']; ?>" class="block text-sm p-3 rounded-lg hover:bg-slate-100 border border-transparent hover:border-slate-200 transition-all duration-200">
                            <p class="font-semibold text-slate-700 truncate"><?php echo esc_html($p_caso['title']); ?></p>
                            <p class="text-xs text-slate-500 mt-0.5"><?php echo esc_html($p_caso['cliente_nombre']); ?></p>
                        </a>
                    <?php endforeach; wp_reset_postdata(); 
                    else: ?>
                        <div class="text-center py-12">
                            <div class="text-5xl text-emerald-500 mb-4"><i class="fas fa-check-circle"></i></div>
                            <h3 class="text-lg font-semibold text-slate-700">¡Todo al día!</h3>
                            <p class="text-slate-500 text-sm mt-1">No hay casos esperando por clientes.</p>
                        </div>
                <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

