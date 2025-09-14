<?php
$action = $action ?? 'list';
$id = $id ?? 0;

if ($action === 'list') {
    $estados_deuda = get_terms(['taxonomy' => 'estado_deuda', 'hide_empty' => false]);
?>
    <div class="p-4 sm:p-6 lg:p-8">
        <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Cuentas por Cobrar</h1>
                <p class="text-slate-500 mt-1 text-sm">Gestiona las deudas de tus clientes y envía recordatorios de pago.</p>
            </div>
            <a href="#" data-spa-link data-view="cuentas-por-cobrar" data-action="create" class="btn-primary flex items-center justify-center">
                <i class="fas fa-plus mr-2"></i>Añadir Deuda
            </a>
        </header>

        <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200/80">
            <div class="flex flex-col sm:flex-row gap-4 mb-4">
                 <div class="relative flex-grow">
                    <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                    <input type="text" data-search-input data-post-type="deuda" placeholder="Buscar por cliente, concepto o monto..." class="pl-10 w-full form-input">
                </div>
                <div class="flex items-center space-x-2 bg-slate-100 p-1 rounded-lg">
                     <button data-status-filter="" class="px-3 py-1.5 text-sm font-semibold rounded-md transition-colors active">Todos</button>
                    <?php foreach($estados_deuda as $estado): ?>
                        <button data-status-filter="<?php echo esc_attr($estado->slug); ?>" class="px-3 py-1.5 text-sm font-semibold rounded-md transition-colors"><?php echo esc_html($estado->name); ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm responsive-table">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="p-3 font-semibold text-slate-500 uppercase tracking-wider">Cliente / Concepto</th>
                            <th class="p-3 font-semibold text-slate-500 uppercase tracking-wider">Balance (Restante / Total)</th>
                            <th class="p-3 font-semibold text-slate-500 uppercase tracking-wider">Fecha Vencimiento</th>
                            <th class="p-3 font-semibold text-slate-500 uppercase tracking-wider">Estado</th>
                            <th class="text-right p-3 font-semibold text-slate-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="data-table-body" class="divide-y divide-slate-100">
                         <tr><td colspan="5" class="text-center p-8 text-slate-500">Cargando deudas...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php
} elseif ($action === 'create' || $action === 'edit') {
    $post = ($id > 0) ? get_post($id) : null;
    $meta = ($id > 0) ? get_post_meta($id) : [];
    $is_edit = $id > 0;
    
    $get_meta = function($key, $default = '') use ($meta) {
        return isset($meta["_{$key}"]) ? esc_attr($meta["_{$key}"][0]) : $default;
    };

    $clientes_query = new WP_Query(['post_type' => 'cliente', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);
    $clientes = $clientes_query->posts;

    $estados_deuda = get_terms(['taxonomy' => 'estado_deuda', 'hide_empty' => false]);
?>
    <div class="p-4 sm:p-6 lg:p-8">
        <form data-spa-form>
             <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                 <div>
                    <h1 class="text-2xl font-bold text-slate-800"><?php echo $is_edit ? 'Editar Deuda' : 'Añadir Nueva Deuda'; ?></h1>
                </div>
                <div class="flex items-center space-x-2">
                     <a href="#" data-spa-link data-view="cuentas-por-cobrar" class="btn-secondary">Volver</a>
                     <button type="submit" class="btn-primary"><i class="fas fa-save mr-2"></i><span><?php echo $is_edit ? 'Actualizar Deuda' : 'Guardar Deuda'; ?></span></button>
                 </div>
            </header>

            <input type="hidden" name="post_id" value="<?php echo $id; ?>">
            <input type="hidden" name="post_type" value="deuda">
            
            <div class="bg-white p-6 rounded-lg shadow-sm space-y-6 max-w-4xl mx-auto">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label for="post_title" class="form-label">Concepto*</label>
                        <input type="text" id="post_title" name="post_title" value="<?php echo $post ? esc_attr($post->post_title) : ''; ?>" class="form-input w-full" required>
                    </div>
                    <div>
                        <label for="cliente_id" class="form-label">Cliente*</label>
                        <select id="cliente_id" name="cliente_id" class="form-input w-full" required>
                            <option value="">Selecciona un cliente</option>
                            <?php foreach($clientes as $cliente): ?>
                                <option value="<?php echo $cliente->ID; ?>" <?php selected($get_meta('cliente_id'), $cliente->ID); ?>><?php echo esc_html($cliente->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                     <div>
                        <label for="estado_deuda" class="form-label">Estado</label>
                        <select id="estado_deuda" name="estado_deuda" class="form-input w-full">
                             <?php
                                $current_status_id = $is_edit ? (wp_get_post_terms($id, 'estado_deuda', ['fields' => 'ids'])[0] ?? '') : '';
                                foreach($estados_deuda as $estado): ?>
                                <option value="<?php echo $estado->term_id; ?>" <?php selected($current_status_id, $estado->term_id); ?>><?php echo esc_html($estado->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                         <div>
                            <label for="monto_deuda" class="form-label">Monto Total*</label>
                            <input type="number" step="0.01" id="monto_deuda" name="monto_deuda" value="<?php echo $get_meta('monto_deuda'); ?>" class="form-input w-full" required>
                        </div>
                        <div>
                            <label for="divisa" class="form-label">Divisa*</label>
                            <select id="divisa" name="divisa" class="form-input w-full">
                                <option value="USD" <?php selected($get_meta('divisa', 'USD'), 'USD'); ?>>USD</option>
                                <option value="DOP" <?php selected($get_meta('divisa'), 'DOP'); ?>>DOP</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label for="fecha_vencimiento" class="form-label">Fecha de Vencimiento</label>
                        <input type="date" id="fecha_vencimiento" name="fecha_vencimiento" value="<?php echo $get_meta('fecha_vencimiento'); ?>" class="form-input w-full">
                    </div>

                    <?php if ($is_edit): ?>
                    <div>
                        <label for="monto_abonado" class="form-label">Monto Abonado</label>
                        <input type="text" id="monto_abonado_display" name="monto_abonado_display" value="<?php echo esc_attr($get_meta('divisa', 'USD')); ?>$ <?php echo number_format(floatval($get_meta('monto_abonado', 0)), 2); ?>" class="form-input w-full bg-slate-100" readonly>
                        <input type="hidden" name="monto_abonado" value="<?php echo floatval($get_meta('monto_abonado', 0)); ?>">
                    </div>
                     <div>
                        <label class="form-label">Monto Restante</label>
                        <p id="monto_restante_display" class="font-semibold text-slate-700 pt-2.5">
                            <?php echo esc_attr($get_meta('divisa', 'USD')); ?>$ <?php
                                $total = floatval($get_meta('monto_deuda', 0));
                                $abonado = floatval($get_meta('monto_abonado', 0));
                                echo number_format($total - $abonado, 2);
                            ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
    <script>
        (() => {
            const form = document.querySelector('form[data-spa-form]');
            if (!form) return;
            
            const montoDeudaInput = form.querySelector('#monto_deuda');
            const montoAbonadoHidden = form.querySelector('input[name="monto_abonado"]');
            const restanteDisplay = form.querySelector('#monto_restante_display');
            const divisaSelect = form.querySelector('#divisa');

            const formatCurrencyLocal = (amount, currency = 'USD') => {
                const num = parseFloat(amount) || 0;
                const options = { style: 'currency', currency: currency, currencyDisplay: 'narrowSymbol' };
                let prefix = currency === 'USD' ? 'USD$ ' : 'RD$ ';
                return prefix + new Intl.NumberFormat('en-US', options).format(num).replace('$', '');
            };

            const updateRestante = () => {
                if (!restanteDisplay) return;
                const total = parseFloat(montoDeudaInput.value) || 0;
                const abonado = montoAbonadoHidden ? (parseFloat(montoAbonadoHidden.value) || 0) : 0;
                const restante = total - abonado;
                const divisa = divisaSelect.value;
                restanteDisplay.textContent = formatCurrencyLocal(restante, divisa);
            };
            
            if (montoDeudaInput) {
                montoDeudaInput.addEventListener('input', updateRestante);
            }
            if(divisaSelect) {
                 divisaSelect.addEventListener('change', updateRestante);
            }
        })();
    </script>
<?php
}
?>

