<?php
// Determinar la acción (listar, crear, editar)
$action = isset($_POST['flowtax_action']) ? $_POST['flowtax_action'] : 'list';
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($action === 'list') {
    // Lógica para mostrar la lista de clientes
    $args = ['post_type' => 'cliente', 'posts_per_page' => -1];
    $query = new WP_Query($args);
    $all_posts = array_map('Flowtax_Ajax_Handler::format_post_data', $query->posts);
?>
    <div class="p-4 sm:p-6">
        <header class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Clientes</h1>
                <p class="text-slate-500 mt-1 text-sm">Gestiona la información de tus clientes.</p>
            </div>
            <a href="#" data-spa-link data-view="clientes" data-action="create" class="btn btn-primary">
                <i class="fas fa-plus mr-2"></i>Añadir Cliente
            </a>
        </header>

        <div class="card">
            <div class="mb-4">
                <div class="input-wrapper">
                    <i class="fas fa-search icon"></i>
                    <input type="text" data-search-input data-post-type="cliente" placeholder="Buscar por nombre, email..." class="form-input form-input-with-icon">
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Teléfono</th>
                            <th>Fecha de Registro</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="data-table-body">
                        <?php if (!empty($all_posts)): ?>
                            <?php foreach ($all_posts as $item): ?>
                                <tr>
                                    <td><a href="#" data-spa-link data-view="clientes" data-action="perfil" data-id="<?php echo $item['ID']; ?>" class="font-semibold text-blue-600 hover:underline"><?php echo esc_html($item['title']); ?></a></td>
                                    <td><?php echo esc_html($item['email']); ?></td>
                                    <td><?php echo esc_html($item['telefono']); ?></td>
                                    <td><?php echo esc_html($item['fecha']); ?></td>
                                    <td class="text-right space-x-2">
                                        <a href="#" data-spa-link data-view="clientes" data-action="perfil" data-id="<?php echo $item['ID']; ?>" class="btn-icon" title="Ver Perfil"><i class="fas fa-eye"></i></a>
                                        <a href="#" data-spa-link data-view="clientes" data-action="edit" data-id="<?php echo $item['ID']; ?>" class="btn-icon" title="Editar"><i class="fas fa-edit"></i></a>
                                        <button data-delete-id="<?php echo $item['ID']; ?>" class="btn-icon-danger" title="Eliminar"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center py-8 text-slate-500">No se encontraron clientes.</td></tr>
                        <?php endif; ?>
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
    
    $get_meta = fn($key) => isset($meta["_{$key}"]) ? esc_attr($meta["_{$key}"][0]) : '';
?>
    <div class="p-4 sm:p-6">
        <header class="flex justify-between items-center mb-6">
             <div>
                <h1 class="text-2xl font-bold text-slate-800"><?php echo $is_edit ? 'Editar Cliente' : 'Añadir Nuevo Cliente'; ?></h1>
                <p class="text-slate-500 mt-1 text-sm"><?php echo $is_edit ? 'Actualiza los detalles del cliente.' : 'Completa el formulario para añadir un nuevo cliente.'; ?></p>
            </div>
             <a href="#" data-spa-link data-view="clientes" class="btn btn-secondary"><i class="fas fa-arrow-left mr-2"></i>Volver</a>
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
                    <input type="hidden" name="post_id" value="<?php echo $id; ?>">
                    <input type="hidden" name="post_type" value="cliente">
                    
                    <div class="space-y-6">
                        <section>
                            <h3 class="text-lg font-semibold text-slate-800 mb-4 border-b pb-2">Información Personal</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                                <div class="form-group">
                                    <label for="post_title" class="form-label">Nombre Completo*</label>
                                    <input type="text" id="post_title" name="post_title" value="<?php echo $post ? esc_attr($post->post_title) : ''; ?>" class="form-input" placeholder="Escriba el nombre completo" required>
                                </div>
                                <div class="form-group">
                                    <label for="email" class="form-label">Correo Electrónico*</label>
                                    <input type="email" id="email" name="email" value="<?php echo $get_meta('email'); ?>" class="form-input" placeholder="ejemplo@correo.com" required>
                                </div>
                                <div class="form-group">
                                    <label for="telefono" class="form-label">Teléfono*</label>
                                    <input type="tel" id="telefono" name="telefono" value="<?php echo $get_meta('telefono'); ?>" class="form-input" placeholder="(809) 555-1234" required>
                                </div>
                                <div class="form-group">
                                    <label for="tax_id" class="form-label">ID de Impuestos (SSN/ITIN)</label>
                                    <input type="text" id="tax_id" name="tax_id" value="<?php echo $get_meta('tax_id'); ?>" class="form-input" placeholder="000-00-0000">
                                </div>
                            </div>
                        </section>

                        <section>
                            <h3 class="text-lg font-semibold text-slate-800 mb-4 border-b pb-2">Dirección</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                                <div class="md:col-span-2 form-group">
                                    <label for="direccion" class="form-label">Dirección</label>
                                    <input type="text" id="direccion" name="direccion" value="<?php echo $get_meta('direccion'); ?>" class="form-input" placeholder="Calle, número, sector">
                                </div>
                                <div class="form-group">
                                    <label for="ciudad" class="form-label">Ciudad</label>
                                    <input type="text" id="ciudad" name="ciudad" value="<?php echo $get_meta('ciudad'); ?>" class="form-input" placeholder="Santo Domingo">
                                </div>
                                <div class="form-group">
                                    <label for="estado_provincia" class="form-label">Estado / Provincia</label>
                                    <input type="text" id="estado_provincia" name="estado_provincia" value="<?php echo $get_meta('estado_provincia'); ?>" class="form-input" placeholder="Distrito Nacional">
                                </div>
                                <div class="form-group">
                                    <label for="codigo_postal" class="form-label">Código Postal</label>
                                    <input type="text" id="codigo_postal" name="codigo_postal" value="<?php echo $get_meta('codigo_postal'); ?>" class="form-input" placeholder="10101">
                                </div>
                            </div>
                        </section>
                    </div>

                    <div class="mt-8">
                        <button type="submit" class="btn-submit">
                           <span><?php echo $is_edit ? 'Actualizar Cliente' : 'Guardar Cliente'; ?></span>
                           <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php
}

