document.addEventListener('DOMContentLoaded', function () {
    const appRoot = document.getElementById('flowtax-app-root');
    const notificationArea = document.getElementById('notification-area');
    let currentView = null;

    /**
     * MEJORA: Módulo de depuración avanzado para el frontend.
     * Crea una consola visual en la parte inferior de la pantalla si el modo debug
     * está activado en el backend.
     */
    const Debug = {
        enabled: window.flowtax_ajax?.debug_mode || false,
        panel: null,

        init() {
            if (!this.enabled) return;
            this.createPanel();
            this.log('Debugger inicializado.', 'System');
        },

        createPanel() {
            const panel = document.createElement('div');
            panel.id = 'flowtax-debug-panel';
            panel.innerHTML = `
                <div id="debug-header" style="background: #1a202c; color: white; padding: 8px 12px; font-weight: bold; cursor: pointer; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #4a5568;">
                    <span><i class="fas fa-bug"></i> FlowTax Debug Console</span>
                    <div>
                        <button id="debug-clear" style="background: #4a5568; border: none; color: white; padding: 2px 8px; border-radius: 4px; font-size: 12px; cursor: pointer; margin-right: 10px;">Limpiar</button>
                        <span id="debug-toggle" style="cursor: pointer;">[Minimizar]</span>
                    </div>
                </div>
                <div id="debug-content" style="background: #2d3748; color: #e2e8f0; height: 200px; overflow-y: auto; font-family: monospace; font-size: 12px; padding: 10px; line-height: 1.6; resize: vertical;"></div>
            `;
            panel.style.position = 'fixed';
            panel.style.bottom = '0';
            panel.style.left = '0';
            panel.style.width = '100%';
            panel.style.zIndex = '99999';
            document.body.appendChild(panel);
            this.panel = panel.querySelector('#debug-content');

            const header = panel.querySelector('#debug-header');
            const toggleBtn = panel.querySelector('#debug-toggle');
            const clearBtn = panel.querySelector('#debug-clear');

            header.addEventListener('click', (e) => {
                if (e.target !== toggleBtn && e.target !== clearBtn) {
                    this.togglePanel();
                }
            });
            toggleBtn.addEventListener('click', (e) => { e.stopPropagation(); this.togglePanel(); });
            clearBtn.addEventListener('click', (e) => { e.stopPropagation(); this.clear(); });
        },

        log(message, context = 'General') {
            if (!this.enabled) return;
            const timestamp = new Date().toLocaleTimeString();
            console.log(`[${context}]`, message);

            const entry = document.createElement('div');
            entry.style.borderBottom = '1px solid #4a5568';
            entry.style.paddingBottom = '4px';
            entry.style.marginBottom = '4px';

            let formattedMessage = typeof message === 'object' ? JSON.stringify(message, null, 2) : message;
            const contentHTML = typeof message === 'object' 
                ? `<pre style="white-space: pre-wrap; word-break: break-all; margin-top: 4px; color: #bee3f8;">${formattedMessage}</pre>`
                : `<span style="color: #a0aec0;">${formattedMessage}</span>`;

            entry.innerHTML = `<span style="color: #90cdf4;">[${timestamp} - <strong>${context}</strong>]</span> ${contentHTML}`;
            this.panel.appendChild(entry);
            this.panel.scrollTop = this.panel.scrollHeight;
        },
        
        renderBackendLogs(logs) {
            if (!this.enabled || !logs) return;
            const header = document.createElement('div');
            header.innerHTML = `--- <i class="fas fa-server"></i> Registros del Backend Recibidos ---`;
            header.style.textAlign = 'center';
            header.style.background = '#4a5568';
            header.style.padding = '4px';
            header.style.margin = '8px -10px';
            this.panel.appendChild(header);

            logs.forEach(log => {
                const entry = document.createElement('div');
                entry.style.borderBottom = '1px solid #4a5568';
                entry.style.paddingBottom = '4px';
                entry.style.marginBottom = '4px';
                let message = log.message;
                try {
                    const parsed = JSON.parse(message);
                    message = `<pre style="white-space: pre-wrap; word-break: break-all; margin-top: 4px;">${JSON.stringify(parsed, null, 2)}</pre>`;
                } catch(e) { message = `<span style="color: #a0aec0;">${message}</span>`; }
                entry.innerHTML = `<span style="color: #f6ad55;">[${log.timestamp} - <strong>${log.context}</strong>]</span> ${message}`;
                this.panel.appendChild(entry);
            });
            this.panel.scrollTop = this.panel.scrollHeight;
        },

        togglePanel() {
            const content = this.panel.parentElement.querySelector('#debug-content');
            const toggleBtn = this.panel.parentElement.querySelector('#debug-toggle');
            const isHidden = content.style.display === 'none';
            content.style.display = isHidden ? 'block' : 'none';
            toggleBtn.textContent = isHidden ? '[Minimizar]' : '[Maximizar]';
        },

        clear() {
            this.panel.innerHTML = '';
            this.log('Panel limpiado.', 'System');
        }
    };

    /**
     * Objeto principal de la aplicación SPA.
     */
    const App = {
        init() {
            Debug.init();
            window.addEventListener('popstate', this.handlePopState.bind(this));
            appRoot.addEventListener('click', this.handleEvents.bind(this));
            appRoot.addEventListener('submit', this.handleFormSubmit.bind(this));
            appRoot.addEventListener('input', this.handleSearch.bind(this));
            this.loadViewFromUrl();
        },

        showNotification(message, type = 'success') {
            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                warning: 'bg-yellow-500'
            };
            const icon = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle'
            };

            const notification = document.createElement('div');
            notification.className = `notification text-white p-4 rounded-lg shadow-lg mb-2 flex items-center ${colors[type]}`;
            notification.innerHTML = `<i class="fas ${icon[type]} mr-3"></i> ${message}`;
            notificationArea.appendChild(notification);
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 500);
            }, 5000);
        },
        
        showLoader() {
            appRoot.innerHTML = `<div class="flex justify-center items-center min-h-screen"><i class="fas fa-spinner fa-spin fa-3x text-blue-600"></i></div>`;
        },

        async loadView(view, action = 'list', id = 0, pushState = true) {
            this.showLoader();
            Debug.log(`Cargando vista: view=${view}, action=${action}, id=${id}`, 'Navigation');
            
            const url = new URL(flowtax_ajax.home_url);
            url.pathname += `${view}/${action !== 'list' ? `${action}/${id > 0 ? id : ''}` : ''}`;

            if (pushState) {
                history.pushState({ view, action, id }, '', url.toString());
            }
            
            currentView = view;

            try {
                const params = new URLSearchParams({
                    action: 'flowtax_get_view',
                    nonce: flowtax_ajax.nonce,
                    view: view,
                    flowtax_action: action,
                    id: id
                });
                const response = await fetch(flowtax_ajax.ajax_url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params
                });
                Debug.log({ status: response.status, url: response.url }, 'AJAX Response Status');
                const result = await response.json();

                if (result.success) {
                    appRoot.innerHTML = result.data.html;
                    appRoot.firstChild.classList.add('fade-in');
                    Debug.renderBackendLogs(result.data.debug_logs);
                } else {
                    throw new Error(result.data.message || 'Error desconocido.');
                }
            } catch (error) {
                Debug.log(error, 'Error LoadView');
                this.showNotification(`Error al cargar la vista: ${error.message}`, 'error');
                appRoot.innerHTML = `<div class="text-center text-red-500 p-8">Error al cargar contenido. Intenta de nuevo.</div>`;
            }
        },
        
        loadViewFromUrl() {
            // Lógica mejorada para leer rutas amigables, ej: /inicio/impuestos/edit/123
            const path = window.location.pathname.replace(new URL(flowtax_ajax.home_url).pathname, '').split('/').filter(p => p);
            const view = path[0] || 'dashboard';
            const action = path[1] || 'list';
            const id = path[2] || 0;
            this.loadView(view, action, id, false);
        },

        handlePopState(event) {
            Debug.log(event.state, 'PopState Event');
            if (event.state) {
                this.loadView(event.state.view, event.state.action, event.state.id, false);
            } else {
                this.loadViewFromUrl();
            }
        },

        handleEvents(event) {
            const link = event.target.closest('a[data-spa-link]');
            if (link) {
                event.preventDefault();
                const { view, action = 'list', id = 0 } = link.dataset;
                this.loadView(view, action, id);
                return;
            }

            const deleteButton = event.target.closest('button[data-delete-id]');
            if(deleteButton){
                event.preventDefault();
                this.handleDelete(deleteButton);
                return;
            }
        },
        
        async handleFormSubmit(event) {
            const form = event.target.closest('form[data-spa-form]');
            if (form) {
                event.preventDefault();
                const submitButton = form.querySelector('button[type="submit"]');
                const originalButtonText = submitButton.innerHTML;
                submitButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Guardando...`;
                submitButton.disabled = true;

                form.querySelectorAll('.error-message').forEach(el => el.remove());
                form.querySelectorAll('.border-red-500').forEach(el => el.classList.remove('border-red-500'));

                const formData = new FormData(form);
                formData.append('action', 'flowtax_save_form');
                formData.append('nonce', flowtax_ajax.nonce);

                const formObject = {};
                formData.forEach((value, key) => formObject[key] = value);
                Debug.log(formObject, 'Form Submit');

                try {
                    const response = await fetch(flowtax_ajax.ajax_url, { method: 'POST', body: formData });
                    const result = await response.json();
                    Debug.log(result, 'Form Submit Response');
                    Debug.renderBackendLogs(result.data.debug_logs);
                    
                    if (result.success) {
                        this.showNotification(result.data.message, 'success');
                        this.loadView(result.data.redirect_view);
                    } else {
                         this.showNotification(result.data.message || 'Error al guardar.', 'error');
                         if (result.data.errors) {
                            Object.keys(result.data.errors).forEach(key => {
                                const field = form.querySelector(`[name="${key}"]`);
                                if (field) {
                                    field.classList.add('border-red-500');
                                    const errorEl = document.createElement('p');
                                    errorEl.className = 'text-red-500 text-sm mt-1 error-message';
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
            }
        },
        
        async handleSearch(event) {
            const searchInput = event.target.closest('input[data-search-input]');
            if (searchInput) {
                const searchTerm = searchInput.value;
                const postType = searchInput.dataset.postType;
                const tableBody = document.querySelector('#data-table-body');
                const originalContent = tableBody.innerHTML;
                
                if (searchTerm.length < 3 && searchTerm.length > 0) return;
                
                tableBody.innerHTML = `<tr><td colspan="5" class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Buscando...</td></tr>`;

                try {
                    const params = new URLSearchParams({
                        action: 'flowtax_get_search_results',
                        nonce: flowtax_ajax.nonce,
                        search_term: searchTerm,
                        post_type: postType
                    });
                    const response = await fetch(flowtax_ajax.ajax_url, {
                        method: 'POST',
                        body: params
                    });
                    const result = await response.json();
                    if (result.success) {
                       this.renderTableRows(result.data);
                    } else {
                       throw new Error('No se pudo buscar.');
                    }
                } catch(error) {
                    this.showNotification('Error en la búsqueda.', 'error');
                    tableBody.innerHTML = originalContent; // Restore on error
                }
            }
        },
        
        async handleDelete(deleteButton) {
            const postId = deleteButton.dataset.deleteId;
            if (confirm('¿Estás seguro de que quieres eliminar este registro? Esta acción no se puede deshacer.')) {
                 Debug.log(`Intentando eliminar post ID: ${postId}`, 'Delete Action');
                try {
                    const params = new URLSearchParams({
                        action: 'flowtax_delete_post',
                        nonce: flowtax_ajax.nonce,
                        post_id: postId
                    });
                    const response = await fetch(flowtax_ajax.ajax_url, { method: 'POST', body: params });
                    const result = await response.json();
                    Debug.log(result, 'Delete Response');
                    Debug.renderBackendLogs(result.data.debug_logs);

                    if (result.success) {
                        this.showNotification(result.data.message, 'success');
                        this.loadView(currentView, 'list', 0, true);
                    } else {
                        throw new Error(result.data.message);
                    }
                } catch (error) {
                    Debug.log(error, 'Error Delete');
                    this.showNotification(`Error al eliminar: ${error.message}`, 'error');
                }
            }
        },
        
        renderTableRows(data) {
            const tableBody = document.querySelector('#data-table-body');
            const view = currentView || 'dashboard';

            if (!tableBody) return;

            if (data.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="5" class="text-center py-4">No se encontraron resultados.</td></tr>`;
                return;
            }

            tableBody.innerHTML = data.map(item => `
                <tr class="border-b hover:bg-gray-50">
                    <td class="p-3">
                        <a href="#" data-spa-link data-view="${view}" data-action="edit" data-id="${item.ID}" class="font-semibold text-blue-600 hover:underline">${item.title}</a>
                        ${view === 'inmigracion' ? `<p class="text-sm text-gray-500">${item.singular_name}</p>`:''}
                    </td>
                    <td class="p-3">${item.cliente_nombre || item.email || 'N/A'}</td>
                    <td class="p-3">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full ${item.estado_color}">${item.estado}</span>
                    </td>
                    <td class="p-3 text-sm text-gray-600">${item.fecha}</td>
                    <td class="p-3 text-right">
                        <a href="#" data-spa-link data-view="${view}" data-action="edit" data-id="${item.ID}" class="text-gray-500 hover:text-blue-600 mr-2"><i class="fas fa-edit"></i></a>
                        <button data-delete-id="${item.ID}" class="text-gray-500 hover:text-red-600"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `).join('');
        }
    };

    App.init();
});

