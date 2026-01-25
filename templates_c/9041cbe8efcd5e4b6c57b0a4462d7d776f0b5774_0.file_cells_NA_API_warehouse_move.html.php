<?php
/* Smarty version 5.3.1, created on 2026-01-25 15:14:58
  from 'file:cells_NA_API_warehouse_move.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_69763372da6c95_97516466',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '9041cbe8efcd5e4b6c57b0a4462d7d776f0b5774' => 
    array (
      0 => 'cells_NA_API_warehouse_move.html',
      1 => 1769353463,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69763372da6c95_97516466 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?>    <div class="pagetitle">
      <h1>Warehouse Move</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.html">Main</a></li>
          <li class="breadcrumb-item">Warehouse</li>
          <li class="breadcrumb-item active">Перемещение</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section">
      <div class="row">
        <div class="col-lg-6">
          <div class="card table-responsive warehouse-move-wrapper">
            <div class="card-body">
              <h5 class="card-title">Перемещение</h5>

              <ul class="nav nav-tabs d-flex" id="warehouseMoveTabs" role="tablist">
                <li class="nav-item flex-fill" role="presentation">
                  <button class="nav-link w-100 active" id="warehouse-move-scanner-tab" data-bs-toggle="tab" data-bs-target="#warehouse-move-scanner" type="button" role="tab" aria-controls="warehouse-move-scanner" aria-selected="true">
                    Сканнер перемещение
                  </button>
                </li>
                <li class="nav-item flex-fill" role="presentation">
                  <button class="nav-link w-100" id="warehouse-move-batch-tab" data-bs-toggle="tab" data-bs-target="#warehouse-move-batch" type="button" role="tab" aria-controls="warehouse-move-batch" aria-selected="false" tabindex="-1">
                    Пакетное перемещение
                  </button>
                </li>
              </ul>

              <div class="tab-content pt-3" id="warehouseMoveTabsContent">
                <div class="tab-pane fade show active" id="warehouse-move-scanner" role="tabpanel" aria-labelledby="warehouse-move-scanner-tab">
                  <p class="text-muted mb-1">Введите или отсканируйте TUID/трек-номер для поиска.</p>
                  <small class="text-muted">Цель: присвоение новых значений <code>warehouse_item_stock.cell_id</code> для товаров на складе.</small>


                  <div class="row g-2 align-items-end mt-3">
                    <div class="col-12 col-md-8">
                      <label class="form-label small mb-1" for="warehouse-move-search">Поиск</label>
                      <input type="text" id="warehouse-move-search" class="form-control form-control-sm" placeholder="TUID или трек-номер">
                    </div>
                  </div>
                  <!-- Debug status indicator for device testing -->
                  <div id="scanner-debug-status" style="display:none; margin-top:10px; padding:8px; border-radius:4px; font-size:12px; background:#f8f9fa; border:1px solid #dee2e6;">
                    <strong>Debug:</strong> <span id="scanner-debug-text"></span>
                  </div>

                  <p class="small text-muted mb-2 mt-3">
                    Найдено: <span id="warehouse-move-total">0</span>
                  </p>

                  <div class="table-responsive">
                    <table class="table table-sm align-middle users-table">
                      <thead>
                        <tr>
                          <th scope="col">Посылка</th>
                          <th scope="col">Источник</th>
                          <th scope="col">Ячейка</th>
                          <?php if ($_smarty_tpl->getValue('current_user')['role'] == 'ADMIN') {?>
                            <th scope="col">Пользователь</th>
                          <?php }?>
                          <th scope="col">Дата</th>
                        </tr>
                      </thead>
                      <tbody id="warehouse-move-results-tbody"></tbody>
                    </table>
                  </div>
                </div>
                <div class="tab-pane fade" id="warehouse-move-batch" role="tabpanel" aria-labelledby="warehouse-move-batch-tab">
                  <p class="text-muted mb-1">Выберите ячейку и добавляйте посылки по трек-номеру.</p>
                  <small class="text-muted">Цель: пакетное присвоение новых значений <code>warehouse_item_stock.cell_id</code>.</small>

                  <div class="row g-2 align-items-end mt-3">
                    <div class="col-12 col-md-5">
                      <label class="form-label small mb-1" for="warehouse-move-batch-cell">Ячейка склада</label>
                      <select class="form-select form-select-sm" id="warehouse-move-batch-cell">
                        <option value="">— выберите ячейку —</option>
                        <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('cells'), 'cell');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('cell')->value) {
$foreach0DoElse = false;
?>
                          <option value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('cell')['id'], ENT_QUOTES, 'UTF-8', true);?>
"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('cell')['code'], ENT_QUOTES, 'UTF-8', true);?>
</option>
                        <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                      </select>
                    </div>
                    <div class="col-12 col-md-7">
                      <label class="form-label small mb-1" for="warehouse-move-batch-search">Трек-номер</label>
                      <input type="text" id="warehouse-move-batch-search" class="form-control form-control-sm" placeholder="TUID или трек-номер">
                    </div>
                  </div>

                  <p class="small text-muted mb-2 mt-3">
                    Найдено: <span id="warehouse-move-batch-total">0</span>
                  </p>

                  <div class="table-responsive">
                    <table class="table table-sm align-middle users-table">
                      <thead>
                        <tr>
                          <th scope="col">Посылка</th>
                          <th scope="col">Источник</th>
                          <th scope="col">Ячейка</th>
                          <th scope="col">Переместить</th>
                          <?php if ($_smarty_tpl->getValue('current_user')['role'] == 'ADMIN') {?>
                            <th scope="col">Пользователь</th>
                          <?php }?>
                          <th scope="col">Дата</th>
                        </tr>
                      </thead>
                      <tbody id="warehouse-move-batch-results-tbody"></tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>


<?php echo '<script'; ?>
>
// ============================================================================
// JavaScript функции для работы с перемещением через сканер
// ============================================================================
// Вспомогательная функция для отображения отладочных сообщений на устройстве
function showDebug(message, isError = false) {
  console.log(message);
  const debugDiv = document.getElementById('scanner-debug-status');
  const debugText = document.getElementById('scanner-debug-text');
  if (debugDiv && debugText) {
    debugDiv.style.display = 'block';
    debugDiv.style.background = isError ? '#fff3cd' : '#d1ecf1';
    debugDiv.style.borderColor = isError ? '#ffc107' : '#0dcaf0';
    debugText.textContent = message;
    
    // Автоматически скрываем через 5 секунд для неошибочных сообщений
    if (!isError) {
      setTimeout(() => {
        debugDiv.style.display = 'none';
      }, 5000);
    }
  }
}

/**
 * Открывает модальное окно для перемещения товара
 * Вызывается после сканирования трека, если найдена ровно 1 запись
 */
window.openMoveModal = function() {
  showDebug('📦 openMoveModal: начало');
    
  const searchInput = document.getElementById('warehouse-move-search');
  const searchValue = searchInput?.value?.trim();
  
  if (!searchValue) {
    showDebug('❌ Нет значения для поиска', true);
    return false;
  }
  
  showDebug('🔍 Ищем записи для: ' + searchValue);
    
  // Проверяем количество записей в таблице результатов
  const tbody = document.getElementById('warehouse-move-results-tbody');
  const rows = tbody?.querySelectorAll('tr:not(.no-results)');
  
  if (!rows || rows.length === 0) {
    showDebug('❌ Записей не найдено', true);
    return false;
  }
  
  if (rows.length !== 1) {
    showDebug('❌ Найдено записей: ' + rows.length + ' (нужна ровно 1)', true);
    return false;
  }
  
  showDebug('✓ Найдена 1 запись, открываем модалку');
    
    // Получаем кнопку для открытия модалки из первой строки
  const firstRow = rows[0];
  const button = firstRow.querySelector('button[data-core-action="warehouse_move_open_modal"]');
  
  if (!button) {
    showDebug('❌ Не найдена кнопка открытия', true);
    return false;
  }

  showDebug('✓ Модалка должна открыться');
  button.click();

  return true;
};
/**
 * Устанавливает значение ячейки из отсканированного QR кода
 * Вызывается из приложения при сканировании QR в модалке
 * 
 * @param {string} qrValue - Значение QR кода (формат: "CELL:D1" или просто "D1")
 * @returns {boolean} true если ячейка найдена и установлена, иначе false
 */
window.setCellFromQR = function(qrValue) {
  showDebug('📱 setCellFromQR: ' + qrValue);
    
  // Парсим QR: ожидаем формат "CELL:D1" или просто "D1"
  let cellCode = null;
  
  if (qrValue.toUpperCase().startsWith('CELL:')) {
    cellCode = qrValue.substring(5).trim();
    console.log('✓ Извлечён код ячейки из формата CELL:', cellCode);
  } else {
    cellCode = qrValue.trim();
    showDebug('✓ Код ячейки: ' + cellCode);
  }
  
  if (!cellCode) {
    showDebug('❌ Не удалось извлечь код ячейки', true);
    return false;
  }
  
  // Функция для попытки установки значения
  const trySetCell = (retryCount = 0) => {
    showDebug('🔄 Попытка ' + (retryCount + 1) + ' установить ячейку');

    // Ищем select в модалке
    const cellSelect = document.getElementById('cellId');
    if (!cellSelect) {
      showDebug('❌ Select #cellId не найден', true);
            
      // Если модалка ещё не загружена, повторяем через 300ms (максимум 5 попыток)
      if (retryCount < 5) {
        setTimeout(() => trySetCell(retryCount + 1), 300);
        return false;
      }
      showDebug('❌ Превышено кол-во попыток', true);
      return false;
    }
    
    showDebug('🔍 Ищем ячейку: ' + cellCode);
        
    // Ищем option с нужным кодом ячейки (case-insensitive)
    let foundOption = null;
    const upperCellCode = cellCode.toUpperCase();
    
    for (let option of cellSelect.options) {
      const optionText = option.text.trim().toUpperCase();
      if (optionText === upperCellCode) {
        foundOption = option;
        showDebug('✓ Найдена: ' + option.text);
        break;
      }
    }
    
    if (!foundOption) {
      showDebug('❌ Ячейка ' + cellCode + ' не найдена', true);
      return false;
    }

    
    // Устанавливаем значение
    cellSelect.value = foundOption.value;
    cellSelect.dispatchEvent(new Event('change', { bubbles: true }));
    cellSelect.dispatchEvent(new Event('input', { bubbles: true }));
      
    showDebug('✅ Ячейка установлена: ' + foundOption.text);
  foundOption.text);
  return true;
    // Запускаем попытку установки
  return trySetCell();
};
/**
 * Сохраняет перемещение и закрывает модальное окно
 * Вызывается при двойном нажатии Vol Down после выбора ячейки
 */
window.saveMoveAndClose = function() {
  showDebug('💾 saveMoveAndClose: начало');
    
  // Ищем кнопку сохранения в модалке
  const saveBtn = document.querySelector('button.js-core-link[data-core-action="warehouse_move_save_cell"]');
  
  if (!saveBtn) {
    showDebug('❌ Кнопка сохранения не найдена', true);
    return false;
  }
  
  console.log('✓ Найдена кнопка сохранения, проверяем состояние');
  console.log('Кнопка disabled:', saveBtn.disabled);
  console.log('Кнопка видна:', saveBtn.offsetParent !== null);
  
  console.log('✓ Нажимаем кнопку сохранения');
  showDebug('✓ Нажимаем кнопку сохранения');
  saveBtn.click();
  
  // Закрываем модалку через небольшую задержку (чтобы сохранение успело выполниться)
  setTimeout(() => {
    console.log('🚪 Закрываем модалку');
    showDebug('🚪 Закрываем модалку');
    const modal = document.querySelector('.modal.show');
    if (modal) {
      const closeBtn = modal.querySelector('.btn-close, [data-bs-dismiss="modal"]');
      if (closeBtn) {
        closeBtn.click();
        console.log('✓ Модалка закрыта');
      } else {
        console.log('❌ Не найдена кнопка закрытия модалки');
      }
    } else {
      console.log('⚠️ Модалка уже закрыта или не найдена');
    }
  }, 500);
  
  return true;
};
// Вспомогательная функция: очистка поля поиска
window.clear_search = function() {
  const searchInput = document.getElementById('warehouse-move-search');
  if (searchInput) {
    searchInput.value = '';
    searchInput.dispatchEvent(new Event('input', { bubbles: true }));
    console.log('✓ Поле поиска очищено');
  }
};
// Вспомогательная функция: сброс формы
window.reset_form = function() {
  window.clear_search();
  const tbody = document.getElementById('warehouse-move-results-tbody');
  if (tbody) tbody.innerHTML = '';
  console.log('✓ Форма сброшена');
};
console.log('✓ Функции для warehouse move загружены');
<?php echo '</script'; ?>
>


<?php echo '<script'; ?>
 id="device-scan-config" type="application/json">
{
  "task_id":"warehouse_move",
  "default_mode":"barcode",
  "modes":["barcode","qr"],

  "buttons":{
    "vol_down_single":"scan",
    "vol_down_double":"confirm",
    "vol_up_single":"clear",
    "vol_up_double":"reset"
  },

  "api": {
    "move_apply": "/api/warehouse_move_apply.php"
  },

  "contexts": {
    "scanner": {
      "active_tab_selector": "#warehouse-move-scanner-tab.nav-link.active",
      
      "barcode": { 
        "action": "fill_field",
        "field_id": "warehouse-move-search"
      },
      "qr": {
        "action": "api_check",
        "endpoint": "/api/qr_check.php"
      },

      "flow": {
        "start": "scan_parcel",
        "steps": {
          "scan_parcel": {
            "mode": "barcode",
            "next_on_scan": "wait_for_confirm",
            "barcode": {
              "action": "fill_field",
              "field_id": "warehouse-move-search"
            },
            "on_action": {
              "scan": [{"op": "open_scanner", "mode": "barcode"}],
              "confirm": [
                {"op": "web", "name": "openMoveModal"}, 
                {"op": "set_step", "to": "scan_cell_in_modal"}
              ],
              "clear": [{"op": "web", "name": "clear_search"}],
              "reset": [{"op": "web", "name": "reset_form"}, {"op": "set_step", "to": "scan_parcel"}]
            }
          },

          "wait_for_confirm": {
            "mode": "none",
            "on_action": {
              "scan": [{"op": "noop"}],
              "confirm": [
                {"op": "web", "name": "openMoveModal"}, 
                {"op": "set_step", "to": "scan_cell_in_modal"}
              ],
              "clear": [{"op": "web", "name": "clear_search"}, {"op": "set_step", "to": "scan_parcel"}],
              "reset": [{"op": "web", "name": "reset_form"}, {"op": "set_step", "to": "scan_parcel"}]
            }
          },
"scan_cell_in_modal": {
  "mode": "qr",
  "next_on_scan": "wait_for_save",
  "qr": {
    "action": "web_callback",
    "callback": "setCellFromQR"
  },
  "on_action": {
    "scan": [{"op": "open_scanner", "mode": "qr"}],
    "confirm": [
      {"op": "click_button", "selector": "button[data-core-action='warehouse_move_save_cell']"},
      {"op": "delay", "ms": 500},
      {"op": "click_button", "selector": ".modal.show .btn-close"},
      {"op": "set_step", "to": "scan_parcel"}
    ],
    "clear": [{"op": "set_step", "to": "scan_cell_in_modal"}],
    "reset": [{"op": "web", "name": "reset_form"}, {"op": "set_step", "to": "scan_parcel"}]
  }
},
          "wait_for_save": {
            "mode": "none",
            "on_action": {
              "scan": [{"op": "noop"}],
              "confirm": [
                {"op": "web", "name": "saveMoveAndClose"}, 
                {"op": "set_step", "to": "scan_parcel"}
              ],
              "clear": [{"op": "set_step", "to": "scan_cell_in_modal"}],
              "reset": [{"op": "web", "name": "reset_form"}, {"op": "set_step", "to": "scan_parcel"}]
            }
          }
        }
      }
    },

    "batch": {
      "active_tab_selector": "#warehouse-move-batch-tab.nav-link.active",
      "barcode": { 
        "action":"fill_field", 
        "field_id":"warehouse-move-batch-search"
      },
      "qr": {
        "action":"api_check",
        "endpoint":"/api/qr_check.php",
        "apply_to_select_id":"warehouse-move-cell-select"
      }
    }
  }
}
<?php echo '</script'; ?>
>

<!-- ВРЕМЕННАЯ КНОПКА ДЛЯ ТЕСТА -->
<button onclick="window.saveMoveAndClose()" style="position:fixed; top:10px; right:10px; z-index:9999; padding:20px; background:red; color:white; font-size:20px;">
  TEST DOUBLE
</button>





<div id="ocr-templates" style="display:none">
    <?php echo $_smarty_tpl->getValue('jsonOcrTemplates');?>

</div>
<div id="ocr-templates-destcountry" style="display:none">
    <?php echo $_smarty_tpl->getValue('jsonDestCountry');?>

</div>

<div id="ocr-dicts" style="display:none">
    <?php echo $_smarty_tpl->getValue('jsonOcrDicts');?>

</div>

    <!-- Full Screen Modal -->
    <div class="modal fade" id="fullscreenModal" tabindex="-1">
      <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">

          <div class="modal-header">
            <h5 class="modal-title">Просмотр посылки</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            Загрузка...
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div><!-- End Full Screen Modal-->
<?php }
}
