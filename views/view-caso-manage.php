<?php
// Esta es la vista para gestionar un caso individual (impuestos, inmigración, etc.)
// Se rellena dinámicamente con JS a través de la función App.loadCasoManage()

$post_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
?>

<div class="p-4 sm:p-6" data-post-id="<?php echo $post_id; ?>" id="caso-manage-view">
    <!-- Header: se llenará con JS -->
    <header class="flex flex-wrap justify-between items-center gap-4 mb-6">
        <div>
            <h1 id="caso-title-header" class="text-2xl font-bold text-slate-800">Cargando...</h1>
            <p id="caso-subtitle-header" class="text-slate-500 mt-1 text-sm"></p>
        </div>
        <div class="flex items-center space-x-2">
            <a href="#" id="back-to-list-btn" data-spa-link data-view="dashboard" class="btn btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i>Volver
            </a>
            <a href="#" id="edit-caso-btn" data-spa-link data-view="dashboard" data-action="edit" data-id="<?php echo $post_id; ?>" class="btn btn-primary">
                <i class="fas fa-edit mr-2"></i>Editar Caso
            </a>
        </div>
    </header>

    <div id="caso-content-area" class="opacity-0 transition-opacity duration-300">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Columna Principal -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Documentos Adjuntos -->
                <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200/80">
                    <h2 class="text-lg font-semibold text-slate-800 mb-4 border-b pb-3">Documentos Adjuntos</h2>
                    <div id="documentos-lista" class="space-y-2 mb-4">
                        <!-- Los documentos se listarán aquí -->
                        <p class="text-center text-slate-500 py-4">Cargando documentos...</p>
                    </div>
                    <form id="upload-doc-form">
                        <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                        <input type="hidden" name="action" value="flowtax_upload_document">
                        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('flowtax_ajax_nonce'); ?>">
                        <div class="flex items-center justify-center w-full">
                            <label for="document_upload" class="flex flex-col items-center justify-center w-full h-32 border-2 border-slate-300 border-dashed rounded-lg cursor-pointer bg-slate-50 hover:bg-slate-100 transition-colors">
                                <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                    <i class="fas fa-cloud-upload-alt fa-2x text-slate-400 mb-3"></i>
                                    <p class="mb-2 text-sm text-slate-500"><span class="font-semibold">Haz clic para subir</span> o arrastra y suelta</p>
                                    <p class="text-xs text-slate-400">PDF, DOCX, XLSX, JPG, PNG</p>
                                </div>
                                <input id="document_upload" name="document_upload" type="file" class="hidden" />
                            </label>
                        </div> 
                        <div id="upload-feedback" class="text-sm mt-2"></div>
                    </form>
                </div>

                <!-- Historial y Notas Internas -->
                 <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200/80">
                    <h2 class="text-lg font-semibold text-slate-800 mb-4 border-b pb-3">Historial y Notas Internas</h2>
                    <div id="notas-historial-lista" class="space-y-4 max-h-96 overflow-y-auto mb-4 pr-2">
                        <!-- Las notas se listarán aquí -->
                         <p class="text-center text-slate-500 py-4">Cargando historial...</p>
                    </div>
                     <form id="add-note-form" class="mt-4">
                        <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                        <textarea name="note_content" class="w-full text-sm" rows="3" placeholder="Escribe una nueva nota o actualización... (presiona Enter para guardar)" required></textarea>
                        <button type="submit" class="btn btn-primary mt-2 w-full sm:w-auto">Añadir Nota</button>
                    </form>
                </div>
            </div>

            <!-- Columna de Información -->
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200/80">
                    <h2 class="text-lg font-semibold text-slate-800 mb-4 border-b pb-3">Información del Cliente</h2>
                    <div id="info-cliente" class="mt-4 space-y-3 text-sm">Cargando...</div>
                </div>
                <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200/80">
                    <h2 class="text-lg font-semibold text-slate-800 mb-4 border-b pb-3">Detalles del Caso</h2>
                    <div id="info-caso" class="mt-4 space-y-3 text-sm">Cargando...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Plantillas para JS -->
<template id="document-item-template">
    <div class="document-item flex items-center justify-between p-3 rounded-md hover:bg-slate-100 transition-colors duration-200">
        <div class="flex items-center truncate min-w-0">
            <i class="document-icon fas fa-file-alt text-slate-500 fa-lg w-8 text-center"></i>
            <span class="document-name font-medium text-slate-700 truncate ml-2 text-sm"></span>
        </div>
        <div class="flex items-center space-x-1 sm:space-x-2 flex-shrink-0 ml-2">
            <button class="view-doc-btn h-8 w-8 rounded-md text-slate-500 hover:bg-slate-200 hover:text-blue-600 flex items-center justify-center transition-colors" title="Ver Documento">
                <i class="fas fa-eye"></i>
            </button>
            <a href="#" class="download-doc-btn h-8 w-8 rounded-md text-slate-500 hover:bg-slate-200 hover:text-green-600 flex items-center justify-center transition-colors" title="Descargar" download>
                <i class="fas fa-download"></i>
            </a>
            <button class="delete-doc-btn h-8 w-8 rounded-md text-slate-500 hover:bg-red-100 hover:text-red-600 flex items-center justify-center transition-colors" title="Eliminar">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </div>
</template>

<template id="note-item-template">
    <div class="note-item p-3 rounded-md bg-slate-50 border border-slate-200">
        <p class="text-sm text-slate-700 whitespace-pre-wrap"></p>
        <div class="flex justify-between items-center mt-2 pt-2 border-t border-slate-200">
            <p class="text-xs text-slate-500"><strong class="font-semibold"></strong></p>
            <p class="text-xs text-slate-400"></p>
        </div>
    </div>
</template>

