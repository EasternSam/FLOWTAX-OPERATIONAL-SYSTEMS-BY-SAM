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
            // La inicialización de componentes UI ahora se hace después de cargar cada vista.
            if (containerWrapper) {
                this.initMobileMenu();
            }
            window.addEventListener('popstate', this.handlePopState.bind(this));
            document.body.addEventListener('click', this.handleGlobalClick.bind(this));
            
            // Los listeners de appRoot se mantienen aquí porque appRoot es persistente.
            if (appRoot) {
                appRoot.addEventListener('submit', this.handleFormSubmit.bind(this));
                appRoot.addEventListener('input', this.handleDebouncedInput.bind(this));
                appRoot.addEventListener('change', this.handleFileInput.bind(this));
                this.loadViewFromUrl();
            }
        },
        
        initDocViewer() {
            const modal = document.getElementById('doc-viewer-modal');
            if (!modal || modal.dataset.viewerInitialized) return; // Si no existe o ya está inicializado, no hacer nada.
            modal.dataset.viewerInitialized = 'true';

            const closeBtn = document.getElementById('close-viewer-btn');
            const titleEl = document.getElementById('viewer-title');
            const imageContainer = document.getElementById('image-viewer-container');
            const iframe = document.getElementById('doc-viewer-iframe');
            const imgEl = document.getElementById('image-viewer-img');
            const zoomControls = document.getElementById('image-zoom-controls');
            const zoomInBtn = zoomControls.querySelector('[data-zoom="in"]');
            const zoomOutBtn = zoomControls.querySelector('[data-zoom="out"]');
            const zoomLevelDisplay = document.getElementById('zoom-level-display');

            let currentZoom = 1;
            let isPanning = false;
            let startPos = { x: 0, y: 0 };
            let startScroll = { left: 0, top: 0 };

            const updateZoom = (newZoom) => {
                currentZoom = Math.max(0.2, Math.min(newZoom, 5));
                imgEl.style.transform = `scale(${currentZoom})`;
                zoomLevelDisplay.textContent = `${Math.round(currentZoom * 100)}%`;
                imgEl.classList.toggle('cursor-grab', currentZoom > 1);
                imgEl.classList.toggle('cursor-zoom-in', currentZoom <= 1);
            };

            const closeViewer = () => {
                iframe.src = 'about:blank';
                imgEl.src = '';
                modal.classList.add('hidden');
                updateZoom(1);
            };
            
            zoomInBtn.addEventListener('click', () => updateZoom(currentZoom + 0.2));
            zoomOutBtn.addEventListener('click', () => updateZoom(currentZoom - 0.2));

            imgEl.addEventListener('mousedown', (e) => {
                if (currentZoom <= 1) return;
                e.preventDefault();
                isPanning = true;
                imgEl.classList.add('cursor-grabbing');
                startPos = { x: e.clientX, y: e.clientY };
                startScroll = { left: imageContainer.scrollLeft, top: imageContainer.scrollTop };
            });

            window.addEventListener('mouseup', () => {
                isPanning = false;
                imgEl.classList.remove('cursor-grabbing');
            });
            
            window.addEventListener('mousemove', (e) => {
                if (!isPanning) return;
                const dx = e.clientX - startPos.x;
                const dy = e.clientY - startPos.y;
                imageContainer.scrollLeft = startScroll.left - dx;
                imageContainer.scrollTop = startScroll.top - dy;
            });

            imageContainer.addEventListener('wheel', (e) => {
                e.preventDefault();
                updateZoom(currentZoom * (e.deltaY < 0 ? 1.1 : 1 / 1.1));
            });
            
            closeBtn.addEventListener('click', closeViewer);
            modal.addEventListener('click', (event) => {
                if (event.target === modal) closeViewer();
            });
        },


        initMobileMenu() {
            const sidebar = document.getElementById('spa-sidebar');
            if (!sidebar || sidebar.dataset.menuInitialized) return;
            sidebar.dataset.menuInitialized = 'true';
            
            const openBtn = document.getElementById('open-mobile-menu');
            const closeBtn = document.getElementById('close-mobile-menu');
            const overlay = document.getElementById('mobile-menu-overlay');

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
            
            sidebar.addEventListener('click', (event) => {
                if (event.target.closest('a')) closeMenu();
            });
        },

        updateActiveSidebarLink(view) {
             document.querySelectorAll('#spa-sidebar .sidebar-link').forEach(link => {
                link.classList.toggle('active', link.dataset.view === view);
            });
        },

        showNotification(message, type = 'success') {
            const colors = { success: 'bg-green-500', error: 'bg-red-500', warning: 'bg-yellow-500' };
            const icon = { success: 'fa-check-circle', error: 'fa-exclamation-circle', warning: 'fa-exclamation-triangle' };
            const notification = document.createElement('div');
            notification.className = `notification text-white p-3 rounded-lg shadow-lg mb-2 flex items-center text-sm ${colors[type]} transform transition-all duration-300 translate-x-full`;
            notification.innerHTML = `<i class="fas ${icon[type]} mr-3"></i> <span>${message}</span>`;
            
            notificationArea.appendChild(notification);
            
            setTimeout(() => notification.classList.remove('translate-x-full'), 10);
            
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
                    
                    this.initDocViewer();

                    const newViewContent = appRoot.children[0];
                    if (newViewContent) {
                        newViewContent.classList.add('animate-fade-in');
                    }
                    
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
            
            document.getElementById('back-to-list-btn').dataset.view = caso.view_slug;
            document.getElementById('edit-caso-btn').dataset.view = caso.view_slug;

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

            document.getElementById('info-caso').innerHTML = `
                <p><strong class="font-medium text-slate-500 w-28 inline-block">Estado Actual:</strong> <span class="px-2 py-1 text-xs font-semibold rounded-full ${caso.estado_color}">${caso.estado}</span></p>
                <p><strong class="font-medium text-slate-500 w-28 inline-block">Año Fiscal:</strong> ${caso.ano_fiscal || 'N/A'}</p>
                <p><strong class="font-medium text-slate-500 w-28 inline-block">Idiomas:</strong> ${caso.idioma_origen ? `${caso.idioma_origen} → ${caso.idioma_destino}` : 'N/A'}</p>
            `;
            
            const docsContainer = document.getElementById('documentos-lista');
            const docTemplate = document.getElementById('document-item-template');
            docsContainer.innerHTML = '';
            
            let documents = [];
            if (caso.meta._documentos_adjuntos && caso.meta._documentos_adjuntos[0]) {
                 try {
                    documents = JSON.parse(caso.meta._documentos_adjuntos[0]);
                    if (!Array.isArray(documents)) documents = [];
                } catch(e) {
                    Debug.log('Error parsing documents JSON:', e);
                }
            }

            if (documents.length > 0) {
                const docFragment = document.createDocumentFragment();
                documents.forEach(doc => {
                    const clone = docTemplate.content.cloneNode(true);
                    clone.querySelector('.document-name').textContent = doc.name;

                    const iconEl = clone.querySelector('.document-icon');
                    const iconMap = { 'pdf': 'fa-file-pdf', 'doc': 'fa-file-word', 'docx': 'fa-file-word', 'xls': 'fa-file-excel', 'xlsx': 'fa-file-excel', 'jpg': 'fa-file-image', 'jpeg': 'fa-file-image', 'png': 'fa-file-image' };
                    iconEl.className = `document-icon fas ${iconMap[doc.name.split('.').pop().toLowerCase()] || 'fa-file-alt'} text-slate-500 fa-lg w-8 text-center`;

                    const viewBtn = clone.querySelector('.view-doc-btn');
                    viewBtn.dataset.url = doc.url;
                    viewBtn.dataset.name = doc.name;

                    clone.querySelector('.download-doc-btn').href = doc.url;
                    clone.querySelector('.delete-doc-btn').dataset.attachmentId = doc.id;
                    clone.querySelector('.delete-doc-btn').dataset.postId = caso.ID;
                    
                    docFragment.appendChild(clone);
                });
                docsContainer.appendChild(docFragment);
            } else {
                docsContainer.innerHTML = '<p class="text-center text-slate-500 py-4 text-sm">No hay documentos adjuntos.</p>';
            }
            
            const notesContainer = document.getElementById('notas-historial-lista');
            const noteTemplate = document.getElementById('note-item-template');
            notesContainer.innerHTML = '';

            let notes = [];
            if(caso.meta._historial_notas && caso.meta._historial_notas[0]) {
                try {
                    notes = JSON.parse(caso.meta._historial_notas[0]);
                    if(!Array.isArray(notes)) notes = [];
                } catch(e) {
                     Debug.log('Error parsing notes JSON:', e);
                }
            }

            if (notes.length > 0) {
                const noteFragment = document.createDocumentFragment();
                notes.forEach(note => {
                    const clone = noteTemplate.content.cloneNode(true);
                    clone.querySelector('p:first-child').textContent = note.content;
                    clone.querySelector('strong').textContent = note.author;
                    clone.querySelector('.text-slate-400').textContent = note.date;
                    noteFragment.appendChild(clone);
                });
                notesContainer.appendChild(noteFragment);
            } else {
                 notesContainer.innerHTML = '<p class="text-center text-slate-500 py-4 text-sm">No hay notas en el historial.</p>';
            }
            
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
            
            const getMeta = (key) => cliente.meta[`_${key}`]?.[0] || 'N/A';
            
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
                const casoFragment = document.createDocumentFragment();
                casos.forEach(caso => {
                    const clone = template.content.cloneNode(true);
                    const link = clone.querySelector('a');
                    link.dataset.view = caso.view_slug;
                    link.dataset.action = 'manage';
                    link.dataset.id = caso.ID;
                    
                    clone.querySelector('.font-semibold').textContent = caso.title;
                    clone.querySelector('.text-xs.text-slate-500').textContent = caso.singular_name;
                    clone.querySelector('.text-xs.text-slate-400').textContent = caso.fecha;
                    const statusBadge = clone.querySelector('.status-badge');
                    statusBadge.textContent = caso.estado;
                    statusBadge.className += ` ${caso.estado_color}`;
                    
                    casoFragment.appendChild(clone);
                });
                casosContainer.appendChild(casoFragment);
            } else {
                casosContainer.innerHTML = '<p class="text-center text-slate-500 py-4">No hay casos asociados a este cliente.</p>';
            }
            
            document.getElementById('perfil-content-area').classList.remove('opacity-0');
        },

        loadViewFromUrl() {
            if(!appRoot) return;
            const path = window.location.pathname.replace(new URL(flowtax_ajax.home_url).pathname, '').split('/').filter(p => p);
            const [view = 'dashboard', action = 'list', id = 0] = path;
            this.loadView(view, action, id, false);
        },

        handlePopState(event) {
            const { view, action, id } = event.state || {};
            this.loadView(view || 'dashboard', action || 'list', id || 0, false);
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
                this.handleDeletePost(deleteButton.dataset.deleteId);
                return;
            }

            const deleteDocButton = event.target.closest('button.delete-doc-btn');
            if(deleteDocButton) {
                event.preventDefault();
                const { postId, attachmentId } = deleteDocButton.dataset;
                this.handleDeleteDocument(postId, attachmentId);
                return;
            }
            
            const viewBtn = event.target.closest('.view-doc-btn');
            if (viewBtn) {
                const { url, name } = viewBtn.dataset;
                const isImage = /\.(jpe?g|png|gif|bmp|webp|svg)$/i.test(name);
                
                const modal = document.getElementById('doc-viewer-modal');
                modal.querySelector('#viewer-title').textContent = name;
                
                const imageContainer = modal.querySelector('#image-viewer-container');
                const iframe = modal.querySelector('#doc-viewer-iframe');
                const zoomControls = modal.querySelector('#image-zoom-controls');

                imageContainer.classList.toggle('hidden', !isImage);
                iframe.classList.toggle('hidden', isImage);
                zoomControls.classList.toggle('hidden', !isImage);
                zoomControls.classList.toggle('flex', isImage);

                if (isImage) {
                    modal.querySelector('#image-viewer-img').src = url;
                } else {
                    iframe.src = `https://docs.google.com/gview?url=${encodeURIComponent(url)}&embedded=true`;
                }
                
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }
        },
        
        async handleFormSubmit(event) {
            event.preventDefault();
            const form = event.target;
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

            // Primero, capturamos los datos mientras los campos están habilitados
            const formData = new FormData(form);
            formData.append('action', 'flowtax_add_note');
            formData.append('nonce', flowtax_ajax.nonce);

            // Luego, deshabilitamos los campos para prevenir envíos duplicados
            submitButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i>`;
            submitButton.disabled = true;
            textarea.disabled = true;

            try {
                const response = await fetch(flowtax_ajax.ajax_url, { method: 'POST', body: formData });
                const result = await response.json();
                Debug.renderBackendLogs(result.data.debug_logs);
                
                if (!result.success) throw new Error(result.data.message || 'Error al añadir nota.');
                
                this.showNotification('Nota añadida con éxito', 'success');
                this.loadCasoManage(formData.get('post_id'));

            } catch (error) {
                 this.showNotification(error.message, 'error');
            } finally {
                // Finalmente, reactivamos todo y limpiamos el formulario
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
                formData.set('post_type', postTypeSelect.value);
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
                        Object.entries(result.data.errors).forEach(([key, value]) => {
                            const field = form.querySelector(`[name="${key}"]`);
                            if (field) {
                                field.classList.add('border-red-500');
                                const errorEl = document.createElement('p');
                                errorEl.className = 'text-red-500 text-xs mt-1 error-message';
                                errorEl.textContent = value;
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
                searchDebounce = setTimeout(() => this.handleSearch(searchInput), 300);
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
                if (!result.success) throw new Error('No se pudo buscar.');
                this.renderTableRows(result.data);
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
                    if (!result.success) throw new Error(result.data.message);
                    
                    this.showNotification('Documento subido con éxito.', 'success');
                    this.loadCasoManage(form.querySelector('[name="post_id"]').value);
                    feedbackEl.innerHTML = `<span class="text-green-600">¡Éxito! Puedes subir otro archivo.</span>`;
                } catch (error) {
                    this.showNotification(error.message, 'error');
                    feedbackEl.innerHTML = `<span class="text-red-500">Error: ${error.message}</span>`;
                } finally {
                    form.reset();
                }
            }
        },
        
        async handleDeletePost(postId) {
            if (!confirm('¿Estás seguro de que quieres eliminar este registro? Esta acción no se puede deshacer.')) return;
            try {
                const params = new URLSearchParams({ action: 'flowtax_delete_post', nonce: flowtax_ajax.nonce, post_id: postId });
                const response = await fetch(flowtax_ajax.ajax_url, { method: 'POST', body: params });
                const result = await response.json();
                Debug.renderBackendLogs(result.data.debug_logs);
                if (!result.success) throw new Error(result.data.message);
                
                this.showNotification(result.data.message, 'success');
                this.loadView(currentView, 'list', 0, false);
            } catch (error) {
                this.showNotification(`Error al eliminar: ${error.message}`, 'error');
            }
        },

        async handleDeleteDocument(postId, attachmentId) {
            if (!confirm('¿Estás seguro de que quieres eliminar este documento?')) return;
             try {
                const params = new URLSearchParams({ 
                    action: 'flowtax_delete_document', nonce: flowtax_ajax.nonce, 
                    post_id: postId, attachment_id: attachmentId 
                });
                const response = await fetch(flowtax_ajax.ajax_url, { method: 'POST', body: params });
                const result = await response.json();
                Debug.renderBackendLogs(result.data.debug_logs);

                if (!result.success) throw new Error(result.data.message);
                
                this.showNotification('Documento eliminado', 'success');
                this.loadCasoManage(postId);
            } catch (error) {
                this.showNotification(`Error al eliminar: ${error.message}`, 'error');
            }
        },
        
        renderTableRows(data) {
            const tableBody = document.querySelector('#data-table-body');
            if (!tableBody) return;
            
            const colCount = tableBody.closest('table').querySelector('thead tr').childElementCount;
            if (!data || data.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="${colCount}" class="text-center py-8 text-slate-500">No se encontraron resultados.</td></tr>`;
                return;
            }

            const rowsHtml = data.map(item => {
                let rowContent = '';
                switch(currentView) {
                    case 'clientes':
                         rowContent = `
                            <td data-label="Nombre"><a href="#" data-spa-link data-view="clientes" data-action="perfil" data-id="${item.ID}" class="font-semibold text-blue-600 hover:underline">${item.title}</a></td>
                            <td data-label="Email">${item.email || ''}</td>
                            <td data-label="Teléfono">${item.telefono || ''}</td>
                            <td data-label="Fecha Reg.">${item.fecha}</td>`;
                        break;
                    case 'impuestos':
                        rowContent = `
                            <td data-label="Caso"><a href="#" data-spa-link data-view="impuestos" data-action="manage" data-id="${item.ID}" class="font-semibold text-blue-600 hover:underline">${item.title}</a><p class="text-sm text-slate-500">${item.cliente_nombre}</p></td>
                            <td data-label="Año Fiscal">${item.ano_fiscal || 'N/A'}</td>
                            <td data-label="Estado"><span class="px-2 py-1 text-xs font-semibold rounded-full ${item.estado_color}">${item.estado}</span></td>
                            <td data-label="Fecha">${item.fecha}</td>`;
                        break;
                    case 'inmigracion':
                        rowContent = `
                            <td data-label="Tipo de Caso"><a href="#" data-spa-link data-view="inmigracion" data-action="manage" data-id="${item.ID}" class="font-semibold text-blue-600 hover:underline">${item.title}</a><p class="text-sm text-slate-500">${item.singular_name}</p></td>
                            <td data-label="Cliente">${item.cliente_nombre}</td>
                            <td data-label="Estado"><span class="px-2 py-1 text-xs font-semibold rounded-full ${item.estado_color}">${item.estado}</span></td>
                            <td data-label="Fecha de Creación">${item.fecha}</td>`;
                        break;
                    case 'traducciones':
                        rowContent = `
                            <td data-label="Proyecto"><a href="#" data-spa-link data-view="traducciones" data-action="manage" data-id="${item.ID}" class="font-semibold text-blue-600 hover:underline">${item.title}</a><p class="text-sm text-slate-500">${item.cliente_nombre}</p></td>
                            <td data-label="Idiomas">${item.idioma_origen || ''} → ${item.idioma_destino || ''}</td>
                            <td data-label="Estado"><span class="px-2 py-1 text-xs font-semibold rounded-full ${item.estado_color}">${item.estado}</span></td>
                            <td data-label="Fecha">${item.fecha}</td>`;
                        break;
                }
                return `<tr>${rowContent}
                    <td data-label="Acciones">
                        <div class="flex justify-end items-center space-x-2">
                            <a href="#" data-spa-link data-view="${currentView}" data-action="manage" data-id="${item.ID}" class="h-8 w-8 rounded-md text-slate-500 hover:bg-slate-200 hover:text-blue-600 flex items-center justify-center transition-colors" title="Gestionar"><i class="fas fa-tasks"></i></a>
                            <a href="#" data-spa-link data-view="${currentView}" data-action="edit" data-id="${item.ID}" class="h-8 w-8 rounded-md text-slate-500 hover:bg-slate-200 hover:text-blue-600 flex items-center justify-center transition-colors" title="Editar"><i class="fas fa-edit"></i></a>
                            <button data-delete-id="${item.ID}" class="h-8 w-8 rounded-md text-slate-500 hover:bg-red-100 hover:text-red-600 flex items-center justify-center transition-colors" title="Eliminar"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>`;
            }).join('');
            tableBody.innerHTML = rowsHtml;
        }
    };

    App.init();
});

