

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
            const response = await fetch('/core_api.php', {
                method: 'POST',
                body: formData
            });
            return this.parseJSON(response);
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

                'form_edit_user': () => this.withAttribute('user_id', link),
                'form_edit_device': () => this.withAttribute('device_id', link),
                'form_edit_tool_stock': () => this.withAttribute('tool_id', link),
                'form_edit_cell': () => this.withAttribute('cell_id', link),

                'delete_cell': () => this.withAttribute('cell_id', link),
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
                'open_item_in_batch': () => this.withAttribute('batch_uid', link)
            };
            const fd = builders[action] ? builders[action]() : new FormData();
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
                if (typeof emitDeviceContext === 'function') {
                    emitDeviceContext();
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
            }
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
                }
            }
        },
        'commit_item_in_batch': (data) => {
            alert(data.message || 'Партия завершена');
            CoreAPI.ui.closeModal();
            CoreAPI.ui.reloadList('warehouse_item_in');
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
            const link = e.target.closest('.js-core-link[data-core-action]');
            if (!link) return;
            e.preventDefault();
            const action = link.getAttribute('data-core-action');
            if (!action) return;
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
                    alert(data?.message || 'Ошибка при выполнении запроса');
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
        }
    },
    // ====================================
    // INIT - инициализация
    // ====================================
    init() {
        document.addEventListener('click', this.events.handleClick.bind(this));
        document.addEventListener('change', this.events.handlePhotoUpload.bind(this));
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


function setFieldValue(id, value) {
    var field = document.getElementById(id);
    if (!field) return;
    field.value = value;
    field.dispatchEvent(new Event('input', { bubbles: true }));
    field.dispatchEvent(new Event('change', { bubbles: true }));
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
