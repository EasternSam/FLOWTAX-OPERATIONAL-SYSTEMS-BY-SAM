<?php
// El ID y la acción son pasados desde el AJAX handler
$action = $action ?? 'list';
$id = $id ?? 0;

if ($action === 'list') {
    $query = new WP_Query(['post_type' => 'impuestos', 'posts_per_page' => -1]);
    $casos = array_map(['Flowtax_Ajax_Handler', 'format_post_data'], $query->posts);
?>
    <div class="p-4 sm:p-6">
        <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Gestión de Impuestos</h1>
                <p class="text-slate-500 mt-1 text-sm">Casos de declaraciones de impuestos.</p>
            </div>
            <a href="#" data-spa-link data-view="impuestos" data-action="create" class="font-bold py-2 px-4 rounded-lg shadow-sm transition-all duration-300 flex items-center justify-center bg-blue-600 text-white hover:bg-blue-700 hover:shadow-md w-full sm:w-auto"><i class="fas fa-plus mr-2"></i>Nueva Declaración</a>
        </header>
        <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200/80">
            <div class="mb-4">
                 <div class="relative">
                    <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                    <input type="text" data-search-input data-post-type="impuestos" placeholder="Buscar por cliente, año fiscal..." class="pl-10 w-full bg-white border border-slate-300 px-4 py-2.5 rounded-lg text-sm text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all duration-200 outline-none">
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm responsive-table">
                    <thead>
                        <tr>
                            <th class="p-3 bg-slate-50 font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">Caso (Cliente)</th>
                            <th class="p-3 bg-slate-50 font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">Año Fiscal</th>
                            <th class="p-3 bg-slate-50 font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">Estado</th>
                            <th class="p-3 bg-slate-50 font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">Fecha</th>
                            <th class="text-right p-3 bg-slate-50 font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="data-table-body">
                        <?php if (empty($casos)) : ?>
                            <tr><td colspan="5" class="text-center p-8 text-slate-500">No se encontraron casos de impuestos.</td></tr>
                        <?php else: foreach ($casos as $caso) : ?>
                        <tr>
                            <td data-label="Caso">
                                <a href="#" data-spa-link data-view="impuestos" data-action="manage" data-id="<?php echo $caso['ID']; ?>" class="font-semibold text-blue-600 hover:underline"><?php echo esc_html($caso['title']); ?></a>
                                <p class="text-sm text-slate-500"><?php echo esc_html($caso['cliente_nombre']); ?></p>
                            </td>
                            <td data-label="Año Fiscal"><?php echo esc_html(get_post_meta($caso['ID'], '_ano_fiscal', true)); ?></td>
                            <td data-label="Estado"><span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo esc_attr($caso['estado_color']); ?>"><?php echo esc_html($caso['estado']); ?></span></td>
                            <td data-label="Fecha"><?php echo esc_html($caso['fecha']); ?></td>
                            <td data-label="Acciones">
                                <div class="flex justify-end items-center space-x-2">
                                    <a href="#" data-spa-link data-view="impuestos" data-action="manage" data-id="<?php echo $caso['ID']; ?>" class="h-8 w-8 rounded-md text-slate-500 hover:bg-slate-200 hover:text-blue-600 flex items-center justify-center transition-colors" title="Gestionar"><i class="fas fa-tasks"></i></a>
                                    <a href="#" data-spa-link data-view="impuestos" data-action="edit" data-id="<?php echo $caso['ID']; ?>" class="h-8 w-8 rounded-md text-slate-500 hover:bg-slate-200 hover:text-blue-600 flex items-center justify-center transition-colors" title="Editar"><i class="fas fa-edit"></i></a>
                                    <button data-delete-id="<?php echo $caso['ID']; ?>" class="h-8 w-8 rounded-md text-slate-500 hover:bg-red-100 hover:text-red-600 flex items-center justify-center transition-colors" title="Eliminar"><i class="fas fa-trash"></i></button>
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
    $caso = ($post_id > 0) ? get_post($post_id) : null;
    $meta = $caso ? get_post_meta($post_id) : [];
    $clientes_query = new WP_Query(['post_type' => 'cliente', 'posts_per_page' => -1]);
    $clientes_options = array_map(fn($p) => ['id' => $p->ID, 'title' => $p->post_title], $clientes_query->posts);
    $estados_terms = get_terms(['taxonomy' => 'estado_caso', 'hide_empty' => false]);
?>
    <div class="p-4 sm:p-6">
        <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
            <div>
                 <h1 class="text-2xl font-bold text-slate-800"><?php echo $post_id > 0 ? 'Editar Declaración' : 'Nueva Declaración'; ?></h1>
                 <p class="text-slate-500 mt-1 text-sm">Completa los campos para registrar la declaración de impuestos.</p>
            </div>
            <a href="#" data-spa-link data-view="impuestos" class="font-bold py-2 px-4 rounded-lg shadow-sm transition-all duration-300 flex items-center justify-center bg-slate-200 text-slate-800 hover:bg-slate-300 w-full sm:w-auto"><i class="fas fa-arrow-left mr-2"></i>Volver</a>
        </header>

        <div class="bg-white rounded-xl shadow-lg shadow-slate-200/50 overflow-hidden border border-slate-200 max-w-4xl mx-auto">
            <div class="p-6 sm:p-8">
                <form data-spa-form>
                    <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                    <input type="hidden" name="post_type" value="impuestos">
                    <input type="hidden" name="post_title" value="<?php echo $caso ? esc_attr($caso->post_title) : 'Nueva Declaración de Impuestos'; ?>">

                    <div class="space-y-8">
                        <section>
                            <h3 class="text-lg font-semibold text-slate-800 mb-4 border-b pb-2">Información Principal</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5 mt-4">
                                <div class="md:col-span-2">
                                    <label class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase" for="cliente_id_imp">Cliente*</label>
                                    <select name="cliente_id" id="cliente_id_imp" class="w-full" required>
                                        <option value="">Seleccione un cliente</option>
                                        <?php foreach($clientes_options as $cliente): ?>
                                            <option value="<?php echo $cliente['id']; ?>" <?php selected($meta['_cliente_id'][0] ?? '', $cliente['id']); ?>><?php echo esc_html($cliente['title']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase" for="ano_fiscal_imp">Año Fiscal*</label>
                                    <input type="number" name="ano_fiscal" id="ano_fiscal_imp" value="<?php echo esc_attr($meta['_ano_fiscal'][0] ?? date('Y')-1); ?>" class="w-full" placeholder="Ej: <?php echo date('Y') - 1; ?>">
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Tipo de Declaración</label>
                                    <input type="text" name="tipo_declaracion" value="<?php echo esc_attr($meta['_tipo_declaracion'][0] ?? ''); ?>" class="w-full" placeholder="Ej: 1040, 1120S...">
                                </div>
                                <div class="md:col-span-2">
                                   <label class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Estado del Caso</label>
                                   <select name="estado_caso" class="w-full">
                                       <?php 
                                       $current_term_id = $caso ? (wp_get_post_terms($post_id, 'estado_caso', ['fields' => 'ids'])[0] ?? 0) : 0;
                                       foreach($estados_terms as $term): ?>
                                            <option value="<?php echo $term->term_id; ?>" <?php selected($current_term_id, $term->term_id); ?>><?php echo esc_html($term->name); ?></option>
                                       <?php endforeach; ?>
                                   </select>
                                </div>
                            </div>
                        </section>
                        <section>
                             <h3 class="text-lg font-semibold text-slate-800 mb-4 border-b pb-2">Notas Internas</h3>
                             <div class="grid grid-cols-1 gap-y-5 mt-4">
                                <div>
                                    <textarea name="notas_preparador" class="w-full min-h-[120px] resize-y" rows="4" placeholder="Añade notas o comentarios sobre este caso..."><?php echo esc_textarea($meta['_notas_preparador'][0] ?? ''); ?></textarea>
                                </div>
                             </div>
                        </section>
                    </div>

                    <div class="mt-8">
                        <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-bold py-3 px-4 rounded-lg shadow-lg shadow-blue-500/20 hover:from-blue-700 hover:to-indigo-700 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300 flex items-center justify-center text-sm">
                            <span><?php echo $post_id > 0 ? 'Actualizar Declaración' : 'Guardar Declaración'; ?></span>
                            <i class="fas fa-arrow-right ml-2 transition-transform duration-300 group-hover:translate-x-1"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form[data-spa-form]');
        if (!form) return;
        const clienteSelect = form.querySelector('[name="cliente_id"]');
        const anoFiscalInput = form.querySelector('[name="ano_fiscal"]');
        const titleInput = form.querySelector('[name="post_title"]');
        function updateTitle() {
            if (!clienteSelect || !anoFiscalInput || !titleInput) return;
            const clienteText = clienteSelect.options[clienteSelect.selectedIndex]?.text || 'Cliente No Seleccionado';
            const anoFiscal = anoFiscalInput.value || '<?php echo date('Y')-1; ?>';
            if (clienteSelect.value) { titleInput.value = `Impuestos ${anoFiscal} para ${clienteText}`; } 
            else { titleInput.value = 'Nueva Declaración de Impuestos'; }
        }
        clienteSelect.addEventListener('change', updateTitle);
        anoFiscalInput.addEventListener('input', updateTitle);
        updateTitle();
    });
    </script>
<?php
}
?>

