<?php
// Determinar la acción (listar, crear, editar)
$action = isset($_POST['flowtax_action']) ? $_POST['flowtax_action'] : 'list';
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($action === 'list') {
    // Lógica para mostrar la lista de clientes
    $args = [
        'post_type' => 'cliente',
        'posts_per_page' => -1,
    ];
    $query = new WP_Query($args);
    $all_posts = array_map('Flowtax_Ajax_Handler::format_post_data', $query->posts);
?>
    <div class="p-6 sm:p-8">
        <header class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Clientes</h1>
                <p class="text-gray-500 mt-1">Gestiona la información de tus clientes.</p>
            </div>
            <a href="#" data-spa-link data-view="clientes" data-action="create" class="btn-primary">
                <i class="fas fa-plus mr-2"></i>Añadir Cliente
            </a>
        </header>

        <div class="bg-white p-6 rounded-lg shadow-sm">
            <div class="mb-4">
                <input type="text" data-search-input data-post-type="cliente" placeholder="Buscar por nombre, email..." class="form-input">
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="p-3 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Nombre</th>
                            <th class="p-3 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Email</th>
                            <th class="p-3 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Teléfono</th>
                            <th class="p-3 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Fecha de Registro</th>
                            <th class="p-3 text-right text-sm font-semibold text-gray-600 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="data-table-body">
                        <?php if (!empty($all_posts)): ?>
                            <?php foreach ($all_posts as $item): ?>
                                <tr class="hover:bg-gray-50">
                                    <td><a href="#" data-spa-link data-view="clientes" data-action="edit" data-id="<?php echo $item['ID']; ?>" class="font-semibold text-blue-600 hover:underline"><?php echo esc_html($item['title']); ?></a></td>
                                    <td><?php echo esc_html($item['email']); ?></td>
                                    <td><?php echo esc_html($item['telefono']); ?></td>
                                    <td><?php echo esc_html($item['fecha']); ?></td>
                                    <td class="text-right">
                                        <a href="#" data-spa-link data-view="clientes" data-action="edit" data-id="<?php echo $item['ID']; ?>" class="text-gray-500 hover:text-blue-600 mr-3 p-1"><i class="fas fa-edit"></i></a>
                                        <button data-delete-id="<?php echo $item['ID']; ?>" class="text-gray-500 hover:text-red-600 p-1"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center py-4 text-gray-500">No se encontraron clientes.</td></tr>
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
    
    // Función auxiliar para obtener metadatos de forma segura
    $get_meta = function($key) use ($meta) {
        return isset($meta["_{$key}"]) ? esc_attr($meta["_{$key}"][0]) : '';
    };
?>
    <div class="p-6 sm:p-8">
        <header class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-800"><?php echo $is_edit ? 'Editar Cliente' : 'Añadir Nuevo Cliente'; ?></h1>
                <p class="text-gray-500 mt-1"><?php echo $is_edit ? 'Actualiza los detalles del cliente.' : 'Completa el formulario para añadir un nuevo cliente.'; ?></p>
            </div>
             <a href="#" data-spa-link data-view="clientes" class="btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i>Volver al listado
            </a>
        </header>

        <form data-spa-form class="bg-white p-6 rounded-lg shadow-sm">
            <input type="hidden" name="post_id" value="<?php echo $id; ?>">
            <input type="hidden" name="post_type" value="cliente">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="post_title" class="form-label">Nombre Completo</label>
                    <input type="text" id="post_title" name="post_title" value="<?php echo $post ? esc_attr($post->post_title) : ''; ?>" class="form-input" required>
                </div>
                <div>
                    <label for="email" class="form-label">Correo Electrónico</label>
                    <input type="email" id="email" name="email" value="<?php echo $get_meta('email'); ?>" class="form-input" required>
                </div>
                 <div>
                    <label for="telefono" class="form-label">Teléfono</label>
                    <input type="tel" id="telefono" name="telefono" value="<?php echo $get_meta('telefono'); ?>" class="form-input">
                </div>
            </div>

            <div class="mt-8 pt-6 border-t">
                 <button type="submit" class="btn-primary">
                    <i class="fas fa-save mr-2"></i><?php echo $is_edit ? 'Actualizar Cliente' : 'Guardar Cliente'; ?>
                </button>
            </div>
        </form>
    </div>
<?php
}
?>

