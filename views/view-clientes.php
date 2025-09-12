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
            <a href="#" data-spa-link data-view="clientes" data-action="create" class="font-bold py-2 px-4 rounded-lg shadow-sm transition-all duration-300 flex items-center justify-center bg-blue-600 text-white hover:bg-blue-700 hover:shadow-md">
                <i class="fas fa-plus mr-2"></i>Añadir Cliente
            </a>
        </header>

        <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200/80">
            <div class="mb-4">
                <div class="relative">
                    <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                    <input type="text" data-search-input data-post-type="cliente" placeholder="Buscar por nombre, email..." class="pl-10 w-full bg-white border border-slate-300 px-4 py-2.5 rounded-lg text-sm text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all duration-200 outline-none">
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr>
                            <th class="p-3 bg-slate-50 font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">Nombre</th>
                            <th class="p-3 bg-slate-50 font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">Email</th>
                            <th class="p-3 bg-slate-50 font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">Teléfono</th>
                            <th class="p-3 bg-slate-50 font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">Fecha de Registro</th>
                            <th class="text-right p-3 bg-slate-50 font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="data-table-body">
                        <?php if (!empty($all_posts)): ?>
                            <?php foreach ($all_posts as $item): ?>
                                <tr class="hover:bg-slate-50/50">
                                    <td class="p-3 border-b border-slate-200 text-slate-600"><a href="#" data-spa-link data-view="clientes" data-action="perfil" data-id="<?php echo $item['ID']; ?>" class="font-semibold text-blue-600 hover:underline"><?php echo esc_html($item['title']); ?></a></td>
                                    <td class="p-3 border-b border-slate-200 text-slate-600"><?php echo esc_html($item['email']); ?></td>
                                    <td class="p-3 border-b border-slate-200 text-slate-600"><?php echo esc_html($item['telefono']); ?></td>
                                    <td class="p-3 border-b border-slate-200 text-slate-600"><?php echo esc_html($item['fecha']); ?></td>
                                    <td class="text-right space-x-2 p-3 border-b border-slate-200 text-slate-600">
                                        <a href="#" data-spa-link data-view="clientes" data-action="perfil" data-id="<?php echo $item['ID']; ?>" class="h-8 w-8 rounded-md text-slate-500 hover:bg-slate-200 hover:text-blue-600 flex items-center justify-center transition-colors inline-flex" title="Ver Perfil"><i class="fas fa-eye"></i></a>
                                        <a href="#" data-spa-link data-view="clientes" data-action="edit" data-id="<?php echo $item['ID']; ?>" class="h-8 w-8 rounded-md text-slate-500 hover:bg-slate-200 hover:text-blue-600 flex items-center justify-center transition-colors inline-flex" title="Editar"><i class="fas fa-edit"></i></a>
                                        <button data-delete-id="<?php echo $item['ID']; ?>" class="h-8 w-8 rounded-md text-slate-500 hover:bg-red-100 hover:text-red-600 flex items-center justify-center transition-colors" title="Eliminar"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center py-8 text-slate-500 p-3 border-b border-slate-200 text-slate-600">No se encontraron clientes.</td></tr>
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
             <a href="#" data-spa-link data-view="clientes" class="font-bold py-2 px-4 rounded-lg shadow-sm transition-all duration-300 flex items-center justify-center bg-slate-200 text-slate-800 hover:bg-slate-300"><i class="fas fa-arrow-left mr-2"></i>Volver</a>
        </header>

        <div class="bg-white rounded-xl shadow-lg shadow-slate-200/50 overflow-hidden border border-slate-200 max-w-4xl mx-auto">
             <div class="px-4 py-2.5 bg-slate-50/70 border-b border-slate-200 flex items-center">
                <div class="flex space-x-1.5">
                    <span class="block w-2.5 h-2.5 rounded-full bg-red-400"></span>
                    <span class="block w-2.5 h-2.5 rounded-full bg-yellow-400"></span>
                    <span class="block w-2.5 h-2.5 rounded-full bg-green-400"></span>
                </div>
            </div>
            <div class="p-6 sm:p-8">
                <form data-spa-form>
                    <input type="hidden" name="post_id" value="<?php echo $id; ?>">
                    <input type="hidden" name="post_type" value="cliente">
                    
                    <div class="space-y-8">
                        <section>
                            <h3 class="text-lg font-semibold text-slate-800 mb-4 border-b pb-2">Información Personal</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5 mt-4">
                                <div>
                                    <label for="post_title" class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Nombre Completo*</label>
                                    <input type="text" id="post_title" name="post_title" value="<?php echo $post ? esc_attr($post->post_title) : ''; ?>" class="w-full" placeholder="Escriba el nombre completo" required>
                                </div>
                                <div>
                                    <label for="email" class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Correo Electrónico*</label>
                                    <input type="email" id="email" name="email" value="<?php echo $get_meta('email'); ?>" class="w-full" placeholder="ejemplo@correo.com" required>
                                </div>
                                <div>
                                    <label for="telefono" class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Teléfono*</label>
                                    <input type="tel" id="telefono" name="telefono" value="<?php echo $get_meta('telefono'); ?>" class="w-full" placeholder="(809) 555-1234" required>
                                </div>
                                <div>
                                    <label for="tax_id" class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">ID de Impuestos (SSN/ITIN)</label>
                                    <input type="text" id="tax_id" name="tax_id" value="<?php echo $get_meta('tax_id'); ?>" class="w-full" placeholder="000-00-0000">
                                </div>
                            </div>
                        </section>

                        <section>
                            <h3 class="text-lg font-semibold text-slate-800 mb-4 border-b pb-2">Dirección</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5 mt-4">
                                <div class="md:col-span-2">
                                    <label for="direccion" class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Dirección</label>
                                    <input type="text" id="direccion" name="direccion" value="<?php echo $get_meta('direccion'); ?>" class="w-full" placeholder="Calle, número, sector">
                                </div>
                                <div>
                                    <label for="ciudad" class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Ciudad</label>
                                    <input type="text" id="ciudad" name="ciudad" value="<?php echo $get_meta('ciudad'); ?>" class="w-full" placeholder="Santo Domingo">
                                </div>
                                <div>
                                    <label for="estado_provincia" class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Estado / Provincia</label>
                                    <input type="text" id="estado_provincia" name="estado_provincia" value="<?php echo $get_meta('estado_provincia'); ?>" class="w-full" placeholder="Distrito Nacional">
                                </div>
                                <div>
                                    <label for="codigo_postal" class="text-xs font-semibold text-slate-600 mb-1.5 block tracking-wide uppercase">Código Postal</label>
                                    <input type="text" id="codigo_postal" name="codigo_postal" value="<?php echo $get_meta('codigo_postal'); ?>" class="w-full" placeholder="10101">
                                </div>
                            </div>
                        </section>
                    </div>

                    <div class="mt-8">
                        <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-bold py-3 px-4 rounded-lg shadow-lg shadow-blue-500/20 hover:from-blue-700 hover:to-indigo-700 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300 flex items-center justify-center text-sm">
                           <span><?php echo $is_edit ? 'Actualizar Cliente' : 'Guardar Cliente'; ?></span>
                           <i class="fas fa-arrow-right ml-2 transition-transform duration-300 group-hover:translate-x-1"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php
}
?>

