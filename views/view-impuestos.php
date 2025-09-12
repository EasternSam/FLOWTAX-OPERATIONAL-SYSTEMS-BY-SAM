<?php
global $action, $id;

if ($action === 'list') {
    $query = new WP_Query(['post_type' => 'impuestos', 'posts_per_page' => -1]);
    $casos = array_map(['Flowtax_Ajax_Handler', 'format_post_data'], $query->posts);
?>
    <div class="p-4 sm:p-6">
        <header class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Gestión de Impuestos</h1>
                <p class="text-slate-500 mt-1 text-sm">Casos de declaraciones de impuestos.</p>
            </div>
            <a href="#" data-spa-link data-view="impuestos" data-action="create" class="btn btn-primary"><i class="fas fa-plus mr-2"></i>Nueva Declaración</a>
        </header>
        <div class="card">
            <div class="mb-4">
                 <div class="input-wrapper">
                    <i class="fas fa-search icon"></i>
                    <input type="text" data-search-input data-post-type="impuestos" placeholder="Buscar por cliente, año fiscal..." class="form-input form-input-with-icon">
                </div>
            </div>
            <table class="data-table">
                <thead><tr><th>Caso (Cliente)</th><th>Año Fiscal</th><th>Estado</th><th>Fecha</th><th class="text-right">Acciones</th></tr></thead>
                <tbody id="data-table-body">
                    <?php if (empty($casos)) : ?>
                        <tr><td colspan="5" class="text-center p-8 text-slate-500">No se encontraron casos de impuestos.</td></tr>
                    <?php else: foreach ($casos as $caso) : ?>
                    <tr>
                        <td>
                            <a href="#" data-spa-link data-view="impuestos" data-action="edit" data-id="<?php echo $caso['ID']; ?>" class="font-semibold text-blue-600 hover:underline"><?php echo esc_html($caso['title']); ?></a>
                            <p class="text-sm text-slate-500"><?php echo esc_html($caso['cliente_nombre']); ?></p>
                        </td>
                        <td><?php echo esc_html(get_post_meta($caso['ID'], '_ano_fiscal', true)); ?></td>
                        <td><span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo esc_attr($caso['estado_color']); ?>"><?php echo esc_html($caso['estado']); ?></span></td>
                        <td><?php echo esc_html($caso['fecha']); ?></td>
                        <td class="text-right space-x-2">
                            <a href="#" data-spa-link data-view="impuestos" data-action="edit" data-id="<?php echo $caso['ID']; ?>" class="btn-icon"><i class="fas fa-edit"></i></a>
                            <button data-delete-id="<?php echo $caso['ID']; ?>" class="btn-icon-danger"><i class="fas fa-trash"></i></button>
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
    $caso = ($post_id > 0) ? get_post($post_id) : null;
    $meta = $caso ? get_post_meta($post_id) : [];
    $clientes_query = new WP_Query(['post_type' => 'cliente', 'posts_per_page' => -1]);
    $clientes_options = array_map(fn($p) => ['id' => $p->ID, 'title' => $p->post_title], $clientes_query->posts);
    $estados_terms = get_terms(['taxonomy' => 'estado_caso', 'hide_empty' => false]);
?>
    <div class="p-4 sm:p-6">
        <header class="flex justify-between items-center mb-6">
            <div>
                 <h1 class="text-2xl font-bold text-slate-800"><?php echo $post_id > 0 ? 'Editar Declaración' : 'Nueva Declaración'; ?></h1>
                 <p class="text-slate-500 mt-1 text-sm">Completa los campos para registrar la declaración de impuestos.</p>
            </div>
            <a href="#" data-spa-link data-view="impuestos" class="btn btn-secondary"><i class="fas fa-arrow-left mr-2"></i>Volver</a>
        </header>

        <div class="form-card max-w-4xl mx-auto">
            <div class="form-card-header">
                <div class="form-card-dots">
                    <span class="bg-red-400"></span>
                    <span class="bg-yellow-400"></span>
                    <span class="bg-green-400"></span>
                </div>
            </div>
            <div class="form-card-body">
                <form data-spa-form>
                    <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                    <input type="hidden" name="post_type" value="impuestos">
                    <input type="hidden" name="post_title" value="<?php echo $caso ? esc_attr($caso->post_title) : 'Nueva Declaración de Impuestos'; ?>">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                        <div class="form-group md:col-span-2">
                            <label class="form-label" for="cliente_id_imp">Cliente*</label>
                            <select name="cliente_id" id="cliente_id_imp" class="form-select" required>
                                <option value="">Seleccione un cliente</option>
                                <?php foreach($clientes_options as $cliente): ?>
                                    <option value="<?php echo $cliente['id']; ?>" <?php selected($meta['_cliente_id'][0] ?? '', $cliente['id']); ?>><?php echo esc_html($cliente['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="ano_fiscal_imp">Año Fiscal*</label>
                            <input type="number" name="ano_fiscal" id="ano_fiscal_imp" value="<?php echo esc_attr($meta['_ano_fiscal'][0] ?? date('Y')-1); ?>" class="form-input" placeholder="Ej: <?php echo date('Y') - 1; ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tipo de Declaración</label>
                            <input type="text" name="tipo_declaracion" value="<?php echo esc_attr($meta['_tipo_declaracion'][0] ?? ''); ?>" class="form-input" placeholder="Ej: 1040, 1120S...">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Reembolso Estimado ($)</label>
                            <input type="number" step="0.01" name="reembolso_estimado" value="<?php echo esc_attr($meta['_reembolso_estimado'][0] ?? ''); ?>" class="form-input" placeholder="0.00">
                        </div>
                        <div class="form-group">
                           <label class="form-label">Monto Adeudado ($)</label>
                           <input type="number" step="0.01" name="monto_adeudado" value="<?php echo esc_attr($meta['_monto_adeudado'][0] ?? ''); ?>" class="form-input" placeholder="0.00">
                        </div>
                        <div class="form-group md:col-span-2">
                           <label class="form-label">Estado del Caso</label>
                           <select name="estado_caso" class="form-select">
                               <?php 
                               $current_term_id = $caso ? (wp_get_post_terms($post_id, 'estado_caso', ['fields' => 'ids'])[0] ?? 0) : 0;
                               foreach($estados_terms as $term): ?>
                                    <option value="<?php echo $term->term_id; ?>" <?php selected($current_term_id, $term->term_id); ?>><?php echo esc_html($term->name); ?></option>
                               <?php endforeach; ?>
                           </select>
                        </div>
                        <div class="md:col-span-2 form-group">
                            <label class="form-label">Detalles de Ingresos</label>
                            <textarea name="ingresos_detalle" class="form-textarea" rows="4" placeholder="Describa las fuentes de ingreso (W2, 1099, etc.)"><?php echo esc_textarea($meta['_ingresos_detalle'][0] ?? ''); ?></textarea>
                        </div>
                         <div class="md:col-span-2 form-group">
                            <label class="form-label">Detalles de Deducciones</label>
                            <textarea name="deducciones_detalle" class="form-textarea" rows="4" placeholder="Liste las posibles deducciones (gastos médicos, donaciones, etc.)"><?php echo esc_textarea($meta['_deducciones_detalle'][0] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="mt-8">
                        <button type="submit" class="btn-submit">
                            <span><?php echo $post_id > 0 ? 'Actualizar Declaración' : 'Guardar Declaración'; ?></span>
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
    // JS Logic to auto-generate title remains the same
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

