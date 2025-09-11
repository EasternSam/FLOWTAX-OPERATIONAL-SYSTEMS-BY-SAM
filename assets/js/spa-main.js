document.addEventListener('DOMContentLoaded', function () {
    const appRoot = document.getElementById('flowtax-app-root');
    const notificationArea = document.getElementById('notification-area');
    let currentView = null;

    /**
     * MEJORA: Módulo de depuración avanzado para el frontend.
     * Crea una consola visual que está oculta por defecto y se puede mostrar
     * con un botón o un atajo de teclado si el modo debug está activado.
     */
    const Debug = {
        enabled: window.flowtax_ajax?.debug_mode || false,
        panel: null,
        panelContainer: null, // Referencia al contenedor principal del panel

        init() {
            if (!this.enabled) return;
            this.createPanel();
            this.createDebugButton();
            this.setupKeyboardShortcut();
            this.log('Debugger inicializado. Presiona Ctrl+Shift+D o usa el botón para mostrar/ocultar.', 'System');
        },

        createPanel() {
            const panel = document.createElement('div');
            this.panelContainer = panel; // Guardar referencia
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
            panel.style.display = 'none'; // Oculto por defecto
            document.body.appendChild(panel);
            this.panel = panel.querySelector('#debug-content');

            const header = panel.querySelector('#debug-header');
            const toggleBtn = panel.querySelector('#debug-toggle');
            const clearBtn = panel.querySelector('#debug-clear');
            
            header.addEventListener('click', (e) => {
                if (e.target !== toggleBtn && e.target !== clearBtn) {
                    this.togglePanelContent();
                }
            });
            toggleBtn.addEventListener('click', (e) => { e.stopPropagation(); this.togglePanelContent(); });
            clearBtn.addEventListener('click', (e) => { e.stopPropagation(); this.clear(); });
        },

        createDebugButton() {
            const button = document.createElement('button');
            button.id = 'flowtax-debug-toggle-button';
            button.innerHTML = '<i class="fas fa-bug"></i>';
            button.style.position = 'fixed';
            button.style.bottom = '15px';
            button.style.right = '15px';
            button.style.width = '50px';
            button.style.height = '50px';
            button.style.background = '#1a202c';
            button.style.color = 'white';
            button.style.border = '2px solid #4a5568';
            button.style.borderRadius = '50%';
            button.style.zIndex = '99998';
            button.style.cursor = 'pointer';
            button.style.fontSize = '20px';
            button.style.display = 'flex';
            button.style.justifyContent = 'center';
            button.style.alignItems = 'center';
            button.title = 'Mostrar/Ocultar Consola de Depuración (Ctrl+Shift+D)';

            button.addEventListener('click', () => this.toggleVisibility());
            document.body.appendChild(button);
        },
        
        setupKeyboardShortcut() {
            window.addEventListener('keydown', (e) => {
                if (e.ctrlKey && e.shiftKey && e.key.toLowerCase() === 'd') {
                    e.preventDefault();
                    this.toggleVisibility();
                }
            });
        },
        
        toggleVisibility() {
            if (!this.panelContainer) return;
            const isVisible = this.panelContainer.style.display === 'block';
            this.panelContainer.style.display = isVisible ? 'none' : 'block';
        },

        log(message, context = 'General') {
            if (!this.enabled) return;
            const timestamp = new Date().toLocaleTimeString();
            console.log(`[${context}]`, message);
            
            if (!this.panel) return;

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

        togglePanelContent() {
            const content = this.panel.parentElement.querySelector('#debug-content');
            const toggleBtn = this.panel.parentElement.querySelector('#debug-toggle');
            const isHidden = content.style.display === 'none';
            content.style.display = isHidden ? 'block' : 'none';
            toggleBtn.textContent = isHidden ? '[Minimizar]' : '[Maximizar]';
        },

        clear() {
            if (!this.panel) return;
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
            document.body.addEventListener('click', this.handleEvents.bind(this)); // Escucha en el body para los links del sidebar
            appRoot.addEventListener('submit', this.handleFormSubmit.bind(this));
            appRoot.addEventListener('input', this.handleSearch.bind(this));
            this.loadViewFromUrl();
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
            appRoot.innerHTML = `<div class="flex justify-center items-center h-full"><i class="fas fa-spinner fa-spin fa-3x text-blue-600"></i></div>`;
        },

        async loadView(view, action = 'list', id = 0, pushState = true) {
            this.showLoader();
            Debug.log(`Cargando vista: view=${view}, action=${action}, id=${id}`, 'Navigation');
            
            const url = new URL(flowtax_ajax.home_url);
            let path = view;
            if (action !== 'list') path += `/${action}`;
            if (id > 0) path += `/${id}`;
            url.pathname += path;

            if (pushState) {
                history.pushState({ view, action, id }, '', url.toString());
            }
            
            currentView = view;
            this.updateActiveSidebarLink(view);

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
                
                const textResponse = await response.text();
                let result;
                try {
                    result = JSON.parse(textResponse);
                } catch (e) {
                    Debug.log(`Error de JSON: ${e.message}`, 'AJAX Error');
                    Debug.log(textResponse, 'Respuesta no válida');
                    throw new Error(`Respuesta inesperada del servidor: ${textResponse.substring(0, 100)}`);
                }


                if (result.success) {
                    appRoot.innerHTML = result.data.html;
                    // Espera a que el DOM se actualice y luego añade la clase.
                    setTimeout(() => {
                         if (appRoot.firstChild && typeof appRoot.firstChild.classList !== 'undefined') {
                             appRoot.firstChild.classList.add('fade-in');
                         }
                    }, 0);
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
                
                // Habilitar el select de post_type si está deshabilitado para que se envíe
                const postTypeSelect = form.querySelector('select[name="post_type"]');
                if (postTypeSelect && postTypeSelect.disabled) {
                    postTypeSelect.disabled = false;
                    formData.set('post_type', postTypeSelect.value);
                     postTypeSelect.disabled = true;
                }

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
                
                if (searchTerm.length > 0 && searchTerm.length < 3) return;
                
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
                    Debug.renderBackendLogs(result.data.debug_logs);
                    if (result.success) {
                       this.renderTableRows(result.data);
                    } else {
                       throw new Error('No se pudo buscar.');
                    }
                } catch(error) {
                    this.showNotification('Error en la búsqueda.', 'error');
                    this.loadView(currentView); // Recarga la vista actual en caso de error
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
            if (!tableBody) return;
            
            const view = currentView || 'dashboard';
            const headers = tableBody.closest('table').querySelectorAll('th');
            const colCount = headers.length;

            if (!data || data.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="${colCount}" class="text-center py-4 text-gray-500">No se encontraron resultados.</td></tr>`;
                return;
            }

            let rowsHtml = '';
            
            // Renderizado dinámico basado en la vista actual
            data.forEach(item => {
                let row = `<tr class="hover:bg-gray-50">`;
                switch(view) {
                    case 'clientes':
                         row += `<td><a href="#" data-spa-link data-view="clientes" data-action="edit" data-id="${item.ID}" class="font-semibold text-blue-600 hover:underline">${item.title}</a></td>`;
                         row += `<td>${item.email || ''}</td>`;
                         row += `<td>${item.telefono || ''}</td>`;
                         row += `<td>${item.fecha}</td>`;
                        break;
                    case 'impuestos':
                        row += `<td><a href="#" data-spa-link data-view="impuestos" data-action="edit" data-id="${item.ID}" class="font-semibold text-blue-600 hover:underline">${item.title}</a><p class="text-sm text-gray-500">${item.cliente_nombre}</p></td>`;
                        row += `<td>${item.ano_fiscal || 'N/A'}</td>`;
                        row += `<td><span class="px-2 py-1 text-xs font-semibold rounded-full ${item.estado_color}">${item.estado}</span></td>`;
                        row += `<td>${item.fecha}</td>`;
                        break;
                    case 'inmigracion':
                        row += `<td><a href="#" data-spa-link data-view="inmigracion" data-action="edit" data-id="${item.ID}" class="font-semibold text-blue-600 hover:underline">${item.singular_name}</a></td>`;
                        row += `<td>${item.cliente_nombre}</td>`;
                        row += `<td><span class="px-2 py-1 text-xs font-semibold rounded-full ${item.estado_color}">${item.estado}</span></td>`;
                        row += `<td>${item.fecha}</td>`;
                        break;
                    case 'traducciones':
                        row += `<td><a href="#" data-spa-link data-view="traducciones" data-action="edit" data-id="${item.ID}" class="font-semibold text-blue-600 hover:underline">${item.title}</a><p class="text-sm text-gray-500">${item.cliente_nombre}</p></td>`;
                        row += `<td>${item.idioma_origen || ''} → ${item.idioma_destino || ''}</td>`;
                        row += `<td><span class="px-2 py-1 text-xs font-semibold rounded-full ${item.estado_color}">${item.estado}</span></td>`;
                        row += `<td>${item.fecha}</td>`;
                        break;
                    // ... otros casos para payroll, transacciones, etc.
                }

                row += `
                    <td class="text-right">
                        <a href="#" data-spa-link data-view="${view}" data-action="edit" data-id="${item.ID}" class="text-gray-500 hover:text-blue-600 mr-3 p-1"><i class="fas fa-edit"></i></a>
                        <button data-delete-id="${item.ID}" class="text-gray-500 hover:text-red-600 p-1"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>`;
                rowsHtml += row;
            });

            tableBody.innerHTML = rowsHtml;
        }
    };

    App.init();
});

