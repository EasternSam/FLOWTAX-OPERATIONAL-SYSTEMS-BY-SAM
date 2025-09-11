<?php
global $action, $id;

if ($action === 'list') {
    // Para simplificar, esta vista combinará visualización de nóminas y empleados
    $nominas_query = new WP_Query(['post_type' => 'nomina', 'posts_per_page' => 10]);
    $empleados_query = new WP_Query(['post_type' => 'empleado', 'posts_per_page' => 10]);
?>
<div class="p-8">
    <header class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Gestión de Payroll</h1>
            <p class="text-gray-500">Administra nóminas y empleados.</p>
        </div>
        <div>
            <a href="#" data-spa-link data-view="payroll" data-action="create_empleado" class="btn btn-secondary mr-2"><i class="fas fa-user-plus mr-2"></i>Nuevo Empleado</a>
            <a href="#" data-spa-link data-view="payroll" data-action="create_nomina" class="btn btn-primary"><i class="fas fa-plus mr-2"></i>Nueva Nómina</a>
        </div>
    </header>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Últimas Nóminas -->
        <div class="card">
            <h2 class="card-title mb-4">Últimas Nóminas</h2>
             <p class="text-center p-8 text-gray-500">Función en desarrollo.</p>
        </div>
        <!-- Empleados Recientes -->
        <div class="card">
            <h2 class="card-title mb-4">Empleados Recientes</h2>
            <p class="text-center p-8 text-gray-500">Función en desarrollo.</p>
        </div>
    </div>
</div>

<?php
} else {
    // Formulario unificado o lógica para mostrar el formulario correcto
?>
    <div class="p-8">
         <div class="card max-w-2xl mx-auto text-center">
             <h1 class="text-2xl font-bold text-gray-800 mb-4">Función en Desarrollo</h1>
             <p class="text-gray-600 mb-6">El formulario para crear/editar registros de payroll está actualmente en construcción.</p>
             <a href="#" data-spa-link data-view="payroll" class="btn btn-primary">Volver a Payroll</a>
         </div>
    </div>
<?php
}
