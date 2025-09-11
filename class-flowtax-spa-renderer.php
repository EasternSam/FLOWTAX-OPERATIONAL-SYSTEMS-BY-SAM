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
                .fade-in { animation: fadeIn 0.5s ease-in-out; }
                @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
                .notification { transition: all 0.5s ease-in-out; }
            </style>
        </head>
        <body <?php body_class('bg-gray-50'); ?>>
            <?php wp_body_open(); ?>
            <div id="flowtax-app-root">
                <!-- La SPA se renderizará aquí -->
                <div class="flex justify-center items-center min-h-screen">
                    <i class="fas fa-spinner fa-spin fa-3x text-blue-600"></i>
                </div>
            </div>
            <div id="notification-area" class="fixed top-5 right-5 z-50"></div>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
        exit();
    }
}
