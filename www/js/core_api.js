

/**
 * CoreAPI - клиент для работы с core_api.php
 * Модульная структура для управления AJAX-запросами
 */
const CoreAPI = {
    // ====================================
    // API CLIENT - запросы к серверу
    // ====================================
    client: {
        /**
         * Универсальный вызов core_api.php
         * @param {FormData} formData - данные для отправки
         * @returns {Promise<Object>}
         */
        async call(formData) {
            const debugRequests = window.__debugCoreApiRequests;
            if (debugRequests) {
                const payload = formData instanceof FormData
                    ? Object.fromEntries(formData.entries())
                    : {};
                console.log('[core_api][request]', payload.action || 'unknown', payload);
            }
            const response = await fetch('/core_api.php', {
                method: 'POST',
                body: formData
            });
            const data = await this.parseJSON(response);
            if (debugRequests) {
                console.log('[core_api][response]', data);
            }
            return data;
        },
        /**
         * Безопасный парсинг JSON с логированием ошибок
         */
        async parseJSON(response) {
            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch (err) {
                console.error('Invalid JSON from core_api.php:', text);
                throw err;
            }
        }
    },
    // ====================================
    // FORMS - сбор данных из форм
    // ====================================
    forms: {
        /**
         * Собрать FormData для конкретного action
         * @param {string} action
         * @param {HTMLElement} link - элемент, который вызвал действие
         * @returns {FormData}
         */
        build(action, link) {
            const builders = {
                'save_user': () => this.getFormById('user-profile-form'),
                'save_tool': () => this.getFormById('tool-profile-form'),
                'save_device': () => this.getFormById('device-profile-form'),
                'save_cell': () => this.getFormById('cell-profile-form'),
                'add_new_cells': () => this.getFormById('cells-form'),
                'add_new_item_in': () => this.getFormById('item-in-modal-form'),
                'save_item_stock': () => this.getFormById('item-stock-modal-form'),
                'save_permission': () => this.getFormById('permission-form'),
                'save_menu_item': () => this.getFormById('menu-item-form'),
                'save_connector': (currentLink) => {
                    const fd = this.getFormById('connector-form');
                    if (currentLink && currentLink.getAttribute('data-delete') === '1') {
                        fd.append('delete', '1');
                    }
                    return fd;
                },


                'form_edit_user': () => this.withAttribute('user_id', link),
                'form_edit_device': () => this.withAttribute('device_id', link),
                'form_edit_tool_stock': () => this.withAttribute('tool_id', link),
                'form_edit_cell': () => this.withAttribute('cell_id', link),
                'form_edit_connector': () => this.withAttribute('connector_id', link),
                'test_connector': () => this.withAttribute('connector_id', link),
                'manual_confirm_connector': () => this.getFormById('connector-form'),
                'manual_confirm_puppeteer': () => this.getFormById('connector-form'),
                'form_connector_operations': () => {
                    const fd = this.withAttribute('connector_id', link);
                    const openTab = link?.getAttribute('data-open-tab') || '';
                    if (openTab) {
                        fd.append('open_tab', openTab);
                    }
                    return fd;
                },
                'save_connector_operations': () => this.getFormById('connector-operations-form'),
                'test_connector_operations': () => this.getFormById('connector-operations-form'),
                'save_connector_addons': () => this.getFormById('connector-operations-form'),

                'tools_management_open_modal': () => this.withAttribute('tool_id', link),
                'tools_management_open_user_modal': () => this.withAttribute('tool_id', link),
                'tools_management_open_cell_modal': () => this.withAttribute('tool_id', link),

                'delete_cell': () => this.withAttribute('cell_id', link),
                'delete_permission': () => this.withAttribute('permission_code', link),
                'delete_menu_item': () => this.withAttribute('menu_item_id', link),
                'delete_item_in': () => {
                    const fd = this.withAttribute('item_id', link);
                    const batchUid = document.querySelector('#item-in-modal-form [name="batch_uid"]')?.value;
                    if (batchUid) fd.append('batch_uid', batchUid);
                    return fd;
                },
                
                'activate_device': () => {
                    const fd = new FormData();
                    fd.append('device_id', link.getAttribute('data-device-id') || '');
                    fd.append('is_active', link.getAttribute('data-is-active') || '1');
                    return fd;
                },
                
                'commit_item_in_batch': () => this.withAttribute('batch_uid', link),
                'open_item_in_batch': () => this.withAttribute('batch_uid', link),
                'open_item_stock_modal': () => this.withAttribute('item_id', link),
                'warehouse_move_open_modal': () => this.withAttribute('item_id', link),
                'warehouse_move_save_cell': () => this.getFormById('item-stock-modal-form'),
                'warehouse_move_batch_assign': () => {
                    const fd = this.withAttribute('item_id', link);
                    const cellSelect = document.getElementById('warehouse-move-batch-cell');
                    if (cellSelect) {
                        fd.append('cell_id', cellSelect.value || '');
                    }
                    return fd;
                },
                'tools_management_save_move': () => this.getFormById('tool-storage-move-form')
            };
            const fd = builders[action] ? builders[action](link) : new FormData();
            fd.append('action', action);
            return fd;
        },
        getFormById(id) {
            const form = document.getElementById(id);
            return form ? new FormData(form) : new FormData();
        },
        withAttribute(attrName, link) {
            const fd = new FormData();
            const attrKey = `data-${attrName.replace(/_/g, '-')}`;
            const value = link.getAttribute(attrKey);
            if (value) fd.append(attrName, value);
            return fd;
        }
    },
    // ====================================
    // UI - управление интерфейсом
    // ====================================
    ui: {
        /**
         * Универсальная перезагрузка списка
         * @param {string} action - action для загрузки
         */
        async reloadList(action) {
            const main = document.getElementById('main');
            if (!main) return;
            const fd = new FormData();
            fd.append('action', action);
            try {
                const data = await CoreAPI.client.call(fd);
                if (data?.status === 'ok' && data.html) {
                    this.loadMain(data.html);
                } else {
                    console.error(`core_api error (${action}):`, data);
                }
            } catch (err) {
                console.error(`core_api fetch error (${action}):`, err);
            }
        },


        /**
         * Показать HTML в модальном окне
         */
        showModal(html) {
            this.cleanupModalBackdrops();
            const modalBody = document.querySelector('#fullscreenModal .modal-body');
            if (modalBody) {
                modalBody.innerHTML = html;
            }

            const modalEl = document.getElementById('fullscreenModal');
            if (modalEl && window.bootstrap?.Modal) {
                const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                modal.show();
            }
        },
        /**
         * Загрузить HTML в главный контейнер
         */
        loadMain(html) {
            const main = document.getElementById('main');
            if (main) {
                main.innerHTML = html;
                // NEW: page init registry (init scripts for content loaded via innerHTML)
                try {
                    const initEl = main.querySelector('[data-page-init]');
                    const key = initEl?.getAttribute('data-page-init');
                    if (key && CoreAPI.pageInits && typeof CoreAPI.pageInits[key] === 'function') {
                        CoreAPI.pageInits[key]();
                    }
                } catch (e) {
                    console.error('CoreAPI.pageInits init error', e);
                }

                if (typeof emitDeviceContext === 'function') {
                    emitDeviceContext();
                }
                if (CoreAPI.warehouseWithoutCells?.init) {
                    CoreAPI.warehouseWithoutCells.init();
                }
                if (CoreAPI.warehouseInStorage?.init) {
                    CoreAPI.warehouseInStorage.init();
                }
                if (CoreAPI.warehouseMove?.init) {
                    CoreAPI.warehouseMove.init();
                }
                if (CoreAPI.warehouseMoveBatch?.init) {
                    CoreAPI.warehouseMoveBatch.init();
                }
            }
        },
        /**
         * Закрыть модальное окно
         */
        closeModal() {
            const modalEl = document.getElementById('fullscreenModal');
            if (modalEl && window.bootstrap?.Modal) {
                const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                modal.hide();
                setTimeout(() => this.cleanupModalBackdrops(), 300);
                return;
            }
                        this.cleanupModalBackdrops();
        },
        /**
         * Очистить зависшие затемнения/классы модалки
         */
        cleanupModalBackdrops() {
            const openModal = document.querySelector('.modal.show');
            if (openModal) return;
            document.querySelectorAll('.modal-backdrop').forEach((backdrop) => backdrop.remove());
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('padding-right');
            document.body.style.removeProperty('overflow');
        },
        /**
         * Установить callback на закрытие модалки (один раз)
         */
        onModalCloseOnce(callback) {
            const modalEl = document.getElementById('fullscreenModal');
            if (!modalEl || !window.bootstrap?.Modal) {
                callback();
                return;
            }
            const handler = () => {
                modalEl.removeEventListener('hidden.bs.modal', handler);
                callback();
            };

            modalEl.addEventListener('hidden.bs.modal', handler);
        }
    },
    // ====================================

    // PERMISSIONS - вспомогательные методы
    // ====================================
    permissions: {
        fillForm(button) {
            const id = button.getAttribute('data-permission-id') || '';
            const code = button.getAttribute('data-permission-code') || '';
            const name = button.getAttribute('data-permission-name') || '';
            const description = button.getAttribute('data-permission-description') || '';
            const idInput = document.getElementById('permission_id');
            const codeInput = document.getElementById('permission_code');
            const nameInput = document.getElementById('permission_name');
            const descriptionInput = document.getElementById('permission_description');
            if (idInput) idInput.value = id;
            if (codeInput) codeInput.value = code;
            if (nameInput) nameInput.value = name;
            if (descriptionInput) descriptionInput.value = description;
        },
        resetForm() {
            const idInput = document.getElementById('permission_id');
            const codeInput = document.getElementById('permission_code');
            const nameInput = document.getElementById('permission_name');
            const descriptionInput = document.getElementById('permission_description');
            if (idInput) idInput.value = '';
            if (codeInput) codeInput.value = '';
            if (nameInput) nameInput.value = '';
            if (descriptionInput) descriptionInput.value = '';
        }
    },
    // ====================================

    // MENU ITEMS - вспомогательные методы
    // ====================================
    menuItems: {
        fillForm(button) {
            const id = button.getAttribute('data-menu-item-id') || '';
            const key = button.getAttribute('data-menu-item-key') || '';
            const group = button.getAttribute('data-menu-item-group') || '';
            const title = button.getAttribute('data-menu-item-title') || '';
            const icon = button.getAttribute('data-menu-item-icon') || '';
            const action = button.getAttribute('data-menu-item-action') || '';
            const sortOrder = button.getAttribute('data-menu-item-sort') || '0';
            const isActive = button.getAttribute('data-menu-item-active') || '0';
            const idInput = document.getElementById('menu_item_id');
            const keyInput = document.getElementById('menu_item_key');
            const groupInput = document.getElementById('menu_item_group');
            const titleInput = document.getElementById('menu_item_title');
            const iconInput = document.getElementById('menu_item_icon');
            const actionInput = document.getElementById('menu_item_action');
            const sortInput = document.getElementById('menu_item_sort');
            const activeInput = document.getElementById('menu_item_active');
            if (idInput) idInput.value = id;
            if (keyInput) keyInput.value = key;
            if (groupInput) groupInput.value = group;
            if (titleInput) titleInput.value = title;
            if (iconInput) iconInput.value = icon;
            if (actionInput) actionInput.value = action;
            if (sortInput) sortInput.value = sortOrder;
            if (activeInput) activeInput.checked = isActive === '1';
        },
        resetForm() {
            const idInput = document.getElementById('menu_item_id');
            const keyInput = document.getElementById('menu_item_key');
            const groupInput = document.getElementById('menu_item_group');
            const titleInput = document.getElementById('menu_item_title');
            const iconInput = document.getElementById('menu_item_icon');
            const actionInput = document.getElementById('menu_item_action');
            const sortInput = document.getElementById('menu_item_sort');
            const activeInput = document.getElementById('menu_item_active');
            if (idInput) idInput.value = '';
            if (keyInput) keyInput.value = '';
            if (groupInput) groupInput.value = '';
            if (titleInput) titleInput.value = '';
            if (iconInput) iconInput.value = '';
            if (actionInput) actionInput.value = '';
            if (sortInput) sortInput.value = '0';
            if (activeInput) activeInput.checked = true;
        }
    },

    // ====================================
    // HANDLERS - обработчики ответов по action
    // ====================================
    handlers: {
        // === USERS ===
        'users_regen_qr': (data) => {
            alert(data.message || 'QR-коды обновлены');
        },
        'form_new_user': (data) => {
            CoreAPI.ui.showModal(data.html);
        },
        'form_edit_user': (data) => {
            CoreAPI.ui.showModal(data.html);
        },
        'save_user': async (data, link, formData) => {
            if (data.deleted) {
                alert(data.message || 'Пользователь удалён');
                CoreAPI.ui.onModalCloseOnce(() => CoreAPI.ui.reloadList('view_users'));
                CoreAPI.ui.closeModal();
                return;
            }
            alert(data.message || 'Сохранено');
            
            const newUserId = data.user_id;
            CoreAPI.ui.onModalCloseOnce(() => CoreAPI.ui.reloadList('view_users'));
            if (newUserId) {
                const fd = new FormData();
                fd.append('action', 'form_edit_user');
                fd.append('user_id', newUserId);
                const d2 = await CoreAPI.client.call(fd);
                if (d2?.status === 'ok') {
                    CoreAPI.ui.showModal(d2.html);
                }
            }
        },
        // === TOOLS ===
        'form_new_tool_stock': (data) => {
            CoreAPI.ui.showModal(data.html);
        },
        'form_edit_tool_stock': (data) => {
            CoreAPI.ui.showModal(data.html);
        },
        'save_tool': async (data) => {
            if (data.deleted) {
                alert(data.message || 'Инструмент удалён');
                CoreAPI.ui.onModalCloseOnce(() => CoreAPI.ui.reloadList('view_tools_stock'));
                CoreAPI.ui.closeModal();
                return;
            }
            alert(data.message || 'Сохранено');
            const newToolId = data.tool_id;
            CoreAPI.ui.onModalCloseOnce(() => CoreAPI.ui.reloadList('view_tools_stock'));

            if (newToolId) {
                const fd = new FormData();
                fd.append('action', 'form_edit_tool_stock');
                fd.append('tool_id', newToolId);
                const d2 = await CoreAPI.client.call(fd);
                if (d2?.status === 'ok') {
                    CoreAPI.ui.showModal(d2.html);
                }
            }
        },
        'upload_tool_photo': (data) => {
            if (typeof renderToolPhotos === 'function') {
                renderToolPhotos(data.photos || []);
            }
        },
        // === DEVICES ===
        'form_edit_device': (data) => {
            CoreAPI.ui.showModal(data.html);
        },
        'activate_device': async (data) => {
            alert(data.message || 'OK');
            await CoreAPI.ui.reloadList('view_devices');
        },
        'save_device': (data) => {
            if (data.deleted) {
                alert(data.message || 'Устройство удалено');
                CoreAPI.ui.onModalCloseOnce(() => CoreAPI.ui.reloadList('view_devices'));
                CoreAPI.ui.closeModal();
                return;
            }
            alert(data.message || 'Сохранено');
            CoreAPI.ui.closeModal();
            CoreAPI.ui.reloadList('view_devices');
        },
        // === WAREHOUSE - Cells ===
        'form_edit_cell': (data) => {
            CoreAPI.ui.showModal(data.html);
        },
        // === CONNECTORS ===
        'form_new_connector': (data) => {
            CoreAPI.ui.showModal(data.html);
            if (CoreAPI.connectors?.initForm) {
                CoreAPI.connectors.initForm();
            }
        },
        'form_edit_connector': (data) => {
            CoreAPI.ui.showModal(data.html);
            if (CoreAPI.connectors?.initForm) {
                CoreAPI.connectors.initForm();
            }
        },

        'form_connector_operations': (data) => {
            CoreAPI.ui.showModal(data.html);
        },
        'save_connector_operations': async (data) => {
            alert(data.message || 'Операции сохранены');
            await CoreAPI.ui.reloadList('view_connectors');
            const connectorId = data.connector_id;
            if (connectorId) {
                const fd = new FormData();
                fd.append('action', 'form_connector_operations');
                fd.append('connector_id', connectorId);
                const d2 = await CoreAPI.client.call(fd);
                if (d2?.status === 'ok') {
                    CoreAPI.ui.showModal(d2.html);
                }
            }
        },


        'save_connector_addons': async (data) => {
            alert(data.message || 'ДопИнфо сохранено');
            await CoreAPI.ui.reloadList('view_connectors');
            const connectorId = data.connector_id;
            if (connectorId) {
                const fd = new FormData();
                fd.append('action', 'form_connector_operations');
                fd.append('connector_id', connectorId);
                const d2 = await CoreAPI.client.call(fd);
                if (d2?.status === 'ok') {
                    CoreAPI.ui.showModal(d2.html);
                }
            }
        },

        'test_connector_operations': async (data) => {
            alert(data.message || 'Тест операции выполнен');
            const connectorId = data.connector_id;
            if (connectorId) {
                const fd = new FormData();
                fd.append('action', 'form_connector_operations');
                fd.append('connector_id', connectorId);
                const d2 = await CoreAPI.client.call(fd);
                if (d2?.status === 'ok') {
                    CoreAPI.ui.showModal(d2.html);
                }
            }
        },
        'form_connector_operations': (data) => {
            CoreAPI.ui.showModal(data.html);
        },
        'save_connector_operations': async (data) => {
            alert(data.message || 'Операции сохранены');
            await CoreAPI.ui.reloadList('view_connectors');
            const connectorId = data.connector_id;
            if (connectorId) {
                const fd = new FormData();
                fd.append('action', 'form_connector_operations');
                fd.append('connector_id', connectorId);
                const d2 = await CoreAPI.client.call(fd);
                if (d2?.status === 'ok') {
                    CoreAPI.ui.showModal(d2.html);
                }
            }
        },
        'save_connector': async (data) => {
            if (data.deleted) {
                alert(data.message || 'Коннектор удалён');
                CoreAPI.ui.onModalCloseOnce(() => CoreAPI.ui.reloadList('view_connectors'));
                CoreAPI.ui.closeModal();
                return;
            }
            alert(data.message || 'Сохранено');
            CoreAPI.ui.closeModal();
            await CoreAPI.ui.reloadList('view_connectors');
        },
        'test_connector': async (data) => {
            if (data.ok) {
                alert(data.message || 'Проверка прошла успешно');
            } else {
                alert(data.message || 'Проверка завершилась ошибкой');
            }
            await CoreAPI.ui.reloadList('view_connectors');
            const connectorId = data.connector_id;
            if (connectorId) {
                const fd = new FormData();
                fd.append('action', 'form_edit_connector');
                fd.append('connector_id', connectorId);
                const d2 = await CoreAPI.client.call(fd);
                if (d2?.status === 'ok') {
                    CoreAPI.ui.showModal(d2.html);
                    if (CoreAPI.connectors?.initForm) {
                        CoreAPI.connectors.initForm();
                    }
                }
            }
        },
        'manual_confirm_connector': async (data) => {
            alert(data.message || 'Токен обновлён');
            await CoreAPI.ui.reloadList('view_connectors');
            const connectorId = data.connector_id;
            if (connectorId) {
                const fd = new FormData();
                fd.append('action', 'form_edit_connector');
                fd.append('connector_id', connectorId);
                const d2 = await CoreAPI.client.call(fd);
                if (d2?.status === 'ok') {
                    CoreAPI.ui.showModal(d2.html);
                    if (CoreAPI.connectors?.initForm) {
                        CoreAPI.connectors.initForm();
                    }
                }
            }
        },
        'manual_confirm_puppeteer': async (data) => {
            if (data.status === 'ok') {
                alert(data.message || 'Токен обновлён');
            } else {
                alert(data.message || 'Не удалось обновить токен');
            }
            await CoreAPI.ui.reloadList('view_connectors');
            const connectorId = data.connector_id;
            if (connectorId) {
                const fd = new FormData();
                fd.append('action', 'form_edit_connector');
                fd.append('connector_id', connectorId);
                const d2 = await CoreAPI.client.call(fd);
                if (d2?.status === 'ok') {
                    CoreAPI.ui.showModal(d2.html);
                    if (CoreAPI.connectors?.initForm) {
                        CoreAPI.connectors.initForm();
                    }
                }
            }
        },
        'add_new_cells': (data) => {
            alert(data.message || 'Ячейки добавлены');
            if (data.html) {
                CoreAPI.ui.loadMain(data.html);
            }
        },

        'delete_cell': (data) => {
            alert(data.message || 'Ячейка удалена');
            if (data.html) {
                CoreAPI.ui.loadMain(data.html);
            }
        },
        'save_cell': (data) => {
            alert(data.message || 'Сохранено');
            CoreAPI.ui.onModalCloseOnce(() => CoreAPI.ui.reloadList('setting_cells'));
            CoreAPI.ui.closeModal();
        },
        // === WAREHOUSE - Item In ===
        'open_item_in_batch': (data) => {
            CoreAPI.ui.showModal(data.html);
            if (typeof initStandDevicePersistence === 'function') {
                initStandDevicePersistence();
            }
            if (typeof initItemInDuplicateCheck === 'function') {
                initItemInDuplicateCheck();
            }
            if (typeof initReceiverAddressQuickCells === 'function') {
                initReceiverAddressQuickCells();
            }
            if (typeof emitDeviceContext === 'function') {
                emitDeviceContext();
            }
            CoreAPI.ui.onModalCloseOnce(() => CoreAPI.ui.reloadList('warehouse_item_in'));
        },
        'add_new_item_in': async (data) => {
            alert(data.message || 'Посылка добавлена');
            const batchUid = data.batch_uid;
            if (batchUid) {
                const fd = new FormData();
                fd.append('action', 'open_item_in_batch');
                fd.append('batch_uid', batchUid);
                const d2 = await CoreAPI.client.call(fd);
                if (d2?.status === 'ok') {
                    const modalBody = document.querySelector('#fullscreenModal .modal-body');
                    if (modalBody) {
                        modalBody.innerHTML = d2.html;
                        if (typeof initStandDevicePersistence === 'function') {
                            initStandDevicePersistence();
                        }
                        if (typeof initItemInDuplicateCheck === 'function') {
                            initItemInDuplicateCheck();
                        }
                        if (typeof initReceiverAddressQuickCells === 'function') {
                            initReceiverAddressQuickCells();
                        }
                        if (typeof emitDeviceContext === 'function') {
                            emitDeviceContext();
                        }
                    }
                }
            }
        },
        'delete_item_in': (data) => {
            alert(data.message || 'Посылка удалена');
            if (data.html) {
                const modalBody = document.querySelector('#fullscreenModal .modal-body');
                if (modalBody) {
                    modalBody.innerHTML = data.html;
                    if (typeof initStandDevicePersistence === 'function') {
                        initStandDevicePersistence();
                    }
                    if (typeof initItemInDuplicateCheck === 'function') {
                        initItemInDuplicateCheck();
                    }
                    if (typeof initReceiverAddressQuickCells === 'function') {
                        initReceiverAddressQuickCells();
                    }
                    if (typeof emitDeviceContext === 'function') {
                        emitDeviceContext();
                    }
                }
            }
        },
        'commit_item_in_batch': (data) => {
            alert(data.message || 'Партия завершена');
            CoreAPI.ui.closeModal();
            CoreAPI.ui.reloadList('warehouse_item_in');
        },
        // === WAREHOUSE - Stock ===
        'open_item_stock_modal': (data) => {
            CoreAPI.ui.showModal(data.html);
            if (typeof initStandDevicePersistence === 'function') {
                initStandDevicePersistence();
            }
            if (typeof initWarehouseStockAddons === 'function') {
                initWarehouseStockAddons();
            }
            if (typeof initWarehouseItemStockPhotoButtons === 'function') {
                initWarehouseItemStockPhotoButtons();
            }
        },
        'warehouse_move_open_modal': (data) => {
            CoreAPI.ui.showModal(data.html);
            if (typeof initStandDevicePersistence === 'function') {
                initStandDevicePersistence();
            }
        },
        'tools_management_open_modal': (data) => {
            setToolsManagementModalType('move');
            CoreAPI.ui.showModal(data.html);
        },
        'tools_management_open_user_modal': (data) => {
            setToolsManagementModalType('user');
            CoreAPI.ui.showModal(data.html);
        },
        'tools_management_open_cell_modal': (data) => {
            setToolsManagementModalType('cell');
            CoreAPI.ui.showModal(data.html);
        },
        'save_item_stock': (data) => {
            alert(data.message || 'Сохранено');
            CoreAPI.ui.onModalCloseOnce(() => {
                if (CoreAPI.warehouseWithoutCells?.resetAndLoad) {
                    CoreAPI.warehouseWithoutCells.resetAndLoad();
                }
                if (CoreAPI.warehouseWithoutAddons?.resetAndLoad) {
                    CoreAPI.warehouseWithoutAddons.resetAndLoad();
                }
                if (CoreAPI.warehouseInStorage?.resetAndLoad) {
                    CoreAPI.warehouseInStorage.resetAndLoad();
                }
            });
            CoreAPI.ui.closeModal();
        },

        'warehouse_move_save_cell': (data) => {
            alert(data.message || 'Сохранено');
            CoreAPI.ui.onModalCloseOnce(() => {
                const searchValue = CoreAPI.warehouseMove?.searchInput?.value?.trim() || '';
                if (searchValue && CoreAPI.warehouseMove?.fetchResults) {
                    CoreAPI.warehouseMove.fetchResults(searchValue);
                } else if (CoreAPI.warehouseMove?.clearResults) {
                    CoreAPI.warehouseMove.clearResults();
                }
            });
            CoreAPI.ui.closeModal();
        },
        'warehouse_move_batch_assign': (data) => {
            alert(data.message || 'Сохранено');
            if (CoreAPI.warehouseMoveBatch?.clearAfterMove) {
                CoreAPI.warehouseMoveBatch.clearAfterMove();
            }
        },
        'tools_management_save_move': (data) => {
            alert(data.message || 'Сохранено');
            CoreAPI.ui.onModalCloseOnce(() => {
                const searchValue = CoreAPI.toolsManagement?.searchInput?.value?.trim() || '';
                if (CoreAPI.toolsManagement?.fetchResults) {
                    CoreAPI.toolsManagement.fetchResults(searchValue);
                }
            });
            CoreAPI.ui.closeModal();
        },
        'save_permission': async (data) => {
            alert(data.message || 'Сохранено');
            await CoreAPI.ui.reloadList('view_role_permissions');
        },
        'delete_permission': async (data) => {
            alert(data.message || 'Удалено');
            await CoreAPI.ui.reloadList('view_role_permissions');
        },

        'save_menu_item': async (data) => {
            alert(data.message || 'Сохранено');
            await CoreAPI.ui.reloadList('view_role_permissions');
        },
        'delete_menu_item': async (data) => {
            alert(data.message || 'Удалено');
            await CoreAPI.ui.reloadList('view_role_permissions');
        },
        // === DEFAULT - все остальные ===
        'default': (data) => {
            if (data.html) {
                CoreAPI.ui.loadMain(data.html);
            }
        }
    },
    // ====================================
    // EVENTS - обработка событий
    // ====================================
    events: {
        /**
         * Обработчик кликов по .js-core-link
         */
        async handleClick(e) {

            const editPermission = e.target.closest('.js-permission-edit');
            if (editPermission) {
                e.preventDefault();
                CoreAPI.permissions.fillForm(editPermission);
                return;
            }
            const resetPermission = e.target.closest('.js-permission-reset');
            if (resetPermission) {
                e.preventDefault();
                CoreAPI.permissions.resetForm();
                return;
            }

            const editMenuItem = e.target.closest('.js-menu-item-edit');
            if (editMenuItem) {
                e.preventDefault();
                CoreAPI.menuItems.fillForm(editMenuItem);
                return;
            }
            const resetMenuItem = e.target.closest('.js-menu-item-reset');
            if (resetMenuItem) {
                e.preventDefault();
                CoreAPI.menuItems.resetForm();
                return;
            }
            const link = e.target.closest('.js-core-link[data-core-action]');
            if (!link) return;
            e.preventDefault();
            const action = link.getAttribute('data-core-action');
            if (!action) return;
                        if (action === 'warehouse_move_batch_assign') {
                const cellSelect = document.getElementById('warehouse-move-batch-cell');
                if (!cellSelect || !cellSelect.value) {
                    alert('Выберите ячейку склада');
                    cellSelect?.focus();
                    return;
                }
            }
            if (action === 'delete_permission') {
                const code = link.getAttribute('data-permission-code') || '';
                if (!confirm(`Удалить право ${code}?`)) {
                    return;
                }
            }
            if (action === 'delete_menu_item') {
                const menuKey = link.getAttribute('data-menu-item-key') || '';
                if (!confirm(`Удалить пункт меню ${menuKey || 'выбранный'}?`)) {
                    return;
                }
            }
            if (action === 'save_connector' && link.getAttribute('data-delete') === '1') {
                if (!confirm('Удалить коннектор?')) {
                    return;
                }
            }
            // Специальная обработка для upload_tool_photo
            if (action === 'upload_tool_photo') {
                const fileInput = document.getElementById('tool-photo-input');
                const toolId = link.getAttribute('data-tool-id') || 
                              document.getElementById('tool_id')?.value || '';
                if (!toolId) {
                    alert('Сначала сохраните инструмент');
                    return;
                }
                if (fileInput) {
                    fileInput.value = '';
                    fileInput.dataset.toolId = toolId;
                    fileInput.click();
                }
                return;
            }
            if (action === 'upload_item_stock_photo') {
                const itemId = document.querySelector('#item-stock-modal-form input[name="item_id"]')?.value || '';
                const photoType = link.getAttribute('data-photo-type') || '';
                const inputId = photoType === 'label' ? 'warehouseStockLabelPhotoInput' : 'warehouseStockBoxPhotoInput';
                const fileInput = document.getElementById(inputId);
                if (!itemId || !photoType || !fileInput) {
                    alert('Не удалось подготовить загрузку фото');
                    return;
                }
                fileInput.value = '';
                fileInput.dataset.itemId = itemId;
                fileInput.dataset.photoType = photoType;
                fileInput.click();
                return;
            }
            // Подсветка активного пункта меню
            const ul = link.closest('ul');
            if (ul) {
                ul.querySelectorAll('.active').forEach(el => el.classList.remove('active'));
                link.classList.add('active');
            }
            // Собираем FormData
            const formData = CoreAPI.forms.build(action, link);
            // Отладка
            console.log('FormData entries:');
            for (const [k, v] of formData.entries()) {
                console.log(k, '=>', v);
            }
            // Вызываем API
            try {
                const data = await CoreAPI.client.call(formData);
                if (!data || data.status !== 'ok') {
                    console.error('core_api error:', data);

                    const stepLog = Array.isArray(data?.step_log) ? data.step_log : [];
                    const artifactsDir = typeof data?.artifacts_dir === 'string' ? data.artifacts_dir.trim() : '';
                    if (stepLog.length > 0) {
                        console.group('connector step log');
                        stepLog.forEach((entry, idx) => {
                            const ts = entry?.time || '';
                            const step = entry?.step || 'step';
                            const msg = entry?.message || '';
                            console.log(`#${idx + 1} [${ts}] ${step}: ${msg}`, entry?.meta || {});
                        });

                        const screenshotPaths = Array.from(new Set(stepLog
                            .map((entry) => String(entry?.screenshot || '').trim())
                            .filter(Boolean)));

                        if (artifactsDir) {
                            console.log('artifacts_dir:', artifactsDir);
                        }

                        if (screenshotPaths.length > 0) {
                            console.group('connector screenshots');
                            screenshotPaths.forEach((fullPath, idx) => {
                                const publicUrl = CoreAPI.events.toPublicArtifactUrl(fullPath);
                                console.log(`${idx + 1}. ${publicUrl}`);
                            });
                            console.groupEnd();
                        }

                        console.groupEnd();
                    }

                    const logHint = stepLog.length > 0
                        ? '\n\nПошаговый лог выведен в консоль браузера (connector step log).\nСсылки на скриншоты — в группе connector screenshots.' + (artifactsDir ? `\nПапка артефактов: ${artifactsDir}` : '')
                        : '';
                    alert((data?.message || 'Ошибка при выполнении запроса') + logHint);
                    return;
                }
                // Вызываем обработчик
                const handler = CoreAPI.handlers[action] || CoreAPI.handlers['default'];
                await handler(data, link, formData);
            } catch (err) {
                console.error('core_api fetch error:', err);
                alert('Ошибка связи с сервером');
                // Fallback для commit_item_in_batch
                if (action === 'commit_item_in_batch') {
                    CoreAPI.ui.reloadList('warehouse_item_in');
                }
            }
        },


        toPublicArtifactUrl(filePath) {
            const normalized = String(filePath || '').trim();
            if (!normalized) return '';

            if (/^https?:\/\//i.test(normalized)) return normalized;

            const marker = '/www/';
            const idx = normalized.lastIndexOf(marker);
            if (idx >= 0) {
                const webPath = normalized.slice(idx + marker.length);
                const base = `${window.location.protocol}//${window.location.host}`;
                return `${base}/${webPath}`;
            }

            if (normalized.startsWith('/')) {
                const base = `${window.location.protocol}//${window.location.host}`;
                return `${base}${normalized}`;
            }

            return normalized;
        },
        /**
         * Обработчик загрузки фото инструмента
         */
        async handlePhotoUpload(e) {
            const input = e.target;
            if (!(input instanceof HTMLInputElement) || input.id !== 'tool-photo-input') return;
            const file = input.files?.[0];
            const toolId = input.dataset.toolId || document.getElementById('tool_id')?.value || '';
            if (!file || !toolId) return;
            const fd = new FormData();
            fd.append('action', 'upload_tool_photo');
            fd.append('tool_id', toolId);
            fd.append('photo', file);
            try {
                const data = await CoreAPI.client.call(fd);

                if (!data || data.status !== 'ok') {
                    alert(data?.message || 'Ошибка загрузки фото');
                    return;
                }
                if (typeof renderToolPhotos === 'function') {
                    renderToolPhotos(data.photos || []);
                }
            } catch (err) {
                console.error('core_api upload_tool_photo error:', err);
                alert('Ошибка связи с сервером');
            }

        },

        async handleWarehouseItemStockPhotoUpload(e) {
            const input = e.target;
            const validIds = ['warehouseStockLabelPhotoInput', 'warehouseStockBoxPhotoInput'];
            if (!(input instanceof HTMLInputElement) || !validIds.includes(input.id)) return;

            const file = input.files?.[0];
            const itemId = input.dataset.itemId || document.querySelector('#item-stock-modal-form input[name="item_id"]')?.value || '';
            const photoType = input.dataset.photoType || input.getAttribute('data-photo-type') || '';
            if (!file || !itemId || !photoType) return;

            const fd = new FormData();
            fd.append('action', 'upload_item_stock_photo');
            fd.append('item_id', itemId);
            fd.append('photo_type', photoType);
            fd.append('photo', file);

            try {
                const data = await CoreAPI.client.call(fd);
                if (!data || data.status !== 'ok') {
                    alert(data?.message || 'Ошибка загрузки фото');
                    return;
                }
                if (typeof window.setWarehouseItemStockPhotoState === 'function') {
                    window.setWarehouseItemStockPhotoState(photoType, data.path || '', data.json || '');
                }
            } catch (err) {
                console.error('core_api upload_item_stock_photo error:', err);
                alert('Ошибка связи с сервером');
            }
        },
        async handleRolePermissionToggle(e) {
            const checkbox = e.target.closest('.js-role-permission-toggle');
            if (!checkbox || !(checkbox instanceof HTMLInputElement)) return;
            const roleCode = checkbox.getAttribute('data-role-code') || '';
            const permissionCode = checkbox.getAttribute('data-permission-code') || '';
            if (!roleCode || !permissionCode) return;
            const original = !checkbox.checked;
            const fd = new FormData();
            fd.append('action', 'toggle_role_permission');
            fd.append('role_code', roleCode);
            fd.append('permission_code', permissionCode);
            fd.append('is_allowed', checkbox.checked ? '1' : '0');
            try {
                const data = await CoreAPI.client.call(fd);
                if (!data || data.status !== 'ok') {
                    checkbox.checked = original;
                    alert(data?.message || 'Ошибка обновления прав');
                }
            } catch (err) {
                console.error('core_api toggle_role_permission error:', err);
                checkbox.checked = original;
                alert('Ошибка связи с сервером');
            }
        }
    },
    // ====================================
    // WAREHOUSE - Parcels without cells
    // ====================================
    warehouseWithoutCells: {
        root: null,
        tbody: null,
        total: null,
        searchInput: null,
        limitSelect: null,
        sortSelect: null,
        sentinel: null,
        observer: null,
        searchTimer: null,
        state: {
            limit: '20',
            offset: 0,
            sort: 'DESC',
            search: '',
            loading: false,
            done: false
        },
        init() {
            const root = document.getElementById('warehouse-without-cells');
            if (!root) return;
            this.root = root;
            this.tbody = root.querySelector('#warehouse-without-cells-tbody');
            this.total = root.querySelector('#warehouse-without-cells-total');
            this.searchInput = root.querySelector('#warehouse-without-cells-search');
            this.limitSelect = root.querySelector('#warehouse-without-cells-limit');
            this.sortSelect = root.querySelector('#warehouse-without-cells-sort');
            this.sentinel = root.querySelector('#warehouse-without-cells-sentinel');

            if (!this.tbody || !this.total || !this.searchInput || !this.limitSelect || !this.sortSelect || !this.sentinel) {
                return;
            }

            this.state.limit = this.limitSelect.value || '20';
            this.state.sort = this.sortSelect.value || 'DESC';
            this.state.search = '';
            this.state.offset = 0;
            this.state.done = false;

            this.bindEvents();
            this.setupObserver();
            this.resetAndLoad();
        },
        bindEvents() {
            this.limitSelect.addEventListener('change', () => {
                this.state.limit = this.limitSelect.value || '20';
                this.resetAndLoad();
            });

            this.sortSelect.addEventListener('change', () => {
                this.state.sort = this.sortSelect.value || 'DESC';
                this.resetAndLoad();
            });

            this.searchInput.addEventListener('input', () => {
                if (this.searchTimer) {
                    clearTimeout(this.searchTimer);
                }
                this.searchTimer = setTimeout(() => {
                    this.state.search = this.searchInput.value.trim();
                    this.resetAndLoad();
                }, 300);
            });
        },
        setupObserver() {
            if (this.observer) {
                this.observer.disconnect();
            }
            this.observer = new IntersectionObserver((entries) => {
                const entry = entries[0];
                if (entry?.isIntersecting) {
                    this.loadNext();
                }
            }, { rootMargin: '200px' });
            this.observer.observe(this.sentinel);
        },
        resetAndLoad() {
            this.state.offset = 0;
            this.state.done = false;
            const columnCount = this.tbody.closest('table')?.querySelectorAll('thead th').length || 3;
            this.tbody.innerHTML = `
                <tr>
                    <td colspan="${columnCount}" class="text-center text-muted">Загрузка...</td>
                </tr>
            `;
            if (this.total) {
                this.total.textContent = '0';
            }
            this.loadNext();
        },
        setLoading(isLoading) {
            this.state.loading = isLoading;
            if (this.sentinel) {
                this.sentinel.textContent = isLoading ? 'Загрузка...' : '';
            }
        },
        async loadNext() {
            if (this.state.loading || this.state.done) return;
            if (this.state.limit === 'all' && this.state.offset > 0) return;
            this.setLoading(true);
            const fd = new FormData();
            fd.append('action', 'item_stock_without_cells');
            fd.append('limit', this.state.limit);
            fd.append('offset', String(this.state.offset));
            fd.append('sort', this.state.sort);
            fd.append('search', this.state.search);
            try {
                const data = await CoreAPI.client.call(fd);
                if (!data || data.status !== 'ok') {
                    console.error('core_api error (item_stock_without_cells):', data);
                    this.setLoading(false);
                    return;
                }
                if (this.state.offset === 0) {
                    this.tbody.innerHTML = data.html || '';
                } else if (data.html) {
                    this.tbody.insertAdjacentHTML('beforeend', data.html);
                }
                if (this.total) {
                    this.total.textContent = String(data.total ?? 0);
                }
                const loaded = Number(data.items_count ?? 0);
                this.state.offset += loaded;
                if (this.state.limit === 'all') {
                    this.state.done = true;
                } else {
                    this.state.done = loaded === 0 || data.has_more === false;
                }
            } catch (err) {
                console.error('core_api fetch error (item_stock_without_cells):', err);
            } finally {
                this.setLoading(false);
            }
        }
    },
    // ====================================

    // WAREHOUSE - Parcels without addons
    // ====================================
    warehouseWithoutAddons: {
        root: null,
        tbody: null,
        total: null,
        searchInput: null,
        limitSelect: null,
        sortSelect: null,
        sentinel: null,
        observer: null,
        searchTimer: null,
        initialized: false,
        state: {
            limit: '20',
            offset: 0,
            sort: 'DESC',
            search: '',
            loading: false,
            done: false
        },
        init() {
            const root = document.getElementById('warehouse-without-addons');
            if (!root) return;
            if (this.initialized && this.root === root) {
                return;
            }
            this.root = root;
            this.tbody = root.querySelector('#warehouse-without-addons-tbody');
            this.total = root.querySelector('#warehouse-without-addons-total');
            this.searchInput = root.querySelector('#warehouse-without-addons-search');
            this.limitSelect = root.querySelector('#warehouse-without-addons-limit');
            this.sortSelect = root.querySelector('#warehouse-without-addons-sort');
            this.sentinel = root.querySelector('#warehouse-without-addons-sentinel');

            if (!this.tbody || !this.total || !this.searchInput || !this.limitSelect || !this.sortSelect || !this.sentinel) {
                return;
            }

            this.state.limit = this.limitSelect.value || '20';
            this.state.sort = this.sortSelect.value || 'DESC';
            this.state.search = '';
            this.state.offset = 0;
            this.state.done = false;

            this.bindEvents();
            this.setupObserver();
            this.resetAndLoad();
            this.initialized = true;
        },
        bindEvents() {
            this.limitSelect.addEventListener('change', () => {
                this.state.limit = this.limitSelect.value || '20';
                this.resetAndLoad();
            });

            this.sortSelect.addEventListener('change', () => {
                this.state.sort = this.sortSelect.value || 'DESC';
                this.resetAndLoad();
            });

            this.searchInput.addEventListener('input', () => {
                if (this.searchTimer) {
                    clearTimeout(this.searchTimer);
                }
                this.searchTimer = setTimeout(() => {
                    this.state.search = this.searchInput.value.trim();
                    this.resetAndLoad();
                }, 300);
            });
        },
        setupObserver() {
            if (this.observer) {
                this.observer.disconnect();
            }
            this.observer = new IntersectionObserver((entries) => {
                const entry = entries[0];
                if (entry?.isIntersecting) {
                    this.loadNext();
                }
            }, { rootMargin: '200px' });
            this.observer.observe(this.sentinel);
        },
        resetAndLoad() {
            this.state.offset = 0;
            this.state.done = false;
            const columnCount = this.tbody.closest('table')?.querySelectorAll('thead th').length || 3;
            this.tbody.innerHTML = `
                <tr>
                    <td colspan="${columnCount}" class="text-center text-muted">Загрузка...</td>
                </tr>
            `;
            if (this.total) {
                this.total.textContent = '0';
            }
            this.loadNext();
        },
        setLoading(isLoading) {
            this.state.loading = isLoading;
            if (this.sentinel) {
                this.sentinel.textContent = isLoading ? 'Загрузка...' : '';
            }
        },
        async loadNext() {
            if (this.state.loading || this.state.done) return;
            if (this.state.limit === 'all' && this.state.offset > 0) return;
            this.setLoading(true);
            const fd = new FormData();
            fd.append('action', 'item_stock_without_addons');
            fd.append('limit', this.state.limit);
            fd.append('offset', String(this.state.offset));
            fd.append('sort', this.state.sort);
            fd.append('search', this.state.search);
            try {
                const data = await CoreAPI.client.call(fd);
                if (!data || data.status !== 'ok') {
                    console.error('core_api error (item_stock_without_addons):', data);
                    this.setLoading(false);
                    return;
                }
                if (this.state.offset === 0) {
                    this.tbody.innerHTML = data.html || '';
                } else if (data.html) {
                    this.tbody.insertAdjacentHTML('beforeend', data.html);
                }
                if (this.total) {
                    this.total.textContent = String(data.total ?? 0);
                }
                const loaded = Number(data.items_count ?? 0);
                this.state.offset += loaded;
                if (this.state.limit === 'all') {
                    this.state.done = true;
                } else {
                    this.state.done = loaded === 0 || data.has_more === false;
                }
            } catch (err) {
                console.error('core_api fetch error (item_stock_without_addons):', err);
            } finally {
                this.setLoading(false);
            }
        }
    },
    // ====================================
    // WAREHOUSE - Parcels in storage (with cells)
    // ====================================
    warehouseInStorage: {
        root: null,
        tbody: null,
        total: null,
        searchInput: null,
        limitSelect: null,
        sortSelect: null,
        sentinel: null,
        observer: null,
        searchTimer: null,
        initialized: false,
        state: {
            limit: '20',
            offset: 0,
            sort: 'DESC',
            search: '',
            loading: false,
            done: false
        },
        init() {
            const root = document.getElementById('warehouse-in-storage');
            if (!root) return;
            if (this.initialized && this.root === root) {
                this.resetAndLoad();
                return;
            }
            if (this.observer) {
                this.observer.disconnect();
            }
            this.root = root;
            this.tbody = root.querySelector('#warehouse-in-storage-tbody');
            this.total = root.querySelector('#warehouse-in-storage-total');
            this.searchInput = root.querySelector('#warehouse-in-storage-search');
            this.limitSelect = root.querySelector('#warehouse-in-storage-limit');
            this.sortSelect = root.querySelector('#warehouse-in-storage-sort');
            this.sentinel = root.querySelector('#warehouse-in-storage-sentinel');

            if (!this.tbody || !this.total || !this.searchInput || !this.limitSelect || !this.sortSelect || !this.sentinel) {
                return;
            }

            this.state.limit = this.limitSelect.value || '20';
            this.state.sort = this.sortSelect.value || 'DESC';
            this.state.search = '';
            this.state.offset = 0;
            this.state.done = false;

            this.bindEvents();
            this.setupObserver();
            this.resetAndLoad();
            this.initialized = true;
        },
        bindEvents() {
            this.limitSelect.addEventListener('change', () => {
                this.state.limit = this.limitSelect.value || '20';
                this.resetAndLoad();
            });

            this.sortSelect.addEventListener('change', () => {
                this.state.sort = this.sortSelect.value || 'DESC';
                this.resetAndLoad();
            });

            this.searchInput.addEventListener('input', () => {
                if (this.searchTimer) {
                    clearTimeout(this.searchTimer);
                }
                this.searchTimer = setTimeout(() => {
                    this.state.search = this.searchInput.value.trim();
                    this.resetAndLoad();
                }, 300);
            });
        },
        setupObserver() {
            if (this.observer) {
                this.observer.disconnect();
            }
            this.observer = new IntersectionObserver((entries) => {
                const entry = entries[0];
                if (entry?.isIntersecting) {
                    this.loadNext();
                }
            }, { rootMargin: '200px' });
            this.observer.observe(this.sentinel);
        },
        resetAndLoad() {
            this.state.offset = 0;
            this.state.done = false;
            const columnCount = this.tbody.closest('table')?.querySelectorAll('thead th').length || 4;
            this.tbody.innerHTML = `
                <tr>
                    <td colspan="${columnCount}" class="text-center text-muted">Загрузка...</td>
                </tr>
            `;
            if (this.total) {
                this.total.textContent = '0';
            }
            this.loadNext();
        },
        setLoading(isLoading) {
            this.state.loading = isLoading;
            if (this.sentinel) {
                this.sentinel.textContent = isLoading ? 'Загрузка...' : '';
            }
        },
        async loadNext() {
            if (this.state.loading || this.state.done) return;
            if (this.state.limit === 'all' && this.state.offset > 0) return;
            this.setLoading(true);
            const fd = new FormData();
            fd.append('action', 'item_stock_in_storage');
            fd.append('limit', this.state.limit);
            fd.append('offset', String(this.state.offset));
            fd.append('sort', this.state.sort);
            fd.append('search', this.state.search);
            try {
                const data = await CoreAPI.client.call(fd);
                if (!data || data.status !== 'ok') {
                    console.error('core_api error (item_stock_in_storage):', data);
                    this.setLoading(false);
                    return;
                }
                if (this.state.offset === 0) {
                    this.tbody.innerHTML = data.html || '';
                } else if (data.html) {
                    this.tbody.insertAdjacentHTML('beforeend', data.html);
                }
                if (this.total) {
                    this.total.textContent = String(data.total ?? 0);
                }
                const loaded = Number(data.items_count ?? 0);
                this.state.offset += loaded;
                if (this.state.limit === 'all') {
                    this.state.done = true;
                } else {
                    this.state.done = loaded === 0 || data.has_more === false;
                }
            } catch (err) {
                console.error('core_api fetch error (item_stock_in_storage):', err);
            } finally {
                this.setLoading(false);
            }
        }
    },
    // ====================================

    // WAREHOUSE - Move (scanner search)
    // ====================================
    toolsManagement: {
        root: null,
        tbody: null,
        total: null,
        searchInput: null,
        searchTimer: null,
        initialized: false,
        init() {
            const root = document.getElementById('tools-storage-move');
            if (!root) return;
            if (this.initialized && this.root === root) {
                return;
            }
            this.root = root;
            this.tbody = root.querySelector('#tools-storage-move-results-tbody');
            this.total = root.querySelector('#tools-storage-move-total');
            this.searchInput = root.querySelector('#tools-storage-move-search');

            if (!this.tbody || !this.total || !this.searchInput) {
                return;
            }

            this.bindEvents();
            this.fetchResults('');
            this.initialized = true;
        },
        bindEvents() {
            const handleSearch = () => {
                if (this.searchTimer) {
                    clearTimeout(this.searchTimer);
                }
                this.searchTimer = setTimeout(() => {
                    const value = this.searchInput.value.trim();
                    this.fetchResults(value);
                }, 300);
            };
            this.searchInput.addEventListener('input', handleSearch);
            this.searchInput.addEventListener('change', handleSearch);
            this.searchInput.addEventListener('search', handleSearch);
        },
        clearResults() {
            if (this.tbody) {
                this.tbody.innerHTML = '';
            }
            if (this.total) {
                this.total.textContent = '0';
            }
        },
        async fetchResults(search) {
            const fd = new FormData();
            fd.append('action', 'tools_management_search');
            fd.append('search', search);
            try {
                const data = await CoreAPI.client.call(fd);
                if (!data || data.status !== 'ok') {
                    console.error('core_api error (tools_management_search):', data);
                    return;
                }
                if (this.tbody) {
                    this.tbody.innerHTML = data.html || '';
                }
                if (this.total) {
                    this.total.textContent = String(data.total ?? 0);
                }
            } catch (err) {
                console.error('core_api fetch error (tools_management_search):', err);
                this.clearResults();
            }
        }
    },

    // ====================================
    // WAREHOUSE - Move (scanner search)
    // ====================================
    warehouseMove: {
        root: null,
        tbody: null,
        total: null,
        searchInput: null,
        searchTimer: null,
        initialized: false,
        init() {
            const root = document.getElementById('warehouse-move-scanner');
            if (!root) return;
            if (this.initialized && this.root === root) {
                return;
            }
            this.root = root;
            this.tbody = root.querySelector('#warehouse-move-results-tbody');
            this.total = root.querySelector('#warehouse-move-total');
            this.searchInput = root.querySelector('#warehouse-move-search');

            if (!this.tbody || !this.total || !this.searchInput) {
                return;
            }

            this.bindEvents();
            this.clearResults();
            this.initialized = true;
        },
        bindEvents() {
            this.searchInput.addEventListener('input', () => {
                if (this.searchTimer) {
                    clearTimeout(this.searchTimer);
                }
                this.searchTimer = setTimeout(() => {
                    const value = this.searchInput.value.trim();
                    if (!value) {
                        this.clearResults();
                        return;
                    }
                    this.fetchResults(value);
                }, 300);
            });
        },
        clearResults() {
            if (this.tbody) {
                this.tbody.innerHTML = '';
            }
            if (this.total) {
                this.total.textContent = '0';
            }
        },
        async fetchResults(search) {
            const fd = new FormData();
            fd.append('action', 'warehouse_move_search');
            fd.append('search', search);
            try {
                const data = await CoreAPI.client.call(fd);
                if (!data || data.status !== 'ok') {
                    console.error('core_api error (warehouse_move_search):', data);
                    return;
                }
                if (this.tbody) {
                    this.tbody.innerHTML = data.html || '';
                }
                if (this.total) {
                    this.total.textContent = String(data.total ?? 0);
                }
            } catch (err) {
                console.error('core_api fetch error (warehouse_move_search):', err);
            }
        }
    },
    // ====================================
    // WAREHOUSE - Move (batch)
    // ====================================
    warehouseMoveBatch: {
        root: null,
        tbody: null,
        total: null,
        searchInput: null,
        cellSelect: null,
        searchTimer: null,
        initialized: false,
        init() {
            const root = document.getElementById('warehouse-move-batch');
            if (!root) return;
            if (this.initialized && this.root === root) {
                return;
            }
            this.root = root;
            this.tbody = root.querySelector('#warehouse-move-batch-results-tbody');
            this.total = root.querySelector('#warehouse-move-batch-total');
            this.searchInput = root.querySelector('#warehouse-move-batch-search');
            this.cellSelect = root.querySelector('#warehouse-move-batch-cell');

            if (!this.tbody || !this.total || !this.searchInput || !this.cellSelect) {
                return;
            }

            this.bindEvents();
            this.clearResults();
            this.initialized = true;
        },
        bindEvents() {
            this.searchInput.addEventListener('input', () => {
                if (this.searchTimer) {
                    clearTimeout(this.searchTimer);
                }
                this.searchTimer = setTimeout(() => {
                    const value = this.searchInput.value.trim();
                    if (!value) {
                        this.clearResults();
                        return;
                    }
                    this.fetchResults(value);
                }, 300);
            });
            this.cellSelect.addEventListener('change', () => {
                this.updateMoveButtons();
            });
        },
        clearResults() {
            if (this.tbody) {
                this.tbody.innerHTML = '';
            }
            if (this.total) {
                this.total.textContent = '0';
            }
            this.updateMoveButtons();
        },
        clearAfterMove() {
            if (this.searchInput) {
                this.searchInput.value = '';
            }
            this.clearResults();
            if (this.searchInput) {
                this.searchInput.focus();
            }
        },
        updateMoveButtons() {
            if (!this.tbody) return;
            this.tbody.querySelectorAll('.js-warehouse-move-batch-action').forEach((button) => {
                if (!(button instanceof HTMLButtonElement)) return;
                button.disabled = false;
            });
        },
        async fetchResults(search) {
            const fd = new FormData();
            fd.append('action', 'warehouse_move_batch_search');
            fd.append('search', search);
            try {
                const data = await CoreAPI.client.call(fd);
                if (!data || data.status !== 'ok') {
                    console.error('core_api error (warehouse_move_batch_search):', data);
                    return;
                }
                if (this.tbody) {
                    this.tbody.innerHTML = data.html || '';
                }
                if (this.total) {
                    this.total.textContent = String(data.total ?? 0);
                }
                this.updateMoveButtons();
            } catch (err) {
                console.error('core_api fetch error (warehouse_move_batch_search):', err);
            }
        }
    },
    // ====================================
    // INIT - инициализация
    // ====================================
    init() {
        document.addEventListener('click', this.events.handleClick.bind(this));
        document.addEventListener('change', this.events.handlePhotoUpload.bind(this));
        document.addEventListener('change', this.events.handleWarehouseItemStockPhotoUpload.bind(this));
        document.addEventListener('change', this.events.handleRolePermissionToggle.bind(this));
        document.addEventListener('shown.bs.tab', (event) => {
            if (event?.target?.id === 'warehouse-in-storage-tab') {
                this.warehouseInStorage.init();
            }
            if (event?.target?.id === 'warehouse-without-addons-tab') {
                this.warehouseWithoutAddons.init();
            }
        });
        // Ensure page init handlers run on full page load (not only via loadMain).
        try {
            const initEl = document.querySelector('[data-page-init]');
            const key = initEl?.getAttribute('data-page-init');
            if (key && CoreAPI.pageInits && typeof CoreAPI.pageInits[key] === 'function') {
                CoreAPI.pageInits[key]();
            }
        } catch (e) {
            console.error('CoreAPI.pageInits init error', e);
        }
        this.warehouseWithoutCells.init();
        this.warehouseWithoutAddons.init();
        this.warehouseInStorage.init();
        this.warehouseMove.init();
        this.warehouseMoveBatch.init();
        console.log('CoreAPI initialized');
    }
};
// =========================
// Хелперы (сохранены для совместимости)
// =========================

function emitDeviceContext(){
  try{
    function read(id){
      var el = document.getElementById(id);
      if(!el) return null;
      var t = (el.textContent || el.innerText || "").trim();
      if (!t || t === "null" || t === "undefined") return null;
      return t;
    }
    var payload = {
      task: read("device-scan-config"),
      ocr_templates: read("ocr-templates"),
      destcountry: read("ocr-templates-destcountry"),
      dicts: read("ocr-dicts")
    };
    if (window.DeviceApp && window.DeviceApp.onMainContext) {
      window.DeviceApp.onMainContext(JSON.stringify(payload));
    }
  }catch(e){}
}

function setToolsManagementModalType(type) {
    const modalEl = document.getElementById('fullscreenModal');
    if (!modalEl) return;
    if (type) {
        modalEl.setAttribute('data-tools-modal', type);
    } else {
        modalEl.removeAttribute('data-tools-modal');
    }
}


function setFieldValue(id, value) {
    var field = document.getElementById(id);
    if (!field) return;
    field.value = value;
    field.dispatchEvent(new Event('input', { bubbles: true }));
    field.dispatchEvent(new Event('change', { bubbles: true }));
}

function showToast(message, duration) {
    var timeoutMs = typeof duration === 'number' ? duration : 2000;
    var containerId = 'core-toast-container';
    var container = document.getElementById(containerId);
    if (!container) {
        container = document.createElement('div');
        container.id = containerId;
        container.style.position = 'fixed';
        container.style.top = '16px';
        container.style.right = '16px';
        container.style.zIndex = '1080';
        container.style.display = 'flex';
        container.style.flexDirection = 'column';
        container.style.gap = '8px';
        document.body.appendChild(container);
    }

    var toast = document.createElement('div');
    toast.textContent = String(message || '');
    toast.style.padding = '10px 14px';
    toast.style.background = '#ffc107';
    toast.style.color = '#212529';
    toast.style.borderRadius = '6px';
    toast.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
    toast.style.fontSize = '14px';
    toast.style.fontWeight = '600';
    toast.style.maxWidth = '320px';
    container.appendChild(toast);

    setTimeout(function () {
        if (toast && toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, timeoutMs);
}


function initWarehouseStockAddons() {
    var section = document.getElementById('warehouseStockAddonsSection');
    if (!section || section.__addonsBound) return;
    section.__addonsBound = true;

    var companySelect = document.getElementById('receiverCompany');
    var controls = document.getElementById('warehouseStockAddonsControls');
    var emptyNode = document.getElementById('warehouseStockAddonsEmpty');
    var hiddenInput = document.getElementById('warehouseStockAddonsJson');
    if (!companySelect || !controls || !emptyNode || !hiddenInput) return;

    var addonsMap = {};
    var selectedAddons = {};
    try { addonsMap = JSON.parse(section.getAttribute('data-addons-map') || '{}') || {}; } catch (e) { addonsMap = {}; }
    try { selectedAddons = JSON.parse(section.getAttribute('data-item-addons') || '{}') || {}; } catch (e) { selectedAddons = {}; }

    function normalizeForwarderName(raw) {
        return String(raw || '').trim().toUpperCase();
    }

    function persist() {
        var payload = {};
        controls.querySelectorAll('select[data-addon-key]').forEach(function (select) {
            var addonKey = select.getAttribute('data-addon-key') || '';
            if (!addonKey) return;
            var val = String(select.value || '').trim();
            if (val === '') return;
            payload[addonKey] = val;
        });
        hiddenInput.value = Object.keys(payload).length ? JSON.stringify(payload) : '';
    }

    function createSelect(addonKey, optionsMap, initialValue) {
        var col = document.createElement('div');
        col.className = 'col-md-6';

        var label = document.createElement('label');
        label.className = 'form-label';
        label.textContent = addonKey;

        var select = document.createElement('select');
        select.className = 'form-select';
        select.setAttribute('data-addon-key', addonKey);

        var emptyOpt = document.createElement('option');
        emptyOpt.value = '';
        emptyOpt.textContent = '— выберите —';
        select.appendChild(emptyOpt);

        Object.keys(optionsMap || {}).forEach(function (valueKey) {
            var opt = document.createElement('option');
            opt.value = String(valueKey);
            opt.textContent = String(optionsMap[valueKey]);
            if (String(initialValue || '') === String(valueKey)) {
                opt.selected = true;
            }
            select.appendChild(opt);
        });

        select.addEventListener('change', persist);

        col.appendChild(label);
        col.appendChild(select);
        controls.appendChild(col);
    }

    function render() {
        controls.innerHTML = '';
        var forwarder = normalizeForwarderName(companySelect.value);
        var extra = addonsMap[forwarder];
        if (!Array.isArray(extra) || !extra.length) {
            emptyNode.style.display = '';
            hiddenInput.value = '';
            return;
        }

        emptyNode.style.display = 'none';
        extra.forEach(function (group) {
            if (!group || typeof group !== 'object') return;
            Object.keys(group).forEach(function (addonKey) {
                var optionsMap = group[addonKey];
                if (!optionsMap || typeof optionsMap !== 'object') return;
                createSelect(addonKey, optionsMap, selectedAddons[addonKey]);
            });
        });
        persist();
    }

    companySelect.addEventListener('change', render);
    companySelect.addEventListener('input', render);
    render();
}

function initReceiverAddressQuickCells() {
    var form = document.getElementById('item-in-modal-form');
    if (!form || form.__receiverQuickCellsInstalled) return;
    form.__receiverQuickCellsInstalled = true;

    var countrySelect = form.querySelector('#receiverCountry');
    var companySelect = form.querySelector('#receiverCompany');
    var addressInput = form.querySelector('#receiverAddress');
    var buttonsWrap = form.querySelector('#receiverAddressQuickCells');
    var configNode = document.getElementById('device-scan-config');

    if (!countrySelect || !companySelect || !addressInput || !buttonsWrap || !configNode) return;

    var config = null;
    try {
        config = JSON.parse((configNode.textContent || '').trim());
    } catch (e) {
        config = null;
    }

    var map = config && typeof config === 'object' ? config.cell_null_default_forwrad : null;
    if (!map || typeof map !== 'object') {
        buttonsWrap.innerHTML = '';
        return;
    }

    function normalizeCountry(raw) {
        var code = String(raw || '').trim().toUpperCase();
        if (code === 'AZ' || code === 'AZE' || code === 'AZB') return 'AZB';
        if (code === 'KG' || code === 'KGZ' || code === 'KGY' || code === 'KGYSTAN') return 'KG';
        if (code === 'GE' || code === 'GEO' || code === 'TBS') return 'TBS';
        return code;
    }

    function findCellCodes(company, country) {
        var companyCode = String(company || '').trim().toUpperCase();
        var countryCode = normalizeCountry(country);
        var directKey = companyCode + '_' + countryCode;
        var values = [];

        if (Object.prototype.hasOwnProperty.call(map, directKey)) {
            values.push(String(map[directKey] || '').trim());
        } else {
            Object.keys(map).forEach(function (key) {
                var upperKey = String(key || '').toUpperCase();
                if (!upperKey.startsWith(companyCode + '_')) return;
                var suffix = upperKey.slice(companyCode.length + 1);
                if (suffix === countryCode) {
                    values.push(String(map[key] || '').trim());
                }
            });
        }

        return values.filter(Boolean);
    }

    function renderButtons() {
        var company = companySelect.value;
        var country = countrySelect.value;
        var cellCodes = findCellCodes(company, country);

        buttonsWrap.innerHTML = '';
        if (!cellCodes.length) return;

        cellCodes.forEach(function (code) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-outline-primary btn-sm';
            btn.textContent = code;
            btn.addEventListener('click', function () {
                addressInput.value = '';
                addressInput.value = code;
                addressInput.dispatchEvent(new Event('input', { bubbles: true }));
                addressInput.dispatchEvent(new Event('change', { bubbles: true }));
            });
            buttonsWrap.appendChild(btn);
        });
    }

    countrySelect.addEventListener('change', renderButtons);
    countrySelect.addEventListener('input', renderButtons);
    companySelect.addEventListener('change', renderButtons);
    companySelect.addEventListener('input', renderButtons);

    renderButtons();
}


function initItemInDuplicateCheck() {
    var form = document.getElementById('item-in-modal-form');
    if (!form || form.__duplicateCheckInstalled) return;
    form.__duplicateCheckInstalled = true;

    var tuidInput = form.querySelector('#tuid');
    var trackingInput = form.querySelector('#trackingNo');
    var carrierSelect = form.querySelector('#carrierName');
    var addButton = form.querySelector('button[data-core-action="add_new_item_in"]');

    if (!tuidInput || !trackingInput || !carrierSelect || !addButton) return;

    var debounceTimer = null;
    var lastKey = '';
    var lastToastKey = '';
    var requestId = 0;

    function setButtonDisabled(disabled) {
        addButton.disabled = !!disabled;
        if (disabled) {
            addButton.classList.add('disabled');
        } else {
            addButton.classList.remove('disabled');
        }
    }

    async function runCheck() {
        var tuid = (tuidInput.value || '').trim();
        var tracking = (trackingInput.value || '').trim();
        var carrier = (carrierSelect.value || '').trim();
        var key = [tuid, tracking, carrier].join('|');

        if (!carrier || (!tuid && !tracking)) {
            setButtonDisabled(false);
            lastKey = key;
            return;
        }
        if (key === lastKey) return;
        lastKey = key;

        var currentRequest = ++requestId;
        var fd = new FormData();
        fd.append('action', 'check_item_in_duplicate');
        fd.append('tuid', tuid);
        fd.append('tracking_no', tracking);
        fd.append('carrier_name', carrier);

        try {
            var data = await CoreAPI.client.call(fd);
            if (currentRequest !== requestId) return;
            var isDuplicate = data && data.status === 'ok' && data.duplicate;
            setButtonDisabled(isDuplicate);
            if (isDuplicate && lastToastKey !== key) {
                showToast('Такая посылка уже на складе', 2000);
                lastToastKey = key;
            }
            if (!isDuplicate) {
                lastToastKey = '';
            }
        } catch (err) {
            console.error('duplicate check failed', err);
            setButtonDisabled(false);
        }
    }

    function scheduleCheck() {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }
        debounceTimer = setTimeout(runCheck, 250);
    }

    tuidInput.addEventListener('input', scheduleCheck);
    trackingInput.addEventListener('input', scheduleCheck);
    carrierSelect.addEventListener('change', scheduleCheck);
    carrierSelect.addEventListener('input', scheduleCheck);
    scheduleCheck();
}


function getSelectedStandDevice() {
    var select = document.getElementById('standDevice');
    if (!select || !select.value) return null;
    var option = select.options[select.selectedIndex];
    return {
        standId: option.value,
        deviceUid: option.value,
        deviceToken: option.getAttribute('data-device-token') || ''
    };
}

function formatNumber(value, digits) {
    if (value === null || value === undefined || isNaN(value)) return '';
    return Number(value).toFixed(digits);
}

async function requestStandMeasurement() {
    var device = getSelectedStandDevice();
    if (!device) {
        alert('Выберите устройство измерения');
        return;
    }
    if (!device.deviceToken) {
        alert('Для устройства не задан device_token');
        return;
    }

    var response = await fetch('/api/stand_measurement_get.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            stand_id: device.standId,
            device_uid: device.deviceUid,
            device_token: device.deviceToken
        })
    });

    var payload = null;
    try {
        payload = await response.json();
    } catch (err) {
        payload = null;
    }

    if (!payload || payload.status !== 'ok' || !payload.data || !payload.data.measurements) {
        alert(payload && payload.message ? payload.message : 'Нет данных измерения');
        return;
    }

    var measurements = payload.data.measurements;
    var weightKg = measurements.weight_g != null ? measurements.weight_g / 1000 : null;
    var sizeL = measurements.depth_mm != null ? measurements.depth_mm / 10 : null;
    var sizeW = measurements.width_mm != null ? measurements.width_mm / 10 : null;
    var sizeH = measurements.height_mm != null ? measurements.height_mm / 10 : null;

    setFieldValue('weightKg', formatNumber(weightKg, 3));
    setFieldValue('sizeL', formatNumber(sizeL, 1));
    setFieldValue('sizeW', formatNumber(sizeW, 1));
    setFieldValue('sizeH', formatNumber(sizeH, 1));
}

document.addEventListener('click', function (event) {
    var target = event.target;
    if (!target) return;
    var button = target.closest ? target.closest('#standMeasureBtn') : null;
    if (!button) return;
    event.preventDefault();
    requestStandMeasurement();
});

window.requestStandMeasurement = requestStandMeasurement;

function initStandDevicePersistence() {
    var select = document.getElementById('standDevice');
    if (!select || !window.localStorage) return;
    var storageKey = 'warehouse_stand_device_uid';
    var storedValue = localStorage.getItem(storageKey);
    if (storedValue) {
        var hasOption = Array.prototype.some.call(select.options, function (opt) {
            return opt.value === storedValue;
        });
        if (hasOption) {
            select.value = storedValue;
            select.dispatchEvent(new Event('change', { bubbles: true }));
        } else {
            localStorage.removeItem(storageKey);
        }
    }

    select.addEventListener('change', function () {
        if (select.value) {
            localStorage.setItem(storageKey, select.value);
        }
    });
}

document.addEventListener('DOMContentLoaded', initStandDevicePersistence);


function setWarehouseItemStockPhotoState(photoType, path, jsonValue) {
    var isLabel = photoType === 'label';
    var hidden = document.getElementById(isLabel ? 'warehouseStockLabelImageJson' : 'warehouseStockBoxImageJson');
    var info = document.getElementById(isLabel ? 'warehouseStockLabelPhotoInfo' : 'warehouseStockBoxPhotoInfo');
    if (hidden) hidden.value = jsonValue || '';
    if (info) info.textContent = path ? ('Загружено: ' + path) : '';
}

function initWarehouseItemStockPhotoButtons() {
    var labelBtn = document.getElementById('warehouseStockTakeLabelPhotoBtn');
    var boxBtn = document.getElementById('warehouseStockTakeBoxPhotoBtn');
    if (labelBtn && !labelBtn.getAttribute('data-core-action')) {
        labelBtn.setAttribute('data-core-action', 'upload_item_stock_photo');
        labelBtn.setAttribute('data-photo-type', 'label');
        labelBtn.classList.add('js-core-link');
    }
    if (boxBtn && !boxBtn.getAttribute('data-core-action')) {
        boxBtn.setAttribute('data-core-action', 'upload_item_stock_photo');
        boxBtn.setAttribute('data-photo-type', 'box');
        boxBtn.classList.add('js-core-link');
    }

    var labelHidden = document.getElementById('warehouseStockLabelImageJson');
    var boxHidden = document.getElementById('warehouseStockBoxImageJson');
    if (labelHidden && labelHidden.value) {
        try {
            var arrL = JSON.parse(labelHidden.value);
            if (Array.isArray(arrL) && arrL[0]) setWarehouseItemStockPhotoState('label', arrL[0], labelHidden.value);
        } catch (e) {}
    }
    if (boxHidden && boxHidden.value) {
        try {
            var arrB = JSON.parse(boxHidden.value);
            if (Array.isArray(arrB) && arrB[0]) setWarehouseItemStockPhotoState('box', arrB[0], boxHidden.value);
        } catch (e) {}
    }
}

window.setWarehouseItemStockPhotoState = setWarehouseItemStockPhotoState;

window.OCRScanner = window.OCRScanner || {};
window.OCRScanner.captureAndUploadWarehouseItemStockPhoto = async function (photoType) {
    var normalized = photoType === 'label' ? 'label' : 'box';
    var inputId = normalized === 'label' ? 'warehouseStockLabelPhotoInput' : 'warehouseStockBoxPhotoInput';
    var input = document.getElementById(inputId);
    if (!input) {
        throw new Error('Фото-инпут не найден: ' + inputId);
    }
    input.dataset.photoType = normalized;
    input.click();
    return { status: 'pending', message: 'Ожидается выбор/съемка фото в камере' };
};

function setSelectValWait(id,v,tries){
  var e=document.getElementById(id);
  if(!e) return;
  e.value=v;
  e.dispatchEvent(new Event('change',{bubbles:true}));
  if(e.value!==v && tries>0){
    setTimeout(function(){ setSelectValWait(id,v,tries-1); }, 120);
  }
}


function renderToolPhotos(photos) {
    const container = document.querySelector('[data-tool-photos="list"]');
    if (!container) return;

    container.innerHTML = '';
    container.classList.add('d-flex', 'flex-column', 'gap-3');

    if (!Array.isArray(photos) || photos.length === 0) {
        container.innerHTML = '<span class="text-muted small">Фото инструмента пока нет</span>';
        return;
    }

    photos.forEach((path, index) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'tool-photo-item border rounded p-2 d-flex flex-column gap-2';

        const img = document.createElement('img');
        img.src = path;
        img.alt = 'Tool photo';
        img.className = 'img-fluid w-100 border';
        img.style.objectFit = 'cover';
        img.style.aspectRatio = '1 / 1';
        wrapper.appendChild(img);

        const formCheck = document.createElement('div');
        formCheck.className = 'form-check mb-0';

        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.className = 'form-check-input';
        checkbox.name = 'delete_photos[]';
        checkbox.value = path;
        checkbox.id = `delete-photo-${index + 1}`;
        checkbox.setAttribute('form', 'tool-profile-form');
        formCheck.appendChild(checkbox);

        const label = document.createElement('label');
        label.className = 'form-check-label small';
        label.setAttribute('for', checkbox.id);
        label.textContent = 'Удалить фото';
        formCheck.appendChild(label);

        wrapper.appendChild(formCheck);

        container.appendChild(wrapper);
    });
}





///////
CoreAPI.pageInits = CoreAPI.pageInits || {};

CoreAPI.pageInits.tools_management = function toolsManagementInit() {
    window.clearToolsStorageMoveSearch = window.clearToolsStorageMoveSearch || function () {
        const el = document.getElementById('tools-storage-move-search');
        if (!el) return false;
        el.value = '';
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
        if (CoreAPI?.toolsManagement?.fetchResults) {
            CoreAPI.toolsManagement.fetchResults('');
        }
        return true;
    };

    if (CoreAPI.toolsManagement?.init) {
        CoreAPI.toolsManagement.init();
    }
};



// Инициализация при загрузке страницы
CoreAPI.init();

// =========================
// Экспорт для совместимости со старым кодом
// =========================

window.CoreAPI = CoreAPI;
window.reloadUserList = () => CoreAPI.ui.reloadList('view_users');
window.reloadToolsStock = () => CoreAPI.ui.reloadList('view_tools_stock');
window.reloadDevices = () => CoreAPI.ui.reloadList('view_devices');
window.reloadWarehouseItemIn = () => CoreAPI.ui.reloadList('warehouse_item_in');

// ============================================================================
// Device Flow API (functions called from Android WebView via evaluateJavascript)
// These must be always available and NOT depend on page init.
// ============================================================================
(function installDeviceFlowApi(){
  if (window.__deviceFlowApiInstalled) return;
  window.__deviceFlowApiInstalled = true;

  window.openMoveModal = function () {
    try {
      const tbody = document.getElementById('warehouse-move-results-tbody');
      if (!tbody) return false;

      // click the actual core link (works with delegated handler)
      const el = tbody.querySelector('.js-core-link[data-core-action="warehouse_move_open_modal"]');
      if (!el) return false;

      el.click();
      return true;
    } catch (e) {
      console.error('openMoveModal error:', e);
      return false;
    }
  };

  window.triggerSaveButton = function () {
    try {
      const saveBtn = document.querySelector('button.js-core-link[data-core-action="warehouse_move_save_cell"]');
      if (!saveBtn) return false;
      saveBtn.click();
      return true;
    } catch (e) {
      console.error('triggerSaveButton error:', e);
      return false;
    }
  };

  window.setCellFromQR = function (qrValue) {
    // если у тебя есть “умная” версия в pageInit — отлично,
    // но базовая должна существовать, чтобы не было NOFN.
    try {
      let cellCode = String(qrValue || '').trim();
      if (!cellCode) return false;
      if (cellCode.toUpperCase().startsWith('CELL:')) cellCode = cellCode.slice(5).trim();
      if (!cellCode) return false;

      const cellSelect = document.getElementById('cellId');
      if (!cellSelect) return false;

      const want = cellCode.toUpperCase();
      for (const opt of cellSelect.options) {
        if ((opt.text || '').trim().toUpperCase() === want) {
          cellSelect.value = opt.value;
          cellSelect.dispatchEvent(new Event('change', { bubbles: true }));
          cellSelect.dispatchEvent(new Event('input', { bubbles: true }));
          return true;
        }
      }
      return false;
    } catch (e) {
      console.error('setCellFromQR error:', e);
      return false;
    }
  };

  window.clearToolsStorageMoveSearch = function () {
    const el = document.getElementById('tools-storage-move-search');
    if (!el) return false;
    el.value = '';
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
    if (CoreAPI?.toolsManagement?.fetchResults) {
      CoreAPI.toolsManagement.fetchResults('');
    }
    return true;
  };

  function setSelectToEmpty(selectId) {
    const select = document.getElementById(selectId);
    if (!select) return false;
    select.value = '';
    select.dispatchEvent(new Event('change', { bubbles: true }));
    select.dispatchEvent(new Event('input', { bubbles: true }));
    return true;
  }

  window.resetToolsUserSelection = function () {
    return setSelectToEmpty('toolAssignedUser');
  };

  window.resetToolsCellSelection = function () {
    return setSelectToEmpty('toolStorageCell');
  };

  function extractTokenFromQr(rawValue) {
    const raw = String(rawValue || '').trim();
    if (!raw) return '';
    const normalized = raw.replace(/\s+/g, ' ').trim();
    const upper = normalized.toUpperCase();
    if (upper.startsWith('USER:')) return normalized.slice(5).trim();
    const urlMatch = normalized.match(/[?&]token=([0-9a-fA-F]{16,64})/);
    if (urlMatch) return urlMatch[1];
    const hexMatch = normalized.match(/[0-9a-fA-F]{32}/);
    if (hexMatch) return hexMatch[0];
    return normalized;
  }

  function extractCellCodeFromQr(rawValue) {
    const raw = String(rawValue || '').trim();
    if (!raw) return '';
    const normalized = raw.replace(/\s+/g, ' ').trim();
    const upper = normalized.toUpperCase();
    if (upper.startsWith('CELL:')) return normalized.slice(5).trim();

    const queryMatch = normalized.match(/[?&]cell(?:_code|_id)?=([^&#]+)/i);
    if (queryMatch) return decodeURIComponent(queryMatch[1]).trim();

    const pathMatch = normalized.match(/\/cells?\/([^/?#]+)/i);
    if (pathMatch) return decodeURIComponent(pathMatch[1]).trim();

    try {
      const url = new URL(normalized);
      const cellParam = url.searchParams.get('cell') || url.searchParams.get('cell_code') || url.searchParams.get('cell_id');
      if (cellParam) return cellParam.trim();
      const pathname = url.pathname || '';
      const segments = pathname.split('/').filter(Boolean);
      if (segments.length) return decodeURIComponent(segments[segments.length - 1]).trim();
    } catch (e) {
      // not a valid URL, continue with fallback parsing
    }

    const tokens = normalized.match(/[A-Za-z0-9_-]+/g);
    if (tokens && tokens.length) return tokens[tokens.length - 1];
    return normalized;
  }

  function normalizeCellCode(value) {
    return String(value || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
  }



  function debugToolsScan(message) {
    const text = String(message || '');
    if (!text) return;
    console.log(`[tools_scan] ${text}`);
    if (window.__debugToolsScanToasts) {
      showToast(text, 2500);
    }
  }

  function withSelectRetry(selectId, handler, tries = 15, delay = 300) {
    const select = document.getElementById(selectId);
    if (select) {
      return handler(select);
    }
    if (tries <= 0) return false;
    return setTimeout(() => withSelectRetry(selectId, handler, tries - 1, delay), delay);
  }

  window.setToolsUserFromQR = function (qrValue) {
    try {
      const token = extractTokenFromQr(qrValue);
      debugToolsScan(`setToolsUserFromQR value="${qrValue}" token="${token}"`);
      if (!token) return false;

      return withSelectRetry('toolAssignedUser', (select) => {
        debugToolsScan(`toolAssignedUser options=${select.options.length}`);
        const tokenUpper = token.toUpperCase();
        let found = null;
        for (const opt of select.options) {
          const optToken = (opt.getAttribute('data-qr-token') || '').trim();
          if (optToken && optToken.toUpperCase() === tokenUpper) {
            found = opt;
            break;
          }
        }

        if (!found && /^\d+$/.test(token)) {
          for (const opt of select.options) {
            if (String(opt.value) === token) {
              found = opt;
              break;
            }
          }
        }

        if (!found) {
          debugToolsScan(`toolAssignedUser match not found for "${token}"`);
          return false;
        }
        select.value = found.value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        select.dispatchEvent(new Event('input', { bubbles: true }));
        debugToolsScan(`toolAssignedUser set value=${found.value}`);
        return true;
      });
    } catch (e) {
      console.error('setToolsUserFromQR error:', e);
      return false;
    }
  };

  window.setToolsCellFromQR = function (qrValue) {
    try {
      let cellCode = extractCellCodeFromQr(qrValue);
      debugToolsScan(`setToolsCellFromQR value="${qrValue}"`);
      if (!cellCode) return false;
      const normalized = cellCode.replace(/\s+/g, ' ').trim();
      const upper = normalized.toUpperCase();
      if (upper.startsWith('CELL:')) cellCode = normalized.slice(5).trim();
      const codeMatch = cellCode.match(/[A-Za-z0-9_-]+/);
      if (codeMatch) cellCode = codeMatch[0];
      if (!cellCode) return false;

      return withSelectRetry('toolStorageCell', (select) => {
        const want = normalizeCellCode(cellCode);
        debugToolsScan(`toolStorageCell code="${cellCode}" normalized="${want}" options=${select.options.length}`);
        for (const opt of select.options) {
          const text = normalizeCellCode(opt.text);
          const value = normalizeCellCode(opt.value);
          if (text === want || value === want) {
            select.value = opt.value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            select.dispatchEvent(new Event('input', { bubbles: true }));
            debugToolsScan(`toolStorageCell set value=${opt.value} text=${opt.text}`);
            return true;
          }
        }
        debugToolsScan(`toolStorageCell match not found for "${cellCode}"`);
        return false;
      });
    } catch (e) {
      console.error('setToolsCellFromQR error:', e);
      return false;
    }
  };

  window.triggerToolsManagementSave = function () {
    try {
      const saveBtn = document.querySelector('button.js-core-link[data-core-action="tools_management_save_move"]');
      if (!saveBtn) return false;
      saveBtn.click();
      return true;
    } catch (e) {
      console.error('triggerToolsManagementSave error:', e);
      return false;
    }
  };
})();


// --- DeviceScanConfig helper ---------------------------------
window.DeviceScanConfig = window.DeviceScanConfig || (function () {
  function _getScriptEl() {
    return document.getElementById('device-scan-config');
  }

  function getRaw() {
    const el = _getScriptEl();
    return el ? (el.textContent || el.innerText || '') : '';
  }

  function get() {
    const raw = (getRaw() || '').trim();
    if (!raw) return null;
    try {
      return JSON.parse(raw);
    } catch (e) {
      console.error('DeviceScanConfig.get(): invalid JSON in #device-scan-config', e);
      return null;
    }
  }

  function set(obj) {
    const el = _getScriptEl();
    if (!el) return false;
    try {
      el.textContent = JSON.stringify(obj, null, 2);
      return true;
    } catch (e) {
      console.error('DeviceScanConfig.set(): failed', e);
      return false;
    }
  }

  function setActiveContext(key) {
    if (!key) return false;
    const cfg = get();
    if (!cfg) return false;
    if (!cfg.contexts || !cfg.contexts[key]) {
      console.warn('DeviceScanConfig.setActiveContext(): unknown context:', key);
      // можно вернуть false, но иногда удобно всё равно проставить строку
      // return false;
    }
    cfg.active_context = key;
    const ok = set(cfg);
    if (ok && typeof emitDeviceContext === 'function') {
      emitDeviceContext();
    }
    return ok;
  }

  return { get, set, setActiveContext };
})();

// --- Auto context switching by config.context_switch ----------
(function installDeviceScanContextSwitching() {
  if (window.__deviceScanContextSwitchInstalled) return;
  window.__deviceScanContextSwitchInstalled = true;

  // Tabs: map tab button selector -> context key
  document.addEventListener('shown.bs.tab', function (event) {
    try {
      const cfg = window.DeviceScanConfig.get();
      const map = cfg?.context_switch?.tabs;
      if (!map || !event?.target) return;

      // event.target is the activated tab button
      for (const selector in map) {
        if (!Object.prototype.hasOwnProperty.call(map, selector)) continue;
        if (event.target.matches(selector)) {
          window.DeviceScanConfig.setActiveContext(map[selector]);
          break;
        }
      }
    } catch (e) {
      console.error('context_switch tabs handler error', e);
    }
  });

  // Modals: map modal selector -> {shown, hidden}
  document.addEventListener('shown.bs.modal', function (event) {
    try {
      const cfg = window.DeviceScanConfig.get();
      const modals = cfg?.context_switch?.modals;
      if (!modals || !event?.target) return;

      const modalEl = event.target;
      for (const selector in modals) {
        if (!Object.prototype.hasOwnProperty.call(modals, selector)) continue;
        const rule = modals[selector];
        if (modalEl.matches(selector) && rule?.shown) {
          window.DeviceScanConfig.setActiveContext(rule.shown);
          // EXTRA: гарантированно пушим обновлённый JSON в Android
          if (typeof emitDeviceContext === 'function') {
            setTimeout(emitDeviceContext, 0);
            setTimeout(emitDeviceContext, 150);
          }
          break;
        }
      }
    } catch (e) {
      console.error('context_switch modal shown handler error', e);
    }
  });

  document.addEventListener('hidden.bs.modal', function (event) {
    try {
      const cfg = window.DeviceScanConfig.get();
      const modals = cfg?.context_switch?.modals;
      if (!modals || !event?.target) return;

      const modalEl = event.target;
      for (const selector in modals) {
        if (!Object.prototype.hasOwnProperty.call(modals, selector)) continue;
        const rule = modals[selector];
        if (modalEl.matches(selector) && rule?.hidden) {
          window.DeviceScanConfig.setActiveContext(rule.hidden);
          if (typeof emitDeviceContext === 'function') {
            setTimeout(emitDeviceContext, 0);
            setTimeout(emitDeviceContext, 150);
          }
          break;
        }
      }
    } catch (e) {
      console.error('context_switch modal hidden handler error', e);
    }
  });
})();
