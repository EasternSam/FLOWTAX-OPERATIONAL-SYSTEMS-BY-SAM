<?php
// El ID y la acción son pasados desde el AJAX handler
$action = $action ?? 'list';
$id = $id ?? 0;

if ($action === 'list') {
    // Lógica para mostrar la lista de casos de inmigración
    $args = [
        'post_type' => ['peticion_familiar', 'ciudadania', 'renovacion_residencia'],
        'posts_per_page' => -1,
    ];
    $query = new WP_Query($args);
    $all_posts = array_map('Flowtax_Ajax_Handler::format_post_data', $query->posts);
?>
    <div class="p-4 sm:p-6">
        <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Casos de Inmigración</h1>
                <p class="text-slate-500 mt-1">Gestiona todos los casos de inmigración.</p>
            </div>
            <a href="#" data-spa-link data-view="inmigracion" data-action="create" class="font-bold py-2 px-4 rounded-lg shadow-sm transition-all duration-300 flex items-center justify-center bg-blue-600 text-white hover:bg-blue-700 hover:shadow-md w-full sm:w-auto">
                <i class="fas fa-plus mr-2"></i>Crear Nuevo Caso
            </a>
        </header>

        <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200/80">
            <div class="mb-4">
                <div class="relative">
                    <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                    <input type="text" data-search-input data-post-type="peticion_familiar,ciudadania,renovacion_residencia" placeholder="Buscar por nombre, cliente..." class="pl-10 w-full bg-white border border-slate-300 px-4 py-2.5 rounded-lg text-sm text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all duration-200 outline-none">
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm responsive-table">
                    <thead>
                        <tr>
                            <th class="p-3 bg-slate-50 font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">Tipo de Caso</th>
                            <th class="p-3 bg-slate-50 font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">Cliente</th>
                            <th class="p-3 bg-slate-50 font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">Estado</th>
                            <th class="p-3 bg-slate-50 font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">Fecha de Creación</th>
                            <th class="p-3 text-right bg-slate-50 font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="data-table-body">
                        <?php if (!empty($all_posts)): ?>
                            <?php foreach ($all_posts as $item): ?>
                                <tr>
                                    <td data-label="Tipo de Caso">
                                        <a href="#" data-spa-link data-view="inmigracion" data-action="manage" data-id="<?php echo $item['ID']; ?>" class="font-semibold text-blue-600 hover:underline"><?php echo esc_html($item['title']); ?></a>
                                        <p class="text-sm text-slate-500"><?php echo esc_html($item['singular_name']); ?></p>
                                    </td>
                                    <td data-label="Cliente"><?php echo esc_html($item['cliente_nombre']); ?></td>
                                    <td data-label="Estado"><span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo esc_attr($item['estado_color']); ?>"><?php echo esc_html($item['estado']); ?></span></td>
                                    <td data-label="Fecha"><?php echo esc_html($item['fecha']); ?></td>
                                    <td data-label="Acciones">
                                        <div class="flex justify-end items-center space-x-2">
                                            <a href="#" data-spa-link data-view="inmigracion" data-action="manage" data-id="<?php echo $item['ID']; ?>" class="h-8 w-8 rounded-md text-slate-500 hover:bg-slate-200 hover:text-blue-600 flex items-center justify-center transition-colors" title="Gestionar"><i class="fas fa-tasks"></i></a>
                                            <a href="#" data-spa-link data-view="inmigracion" data-action="edit" data-id="<?php echo $item['ID']; ?>" class="h-8 w-8 rounded-md text-slate-500 hover:bg-slate-200 hover:text-blue-600 flex items-center justify-center transition-colors" title="Editar"><i class="fas fa-edit"></i></a>
                                            <button data-delete-id="<?php echo $item['ID']; ?>" class="h-8 w-8 rounded-md text-slate-500 hover:bg-red-100 hover:text-red-600 flex items-center justify-center transition-colors" title="Eliminar"><i class="fas fa-trash"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center py-8 text-slate-500">No se encontraron casos de inmigración.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php
} elseif ($action === 'create' || $action === 'edit') {
    // Lógica para mostrar el formulario de creación/edición
    $post = ($id > 0) ? get_post($id) : null;
    $meta = ($id > 0) ? get_post_meta($id) : [];
    $is_edit = $id > 0;

    $clientes_query = new WP_Query(['post_type' => 'cliente', 'posts_per_page' => -1]);
    $clientes = $clientes_query->posts;

    $estados = get_terms(['taxonomy' => 'estado_caso', 'hide_empty' => false]);

    $get_meta = function($key, $default = '') use ($meta) {
        return isset($meta["_{$key}"]) ? esc_attr($meta["_{$key}"][0]) : $default;
    };
    
    $post_type = $post ? $post->post_type : '';
?>
    <div class="p-4 sm:p-6">
        <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800"><?php echo $is_edit ? 'Editar Caso de Inmigración' : 'Crear Nuevo Caso'; ?></h1>
                <p class="text-slate-500 mt-1"><?php echo $is_edit ? 'Actualiza los detalles del caso.' : 'Completa el formulario para un nuevo caso.'; ?></p>
            </div>
             <a href="#" data-spa-link data-view="inmigracion" class="font-bold py-2 px-4 rounded-lg shadow-sm transition-all duration-300 flex items-center justify-center bg-slate-200 text-slate-800 hover:bg-slate-300 w-full sm:w-auto">
                <i class="fas fa-arrow-left mr-2"></i>Volver al listado
            </a>
        </header>

        <form data-spa-form class="bg-white p-6 rounded-lg shadow-sm space-y-8 max-w-4xl mx-auto">
            <input type="hidden" name="post_id" value="<?php echo $id; ?>">
            <input type="hidden" id="post_title_hidden" name="post_title" value="<?php echo $post ? esc_attr($post->post_title) : ''; ?>">

             <section>
                <h3 class="text-lg font-semibold text-slate-800 mb-4 border-b pb-2">Información del Caso</h3>
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                    <div>
                        <label for="post_type" class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Tipo de Caso*</label>
                        <select id="post_type" name="post_type" class="w-full" <?php echo $is_edit ? 'disabled' : ''; ?> required>
                            <option value="">Selecciona un tipo</option>
                            <option value="peticion_familiar" <?php selected($post_type, 'peticion_familiar'); ?>>Petición Familiar</option>
                            <option value="ciudadania" <?php selected($post_type, 'ciudadania'); ?>>Ciudadanía</option>
                            <option value="renovacion_residencia" <?php selected($post_type, 'renovacion_residencia'); ?>>Renovación de Residencia</option>
                        </select>
                         <?php if($is_edit): ?>
                            <p class="text-sm text-gray-500 mt-1">El tipo de caso no se puede cambiar.</p>
                         <?php endif; ?>
                    </div>

                    <div>
                        <label for="cliente_id" class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Cliente*</label>
                        <select id="cliente_id" name="cliente_id" class="w-full" required>
                            <option value="">Selecciona un cliente</option>
                            <?php foreach($clientes as $cliente): ?>
                                <option value="<?php echo $cliente->ID; ?>" <?php selected($get_meta('cliente_id'), $cliente->ID); ?>><?php echo esc_html($cliente->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                 </div>
                 <div class="mt-4">
                    <p class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Título del Caso (Generado automáticamente)</p>
                    <p id="post_title_display" class="w-full bg-gray-100 p-2.5 rounded-md text-sm text-slate-700 min-h-[42px]"><?php echo $post ? esc_html($post->post_title) : '...'; ?></p>
                 </div>
            </section>
            
            <section id="form-fields-container" class="space-y-6"></section>

            <section>
                 <h3 class="text-lg font-semibold text-slate-800 mb-4 border-b pb-2">Gestión y Estado</h3>
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                    <div>
                        <label for="estado_caso" class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Estado del Caso</label>
                        <select id="estado_caso" name="estado_caso" class="w-full">
                            <?php
                                $current_status = $is_edit ? wp_get_post_terms($id, 'estado_caso', ['fields' => 'ids']) : [];
                                $current_status_id = !empty($current_status) ? $current_status[0] : '';
                            ?>
                            <?php foreach($estados as $estado): ?>
                                <option value="<?php echo $estado->term_id; ?>" <?php selected($current_status_id, $estado->term_id); ?>><?php echo esc_html($estado->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                 </div>
                 <div class="mt-4">
                    <label class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Notas Internas</label>
                    <textarea name="notas_preparador" class="w-full min-h-[100px]" placeholder="Añade notas sobre el caso..."><?php echo esc_textarea($get_meta('notas_preparador')); ?></textarea>
                 </div>
            </section>

            <div class="mt-6">
                 <button type="submit" class="w-full font-bold py-3 px-4 rounded-lg shadow-sm transition-all duration-300 flex items-center justify-center bg-blue-600 text-white hover:bg-blue-700 hover:shadow-md">
                    <i class="fas fa-save mr-2"></i><?php echo $is_edit ? 'Actualizar Caso' : 'Guardar Caso'; ?>
                </button>
            </div>
        </form>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form[data-spa-form]');
        if (!form) return;

        const postTypeSelect = document.getElementById('post_type');
        const clienteSelect = document.getElementById('cliente_id');
        const container = document.getElementById('form-fields-container');
        const titleDisplay = document.getElementById('post_title_display');
        const titleInput = document.getElementById('post_title_hidden');

        const fields = {
            peticion_familiar: `
                <h3 class="text-lg font-semibold text-slate-800 border-b pb-2">Detalles de Petición</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                    <div><label class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Nombre del Beneficiario</label><input type="text" name="beneficiario_nombre" value="<?php echo $get_meta('beneficiario_nombre'); ?>" class="w-full"></div>
                    <div><label class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Relación</label><input type="text" name="relacion" value="<?php echo $get_meta('relacion'); ?>" class="w-full"></div>
                    <div><label class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Número de Recibo USCIS</label><input type="text" name="uscis_receipt" value="<?php echo $get_meta('uscis_receipt'); ?>" class="w-full"></div>
                </div>
            `,
            ciudadania: `
                <h3 class="text-lg font-semibold text-slate-800 border-b pb-2">Detalles de Ciudadanía</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                    <div><label class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">A-Number</label><input type="text" name="a_number" value="<?php echo $get_meta('a_number'); ?>" class="w-full"></div>
                    <div><label class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Número de Recibo USCIS</label><input type="text" name="uscis_receipt" value="<?php echo $get_meta('uscis_receipt'); ?>" class="w-full"></div>
                </div>
            `,
            renovacion_residencia: `
                 <h3 class="text-lg font-semibold text-slate-800 border-b pb-2">Detalles de Renovación</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                    <div><label class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">A-Number</label><input type="text" name="a_number" value="<?php echo $get_meta('a_number'); ?>" class="w-full"></div>
                    <div><label class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Fecha de Expiración</label><input type="date" name="card_expiry" value="<?php echo $get_meta('card_expiry'); ?>" class="w-full"></div>
                </div>
            `
        };
        
        function updateTitle() {
            const clienteText = clienteSelect.options[clienteSelect.selectedIndex]?.text;
            const tipoText = postTypeSelect.options[postTypeSelect.selectedIndex]?.text;
            let newTitle = '...';
            
            if (clienteSelect.value && postTypeSelect.value) {
                newTitle = `${tipoText} para ${clienteText}`;
            }
            titleDisplay.textContent = newTitle;
            titleInput.value = newTitle;
        }

        function renderFields() {
            const selectedType = postTypeSelect.value;
            container.innerHTML = fields[selectedType] || '';
            updateTitle();
        }

        postTypeSelect.addEventListener('change', renderFields);
        clienteSelect.addEventListener('change', updateTitle);

        renderFields();
    });
    </script>
<?php
}
?>

