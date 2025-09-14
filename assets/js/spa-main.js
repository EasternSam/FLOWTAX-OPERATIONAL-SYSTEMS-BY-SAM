document.addEventListener('DOMContentLoaded', function () {
    const appRoot = document.getElementById('flowtax-app-root');
    const containerWrapper = document.getElementById('flowtax-container-wrapper');
    const notificationArea = document.getElementById('notification-area');
    let currentView = null;
    let searchDebounce = null;
    let supervisionInterval = null;
    let userPresenceInterval = null;

    const formatCurrency = (amount, currency = 'USD') => {
        const num = parseFloat(amount) || 0;
        const options = {
            style: 'currency',
            currency: currency,
            currencyDisplay: 'narrowSymbol'
        };
        const prefix = currency === 'USD' ? 'USD$ ' : 'RD$ ';
        return prefix + new Intl.NumberFormat('en-US', options).format(num).replace('$', '');
    };

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
                this.initNotifications();
                this.initWatchmanMode();
                this.initReminderModal();
                this.initPaymentModal();
                this.initUserPresence();
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
        
        initPaymentModal() {
            const modal = document.getElementById('payment-modal');
            if (!modal || modal.dataset.initialized) return;
            modal.dataset.initialized = 'true';
        
            const form = modal.querySelector('#payment-form');
            const montoInput = modal.querySelector('#payment-modal-monto');
        
            modal.querySelector('#close-payment-modal').addEventListener('click', () => this.togglePaymentModal(false));
            modal.addEventListener('click', (e) => {
                if (e.target === modal) this.togglePaymentModal(false);
            });
            
            montoInput.addEventListener('input', (e) => {
                let value = e.target.value.replace(/[^0-9.]/g, '');
                const parts = value.split('.');
                if (parts.length > 2) {
                    value = parts[0] + '.' + parts.slice(1).join('');
                }
                if (parts[1] && parts[1].length > 2) {
                    value = parts[0] + '.' + parts[1].substring(0, 2);
                }
                e.target.value = value;
            });
        
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                const deudaId = form.querySelector('#payment-modal-deuda-id').value;
                const montoAbono = montoInput.value;
                this.handleUpdatePayment(deudaId, montoAbono);
            });
        },

        initUserPresence() {
            this.updateOnlineUsersList();
            userPresenceInterval = setInterval(() => this.updateOnlineUsersList(), 20000); // Actualiza cada 20 segundos
        },

        async updateOnlineUsersList() {
            const container = document.getElementById('online-users-container');
            if (!container) {
                if(userPresenceInterval) clearInterval(userPresenceInterval);
                return;
            }
            try {
                const params = new URLSearchParams({ action: 'flowtax_get_online_users', nonce: flowtax_ajax.nonce });
                const response = await fetch(flowtax_ajax.ajax_url, { method: 'POST', body: params });
                const result = await response.json();
                if (result.success) {
                    this.renderOnlineUsers(result.data.users);
                }
            } catch (error) {
                Debug.log('Error fetching online users', 'User Presence');
            }
        },

        renderOnlineUsers(users) {
            const container = document.getElementById('online-users-container');
            if (!container) return;
            container.innerHTML = '';
            
            users.forEach(user => {
                const userLink = document.createElement('a');
                userLink.href = '#';
                userLink.className = 'online-user-avatar';
                userLink.dataset.spaLink = true;
                userLink.dataset.view = 'actividad';
                userLink.dataset.id = user.user_id;
                userLink.title = `${user.display_name} - ${user.location}`;

                const userImg = document.createElement('img');
                userImg.src = user.avatar_url;
                userImg.alt = user.display_name;

                userLink.appendChild(userImg);
                container.appendChild(userLink);
            });
        },

        async handleUpdatePayment(deudaId, montoAbono) {
            const modal = document.getElementById('payment-modal');
            const submitButton = modal.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            submitButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Registrando...`;
            submitButton.disabled = true;
        
            try {
                const params = new URLSearchParams({
                    action: 'flowtax_update_payment',
                    nonce: flowtax_ajax.nonce,
                    deuda_id: deudaId,
                    monto_abono: montoAbono
                });
                const response = await fetch(flowtax_ajax.ajax_url, { method: 'POST', body: params });
                const result = await response.json();
                Debug.renderBackendLogs(result.debug_logs);
                
                if (!result.success) throw new Error(result.data.message || 'Error al actualizar el pago.');
        
                this.showNotification(result.data.message, 'success');
                this.togglePaymentModal(false);
                if (currentView === 'clientes') {
                    this.loadClientePerfil(document.getElementById('cliente-perfil-view').dataset.clienteId);
                } else {
                    this.handleSearch(document.querySelector('input[data-search-input]'));
                }
        
            } catch (error) {
                this.showNotification(error.message, 'error');
            } finally {
                submitButton.innerHTML = originalButtonText;
                submitButton.disabled = false;
            }
        },

        togglePaymentModal(show, deuda = null) {
            const modal = document.getElementById('payment-modal');
            if (show && deuda) {
                modal.querySelector('#payment-modal-total').textContent = formatCurrency(deuda.monto_deuda, deuda.divisa);
                modal.querySelector('#payment-modal-restante').textContent = formatCurrency(deuda.monto_restante, deuda.divisa);
                modal.querySelector('#payment-modal-deuda-id').value = deuda.ID;
                modal.querySelector('#payment-modal-monto').value = '';
                
                const currencyLabel = modal.querySelector('#payment-modal-currency-label');
                currencyLabel.textContent = `Monto a abonar (${deuda.divisa})*`;
                
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                setTimeout(() => modal.querySelector('#payment-modal-monto').focus(), 100);
            } else {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        },

        initReminderModal() {
            const modal = document.getElementById('reminder-modal');
            if (!modal || modal.dataset.initialized) return;
            modal.dataset.initialized = 'true';
            
            modal.querySelector('#close-reminder-modal').addEventListener('click', () => this.toggleReminderModal(false));
            modal.addEventListener('click', (e) => {
                if (e.target.closest('button[data-reminder-method]')) {
                    const method = e.target.closest('button[data-reminder-method]').dataset.reminderMethod;
                    const deudaId = modal.dataset.deudaId;
                    this.sendReminder(deudaId, method);
                    this.toggleReminderModal(false);
                }
                if (e.target === modal) {
                    this.toggleReminderModal(false);
                }
            });
        },

        toggleReminderModal(show, deudaId = null) {
            const modal = document.getElementById('reminder-modal');
            if (show && deudaId) {
                modal.dataset.deudaId = deudaId;
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            } else {
                modal.dataset.deudaId = '';
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        },

        async sendReminder(deudaId, method) {
            this.showNotification('Enviando recordatorio...', 'warning');
            try {
                const params = new URLSearchParams({ action: 'flowtax_send_reminder', nonce: flowtax_ajax.nonce, deuda_id: deudaId, method });
                const response = await fetch(flowtax_ajax.ajax_url, { method: 'POST', body: params });
                const result = await response.json();
                Debug.renderBackendLogs(result.debug_logs);
                
                if (!result.success) throw new Error(result.data.message || 'Error desconocido.');
                
                const reminderResults = result.data.results;

                if (reminderResults.whatsapp && reminderResults.whatsapp.success) {
                    if (reminderResults.whatsapp.method === 'manual') {
                        window.open(reminderResults.whatsapp.url, '_blank');
                        this.showNotification('Link de WhatsApp generado.', 'success');
                    } else {
                        this.showNotification(reminderResults.whatsapp.message, 'success');
                    }
                } else if (reminderResults.whatsapp) {
                    this.showNotification(reminderResults.whatsapp.message, 'error');
                }
                if (reminderResults.email && reminderResults.email.success) {
                     this.showNotification(reminderResults.email.message, 'success');
                } else if (reminderResults.email) {
                    this.showNotification(reminderResults.email.message, 'error');
                }

            } catch (error) {
                this.showNotification(`Error: ${error.message}`, 'error');
            }
        },
        
        initDocViewer() {
            const modal = document.getElementById('doc-viewer-modal');
            if (!modal || modal.dataset.viewerInitialized) return;
            modal.dataset.viewerInitialized = 'true';

            const closeBtn = document.getElementById('close-viewer-btn');
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
                if(e.target === imgEl) {
                    e.preventDefault();
                    updateZoom(currentZoom * (e.deltaY < 0 ? 1.1 : 1 / 1.1));
                }
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


        initNotifications() {
            const container = document.getElementById('notification-bell-container');
            if (!container) return;
        
            const btn = document.getElementById('notification-bell-btn');
            const dropdown = document.getElementById('notification-dropdown');
            const indicator = document.getElementById('notification-indicator');
            const list = document.getElementById('notification-list');
        
            const toggleDropdown = (show) => {
                const isVisible = !dropdown.classList.contains('hidden');
                if (typeof show !== 'undefined' && show === isVisible) return;
        
                if (isVisible) {
                    dropdown.classList.add('hidden');
                } else {
                    dropdown.classList.remove('hidden');
                    this.fetchNotifications(list, indicator);
                }
            };
        
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                toggleDropdown();
            });

            list.addEventListener('click', (e) => {
                if (e.target.closest('[data-spa-link]')) {
                    toggleDropdown(false);
                }
            });
        
            document.body.addEventListener('click', (e) => {
                if (!container.contains(e.target)) {
                    if (!dropdown.classList.contains('hidden')) {
                        toggleDropdown(false);
                    }
                }
            });

            container.querySelector('#view-all-notifications-link').addEventListener('click', () => toggleDropdown(false));
        
            this.checkForNewNotifications(indicator);
            setInterval(() => this.checkForNewNotifications(indicator), 30000);
        },

        initWatchmanMode() {
            const toggle = document.getElementById('watchman-mode-toggle');
            if (!toggle) return;
            toggle.checked = window.flowtax_ajax.watchman_mode_status;
            toggle.addEventListener('change', this.handleToggleWatchmanMode.bind(this));
        },

        async handleToggleWatchmanMode(event) {
            const toggle = event.target;
            const originalState = !toggle.checked;
        
            try {
                const params = new URLSearchParams({ action: 'flowtax_toggle_watchman_mode', nonce: flowtax_ajax.nonce });
                const response = await fetch(flowtax_ajax.ajax_url, { method: 'POST', body: params });
                const result = await response.json();
                
                if (!result.success) throw new Error(result.data.message || 'Error updating status.');
                this.showNotification(result.data.message, 'success');
            } catch (error) {
                Debug.log(error, 'Error Watchman Mode');
                this.showNotification(`Error: ${error.message}`, 'error');
                toggle.checked = originalState;
            }
        },

        async fetchNotifications(list, indicator) {
            list.innerHTML = `<div class="p-4 text-center text-sm text-slate-500">Cargando...</div>`;
            indicator.classList.add('hidden');
        
            try {
                const params = new URLSearchParams({ action: 'flowtax_get_notifications', nonce: flowtax_ajax.nonce });
                const response = await fetch(flowtax_ajax.ajax_url, { method: 'POST', body: params });
                const result = await response.json();
                Debug.renderBackendLogs(result.data.debug_logs);
        
                if (!result.success) throw new Error('Could not fetch notifications.');
        
                if (result.data.notifications && result.data.notifications.length > 0) {
                    list.innerHTML = result.data.notifications.map(n => {
                        const isClickable = n.view_slug && n.related_id && n.action;
                        const tag = isClickable ? `a` : `div`;
                        const linkAttrs = isClickable ? `href="#" data-spa-link data-view="${n.view_slug}" data-action="${n.action}" data-id="${n.related_id}"` : '';
                        
                        return `
                        <${tag} ${linkAttrs} class="block p-3 border-b border-slate-100 last:border-0 hover:bg-slate-50 cursor-pointer">
                            <p class="text-sm text-slate-700">${n.title}</p>
                            <p class="text-xs text-slate-400 mt-1">${n.time_ago} por <strong>${n.author}</strong></p>
                        </${tag}>`;
                    }).join('');
                } else {
                    list.innerHTML = `<div class="p-4 text-center text-sm text-slate-500">No hay notificaciones nuevas.</div>`;
                }
            } catch (error) {
                Debug.log(error, 'Error Fetching Notifications');
                list.innerHTML = `<div class="p-4 text-center text-sm text-red-500">Error al cargar.</div>`;
            }
        },

        async checkForNewNotifications(indicator) {
            try {
                const params = new URLSearchParams({ action: 'flowtax_get_notifications', nonce: flowtax_ajax.nonce, check_only: 'true' });
                const response = await fetch(flowtax_ajax.ajax_url, { method: 'POST', body: params });
                const result = await response.json();
        
                if (result.success && result.data.unread_count > 0) {
                    indicator.classList.remove('hidden');
                } else {
                    indicator.classList.add('hidden');
                }
            } catch (error) { /* Fail silently */ }
        },

        showNotification(message, type = 'success') {
            const colors = { success: 'bg-green-600', error: 'bg-red-500', warning: 'bg-yellow-500' };
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
            if (supervisionInterval) {
                clearInterval(supervisionInterval);
                supervisionInterval = null;
            }

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
                    if (newViewContent) newViewContent.classList.add('animate-fade-in');
                    
                    if (action === 'perfil') this.loadClientePerfil(id);
                    if (action === 'manage') this.loadCasoManage(id);
                    if (view === 'supervision') this.initSupervisionView();
                    
                    if(action === 'list' && document.getElementById('data-table-body')){
                        this.handleSearch(document.querySelector('input[data-search-input]'));
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

        initSupervisionView() {
            this.updateSupervisionFeed();
            supervisionInterval = setInterval(() => this.updateSupervisionFeed(), 5000);
        },

        async updateSupervisionFeed() {
            const feedContainer = document.getElementById('live-activity-feed');
            if (!feedContainer) {
                 if (supervisionInterval) clearInterval(supervisionInterval);
                 return;
            }

            try {
                const params = new URLSearchParams({ action: 'flowtax_get_live_activity', nonce: flowtax_ajax.nonce });
                const response = await fetch(flowtax_ajax.ajax_url, { method: 'POST', body: params });
                const result = await response.json();
                
                if (!result.success) return;
                
                const logsHtml = result.data.logs.map(log => {
                    const isClickable = log.view_slug && log.related_id && log.action;
                    const tag = isClickable ? 'a' : 'div';
                    const linkAttrs = isClickable ? `href="#" data-spa-link data-view="${log.view_slug}" data-action="${log.action}" data-id="${log.related_id}"` : '';
                    
                    return `
                    <${tag} ${linkAttrs} class="block p-4 hover:bg-slate-50 transition-colors duration-200">
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0 pt-1"><i class="fas fa-history text-slate-400"></i></div>
                            <div class="flex-grow">
                                <p class="text-sm text-slate-700">${log.title}</p>
                                <p class="text-xs text-slate-400 mt-1">
                                    Por <strong>${log.author}</strong>, ${log.time_ago}
                                </p>
                            </div>
                             ${isClickable ? '<div class="flex-shrink-0 pt-1"><i class="fas fa-chevron-right text-slate-300"></i></div>' : ''}
                        </div>
                    </${tag}>`;
                }).join('');

                feedContainer.innerHTML = logsHtml || `<div class="p-8 text-center text-slate-500">No hay actividad reciente.</div>`;
            } catch(e) {
                Debug.log(e, 'Error Supervision Feed');
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
                 const contentArea = document.getElementById('perfil-content-area');
                 if(contentArea) contentArea.innerHTML = `<div class="p-8 text-center text-red-500">Error al cargar el perfil.</div>`;
            }
        },

        renderClientePerfil(data) {
            const { cliente, casos, deudas } = data;
            
            document.getElementById('cliente-nombre-header').textContent = cliente.title;
            const iniciales = cliente.title.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase();
            document.getElementById('cliente-iniciales').textContent = iniciales;

            const getMeta = (key) => cliente.meta[`_${key}`]?.[0] || '';
            
            const infoContactoContainer = document.getElementById('info-contacto');
            infoContactoContainer.innerHTML = `
                <li class="flex items-start"><i class="fas fa-envelope fa-fw w-6 text-slate-400 pt-1"></i><div><span class="text-xs text-slate-500">Email</span><p class="font-medium text-slate-700">${getMeta('email') || 'No especificado'}</p></div></li>
                <li class="flex items-start"><i class="fas fa-phone fa-fw w-6 text-slate-400 pt-1"></i><div><span class="text-xs text-slate-500">Teléfono</span><p class="font-medium text-slate-700">${getMeta('telefono') || 'No especificado'}</p></div></li>
                <li class="flex items-start"><i class="fas fa-id-card fa-fw w-6 text-slate-400 pt-1"></i><div><span class="text-xs text-slate-500">Tax ID</span><p class="font-medium text-slate-700">${getMeta('tax_id') || 'No especificado'}</p></div></li>
            `;

            const casosContainer = document.getElementById('casos-asociados-lista');
            const casoTemplate = document.getElementById('caso-item-template');
            casosContainer.innerHTML = '';

            if (casos.length > 0) {
                const casoFragment = document.createDocumentFragment();
                casos.forEach(caso => {
                    const clone = casoTemplate.content.cloneNode(true);
                    const link = clone.querySelector('.caso-item');
                    if (link) {
                        link.setAttribute('data-view', caso.view_slug);
                        link.setAttribute('data-action', 'manage');
                        link.setAttribute('data-id', caso.ID);
                        link.setAttribute('data-spa-link', 'true'); // <-- The fix is here
                    }
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
                casosContainer.innerHTML = '<div class="text-center py-8 text-slate-500"><i class="fas fa-folder-open fa-2x mb-3 text-slate-400"></i><p>No hay casos asociados.</p></div>';
            }

            const deudasContainer = document.getElementById('deudas-asociadas-lista');
            const deudaTemplate = document.getElementById('deuda-item-template');
            deudasContainer.innerHTML = '';

            if (deudas.length > 0) {
                const deudaFragment = document.createDocumentFragment();
                deudas.forEach(deuda => {
                    const clone = deudaTemplate.content.cloneNode(true);
                    clone.querySelector('.font-semibold').textContent = deuda.title;
                    clone.querySelector('.text-xs.text-slate-400').textContent = `Vence: ${deuda.fecha_vencimiento ? new Date(deuda.fecha_vencimiento + 'T00:00:00').toLocaleDateString('es-DO', { year: 'numeric', month: 'short', day: 'numeric' }) : 'N/A'}`;
                    
                    const statusBadge = clone.querySelector('.status-badge');
                    statusBadge.textContent = deuda.estado;
                    statusBadge.className += ` ${deuda.estado_color}`;

                    clone.querySelector('.balance-restante').textContent = formatCurrency(deuda.monto_restante, deuda.divisa);
                    clone.querySelector('.balance-total').textContent = formatCurrency(deuda.monto_deuda, deuda.divisa);
                    
                    const paymentButton = clone.querySelector('.add-payment-btn');
                    paymentButton.dataset.addPaymentId = deuda.ID;
                    paymentButton.dataset.montoDeuda = deuda.monto_deuda;
                    paymentButton.dataset.montoRestante = deuda.monto_restante;
                    paymentButton.dataset.divisa = deuda.divisa;

                    const editLink = clone.querySelector('.edit-deuda-btn');
                    editLink.setAttribute('data-spa-link', 'true');
                    editLink.setAttribute('data-view', 'cuentas-por-cobrar');
                    editLink.setAttribute('data-action', 'edit');
                    editLink.setAttribute('data-id', deuda.ID);

                    deudaFragment.appendChild(clone);
                });
                deudasContainer.appendChild(deudaFragment);
            } else {
                deudasContainer.innerHTML = '<div class="text-center py-8 text-slate-500"><i class="fas fa-file-invoice-dollar fa-2x mb-3 text-slate-400"></i><p>No hay deudas registradas.</p></div>';
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
                // Using getAttribute for robustness, to match the change in renderClientePerfil.
                const view = link.getAttribute('data-view');
                const action = link.getAttribute('data-action') || 'list';
                const id = link.getAttribute('data-id') || 0;

                this.loadView(view, action, id);
                return;
            }

            const deleteButton = event.target.closest('button[data-delete-id]');
            if(deleteButton){
                event.preventDefault();
                this.handleDeletePost(deleteButton.dataset.deleteId);
                return;
            }
            
            const reminderButton = event.target.closest('[data-send-reminder-id]');
            if (reminderButton) { 
                event.preventDefault(); 
                this.toggleReminderModal(true, reminderButton.dataset.sendReminderId);
                return; 
            }

            const paymentButton = event.target.closest('[data-add-payment-id]');
            if (paymentButton) {
                event.preventDefault();
                const deudaData = {
                    ID: paymentButton.dataset.addPaymentId,
                    monto_deuda: paymentButton.dataset.montoDeuda,
                    monto_restante: paymentButton.dataset.montoRestante,
                    divisa: paymentButton.dataset.divisa
                };
                this.togglePaymentModal(true, deudaData);
                return;
            }

            const statusFilterButton = event.target.closest('[data-status-filter]');
            if(statusFilterButton) {
                document.querySelectorAll('[data-status-filter]').forEach(btn => btn.classList.remove('active'));
                statusFilterButton.classList.add('active');
                this.handleSearch(document.querySelector('input[data-search-input]'));
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

            const formData = new FormData(form);
            formData.append('action', 'flowtax_add_note');
            formData.append('nonce', flowtax_ajax.nonce);

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
                    this.loadView(result.data.redirect_view, result.data.redirect_action || 'list', result.data.post_id);
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
            if (!searchInput) return;
            
            const searchTerm = searchInput.value;
            const postType = searchInput.dataset.postType;
            const tableBody = document.querySelector('#data-table-body');
            const statusFilter = document.querySelector('[data-status-filter].active')?.dataset.statusFilter || '';
            
            tableBody.innerHTML = `<tr><td colspan="5" class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Buscando...</td></tr>`;

            try {
                const params = new URLSearchParams({ 
                    action: 'flowtax_get_search_results', 
                    nonce: flowtax_ajax.nonce, 
                    search_term: searchTerm, 
                    post_type: postType,
                    status_filter: statusFilter 
                });
                const response = await fetch(flowtax_ajax.ajax_url, { method: 'POST', body: params });
                const result = await response.json();
                Debug.renderBackendLogs(result.data.debug_logs);
                if (!result.success) throw new Error('La respuesta del servidor indica un fallo.');
                this.renderTableRows(result.data, searchTerm);
            } catch(error) {
                Debug.log(error, 'Error HandleSearch');
                this.showNotification('Error en la búsqueda.', 'error');
                const colCount = tableBody.closest('table')?.querySelector('thead tr')?.childElementCount || 5;
                tableBody.innerHTML = `<tr><td colspan="${colCount}" class="text-center py-16"><div class="text-red-500"><i class="fas fa-exclamation-triangle fa-2x"></i></div><h3 class="mt-2 text-lg font-semibold text-gray-800">Error al Cargar</h3><p class="mt-1 text-sm text-gray-500">No se pudieron cargar los datos.</p></td></tr>`;
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
                    formData.append('action', 'flowtax_upload_document');
                    formData.append('nonce', flowtax_ajax.nonce);
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
        
        renderTableRows(data, searchTerm = '') {
            const tableBody = document.querySelector('#data-table-body');
            if (!tableBody) return;

            let items = [];
            if (Array.isArray(data)) {
                items = data;
            } else if (typeof data === 'object' && data !== null) {
                items = Object.values(data).filter(item => typeof item === 'object' && item !== null && item.hasOwnProperty('ID'));
            }
            
            const colCount = tableBody.closest('table').querySelector('thead tr').childElementCount;
            
            if (items.length === 0) {
                const isSearching = searchTerm.length > 0;
                let emptyStateHtml = '';
                if (isSearching) {
                    emptyStateHtml = `<tr><td colspan="${colCount}" class="text-center py-16"><h3 class="text-lg font-semibold text-gray-800">Sin resultados</h3><p class="mt-1 text-sm text-gray-500">No se encontraron registros.</p></div></td></tr>`;
                } else {
                     emptyStateHtml = `<tr><td colspan="${colCount}" class="text-center py-16"><h3 class="text-lg font-semibold text-gray-800">No hay registros</h3><p class="mt-1 text-sm text-gray-500">Empieza por añadir uno nuevo.</p><div class="mt-6"><a href="#" data-spa-link data-view="${currentView}" data-action="create" class="btn-primary"><i class="fas fa-plus mr-2"></i>Añadir Registro</a></div></td></tr>`;
                }
                tableBody.innerHTML = emptyStateHtml;
                return;
            }

            const rowsHtml = items.map(item => {
                let rowContent = '';
                let actionButtons = '';

                switch(item.post_type) {
                    case 'cliente':
                        rowContent = `<td data-label="Nombre"><a href="#" data-spa-link data-view="clientes" data-action="perfil" data-id="${item.ID}" class="font-semibold text-blue-600 hover:underline">${item.title}</a></td><td data-label="Email">${item.email || ''}</td><td data-label="Teléfono">${item.telefono || ''}</td><td data-label="Fecha Reg.">${item.fecha}</td>`;
                        actionButtons = `<a href="#" data-spa-link data-view="${item.view_slug}" data-action="perfil" data-id="${item.ID}" class="btn-icon" title="Ver Perfil"><i class="fas fa-eye"></i></a><a href="#" data-spa-link data-view="${item.view_slug}" data-action="edit" data-id="${item.ID}" class="btn-icon" title="Editar"><i class="fas fa-edit"></i></a><button data-delete-id="${item.ID}" class="btn-icon-danger" title="Eliminar"><i class="fas fa-trash"></i></button>`;
                        break;

                    case 'deuda':
                        const montoDeuda = parseFloat(item.monto_deuda || 0);
                        const montoRestante = parseFloat(item.monto_restante || 0);
                        const fechaVenc = item.fecha_vencimiento ? new Date(item.fecha_vencimiento + 'T00:00:00').toLocaleDateString('es-DO', { year: 'numeric', month: 'short', day: 'numeric' }) : 'N/A';
                        
                        rowContent = `
                            <td data-label="Concepto"><p class="font-semibold text-slate-800">${item.title}</p><p class="text-xs text-slate-500">${item.cliente_nombre}</p></td>
                            <td data-label="Balance"><p class="font-semibold text-slate-700">${formatCurrency(montoRestante, item.divisa)}</p><p class="text-xs text-slate-500">de ${formatCurrency(montoDeuda, item.divisa)}</p></td>
                            <td data-label="Vencimiento">${fechaVenc}</td>
                            <td data-label="Estado"><span class="px-2 py-1 text-xs font-semibold rounded-full ${item.estado_color}">${item.estado}</span></td>`;
                        
                        actionButtons = `
                            <button data-add-payment-id="${item.ID}" data-monto-deuda="${montoDeuda}" data-monto-restante="${montoRestante}" data-divisa="${item.divisa}" class="btn-icon text-green-500" title="Registrar Pago"><i class="fas fa-dollar-sign"></i></button>
                            <button data-send-reminder-id="${item.ID}" class="btn-icon text-sky-500" title="Enviar Recordatorio"><i class="fas fa-paper-plane"></i></button>
                            <a href="#" data-spa-link data-view="${item.view_slug}" data-action="edit" data-id="${item.ID}" class="btn-icon" title="Editar"><i class="fas fa-edit"></i></a>
                            <button data-delete-id="${item.ID}" class="btn-icon-danger" title="Eliminar"><i class="fas fa-trash"></i></button>`;
                        break;
                    
                    default:
                        rowContent = `<td data-label="Caso"><a href="#" data-spa-link data-view="${item.view_slug}" data-action="manage" data-id="${item.ID}" class="font-semibold text-blue-600 hover:underline">${item.title}</a><p class="text-sm text-slate-500">${item.cliente_nombre}</p></td><td data-label="Detalle">${item.ano_fiscal || item.singular_name}</td><td data-label="Estado"><span class="px-2 py-1 text-xs font-semibold rounded-full ${item.estado_color}">${item.estado}</span></td><td data-label="Fecha">${item.fecha}</td>`;
                         actionButtons = `<a href="#" data-spa-link data-view="${item.view_slug}" data-action="manage" data-id="${item.ID}" class="btn-icon" title="Gestionar"><i class="fas fa-tasks"></i></a><a href="#" data-spa-link data-view="${item.view_slug}" data-action="edit" data-id="${item.ID}" class="btn-icon" title="Editar"><i class="fas fa-edit"></i></a><button data-delete-id="${item.ID}" class="btn-icon-danger" title="Eliminar"><i class="fas fa-trash"></i></button>`;
                        break;
                }
                
                return `<tr class="hover:bg-slate-50">${rowContent}<td data-label="Acciones"><div class="flex justify-end items-center space-x-3">${actionButtons}</div></td></tr>`;
            }).join('');
            tableBody.innerHTML = rowsHtml;
        }
    };

    App.init();
});


