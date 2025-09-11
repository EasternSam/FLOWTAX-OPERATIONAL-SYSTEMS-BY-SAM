<?php
global $action, $id;

if ($action === 'list') {
?>
    <div class="p-8">
        <header class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Pagos y Cheques</h1>
            </div>
             <a href="#" data-spa-link data-view="transacciones" data-action="create" class="btn btn-primary"><i class="fas fa-plus mr-2"></i>Nueva Transacción</a>
        </header>
        <div class="card">
             <p class="text-center p-8 text-gray-500">Función en desarrollo.</p>
        </div>
    </div>
<?php
} else {
    // Formulario
?>
    <div class="p-8">
         <div class="card max-w-2xl mx-auto text-center">
             <h1 class="text-2xl font-bold text-gray-800 mb-4">Función en Desarrollo</h1>
             <p class="text-gray-600 mb-6">El formulario para registrar transacciones está en construcción.</p>
             <a href="#" data-spa-link data-view="transacciones" class="btn btn-primary">Volver a Transacciones</a>
         </div>
    </div>
<?php
}
