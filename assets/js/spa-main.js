document.addEventListener('DOMContentLoaded', function () {
    const appRoot = document.getElementById('flowtax-app-root');
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
            window.addEventListener('popstate', this.handlePopState.bind(this));
            document.body.addEventListener('click', this.handleEvents.bind(this));
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
            const colors = { success: 'bg-green-500', error: 'bg-red-500', warning: 'bg-yellow-500' };
            const icon = { success: 'fa-check-circle', error: 'fa-exclamation-circle', warning: 'fa-exclamation-triangle' };
            const notification = document.createElement('div');
            notification.className = `notification text-white p-3 rounded-lg shadow-lg mb-2 flex items-center text-sm ${colors[type]}`;
            notification.innerHTML = `<i class="fas ${icon[type]} mr-3"></i> ${message}`;
            notificationArea.appendChild(notification);
            setTimeout(() => {
                notification.style.opacity = '0';
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
            if (action !== 'list') path += `/${action}`;
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

                if (result.success) {
                    appRoot.innerHTML = result.data.html;
                    setTimeout(() => {
                         if (appRoot.firstChild && typeof appRoot.firstChild.classList !== 'undefined') {
                             appRoot.firstChild.classList.add('fade-in');
                         }
                    }, 0);
                    Debug.renderBackendLogs(result.data.debug_logs);
                    
                    // Si es la vista de perfil, cargar los datos
                    if (view === 'clientes' && action === 'perfil') {
                        this.loadClientePerfil(id);
                    }

                } else {
                    throw new Error(result.data.message || 'Error desconocido.');
                }
            } catch (error) {
                Debug.log(error, 'Error LoadView');
                this.showNotification(`Error al cargar la vista: ${error.message}`, 'error');
                appRoot.innerHTML = `<div class="text-center text-red-500 p-8">Error al cargar contenido.</div>`;
            }
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
                    link.dataset.action = 'edit';
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

        handleEvents(event) {
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
                        this.loadView(result.data.redirect_view);
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
            }
        },
        
        handleSearch(event) {
            const searchInput = event.target.closest('input[data-search-input]');
            if (searchInput) {
                clearTimeout(searchDebounce);
                searchDebounce = setTimeout(async () => {
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
                }, 300); // Debounce de 300ms
            }
        },
        
        async handleDelete(deleteButton) {
            const postId = deleteButton.dataset.deleteId;
            if (confirm('¿Estás seguro de que quieres eliminar este registro? Esta acción no se puede deshacer.')) {
                try {
                    const params = new URLSearchParams({ action: 'flowtax_delete_post', nonce: flowtax_ajax.nonce, post_id: postId });
                    const response = await fetch(flowtax_ajax.ajax_url, { method: 'POST', body: params });
                    const result = await response.json();
                    Debug.renderBackendLogs(result.data.debug_logs);
                    if (result.success) {
                        this.showNotification(result.data.message, 'success');
                        this.loadView(currentView, 'list', 0, true);
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
                let row = `<tr>`;
                // Renderizado dinámico basado en la vista actual
                switch(currentView) {
                    case 'clientes':
                         row += `<td><a href="#" data-spa-link data-view="clientes" data-action="perfil" data-id="${item.ID}" class="font-semibold text-blue-600 hover:underline">${item.title}</a></td>`;
                         row += `<td>${item.email || ''}</td>`;
                         row += `<td>${item.telefono || ''}</td>`;
                         row += `<td>${item.fecha}</td>`;
                        break;
                    case 'impuestos':
                        row += `<td><a href="#" data-spa-link data-view="impuestos" data-action="edit" data-id="${item.ID}" class="font-semibold text-blue-600 hover:underline">${item.title}</a><p class="text-xs text-slate-500">${item.cliente_nombre}</p></td>`;
                        row += `<td>${item.ano_fiscal || 'N/A'}</td>`;
                        row += `<td><span class="px-2 py-0.5 text-xs font-semibold rounded-full ${item.estado_color}">${item.estado}</span></td>`;
                        row += `<td>${item.fecha}</td>`;
                        break;
                    case 'inmigracion':
                        row += `<td><a href="#" data-spa-link data-view="inmigracion" data-action="edit" data-id="${item.ID}" class="font-semibold text-blue-600 hover:underline">${item.singular_name}</a></td>`;
                        row += `<td>${item.cliente_nombre}</td>`;
                        row += `<td><span class="px-2 py-0.5 text-xs font-semibold rounded-full ${item.estado_color}">${item.estado}</span></td>`;
                        row += `<td>${item.fecha}</td>`;
                        break;
                    case 'traducciones':
                        row += `<td><a href="#" data-spa-link data-view="traducciones" data-action="edit" data-id="${item.ID}" class="font-semibold text-blue-600 hover:underline">${item.title}</a><p class="text-xs text-slate-500">${item.cliente_nombre}</p></td>`;
                        row += `<td>${item.idioma_origen || ''} → ${item.idioma_destino || ''}</td>`;
                        row += `<td><span class="px-2 py-0.5 text-xs font-semibold rounded-full ${item.estado_color}">${item.estado}</span></td>`;
                        row += `<td>${item.fecha}</td>`;
                        break;
                }
                row += `
                    <td class="text-right space-x-2">
                        <a href="#" data-spa-link data-view="${currentView}" data-action="edit" data-id="${item.ID}" class="btn-icon" title="Editar"><i class="fas fa-edit"></i></a>
                        <button data-delete-id="${item.ID}" class="btn-icon-danger" title="Eliminar"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>`;
                rowsHtml += row;
            });
            tableBody.innerHTML = rowsHtml;
        }
    };
    App.init();
});
