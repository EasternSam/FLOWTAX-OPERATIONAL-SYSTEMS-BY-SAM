<?php
// Esta vista es para el panel de supervisión en tiempo real.
// El contenido se cargará y actualizará dinámicamente con JavaScript.
?>
<div class="p-4 sm:p-6">
    <header class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800 flex items-center">
            Panel de Supervisión 
            <span class="flex items-center ml-3 text-sm font-semibold text-green-600 bg-green-100 px-2 py-0.5 rounded-full">
                <span class="relative flex h-2 w-2 mr-2">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                </span>
                EN VIVO
            </span>
        </h1>
        <p class="text-slate-500 mt-1 text-sm">Actividad reciente de todos los usuarios, actualizada automáticamente.</p>
    </header>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200/80">
        <div id="live-activity-feed" class="divide-y divide-slate-100">
            <div class="p-8 text-center text-slate-500">
                <i class="fas fa-spinner fa-spin fa-lg mr-2"></i>
                Cargando actividad inicial...
            </div>
        </div>
    </div>
</div>
