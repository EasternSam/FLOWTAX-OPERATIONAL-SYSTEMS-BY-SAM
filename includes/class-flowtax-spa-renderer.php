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
            <title><?php wp_title('|', true, 'right'); ?> - FlowTax OS</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
            <?php wp_head(); ?>
            <style>
                :root {
                    --font-sans: 'Inter', sans-serif;
                    --background: #f1f5f9;
                    --sidebar-bg: #ffffff;
                    --card-bg: #ffffff;
                    --text-primary: #1e293b;
                    --text-secondary: #64748b;
                    --border-color: #e2e8f0;
                    --primary-accent: #2563eb;
                    --primary-accent-hover: #1d4ed8;
                }
                #wpadminbar { display: none !important; }
                html { margin-top: 0 !important; font-size: 95%; }
                body { font-family: var(--font-sans); background-color: var(--background); color: var(--text-primary); -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
                .fade-in { animation: fadeIn 0.3s ease-in-out; }
                @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
                .notification { transition: all 0.5s ease-in-out; }
                /* Custom scrollbar */
                ::-webkit-scrollbar { width: 6px; height: 6px; }
                ::-webkit-scrollbar-track { background: #e2e8f0; }
                ::-webkit-scrollbar-thumb { background: #94a3b8; border-radius: 10px; }
                ::-webkit-scrollbar-thumb:hover { background: #64748b; }

                /* Sidebar responsive */
                @media (max-width: 768px) {
                    #spa-sidebar { width: 56px; }
                    #spa-sidebar .sidebar-link span, #spa-sidebar .sidebar-link-logout span { display: none; }
                    #spa-sidebar .sidebar-link i, #spa-sidebar .sidebar-link-logout i { margin-right: 0; }
                    #spa-sidebar h1 { font-size: 1.25rem; }
                }

                /* NOTA: Todos los estilos de componentes (botones, tarjetas, formularios) se han movido a spa-styles.css para mejor organizaci¨®n. */
            </style>
        </head>
        <body <?php body_class('antialiased'); ?>>
            <?php wp_body_open(); ?>
            <div class="flex h-screen bg-slate-50">
                <!-- Sidebar -->
                <aside id="spa-sidebar" class="w-56 bg-white border-r border-slate-200 flex-shrink-0 flex flex-col transition-all duration-300">
                    <div class="h-16 flex items-center justify-center border-b border-slate-200">
                         <h1 class="text-xl font-bold text-blue-600">FlowTax</h1>
                    </div>
                    <nav class="flex-1 px-3 py-4 space-y-1">
                        <?php
                        $modules = [
                            ['view' => 'dashboard', 'title' => 'Dashboard', 'icon' => 'fa-solid fa-chart-pie'],
                            ['view' => 'clientes', 'title' => 'Clientes', 'icon' => 'fa-solid fa-users'],
                            ['view' => 'impuestos', 'title' => 'Impuestos', 'icon' => 'fa-solid fa-calculator'],
                            ['view' => 'inmigracion', 'title' => 'Inmigraci¨®n', 'icon' => 'fa-solid fa-flag-usa'],
                            ['view' => 'payroll', 'title' => 'Payroll', 'icon' => 'fa-solid fa-money-check-dollar'],
                            ['view' => 'traducciones', 'title' => 'Traducciones', 'icon' => 'fa-solid fa-language'],
                            ['view' => 'transacciones', 'title' => 'Pagos y Cheques', 'icon' => 'fa-solid fa-cash-register'],
                        ];
                        foreach ($modules as $module) {
                            echo <<<HTML
                            <a href="#" data-spa-link data-view="{$module['view']}" class="sidebar-link flex items-center px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-blue-600 rounded-md transition-colors duration-200">
                                <i class="{$module['icon']} fa-fw w-6 text-center mr-3 text-slate-400"></i>
                                <span>{$module['title']}</span>
                            </a>
HTML;
                        }
                        ?>
                    </nav>
                     <div class="px-3 py-3 border-t border-slate-200">
                        <a href="<?php echo wp_logout_url(home_url()); ?>" class="sidebar-link-logout flex items-center px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100 rounded-md transition-colors duration-200">
                           <i class="fa-solid fa-arrow-right-from-bracket fa-fw w-6 text-center mr-3 text-slate-400"></i>
                           <span>Salir del sistema</span>
                        </a>
                    </div>
                </aside>

                <!-- Main Content -->
                <div class="flex-1 flex flex-col overflow-hidden">
                    <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-end px-6">
                         <div class="flex items-center">
                            <span class="text-slate-600 font-medium mr-3 text-sm">Hola, <?php echo esc_html($current_user->display_name); ?></span>
                            <img class="h-9 w-9 rounded-full object-cover ring-2 ring-offset-2 ring-slate-200" src="<?php echo esc_url(get_avatar_url($current_user->ID)); ?>" alt="User Avatar">
                        </div>
                    </header>
                    <main class="flex-1 overflow-x-hidden overflow-y-auto bg-slate-100">
                         <div id="flowtax-app-root">
                            <div class="flex justify-center items-center h-full">
                                <i class="fas fa-spinner fa-spin fa-2x text-slate-400"></i>
                            </div>
                        </div>
                    </main>
                </div>
            </div>

            <div id="notification-area" class="fixed top-5 right-5 z-[10000] w-80"></div>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
        exit();
    }
}

