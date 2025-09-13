<?php
$query = new WP_Query([
    'post_type' => 'flowtax_log',
    'posts_per_page' => 50, // Paginate later if needed
    'orderby' => 'date',
    'order' => 'DESC'
]);
$logs = $query->posts;
?>
<div class="p-4 sm:p-6">
    <header class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Registro de Actividad</h1>
        <p class="text-slate-500 mt-1 text-sm">Un registro completo de todas las acciones realizadas en el sistema.</p>
    </header>
    <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200/80">
        <div class="space-y-1">
            <?php if (empty($logs)) : ?>
                <p class="text-center p-8 text-slate-500">No hay actividad registrada todavía.</p>
            <?php else: foreach ($logs as $log_post) :
                $user_name = get_post_meta($log_post->ID, '_user_name', true);
                $object_id = get_post_meta($log_post->ID, '_object_id', true);
                $object_type = get_post_meta($log_post->ID, '_object_type', true);
                $raw_title = $log_post->post_title;
                
                $final_html = esc_html($raw_title);

                if ($object_id && $object_type) {
                    $object_post = get_post($object_id);
                    if ($object_post) {
                        $view_slug = Flowtax_Ajax_Handler::get_view_for_post_type($object_type);
                        $action = ($object_type === 'cliente') ? 'perfil' : 'manage';
                        
                        $link = sprintf(
                            '<a href="#" data-spa-link data-view="%s" data-action="%s" data-id="%d" class="font-semibold text-blue-600 hover:underline">%s</a>',
                            esc_attr($view_slug),
                            esc_attr($action),
                            esc_attr($object_id),
                            esc_html($object_post->post_title)
                        );

                        // Replace the object title in the raw string with the link
                        $final_html = str_replace(
                            '"' . esc_html($object_post->post_title) . '"',
                            $link,
                            esc_html($raw_title)
                        );
                    }
                }
            ?>
            <div class="log-item flex items-start space-x-4 p-3 border-b border-slate-100 last:border-b-0">
                <div class="flex-shrink-0 pt-1">
                    <i class="fas fa-history text-slate-400"></i>
                </div>
                <div class="flex-grow">
                    <p class="text-sm text-slate-700"><?php echo $final_html; ?></p>
                    <p class="text-xs text-slate-400 mt-1">
                        Por <strong><?php echo esc_html($user_name); ?></strong> el <?php echo get_the_date('d M Y, h:i a', $log_post); ?>
                    </p>
                </div>
            </div>
            <?php endforeach; wp_reset_postdata(); endif; ?>
        </div>
    </div>
</div>
