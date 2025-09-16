<?php
// Determinar la acción (listar, crear, editar)
$action = isset($_POST['flowtax_action']) ? $_POST['flowtax_action'] : 'list';
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($action === 'list') {
?>
    <div class="p-4 sm:p-6 lg:p-8 animate-fade-in-up">
        <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Clientes</h1>
                <p class="text-slate-500 mt-1 text-sm">Gestiona la información y los casos de tus clientes.</p>
            </div>
            <a href="#" data-spa-link data-view="clientes" data-action="create" class="btn-primary-gradient flex items-center justify-center">
                <i class="fas fa-plus mr-2"></i>Añadir Cliente
            </a>
        </header>

        <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200/80">
            <div class="mb-4">
                <div class="relative">
                    <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                    <input type="text" data-search-input data-post-type="cliente" placeholder="Buscar por nombre, email o teléfono..." class="pl-10 w-full form-input">
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm responsive-table">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="p-3 font-semibold text-slate-500 uppercase tracking-wider">Nombre</th>
                            <th class="p-3 font-semibold text-slate-500 uppercase tracking-wider">Email</th>
                            <th class="p-3 font-semibold text-slate-500 uppercase tracking-wider">Teléfono</th>
                            <th class="p-3 font-semibold text-slate-500 uppercase tracking-wider">Fecha de Registro</th>
                            <th class="text-right p-3 font-semibold text-slate-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="data-table-body" class="divide-y divide-slate-100">
                        <!-- El contenido de la tabla se carga dinámicamente vía JavaScript -->
                         <tr>
                            <td colspan="5">
                                <div class="text-center py-16">
                                    <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                      <path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m-7.5-2.96a3 3 0 00-4.682 2.72 8.986 8.986 0 003.74.477M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <h3 class="mt-2 text-lg font-semibold text-gray-800">No hay clientes</h3>
                                    <p class="mt-1 text-sm text-gray-500">Empieza por añadir tu primer cliente.</p>
                                    <div class="mt-6">
                                      <a href="#" data-spa-link data-view="clientes" data-action="create" class="btn-primary-gradient">
                                        <i class="fas fa-plus mr-2"></i>
                                        Añadir Cliente
                                      </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
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
    
    $get_meta = function($key) use ($meta) {
        return isset($meta["_{$key}"]) ? esc_attr($meta["_{$key}"][0]) : '';
    };
?>
    <div class="p-4 sm:p-6 lg:p-8 animate-fade-in-up">
        <form data-spa-form>
             <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                 <div>
                    <h1 class="text-2xl font-bold text-slate-800"><?php echo $is_edit ? 'Editar Cliente' : 'Añadir Nuevo Cliente'; ?></h1>
                    <p class="text-slate-500 mt-1 text-sm"><?php echo $is_edit ? 'Actualiza los detalles del cliente.' : 'Completa el formulario para añadir un nuevo cliente.'; ?></p>
                </div>
                <div class="flex items-center space-x-2">
                     <a href="#" data-spa-link data-view="clientes" class="btn-secondary">Volver</a>
                     <button type="submit" class="btn-primary-gradient">
                       <i class="fas fa-save mr-2"></i>
                       <span><?php echo $is_edit ? 'Actualizar Cliente' : 'Guardar Cliente'; ?></span>
                    </button>
                 </div>
            </header>

            <input type="hidden" name="post_id" value="<?php echo $id; ?>">
            <input type="hidden" name="post_type" value="cliente">

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-6">
                    <!-- Tarjeta de Información Personal -->
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200/80">
                        <div class="p-5 border-b border-slate-200/80">
                             <h3 class="text-lg font-semibold text-slate-800">Información Personal</h3>
                        </div>
                        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                            <div>
                                <label for="post_title" class="form-label">Nombre Completo*</label>
                                <input type="text" id="post_title" name="post_title" value="<?php echo $post ? esc_attr($post->post_title) : ''; ?>" class="form-input w-full" placeholder="Escriba el nombre completo" required>
                            </div>
                            <div>
                                <label for="email" class="form-label">Correo Electrónico*</label>
                                <input type="email" id="email" name="email" value="<?php echo $get_meta('email'); ?>" class="form-input w-full" placeholder="ejemplo@correo.com" required>
                            </div>
                            <div>
                                <label for="telefono" class="form-label">Teléfono*</label>
                                <input type="tel" id="telefono" name="telefono" value="<?php echo $get_meta('telefono'); ?>" class="form-input w-full" placeholder="(809) 555-1234" required>
                            </div>
                            <div>
                                <label for="tax_id" class="form-label">ID de Impuestos (SSN/ITIN)</label>
                                <input type="text" id="tax_id" name="tax_id" value="<?php echo $get_meta('tax_id'); ?>" class="form-input w-full" placeholder="000-00-0000">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tarjeta de Dirección -->
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200/80">
                         <div class="p-5 border-b border-slate-200/80">
                             <h3 class="text-lg font-semibold text-slate-800">Dirección</h3>
                        </div>
                        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                            <div class="md:col-span-2">
                                <label for="direccion" class="form-label">Dirección</label>
                                <input type="text" id="direccion" name="direccion" value="<?php echo $get_meta('direccion'); ?>" class="form-input w-full" placeholder="Calle, número, sector">
                            </div>
                            <div>
                                <label for="ciudad" class="form-label">Ciudad</label>
                                <input type="text" id="ciudad" name="ciudad" value="<?php echo $get_meta('ciudad'); ?>" class="form-input w-full" placeholder="Santo Domingo">
                            </div>
                            <div>
                                <label for="estado_provincia" class="form-label">Estado / Provincia</label>
                                <input type="text" id="estado_provincia" name="estado_provincia" value="<?php echo $get_meta('estado_provincia'); ?>" class="form-input w-full" placeholder="Distrito Nacional">
                            </div>
                            <div>
                                <label for="codigo_postal" class="form-label">Código Postal</label>
                                <input type="text" id="codigo_postal" name="codigo_postal" value="<?php echo $get_meta('codigo_postal'); ?>" class="form-input w-full" placeholder="10101">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha -->
                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200/80">
                         <div class="p-5 border-b border-slate-200/80">
                             <h3 class="text-lg font-semibold text-slate-800">Organización</h3>
                        </div>
                        <div class="p-5 space-y-4">
                             <div>
                                <label class="form-label">Etiquetas</label>
                                <p class="text-sm text-slate-400 mt-1">Asigna etiquetas para organizar. (Ej: VIP, Nuevo, Local)</p>
                                <div class="mt-2">
                                    <input type="text" class="form-input w-full" placeholder="Añadir etiqueta..." disabled>
                                    <p class="text-xs text-slate-400 mt-1">Función en desarrollo.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
<?php
}
?>
