<?php
// Esta vista es un cascarón. Los datos se cargarán dinámicamente con JS.
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
?>
<div class="p-4 sm:p-6" data-cliente-id="<?php echo $id; ?>" id="cliente-perfil-view">
    <!-- Header: se llenará con JS -->
    <header class="flex justify-between items-center mb-6">
        <div>
            <h1 id="cliente-nombre-header" class="text-2xl font-bold text-slate-800">Cargando...</h1>
            <p class="text-slate-500 mt-1 text-sm">Expediente completo del cliente.</p>
        </div>
        <div>
            <a href="#" data-spa-link data-view="clientes" class="btn btn-secondary mr-2">
                <i class="fas fa-arrow-left mr-2"></i>Volver
            </a>
            <a href="#" data-spa-link data-view="clientes" data-action="edit" data-id="<?php echo $id; ?>" class="btn btn-primary">
                <i class="fas fa-edit mr-2"></i>Editar Cliente
            </a>
        </div>
    </header>

    <div id="perfil-content-area" class="opacity-0 transition-opacity duration-300">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Columna de Información -->
            <div class="lg:col-span-1 space-y-6">
                <div class="card">
                    <h2 class="card-title">Información de Contacto</h2>
                    <div id="info-contacto" class="mt-4 space-y-3 text-sm">Cargando...</div>
                </div>
                <div class="card">
                    <h2 class="card-title">Dirección</h2>
                    <div id="info-direccion" class="mt-4 space-y-2 text-sm">Cargando...</div>
                </div>
            </div>

            <!-- Columna de Casos -->
            <div class="lg:col-span-2">
                <div class="card">
                    <h2 class="card-title">Casos Asociados</h2>
                    <div id="casos-asociados-lista" class="mt-4 space-y-4">Cargando...</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Plantilla para un item de caso (oculta) -->
    <template id="caso-item-template">
        <a href="#" class="caso-item block p-4 rounded-lg border border-slate-200 hover:bg-slate-50 hover:border-blue-400 transition-all duration-200">
            <div class="flex justify-between items-center">
                <div>
                    <p class="font-semibold text-blue-600"></p>
                    <p class="text-xs text-slate-500"></p>
                </div>
                <div class="flex items-center space-x-3">
                    <span class="text-xs text-slate-400"></span>
                    <span class="status-badge text-xs font-semibold px-2 py-0.5 rounded-full"></span>
                </div>
            </div>
        </a>
    </template>
</div>
