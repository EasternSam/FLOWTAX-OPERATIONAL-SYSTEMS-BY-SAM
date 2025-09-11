<?php
global $action, $id;

if ($action === 'list') {
    $query = new WP_Query(['post_type' => 'traduccion', 'posts_per_page' => -1]);
    $casos = array_map(['Flowtax_Ajax_Handler', 'format_post_data'], $query->posts);
?>
    <div class="p-8">
        <header class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Gestión de Traducciones</h1>
            </div>
            <a href="#" data-spa-link data-view="traducciones" data-action="create" class="btn btn-primary"><i class="fas fa-plus mr-2"></i>Nuevo Proyecto</a>
        </header>
        <div class="card">
             <div class="mb-4">
                 <input type="text" data-search-input data-post-type="traduccion" placeholder="Buscar por cliente..." class="form-input">
            </div>
            <table class="data-table">
                <thead><tr><th>Proyecto (Cliente)</th><th>Idiomas</th><th>Estado</th><th>Fecha</th><th></th></tr></thead>
                <tbody id="data-table-body">
                    <?php if (empty($casos)) : ?>
                        <tr><td colspan="5" class="text-center p-4 text-gray-500">No hay proyectos.</td></tr>
                    <?php else: foreach ($casos as $caso) :
                        $meta = get_post_meta($caso['ID']);
                    ?>
                    <tr>
                        <td>
                             <a href="#" data-spa-link data-view="traducciones" data-action="edit" data-id="<?php echo $caso['ID']; ?>" class="font-semibold text-blue-600 hover:underline"><?php echo $caso['title']; ?></a>
                            <p class="text-sm text-gray-500"><?php echo esc_html($caso['cliente_nombre']); ?></p>
                        </td>
                        <td><?php echo esc_html($meta['_idioma_origen'][0] ?? ''); ?> → <?php echo esc_html($meta['_idioma_destino'][0] ?? ''); ?></td>
                        <td><span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $caso['estado_color']; ?>"><?php echo $caso['estado']; ?></span></td>
                        <td><?php echo $caso['fecha']; ?></td>
                        <td class="text-right">
                             <a href="#" data-spa-link data-view="traducciones" data-action="edit" data-id="<?php echo $caso['ID']; ?>" class="text-gray-500 hover:text-blue-600 mr-2"><i class="fas fa-edit"></i></a>
                            <button data-delete-id="<?php echo $caso['ID']; ?>" class="text-gray-500 hover:text-red-600"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php
} else {
    // Formulario
?>
     <div class="p-8">
         <div class="card max-w-2xl mx-auto text-center">
             <h1 class="text-2xl font-bold text-gray-800 mb-4">Función en Desarrollo</h1>
             <p class="text-gray-600 mb-6">El formulario para crear/editar traducciones está en construcción.</p>
             <a href="#" data-spa-link data-view="traducciones" class="btn btn-primary">Volver a Traducciones</a>
         </div>
    </div>
<?php
}
