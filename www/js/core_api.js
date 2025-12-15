// =========================
// Хелперы
// =========================

// Перерисовать список пользователей
function reloadUserList() {
    const main = document.getElementById('main');
    if (!main) return;

    const fd = new FormData();
    fd.append('action', 'view_users');

    fetch('/core_api.php', {
        method: 'POST',
        body: fd
    })
        .then(r => r.json())
        .then(d => {
            if (!d || d.status !== 'ok') {
                console.error('core_api error (reloadUserList):', d);
                return;
            }
            if (d.html) {
                main.innerHTML = d.html;
            }
        })
        .catch(err => {
            console.error('core_api fetch error (reloadUserList):', err);
        });
}

// Перерисовать список оборотных инструментов (Раздел «Ресурсы»)
function reloadToolsStock() {
    const main = document.getElementById('main');
    if (!main) return;

    const fd = new FormData();
    fd.append('action', 'view_tools_stock');

    fetch('/core_api.php', {
        method: 'POST',
        body: fd
    })
        .then(r => r.json())
        .then(d => {
            if (!d || d.status !== 'ok') {
                console.error('core_api error (reloadToolsStock):', d);
                return;
            }
            if (d.html) {
                main.innerHTML = d.html;
            }
        })
        .catch(err => {
            console.error('core_api fetch error (reloadToolsStock):', err);
        });
}


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


function setSelectValWait(id,v,tries){
  var e=document.getElementById(id);
  if(!e) return;
  e.value=v;
  e.dispatchEvent(new Event('change',{bubbles:true}));
  if(e.value!==v && tries>0){
    setTimeout(function(){ setSelectValWait(id,v,tries-1); }, 120);
  }
}

// Перерисовать список незавершённых приходов
function reloadWarehouseItemIn() {
    const main = document.getElementById('main');
    if (!main) return;

    const fd = new FormData();
    fd.append('action', 'warehouse_item_in');  // ВАЖНО: НЕ view_users

    fetch('/core_api.php', {
        method: 'POST',
        body: fd
    })
        .then(r => r.json())
        .then(d => {
            if (!d || d.status !== 'ok') {
                console.error('core_api error (reloadWarehouseItemIn):', d);
                return;
            }
            if (d.html) {
                main.innerHTML = d.html;
            }
        })
        .catch(err => {
            console.error('core_api fetch error (reloadWarehouseItemIn):', err);
        });
}

// Общий хелпер: один раз обновить что-то после закрытия модалки
function setReloadOnModalCloseOnce(reloadFn) {
    const modalEl = document.getElementById('fullscreenModal');
    if (!modalEl || !window.bootstrap || !bootstrap.Modal) {
        // если нет модалки — сразу перерисуем
        reloadFn();
        return;
    }

    const handler = function () {
        modalEl.removeEventListener('hidden.bs.modal', handler);
        reloadFn();
    };

    modalEl.addEventListener('hidden.bs.modal', handler);
}


// Показ HTML в модалке #fullscreenModal
function showInModal(html) {
    const modalBody = document.querySelector('#fullscreenModal .modal-body');
    if (modalBody && typeof html === 'string') {
        modalBody.innerHTML = html;
    }
    const modalEl = document.getElementById('fullscreenModal');
    if (modalEl && window.bootstrap && bootstrap.Modal) {
        const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        modal.show();
    }
}

// Загрузить HTML в <main>
function loadIntoMain(html) {
    const main = document.getElementById('main');
    if (main && typeof html === 'string') {
        main.innerHTML = html;
        emitDeviceContext(); // <-- вот т
    }
}

// Безопасное преобразование ответа fetch в JSON с логированием сырого ответа
async function parseJsonResponse(response) {
    const text = await response.text();
    try {
        return JSON.parse(text);
    } catch (err) {
        console.error('Invalid JSON from core_api.php:', text);
        throw err;
    }
}

// =========================
// Главный обработчик кликов по .js-core-link
// =========================

document.addEventListener('click', function (e) {
    const link = e.target.closest('.js-core-link[data-core-action]');
    if (!link) return;

    e.preventDefault();

    const action = link.getAttribute('data-core-action');
    if (!action) return;

    // Подсветка пункта меню слева — только если клик из <ul> сайдбара
    const ul = link.closest('ul');
    if (ul) {
        ul.querySelectorAll('.active').forEach(el => el.classList.remove('active'));
        link.classList.add('active');
    }

    let formData;

    // -------------------------
    // Сбор данных для разных action'ов
    // -------------------------

    if (action === 'save_user') {
        // Сохранение профиля пользователя (создание/редакт)
        const userForm = document.getElementById('user-profile-form');
        if (userForm) {
            formData = new FormData(userForm);
        } else {
            formData = new FormData();
        }
    } else if (action === 'save_tool') {
        // Сохранение оборотного инструмента
        const toolForm = document.getElementById('tool-profile-form');
        if (toolForm) {
            formData = new FormData(toolForm);
        } else {
            formData = new FormData();
        }

    } else if (action === 'save_device') {
        // Сохранение устройства
        const devForm = document.getElementById('device-profile-form');
        if (devForm) {
            formData = new FormData(devForm);
        } else {
            formData = new FormData();
        }

    } else if (action === 'add_new_cells') {
        // Добавление диапазона ячеек
        // Берём форму вокруг кнопки или форму с id="cells-form"
        //const cellsForm = link.closest('form') || document.getElementById('cells-form');
        const cellsForm = document.getElementById('cells-form');
        if (cellsForm) {
            formData = new FormData(cellsForm);
        } else {
            formData = new FormData();
        }
    } else if (action === 'delete_cell') {
        formData = new FormData();
        const cellId = link.getAttribute('data-cell-id');
        if (cellId) {
            formData.append('cell_id', cellId);
        }
    } else if (action === 'form_edit_user') {
        // Открытие профиля конкретного пользователя
        formData = new FormData();
        const userId = link.getAttribute('data-user-id');
        if (userId) {
            formData.append('user_id', userId);
        }

    } else if (action === 'form_new_user') {
        // Форма нового пользователя
        formData = new FormData();

    } else if (action === 'form_edit_device') {
        // Открытие карточки устройства
        formData = new FormData();
        const deviceId = link.getAttribute('data-device-id');
        if (deviceId) {
            formData.append('device_id', deviceId);
        }

    } else if (action === 'activate_device') {
        // Активация/деактивация устройства
        formData = new FormData();
        formData.append('device_id', link.getAttribute('data-device-id') || '');
        formData.append('is_active', link.getAttribute('data-is-active') || '1');

    } else if (action === 'add_new_item_in') {
        //const f = document.getElementById('warehouse-item_in-form');
        const form = document.getElementById('item-in-modal-form'); // форма в модалке
        if (form) {
            formData = new FormData(form);
        } else {
            formData = new FormData();
        }
    } else if (action === 'commit_item_in_batch') {
        formData = new FormData();
        formData.append('batch_uid', link.getAttribute('data-batch-uid') || '');

    } else if (action === 'open_item_in_batch') {
        formData = new FormData();
        formData.append('batch_uid', link.getAttribute('data-batch-uid') || '');

    } else if (action === 'form_edit_tool_stock') {
        formData = new FormData();
        const toolId = link.getAttribute('data-tool-id');
        if (toolId) {
            formData.append('tool_id', toolId);
        }

     }else {
        // Все остальные действия, которым не нужны дополнительные поля
        formData = new FormData();
    }

    // Добавляем action
    formData.append('action', action);

    // Отладка: что реально улетает
    console.log('FormData entries:');
    for (const [k, v] of formData.entries()) {
        console.log(k, '=>', v);
    }

    // -------------------------
    // Запрос к core_api.php
    // -------------------------

    fetch('/core_api.php', {
        method: 'POST',
        body: formData
    })
        ///.then(r => r.json())
        .then(parseJsonResponse)
        .then(data => {
            if (!data || data.status !== 'ok') {
                console.error('core_api error:', data);
                alert(data && data.message ? data.message : 'Ошибка при выполнении запроса');
                return;
            }

            // -------------------------
            // Спец. обработчики по action
            // -------------------------

            // Регенерация QR по пользователям
            if (action === 'users_regen_qr') {
                alert(data.message || 'QR-коды обновлены');
                return;
            }

            // Формы в модалке (пользователь / устройство)
            if (action === 'form_new_user' ||
                action === 'form_edit_user' ||
                action === 'form_edit_device' ||
                action === 'form_new_tool_stock' ||
                action === 'form_edit_tool_stock') {
                showInModal(data.html);
                return;
            }

            // Активация/деактивация устройства
            if (action === 'activate_device') {
                alert(data.message || 'OK');

                const fd = new FormData();
                fd.append('action', 'view_devices');

                fetch('/core_api.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(d2 => {
                        if (d2 && d2.status === 'ok') {
                            loadIntoMain(d2.html);
                        }
                    })
                    .catch(err => {
                        console.error('core_api fetch error (view_devices after activate):', err);
                    });

                return;
            }

            // Сохранение устройства
            if (action === 'save_device') {
                alert(data.message || 'Сохранено');

                // Закрыть модалку
                const modalEl = document.getElementById('fullscreenModal');
                if (modalEl && window.bootstrap && bootstrap.Modal) {
                    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                    modal.hide();
                }

                // Обновить список устройств
                const fd = new FormData();
                fd.append('action', 'view_devices');

                fetch('/core_api.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(d2 => {
                        if (d2 && d2.status === 'ok') {
                            loadIntoMain(d2.html);
                        }
                    })
                    .catch(err => {
                        console.error('core_api fetch error (view_devices after save_device):', err);
                    });

                return;
            }

            // Сохранение пользователя (create/update/delete)
            if (action === 'save_user') {

                // Вариант: пользователь удалён
                if (data.deleted) {
                    alert(data.message || 'Пользователь удалён');

                    // Обновить список после закрытия модалки
                    //////setReloadOnModalCloseOnce();
                    setReloadOnModalCloseOnce(reloadUserList);

                    // Закрыть модалку
                    const modalEl = document.getElementById('fullscreenModal');
                    if (modalEl && window.bootstrap && bootstrap.Modal) {
                        const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                        modal.hide();
                    } else {
                        // Если модалка не сработала — обновим сразу
                        reloadUserList();
                    }
                    return;
                }

                // Обычное сохранение
                alert(data.message || 'Сохранено');

                const newUserId = data.user_id || null;

                // Список пользователей обновится при закрытии модалки
                ////setReloadOnModalCloseOnce();
                setReloadOnModalCloseOnce(reloadUserList);

                if (newUserId) {
                    // Перечитать профиль только что сохранённого пользователя
                    const fd = new FormData();
                    fd.append('action', 'form_edit_user');
                    fd.append('user_id', newUserId);

                    fetch('/core_api.php', {
                        method: 'POST',
                        body: fd
                    })
                        .then(r => r.json())
                        .then(d2 => {
                            if (!d2 || d2.status !== 'ok') {
                                console.error('core_api error (form_edit_user after save):', d2);
                                return;
                            }
                            showInModal(d2.html);
                        })
                        .catch(err => {
                            console.error('core_api fetch error (form_edit_user after save):', err);
                        });
                }

                // Модалка остаётся открытой, список обновится при её закрытии
                return;
            }

            if (action === 'save_tool') {
                alert(data.message || 'Сохранено');

                const newToolId = data.tool_id || null;

                setReloadOnModalCloseOnce(reloadToolsStock);

                if (newToolId) {
                    const fd = new FormData();
                    fd.append('action', 'form_edit_tool_stock');
                    fd.append('tool_id', newToolId);

                    fetch('/core_api.php', {
                        method: 'POST',
                        body: fd
                    })
                        .then(r => r.json())
                        .then(d2 => {
                            if (!d2 || d2.status !== 'ok') {
                                console.error('core_api error (form_edit_tool_stock after save):', d2);
                                return;
                            }
                            showInModal(d2.html);
                        })
                        .catch(err => {
                            console.error('core_api fetch error (form_edit_tool_stock after save):', err);
                        });
                }

                return;
            }

            // Добавление диапазона ячеек
            if (action === 'add_new_cells') {
                alert(data.message || 'Ячейки добавлены');
                if (data.html) {
                    loadIntoMain(data.html);
                }
                return;
            }
            // === Удаление ячейки склада ===
            if (action === 'delete_cell') {
                alert(data.message || 'Ячейка удалена');

                const main = document.getElementById('main');
                if (main && data.html) {
                    main.innerHTML = data.html; // прилетит уже обновлённая страница с формой + таблицей
                }
                return;
            }
                // === Открыть партию прихода в модалке ===
    if (action === 'open_item_in_batch') {
        const modalBody = document.querySelector('#fullscreenModal .modal-body');
        if (modalBody && data.html) {
            modalBody.innerHTML = data.html;
        }
        const modalEl = document.getElementById('fullscreenModal');
        if (modalEl && window.bootstrap && bootstrap.Modal) {
            const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            modal.show();
        }
        // после закрытия модалки один раз обновляем список приходов
        setReloadOnModalCloseOnce(reloadWarehouseItemIn);
        return;
    }

    // === Добавление посылки в партию ===
    if (action === 'add_new_item_in') {
        alert(data.message || 'Посылка добавлена');

        const batchUid = data.batch_uid || '';
        if (batchUid) {
            // Перечитать содержимое партии в модалке
            const fd2 = new FormData();
            fd2.append('action', 'open_item_in_batch');
            fd2.append('batch_uid', batchUid);


            fetch('/core_api.php', {
                method: 'POST',
                body: fd2
            })
                .then(r => r.json())
                .then(d2 => {
                    if (!d2 || d2.status !== 'ok') {
                        console.error('core_api error (open_item_in_batch after add):', d2);
                        return;
                    }
                    const modalBody = document.querySelector('#fullscreenModal .modal-body');
                    if (modalBody && d2.html) {
                        modalBody.innerHTML = d2.html;
                    }
                })
                .catch(err => {
                    console.error('core_api fetch error (open_item_in_batch after add):', err);
                });
        }

        return;
    }

    // === Завершение партии прихода ===
    if (action === 'commit_item_in_batch') {
        alert(data.message || 'Партия завершена');

        // Закрываем модалку
        const modalEl = document.getElementById('fullscreenModal');
        if (modalEl && window.bootstrap && bootstrap.Modal) {
            const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            modal.hide();
        }

        // Перечитать список незавершённых партий
        const fd = new FormData();
        fd.append('action', 'warehouse_item_in'); // имя action для списка приходов

        fetch('/core_api.php', {
            method: 'POST',
            body: fd
        })
            .then(r => r.json())
            .then(d2 => {
                if (!d2 || d2.status !== 'ok') {
                    console.error('core_api error (warehouse_item_in after commit):', d2);
                    return;
                }
                if (d2.html) {
                    const main = document.getElementById('main');
                    if (main) {
                        main.innerHTML = d2.html;
                    }
                }
            })
            .catch(err => {
                console.error('core_api fetch error (warehouse_item_in after commit):', err);
            });

        return;
    }

            // -------------------------
            // Все прочие actions — просто рисуем HTML в <main>
            // (view_users, view_devices, setting_cells и т.п.)
            // -------------------------

            if (data.html) {
                loadIntoMain(data.html);
            }
        })
        .catch(err => {
            console.error('core_api fetch error:', err);
            alert('Ошибка связи с сервером');
            
            // Даже если парсинг ответа упал, после коммита перечитаем список приходов
            if (action === 'commit_item_in_batch') {
                reloadWarehouseItemIn();
            }
        });
        
});

