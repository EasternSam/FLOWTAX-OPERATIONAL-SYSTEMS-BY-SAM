<?php
$action = $action ?? 'list';
$id = $id ?? 0;

if ($action === 'list') {
    $query = new WP_Query(['post_type' => 'traduccion', 'posts_per_page' => -1]);
    $casos = array_map(['Flowtax_Ajax_Handler', 'format_post_data'], $query->posts);
?>
    <div class="p-4 sm:p-6">
        <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Gestión de Traducciones</h1>
            </div>
            <a href="#" data-spa-link data-view="traducciones" data-action="create" class="font-bold py-2 px-4 rounded-lg shadow-sm transition-all duration-300 flex items-center justify-center bg-blue-600 text-white hover:bg-blue-700 hover:shadow-md w-full sm:w-auto"><i class="fas fa-plus mr-2"></i>Nuevo Proyecto</a>
        </header>
        <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200/80">
             <div class="mb-4">
                 <input type="text" data-search-input data-post-type="traduccion" placeholder="Buscar por cliente, idioma..." class="w-full bg-white border border-slate-300 px-4 py-2.5 rounded-lg text-sm text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all duration-200 outline-none">
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm responsive-table">
                    <thead>
                        <tr>
                            <th class="p-3 bg-slate-50 font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">Proyecto (Cliente)</th>
                            <th class="p-3 bg-slate-50 font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">Idiomas</th>
                            <th class="p-3 bg-slate-50 font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">Estado</th>
                            <th class="p-3 bg-slate-50 font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">Fecha</th>
                            <th class="text-right p-3 bg-slate-50 font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="data-table-body">
                        <?php if (empty($casos)) : ?>
                            <tr><td colspan="5" class="text-center p-4 text-gray-500">No hay proyectos de traducción.</td></tr>
                        <?php else: foreach ($casos as $caso) : ?>
                        <tr>
                            <td data-label="Proyecto">
                                 <a href="#" data-spa-link data-view="traducciones" data-action="manage" data-id="<?php echo $caso['ID']; ?>" class="font-semibold text-blue-600 hover:underline"><?php echo esc_html($caso['title']); ?></a>
                                <p class="text-sm text-gray-500"><?php echo esc_html($caso['cliente_nombre']); ?></p>
                            </td>
                            <td data-label="Idiomas"><?php echo esc_html($caso['idioma_origen']); ?> → <?php echo esc_html($caso['idioma_destino']); ?></td>
                            <td data-label="Estado"><span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo esc_attr($caso['estado_color']); ?>"><?php echo esc_html($caso['estado']); ?></span></td>
                            <td data-label="Fecha"><?php echo esc_html($caso['fecha']); ?></td>
                            <td data-label="Acciones">
                                <div class="flex justify-end items-center space-x-2">
                                    <a href="#" data-spa-link data-view="traducciones" data-action="manage" data-id="<?php echo $caso['ID']; ?>" class="h-8 w-8 rounded-md text-slate-500 hover:bg-slate-200 hover:text-blue-600 flex items-center justify-center transition-colors" title="Gestionar"><i class="fas fa-tasks"></i></a>
                                    <a href="#" data-spa-link data-view="traducciones" data-action="edit" data-id="<?php echo $caso['ID']; ?>" class="h-8 w-8 rounded-md text-slate-500 hover:bg-slate-200 hover:text-blue-600 flex items-center justify-center transition-colors" title="Editar"><i class="fas fa-edit"></i></a>
                                    <button data-delete-id="<?php echo $caso['ID']; ?>" class="h-8 w-8 rounded-md text-slate-500 hover:bg-red-100 hover:text-red-600 flex items-center justify-center transition-colors"><i class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
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
     <div class="p-4 sm:p-6">
        <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
            <h1 class="text-2xl font-bold text-slate-800"><?php echo $is_edit ? 'Editar Proyecto de Traducción' : 'Nuevo Proyecto de Traducción'; ?></h1>
             <a href="#" data-spa-link data-view="traducciones" class="font-bold py-2 px-4 rounded-lg shadow-sm transition-all duration-300 flex items-center justify-center bg-slate-200 text-slate-800 hover:bg-slate-300 w-full sm:w-auto">Cancelar</a>
        </header>
        <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200/80 max-w-4xl mx-auto">
             <form data-spa-form>
                <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                <input type="hidden" name="post_type" value="traduccion">
                <input type="hidden" name="post_title" value="<?php echo $caso ? esc_attr($caso->post_title) : 'Nuevo Proyecto de Traducción'; ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
                     <div>
                        <label class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Idioma de Origen</label>
                        <input type="text" name="idioma_origen" value="<?php echo $get_meta('idioma_origen'); ?>" class="w-full" placeholder="Ej: Español">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Idioma de Destino</label>
                        <input type="text" name="idioma_destino" value="<?php echo $get_meta('idioma_destino'); ?>" class="w-full" placeholder="Ej: Inglés">
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Notas Internas</label>
                        <textarea name="notas_preparador" class="w-full min-h-[100px]" placeholder="Añade notas sobre el caso..."><?php echo esc_textarea($get_meta('notas_preparador')); ?></textarea>
                     </div>
                </div>
                 <div class="mt-6 flex justify-end">
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

