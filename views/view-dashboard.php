<?php
// Obtener algunas estadísticas rápidas
$counts = [
    'impuestos' => wp_count_posts('impuestos')->publish,
    'inmigracion' => wp_count_posts('peticion_familiar')->publish + wp_count_posts('ciudadania')->publish + wp_count_posts('renovacion_residencia')->publish,
    'clientes' => wp_count_posts('cliente')->publish,
    'traducciones' => wp_count_posts('traduccion')->publish,
];
?>
<div class="p-8">
    <header class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Panel de Control</h1>
            <p class="text-gray-500">Resumen general del sistema.</p>
        </div>
        <a href="<?php echo wp_logout_url(home_url()); ?>" class="btn btn-secondary"><i class="fas fa-sign-out-alt mr-2"></i>Salir</a>
    </header>

    <!-- KPIs -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="card">
            <h2 class="text-gray-500 font-semibold">Casos de Impuestos</h2>
            <p class="text-4xl font-bold text-blue-600"><?php echo $counts['impuestos']; ?></p>
        </div>
        <div class="card">
            <h2 class="text-gray-500 font-semibold">Casos de Inmigración</h2>
            <p class="text-4xl font-bold text-purple-600"><?php echo $counts['inmigracion']; ?></p>
        </div>
        <div class="card">
            <h2 class="text-gray-500 font-semibold">Clientes Activos</h2>
            <p class="text-4xl font-bold text-green-600"><?php echo $counts['clientes']; ?></p>
        </div>
         <div class="card">
            <h2 class="text-gray-500 font-semibold">Traducciones</h2>
            <p class="text-4xl font-bold text-yellow-600"><?php echo $counts['traducciones']; ?></p>
        </div>
    </div>

    <!-- Módulos -->
    <h2 class="text-2xl font-semibold text-gray-800 mb-6">Módulos del Sistema</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php
        $modules = [
            ['view' => 'impuestos', 'title' => 'Impuestos', 'icon' => 'fa-calculator', 'color' => 'blue'],
            ['view' => 'inmigracion', 'title' => 'Inmigración', 'icon' => 'fa-flag-usa', 'color' => 'purple'],
            ['view' => 'payroll', 'title' => 'Payroll', 'icon' => 'fa-money-check-dollar', 'color' => 'green'],
            ['view' => 'traducciones', 'title' => 'Traducciones', 'icon' => 'fa-language', 'color' => 'yellow'],
            ['view' => 'transacciones', 'title' => 'Pagos y Cheques', 'icon' => 'fa-cash-register', 'color' => 'indigo'],
            ['view' => 'clientes', 'title' => 'Clientes', 'icon' => 'fa-users', 'color' => 'pink'],
        ];
        foreach ($modules as $module) {
            echo <<<HTML
            <a href="#" data-spa-link data-view="{$module['view']}" class="block card hover:shadow-xl hover:-translate-y-1">
                <div class="flex items-center">
                    <div class="bg-{$module['color']}-100 p-3 rounded-full">
                        <i class="fas {$module['icon']} fa-lg text-{$module['color']}-600"></i>
                    </div>
                    <h3 class="ml-4 text-xl font-bold text-gray-800">{$module['title']}</h3>
                </div>
            </a>
HTML;
        }
        ?>
    </div>
</div>
