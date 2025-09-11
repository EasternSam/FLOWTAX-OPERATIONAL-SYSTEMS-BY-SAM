document.addEventListener('DOMContentLoaded', function () {
    const appRoot = document.getElementById('flowtax-app-root');
    const notificationArea = document.getElementById('notification-area');
    let currentView = null;

    const App = {
        // Inicializa la aplicación
        init() {
            window.addEventListener('popstate', this.handlePopState.bind(this));
            appRoot.addEventListener('click', this.handleNavigation.bind(this));
            appRoot.addEventListener('submit', this.handleFormSubmit.bind(this));
            appRoot.addEventListener('input', this.handleSearch.bind(this));
            appRoot.addEventListener('click', this.handleDelete.bind(this));
            this.loadViewFromUrl();
        },

        // Muestra notificaciones
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
        
        // Muestra un loader
        showLoader() {
            appRoot.innerHTML = `<div class="flex justify-center items-center min-h-screen"><i class="fas fa-spinner fa-spin fa-3x text-blue-600"></i></div>`;
        },

        // Carga una vista desde el servidor
        async loadView(view, action = 'list', id = 0, pushState = true) {
            this.showLoader();
            const url = new URL(flowtax_ajax.home_url);
            url.searchParams.set('view', view);
            if (action !== 'list') url.searchParams.set('action', action);
            if (id > 0) url.searchParams.set('id', id);

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

                const result = await response.json();
                if (result.success) {
                    appRoot.innerHTML = result.data.html;
                    appRoot.firstChild.classList.add('fade-in');
                } else {
                    throw new Error(result.data.message || 'Error desconocido.');
                }
            } catch (error) {
                this.showNotification(`Error al cargar la vista: ${error.message}`, 'error');
                appRoot.innerHTML = `<div class="text-center text-red-500 p-8">Error al cargar contenido. Intenta de nuevo.</div>`;
            }
        },
        
        // Carga la vista inicial basada en la URL actual
        loadViewFromUrl() {
            const urlParams = new URLSearchParams(window.location.search);
            const view = urlParams.get('view') || 'dashboard';
            const action = urlParams.get('action') || 'list';
            const id = urlParams.get('id') || 0;
            this.loadView(view, action, id, false);
        },

        // Maneja el botón de retroceso del navegador
        handlePopState(event) {
            if (event.state) {
                this.loadView(event.state.view, event.state.action, event.state.id, false);
            } else {
                this.loadViewFromUrl();
            }
        },

        // Maneja clicks en enlaces de navegación
        handleNavigation(event) {
            const link = event.target.closest('a[data-spa-link]');
            if (link) {
                event.preventDefault();
                const { view, action, id } = link.dataset;
                this.loadView(view, action, id);
            }
        },
        
        // Maneja el envío de formularios
        async handleFormSubmit(event) {
            const form = event.target.closest('form[data-spa-form]');
            if (form) {
                event.preventDefault();
                const submitButton = form.querySelector('button[type="submit"]');
                const originalButtonText = submitButton.innerHTML;
                submitButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Guardando...`;
                submitButton.disabled = true;

                // Limpiar errores previos
                form.querySelectorAll('.error-message').forEach(el => el.remove());
                form.querySelectorAll('.border-red-500').forEach(el => el.classList.remove('border-red-500'));

                const formData = new FormData(form);
                formData.append('action', 'flowtax_save_form');
                formData.append('nonce', flowtax_ajax.nonce);

                try {
                    const response = await fetch(flowtax_ajax.ajax_url, {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    
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
                    this.showNotification('Error de conexión al guardar.', 'error');
                } finally {
                    submitButton.innerHTML = originalButtonText;
                    submitButton.disabled = false;
                }
            }
        },
        
        // Maneja la búsqueda en tiempo real
        async handleSearch(event) {
            const searchInput = event.target.closest('input[data-search-input]');
            if (searchInput) {
                const searchTerm = searchInput.value;
                const postType = searchInput.dataset.postType;
                const tableBody = document.querySelector('#data-table-body');
                const originalContent = tableBody.innerHTML;
                
                if (searchTerm.length < 3 && searchTerm.length > 0) return;
                
                tableBody.innerHTML = `<tr><td colspan="4" class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Buscando...</td></tr>`;

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
        
        // Maneja la eliminación de registros
        async handleDelete(event) {
            const deleteButton = event.target.closest('button[data-delete-id]');
            if (deleteButton) {
                const postId = deleteButton.dataset.deleteId;
                if (confirm('¿Estás seguro de que quieres eliminar este registro? Esta acción no se puede deshacer.')) {
                    try {
                        const params = new URLSearchParams({
                            action: 'flowtax_delete_post',
                            nonce: flowtax_ajax.nonce,
                            post_id: postId
                        });
                        const response = await fetch(flowtax_ajax.ajax_url, {
                            method: 'POST',
                            body: params
                        });
                        const result = await response.json();
                        if (result.success) {
                            this.showNotification(result.data.message, 'success');
                            this.loadView(currentView); // Recargar la vista actual
                        } else {
                            throw new Error(result.data.message);
                        }
                    } catch (error) {
                        this.showNotification(`Error al eliminar: ${error.message}`, 'error');
                    }
                }
            }
        },
        
        // Renderiza las filas de la tabla
        renderTableRows(data) {
            const tableBody = document.querySelector('#data-table-body');
            const view = new URLSearchParams(window.location.search).get('view') || 'dashboard';

            if (data.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="4" class="text-center py-4">No se encontraron resultados.</td></tr>`;
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
