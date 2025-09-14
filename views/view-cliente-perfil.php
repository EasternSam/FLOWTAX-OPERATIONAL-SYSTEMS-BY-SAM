<?php
// Esta vista es la plantilla principal para el perfil del cliente.
// Los datos se cargarán dinámicamente con JavaScript.
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
?>
<div class="p-4 sm:p-6 lg:p-8" data-cliente-id="<?php echo $id; ?>" id="cliente-perfil-view">
    
    <!-- Header del Perfil -->
    <header class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <div class="flex items-center">
            <div id="cliente-avatar" class="h-16 w-16 rounded-full bg-slate-200 flex items-center justify-center text-slate-500 text-2xl font-bold mr-4 flex-shrink-0">
                <span id="cliente-iniciales">--</span>
            </div>
            <div>
                <h1 id="cliente-nombre-header" class="text-2xl font-bold text-slate-800 leading-tight">Cargando...</h1>
                <p class="text-slate-500 mt-1 text-sm">Expediente completo del cliente.</p>
            </div>
        </div>
        <div class="flex items-center space-x-2 w-full md:w-auto">
            <a href="#" data-spa-link data-view="clientes" class="btn-secondary w-full sm:w-auto">
                <i class="fas fa-arrow-left mr-2"></i>Volver
            </a>
            <a href="#" data-spa-link data-view="clientes" data-action="edit" data-id="<?php echo $id; ?>" class="btn-primary w-full sm:w-auto">
                <i class="fas fa-edit mr-2"></i>Editar Cliente
            </a>
        </div>
    </header>

    <!-- Contenido del Perfil -->
    <div id="perfil-content-area" class="opacity-0 transition-opacity duration-300 grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Columna Izquierda (Información de Contacto) -->
        <div class="lg:col-span-1 space-y-6">
            <div class="card">
                <h2 class="card-title">Información de Contacto</h2>
                <ul id="info-contacto" class="mt-4 space-y-4 text-sm">
                    <!-- Esqueleto de Carga -->
                    <li class="flex items-start"><i class="fas fa-envelope fa-fw w-6 text-slate-300 pt-1"></i><div><div class="h-3 bg-slate-200 rounded-full w-16 mb-1.5"></div><div class="h-4 bg-slate-200 rounded w-40"></div></div></li>
                    <li class="flex items-start"><i class="fas fa-phone fa-fw w-6 text-slate-300 pt-1"></i><div><div class="h-3 bg-slate-200 rounded-full w-12 mb-1.5"></div><div class="h-4 bg-slate-200 rounded w-32"></div></div></li>
                    <li class="flex items-start"><i class="fas fa-id-card fa-fw w-6 text-slate-300 pt-1"></i><div><div class="h-3 bg-slate-200 rounded-full w-10 mb-1.5"></div><div class="h-4 bg-slate-200 rounded w-28"></div></div></li>
                </ul>
            </div>
        </div>

        <!-- Columna Derecha (Casos y Deudas) -->
        <div class="lg:col-span-2 space-y-6">
            <div class="card">
                <div class="flex justify-between items-center mb-4">
                     <h2 class="card-title">Casos Asociados</h2>
                     <a href="#" data-spa-link data-view="impuestos" data-action="create" class="btn-secondary text-xs"><i class="fas fa-plus mr-1.5"></i> Nuevo Caso</a>
                </div>
                <div id="casos-asociados-lista" class="space-y-3"><p class="text-center py-8 text-slate-500">Cargando casos...</p></div>
            </div>

             <div class="card">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="card-title">Deudas y Pagos</h2>
                    <a href="#" data-spa-link data-view="cuentas-por-cobrar" data-action="create" class="btn-secondary text-xs"><i class="fas fa-plus mr-1.5"></i> Nueva Deuda</a>
                </div>
                <div id="deudas-asociadas-lista" class="space-y-3">
                    <p class="text-center py-8 text-slate-500">Cargando deudas...</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Plantillas Ocultas para JS -->
    <template id="caso-item-template">
        <a href="#" class="caso-item block p-4 rounded-lg border border-slate-200 hover:bg-slate-50 hover:border-slate-300 transition-all duration-200">
            <div class="flex justify-between items-center">
                <div>
                    <p class="font-semibold text-slate-700"></p>
                    <p class="text-xs text-slate-500"></p>
                </div>
                <div class="flex items-center space-x-3">
                    <span class="text-xs text-slate-400"></span>
                    <span class="status-badge text-xs font-semibold px-2 py-0.5 rounded-full"></span>
                </div>
            </div>
        </a>
    </template>
    
    <template id="deuda-item-template">
        <div class="deuda-item block p-4 rounded-lg border border-slate-200 hover:bg-slate-50 transition-all duration-200">
            <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-2">
                <div class="flex-grow">
                    <p class="font-semibold text-slate-700"></p>
                    <p class="text-xs text-slate-400"></p>
                </div>
                <div class="flex items-center gap-4">
                     <div class="text-right">
                        <p class="font-semibold text-green-600 balance-restante"></p>
                        <p class="text-xs text-slate-500">de <span class="balance-total"></span></p>
                    </div>
                    <span class="status-badge text-xs font-semibold px-2 py-0.5 rounded-full"></span>
                    <div class="flex items-center">
                        <button class="btn-icon text-green-500 add-payment-btn" title="Registrar Pago"><i class="fas fa-dollar-sign"></i></button>
                        <a href="#" class="btn-icon edit-deuda-btn" title="Editar Deuda"><i class="fas fa-edit"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </template>

</div>

