<?php
global $action, $id;

if ($action === 'list') {
    $query = new WP_Query(['post_type' => 'traduccion', 'posts_per_page' => -1]);
    $casos = array_map(['Flowtax_Ajax_Handler', 'format_post_data'], $query->posts);
?>
    <div class="p-8">
        <header class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Gestión de Traducciones</h1>
            </div>
            <a href="#" data-spa-link data-view="traducciones" data-action="create" class="font-bold py-2 px-4 rounded-lg shadow-sm transition-all duration-300 flex items-center justify-center bg-blue-600 text-white hover:bg-blue-700 hover:shadow-md"><i class="fas fa-plus mr-2"></i>Nuevo Proyecto</a>
        </header>
        <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200/80">
             <div class="mb-4">
                 <input type="text" data-search-input data-post-type="traduccion" placeholder="Buscar por cliente, idioma..." class="w-full bg-white border border-slate-300 px-4 py-2.5 rounded-lg text-sm text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all duration-200 outline-none">
            </div>
            <table class="w-full text-left text-sm">
                <thead><tr><th class="p-3 bg-slate-50 font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">Proyecto (Cliente)</th><th class="p-3 bg-slate-50 font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">Idiomas</th><th class="p-3 bg-slate-50 font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">Estado</th><th class="p-3 bg-slate-50 font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">Fecha</th><th class="p-3 bg-slate-50 font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200"></th></tr></thead>
                <tbody id="data-table-body">
                    <?php if (empty($casos)) : ?>
                        <tr><td colspan="5" class="text-center p-4 text-gray-500 p-3 border-b border-slate-200 text-slate-600">No hay proyectos de traducción.</td></tr>
                    <?php else: foreach ($casos as $caso) : ?>
                    <tr class="hover:bg-slate-50/50">
                        <td class="p-3 border-b border-slate-200 text-slate-600">
                             <a href="#" data-spa-link data-view="traducciones" data-action="edit" data-id="<?php echo $caso['ID']; ?>" class="font-semibold text-blue-600 hover:underline"><?php echo esc_html($caso['title']); ?></a>
                            <p class="text-sm text-gray-500"><?php echo esc_html($caso['cliente_nombre']); ?></p>
                        </td>
                        <td class="p-3 border-b border-slate-200 text-slate-600"><?php echo esc_html($caso['idioma_origen']); ?> → <?php echo esc_html($caso['idioma_destino']); ?></td>
                        <td class="p-3 border-b border-slate-200 text-slate-600"><span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo esc_attr($caso['estado_color']); ?>"><?php echo esc_html($caso['estado']); ?></span></td>
                        <td class="p-3 border-b border-slate-200 text-slate-600"><?php echo esc_html($caso['fecha']); ?></td>
                        <td class="text-right p-3 border-b border-slate-200 text-slate-600">
                             <a href="#" data-spa-link data-view="traducciones" data-action="edit" data-id="<?php echo $caso['ID']; ?>" class="text-gray-500 hover:text-blue-600 mr-2"><i class="fas fa-edit"></i></a>
                            <button data-delete-id="<?php echo $caso['ID']; ?>" class="text-gray-500 hover:text-red-600"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php
} else {
    $post_id = $id;
    $is_edit = $post_id > 0;
    $caso = $is_edit ? get_post($post_id) : null;
    $meta = $is_edit ? get_post_meta($post_id) : [];

    $get_meta = function($key) use ($meta) {
        return isset($meta["_{$key}"]) ? esc_attr($meta["_{$key}"][0]) : '';
    };

    $clientes_query = new WP_Query(['post_type' => 'cliente', 'posts_per_page' => -1]);
    $clientes_options = array_map(function($post) {
        return ['id' => $post->ID, 'title' => $post->post_title];
    }, $clientes_query->posts);

    $estados_terms = get_terms(['taxonomy' => 'estado_caso', 'hide_empty' => false]);
?>
     <div class="p-8">
        <header class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800"><?php echo $is_edit ? 'Editar Proyecto de Traducción' : 'Nuevo Proyecto de Traducción'; ?></h1>
        </header>
        <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200/80 max-w-4xl mx-auto">
             <form data-spa-form>
                <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                <input type="hidden" name="post_type" value="traduccion">
                <input type="hidden" name="post_title" value="<?php echo $caso ? esc_attr($caso->post_title) : 'Nuevo Proyecto de Traducción'; ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div>
                            <label class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase" for="cliente_id_trad">Cliente</label>
                            <select name="cliente_id" id="cliente_id_trad" class="w-full" required>
                                <option value="">Seleccione un cliente</option>
                                <?php foreach($clientes_options as $cliente): ?>
                                    <option value="<?php echo $cliente['id']; ?>" <?php selected($get_meta('cliente_id'), $cliente['id']); ?>><?php echo esc_html($cliente['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Idioma de Origen</label>
                            <input type="text" name="idioma_origen" value="<?php echo $get_meta('idioma_origen'); ?>" class="w-full" placeholder="Ej: Español">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Idioma de Destino</label>
                            <input type="text" name="idioma_destino" value="<?php echo $get_meta('idioma_destino'); ?>" class="w-full" placeholder="Ej: Inglés">
                        </div>
                         <div>
                           <label class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Estado del Caso</label>
                           <select name="estado_caso" class="w-full">
                               <?php
                               $current_term_id = 0;
                               if ($caso) {
                                   $current_terms = wp_get_post_terms($post_id, 'estado_caso', ['fields' => 'ids']);
                                   if (!is_wp_error($current_terms) && !empty($current_terms)) {
                                       $current_term_id = $current_terms[0];
                                   }
                               }
                               foreach($estados_terms as $term): ?>
                                    <option value="<?php echo $term->term_id; ?>" <?php selected($current_term_id, $term->term_id); ?>><?php echo esc_html($term->name); ?></option>
                               <?php endforeach; ?>
                           </select>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Número de Páginas</label>
                            <input type="number" name="num_paginas" value="<?php echo $get_meta('num_paginas'); ?>" class="w-full">
                        </div>
                         <div>
                            <label class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Costo Total ($)</label>
                            <input type="number" step="0.01" name="costo_total" value="<?php echo $get_meta('costo_total'); ?>" class="w-full">
                        </div>
                         <div>
                            <label class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Fecha de Entrega</label>
                            <input type="date" name="fecha_entrega" value="<?php echo $get_meta('fecha_entrega'); ?>" class="w-full">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Estado del Pago</label>
                            <select name="estado_pago" class="w-full">
                                <option value="Pendiente" <?php selected($get_meta('estado_pago'), 'Pendiente'); ?>>Pendiente</option>
                                <option value="Pagado" <?php selected($get_meta('estado_pago'), 'Pagado'); ?>>Pagado</option>
                            </select>
                        </div>
                    </div>
                </div>
                 <div class="mt-6 flex justify-end space-x-3">
                    <a href="#" data-spa-link data-view="traducciones" class="font-bold py-2 px-4 rounded-lg shadow-sm transition-all duration-300 flex items-center justify-center bg-slate-200 text-slate-800 hover:bg-slate-300">Cancelar</a>
                    <button type="submit" class="font-bold py-2 px-4 rounded-lg shadow-sm transition-all duration-300 flex items-center justify-center bg-blue-600 text-white hover:bg-blue-700 hover:shadow-md">Guardar Proyecto</button>
                </div>
            </form>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form[data-spa-form]');
        if (!form) return;

        const clienteSelect = form.querySelector('[name="cliente_id"]');
        const titleInput = form.querySelector('[name="post_title"]');
        const origenInput = form.querySelector('[name="idioma_origen"]');

        function updateTitle() {
            if (!clienteSelect || !titleInput) return;
            const clienteText = clienteSelect.options[clienteSelect.selectedIndex]?.text || 'Cliente No Seleccionado';
            const origenLang = origenInput.value ? `(${origenInput.value})` : '';

            if (clienteSelect.value) {
                titleInput.value = `Traducción ${origenLang} para ${clienteText}`;
            } else {
                titleInput.value = 'Nuevo Proyecto de Traducción';
            }
        }

        clienteSelect.addEventListener('change', updateTitle);
        origenInput.addEventListener('input', updateTitle);
        
        updateTitle();
    });
    </script>
<?php
}
