<?php
// Determinar la acción (listar, crear, editar)
$action = isset($_POST['flowtax_action']) ? $_POST['flowtax_action'] : 'list';
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($action === 'list') {
    // Lógica para mostrar la lista de casos de inmigración
    $args = [
        'post_type' => ['peticion_familiar', 'ciudadania', 'renovacion_residencia'],
        'posts_per_page' => -1,
    ];
    $query = new WP_Query($args);
    $all_posts = array_map('Flowtax_Ajax_Handler::format_post_data', $query->posts);
?>
    <div class="p-6 sm:p-8">
        <header class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Casos de Inmigración</h1>
                <p class="text-gray-500 mt-1">Gestiona todos los casos de inmigración.</p>
            </div>
            <a href="#" data-spa-link data-view="inmigracion" data-action="create" class="font-bold py-2 px-4 rounded-lg shadow-sm transition-all duration-300 flex items-center justify-center bg-blue-600 text-white hover:bg-blue-700 hover:shadow-md">
                <i class="fas fa-plus mr-2"></i>Crear Nuevo Caso
            </a>
        </header>

        <div class="bg-white p-6 rounded-lg shadow-sm">
            <div class="mb-4">
                <input type="text" data-search-input data-post-type="peticion_familiar,ciudadania,renovacion_residencia" placeholder="Buscar por nombre, cliente..." class="w-full bg-white border border-slate-300 px-4 py-2.5 rounded-lg text-sm text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all duration-200 outline-none">
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="p-3 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Tipo de Caso</th>
                            <th class="p-3 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Cliente</th>
                            <th class="p-3 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Estado</th>
                            <th class="p-3 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Fecha de Creación</th>
                            <th class="p-3 text-right text-sm font-semibold text-gray-600 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="data-table-body">
                        <?php if (!empty($all_posts)): ?>
                            <?php foreach ($all_posts as $item): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="p-3 border-b border-slate-200 text-slate-600"><a href="#" data-spa-link data-view="inmigracion" data-action="edit" data-id="<?php echo $item['ID']; ?>" class="font-semibold text-blue-600 hover:underline"><?php echo esc_html($item['singular_name']); ?></a></td>
                                    <td class="p-3 border-b border-slate-200 text-slate-600"><?php echo esc_html($item['cliente_nombre']); ?></td>
                                    <td class="p-3 border-b border-slate-200 text-slate-600"><span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo esc_attr($item['estado_color']); ?>"><?php echo esc_html($item['estado']); ?></span></td>
                                    <td class="p-3 border-b border-slate-200 text-slate-600"><?php echo esc_html($item['fecha']); ?></td>
                                    <td class="text-right p-3 border-b border-slate-200 text-slate-600">
                                        <a href="#" data-spa-link data-view="inmigracion" data-action="edit" data-id="<?php echo $item['ID']; ?>" class="text-gray-500 hover:text-blue-600 mr-3 p-1"><i class="fas fa-edit"></i></a>
                                        <button data-delete-id="<?php echo $item['ID']; ?>" class="text-gray-500 hover:text-red-600 p-1"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center py-4 text-gray-500 p-3 border-b border-slate-200 text-slate-600">No se encontraron casos de inmigración.</td></tr>
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

    // Función auxiliar para obtener metadatos de forma segura
    $get_meta = function($key) use ($meta) {
        return isset($meta["_{$key}"]) ? esc_attr($meta["_{$key}"][0]) : '';
    };
    
    $post_type = $post ? $post->post_type : '';
?>
    <div class="p-6 sm:p-8">
        <header class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-800"><?php echo $is_edit ? 'Editar Caso de Inmigración' : 'Crear Nuevo Caso'; ?></h1>
                <p class="text-gray-500 mt-1"><?php echo $is_edit ? 'Actualiza los detalles del caso.' : 'Completa el formulario para un nuevo caso.'; ?></p>
            </div>
             <a href="#" data-spa-link data-view="inmigracion" class="font-bold py-2 px-4 rounded-lg shadow-sm transition-all duration-300 flex items-center justify-center bg-slate-200 text-slate-800 hover:bg-slate-300">
                <i class="fas fa-arrow-left mr-2"></i>Volver al listado
            </a>
        </header>

        <form data-spa-form class="bg-white p-6 rounded-lg shadow-sm grid grid-cols-1 md:grid-cols-3 gap-6">
            <input type="hidden" name="post_id" value="<?php echo $id; ?>">
            
            <!-- Columna 1: Detalles Principales -->
            <div class="md:col-span-2 space-y-6">
                <div class="border-b pb-4 mb-4">
                    <h2 class="text-xl font-semibold text-gray-700">Información del Caso</h2>
                </div>

                <div>
                    <label for="post_type" class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Tipo de Caso</label>
                    <select id="post_type" name="post_type" class="w-full" <?php echo $is_edit ? 'disabled' : ''; ?>>
                        <option value="">Selecciona un tipo</option>
                        <option value="peticion_familiar" <?php selected($post_type, 'peticion_familiar'); ?>>Petición Familiar</option>
                        <option value="ciudadania" <?php selected($post_type, 'ciudadania'); ?>>Ciudadanía</option>
                        <option value="renovacion_residencia" <?php selected($post_type, 'renovacion_residencia'); ?>>Renovación de Residencia</option>
                    </select>
                     <?php if($is_edit): ?>
                        <p class="text-sm text-gray-500 mt-1">El tipo de caso no se puede cambiar una vez creado.</p>
                     <?php endif; ?>
                </div>

                <div>
                    <label for="cliente_id" class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Cliente</label>
                    <select id="cliente_id" name="cliente_id" class="w-full">
                        <option value="">Selecciona un cliente</option>
                        <?php foreach($clientes as $cliente): ?>
                            <option value="<?php echo $cliente->ID; ?>" <?php selected($get_meta('cliente_id'), $cliente->ID); ?>><?php echo esc_html($cliente->post_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="post_title" class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Título del Caso (Generado automáticamente)</label>
                    <input type="text" id="post_title" name="post_title" class="w-full bg-gray-100 cursor-not-allowed" value="<?php echo $post ? esc_attr($post->post_title) : ''; ?>" readonly>
                </div>
                
                 <!-- Campos que se muestran condicionalmente -->
                <div id="form-fields-container" class="space-y-6">
                    <!-- Los campos específicos se inyectarán aquí por JS -->
                </div>

            </div>

            <!-- Columna 2: Estado y Acciones -->
            <div class="md:col-span-1 space-y-6">
                 <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="border-b pb-4 mb-4">
                        <h2 class="text-xl font-semibold text-gray-700">Estado y Gestión</h2>
                    </div>
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

                    <div class="mt-6">
                         <button type="submit" class="w-full font-bold py-2 px-4 rounded-lg shadow-sm transition-all duration-300 flex items-center justify-center bg-blue-600 text-white hover:bg-blue-700 hover:shadow-md">
                            <i class="fas fa-save mr-2"></i><?php echo $is_edit ? 'Actualizar Caso' : 'Guardar Caso'; ?>
                        </button>
                    </div>
                </div>
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
        const titleInput = document.getElementById('post_title');

        const fields = {
            peticion_familiar: `
                <div><label class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Nombre del Beneficiario</label><input type="text" name="beneficiario_nombre" value="<?php echo $get_meta('beneficiario_nombre'); ?>" class="w-full"></div>
                <div><label class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Relación</label><input type="text" name="relacion" value="<?php echo $get_meta('relacion'); ?>" class="w-full"></div>
                <div><label class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Número de Recibo USCIS</label><input type="text" name="uscis_receipt" value="<?php echo $get_meta('uscis_receipt'); ?>" class="w-full"></div>
            `,
            ciudadania: `
                <div><label class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">A-Number</label><input type="text" name="a_number" value="<?php echo $get_meta('a_number'); ?>" class="w-full"></div>
                <div><label class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Número de Recibo USCIS</label><input type="text" name="uscis_receipt" value="<?php echo $get_meta('uscis_receipt'); ?>" class="w-full"></div>
            `,
            renovacion_residencia: `
                <div><label class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">A-Number</label><input type="text" name="a_number" value="<?php echo $get_meta('a_number'); ?>" class="w-full"></div>
                <div><label class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Fecha de Expiración</label><input type="date" name="card_expiry" value="<?php echo $get_meta('card_expiry'); ?>" class="w-full"></div>
            `
        };
        
        function updateTitle() {
            const clienteText = clienteSelect.options[clienteSelect.selectedIndex]?.text;
            const tipoText = postTypeSelect.options[postTypeSelect.selectedIndex]?.text;
            
            if (clienteSelect.value && postTypeSelect.value) {
                titleInput.value = `${tipoText} para ${clienteText}`;
            } else {
                titleInput.value = '';
            }
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
