

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
                'save_menu_group': () => this.getFormById('menu-group-form'),
                'save_menu_item': () => this.getFormById('menu-item-form'),
                'save_system_task': () => this.getFormById('system-task-form'),
                'delete_system_task': () => this.withAttribute('task_id', link),
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
                'save_connector_operations': (currentLink) => {
                    const fd = this.getFormById('connector-operations-form', currentLink);
                    const openTab = currentLink?.getAttribute('data-open-tab') || '';
                    if (openTab) {
                        fd.append('open_tab', openTab);
                    }
                    return fd;
                },
                'test_connector_operations': (currentLink) => {
                    const fd = this.getFormById('connector-operations-form', currentLink);
                    const testOperation = currentLink?.getAttribute('data-test-operation') || '';
                    if (testOperation) {
                        fd.append('test_operation', testOperation);
                    }
                    const openTab = currentLink?.getAttribute('data-open-tab') || '';
                    if (openTab) {
                        fd.append('open_tab', openTab);
                    }
                    return fd;
                },
                'save_connector_addons': (currentLink) => this.getFormById('connector-operations-form', currentLink),

                'warehouse_sync_process_helper': () => {
                    const fd = new FormData();
                    const itemInput = document.getElementById('process-helper-item-id');
                    const connectorInput = document.querySelector('#connector-operations-form input[name="connector_id"]');
                    const itemId = (itemInput?.value || '').trim();
                    const connectorId = (connectorInput?.value || '').trim();
                    if (itemId) {
                        fd.append('item_id', itemId);
                    }
                    if (connectorId) {
                        fd.append('connector_id', connectorId);
                    }
                    return fd;
                },

                'tools_management_open_modal': () => this.withAttribute('tool_id', link),
                'tools_management_open_user_modal': () => this.withAttribute('tool_id', link),
                'tools_management_open_cell_modal': () => this.withAttribute('tool_id', link),

                'delete_cell': () => this.withAttribute('cell_id', link),
                'delete_permission': () => this.withAttribute('permission_code', link),
                'delete_menu_group': () => this.withAttribute('menu_group_id', link),
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
                'warehouse_move_box_assign': () => {
                    const fd = new FormData();
                    const fromCellSelect = document.getElementById('warehouse-move-box-from-cell');
                    const toCellSelect = document.getElementById('warehouse-move-box-to-cell');
                    if (fromCellSelect) fd.append('from_cell_id', fromCellSelect.value || '');
                    if (toCellSelect) fd.append('to_cell_id', toCellSelect.value || '');
                    return fd;
                },
                'tools_management_save_move': () => this.getFormById('tool-storage-move-form')
            };
            const fd = builders[action] ? builders[action](link) : new FormData();
            fd.append('action', action);
            return fd;
        },
        getFormById(id, currentLink = null) {
            const closestForm = currentLink?.closest?.('form');
            if (closestForm) {
                return new FormData(closestForm);
            }

            const scopedContainer = currentLink?.closest?.('.modal, section, .card, [role="dialog"], body');
            if (scopedContainer) {
                const scopedForm = scopedContainer.querySelector(`#${id}`);
                if (scopedForm) {
                    return new FormData(scopedForm);
                }
            }
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


        runScriptsInElement(container) {
            if (!container) return;
            const scripts = Array.from(container.querySelectorAll('script'));
            scripts.forEach((oldScript) => {
                const newScript = document.createElement('script');
                Array.from(oldScript.attributes || []).forEach((attr) => {
                    newScript.setAttribute(attr.name, attr.value);
                });
                if (!newScript.src) {
                    newScript.textContent = oldScript.textContent || '';
                }
                oldScript.parentNode?.replaceChild(newScript, oldScript);
            });
        },
        /**
         * Показать HTML в модальном окне
         */
        showModal(html) {
            this.cleanupModalBackdrops();
            const modalBody = document.querySelector('#fullscreenModal .modal-body');
            if (modalBody) {
                modalBody.innerHTML = html;
                this.runScriptsInElement(modalBody);
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
                if (CoreAPI.warehouseSync?.init) {
                    CoreAPI.warehouseSync.init();
                }
                if (CoreAPI.warehouseMove?.init) {
                    CoreAPI.warehouseMove.init();
                }
                if (CoreAPI.warehouseMoveBatch?.init) {
                    CoreAPI.warehouseMoveBatch.init();
                }
                if (CoreAPI.warehouseItemOut?.init) {
                    CoreAPI.warehouseItemOut.init();
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

    menuGroups: {
        fillForm(button) {
            const id = button.getAttribute('data-menu-group-id') || '';
            const code = button.getAttribute('data-menu-group-code') || '';
            const title = button.getAttribute('data-menu-group-title') || '';
            const icon = button.getAttribute('data-menu-group-icon') || '';
            const sortOrder = button.getAttribute('data-menu-group-sort') || '0';
            const isActive = button.getAttribute('data-menu-group-active') || '0';
            const idInput = document.getElementById('menu_group_id');
            const codeInput = document.getElementById('menu_group_code');
            const titleInput = document.getElementById('menu_group_title');
            const iconInput = document.getElementById('menu_group_icon');
            const sortInput = document.getElementById('menu_group_sort');
            const activeInput = document.getElementById('menu_group_active');
            if (idInput) idInput.value = id;
            if (codeInput) codeInput.value = code;
            if (titleInput) titleInput.value = title;
            if (iconInput) iconInput.value = icon;
            if (sortInput) sortInput.value = sortOrder;
            if (activeInput) activeInput.checked = isActive === '1';
        },
        resetForm() {
            const idInput = document.getElementById('menu_group_id');
            const codeInput = document.getElementById('menu_group_code');
            const titleInput = document.getElementById('menu_group_title');
            const iconInput = document.getElementById('menu_group_icon');
            const sortInput = document.getElementById('menu_group_sort');
            const activeInput = document.getElementById('menu_group_active');
            if (idInput) idInput.value = '';
            if (codeInput) codeInput.value = '';
            if (titleInput) titleInput.value = '';
            if (iconInput) iconInput.value = '';
            if (sortInput) sortInput.value = '0';
            if (activeInput) activeInput.checked = true;
        }
    },

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

    systemTasks: {
        fillForm(button) {
            const form = document.getElementById('system-task-form');
            if (!form || !button) return;
            form.querySelector('[name="task_id"]').value = button.getAttribute('data-task-id') || '';
            form.querySelector('[name="code"]').value = button.getAttribute('data-task-code') || '';
            form.querySelector('[name="name"]').value = button.getAttribute('data-task-name') || '';
            form.querySelector('[name="description"]').value = button.getAttribute('data-task-description') || '';
            form.querySelector('[name="endpoint_action"]').value = button.getAttribute('data-task-endpoint') || '';
            form.querySelector('[name="interval_minutes"]').value = button.getAttribute('data-task-interval') || '60';
            form.querySelector('[name="is_enabled"]').checked = button.getAttribute('data-task-enabled') === '1';
        },
        resetForm() {
            const form = document.getElementById('system-task-form');
            if (!form) return;
            form.reset();
            const idField = form.querySelector('[name="task_id"]');
            if (idField) idField.value = '';
            const enabled = form.querySelector('[name="is_enabled"]');
            if (enabled) enabled.checked = true;
            const interval = form.querySelector('[name="interval_minutes"]');
            if (interval) interval.value = '60';
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
                const openTab = String(data?.open_tab || '').trim();
                if (openTab) {
                    fd.append('open_tab', openTab);
                }
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


        'warehouse_sync_process_helper': (data) => {
            const setText = (id, html, fallback = '—') => {
                const el = document.getElementById(id);
                if (!el) return;
                const safe = (typeof html === 'string' && html.trim() !== '') ? html : fallback;
                el.innerHTML = safe;
            };

            const processHelper = (data && typeof data === 'object' && data.process_helper && typeof data.process_helper === 'object')
                ? data.process_helper
                : {};
            const executionPlan = (data && typeof data === 'object' && data.execution_plan && typeof data.execution_plan === 'object')
                ? data.execution_plan
                : {};

            const requiredVars = Array.isArray(executionPlan?.data_block?.required_vars)
                ? executionPlan.data_block.required_vars
                : [];
            const missingVars = Array.isArray(executionPlan?.data_block?.missing_required_vars)
                ? executionPlan.data_block.missing_required_vars
                : [];

            const requiredHtml = requiredVars.length > 0
                ? `<ul class="mb-0">${requiredVars.map((name) => {
                    const safeName = String(name).replace(/[<>&]/g, '');
                    const isMissing = missingVars.includes(name);
                    return `<li>${isMissing ? '<span class="text-danger">' : ''}<code>${safeName}</code>${isMissing ? '</span> <span class="text-danger">(не заполнено)</span>' : ''}</li>`;
                }).join('')}</ul>`
                : 'Нет обязательных переменных по текущему сценарию.';

            const blockers = Array.isArray(executionPlan?.stop_reasons) ? executionPlan.stop_reasons : [];
            const blockersHtml = blockers.length > 0
                ? `<ul class="mb-0">${blockers.map((reason) => `<li class="text-danger">${String(reason).replace(/[<>&]/g, '')}</li>`).join('')}</ul>`
                : '<span class="text-success">Блокирующие проверки не найдены.</span>';

            const quickCheck = Array.isArray(processHelper?.quick_check) ? processHelper.quick_check : [];
            const quickCheckHtml = quickCheck.length > 0
                ? `<ol class="mb-0">${quickCheck.map((point) => `<li>${String(point).replace(/[<>&]/g, '')}</li>`).join('')}</ol>`
                : 'Quick-check не задан в process helper.';

            const itemId = Number(data?.item_id || 0);
            const connectorId = Number(data?.connector_id || 0);
            const canExecute = !!executionPlan?.can_execute;
            const statusHtml = itemId > 0
                ? `Посылка <code>#${itemId}</code>, коннектор <code>#${connectorId || 0}</code>. ${canExecute ? '<span class="text-success">Можно запускать.</span>' : '<span class="text-danger">Запуск сейчас заблокирован.</span>'}`
                : 'Подсказка обновлена.';

            setText('process-helper-status', statusHtml);
            setText('process-helper-required-vars', requiredHtml);
            setText('process-helper-blockers', blockersHtml);
            setText('process-helper-quick-check', quickCheckHtml);
        },

        'test_connector_operations': async (data) => {

            const safeText = (value) => String(value || '').replace(/[<>&]/g, '');
            const renderChainStatus = (chainStatus) => {
                const statusPayload = (chainStatus && typeof chainStatus === 'object' && !Array.isArray(chainStatus))
                    ? chainStatus
                    : { operations: Array.isArray(chainStatus) ? chainStatus : [] };
                const rows = Array.isArray(statusPayload?.operations) ? statusPayload.operations : [];
                if (rows.length === 0) {
                    return '<div class="text-muted">Статус цепочки пока не построен.</div>';
                }

                const badgeByStatus = {
                    success: 'success',
                    failed: 'danger',
                    pending: 'secondary',
                };

                const titleByStatus = {
                    success: 'success',
                    failed: 'failed',
                    pending: 'pending',
                };

                const stages = statusPayload?.stages && typeof statusPayload.stages === 'object'
                    ? statusPayload.stages
                    : null;
                const currentEvent = statusPayload?.current_event && typeof statusPayload.current_event === 'object'
                    ? statusPayload.current_event
                    : null;
                const timeline = Array.isArray(statusPayload?.timeline) ? statusPayload.timeline : [];

                const stageNames = ['before', 'during', 'main', 'finally'];
                const stageSummaryHtml = stages
                    ? `<div class="mt-2 d-flex flex-wrap gap-2">${stageNames.map((name) => {
                        const stats = stages?.[name] || {};
                        const executed = Number(stats?.executed || 0);
                        const success = Number(stats?.success || 0);
                        const failed = Number(stats?.failed || 0);
                        return `<span class="badge text-bg-light border">${name}: exec=${executed}, ok=${success}, fail=${failed}</span>`;
                    }).join('')}</div>`
                    : '';

                const currentEventHtml = currentEvent
                    ? `<div class="mt-2"><small>current_event: <code>${safeText(currentEvent.operation_id || '')}</code> (${safeText(currentEvent.stage || '')}) → <strong>${safeText(currentEvent.status || '')}</strong></small></div>`
                    : '';

                const timelineHtml = timeline.length > 0
                    ? `<div class="mt-2"><small><strong>timeline</strong></small><ul class="mb-0">${timeline.map((event) => {
                        const op = safeText(event?.operation_id || '');
                        const stage = safeText(event?.stage || '');
                        const status = safeText(event?.status || '');
                        const duration = Number(event?.duration_ms || 0);
                        return `<li><code>${op}</code> [${stage}] → ${status} (${duration}ms)</li>`;
                    }).join('')}</ul></div>`
                    : '';
                return `<div class="d-flex flex-wrap gap-2">${rows.map((row, idx) => {
                    const status = String(row?.status || 'pending').toLowerCase();
                    const badge = badgeByStatus[status] || 'secondary';
                    const title = titleByStatus[status] || 'pending';
                    const op = safeText(row?.operation_id || `op_${idx + 1}`);
                    return `<span class="badge text-bg-${badge}">${op}: ${title}</span>`;
                }).join('')}</div>${stageSummaryHtml}${currentEventHtml}${timelineHtml}`;
            };

            const renderRunReport = (box, payload, fallbackTitle) => {
                if (!box) return;

                const lines = [];
                const safeMessage = safeText(payload?.message || fallbackTitle);
                const runId = safeText(payload?.run_id || '');
                const traceLog = Array.isArray(payload?.trace_log) ? payload.trace_log : [];
                const stepLog = Array.isArray(payload?.step_log) ? payload.step_log : [];

                lines.push(`<div><strong>${safeMessage}</strong></div>`);
                if (runId) {
                    lines.push(`<div class="mt-1">run_id: <code>${runId}</code></div>`);
                }

                lines.push('<div class="mt-2"><strong>Статус цепочки</strong></div>');
                lines.push(renderChainStatus(payload?.chain_status));

                if (traceLog.length > 0) {
                    lines.push('<div class="mt-2"><strong>trace_log</strong></div>');
                    lines.push(`<pre class="mb-0" style="max-height:220px;overflow:auto;">${safeText(JSON.stringify(traceLog, null, 2))}</pre>`);
                }

                if (stepLog.length > 0) {
                    lines.push('<div class="mt-2"><strong>step_log</strong></div>');
                    lines.push(`<pre class="mb-0" style="max-height:260px;overflow:auto;">${safeText(JSON.stringify(stepLog, null, 2))}</pre>`);
                }

                box.innerHTML = lines.join('');

                box.classList.remove('d-none');
            };

            const updateOperationStatusBadge = (operationId, status, message, finishedAt) => {
                const normalizedOperationId = String(operationId || '').trim();
                if (!normalizedOperationId) return;

                const badges = Array.from(document.querySelectorAll('[data-op-status-for]')).filter((el) => {
                    return String(el.getAttribute('data-op-status-for') || '').trim() === normalizedOperationId;
                });
                if (badges.length === 0) return;

                const normalizedStatus = String(status || '').toLowerCase();
                const className = (normalizedStatus === 'ok' || normalizedStatus === 'success')
                    ? 'success'
                    : ((normalizedStatus === 'error' || normalizedStatus === 'failed' || normalizedStatus === 'fail') ? 'danger' : 'secondary');
                const title = (normalizedStatus === 'ok' || normalizedStatus === 'success') ? 'OK' : ((normalizedStatus === 'error' || normalizedStatus === 'failed' || normalizedStatus === 'fail') ? 'ERR' : 'N/A');
                const tooltip = [finishedAt, message, status].map((v) => String(v || '').trim()).filter(Boolean).join(' · ');

                badges.forEach((badge) => {
                    badge.className = `badge text-bg-${className} ms-1`;
                    badge.textContent = title;
                    badge.setAttribute('title', tooltip);
                });
            };

            const testOperation = String(data?.test_operation || '').trim();

            const reportBoxes = Array.from(document.querySelectorAll('[data-op-report-for]')).filter((el) => {
                return String(el.getAttribute('data-op-report-for') || '').trim() === testOperation;
            });
            reportBoxes.forEach((box) => {
                renderRunReport(box, data, `Тест операции ${testOperation || ''} выполнен`);
            });

            updateOperationStatusBadge(testOperation, data?.status || '', data?.message || '', data?.finished_at || '');

            alert(data.message || 'Тест операции выполнен');

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
                const openTab = String(data?.open_tab || '').trim();
                if (openTab) {
                    fd.append('open_tab', openTab);
                }
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
            if (typeof initWarehouseStockAddons === 'function') {
                initWarehouseStockAddons();
            }
            if (typeof initWarehouseItemStockPhotoButtons === 'function') {
                initWarehouseItemStockPhotoButtons();
            }
            if (typeof initItemInDraftControls === 'function') {
                initItemInDraftControls();
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
                        if (typeof initWarehouseStockAddons === 'function') {
                            initWarehouseStockAddons();
                        }
                        if (typeof initWarehouseItemStockPhotoButtons === 'function') {
                            initWarehouseItemStockPhotoButtons();
                        }
                        if (typeof initItemInDraftControls === 'function') {
                            initItemInDraftControls();
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
                    if (typeof initWarehouseStockAddons === 'function') {
                        initWarehouseStockAddons();
                    }
                    if (typeof initWarehouseItemStockPhotoButtons === 'function') {
                        initWarehouseItemStockPhotoButtons();
                    }
                    if (typeof initItemInDraftControls === 'function') {
                        initItemInDraftControls();
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
        'warehouse_move_box_assign': (data) => {
            alert(data.message || 'Сохранено');
            if (CoreAPI.warehouseMoveBox?.refreshAfterMove) {
                CoreAPI.warehouseMoveBox.refreshAfterMove();
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
        'save_menu_group': async (data) => {
            alert(data.message || 'Сохранено');
            await CoreAPI.ui.reloadList('view_role_permissions');
        },
        'delete_menu_group': async (data) => {
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
        'save_system_task': async (data) => {
            alert(data.message || 'Сохранено');
            await CoreAPI.ui.reloadList('system_tasks');
        },
        'delete_system_task': async (data) => {
            alert(data.message || 'Удалено');
            await CoreAPI.ui.reloadList('system_tasks');
        },
        'run_system_tasks_now': async (data) => {
            alert(data.message || 'Выполнено');
            await CoreAPI.ui.reloadList('system_tasks');
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

            const editMenuGroup = e.target.closest('.js-menu-group-edit');
            if (editMenuGroup) {
                e.preventDefault();
                CoreAPI.menuGroups.fillForm(editMenuGroup);
                return;
            }
            const resetMenuGroup = e.target.closest('.js-menu-group-reset');
            if (resetMenuGroup) {
                e.preventDefault();
                CoreAPI.menuGroups.resetForm();
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
            const editSystemTask = e.target.closest('.js-system-task-edit');
            if (editSystemTask) {
                e.preventDefault();
                CoreAPI.systemTasks.fillForm(editSystemTask);
                return;
            }
            const resetSystemTask = e.target.closest('.js-system-task-reset');
            if (resetSystemTask) {
                e.preventDefault();
                CoreAPI.systemTasks.resetForm();
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

            if (action === 'warehouse_move_box_assign') {
                const fromCellSelect = document.getElementById('warehouse-move-box-from-cell');
                const toCellSelect = document.getElementById('warehouse-move-box-to-cell');
                if (!fromCellSelect || !fromCellSelect.value) {
                    alert('Выберите исходную ячейку');
                    fromCellSelect?.focus();
                    return;
                }
                if (!toCellSelect || !toCellSelect.value) {
                    alert('Выберите целевую ячейку');
                    toCellSelect?.focus();
                    return;
                }
                if (fromCellSelect.value !== '__without_cell__' && fromCellSelect.value === toCellSelect.value) {
                    alert('Исходная и целевая ячейки должны отличаться');
                    toCellSelect?.focus();
                    return;
                }
                const confirmText = fromCellSelect.value === '__without_cell__'
                    ? 'Назначить выбранную ячейку всем посылкам без ячейки?'
                    : 'Переместить все посылки из выбранной ячейки в целевую?';
                if (!confirm(confirmText)) {
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
                const stockItemId = document.querySelector('#item-stock-modal-form input[name="item_id"]')?.value || '';
                const draftItemId = document.getElementById('itemInDraftId')?.value || '';
                const itemId = stockItemId || draftItemId;
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

                    if (action === 'form_connector_operations') {
                        const connectorId = String(formData.get('connector_id') || '').trim();
                        if (connectorId) {
                            try {
                                const fallbackFd = new FormData();
                                fallbackFd.append('action', 'form_edit_connector');
                                fallbackFd.append('connector_id', connectorId);
                                const fallbackData = await CoreAPI.client.call(fallbackFd);
                                if (fallbackData?.status === 'ok' && fallbackData.html) {
                                    console.warn('form_connector_operations failed, fallback to form_edit_connector');
                                    CoreAPI.ui.showModal(fallbackData.html);
                                    if (CoreAPI.connectors?.initForm) {
                                        CoreAPI.connectors.initForm();
                                    }
                                    alert((data?.message || 'Не удалось открыть операции коннектора') + '\n\nОткрыта карточка коннектора вместо операций.');
                                    return;
                                }
                            } catch (fallbackErr) {
                                console.error('core_api fallback error (form_edit_connector):', fallbackErr);
                            }
                        }
                    }

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
            const itemId = input.dataset.itemId || document.querySelector('#item-stock-modal-form input[name="item_id"]')?.value || document.getElementById('itemInDraftId')?.value || '';
            const photoType = input.dataset.photoType || input.getAttribute('data-photo-type') || '';
            if (!file || !itemId || !photoType) return;

            const isDraft = !!document.getElementById('item-in-modal-form');

            const fd = new FormData();
            fd.append('action', isDraft ? 'upload_item_in_photo' : 'upload_item_stock_photo');
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
        },        async loadNext() {
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
                this.updateAllSyncState();
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
                this.updateAllSyncState();
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
    // WAREHOUSE - Sync with forwarders
    // ====================================
    warehouseSync: {
        root: null,
        tbody: null,
        total: null,
        searchInput: null,
        limitSelect: null,
        forwarderSelect: null,
        allSyncBtn: null,
        backfillBtn: null,
        reconcileBtn: null,
        sentinel: null,
        observer: null,
        searchTimer: null,
        initialized: false,
        state: {
            limit: '50',
            offset: 0,
            search: '',
            forwarder: 'ALL',
            loading: false,
            done: false
        },
        init() {
            const root = document.getElementById('warehouse-sync-missing');
            if (!root) return;
            if (this.initialized && this.root === root) {
                this.resetAndLoad();
                return;
            }
            if (this.observer) {
                this.observer.disconnect();
            }
            this.root = root;
            this.tbody = root.querySelector('#warehouse-sync-missing-tbody');
            this.total = root.querySelector('#warehouse-sync-missing-total');
            this.searchInput = root.querySelector('#warehouse-sync-search');
            this.limitSelect = root.querySelector('#warehouse-sync-limit');
            this.forwarderSelect = root.querySelector('#warehouse-sync-forwarder');
            this.allSyncBtn = root.querySelector('#warehouse-sync-all-sync-btn');
            this.backfillBtn = root.querySelector('#warehouse-sync-backfill-btn');
            this.reconcileBtn = root.querySelector('#warehouse-sync-reconcile-btn');
            this.sentinel = root.querySelector('#warehouse-sync-missing-sentinel');

            if (!this.tbody || !this.total || !this.searchInput || !this.limitSelect || !this.forwarderSelect || !this.allSyncBtn || !this.backfillBtn || !this.reconcileBtn || !this.sentinel) {
                return;
            }

            this.state.limit = this.limitSelect.value || '50';
            this.state.forwarder = this.forwarderSelect.value || 'ALL';
            this.state.search = '';
            this.state.offset = 0;
            this.state.done = false;

            this.bindEvents();
            this.setupObserver();
            this.resetAndLoad();
            this.initialized = true;
        },

        formatSyncHelperMessage(payload = {}, fallbackTitle = '') {
            const executionPlan = (payload && typeof payload === 'object' && payload.execution_plan && typeof payload.execution_plan === 'object')
                ? payload.execution_plan
                : {};
            const processHelper = (payload && typeof payload === 'object' && payload.process_helper && typeof payload.process_helper === 'object')
                ? payload.process_helper
                : {};

            const stopReasons = Array.isArray(executionPlan.stop_reasons)
                ? executionPlan.stop_reasons.filter((item) => String(item || '').trim() !== '')
                : [];
            const quickCheck = Array.isArray(processHelper.quick_check)
                ? processHelper.quick_check.filter((item) => String(item || '').trim() !== '')
                : [];
            const whatUserFills = Array.isArray(processHelper.what_to_fill)
                ? processHelper.what_to_fill.filter((item) => String(item || '').trim() !== '')
                : [];
            const missingVars = Array.isArray(executionPlan?.data_block?.missing_required_vars)
                ? executionPlan.data_block.missing_required_vars.filter((item) => String(item || '').trim() !== '')
                : [];

            const blocks = [];
            if (fallbackTitle) {
                blocks.push(String(fallbackTitle));
            }
            if (stopReasons.length > 0) {
                blocks.push(`Причины остановки:
- ${stopReasons.map((item) => String(item)).join('\n- ')}`);
            }
            if (quickCheck.length > 0) {
                blocks.push(`Quick check:
- ${quickCheck.map((item) => String(item)).join('\n- ')}`);
            }
            if (whatUserFills.length > 0) {
                blocks.push(`Что заполняет пользователь:
- ${whatUserFills.map((item) => String(item)).join('\n- ')}`);
            }
            if (missingVars.length > 0) {
                blocks.push(`Недостающие переменные:
- ${missingVars.map((item) => String(item)).join('\n- ')}`);
            }

            return blocks.length > 0 ? blocks.join('\n\n') : 'Подсказки отсутствуют.';
        },
        async fetchSyncProcessHelper(itemId, connectorId = '') {
            const fd = new FormData();
            fd.append('action', 'warehouse_sync_process_helper');
            fd.append('item_id', String(itemId || ''));
            if (connectorId) {
                fd.append('connector_id', String(connectorId));
            }
            const data = await CoreAPI.client.call(fd);
            if (!data || data.status !== 'ok') {
                throw new Error(data?.message || 'helper error');
            }
            return data;
        },
        async fetchSyncControlPlan(itemId, connectorId = '') {
            const fd = new FormData();
            fd.append('action', 'warehouse_sync_control_plan');
            fd.append('item_id', String(itemId || ''));
            if (connectorId) {
                fd.append('connector_id', String(connectorId));
            }
            const data = await CoreAPI.client.call(fd);
            if (!data || data.status !== 'ok') {
                throw new Error(data?.message || 'control plan error');
            }
            return data;
        },
        async showSyncProcessHelperAlert(itemId, connectorId = '', title = '') {
            try {
                const helper = await this.fetchSyncProcessHelper(itemId, connectorId);
                alert(this.formatSyncHelperMessage(helper, title));
                return helper;
            } catch (helperErr) {
                console.error('warehouse_sync_process_helper error:', helperErr);
                alert(`${title ? `${title}\n\n` : ''}Не удалось получить process helper: ${helperErr?.message || helperErr}`);
                return null;
            }
        },
        bindEvents() {
            this.limitSelect.addEventListener('change', () => {
                this.state.limit = this.limitSelect.value || '50';
                this.resetAndLoad();
            });

            this.forwarderSelect.addEventListener('change', () => {
                this.state.forwarder = this.forwarderSelect.value || 'ALL';
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

            this.tbody.addEventListener('click', async (event) => {

                const helperBtn = event.target.closest('.warehouse-sync-helper-btn');
                if (helperBtn) {
                    const itemId = helperBtn.dataset.itemId || '';
                    const connectorId = helperBtn.dataset.connectorId || '';
                    if (!itemId) return;
                    await this.showSyncProcessHelperAlert(itemId, connectorId, 'Подсказка по sync');
                    return;
                }
                const btn = event.target.closest('.warehouse-sync-row-btn');
                if (!btn) return;
                const itemId = btn.dataset.itemId || '';
                const connectorId = btn.dataset.connectorId || '';
                if (!itemId) return;

                btn.disabled = true;
                const prev = btn.textContent;
                btn.textContent = 'sync...';
                try {

                    const controlPlan = await this.fetchSyncControlPlan(itemId, connectorId);
                    const canExecute = !!controlPlan?.execution_plan?.can_execute;
                    if (!canExecute) {
                        btn.disabled = false;
                        btn.textContent = prev;
                        alert(this.formatSyncHelperMessage(controlPlan, 'sync остановлен preflight-проверкой'));
                        return;
                    }

                    const fd = new FormData();
                    fd.append('action', 'warehouse_sync_item');
                    fd.append('item_id', itemId);
                    if (connectorId) {
                        fd.append('connector_id', connectorId);
                    }
                    const data = await CoreAPI.client.call(fd);
                    if (!data || data.status !== 'ok') {
                        console.log('[warehouse_sync_item][single][node_payload]', data?.node_payload || null);
                        throw new Error(data?.message || 'sync error');
                    }
                    console.log('[warehouse_sync_item][single][node_payload]', data?.node_payload || null);
                    btn.classList.remove('btn-outline-primary');
                    btn.classList.add('btn-outline-success');
                    btn.textContent = 'synced';
                    alert(data.message || 'sync выполнен');
                    await this.resetAndLoad();
                    CoreAPI.warehouseSyncHistory.load();
                } catch (err) {
                    console.error('warehouse_sync_item error:', err);
                    btn.disabled = false;
                    btn.textContent = prev;
                    alert(`sync ошибка: ${err?.message || err}`);
                    await this.showSyncProcessHelperAlert(itemId, connectorId, 'Подсказка после ошибки sync');
                    CoreAPI.warehouseSyncHistory.load();
                }
            });


            this.backfillBtn.addEventListener('click', async () => {
                const limitRaw = prompt('Backfill limit (1..5000)', '1000');
                if (limitRaw === null) return;
                const limit = Math.max(1, Math.min(5000, Number.parseInt(limitRaw, 10) || 1000));

                this.backfillBtn.disabled = true;
                const prevText = this.backfillBtn.textContent;
                this.backfillBtn.textContent = 'backfill...';
                try {
                    const fd = new FormData();
                    fd.append('action', 'warehouse_sync_out_backfill');
                    fd.append('limit', String(limit));
                    const data = await CoreAPI.client.call(fd);
                    if (!data || data.status !== 'ok') {
                        throw new Error(data?.message || 'backfill error');
                    }
                    alert(`Backfill завершен. inserted: ${data.inserted || 0}, updated: ${data.updated || 0}`);
                    await this.resetAndLoad();
                    CoreAPI.warehouseSyncHistory.load();
                } catch (err) {
                    console.error('warehouse_sync_out_backfill error:', err);
                    alert(`backfill ошибка: ${err?.message || err}`);
                } finally {
                    this.backfillBtn.disabled = false;
                    this.backfillBtn.textContent = prevText;
                }
            });

            this.reconcileBtn.addEventListener('click', async () => {
                const limitRaw = prompt('Reconcile limit (1..2000)', '300');
                if (limitRaw === null) return;
                const limit = Math.max(1, Math.min(2000, Number.parseInt(limitRaw, 10) || 300));

                this.reconcileBtn.disabled = true;
                const prevText = this.reconcileBtn.textContent;
                this.reconcileBtn.textContent = 'reconcile...';
                try {
                    const fd = new FormData();
                    fd.append('action', 'warehouse_sync_reconcile');
                    fd.append('limit', String(limit));
                    const data = await CoreAPI.client.call(fd);
                    if (!data || data.status !== 'ok') {
                        throw new Error(data?.message || 'reconcile error');
                    }
                    const stats = data.stats || {};
                    alert(`Reconcile завершен. checked: ${stats.checked || 0}, confirmed_sync: ${stats.confirmed_sync || 0}, error: ${stats.error || 0}, unchanged: ${stats.unchanged || 0}`);
                    await this.resetAndLoad();
                    CoreAPI.warehouseSyncHistory.load();
                } catch (err) {
                    console.error('warehouse_sync_reconcile error:', err);
                    alert(`reconcile ошибка: ${err?.message || err}`);
                } finally {
                    this.reconcileBtn.disabled = false;
                    this.reconcileBtn.textContent = prevText;
                }
            });

            this.allSyncBtn.addEventListener('click', async () => {

                this.allSyncBtn.disabled = true;
                const previousText = this.allSyncBtn.textContent;
                this.allSyncBtn.textContent = 'all_sync...';
                let ok = 0;
                let fail = 0;

                try {
                    const targets = await this.collectAllSyncTargets();
                    const available = targets.length;
                    if (!available) {
                        alert('Нет посылок со статусом "Готов к синхронизации" в текущем фильтре');
                        return;
                    }

                    const preflightTargets = [];
                    const blockedSummaries = [];
                    for (const target of targets) {
                        const controlPlan = await this.fetchSyncControlPlan(target.itemId, target.connectorId || '');
                        const canExecute = !!controlPlan?.execution_plan?.can_execute;
                        if (canExecute) {
                            preflightTargets.push(target);
                        } else {
                            const stopReasons = Array.isArray(controlPlan?.execution_plan?.stop_reasons)
                                ? controlPlan.execution_plan.stop_reasons.filter((item) => String(item || '').trim() !== '')
                                : [];
                            blockedSummaries.push(`#${target.itemId}: ${stopReasons.length > 0 ? stopReasons.join('; ') : 'blocked by preflight'}`);
                        }
                    }

                    if (!preflightTargets.length) {
                        alert(`all_sync остановлен preflight: все ${available} шт. заблокированы.\n\n${blockedSummaries.join('\n')}`);
                        return;
                    }

                    const targetLabel = this.state.forwarder === 'ALL'
                        ? `для всех форвардов (${preflightTargets.length} из ${available} шт.)`
                        : `для форварда ${this.state.forwarder} (${preflightTargets.length} из ${available} шт.)`;
                    const blockedNote = blockedSummaries.length > 0
                        ? `\n\nБудут пропущены preflight-blocked: ${blockedSummaries.length}`
                        : '';
                    if (!confirm(`Запустить all_sync ${targetLabel}?${blockedNote}`)) {
                        return;
                    }

                    if (blockedSummaries.length > 0) {
                        alert(`all_sync preflight: пропущено ${blockedSummaries.length} шт.\n\n${blockedSummaries.join('\n')}`);
                    }

                    const fd = new FormData();
                    fd.append('action', 'warehouse_sync_batch_enqueue');
                    fd.append('targets_json', JSON.stringify(preflightTargets));
                    fd.append('forwarder', this.state.forwarder || 'ALL'); 
                    const data = await CoreAPI.client.call(fd);
                    if (!data || data.status !== 'ok') {
                        throw new Error(data?.message || 'enqueue error');
                    }

                    ok = Number.parseInt(data?.queued || '0', 10) || 0;
                    fail = Number.parseInt(data?.skipped || '0', 10) || 0;
                    alert(`Задача поставлена в background. Job #${data.job_id}. В очереди: ${ok}, пропущено: ${fail}`);
                    await this.resetAndLoad();
                    CoreAPI.warehouseSyncHistory.load();
                } catch (err) {
                    console.error('warehouse_sync_all error:', err);
                    alert(`all_sync ошибка: ${err?.message || err}`);
                } finally {
                    this.allSyncBtn.disabled = false;
                    this.allSyncBtn.textContent = previousText;
                }
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
        async resetAndLoad() {
            this.state.offset = 0;
            this.state.done = false;
            this.tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center text-muted">Загрузка...</td>
                </tr>
            `;
            if (this.total) {
                this.total.textContent = '0';
            }
            this.updateAllSyncState();
            await this.loadNext();
        },
        updateAllSyncState() {
            if (!this.allSyncBtn || !this.tbody) return;
            const available = this.tbody.querySelectorAll('.warehouse-sync-row-btn').length;
            this.allSyncBtn.disabled = available === 0;
        },
        setLoading(isLoading) {
            this.state.loading = isLoading;
            if (this.sentinel) {
                this.sentinel.textContent = isLoading ? 'Загрузка...' : '';
            }
        },


        extractSyncTargetsFromHtml(html) {
            const markup = String(html || '').trim();
            if (!markup) return [];
            const parser = new DOMParser();
            const doc = parser.parseFromString(`<table><tbody>${markup}</tbody></table>`, 'text/html');
            return Array.from(doc.querySelectorAll('.warehouse-sync-row-btn')).map((btn) => ({
                itemId: btn.dataset.itemId || '',
                connectorId: btn.dataset.connectorId || ''
            })).filter((target) => target.itemId);
        },
        async collectAllSyncTargets() {
            const targets = [];
            const seen = new Set();
            let offset = 0;
            const pageLimit = '200';
            let hasMore = true;

            while (hasMore) {
                const fd = new FormData();
                fd.append('action', 'warehouse_sync_missing');
                fd.append('limit', pageLimit);
                fd.append('offset', String(offset));
                fd.append('search', this.state.search);
                fd.append('forwarder', this.state.forwarder);

                const data = await CoreAPI.client.call(fd);
                if (!data || data.status !== 'ok') {
                    throw new Error(data?.message || 'load list error');
                }

                const pageTargets = this.extractSyncTargetsFromHtml(data.html);
                for (const target of pageTargets) {
                    if (!seen.has(target.itemId)) {
                        seen.add(target.itemId);
                        targets.push(target);
                    }
                }

                const loaded = Number(data.items_count ?? 0);
                if (loaded <= 0 || data.has_more === false) {
                    hasMore = false;
                } else {
                    offset += loaded;
                }
            }

            return targets;
        },
        async loadNext() {
            if (this.state.loading || this.state.done) return;
            if (this.state.limit === 'all' && this.state.offset > 0) return;
            this.setLoading(true);
            const fd = new FormData();
            fd.append('action', 'warehouse_sync_missing');
            fd.append('limit', this.state.limit);
            fd.append('offset', String(this.state.offset));
            fd.append('search', this.state.search);
            fd.append('forwarder', this.state.forwarder);
            try {
                const data = await CoreAPI.client.call(fd);
                if (!data || data.status !== 'ok') {
                    console.error('core_api error (warehouse_sync_missing):', data);
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
                this.updateAllSyncState();
                const loaded = Number(data.items_count ?? 0);
                this.state.offset += loaded;
                if (this.state.limit === 'all') {
                    this.state.done = true;
                } else {
                    this.state.done = loaded === 0 || data.has_more === false;
                }
            } catch (err) {
                console.error('core_api fetch error (warehouse_sync_missing):', err);
            } finally {
                this.setLoading(false);
            }
        }
    },


    warehouseItemOut: {
        root: null,
        tbody: null,
        total: null,
        searchInput: null,
        limitSelect: null,
        forwarderSelect: null,
        sentinel: null,
        observer: null,
        searchTimer: null,
        initialized: false,
        state: {
            limit: '50',
            offset: 0,
            search: '',
            forwarder: 'ALL',
            loading: false,
            done: false
        },
        init() {
            const root = document.getElementById('warehouse-item-out');
            if (!root) return;
            if (this.initialized && this.root === root) {
                this.resetAndLoad();
                return;
            }
            if (this.observer) {
                this.observer.disconnect();
            }
            this.root = root;
            this.tbody = root.querySelector('#warehouse-item-out-tbody');
            this.total = root.querySelector('#warehouse-item-out-total');
            this.searchInput = root.querySelector('#warehouse-item-out-search');
            this.limitSelect = root.querySelector('#warehouse-item-out-limit');
            this.forwarderSelect = root.querySelector('#warehouse-item-out-forwarder');
            this.sentinel = root.querySelector('#warehouse-item-out-sentinel');

            if (!this.tbody || !this.total || !this.searchInput || !this.limitSelect || !this.forwarderSelect || !this.sentinel) {
                return;
            }

            this.state.limit = this.limitSelect.value || '50';
            this.state.forwarder = this.forwarderSelect.value || 'ALL';
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
                this.state.limit = this.limitSelect.value || '50';
                this.resetAndLoad();
            });

            this.forwarderSelect.addEventListener('change', () => {
                this.state.forwarder = this.forwarderSelect.value || 'ALL';
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
        setLoading(isLoading) {
            this.state.loading = isLoading;
            if (this.sentinel) {
                this.sentinel.textContent = isLoading ? 'Загрузка...' : '';
            }
        },
        async resetAndLoad() {
            this.state.offset = 0;
            this.state.done = false;
            this.tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center text-muted">Загрузка...</td>
                </tr>
            `;
            if (this.total) {
                this.total.textContent = '0';
            }
            await this.loadNext();
        },
        async loadNext() {
            if (this.state.loading || this.state.done) return;
            if (this.state.limit === 'all' && this.state.offset > 0) return;
            this.setLoading(true);

            const fd = new FormData();
            fd.append('action', 'warehouse_item_out_to_send');
            fd.append('limit', this.state.limit);
            fd.append('offset', String(this.state.offset));
            fd.append('search', this.state.search);
            fd.append('forwarder', this.state.forwarder);

            try {
                const data = await CoreAPI.client.call(fd);
                if (!data || data.status !== 'ok') {
                    console.error('core_api error (warehouse_item_out_to_send):', data);
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
                console.error('core_api fetch error (warehouse_item_out_to_send):', err);
            } finally {
                this.setLoading(false);
            }
        }
    },

    departures: {
        root: null,
        tbody: null,
        total: null,
        forwarderFilter: null,
        statusFilter: null,
        addFlightDateInput: null,
        addFlightAwbInput: null,
        addFlightStatus: null,
        activeActionButton: null,
        initialized: false,
        init() {
            const root = document.getElementById('departures-page');
            if (!root) return;

            const shouldBindEvents = !this.initialized || this.root !== root;
            this.root = root;
            this.tbody = root.querySelector('#departures-tbody');
            this.total = document.getElementById('departures-total');
            this.forwarderFilter = root.querySelector('#departures-forwarder-filter');
            this.statusFilter = root.querySelector('#departures-status-filter');
            this.addFlightDateInput = root.querySelector('#departures-add-flight-date');
            this.addFlightAwbInput = root.querySelector('#departures-add-flight-awb');
            this.addFlightStatus = root.querySelector('#departures-add-flight-status');

            if (!this.tbody || !this.total || !this.forwarderFilter || !this.statusFilter) {
                return;
            }

            if (this.addFlightDateInput && !this.addFlightDateInput.value) {
                const today = new Date();
                const yyyy = today.getFullYear();
                const mm = String(today.getMonth() + 1).padStart(2, '0');
                const dd = String(today.getDate()).padStart(2, '0');
                this.addFlightDateInput.value = `${yyyy}-${mm}-${dd}`;
            }

            if (shouldBindEvents) {
                this.bindEvents();
            }

            this.load();
            this.initialized = true;
        },
        bindEvents() {
            this.forwarderFilter.addEventListener('change', () => this.load());
            this.statusFilter.addEventListener('change', () => this.load());
            if (this.addFlightAwbInput) {
                this.addFlightAwbInput.addEventListener('input', () => {
                    const normalized = this.normalizeAwb(this.addFlightAwbInput.value || '');
                    if (this.addFlightAwbInput.value !== normalized) {
                        this.addFlightAwbInput.value = normalized;
                    }
                });
            }
            this.root.addEventListener('click', (event) => {

                const actionToggle = event.target.closest('.js-departure-action-toggle');
                if (actionToggle) {
                    const targetId = actionToggle.getAttribute('data-target') || '';
                    if (!targetId) return;

                    const target = document.getElementById(targetId);
                    if (!target) return;

                    const isOpen = actionToggle.getAttribute('data-open') === '1';
                    actionToggle.setAttribute('data-open', isOpen ? '0' : '1');
                    actionToggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
                    target.classList.toggle('d-none', isOpen);
                    return;
                }

                const placeholderButton = event.target.closest('.js-departure-placeholder-action');
                if (placeholderButton) {
                    const operation = placeholderButton.getAttribute('data-operation') || '';
                    if (operation === 'add_flight') {
                        this.triggerAddFlight(placeholderButton);
                        return;
                    }
                    const payload = {
                        operation,
                        flight: placeholderButton.getAttribute('data-flight') || ''
                    };
                    console.info('Departure placeholder action', payload);
                    return;
                }
                const button = event.target.closest('.js-departure-toggle');
                if (!button) return;

                const targetId = button.getAttribute('data-target') || '';
                if (!targetId) return;

                const detailRow = document.getElementById(targetId);
                if (!detailRow) return;

                const isOpen = button.getAttribute('data-open') === '1';
                button.setAttribute('data-open', isOpen ? '0' : '1');
                detailRow.classList.toggle('d-none', isOpen);

                const icon = button.querySelector('i');
                if (icon) {
                    icon.classList.toggle('bi-chevron-down', isOpen);
                    icon.classList.toggle('bi-chevron-up', !isOpen);
                }
            });
        },

        normalizeAwb(value) {
            return String(value || '').replace(/\D+/g, '');
        },
        setActionStatus(message, tone) {
            if (!this.addFlightStatus) return;
            const text = String(message || '').trim();
            this.addFlightStatus.textContent = text;
            this.addFlightStatus.classList.remove('text-muted', 'text-success', 'text-danger', 'text-primary');
            if (!text) {
                this.addFlightStatus.classList.add('text-muted');
                return;
            }
            const toneClassMap = {
                success: 'text-success',
                danger: 'text-danger',
                primary: 'text-primary'
            };
            this.addFlightStatus.classList.add(toneClassMap[tone] || 'text-muted');
        },
        setActionBusy(button, isBusy) {
            if (!button) return;
            if (isBusy) {
                if (!button.dataset.originalLabel) {
                    button.dataset.originalLabel = button.textContent || '';
                }
                button.disabled = true;
                button.textContent = 'Выполняется...';
                this.activeActionButton = button;
                return;
            }

            button.disabled = false;
            if (button.dataset.originalLabel) {
                button.textContent = button.dataset.originalLabel;
            }
            if (this.activeActionButton === button) {
                this.activeActionButton = null;
            }
        },
        async runConnectorOperation(connectorId, operationId, runtimeVars) {
            const fd = new FormData();
            fd.append('action', 'test_connector_operations');
            fd.append('connector_id', String(connectorId));
            fd.append('test_operation', String(operationId || ''));
            if (runtimeVars && typeof runtimeVars === 'object') {
                fd.append('runtime_vars_json', JSON.stringify(runtimeVars));
            }
            const data = await CoreAPI.client.call(fd);
            if (!data || data.status !== 'ok') {
                const err = new Error(data?.message || `Операция ${operationId} завершилась ошибкой`);
                err.payload = data;
                throw err;
            }
            return data;
        },
        async triggerAddFlight(button) {
            const connectorId = Number(this.forwarderFilter?.value || 0);
            if (!connectorId) {
                alert('Сначала выберите конкретного форварда вместо "Все форварды".');
                return;
            }

            const dateSelector = button?.getAttribute('data-date-input') || '';
            const awbSelector = button?.getAttribute('data-input') || '';
            const dateInput = dateSelector ? this.root.querySelector(dateSelector) : this.addFlightDateInput;
            const awbInput = awbSelector ? this.root.querySelector(awbSelector) : this.addFlightAwbInput;
            const refreshOperation = button?.getAttribute('data-refresh-operation') || 'flight_list';
            const setDate = (dateInput?.value || '').trim();
            const awb = this.normalizeAwb(awbInput?.value || '');

            if (!setDate) {
                alert('Укажите дату рейса.');
                dateInput?.focus();
                return;
            }
            if (!awb) {
                alert('Укажите AWB цифрами без префикса AWB.');
                awbInput?.focus();
                return;
            }

            if (awbInput) {
                awbInput.value = awb;
            }

            this.setActionBusy(button, true);
            this.setActionStatus(`Запускаю add_flight для AWB ${awb}...`, 'primary');

            try {
                const runtimeVars = {
                    set_date: setDate,
                    add_flight: awb
                };
                const addFlightResult = await this.runConnectorOperation(connectorId, 'add_flight', runtimeVars);
                this.setActionStatus(addFlightResult?.message || 'Рейс добавлен. Обновляю список рейсов...', 'primary');
                if (refreshOperation) {
                    await this.runConnectorOperation(connectorId, refreshOperation, runtimeVars);
                }
                await this.load();
                this.setActionStatus(`Рейс для AWB ${awb} добавлен, список рейсов обновлён.`, 'success');
                showToast('Рейс добавлен и список рейсов обновлён', 2500);
            } catch (err) {
                console.error('core_api error (departures add_flight):', err?.payload || err);
                this.setActionStatus(err?.message || 'Не удалось выполнить add_flight.', 'danger');
                alert(err?.message || 'Не удалось выполнить add_flight');
            } finally {
                this.setActionBusy(button, false);
            }
        },
        async load() {
            if (!this.tbody) return;

            this.tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-muted">Загрузка...</td>
                </tr>
            `;

            const fd = new FormData();
            fd.append('action', 'departures_flights');
            fd.append('forwarder_id', this.forwarderFilter?.value || 'ALL');
            fd.append('flight_status', this.statusFilter?.value || 'ALL');

            try {
                const data = await CoreAPI.client.call(fd);
                if (!data || data.status !== 'ok') {
                    console.error('core_api error (departures_flights):', data);
                    return;
                }

                this.tbody.innerHTML = data.html || '';
                if (this.total) {
                    this.total.textContent = String(data.total ?? 0);
                }
            } catch (err) {
                console.error('core_api fetch error (departures_flights):', err);
                this.tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center text-danger">Не удалось загрузить список рейсов.</td>
                    </tr>
                `;
            }
        }
    },

    warehouseSyncReports: {
        root: null,
        tbody: null,
        total: null,
        initialized: false,
        init() {
            const root = document.getElementById('warehouse-sync-reports');
            if (!root) return;
            this.root = root;
            this.tbody = root.querySelector('#warehouse-sync-reports-tbody');
            this.total = root.querySelector('#warehouse-sync-reports-total');
            if (!this.tbody || !this.total) {
                return;
            }
            this.load();
            this.initialized = true;
        },
        async load() {
            this.tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center text-muted">Загрузка...</td>
                </tr>
            `;
            const fd = new FormData();
            fd.append('action', 'warehouse_sync_reports');
            try {
                const data = await CoreAPI.client.call(fd);
                if (!data || data.status !== 'ok') {
                    console.error('core_api error (warehouse_sync_reports):', data);
                    return;
                }
                this.tbody.innerHTML = data.html || '';
                this.total.textContent = String(data.total ?? 0);
            } catch (err) {
                console.error('core_api fetch error (warehouse_sync_reports):', err);
            }
        }
    },


    warehouseSyncHistory: {
        root: null,
        tbody: null,
        total: null,
        statusFilter: null,
        trackingFilter: null,
        limitSelect: null,
        searchTimer: null,
        initialized: false,
        init() {
            const root = document.getElementById('warehouse-sync-history');
            if (!root) return;
            const shouldBindEvents = !this.initialized || this.root !== root;
            this.root = root;
            this.tbody = root.querySelector('#warehouse-sync-history-tbody');
            this.total = root.querySelector('#warehouse-sync-history-total');
            this.statusFilter = root.querySelector('#warehouse-sync-history-status-filter');
            this.trackingFilter = root.querySelector('#warehouse-sync-history-tracking-filter');
            this.limitSelect = root.querySelector('#warehouse-sync-history-limit');
            if (!this.tbody || !this.total || !this.statusFilter || !this.trackingFilter || !this.limitSelect) {
                return;
            }
            if (shouldBindEvents) {
                this.bindEvents();
            }
            this.load();
            this.initialized = true;
        },
        bindEvents() {
            this.statusFilter.addEventListener('change', () => this.load());
            this.limitSelect.addEventListener('change', () => this.load());
            this.trackingFilter.addEventListener('input', () => {
                if (this.searchTimer) {
                    clearTimeout(this.searchTimer);
                }
                this.searchTimer = setTimeout(() => this.load(), 300);
            });
            this.trackingFilter.addEventListener('change', () => this.load());
            this.trackingFilter.addEventListener('search', () => this.load());
        },
        async load() {
            if (!this.tbody || !this.total) return;
            this.tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center text-muted">Загрузка...</td>
                </tr>
            `;
            const fd = new FormData();
            fd.append('action', 'warehouse_sync_history');
            fd.append('status_filter', this.statusFilter?.value || 'all');
            fd.append('tracking_no', (this.trackingFilter?.value || '').trim());
            fd.append('limit', this.limitSelect?.value || '50');
            try {
                const data = await CoreAPI.client.call(fd);
                if (!data || data.status !== 'ok') {
                    console.error('core_api error (warehouse_sync_history):', data);
                    return;
                }
                const rows = Array.isArray(data.rows) ? data.rows : [];
                if (!rows.length) {
                    this.tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Пока нет записей аудита</td></tr>';
                    this.total.textContent = '0';
                    return;
                }
                const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (ch) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch] || ch));
                this.tbody.innerHTML = rows.map((row) => {
                    const status = String(row.status || '').toLowerCase();
                    const statusStyle = {
                        error: 'text-danger',
                        for_sync: 'text-warning',
                        half_sync: 'text-warning',
                        confirmed_sync: 'text-success',
                        to_send: 'text-primary',
                        sended: 'text-info',
                        success: 'text-success'
                    };
                    const statusClass = statusStyle[status] || 'text-muted';
                    const statusLabel = status || 'unknown';
                    const createdAt = String(row.created_at || '');
                    const tracking = String(row.tracking_no || '—');
                    const forwarder = String(row.forwarder || '—');
                    const country = String(row.country_code || '—');
                    const message = String(row.message || '');
                    const userName = String(row.user_name || '—');
                    const itemId = String(row.item_id || '');
                    return `
                        <tr>
                          <td>${esc(createdAt)}</td>
                          <td>${esc(tracking)}${itemId ? `<div class="small text-muted">#${esc(itemId)}</div>` : ''}</td>
                          <td>${esc(forwarder)}</td>
                          <td>${esc(country)}</td>
                          <td class="${statusClass}">${esc(statusLabel)}</td>
                          <td>${esc(message)}</td>
                          <td>${esc(userName)}</td>
                        </tr>
                    `;
                }).join('');
                this.total.textContent = String(data.total ?? rows.length);
            } catch (err) {
                console.error('core_api fetch error (warehouse_sync_history):', err);
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
    // WAREHOUSE - Move (box items preview)
    // ====================================
    warehouseMoveBox: {
        root: null,
        tbody: null,
        total: null,
        fromCellSelect: null,
        initialized: false,
        init() {
            const root = document.getElementById('warehouse-move-box');
            if (!root) return;
            if (this.initialized && this.root === root) {
                return;
            }
            this.root = root;
            this.tbody = root.querySelector('#warehouse-move-box-items-tbody');
            this.total = root.querySelector('#warehouse-move-box-total');
            this.fromCellSelect = root.querySelector('#warehouse-move-box-from-cell');

            if (!this.tbody || !this.total || !this.fromCellSelect) {
                return;
            }

            this.bindEvents();
            this.initialized = true;
            this.refreshAfterMove();
        },
        bindEvents() {
            const handleCellChange = () => {
                const cellId = this.fromCellSelect.value || '';
                if (!cellId) {
                    this.clearResults();
                    return;
                }
                this.fetchItems(cellId);
            };
            this.fromCellSelect.addEventListener('change', handleCellChange);
            this.fromCellSelect.addEventListener('input', handleCellChange);
        },
        clearResults() {
            if (this.tbody) {
                this.tbody.innerHTML = '';
            }
            if (this.total) {
                this.total.textContent = '0';
            }
        },
        async fetchItems(fromCellId) {
            const fd = new FormData();
            fd.append('action', 'warehouse_move_box_items');
            fd.append('from_cell_id', fromCellId);
            try {
                const data = await CoreAPI.client.call(fd);
                if (!data || data.status !== 'ok') {
                    console.error('core_api error (warehouse_move_box_items):', data);
                    this.clearResults();
                    return;
                }
                if (this.tbody) {
                    this.tbody.innerHTML = data.html || '';
                }
                if (this.total) {
                    this.total.textContent = String(data.total ?? 0);
                }
            } catch (err) {
                console.error('core_api fetch error (warehouse_move_box_items):', err);
                this.clearResults();
            }
        },
        refreshAfterMove() {
            const fromCellId = this.fromCellSelect?.value || '';
            if (!fromCellId) {
                this.clearResults();
                return;
            }
            this.fetchItems(fromCellId);
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
            if (event?.target?.id === 'warehouse-sync-missing-tab') {
                this.warehouseSync.init();
            }
            if (event?.target?.id === 'warehouse-sync-reports-tab') {
                this.warehouseSyncReports.init();
            }
            if (event?.target?.id === 'warehouse-sync-history-tab') {
                this.warehouseSyncHistory.init();
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
        this.warehouseSync.init();
        this.warehouseSyncReports.init();
        this.warehouseSyncHistory.init();
        this.warehouseMove.init();
        this.warehouseMoveBatch.init();
        this.warehouseMoveBox.init();
        this.warehouseItemOut.init();
        this.departures.init();
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
        //emptyOpt.value = '';
        //emptyOpt.textContent = '— выберите —';
        //select.appendChild(emptyOpt);

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
        fd.append('item_id', document.getElementById('itemInDraftId')?.value || '0');

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


    if (document.getElementById('item-in-modal-form')) {
        try {
            await ensureItemInDraftCreated();
        } catch (e) {
            alert(e?.message || 'Не удалось создать черновик посылки');
        }
    }
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


function isOcrScannerWebView() {
    return !!(window.DeviceApp && typeof window.DeviceApp === 'object');
}

function closeWarehousePhotoOverlay(fromPopState) {
    var overlay = document.getElementById('warehousePhotoPreviewOverlay');
    if (!overlay) return;
    overlay.classList.add('d-none');

    if (!fromPopState) {
        try {
            if (window.history && window.history.state && window.history.state.warehousePhotoPreview === true) {
                window.history.back();
            }
        } catch (e) {}
    }
}

function ensureWarehousePhotoOverlay() {
    var existing = document.getElementById('warehousePhotoPreviewOverlay');
    if (existing) return existing;

    var overlay = document.createElement('div');
    overlay.id = 'warehousePhotoPreviewOverlay';
    overlay.className = 'position-fixed top-0 start-0 w-100 h-100 d-none';
    overlay.style.zIndex = '2000';
    overlay.style.background = '#111';

    overlay.innerHTML =
        '<div class="d-flex justify-content-end p-2">' +
        '<button type="button" class="btn btn-outline-light" data-photo-preview-close="1" aria-label="Закрыть" title="Закрыть">&times;</button>' +
        '</div>' +
        '<div class="d-flex align-items-center justify-content-center" style="height:calc(100% - 56px);padding:10px;">' +
        '<img data-photo-preview-image="1" alt="Фото" style="max-width:100%;max-height:100%;object-fit:contain;">' +
        '</div>';

    overlay.querySelector('[data-photo-preview-close="1"]').addEventListener('click', function () {
        closeWarehousePhotoOverlay(false);
    });

    document.body.appendChild(overlay);

    if (!window.__warehousePhotoPreviewPopstateInstalled) {
        window.__warehousePhotoPreviewPopstateInstalled = true;
        window.addEventListener('popstate', function (event) {
            var state = event && event.state ? event.state : null;
            if (state && state.warehousePhotoPreview === true) {
                return;
            }
            closeWarehousePhotoOverlay(true);
        });
    }

    return overlay;
}

function openWarehousePhotoPreview(photoPath) {
    var normalizedPath = String(photoPath || '').trim();
    if (!normalizedPath) return;

    if (isOcrScannerWebView()) {
        var overlay = ensureWarehousePhotoOverlay();
        var img = overlay.querySelector('[data-photo-preview-image="1"]');
        if (img) {
            img.src = normalizedPath;
        }
        overlay.classList.remove('d-none');
        try {
            window.history.pushState({ warehousePhotoPreview: true }, '');
        } catch (e) {}
        return;
    }

    var previewWindow = window.open('', '_blank');
    if (!previewWindow) {
        window.open(normalizedPath, '_blank');
        return;
    }

    var escapedPath = normalizedPath
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

    previewWindow.document.write(
        '<!doctype html>' +
        '<html lang="ru"><head><meta charset="utf-8"><title>Просмотр фото</title>' +
        '<meta name="viewport" content="width=device-width, initial-scale=1">' +
        '<style>' +
        'body{margin:0;background:#111;color:#fff;font-family:Arial,sans-serif;display:flex;flex-direction:column;height:100vh;}' +
        '.bar{padding:10px 14px;display:flex;justify-content:flex-end;}' +
        '.close{border:0;background:transparent;color:#fff;font-size:28px;line-height:1;cursor:pointer;}' +
        '.wrap{flex:1;display:flex;align-items:center;justify-content:center;padding:12px;}' +
        'img{max-width:100%;max-height:100%;object-fit:contain;}' +
        '</style></head><body>' +
        '<div class="bar"><button class="close" type="button" aria-label="Закрыть" title="Закрыть">&times;</button></div>' +
        '<div class="wrap"><img src="' + escapedPath + '" alt="Фото"></div>' +
        '<script>' +
        'function closePreview(){if(window.opener&&!window.opener.closed){window.opener.focus();}window.close();setTimeout(function(){if(!window.closed){if(window.history&&window.history.length>1){window.history.back();}else{window.location.href="about:blank";}}},60);}' +
        'document.querySelector(".close").addEventListener("click",closePreview);' +
        'document.addEventListener("keydown",function(e){if(e.key==="Escape"){closePreview();}});' +
        '<\/script></body></html>'
    );
    previewWindow.document.close();
}

function parseWarehousePhotoJson(jsonValue, fallbackPath) {
    var value = String(jsonValue || '').trim();
    if (value) {
        try {
            var parsed = JSON.parse(value);
            if (Array.isArray(parsed)) {
                return parsed.filter(function (entry) {
                    return typeof entry === 'string' && entry.trim() !== '';
                });
            }
            if (typeof parsed === 'string' && parsed.trim() !== '') {
                return [parsed.trim()];
            }
        } catch (e) {}
    }
    return fallbackPath ? [fallbackPath] : [];
}

function deleteWarehouseItemStockPhoto(photoType) {
    var stockItemId = document.querySelector('#item-stock-modal-form input[name="item_id"]')?.value || '';
    var draftItemId = document.getElementById('itemInDraftId')?.value || '';
    var itemId = stockItemId || draftItemId;
    var isDraft = !!document.getElementById('item-in-modal-form');
    if (!itemId || !photoType) {
        alert('Не удалось удалить фото');
        return;
    }

    var fd = new FormData();
    fd.append('action', isDraft ? 'delete_item_in_photo' : 'delete_item_stock_photo');
    fd.append('item_id', itemId);
    fd.append('photo_type', photoType);

    CoreAPI.client.call(fd)
        .then(function (data) {
            if (!data || data.status !== 'ok') {
                alert(data?.message || 'Ошибка удаления фото');
                return;
            }
            setWarehouseItemStockPhotoState(photoType, '', data.json || '');
        })
        .catch(function (err) {
            console.error('core_api delete_item_stock_photo error:', err);
            alert('Ошибка связи с сервером');
        });
}

function renderWarehousePhotoPreviewButtons(photoType, paths) {
    var isLabel = photoType === 'label';
    var info = document.getElementById(isLabel ? 'warehouseStockLabelPhotoInfo' : 'warehouseStockBoxPhotoInfo');
    if (!info) return;

    info.innerHTML = '';
    if (!Array.isArray(paths) || paths.length === 0) return;

    var holder = document.createElement('div');
    holder.className = 'd-flex flex-wrap gap-2 align-items-center';

    paths.forEach(function (path, index) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-outline-primary';
        button.textContent = paths.length > 1 ? ('Открыть фото ' + (index + 1)) : 'Открыть фото';
        button.addEventListener('click', function () {
            openWarehousePhotoPreview(path);
        });
        holder.appendChild(button);
    });

    var deleteButton = document.createElement('button');
    deleteButton.type = 'button';
    deleteButton.className = 'btn btn-sm btn-outline-danger';
    deleteButton.textContent = 'Удалить фото';
    deleteButton.addEventListener('click', function () {
        deleteWarehouseItemStockPhoto(photoType);
    });
    holder.appendChild(deleteButton);

    info.appendChild(holder);
}


function setWarehouseItemStockPhotoState(photoType, path, jsonValue) {
    var isLabel = photoType === 'label';
    var hidden = document.getElementById(isLabel ? 'warehouseStockLabelImageJson' : 'warehouseStockBoxImageJson');
    if (hidden) hidden.value = jsonValue || '';
    var paths = parseWarehousePhotoJson(jsonValue, path);
    renderWarehousePhotoPreviewButtons(photoType, paths);
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
    setWarehouseItemStockPhotoState('label', '', labelHidden ? labelHidden.value : '');
    setWarehouseItemStockPhotoState('box', '', boxHidden ? boxHidden.value : '');
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


function setItemInDraftControlsEnabled(enabled) {
    var section = document.getElementById('warehouseStockAddonsSection');
    var labelBtn = document.getElementById('warehouseStockTakeLabelPhotoBtn');
    var boxBtn = document.getElementById('warehouseStockTakeBoxPhotoBtn');
    var lockTextId = 'itemInDraftLockText';

    [section, labelBtn, boxBtn].forEach(function (el) {
        if (!el) return;
        el.classList.toggle('opacity-50', !enabled);
    });

    if (labelBtn) labelBtn.disabled = !enabled;
    if (boxBtn) boxBtn.disabled = !enabled;
    if (section) {
        section.querySelectorAll('select[data-addon-key]').forEach(function (el) {
            el.disabled = !enabled;
        });
    }

    var lockNode = document.getElementById(lockTextId);
    if (section) {
        if (!lockNode) {
            lockNode = document.createElement('div');
            lockNode.id = lockTextId;
            lockNode.className = 'form-text text-warning';
            section.appendChild(lockNode);
        }
        lockNode.textContent = enabled ? '' : 'Сначала нажмите «Получить измерения», чтобы активировать ДопИнфо и фото.';
    }
}

async function ensureItemInDraftCreated() {
    var form = document.getElementById('item-in-modal-form');
    if (!form) return null;

    var itemIdField = document.getElementById('itemInDraftId');
    if (itemIdField && itemIdField.value) {
        setItemInDraftControlsEnabled(true);
        return itemIdField.value;
    }

    var fd = new FormData(form);
    fd.append('action', 'save_item_in_draft');
    var data = await CoreAPI.client.call(fd);
    if (!data || data.status !== 'ok' || !data.item_id) {
        throw new Error(data?.message || 'Не удалось создать черновик');
    }
    if (itemIdField) itemIdField.value = String(data.item_id);
    var batchField = form.querySelector('input[name="batch_uid"]');
    if (batchField && data.batch_uid) {
        batchField.value = String(data.batch_uid);
    }
    setItemInDraftControlsEnabled(true);
    return String(data.item_id);
}

async function clearItemInDraftForm() {
    var form = document.getElementById('item-in-modal-form');
    if (!form) return;
    var itemIdField = document.getElementById('itemInDraftId');
    var itemId = itemIdField ? (itemIdField.value || '') : '';

    if (itemId) {
        var fd = new FormData();
        fd.append('action', 'clear_item_in_draft');
        fd.append('item_id', itemId);
        try { await CoreAPI.client.call(fd); } catch (e) {}
    }

    form.querySelectorAll('input[type="text"], input[type="number"], input[type="hidden"], select').forEach(function (el) {
        if (!el.name) return;
        if (el.name === 'batch_uid') return;
        if (el.id === 'itemInDraftId') return;
        if (el.tagName === 'SELECT') {
            el.selectedIndex = 0;
        } else {
            el.value = '';
        }
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    });

    if (itemIdField) itemIdField.value = '';
    setWarehouseItemStockPhotoState('label', '', '');
    setWarehouseItemStockPhotoState('box', '', '');
    var addons = document.getElementById('warehouseStockAddonsJson');
    if (addons) addons.value = '';
    setItemInDraftControlsEnabled(false);
}



function isItemInDraftFormDirty() {
    var form = document.getElementById('item-in-modal-form');
    if (!form) return false;

    var controls = form.querySelectorAll('input[type="text"], input[type="number"], select, textarea');
    for (var i = 0; i < controls.length; i += 1) {
        var el = controls[i];
        if (el.disabled) continue;
        if (el.tagName === 'SELECT') {
            if (el.value) return true;
            continue;
        }
        if ((el.value || '').trim() !== '') return true;
    }
    return false;
}

async function clearItemInDraftBeforeModalClose() {
    var form = document.getElementById('item-in-modal-form');
    if (!form) return;
    if (form.__itemInDraftClosingInProgress) return;
    form.__itemInDraftClosingInProgress = true;
    try {
        await clearItemInDraftForm();
    } finally {
        form.__itemInDraftClosingInProgress = false;
    }
}

function hasManualMeasurementValues() {
    var ids = ['weightKg', 'sizeL', 'sizeW', 'sizeH'];
    for (var i = 0; i < ids.length; i += 1) {
        var el = document.getElementById(ids[i]);
        if (!el || el.disabled) continue;
        if ((el.value || '').trim() !== '') {
            return true;
        }
    }
    return false;
}

function triggerDraftCreationByManualMeasurements() {
    var form = document.getElementById('item-in-modal-form');
    if (!form) return;
    if (!hasManualMeasurementValues()) return;
    if (document.getElementById('itemInDraftId')?.value) {
        setItemInDraftControlsEnabled(true);
        return;
    }
    if (form.__manualMeasurementDraftPending) return;

    form.__manualMeasurementDraftPending = true;
    ensureItemInDraftCreated().catch(function (e) {
        console.warn('Не удалось создать черновик по ручным измерениям', e);
    }).finally(function () {
        form.__manualMeasurementDraftPending = false;
    });
}

function initItemInDraftControls() {
    var form = document.getElementById('item-in-modal-form');
    if (!form || form.__itemInDraftBound) return;
    form.__itemInDraftBound = true;

    setItemInDraftControlsEnabled(!!(document.getElementById('itemInDraftId')?.value));

    var clearBtn = document.getElementById('itemInClearBtn');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            clearItemInDraftForm();
        });
    }

    ['weightKg', 'sizeL', 'sizeW', 'sizeH'].forEach(function (id) {
        var field = document.getElementById(id);
        if (!field) return;
        field.addEventListener('input', triggerDraftCreationByManualMeasurements);
        field.addEventListener('change', triggerDraftCreationByManualMeasurements);
    });
    triggerDraftCreationByManualMeasurements();

    var modalEl = document.getElementById('fullscreenModal');
    if (modalEl && !modalEl.__itemInDraftHideHandlerBound) {
        modalEl.__itemInDraftHideHandlerBound = true;
        modalEl.addEventListener('hide.bs.modal', function (event) {
            if (!document.getElementById('item-in-modal-form')) return;
            if (modalEl.dataset.itemInDraftAllowClose === '1') {
                delete modalEl.dataset.itemInDraftAllowClose;
                return;
            }
            if (!isItemInDraftFormDirty() && !(document.getElementById('itemInDraftId')?.value)) {
                modalEl.dataset.itemInDraftAllowClose = '1';
                return;
            }

            event.preventDefault();
            var closeTriggered = false;
            var finalizeClose = function () {
                if (closeTriggered) return;
                closeTriggered = true;
                modalEl.dataset.itemInDraftAllowClose = '1';
                if (window.bootstrap?.Modal) {
                    var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                    modal.hide();
                }
            };

            clearItemInDraftBeforeModalClose().finally(finalizeClose);
            setTimeout(finalizeClose, 1500);
        });
    }
}

window.clearItemInDraftForm = clearItemInDraftForm;

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

CoreAPI.pageInits.warehouse_move = function warehouseMoveInit() {
    if (CoreAPI.warehouseMove?.init) {
        CoreAPI.warehouseMove.init();
    }
    if (CoreAPI.warehouseMoveBatch?.init) {
        CoreAPI.warehouseMoveBatch.init();
    }
    if (CoreAPI.warehouseMoveBox?.init) {
        CoreAPI.warehouseMoveBox.init();
    }
};

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


CoreAPI.pageInits.departures = function departuresInit() {
    if (CoreAPI.departures?.init) {
        CoreAPI.departures.init();
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


  window.confirmBoxMove = function () {
    try {
      const btn = document.querySelector('#warehouse-move-box .js-core-link[data-core-action="warehouse_move_box_assign"]');
      if (!btn) return false;
      btn.click();
      return true;
    } catch (e) {
      console.error('confirmBoxMove error:', e);
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
