document.addEventListener('DOMContentLoaded', function () {
    const appRoot = document.getElementById('flowtax-app-root');
    const containerWrapper = document.getElementById('flowtax-container-wrapper');
    const notificationArea = document.getElementById('notification-area');
    let currentView = null;
    let searchDebounce = null;

    const Debug = {
        enabled: window.flowtax_ajax?.debug_mode || false,
        log(message, context = 'General') {
            if (!this.enabled) return;
            console.log(`[FlowTax Debug | ${context}]`, message);
        },
        renderBackendLogs(logs) {
            if (!this.enabled || !logs) return;
            console.groupCollapsed(`[FlowTax Debug | Backend Logs]`);
            logs.forEach(log => console.log(`[${log.timestamp} - ${log.context}]`, log.message));
            console.groupEnd();
        }
    };

    const App = {
        init() {
            Debug.log('App inicializada.', 'System');
            if (containerWrapper) {
                this.initMobileMenu();
                this.initDocViewer();
            } else {
                 Debug.log('Contenedor principal no encontrado, saltando inicialización de componentes UI.', 'System');
            }
            window.addEventListener('popstate', this.handlePopState.bind(this));
            document.body.addEventListener('click', this.handleGlobalClick.bind(this));
            if (appRoot) {
                appRoot.addEventListener('submit', this.handleFormSubmit.bind(this));
                appRoot.addEventListener('input', this.handleDebouncedInput.bind(this));
                appRoot.addEventListener('change', this.handleFileInput.bind(this));
                this.loadViewFromUrl();
            }
        },
        
        initDocViewer() {
            const modal = document.getElementById('doc-viewer-modal');
            const closeBtn = document.getElementById('close-viewer-btn');
            const titleEl = document.getElementById('viewer-title');
            const iframe = modal.querySelector('iframe');

            if (!modal || !closeBtn || !iframe) return;

            const closeViewer = () => modal.classList.add('hidden');
            
            appRoot.addEventListener('click', function(event) {
                const viewBtn = event.target.closest('.view-doc-btn');
                if (viewBtn) {
                    const url = viewBtn.dataset.url;
                    const name = viewBtn.dataset.name;
                    const googleViewerUrl = `https://docs.google.com/gview?url=${encodeURIComponent(url)}&embedded=true`;
                    
                    titleEl.textContent = name;
                    iframe.src = googleViewerUrl;
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                }
            });

            closeBtn.addEventListener('click', closeViewer);
            modal.addEventListener('click', function(event) {
                if(event.target === modal) closeViewer();
            });
        },

        initMobileMenu() {
            const sidebar = document.getElementById('spa-sidebar');
            const openBtn = document.getElementById('open-mobile-menu');
            const closeBtn = document.getElementById('close-mobile-menu');
            const overlay = document.getElementById('mobile-menu-overlay');

            if (!sidebar || !openBtn || !closeBtn || !overlay) {
                Debug.log('Mobile menu elements not found, skipping initialization.', 'MobileMenu');
                return;
            }

            const openMenu = () => {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
            };
            const closeMenu = () => {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
            };

            openBtn.addEventListener('click', openMenu);
            closeBtn.addEventListener('click', closeMenu);
            overlay.addEventListener('click', closeMenu);
            
            sidebar.addEventListener('click', function(event) {
                if (event.target.closest('a')) {
                    closeMenu();
                }
            });
        },

        updateActiveSidebarLink(view) {
             document.querySelectorAll('#spa-sidebar .sidebar-link').forEach(link => {
                link.classList.remove('active');
                if (link.dataset.view === view) {
                    link.classList.add('active');
                }
            });
        },

        showNotification(message, type = 'success') {
            const colors = { success: 'bg-green-500', error: 'bg-red-500', warning: 'bg-yellow-500' };
            const icon = { success: 'fa-check-circle', error: 'fa-exclamation-circle', warning: 'fa-exclamation-triangle' };
            const notification = document.createElement('div');
            notification.className = `notification text-white p-3 rounded-lg shadow-lg mb-2 flex items-center text-sm ${colors[type]} transform transition-all duration-300 translate-x-full`;
            notification.innerHTML = `<i class="fas ${icon[type]} mr-3"></i> <span>${message}</span>`;
            
            notificationArea.appendChild(notification);
            
            // Animate in
            setTimeout(() => notification.classList.remove('translate-x-full'), 10);
            
            // Animate out and remove
            setTimeout(() => {
                notification.classList.add('opacity-0');
                setTimeout(() => notification.remove(), 500);
            }, 5000);
        },
        
        showLoader() {
            appRoot.innerHTML = `<div class="flex justify-center items-center h-full"><i class="fas fa-spinner fa-spin fa-2x text-slate-400"></i></div>`;
        },

        async loadView(view, action = 'list', id = 0, pushState = true) {
            this.showLoader();
            Debug.log(`Cargando vista: view=${view}, action=${action}, id=${id}`, 'Navigation');
            
            const url = new URL(flowtax_ajax.home_url);
            let path = view;
            if (action !== 'list' && action !== '') path += `/${action}`;
            if (id > 0) path += `/${id}`;
            url.pathname += path;

            if (pushState) {
                history.pushState({ view, action, id }, '', url.toString());
            }
            
            currentView = view;
            this.updateActiveSidebarLink(view);

            try {
                const params = new URLSearchParams({ action: 'flowtax_get_view', nonce: flowtax_ajax.nonce, view, flowtax_action: action, id });
                const response = await fetch(flowtax_ajax.ajax_url, { method: 'POST', body: params });
                const result = await response.json();
                 Debug.renderBackendLogs(result.data.debug_logs);

                if (result.success) {
                    appRoot.innerHTML = result.data.html;
                    // Efecto de fade-in para la vista cargada
                    const newViewContent = appRoot.children[0];
                    if (newViewContent) {
                        newViewContent.classList.add('animate-fade-in');
                    }
                    
                    // Cargar datos específicos si es una vista de gestión o perfil
                    if (action === 'perfil') this.loadClientePerfil(id);
                    if (action === 'manage') this.loadCasoManage(id);

                } else {
                    throw new Error(result.data.message || 'Error desconocido.');
                }
            } catch (error) {
                Debug.log(error, 'Error LoadView');
                this.showNotification(`Error al cargar la vista: ${error.message}`, 'error');
                appRoot.innerHTML = `<div class="text-center text-red-500 p-8">Error al cargar contenido.</div>`;
            }
        },
        
        async loadCasoManage(postId) {
            try {
                const params = new URLSearchParams({ action: 'flowtax_get_caso_details', nonce: flowtax_ajax.nonce, post_id: postId });
                const response = await fetch(flowtax_ajax.ajax_url, { method: 'POST', body: params });
                const result = await response.json();
                
                if (result.success) {
                    this.renderCasoManage(result.data);
                } else {
                    throw new Error(result.data.message || 'No se pudo cargar el caso.');
                }
            } catch (error) {
                 Debug.log(error, 'Error LoadCasoManage');
                 const errorMsg = `Error al cargar los detalles del caso. Por favor, inténtalo de nuevo.`;
                 this.showNotification(errorMsg, 'error');
                 const contentArea = appRoot.querySelector('#caso-content-area');
                 if (contentArea) {
                    contentArea.innerHTML = `<div class="p-8 text-center text-red-600 bg-red-50 rounded-lg">${errorMsg}</div>`;
                    contentArea.classList.remove('opacity-0');
                 }
            }
        },

        renderCasoManage(data) {
            const { caso, cliente } = data;
            
            document.getElementById('caso-title-header').textContent = caso.title;
            document.getElementById('caso-subtitle-header').textContent = `${caso.singular_name} • Creado el ${caso.fecha}`;
            
            const backBtn = document.getElementById('back-to-list-btn');
            backBtn.dataset.view = caso.view_slug;

            const editBtn = document.getElementById('edit-caso-btn');
            editBtn.dataset.view = caso.view_slug;

            // Render cliente info
            const clienteInfoContainer = document.getElementById('info-cliente');
            if (cliente) {
                clienteInfoContainer.innerHTML = `
                    <p><strong class="font-medium text-slate-500 w-24 inline-block">Nombre:</strong> <a href="#" data-spa-link data-view="clientes" data-action="perfil" data-id="${cliente.ID}" class="text-blue-600 hover:underline font-semibold">${cliente.title}</a></p>
                    <p><strong class="font-medium text-slate-500 w-24 inline-block">Email:</strong> ${cliente.email || 'N/A'}</p>
                    <p><strong class="font-medium text-slate-500 w-24 inline-block">Teléfono:</strong> ${cliente.telefono || 'N/A'}</p>
                `;
            } else {
                 clienteInfoContainer.innerHTML = '<p class="text-slate-500">No hay cliente asociado.</p>';
            }

            // Render caso info
            document.getElementById('info-caso').innerHTML = `
                <p><strong class="font-medium text-slate-500 w-28 inline-block">Estado Actual:</strong> <span class="px-2 py-1 text-xs font-semibold rounded-full ${caso.estado_color}">${caso.estado}</span></p>
                <p><strong class="font-medium text-slate-500 w-28 inline-block">Año Fiscal:</strong> ${caso.ano_fiscal || 'N/A'}</p>
                <p><strong class="font-medium text-slate-500 w-28 inline-block">Idiomas:</strong> ${caso.idioma_origen ? `${caso.idioma_origen} → ${caso.idioma_destino}` : 'N/A'}</p>
            `;
            
            // Render documents
            const docsContainer = document.getElementById('documentos-lista');
            const docTemplate = document.getElementById('document-item-template');
            docsContainer.innerHTML = '';
            
            let documents = [];
            if (caso.meta._documentos_adjuntos && caso.meta._documentos_adjuntos[0]) {
                 try {
                    const parsedDocs = JSON.parse(caso.meta._documentos_adjuntos[0]);
                    if (Array.isArray(parsedDocs)) {
                        documents = parsedDocs;
                    }
                } catch(e) {
                    Debug.log('Error parsing documents JSON:', e);
                }
            }

            if (documents.length > 0) {
                documents.forEach(doc => {
                    const clone = docTemplate.content.cloneNode(true);
                    const docNameEl = clone.querySelector('.document-name');
                    docNameEl.textContent = doc.name;

                    const iconEl = clone.querySelector('.document-icon');
                    const iconMap = { 'pdf': 'fa-file-pdf', 'doc': 'fa-file-word', 'docx': 'fa-file-word', 'xls': 'fa-file-excel', 'xlsx': 'fa-file-excel', 'jpg': 'fa-file-image', 'jpeg': 'fa-file-image', 'png': 'fa-file-image' };
                    const extension = doc.name.split('.').pop().toLowerCase();
                    iconEl.classList.remove('fa-file-alt');
                    iconEl.classList.add(iconMap[extension] || 'fa-file-alt');

                    const viewBtn = clone.querySelector('.view-doc-btn');
                    viewBtn.dataset.url = doc.url;
                    viewBtn.dataset.name = doc.name;

                    const downloadBtn = clone.querySelector('.download-doc-btn');
                    downloadBtn.href = doc.url;
                    downloadBtn.setAttribute('download', doc.name);

                    const deleteBtn = clone.querySelector('.delete-doc-btn');
                    deleteBtn.dataset.attachmentId = doc.id;
                    deleteBtn.dataset.postId = caso.ID;
                    
                    docsContainer.appendChild(clone);
                });
            } else {
                docsContainer.innerHTML = '<p class="text-center text-slate-500 py-4 text-sm">No hay documentos adjuntos.</p>';
            }
            
            // Render notes
            const notesContainer = document.getElementById('notas-historial-lista');
            const noteTemplate = document.getElementById('note-item-template');
            notesContainer.innerHTML = '';

            let notes = [];
            if(caso.meta._historial_notas && caso.meta._historial_notas[0]) {
                try {
                    const parsedNotes = JSON.parse(caso.meta._historial_notas[0]);
                    if(Array.isArray(parsedNotes)) {
                        notes = parsedNotes;
                    }
                } catch(e) {
                     Debug.log('Error parsing notes JSON:', e);
                }
            }

            if (notes.length > 0) {
                notes.forEach(note => {
                    const clone = noteTemplate.content.cloneNode(true);
                    clone.querySelector('p:first-child').textContent = note.content;
                    clone.querySelector('strong').textContent = note.author;
                    clone.querySelector('.text-slate-400').textContent = note.date;
                    notesContainer.appendChild(clone);
                });
            } else {
                 notesContainer.innerHTML = '<p class="text-center text-slate-500 py-4 text-sm">No hay notas en el historial.</p>';
            }
            
            // Mostrar contenido
            document.getElementById('caso-content-area').classList.remove('opacity-0');
        },

        async loadClientePerfil(clienteId) {
            try {
                const params = new URLSearchParams({ action: 'flowtax_get_cliente_perfil', nonce: flowtax_ajax.nonce, cliente_id: clienteId });
                const response = await fetch(flowtax_ajax.ajax_url, { method: 'POST', body: params });
                const result = await response.json();
                
                if (result.success) {
                    this.renderClientePerfil(result.data);
                } else {
                    throw new Error(result.data.message || 'No se pudo cargar el perfil.');
                }
            } catch (error) {
                 Debug.log(error, 'Error LoadClientePerfil');
                 this.showNotification(error.message, 'error');
            }
        },

        renderClientePerfil(data) {
            const { cliente, casos } = data;
            document.getElementById('cliente-nombre-header').textContent = cliente.title;
            
            const getMeta = (key) => cliente.meta[`_${key}`] ? cliente.meta[`_${key}`][0] : 'N/A';
            
            document.getElementById('info-contacto').innerHTML = `
                <p><strong class="font-medium text-slate-500 w-24 inline-block">Email:</strong> ${getMeta('email')}</p>
                <p><strong class="font-medium text-slate-500 w-24 inline-block">Teléfono:</strong> ${getMeta('telefono')}</p>
                <p><strong class="font-medium text-slate-500 w-24 inline-block">Tax ID:</strong> ${getMeta('tax_id')}</p>
            `;
            document.getElementById('info-direccion').innerHTML = `
                <p>${getMeta('direccion')}</p>
                <p>${getMeta('ciudad')}, ${getMeta('estado_provincia')} ${getMeta('codigo_postal')}</p>
            `;

            const casosContainer = document.getElementById('casos-asociados-lista');
            const template = document.getElementById('caso-item-template');
            casosContainer.innerHTML = '';
            if (casos.length > 0) {
                casos.forEach(caso => {
                    const clone = template.content.cloneNode(true);
                    const link = clone.querySelector('a');
                    link.dataset.spaLink = '';
                    link.dataset.view = caso.view_slug;
                    link.dataset.action = 'manage'; // Cambiado de 'edit' a 'manage'
                    link.dataset.id = caso.ID;
                    
                    clone.querySelector('.font-semibold').textContent = caso.title;
                    clone.querySelector('.text-xs.text-slate-500').textContent = caso.singular_name;
                    clone.querySelector('.text-xs.text-slate-400').textContent = caso.fecha;
                    const statusBadge = clone.querySelector('.status-badge');
                    statusBadge.textContent = caso.estado;
                    statusBadge.className += ` ${caso.estado_color}`;
                    
                    casosContainer.appendChild(clone);
                });
            } else {
                casosContainer.innerHTML = '<p class="text-center text-slate-500 py-4">No hay casos asociados a este cliente.</p>';
            }
            
            document.getElementById('perfil-content-area').classList.remove('opacity-0');
        },

        loadViewFromUrl() {
            if(!appRoot) return;
            const path = window.location.pathname.replace(new URL(flowtax_ajax.home_url).pathname, '').split('/').filter(p => p);
            const view = path[0] || 'dashboard';
            const action = path[1] || 'list';
            const id = path[2] || 0;
            this.loadView(view, action, id, false);
        },

        handlePopState(event) {
            if (event.state) {
                this.loadView(event.state.view, event.state.action, event.state.id, false);
            } else {
                this.loadViewFromUrl();
            }
        },

        handleGlobalClick(event) {
            const link = event.target.closest('[data-spa-link]');
            if (link) {
                event.preventDefault();
                const { view, action = 'list', id = 0 } = link.dataset;
                this.loadView(view, action, id);
                return;
            }

            const deleteButton = event.target.closest('button[data-delete-id]');
            if(deleteButton){
                event.preventDefault();
                this.handleDeletePost(deleteButton);
                return;
            }

            const deleteDocButton = event.target.closest('button.delete-doc-btn');
            if(deleteDocButton) {
                event.preventDefault();
                this.handleDeleteDocument(deleteDocButton);
                return;
            }
        },
        
        async handleFormSubmit(event) {
            const form = event.target;
            event.preventDefault();

            if (form.id === 'add-note-form') {
                this.handleAddNote(form);
            } else if (form.hasAttribute('data-spa-form')) {
                this.handleSavePost(form);
            }
        },
        
        async handleAddNote(form) {
            const submitButton = form.querySelector('button[type="submit"]');
            const textarea = form.querySelector('textarea');
            const originalButtonText = submitButton.innerHTML;
            submitButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i>`;
            submitButton.disabled = true;
            textarea.disabled = true;

            try {
                const formData = new FormData(form);
                formData.append('action', 'flowtax_add_note');
                formData.append('nonce', flowtax_ajax.nonce);

                const response = await fetch(flowtax_ajax.ajax_url, { method: 'POST', body: formData });
                const result = await response.json();
                Debug.renderBackendLogs(result.data.debug_logs);
                
                if (result.success) {
                    this.showNotification('Nota añadida con éxito', 'success');
                    this.loadCasoManage(formData.get('post_id'));
                } else {
                    throw new Error(result.data.message || 'Error al añadir nota.');
                }
            } catch (error) {
                 this.showNotification(error.message, 'error');
            } finally {
                submitButton.innerHTML = originalButtonText;
                submitButton.disabled = false;
                textarea.disabled = false;
                textarea.value = '';
            }
        },

        async handleSavePost(form) {
            const submitButton = form.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            submitButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Guardando...`;
            submitButton.disabled = true;

            form.querySelectorAll('.error-message').forEach(el => el.remove());
            form.querySelectorAll('.border-red-500').forEach(el => el.classList.remove('border-red-500'));

            const formData = new FormData(form);
            formData.append('action', 'flowtax_save_form');
            formData.append('nonce', flowtax_ajax.nonce);
            
            const postTypeSelect = form.querySelector('select[name="post_type"]');
            if (postTypeSelect && postTypeSelect.disabled) {
                postTypeSelect.disabled = false;
                formData.set('post_type', postTypeSelect.value);
                 postTypeSelect.disabled = true;
            }

            try {
                const response = await fetch(flowtax_ajax.ajax_url, { method: 'POST', body: formData });
                const result = await response.json();
                Debug.renderBackendLogs(result.data.debug_logs);
                
                if (result.success) {
                    this.showNotification(result.data.message, 'success');
                    this.loadView(result.data.redirect_view, 'manage', result.data.post_id);
                } else {
                     this.showNotification(result.data.message || 'Error al guardar.', 'error');
                     if (result.data.errors) {
                        Object.keys(result.data.errors).forEach(key => {
                            const field = form.querySelector(`[name="${key}"]`);
                            if (field) {
                                field.classList.add('border-red-500');
                                const errorEl = document.createElement('p');
                                errorEl.className = 'text-red-500 text-xs mt-1 error-message';
                                errorEl.textContent = result.data.errors[key];
                                field.parentNode.appendChild(errorEl);
                            }
                        });
                     }
                }
            } catch (error) {
                Debug.log(error, 'Error FormSubmit');
                this.showNotification('Error de conexión al guardar.', 'error');
            } finally {
                submitButton.innerHTML = originalButtonText;
                submitButton.disabled = false;
            }
        },

        handleDebouncedInput(event) {
            const searchInput = event.target.closest('input[data-search-input]');
            if (searchInput) {
                clearTimeout(searchDebounce);
                searchDebounce = setTimeout(() => {
                    this.handleSearch(searchInput);
                }, 300); // Debounce de 300ms
            }
        },
        
        async handleSearch(searchInput) {
            const searchTerm = searchInput.value;
            const postType = searchInput.dataset.postType;
            const tableBody = document.querySelector('#data-table-body');
            
            if (searchTerm.length > 0 && searchTerm.length < 3) return;
            
            tableBody.innerHTML = `<tr><td colspan="5" class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Buscando...</td></tr>`;

            try {
                const params = new URLSearchParams({ action: 'flowtax_get_search_results', nonce: flowtax_ajax.nonce, search_term: searchTerm, post_type: postType });
                const response = await fetch(flowtax_ajax.ajax_url, { method: 'POST', body: params });
                const result = await response.json();
                Debug.renderBackendLogs(result.data.debug_logs);
                if (result.success) {
                   this.renderTableRows(result.data);
                } else {
                   throw new Error('No se pudo buscar.');
                }
            } catch(error) {
                this.showNotification('Error en la búsqueda.', 'error');
                this.loadView(currentView);
            }
        },

        async handleFileInput(event) {
            if (event.target.id === 'document_upload') {
                const form = event.target.closest('form');
                const file = event.target.files[0];
                if (!file) return;

                const feedbackEl = document.getElementById('upload-feedback');
                feedbackEl.innerHTML = `<span class="text-slate-600"><i class="fas fa-spinner fa-spin mr-2"></i>Subiendo ${file.name}...</span>`;

                try {
                    const formData = new FormData(form);
                    const response = await fetch(flowtax_ajax.ajax_url, { method: 'POST', body: formData });
                    const result = await response.json();
                    Debug.renderBackendLogs(result.data.debug_logs);

                    if (result.success) {
                        this.showNotification('Documento subido con éxito.', 'success');
                        this.loadCasoManage(form.querySelector('[name="post_id"]').value);
                        feedbackEl.innerHTML = `<span class="text-green-600">¡Éxito! Puedes subir otro archivo.</span>`;
                    } else {
                        throw new Error(result.data.message);
                    }
                } catch (error) {
                    this.showNotification(error.message, 'error');
                    feedbackEl.innerHTML = `<span class="text-red-500">Error: ${error.message}</span>`;
                } finally {
                    form.reset();
                }
            }
        },
        
        async handleDeletePost(deleteButton) {
            const postId = deleteButton.dataset.deleteId;
            if (confirm('¿Estás seguro de que quieres eliminar este registro? Esta acción no se puede deshacer.')) {
                try {
                    const params = new URLSearchParams({ action: 'flowtax_delete_post', nonce: flowtax_ajax.nonce, post_id: postId });
                    const response = await fetch(flowtax_ajax.ajax_url, { method: 'POST', body: params });
                    const result = await response.json();
                    Debug.renderBackendLogs(result.data.debug_logs);
                    if (result.success) {
                        this.showNotification(result.data.message, 'success');
                        this.loadView(currentView, 'list', 0, false);
                    } else {
                        throw new Error(result.data.message);
                    }
                } catch (error) {
                    this.showNotification(`Error al eliminar: ${error.message}`, 'error');
                }
            }
        },

        async handleDeleteDocument(button) {
            const { postId, attachmentId } = button.dataset;
            if (confirm('¿Estás seguro de que quieres eliminar este documento?')) {
                 try {
                    const params = new URLSearchParams({ 
                        action: 'flowtax_delete_document', 
                        nonce: flowtax_ajax.nonce, 
                        post_id: postId,
                        attachment_id: attachmentId 
                    });
                    const response = await fetch(flowtax_ajax.ajax_url, { method: 'POST', body: params });
                    const result = await response.json();
                    Debug.renderBackendLogs(result.data.debug_logs);

                    if (result.success) {
                        this.showNotification('Documento eliminado', 'success');
                        this.loadCasoManage(postId);
                    } else {
                        throw new Error(result.data.message);
                    }
                } catch (error) {
                    this.showNotification(`Error al eliminar: ${error.message}`, 'error');
                }
            }
        },
        
        renderTableRows(data) {
            const tableBody = document.querySelector('#data-table-body');
            if (!tableBody) return;
            const colCount = tableBody.closest('table').querySelector('th').parentElement.childElementCount;

            if (!data || data.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="${colCount}" class="text-center py-8 text-slate-500">No se encontraron resultados.</td></tr>`;
                return;
            }

            let rowsHtml = '';
            data.forEach(item => {
                let row = `<tr class="bg-white">`;
                // Renderizado dinámico basado en la vista actual
                switch(currentView) {
                    case 'clientes':
                         row += `<td data-label="Nombre"><a href="#" data-spa-link data-view="clientes" data-action="perfil" data-id="${item.ID}" class="font-semibold text-blue-600 hover:underline">${item.title}</a></td>`;
                         row += `<td data-label="Email">${item.email || ''}</td>`;
                         row += `<td data-label="Teléfono">${item.telefono || ''}</td>`;
                         row += `<td data-label="Fecha">${item.fecha}</td>`;
                        break;
                    case 'impuestos':
                        row += `<td data-label="Caso"><a href="#" data-spa-link data-view="impuestos" data-action="manage" data-id="${item.ID}" class="font-semibold text-blue-600 hover:underline">${item.title}</a><p class="text-xs text-slate-500 sm:hidden">${item.cliente_nombre}</p></td>`;
                        row += `<td data-label="Cliente" class="hidden sm:table-cell">${item.cliente_nombre}</td>`;
                        row += `<td data-label="Año Fiscal">${item.ano_fiscal || 'N/A'}</td>`;
                        row += `<td data-label="Estado"><span class="px-2 py-0.5 text-xs font-semibold rounded-full ${item.estado_color}">${item.estado}</span></td>`;
                        break;
                    case 'inmigracion':
                        row += `<td data-label="Tipo de Caso"><a href="#" data-spa-link data-view="inmigracion" data-action="manage" data-id="${item.ID}" class="font-semibold text-blue-600 hover:underline">${item.singular_name}</a><p class="text-xs text-slate-500 sm:hidden">${item.cliente_nombre}</p></td>`;
                        row += `<td data-label="Cliente" class="hidden sm:table-cell">${item.cliente_nombre}</td>`;
                        row += `<td data-label="Estado"><span class="px-2 py-0.5 text-xs font-semibold rounded-full ${item.estado_color}">${item.estado}</span></td>`;
                        row += `<td data-label="Fecha">${item.fecha}</td>`;
                        break;
                    case 'traducciones':
                        row += `<td data-label="Proyecto"><a href="#" data-spa-link data-view="traducciones" data-action="manage" data-id="${item.ID}" class="font-semibold text-blue-600 hover:underline">${item.title}</a><p class="text-xs text-slate-500 sm:hidden">${item.cliente_nombre}</p></td>`;
                        row += `<td data-label="Cliente" class="hidden sm:table-cell">${item.cliente_nombre}</td>`;
                        row += `<td data-label="Idiomas">${item.idioma_origen || ''} → ${item.idioma_destino || ''}</td>`;
                        row += `<td data-label="Estado"><span class="px-2 py-0.5 text-xs font-semibold rounded-full ${item.estado_color}">${item.estado}</span></td>`;
                        break;
                }
                row += `
                    <td data-label="Acciones" class="text-right space-x-1">
                        <a href="#" data-spa-link data-view="${currentView}" data-action="manage" data-id="${item.ID}" class="h-8 w-8 rounded-md text-slate-500 hover:bg-slate-200 hover:text-blue-600 flex items-center justify-center transition-colors inline-flex" title="Gestionar"><i class="fas fa-tasks"></i></a>
                        <a href="#" data-spa-link data-view="${currentView}" data-action="edit" data-id="${item.ID}" class="h-8 w-8 rounded-md text-slate-500 hover:bg-slate-200 hover:text-blue-600 flex items-center justify-center transition-colors inline-flex" title="Editar"><i class="fas fa-edit"></i></a>
                        <button data-delete-id="${item.ID}" class="h-8 w-8 rounded-md text-slate-500 hover:bg-red-100 hover:text-red-600 flex items-center justify-center transition-colors inline-flex" title="Eliminar"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>`;
                rowsHtml += row;
            });
            tableBody.innerHTML = rowsHtml;
        }
    };

    App.init();
});

