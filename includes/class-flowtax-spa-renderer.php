<?php
class Flowtax_SPARenderer {
    public static function init() {
        add_action('template_redirect', array(__CLASS__, 'render_spa_page'));
    }

    public static function render_spa_page() {
        if (!is_page('inicio')) return;
        if (!current_user_can('manage_options')) {
            wp_redirect(wp_login_url(get_permalink()));
            exit;
        }
        
        $current_user = wp_get_current_user();
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php wp_title('|', true, 'right'); ?> - FlowTax</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
            <?php wp_head(); ?>
            <style>
                #wpadminbar { display: none !important; }
                html { margin-top: 0 !important; }
                .fade-in { animation: fadeIn 0.3s ease-in-out; }
                @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
                .notification { transition: all 0.5s ease-in-out; }
                /* Custom scrollbar for a cleaner look */
                ::-webkit-scrollbar { width: 8px; height: 8px; }
                ::-webkit-scrollbar-track { background: #f1f5f9; }
                ::-webkit-scrollbar-thumb { background: #94a3b8; border-radius: 10px; }
                ::-webkit-scrollbar-thumb:hover { background: #64748b; }
            </style>
        </head>
        <body <?php body_class('bg-slate-100 antialiased'); ?>>
            <?php wp_body_open(); ?>
            <div class="flex h-screen bg-gray-100">
                <!-- Sidebar -->
                <aside id="spa-sidebar" class="w-64 bg-white shadow-lg flex-shrink-0 flex flex-col transition-all duration-300">
                    <div class="h-20 flex items-center justify-center border-b">
                         <h1 class="text-2xl font-bold text-blue-600">FlowTax</h1>
                    </div>
                    <nav class="flex-1 px-4 py-6 space-y-2">
                        <?php
                        $modules = [
                            ['view' => 'dashboard', 'title' => 'Dashboard', 'icon' => 'fa-tachometer-alt'],
                            ['view' => 'impuestos', 'title' => 'Impuestos', 'icon' => 'fa-calculator'],
                            ['view' => 'inmigracion', 'title' => 'Inmigración', 'icon' => 'fa-flag-usa'],
                            ['view' => 'payroll', 'title' => 'Payroll', 'icon' => 'fa-money-check-dollar'],
                            ['view' => 'traducciones', 'title' => 'Traducciones', 'icon' => 'fa-language'],
                            ['view' => 'transacciones', 'title' => 'Pagos y Cheques', 'icon' => 'fa-cash-register'],
                            ['view' => 'clientes', 'title' => 'Clientes', 'icon' => 'fa-users'],
                        ];
                        foreach ($modules as $module) {
                            echo <<<HTML
                            <a href="#" data-spa-link data-view="{$module['view']}" class="sidebar-link flex items-center px-4 py-3 text-gray-600 hover:bg-blue-50 hover:text-blue-600 rounded-lg transition-colors duration-200">
                                <i class="fas {$module['icon']} fa-fw mr-3"></i>
                                <span class="font-medium">{$module['title']}</span>
                            </a>
HTML;
                        }
                        ?>
                    </nav>
                     <div class="px-4 py-4 border-t">
                        <a href="<?php echo wp_logout_url(home_url()); ?>" class="sidebar-link flex items-center px-4 py-3 text-gray-600 hover:bg-red-50 hover:text-red-600 rounded-lg transition-colors duration-200">
                           <i class="fas fa-sign-out-alt fa-fw mr-3"></i>
                           <span class="font-medium">Salir del sistema</span>
                        </a>
                    </div>
                </aside>

                <!-- Main Content -->
                <div class="flex-1 flex flex-col overflow-hidden">
                    <!-- Header -->
                    <header class="h-20 bg-white shadow-md flex items-center justify-end px-8">
                         <div class="flex items-center">
                            <span class="text-gray-600 font-medium mr-4">Hola, <?php echo esc_html($current_user->display_name); ?></span>
                            <img class="h-10 w-10 rounded-full object-cover" src="<?php echo esc_url(get_avatar_url($current_user->ID)); ?>" alt="User Avatar">
                        </div>
                    </header>
                    <!-- App Root -->
                    <main class="flex-1 overflow-x-hidden overflow-y-auto bg-slate-100">
                         <div id="flowtax-app-root">
                            <!-- La SPA se renderizará aquí -->
                            <div class="flex justify-center items-center h-full">
                                <i class="fas fa-spinner fa-spin fa-3x text-blue-600"></i>
                            </div>
                        </div>
                    </main>
                </div>
            </div>

            <div id="notification-area" class="fixed top-5 right-5 z-[10000]"></div>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
        exit();
    }
}
